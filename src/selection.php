<?
require_once "utils/start.php";
$season = $currentSeason;

require "DataObjects/Draftpicks.php";
require "DataObjects/Team.php";
require "DataObjects/Roster.php";
require "DataObjects/Newplayers.php";
require "clock.class.php";

//$team = $_REQUEST["team"];
$team = $_SESSION["teamnum"];
$player = substr($_REQUEST["player"], 3);

/*
print($isin);
if (!$isin) {
    print "A";
} else {
    print "B";
}
exit();
*/

$errors = array();

if (!$isin) {
    array_push($errors, "Must Be Logged In");
}

if ($team == null || trim($team) == "") {
    array_push($errors, "No Selection Team Given");
}

if ($player == null || trim($player) == "") {
    array_push($errors, "No Player Selected");
}

    /*
    $fp = fopen('data.txt', 'a');
    $output = print_r($logArr, true);
    fwrite($fp,$output);
    fwrite($fp,"\nTeam is: $team  Player is: $player \n");
    fwrite($fp, "Set: ".isset($logArr)."\n");
    fwrite($fp, "Search: ".array_search($team, $logArr));
    fclose($fp);
    */

/*
if ((!isset($logArr) || !array_search($team, $logArr)) && !$commish) {
    array_push($errors, "Must be logged in as a team to make a selection");
}
*/


// Get the current Pick
// Make sure this is the correct team for it
$draftPicks = new DataObjects_Draftpicks;
$draftPicks->Season = $season;
$draftPicks->whereAdd("playerid is null");
$draftPicks->orderBy("Round");
$draftPicks->orderBy("Pick");
$draftPicks->find(true);

$teamPicked = new DataObjects_Team;
$teamPicked->TeamID = $team;
$teamPicked->find(true);

if ($team != $draftPicks->teamid) {
    $otherTeam = $draftPicks->getLink('teamid'); 
    array_push($errors, $otherTeam->Name." are on the clock, not ".$teamPicked->Name);
}

// Make sure that this player exists and is not already taken
$rosterPlayer = new DataObjects_Roster;
$rosterPlayer->PlayerID = $player;
$rosterPlayer->whereAdd("DateOff is null");
$rosterPlayer->find(true);

$playerInfo = new DataObjects_Newplayers;
$playerInfo->get($player);

if (!$playerInfo->N) {
    array_push($errors, "The playerId $player does not exist");
}

if ($rosterPlayer->N) {
    array_push($errors, "{$playerInfo->firstname} {$playerInfo->lastname} is already on a team");
}

$responseArray = array();

if (sizeof($errors) > 0) {
    $resp = "Illegal Draft Pick: \n";
    foreach ($errors as $err) {
        $resp .= "\t$err\n";
    }
    $responseArray["code"] = 0;
    $responseArray["alert"] = $resp;
    $responseArray["msg"] = "Invalid Pick";
} else {

	/*
    // Get the previous pick time
    $sql = "SELECT max(pickTime) as 'pickTime'  FROM draftpicks";
    $results = mysqli_query($conn, $sql) or die ("Unable to get max time: ".mysqli_error($conn)); 
    $rows = mysqli_fetch_assoc($results);
    $pickTime = strtotime($rows["pickTime"]);

    // Get any clock stop time
    $sql = "SELECT * FROM draftclockstop WHERE season=$season AND round={$draftPicks->Round} and pick={$draftPicks->Pick}";
    $results = mysqli_query($conn, $sql) or die ("Unable to get clock stop: ".mysqli_error($conn)); 
    $totalExtra = 0;
    while ($rows = mysqli_fetch_assoc($results)) {
        $timeStopped = strtotime($rows["timeStopped"]);
	$timeStarted = strtotime($rows["timeStarted"]);
	if ($timeStarted == null) {
	    $timeStarted = time();
	}
	$totalExtra += $timeStarted - $timeStopped;
    }

    // Calculate total time used
    $totalTime = time() - $pickTime + ($totalExtra) - 1;
	 */
    $totalTime = getTotalTimeUsed($season, $draftPicks->Round, $draftPicks->Pick);

    // Save player as the selected one
    $sql = "UPDATE draftpicks SET playerid=$player where Season=$season and Round={$draftPicks->Round} and Pick={$draftPicks->Pick}";
    mysqli_query($conn, $sql) or die ("MySQL Error #1: " . mysqli_error($conn));
    
    // Save the player onto the roster (NOT YET THOUGH)
    $sql = "INSERT INTO roster (playerid, teamid, dateon) VALUES ($player, $team, now())";
    mysqli_query($conn, $sql) or die ("MySQL ERROR #2: " . mysqli_error($conn));

    // Update the time remaining
    $sql = "UPDATE config c1 JOIN config c2 on c2.key='draft.clock.maxTime' SET c1.value = if(if(c1.value-$totalTime > c2.value, c2.value, c1.value-$totalTime) > 0, if(c1.value-$totalTime > c2.value, c2.value, c1.value-$totalTime), 0) WHERE c1.key = 'draft.team.$team'";
    //error_log("Update: $sql\n", 3, "check.log");
    mysqli_query($conn, $sql) or die ("Unable to update clock " . mysqli_error($conn));

    if ($draftPicks->fetch()) {
	$inNow = "{$draftPicks->Round} - {$draftPicks->Pick} - {$draftPicks->teamid}";
	$sql = "UPDATE config c1 JOIN config c2 on c2.key='draft.clock.addTime' JOIN config c3 ON c3.key='draft.clock.maxTime' SET c1.value=if(c1.value+c2.value > c3.value, c3.value, c1.value+c2.value) where c1.key = 'draft.team.{$draftPicks->teamid}'";
    //error_log("Update: $sql\n", 3, "check.log");
        mysqli_query($conn, $sql) or die ("Unable to add time to clock " . mysqli_error($conn));
    }

    $responseArray["code"] = 1;
    $responseArray["alert"] = "{$teamPicked->Name} successfully picked {$playerInfo->firstname} {$playerInfo->lastname}";
    $responseArray["msg"] = "{$teamPicked->Name} select {$playerInfo->lastname}, {$playerInfo->firstname} - {$playerInfo->pos} - {$playerInfo->team}";
    //$responseArray["alert"] = "Val: $inNow";

    // Delete the roster Item that was just set
}

print json_encode($responseArray);
?>
