<?
require_once "utils/start.php";

$sql = "SELECT MAX(messageId) FROM chat ";
$results = mysqli_query($conn, $sql) or die("Die: " + mysqli_error($conn));
list($message) = mysqli_fetch_row($results);

print $message;
?>
