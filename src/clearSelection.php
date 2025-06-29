<?php
require_once "utils/start.php";

if (!$isin) {
    exit();
}

$teamId = $_SESSION["teamnum"];

// Remove this team's preselections
$sql = "DELETE FROM draftPickHold WHERE teamid=$teamId";
mysqli_query($conn, $sql) or die ("Unable to remove hold: " . mysqli_error($conn));
