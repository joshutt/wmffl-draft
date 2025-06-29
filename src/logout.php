<?
require_once "utils/start.php";

if (!isset($logArr)) {
    $_SESSION['logArr'] = array();
    $logArr = &$_SESSION['logArr'];
}

if (isset($team)) {
    $spot = array_search($team, $logArr);
    if (is_numeric($spot)) {
        $logArr[$spot] = 0;
    }
}

unset($team);
unset($_SESSION["teamnum"]);
unset($_SESSION["user"]);
unset($_SESSION["fullname"]);
$_SESSION["isin"] = False;

print_r($_SESSION);
?>
