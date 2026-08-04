<?php

declare(strict_types=1);

/**
 * Seeds the local test database (wmffl_test, per config/db.ini) with a
 * realistic full draft scenario for the integration pass
 * (docs/modernization-spec.md §9, Aug 19-22): 12 teams, 12 owners + a
 * commissioner, 5 snake rounds, ~185 players with prior-season scores
 * (for the autopick heuristic), NFL rosters and byes.
 *
 * Usage: php bin/seed-draft.php   (DESTRUCTIVE: reloads tests/schema.sql)
 *
 * Owner logins: owner1..owner12 / pw1..pw12 (team N = ownerN).
 * Commish login: commish / cpw.
 */

use App\Db;

require_once __DIR__ . '/../vendor/autoload.php';

const SEASON = 2026;
const ROUNDS = 5;
const TEAMS = 12;

$pdo = Db::connection();

// Recreate the schema so the seed is deterministic.
$pdo->exec(file_get_contents(__DIR__ . '/../tests/schema.sql'));

$teamNames = [
    1 => ['Aces', 'ACE'], 2 => ['Bandits', 'BAN'], 3 => ['Crushers', 'CRU'],
    4 => ['Dynamos', 'DYN'], 5 => ['Enforcers', 'ENF'], 6 => ['Flyers', 'FLY'],
    7 => ['Grinders', 'GRI'], 8 => ['Hawks', 'HWK'], 9 => ['Icemen', 'ICE'],
    10 => ['Jackals', 'JAK'], 11 => ['Krakens', 'KRA'], 12 => ['Lancers', 'LAN'],
];

$insTeam = $pdo->prepare('INSERT INTO teamnames (teamid, season, name, abbrev) VALUES (?, ?, ?, ?)');
$insUser = $pdo->prepare(
    'INSERT INTO user (UserID, TeamID, Username, Password, Name, active, commish)
     VALUES (?, ?, ?, MD5(?), ?, ?, ?)'
);
$insOwner = $pdo->prepare('INSERT INTO owners (teamid, userid, season, `primary`) VALUES (?, ?, ?, 1)');

foreach ($teamNames as $teamId => [$name, $abbrev]) {
    $insTeam->execute([$teamId, SEASON, $name, $abbrev]);
    $insUser->execute([$teamId, $teamId, "owner{$teamId}", "pw{$teamId}", "Owner {$teamId}", 'Y', 0]);
    $insOwner->execute([$teamId, $teamId, SEASON]);
}
$insUser->execute([99, null, 'commish', 'cpw', 'The Commish', 'Y', 1]);

// Season window covering "now" so WeekmapRepository::currentSeason() resolves.
$pdo->prepare(
    'INSERT INTO weekmap (Season, Week, StartDate, EndDate, DisplayDate, weekname)
     VALUES (?, 1, DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_ADD(NOW(), INTERVAL 60 DAY), NOW(), ?)'
)->execute([SEASON, 'Draft']);

// Draft clock config. draft.team.<id> keys must pre-exist — startDraft's
// reset scans that prefix, mirroring the legacy UPDATE ... LIKE 'draft.team.%'.
$insConf = $pdo->prepare('INSERT INTO config (`key`, `value`) VALUES (?, ?)');
$insConf->execute(['draft.clock.allowed', '600']);
$insConf->execute(['draft.clock.maxTime', '900']);
$insConf->execute(['draft.clock.addTime', '60']);
$insConf->execute(['draft.clock.run', 'false']);
$insConf->execute(['draft.start', 'false']);
foreach (array_keys($teamNames) as $teamId) {
    $insConf->execute(["draft.team.{$teamId}", '600']);
}

// Snake draft order.
$insPick = $pdo->prepare(
    'INSERT INTO draftpicks (Season, Round, Pick, teamid, playerid) VALUES (?, ?, ?, ?, NULL)'
);
for ($round = 1; $round <= ROUNDS; $round++) {
    for ($pick = 1; $pick <= TEAMS; $pick++) {
        $teamId = $round % 2 === 1 ? $pick : TEAMS + 1 - $pick;
        $insPick->execute([SEASON, $round, $pick, $teamId]);
    }
}

// Players: enough at each position for 5 rounds x 12 teams, with strictly
// decreasing prior-season scores inside each position so the autopick
// heuristic's "best available" is deterministic.
$nfl = [
    'ARI', 'ATL', 'BAL', 'BUF', 'CAR', 'CHI', 'CIN', 'CLE', 'DAL', 'DEN', 'DET',
    'GB', 'HOU', 'IND', 'JAC', 'KC', 'LAC', 'LAR', 'LV', 'MIA', 'MIN', 'NE',
    'NO', 'NYG', 'NYJ', 'PHI', 'PIT', 'SEA', 'SF', 'TB', 'TEN', 'WAS',
];
$positionCounts = [
    'QB' => 20, 'RB' => 30, 'WR' => 30, 'TE' => 15, 'K' => 15,
    'OL' => 15, 'DL' => 20, 'LB' => 20, 'DB' => 20,
];

$insPlayer = $pdo->prepare(
    'INSERT INTO newplayers (playerid, flmid, lastname, firstname, pos, usePos) VALUES (?, ?, ?, ?, ?, 1)'
);
$insNflRoster = $pdo->prepare(
    "INSERT INTO nflrosters (playerid, nflteamid, dateon, dateoff, pos) VALUES (?, ?, '2026-03-01', NULL, ?)"
);
$insScore = $pdo->prepare(
    'INSERT INTO playerscores (playerid, season, week, pts, active) VALUES (?, ?, ?, ?, 1)'
);

$playerId = 1000;
$nflIndex = 0;
foreach ($positionCounts as $pos => $count) {
    for ($n = 1; $n <= $count; $n++) {
        $playerId++;
        $team = $nfl[$nflIndex % count($nfl)];
        $nflIndex++;

        $insPlayer->execute([$playerId, $playerId, "{$pos}man{$n}", "Player", $pos]);
        $insNflRoster->execute([$playerId, $team, $pos]);

        $weeklyPts = max(1, $count - $n + 1);
        for ($week = 1; $week <= 14; $week++) {
            $insScore->execute([$playerId, SEASON - 1, $week, $weeklyPts]);
        }
    }
}

// Bye weeks (season 2026) so the roster viewer's bye column has data.
$insBye = $pdo->prepare('INSERT INTO nflbyes (season, week, nflteam) VALUES (?, ?, ?)');
foreach ($nfl as $i => $team) {
    $insBye->execute([SEASON, 5 + ($i % 10), $team]);
}

$playerCount = $playerId - 1000;
echo "Seeded season " . SEASON . ": " . TEAMS . " teams, " . ROUNDS . " snake rounds, {$playerCount} players.\n";
echo "Logins: owner1..owner" . TEAMS . " (pw1..pw" . TEAMS . "), commish (cpw).\n";
