<?
require_once "utils/start.php";

$message = $_REQUEST["message"];
//$last = $_REQUEST["last"];
$league = $_REQUEST["league"];
/*
print $message;
print "<br/>";
print mysqli_real_escape_string($conn, $message);
print "<br/>";
*/
#print $_SESSION["userid"];
$toUseId = $_SESSION["usernum"];
//$toUseId = $_SESSION["userid"];

if ($league == "true") {
    $toUseId=0;
}

$sql = "INSERT INTO chat (userid, message) VALUES ($toUseId, '" . mysqli_real_escape_string($conn, $message) . "')";
//print $sql;
mysqli_query($conn, $sql) or die("Die: " + mysqli_error($conn));


include "chat.php";
?>
