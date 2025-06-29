<?
require_once "utils/start.php";
$pos = $_REQUEST["pos"];
$nfl = $_REQUEST["nfl"];

$sql = "SELECT p.playerid, p.lastname, p.firstname, p.pos, r.nflteamid FROM newplayers p, nflrosters r left join roster wr on wr.playerid=p.playerid and wr.dateoff is null WHERE p.playerid=r.playerid and r.dateoff is null and usePos=1 and wr.teamid is null ";
if ($pos != "*" && $pos != "") {
    $sql .= "and p.pos='$pos' ";
}
if ($nfl != "*" && $nfl != "") {
    $sql .= "and r.nflteamid='$nfl' ";
}
$sql .= "order by p.lastname, p.firstname ";

print $sql;
$result = mysqli_query($conn, $sql) or die("Ug I died: " . mysqli_error($conn));

print "The List<br/>";
while ($playerList = mysqli_fetch_array($result)) {
    print "{$playerList["playerid"]} - {$playerList["lastname"]}, {$playerList["firstname"]} - {$playerList["pos"]} - {$playerList["team"]}<br/>";
//    print "<option value=\"{$playerList["playerid"]}\">{$playerList["lastname"]}, {$playerList["firstname"]} - {$playerList["pos"]} - {$playerList["nflteamid"]}</option>";
}


?>
