<?
require_once "utils/start.php";

$sql = <<<EOD

SELECT p.pos, CONCAT(p.firstName, ' ', p.lastName) as 'playername', t.name, t.teamid, r.dateon
FROM newplayers p
JOIN roster r ON r.playerid=p.playerid and r.dateoff is null
JOIN teamnames t ON r.teamid=t.teamid
WHERE t.season=$currentSeason
ORDER BY t.name, p.pos, p.lastname

EOD;

$results = mysqli_query($conn, $sql) or die("Error: " . mysqli_error($conn));
$body = "";
$team = "";
$first = true;
$maxPick = 0;
while ($row = mysqli_fetch_assoc($results)) {
    if ($row["name"] != $team) {
        if (!$first) {
            $body .= "</team>";
        }
        $first = false;
        $body .= "<team name=\"{$row["name"]}\" id=\"{$row["teamid"]}\">";
        $team = $row["name"]; 
    }
    $name = trim($row["playername"]);
    $body .= "<player pos=\"{$row["pos"]}\" name=\"$name\" />";
    //print $row["pos"]." - ".$row["playername"]." - ".$row["name"]."<br/>";
    $timeStamp = $row["dateon"];
    $timeStamp = strtotime($timeStamp);
    if ($timeStamp > $maxPick) {
        $maxPick = $timeStamp;
    }
}
$body .= "</team>";

header("Content-type: text/xml");

$xmlOutput = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
$xmlOutput .= "<roster timestamp=\"$maxPick\">\n";
$xmlOutput .= $body;
$xmlOutput .= "</roster>\n";

print $xmlOutput;

?>
