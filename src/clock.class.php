<?
require_once "utils/start.php";

class Clock {


}

function getPreviousPickTime() {
    global $conn;
    // Get the previous pick time
    $sql = "SELECT max(pickTime) as 'pickTime', max(c.value) as 'startTime'  FROM draftpicks p JOIN config c ON c.key='draft.full.start'";
    $results = mysqli_query($conn, $sql) or die ("Unable to get max time: " . mysqli_error($conn));
    $rows = mysqli_fetch_assoc($results);
    $pickTime = strtotime($rows["pickTime"]);
    $startTime = $rows["startTime"];
    return max($pickTime, $startTime);
    //return $pickTime>$startTime ? $pickTime : $startTime;
}


function getExtraTime($season, $round, $pick) {
    global $conn;
    // Get any clock stop time
    //$sql = "SELECT *, CONVERT_TZ(timeStarted, 'SYSTEM', 'GMT') as timeStarted,
    //   CONVERT_TZ(timeStopped,'SYSTEM','GMT') as timeStopped FROM draftclockstop
    //    WHERE season=$season AND round=$round and pick=$pick";
    $sql = "SELECT * FROM draftclockstop WHERE season=$season AND round=$round and pick=$pick";
    $results = mysqli_query($conn, $sql) or die ("Unable to get clock stop: " . mysqli_error($conn));
    $totalExtra = 0;
    while ($rows = mysqli_fetch_assoc($results)) {
        //error_log(print_r($rows,true), 3, "check.log");
        $timeStopped = strtotime($rows["timeStopped"]);
        $timeStarted = strtotime($rows["timeStarted"]);
//	print $timeStarted;
//	print "***";
        if ($timeStarted == null) {
            $timeStarted = time();
        }
//	print $timeStarted;
//	print "***";
//	print $timeStopped;
        //error_log("Time Started: $timeStarted *** Time Stopped: $timeStopped\n", 3, "check.log");
        $totalExtra += $timeStarted - $timeStopped;
        //error_log("Extra Time: $totalExtra", 3, "check.log");
    }
    return $totalExtra;
}

function getCurrentPick($season) {
    global $conn;
    $sql = "SELECT round, pick FROM draftpicks WHERE season=$season and playerid is null ORDER BY round, pick";
    $results = mysqli_query($conn, $sql) or die ("Unable to get current pick: " . mysqli_error($conn));
    $rows = mysqli_fetch_assoc($results);
    $returnArray = array($rows["round"], $rows["pick"]);
    return $returnArray;
}

function getTimeAvail($team) {
    global $conn;
    $sql = "SELECT value FROM config WHERE `key`='draft.team.$team' ";
    $results = mysqli_query($conn, $sql) or die ("Unable to get time available: " . mysqli_error($conn));
    $rows = mysqli_fetch_assoc($results);
    $remainTime = $rows["value"];
    return $remainTime;
}

function clockRunning() {
    global $conn;
    $sql = "SELECT value from config WHERE `key`='draft.clock.run' ";
    $results = mysqli_query($conn, $sql) or die ("Unable to get time available: " . mysqli_error($conn));
    $rows = mysqli_fetch_assoc($results);
    $clockRun = $rows["value"];
    return $clockRun;
}


function getTotalTimeUsed($season, $round=0, $pick=0) {

    if ($round == 0) {
        list($round, $pick) = getCurrentPick($season);
    }

    // Calculate total time used
    if ($round == 1 && $pick == 1) {
        $extra = 0;
    } else {
        $extra = getExtraTime($season, $round, $pick);
    }
    
    //print "Time: ".time();
    //print "Extra: $extra";
//    error_log("Time: ".time()."\n", 3, "check.log");
//    error_log("Extra: ".$extra."\n", 3, "check.log");
    $prev = getPreviousPickTime();
//    error_log("Previous: ".$prev."\n", 3, "check.log");
    $totalTime = time() - $prev - $extra;
//    error_log("Total Time: ".$totalTime."\n", 3, "check.log");

    return $totalTime;
}
