<?php
/************************************************************************
 * This will open the session and a database connection.
 *
 * The following session variables are defined in here:
 *   $currentWeek - The current season week
 *   $currentSeason - The current season year
 *   $weekName - The name of the current week
 *   $lastFetch - The time stamp of the last time week info was retrieved
 **************************************************************************/ 

require_once 'setup.php';

// If we've already loaded the mysqli then just return
if (isset($conn) && get_class($conn) == 'mysqli') {
    return;
}

// Start the session, so that every page is in session
session_start();

// establish Database connection information
$conn = mysqli_connect('localhost', $ini['userName'], $ini['password'], $ini['dbName']);
//mysqli_select_db($conn, $ini["dbName"]);

// Make sure timezone is correct
$tzQuery = "SET time_zone = 'America/New_York'";
$tzResult = mysqli_query($conn, $tzQuery);

// Determine the current season and current week, but not every time, use cachin
//if (!isset($_SESSION["lastFetch"]) || time() > $lastFetch + 60 * 60) {
    $dateQuery = "SELECT w1.season, w1.week, w1.weekname, w2.weekname as 'previous' FROM weekmap w1, weekmap w2 ";
    $dateQuery .= 'WHERE now() BETWEEN w1.startDate and w1.endDate ';
    $dateQuery .= 'and IF(w1.week=0, w2.season=w1.season-1 and w2.week=16, w2.week=w1.week-1 and w2.season=w1.season) ';
$dateResult = mysqli_query($conn, $dateQuery);
list($_SESSION['currentSeason'], $_SESSION['currentWeek'], $_SESSION['weekName'], $_SESSION['previousWeekName']) = mysqli_fetch_row($dateResult);
    if ($_SESSION['currentWeek'] == 0) {
        $_SESSION['previousWeekSeason'] = $_SESSION['currentSeason']-1;
        $_SESSION['previousWeek'] = 16;
    } else {
        $_SESSION['previousWeekSeason'] = $_SESSION['currentSeason'];
        $_SESSION['previousWeek'] = $_SESSION['currentWeek']-1;
    }
    $_SESSION['lastFetch'] = time();
    
//}
//print "$currentWeek - $currentSeason<br/>";
//print $_SESSION["currentWeek"]." -".$_SESSION["currentSeason"]."<br/>";

foreach ($_SESSION as $key => $value) {
    ${$key} = $value;
    $GLOBALS[$key] = $value;
    error_log("WHAR: [$key] [$value]");
}

if (!isset($isin) || empty($isin)) {
    $isin = false;
}

error_log("Is in? [$isin]");
