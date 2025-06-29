<?
require_once "utils/start.php";

if (isset($_REQUEST['last'])) {
    $last = $_REQUEST['last'];
} else {
    $last = 0;
}

$sql = <<<EOD

SELECT c.messageId, c.userid, c.message, c.time, u.name
FROM chat c
LEFT JOIN user u ON c.userid=u.userid
WHERE c.time > '2017-08-25'
AND c.messageId > $last
ORDER BY c.time DESC
LIMIT 25

EOD;

$results = mysqli_query($conn, $sql) or die("Error: " . mysqli_error($conn));
$body = "<table class=\"draft_picks_header report\" cellspacing=\"1\" align=\"center\" id=\"chat\">";
$body .= "<tbody><tr><th class=\"byName\">By</th>";
$body .= "<th class=\"message\">Message</th></tr>";
$team = "";
$first = true;
$maxPick = 0;
$count = 0;
$xmlOutput = "";
$text = "";
while ($row = mysqli_fetch_assoc($results)) {

    if ($count % 2) {
        $class = "oddtablerow";
    } else {
        $class = "eventablerow";
    }

    if ($row["userid"] == 0) {
        $extraCss = "league";
    } else {
        $extraCss = "";
    }

    $count = $count+1;
    $add = "<tr id=\"{$row["messageId"]}\" class=\"$class\">";
    $add .= "<td class=\"byName\">{$row["name"]}</td><td class=\"message $extraCss\">{$row["message"]}</td></tr>";
    $text = $add . $text;
    
}
$body .= $text;

header("Content-type: text/html");

$xmlOutput .= $body;

print $xmlOutput;

?>
</tbody>
</table>
