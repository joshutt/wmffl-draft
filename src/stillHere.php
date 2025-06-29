<?php
require_once "utils/start.php";

$query = "REPLACE into config (`key`, `value`) VALUES ('draft.login.{$_SESSION["usernum"]}', now())";
mysqli_query($conn, $query) or die("Error on replace: " . mysqli_error($conn));

?>
