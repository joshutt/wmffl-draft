<?php
require_once "utils/start.php";

// Make sure post variable exists
if (!isset($_POST)) {
    print json_encode(array("code" => -1, "msg" => "ERROR: Please provide username and password"));
    exit();
}

//Make sure username and password have been passed
$userName = $_POST["user"];
$password = $_POST["pass"];
if (!isset($userName) || !isset($password)) {
    print json_encode(array("code"=>-1, "msg"=>"ERROR: Please provide username and password"));
    exit();
}


$query = "SELECT teamid, name, userid, commish FROM user WHERE username='" . mysqli_real_escape_string($conn, $userName) . "' AND password=md5('" . mysqli_real_escape_string($conn, $password) . "') AND active='Y'";
$result = mysqli_query($conn, $query);
$numRows = mysqli_num_rows($result);
$count = mysqli_fetch_assoc($result);

if ($numRows != 1) {
    print json_encode(array("code"=>-1, "msg"=>"ERROR: Invalid username/password combination"));
    exit();
}

$_SESSION["isin"] = true;
$_SESSION["teamnum"] = $count["teamid"];
$_SESSION["usernum"] = $count["userid"];
$_SESSION["fullname"] = $count["name"];


$sql = "SELECT CONCAT(p.lastname, ', ', p.firstname, ' - ', p.pos, ' - ', r.nflteamid)
FROM draftPickHold d JOIN newplayers p ON d.playerid=p.playerid
JOIN nflrosters r on r.playerid=d.playerid and r.dateoff is null
WHERE d.teamid={$count["teamid"]}";
$result2 = mysqli_query($conn, $sql);
$playerArray = mysqli_fetch_array($result2);

//print json_encode($count);
print json_encode(array("code"=>1, "results"=>$_SESSION, "pre"=>$playerArray));

?>
