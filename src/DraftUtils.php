<?php
require_once "utils/start.php";
require_once "DataObjects/Draftpicks.php";
require_once "DataObjects/Team.php";
require_once "DataObjects/Roster.php";
require_once "DataObjects/Newplayers.php";
require "clock.class.php";


// Return the id of the team currently on the clock
function getTeamOnClock($season) {
    global $conn;
    // Get the current Pick
    // Make sure this is the correct team for it
    $draftPicks = new DataObjects_Draftpicks;
    $draftPicks->Season = $season;
    $draftPicks->whereAdd("playerid is null");
    $draftPicks->orderBy("Round");
    $draftPicks->orderBy("Pick");
    $draftPicks->find(true);

    $sql = "SELECT value FROM config where `key`='draft.start'";
    $result = mysqli_query($conn, $sql) or die("Unable to get start: " . mysqli_error($conn));
    $row = mysqli_fetch_array($result);
    if ($row[0] == "true") {
        return $draftPicks->teamid;
    } else {
        return null;
    }
}


// Save the current pick as a future selection
function saveTeamPick($teamid, $playerid) {
    global $conn;
    // UPDATE pick with this player
    $sql = "REPLACE draftPickHold (teamid, playerid) VALUES ($teamid, $playerid)";
    $result = mysqli_query($conn, $sql) or die("Unable to update draftPickHold: " . mysqli_error($conn));
}


function makePick($teamid, $playerid, $season) {
    global $conn;
    $errors = confirmPlayerAvailable($playerid);
    if (sizeof($errors) > 0) {
        return $errors;
    }

    list($round, $pick) = getCurrentPick($season);
    $totalTime = getTotalTimeUsed($season, $round, $pick);

    // Save player as the selected one
    $sql = "UPDATE draftpicks SET playerid=$playerid where Season=$season and Round=$round and Pick=$pick";
    mysqli_query($conn, $sql) or die (array_push($errors, "MySQL Error #1: " . mysqli_error($conn)));
    
    // Save the player onto the roster (NOT YET THOUGH)
    $sql = "INSERT INTO roster (playerid, teamid, dateon) VALUES ($playerid, $teamid, now())";
    mysqli_query($conn, $sql) or die (array_push($errors, "MySQL ERROR #2: " . mysqli_error($conn)));

    // Remove this team's preselections
    $sql = "DELETE FROM draftPickHold WHERE teamid=$teamid OR playerid=$playerid";
    mysqli_query($conn, $sql) or die (array_push($errors, "MySQL ERROR #3: " . mysqli_error($conn)));

    if (sizeof($errors) > 0) {
        return $errors;
    }

    // Adjust clock
    adjustClock($season, $round, $pick, $teamid, $totalTime);

    return $errors;
}


function confirmPlayerAvailable($playerid) {
    $errors = array();

    // Make sure that this player exists and is not already taken
    $rosterPlayer = new DataObjects_Roster;
    $rosterPlayer->PlayerID = $playerid;
    $rosterPlayer->whereAdd("DateOff is null");
    $rosterPlayer->find(true);

    $playerInfo = new DataObjects_Newplayers;
    $playerInfo->get($playerid);

    if (!$playerInfo->N) {
        array_push($errors, "The playerId $playerid does not exist");
    }

    if ($rosterPlayer->N) {
        array_push($errors, "{$playerInfo->firstname} {$playerInfo->lastname} is already on a team");
    }

    return $errors;
}


/*
function getCurrentPick($season) {
    // Get the current Pick
    // Make sure this is the correct team for it
    $draftPicks = new DataObjects_Draftpicks;
    $draftPicks->Season = $season;
    $draftPicks->whereAdd("playerid is null");
    $draftPicks->orderBy("Round");
    $draftPicks->orderBy("Pick");
    $draftPicks->find(true);

    return array($draftPicks->Round, $draftPicks->Pick);
}
*/


function adjustClock($season, $round, $pick, $teamid, $totalTime) {
    global $conn;

//    error_log("-------------Adjust Clock---------------\n", 3, "check.log");
    //$totalTime = getTotalTimeUsed($season, $round, $pick);
    // Update the time remaining
    $sql = "UPDATE config c1 JOIN config c2 on c2.key='draft.clock.maxTime' SET c1.value = if(if(c1.value-$totalTime > c2.value, c2.value, c1.value-$totalTime) > 0, if(c1.value-$totalTime > c2.value, c2.value, c1.value-$totalTime), 0) WHERE c1.key = 'draft.team.$teamid'";
//    error_log("$sql\n", 3, "check.log");
    mysqli_query($conn, $sql) or die ("Unable to update clock " . mysqli_error($conn));

    // Add time to next team
    $nextTeam = getTeamOnClock($season);
    $sql = "UPDATE config c1 JOIN config c2 on c2.key='draft.clock.addTime' JOIN config c3 ON c3.key='draft.clock.maxTime' SET c1.value=if(c1.value+c2.value > c3.value, c3.value, c1.value+c2.value) where c1.key = 'draft.team.$nextTeam'";
//    error_log("$sql\n", 3, "check.log");
    mysqli_query($conn, $sql) or die ("Unable to add time to clock " . mysqli_error($conn));

//    error_log("---------------------------------------\n", 3, "check.log");
}


function getPreselection($teamid) {
    global $conn;
    $sql = "SELECT playerid FROM draftPickHold WHERE teamid=$teamid";
    $results = mysqli_query($conn, $sql) or die ("Unable to get draftPickHold: " . mysqli_error($conn));
    return mysqli_fetch_array($results);
}


/**
 * If the current team on the clock has a preselection, use it.
 * Continue as long as teams have preselections
 *
 * @param int $currentSeason
 */
function checkPreselect(int $currentSeason): void
{
// While the team on the clock has a pick
    $teamId = getTeamOnClock($currentSeason);
    $selection = getPreselection($teamId);
    while ($selection["playerid"]) {
        makePick($teamId, $selection["playerid"], $currentSeason);
        $teamId = getTeamOnClock($currentSeason);
        $selection = getPreselection($teamId);
    }
}
