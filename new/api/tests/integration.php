<?php

declare(strict_types=1);

/**
 * HTTP integration pass for the docs/modernization-spec.md §4 parity
 * checklist — exercises the real API over HTTP (login/session/heartbeat,
 * board, players/rosters, pick/hold/preselect auto-advance, the §6
 * concurrent-pick race, and every commish flow).
 *
 * Prereqs:
 *   php bin/seed-draft.php
 *   PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8080 public/index.php
 *
 * Usage: php tests/integration.php [base-url]
 *   base-url defaults to http://127.0.0.1:8080 — point it at staging to
 *   re-run the same pass there.
 *
 * Exits non-zero if any check fails. DESTRUCTIVE for the target DB's draft
 * state (it drafts players) — never point it at production.
 */

$base = $argv[1] ?? 'http://127.0.0.1:8080';

/** Cookie-holding HTTP client — one per simulated "browser". */
final class Client
{
    private string $cookieFile;

    public function __construct(private readonly string $base, string $name)
    {
        $this->cookieFile = sys_get_temp_dir() . "/wmffl-integration-{$name}-" . getmypid() . '.cookies';
        @unlink($this->cookieFile);
    }

    /** @return array{0:int, 1:mixed} [status, decoded JSON body] */
    public function call(string $method, string $path, ?array $body = null): array
    {
        $ch = $this->handle($method, $path, $body);
        $raw = curl_exec($ch);
        if ($raw === false) {
            fwrite(STDERR, "HTTP error on {$method} {$path}: " . curl_error($ch) . "\n");
            exit(2);
        }
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [$status, json_decode((string) $raw, true)];
    }

    /** A raw handle for curl_multi (parallel race test). */
    public function handle(string $method, string $path, ?array $body = null): CurlHandle
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_TIMEOUT => 15,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        return $ch;
    }

    public function get(string $path): array
    {
        return $this->call('GET', $path);
    }

    public function post(string $path, ?array $body = null): array
    {
        return $this->call('POST', $path, $body);
    }
}

$passCount = 0;
$failCount = 0;

function check(bool $ok, string $label, mixed $detail = null): void
{
    global $passCount, $failCount;
    if ($ok) {
        $passCount++;
        echo "  ok  {$label}\n";
    } else {
        $failCount++;
        echo "FAIL  {$label}" . ($detail !== null ? ' — ' . json_encode($detail) : '') . "\n";
    }
}

function section(string $name): void
{
    echo "\n== {$name} ==\n";
}

/** @var array<int, Client> one logged-in client per team's owner */
$owners = [];
$loginOwner = function (int $team) use (&$owners, $base): Client {
    if (!isset($owners[$team])) {
        $c = new Client($base, "owner{$team}");
        [$status, $data] = $c->post('/api/auth/login', ['username' => "owner{$team}", 'password' => "pw{$team}"]);
        if ($status !== 200) {
            fwrite(STDERR, 'Login failed for owner' . $team . ': ' . json_encode($data) . "\n");
            exit(2);
        }
        $owners[$team] = $c;
    }

    return $owners[$team];
};

// ---------------------------------------------------------------- health
section('Health');
$anon = new Client($base, 'anon');
[$status, $data] = $anon->get('/api/health?db=1');
check($status === 200 && ($data['db'] ?? null) === 'ok', 'health + db connectivity', $data);

// ------------------------------------------------------------------ auth
section('Auth / session');
[$status, $data] = $anon->post('/api/auth/login', ['username' => 'owner1', 'password' => 'WRONG']);
check($status === 401, 'bad credentials rejected with 401', $data);

[$status] = $anon->get('/api/players');
check($status === 401, 'players endpoint requires login');
[$status] = $anon->get('/api/commish/status');
check($status === 401, 'commish endpoint requires login');

$o1 = $loginOwner(1);
[$status, $data] = $o1->get('/api/session');
check(
    $status === 200 && $data['isin'] === true && $data['teamId'] === 1 && $data['commish'] === false,
    'session reflects owner1 login (isin, teamId, not commish)',
    $data,
);

[$status, $data] = $o1->post('/api/session/heartbeat');
check($status === 200 && ($data['ok'] ?? false) === true, 'presence heartbeat accepted');

// ------------------------------------------------------- pre-start state
section('Pre-draft state');
[$status, $board] = $o1->get('/api/draft/board');
check($status === 200 && $board['draftStarted'] === false, 'board shows draft not started', $board['draftStarted'] ?? null);
check(($board['currentPick']['round'] ?? 0) === 1 && ($board['currentPick']['pick'] ?? 0) === 1, 'current pick is 1.1');
check(count($board['picks']) === 60, '60 picks on the board (5 rounds x 12 teams)', count($board['picks']));
check(count($board['teamClocks']) === 12, '12 team clocks', count($board['teamClocks']));
check(($board['teamClocks'][0]['abbrev'] ?? '') !== '', 'team clocks carry abbrevs');

[$status, $data] = $o1->get('/api/players');
$allPlayerCount = count($data['players'] ?? []);
check($status === 200 && $allPlayerCount === 185, 'full available-player list (185)', $allPlayerCount);

[$status, $data] = $o1->get('/api/players?pos=QB');
$qbs = $data['players'] ?? [];
check($status === 200 && count($qbs) === 20 && !array_filter($qbs, fn($p) => $p['pos'] !== 'QB'), 'pos filter returns only QBs');

$nflTeam = $qbs[0]['nflTeam'];
[$status, $data] = $o1->get("/api/players?nfl={$nflTeam}");
check(
    $status === 200 && count($data['players']) > 0 && !array_filter($data['players'], fn($p) => $p['nflTeam'] !== $nflTeam),
    "nfl filter returns only {$nflTeam} players",
);

[$status, $data] = $o1->get('/api/roster/1');
check($status === 200 && $data['players'] === [] && $data['teamName'] !== '', 'team 1 roster empty pre-draft', $data);
[$status] = $o1->get('/api/roster/999');
check($status === 404, 'unknown team roster is 404');

// Picking before the draft starts must be rejected...
$qbBest = $qbs[0]['id'];
[$status, $data] = $o1->post('/api/draft/pick', ['playerId' => $qbBest]);
check($status === 409, 'direct pick before draft start rejected with 409', $data);

// ...but queueing a preselection must work (startDraft auto-drafts them).
[$status] = $o1->post('/api/draft/hold', ['playerId' => $qbBest]);
check($status === 200, 'owner1 queues preselection before start');
$o2 = $loginOwner(2);
$rb = $o2->get('/api/players?pos=RB')[1]['players'];
[$status] = $o2->post('/api/draft/hold', ['playerId' => $rb[0]['id']]);
check($status === 200, 'owner2 queues preselection before start');

[, $board] = $o1->get('/api/draft/board');
check(($board['myHold']['playerId'] ?? null) === $qbBest, 'owner1 sees own queued hold on board', $board['myHold'] ?? null);

// -------------------------------------------------------- commish guard
section('Commish guard');
[$status] = $o1->get('/api/commish/status');
check($status === 403, 'non-commish blocked from commish status with 403');
[$status] = $o1->post('/api/commish/draft/start');
check($status === 403, 'non-commish blocked from starting draft');

$commish = new Client($base, 'commish');
[$status, $data] = $commish->post('/api/auth/login', ['username' => 'commish', 'password' => 'cpw']);
check($status === 200 && $data['commish'] === true, 'commish login has commish role', $data);

[$status, $data] = $commish->get('/api/commish/status');
$rows = $data['owners'] ?? [];
$owner1Row = array_values(array_filter($rows, fn($r) => $r['teamId'] === 1))[0] ?? null;
$owner3Row = array_values(array_filter($rows, fn($r) => $r['teamId'] === 3))[0] ?? null;
check($status === 200 && count($rows) === 12, 'presence table lists 12 owners', count($rows));
check(($owner1Row['isIn'] ?? false) === true, 'owner1 shows In after heartbeat', $owner1Row);
check(($owner3Row['isIn'] ?? true) === false, 'owner3 (never seen) shows Out', $owner3Row);
check(($owner1Row['remainingSeconds'] ?? 0) === 600, 'pre-start team clock at allowed budget (600)', $owner1Row);

// ----------------------------------------------------------- start draft
section('Start draft (auto-drafts queued preselections)');
[$status] = $commish->post('/api/commish/draft/start');
check($status === 200, 'draft started');

[, $board] = $o1->get('/api/draft/board');
check($board['draftStarted'] === true && $board['clockRunning'] === true, 'board shows started + clock running');
$filled = array_values(array_filter($board['picks'], fn($p) => $p['playerId'] !== null));
check(count($filled) === 2, 'picks 1.1 and 1.2 auto-drafted from preselect queue', count($filled));
check(($board['currentPick']['pick'] ?? 0) === 3, 'current pick advanced to 1.3', $board['currentPick'] ?? null);
check($board['myHold'] === null, 'owner1 hold cleared after auto-draft');
check(($board['lastPick']['teamName'] ?? '') === 'Bandits', 'last pick shows team 2', $board['lastPick'] ?? null);
check(is_int($board['draftStartedAt']) && $board['draftStartedAt'] > 0, 'draftStartedAt stamped');
check($board['timeRemaining'] > 0 && $board['timeRemaining'] <= 660, 'current pick clock in sane range', $board['timeRemaining']);

// ------------------------------------------------------- pick submission
section('Pick submission');
$o3 = $loginOwner(3);
$available = $o3->get('/api/players?pos=WR')[1]['players'];
$wrBest = $available[0];
[$status, $data] = $o3->post('/api/draft/pick', ['playerId' => $wrBest['id']]);
check($status === 200, 'on-the-clock team 3 submits a pick', $data);

$o5 = $loginOwner(5);
[$status, $data] = $o5->post('/api/draft/pick', ['playerId' => $available[1]['id']]);
check($status === 409, 'out-of-turn pick rejected with 409', $data);

$o4 = $loginOwner(4);
[$status, $data] = $o4->post('/api/draft/pick', ['playerId' => $wrBest['id']]);
check($status === 409, 'already-drafted player rejected with 409', $data);
[$status, $data] = $o4->post('/api/draft/pick', ['playerId' => 999999]);
check($status === 400, 'nonexistent player rejected with 400', $data);

// --------------------------------------------- hold + mid-draft advance
section('Hold / preselect auto-advance');
$o5Players = $o5->get('/api/players?pos=RB')[1]['players'];
[$status] = $o5->post('/api/draft/hold', ['playerId' => $o5Players[0]['id']]);
check($status === 200, 'on-deck team 5 queues a hold');
[$status] = $o5->call('DELETE', '/api/draft/hold');
check($status === 200, 'team 5 clears its hold');
[, $board] = $o5->get('/api/draft/board');
check($board['myHold'] === null, 'cleared hold no longer on board');
[$status] = $o5->post('/api/draft/hold', ['playerId' => $o5Players[0]['id']]);
check($status === 200, 'team 5 re-queues the hold');

$o4Players = $o4->get('/api/players?pos=DL')[1]['players'];
[$status] = $o4->post('/api/draft/pick', ['playerId' => $o4Players[0]['id']]);
check($status === 200, 'team 4 picks');
[, $board] = $o4->get('/api/draft/board');
check(($board['currentPick']['pick'] ?? 0) === 6, 'team 5\'s queued hold auto-drafted; clock moved to 1.6', $board['currentPick'] ?? null);
$pick15 = array_values(array_filter($board['picks'], fn($p) => $p['round'] === 1 && $p['pick'] === 5))[0];
check($pick15['playerId'] === $o5Players[0]['id'], 'pick 1.5 filled with team 5\'s held player');

// ------------------------------------------------- §6 concurrency race
section('Concurrent-pick race (spec §6)');
// Two "browsers" logged in as team 6's owner submit the same player at the
// same time against PHP_CLI_SERVER_WORKERS>1 — exactly one may win.
$raceA = new Client($base, 'race-a');
$raceA->post('/api/auth/login', ['username' => 'owner6', 'password' => 'pw6']);
$raceB = new Client($base, 'race-b');
$raceB->post('/api/auth/login', ['username' => 'owner6', 'password' => 'pw6']);

$target = $raceA->get('/api/players?pos=LB')[1]['players'][0]['id'];
$hA = $raceA->handle('POST', '/api/draft/pick', ['playerId' => $target]);
$hB = $raceB->handle('POST', '/api/draft/pick', ['playerId' => $target]);
$multi = curl_multi_init();
curl_multi_add_handle($multi, $hA);
curl_multi_add_handle($multi, $hB);
do {
    curl_multi_exec($multi, $running);
    curl_multi_select($multi, 0.05);
} while ($running > 0);
$statusA = curl_getinfo($hA, CURLINFO_RESPONSE_CODE);
$statusB = curl_getinfo($hB, CURLINFO_RESPONSE_CODE);
curl_multi_remove_handle($multi, $hA);
curl_multi_remove_handle($multi, $hB);
curl_multi_close($multi);

$statuses = [$statusA, $statusB];
sort($statuses);
check($statuses === [200, 409], 'simultaneous picks: exactly one 200, one 409', [$statusA, $statusB]);
[, $board] = $o1->get('/api/draft/board');
$pick16 = array_values(array_filter($board['picks'], fn($p) => $p['round'] === 1 && $p['pick'] === 6))[0];
check($pick16['playerId'] === $target, 'the winning submission filled pick 1.6 exactly once');

// ------------------------------------------------------- commish console
section('Commish clock / undo / auto-pick');
[$status] = $commish->post('/api/commish/clock/stop');
[, $board] = $commish->get('/api/draft/board');
check($status === 200 && $board['clockRunning'] === false, 'commish stops the clock');
[$status] = $commish->post('/api/commish/clock/start');
[, $board] = $commish->get('/api/draft/board');
check($status === 200 && $board['clockRunning'] === true, 'commish restarts the clock');

[$status, $data] = $commish->post('/api/commish/pick/undo');
check($status === 200 && ($data['undone']['pick'] ?? 0) === 6, 'undo reopens the last pick (1.6)', $data);
[, $board] = $commish->get('/api/draft/board');
check(($board['currentPick']['pick'] ?? 0) === 6, 'clock back on pick 1.6 after undo');
$stillAvailable = array_filter(
    $commish->get('/api/players?pos=LB')[1]['players'],
    fn($p) => $p['id'] === $target,
);
check($stillAvailable !== [], 'undone player is available again');

[$status, $data] = $commish->post('/api/commish/pick/auto', ['teamId' => 6, 'pos' => 'K']);
check(
    $status === 200 && ($data['queued'] ?? true) === false && ($data['player']['pos'] ?? '') === 'K',
    'forced auto-pick at explicit position fills on-clock team 6',
    $data,
);

[$status, $data] = $commish->post('/api/commish/pick/auto', ['teamId' => 7]);
check(
    $status === 200 && ($data['queued'] ?? true) === false && ($data['player'] ?? null) !== null,
    'heuristic auto-pick (no pos) fills on-clock team 7',
    $data,
);

[$status, $data] = $commish->post('/api/commish/pick/auto', ['teamId' => 9]);
check($status === 200 && ($data['queued'] ?? false) === true, 'auto-pick for off-clock team 9 queues a hold', $data);

$o8 = $loginOwner(8);
$o8Players = $o8->get('/api/players?pos=DB')[1]['players'];
[$status] = $o8->post('/api/draft/pick', ['playerId' => $o8Players[0]['id']]);
[, $board] = $o8->get('/api/draft/board');
check(
    $status === 200 && ($board['currentPick']['pick'] ?? 0) === 10,
    'team 8 pick auto-advances through team 9\'s queued auto-pick to 1.10',
    $board['currentPick'] ?? null,
);

// ---------------------------------------------------------- roster view
section('Rosters after picks');
[$status, $data] = $o1->get('/api/roster/6');
$rosterPlayers = $data['players'] ?? [];
check(
    $status === 200 && count($rosterPlayers) === 1 && $rosterPlayers[0]['pos'] === 'K',
    'team 6 roster shows exactly the re-picked K (race pick was undone)',
    $rosterPlayers,
);
check(
    $rosterPlayers !== [] && $rosterPlayers[0]['nflTeam'] !== null && $rosterPlayers[0]['byeWeek'] !== null,
    'roster rows include NFL team and bye week',
    $rosterPlayers[0] ?? null,
);

[, $board] = $o1->get('/api/draft/board');
$team1Clock = array_values(array_filter($board['teamClocks'], fn($t) => $t['teamId'] === 1))[0];
check($team1Clock['seconds'] <= 660, 'team clocks reflect adjustClock deductions', $team1Clock);

// -------------------------------------------------------------- logout
section('Logout');
[$status] = $o1->post('/api/auth/logout');
[, $data] = $o1->get('/api/session');
check($status === 200 && $data['isin'] === false, 'logout clears the session');

// -------------------------------------------------------------- summary
echo "\n{$passCount} passed, {$failCount} failed\n";
exit($failCount === 0 ? 0 : 1);
