<?php
require_once "utils/start.php";

if (isset($_REQUEST['url'])) {
    $url = $_REQUEST['url'];
} else {
    $url = '';
}

$updateQuery = <<<EOD

update config
set value=?
where `key`='draft.hangout.url'

EOD;

$stmt = mysqli_prepare($conn, $updateQuery);
mysqli_stmt_bind_param($stmt, 's', $url);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$numrow = mysqli_num_rows($result);

if ($numrow == 0) {
    print "Error";
} else {
    print "Ok - $numrow";
}
