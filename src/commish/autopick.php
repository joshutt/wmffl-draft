<?php
require_once "utils/start.php";

if (isset($_REQUEST['teamid'])) {
    $autoteam = $_REQUEST['teamid'];
} else {
    $autoteam = 0;
}

if (isset($_REQUEST['pos'])) {
    $pickPos = $_REQUEST['pos'];
    $bigWhere = "p.pos='$pickPos' ";
} else {

    // Determine number of teams at each position
    $posQuery = <<<EOD

select p.pos, count(*)
from newplayers p
JOIN roster r on p.playerid=r.PlayerID and r.DateOff is null
where r.teamid=$autoteam
group by p.pos
EOD;

    $results = mysqli_query($conn, $posQuery) or die("Unable to do query: " . mysqli_error($conn));
    $posMap = array("HC" => 0, "QB" => 0, "RB" => 0, "WR" => 0, "TE" => 0, "K" => 0, "OL"=>0, "DL" => 0, "LB" => 0, "DB" => 0);
    while ($row = mysqli_fetch_array($results)) {
        $posMap[$row[0]] = $row[1];
    }

    $starters = array();
    $backup = array();
    foreach ($posMap as $pos => $num) {
        switch ($pos) {
            case "QB":
            case "TE":
            case "K":
            case "OL":
                if ($num < 1) {
                    array_push($starters, $pos);
                } else if ($num == 1) {
                    array_push($backup, $pos);
                }
                break;
            case "RB":
            case "WR":
            case "DL":
            case "LB":
            case "DB":
                if ($num < 2) {
                    array_push($starters, $pos);
                } else if ($num == 2) {
                    array_push($backup, $pos);
                }
                break;
        }
    }

    if (sizeof($starters) > 0) {
        $bigWhere = "p.pos IN ('" . implode("','",$starters) . "')";
    } else if (sizeof($backup) > 0) {
        $bigWhere = "p.pos IN ('" . implode("','",$backup) . "')";
    } else {
        $bigWhere = "1=1";
    }
}

$evalSeason = $currentSeason - 1;  // $currentSeason will exist because of start.php
$query = <<<EOD

SELECT p.playerid, p.firstname, p.lastname, p.pos, sum(ps.pts), r.teamid
FROM newplayers p
JOIN playerscores ps ON p.playerid = ps.playerid
LEFT JOIN roster r ON p.playerid = r.playerid AND r.dateoff IS NULL
LEFT JOIN nflrosters nr ON nr.playerid=p.playerid and nr.dateoff is null
WHERE ps.season = $evalSeason AND ps.week <= 14 AND r.teamid IS NULL AND p.pos <> 'HC' and p.usePos=1 and p.pos<>'' 
AND nr.nflteamid is not null
AND $bigWhere 
GROUP BY p.playerid
ORDER BY sum(ps.pts) DESC, RAND();

EOD;

$results = mysqli_query($conn, $query) or die("Unable to do query: " . mysqli_error($conn));
$row = mysqli_fetch_array($results);


$autoDraft = true;
$autoPlayer = "id-" . $row["playerid"];

include "../setPick.php";

