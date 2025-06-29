<?
require_once "utils/start.php";


require "DataObjects/Draftpicks.php";
require "DataObjects/Config.php";
require "clock.class.php";

$draftPicks = new DataObjects_Draftpicks;
//$draftPicks->Season = 2015;
$draftPicks->Season = $currentSeason;
$draftPicks->orderBy("Round");
$draftPicks->orderBy("Pick");
$draftPicks->find();

$config = new DataObjects_Config;


// Get the time the clock started
$config->key = "draft.clock.start";
$config->find(1);
$startClock = $config->value;

// Get the time the draft actually started
$config = new DataObjects_Config;
$config->key = "draft.full.start";
$config->find(1);
$draftStart = $config->value;

$body = "";
$nextPick = "";
$onDeckPick = "";
$maxPick = 0;
$pickList = array();
$round = 0;
$roundArray = array();
$pick = 0;
$draftPick = array();
while ($draftPicks->fetch()) {
    // Determine and set-up round and pick
    $roundDist = sprintf("%02d", $draftPicks->Round);
    $pickDist = sprintf("%02d", $draftPicks->Pick);
    if ($roundDist != $round) {
        if ($round != 0) {
            $pickList[$round] = $roundArray;
        }
        $round = $roundDist;
        $roundArray = array();
    }

    // Get Team info
    $team = $draftPicks->getLink("teamid");
    #$teamName = sprintf("%20.20s", $team->Name);
    $teamName = $team->Name;
    $teamId = $team->TeamID;

    // Get player and pick info
    $player = $draftPicks->getLink("playerid");
    $timeStamp = $draftPicks->pickTime;
    $timeStamp = strtotime($timeStamp);
    if ($timeStamp > $maxPick) {
        $maxPick = $timeStamp;
    }
    
    if ($player != null) {
        $playerName = $player->firstname.' '.$player->lastname.' ('.$player->pos.'-'.$player->team.')';
        $playerId = $player->flmid;
        $playerPos = $player->pos;
        $playerTeam = $player->team;

        $playerArray = array( "id"=> $playerId, "pos"=>$playerPos, "team"=>$playerTeam, "name"=>$playerName);
        $franchise = array( "id"=> $teamId, "name"=>$teamName);
        $draftPick = array( "round"=>$roundDist, "pick"=>$pickDist, "timestamp"=>$timeStamp, "player"=> $playerArray, "franchise"=>$franchise);
    } else {
        //$startClock = strtotime($startClock);
        if ($startClock > $maxPick) {
            $maxPick = $startClock;
        }

        if ($nextPick == "") {
            // Determine time of expire
            $diff = getTimeAvail($teamId) - getTotalTimeUsed($currentSeason, $roundDist, $pickDist);
            if ($diff < 0) {$diff = 0;}
            $nextPick = array( "round"=>$roundDist, "pick"=>$pickDist, "time"=>$diff, "max"=>$maxPick, "start"=>$startClock, "rtime"=>time(), "team"=>$teamName, "teamid"=>$teamId);
            $lastPick = $draftPick;
        } else if ($onDeckPick == "") {
            $onDeckFranchise = array("id"=>$teamId, "name"=>$teamName);
            $onDeckPick = array("team"=>$teamName, "round"=>$roundDist, "pick"=>$pickDist, "franchise"=>$onDeckFranchise);
        }
        $franchise = array( "id"=> $teamId, "name"=>$teamName);
        $draftPick = array( "round"=>$roundDist, "pick"=>$pickDist, "franchise"=>$franchise);
    }
    $roundArray[$pickDist] = $draftPick;
}
$pickList[$round] = $roundArray;


$config2 = new DataObjects_Config;
$config2->key = "draft.clock.run";
$config2->find(1);
$pausedValue = $config2->value;
if ($pausedValue == "true") {
    $pausedValue = "false";
} else {
    $pausedValue = "true";
}

//exit();

$preArray = array();
if ($isin) {
    $sql = "SELECT CONCAT(p.lastname, ', ', p.firstname, ' - ', p.pos, ' - ', r.nflteamid)
    FROM draftPickHold d JOIN newplayers p ON d.playerid=p.playerid
    JOIN nflrosters r on r.playerid=d.playerid and r.dateoff is null
    WHERE d.teamid=$teamnum";
    $result2 = mysqli_query($conn, $sql);
    $preArray = mysqli_fetch_array($result2);
}


$draftResults = array( "timestamp"=> $maxPick, "draftstart"=>$draftStart, "paused"=> $pausedValue, "picks" => $pickList, "nextPick" => $nextPick);
$draftResults["onDeckPick"] = $onDeckPick;
$draftResults["lastPick"] = $lastPick;
$draftResults["preArray"] = $preArray;

header("Content-type: text/x-json");
print json_encode($draftResults);

?>
