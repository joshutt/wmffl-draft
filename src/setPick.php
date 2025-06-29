<?php
require_once "utils/start.php";
require_once "utils/StringUtils.php";
require "DraftUtils.php";

header("Content-type: text/x-json");

if (!$isin) {
    print json_encode( array("error"=> "Not Logged In") );
    exit();
}

// Get the team and the pick
$teamId = $_SESSION["teamnum"];
$fullPlayer = array_key_exists("player", $_REQUEST) ? $_REQUEST["player"] : "";

// If this is autodraft use it
if (isset($autoDraft) && $teamId == 2) {
    $teamId = $autoteam;
    $fullPlayer = $autoPlayer;
}

// Make sure the pick is valid
if (!startsWith($fullPlayer, "id-")) {
    print json_encode( array("error"=> "Selection is not valid") );
    exit();
}
$playerId = substr($fullPlayer, 3);


// If this team is not on the clock
$onClockTeam = getTeamOnClock($currentSeason);
if ($teamId != $onClockTeam) {
    // Save the pick as the next pick for the team
    saveTeamPick($teamId, $playerId);
    exit();
}

// If the team is on the clock
// Make the pick
$errors = makePick($teamId, $playerId, $currentSeason);
if (sizeof($errors) > 0) {
    print json_encode( array("error"=>$errors));
    exit();
}

checkPreselect($currentSeason);


