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
from players p
JOIN roster r on p.playerid=r.PlayerID and r.DateOff is null
where r.teamid=$autoteam
group by p.pos
EOD;

    $results = mysqli_query($conn, $posQuery) or die("Unable to do query: " . mysqli_error($conn));
    $posMap = array("HC" => 0, "QB" => 0, "RB" => 0, "WR" => 0, "TE" => 0, "K" => 0, "OL"=>0, "DL" => 0, "LB" => 0, "DB" => 0);
    while ($row = mysqli_fetch_array($results)) {
        $posMap[$row[0]] = $row[1];
    }

    // Determine current round
    $roundQuery = <<<EOD
select min(dp.round)
from draftpicks dp
where dp.playerid is null and dp.teamid=$autoteam and dp.season=$currentSeason
EOD;
    $result2 = mysqli_query($conn, $roundQuery) or die("Unable to do query: " . mysqli_error($conn));
    $row = mysqli_fetch_array($result2);
    $round = $row[0];

#print_r($posMap);
    $starters = array();
    $backup = array();
    foreach ($posMap as $pos => $num) {
        $pickRound = 0;
        switch ($pos) {
            case "TE":
                $pickRound = 2;
            case "QB":
                if ($num < 1 && $round > $pickRound) {
                    array_push($starters, $pos);
                } else if ($num == 1) {
                    array_push($backup, $pos);
                }
                break;
            case "K":
                if ($num < 1 && $round > 12) {
                    array_push($starters, $pos);
                }
                break;
            case "OL":
                if ($num < 1 && $round > 9) {
                    array_push($starters, $pos);
                }
                break;
            case "DL":
            case "LB":
            case "DB":
                $pickRound = 2;
            case "RB":
            case "WR":
                if ($num < 2 && $round > $pickRound) {
                    array_push($starters, $pos);
                } else if ($num == 2) {
                    array_push($backup, $pos);
                }
                break;
        }
    }
    #print_r($starters);
    #print_r($backup);

    if (sizeof($starters) > 0) {
        $bigWhere = "p.pos IN ('" . implode("','",$starters) . "')";
    } else if (sizeof($backup) > 0) {
        $bigWhere = "p.pos IN ('" . implode("','",$backup) . "')";
    } else {
        $bigWhere = "1=1";
    }
    #print $bigWhere;
}

$evalSeason = $currentSeason - 1;  // $currentSeason will exist because of start.php
$query = <<<EOD

SELECT p.playerid, p.firstname, p.lastname, p.pos, sum(ps.pts), r.teamid
FROM players p
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

