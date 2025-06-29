<?php
require_once "utils/start.php";
$pos = $_REQUEST["pos"];
$nfl = $_REQUEST["nfl"];

$sql = "SELECT p.playerid, p.lastname, p.firstname, p.pos, r.nflteamid FROM newplayers p JOIN nflrosters r ON p.playerid=r.playerid left join roster wr on wr.playerid=p.playerid and wr.dateoff is null WHERE r.dateoff is null and usePos=1 and wr.teamid is null ";
if ($pos != "*") {
    $sql .= "and p.pos='$pos' ";
}
if ($nfl != "*") {
    $sql .= "and r.nflteamid='$nfl' ";
}
$sql .= "order by p.lastname, p.firstname ";

$result = mysqli_query($conn, $sql) or die("Ug I died: " . mysqli_error($conn));

$returnArray = array();
while ($playerList = mysqli_fetch_array($result)) {
    //print "{$playerList["playerid"]} - {$playerList["lastname"]}, {$playerList["firstname"]} - {$playerList["pos"]} - {$playerList["team"]}";
//    print "<option value=\"{$playerList["playerid"]}\">{$playerList["lastname"]}, {$playerList["firstname"]} - {$playerList["pos"]} - {$playerList["nflteamid"]}</option>";
    $returnArray["id-".$playerList["playerid"]] = "{$playerList["lastname"]}, {$playerList["firstname"]} - {$playerList["pos"]} - {$playerList["nflteamid"]}";
}
//print_r( $returnArray);
//exit();


header("Content-Type: application/json");
print json_encode($returnArray);

