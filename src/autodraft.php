<?
require_once "utils/start.php";

if (isset($_REQUEST['teamid'])) {
    $team = $_REQUEST['teamid'];
} else {
    $teamid = 0;
}

$sql = <<<EOD
select p.firstname, p.lastname, p.pos, sum(ps.pts), r.teamid, t.name
from newplayers p
join playerscores ps on p.playerid=ps.playerid
left join roster r on p.playerid=r.playerid and r.dateoff is null
join team t on t.teamid=$teamid
where ps.season=2011 and ps.week<=14 and r.teamid is null
and p.pos not in (
select p.pos
from roster r
join newplayers p on r.playerid=p.playerid
where r.dateoff is null
and r.teamid=$teamid
group by p.pos
HAVING count(r.playerid) >= 2
)
group by p.playerid
order by 4 desc;
EOD;

$sql2 = <<<EOD
select p.firstname, p.lastname, p.pos, sum(ps.pts), r.teamid, t.name
from newplayers p
join playerscores ps on p.playerid=ps.playerid
left join roster r on p.playerid=r.playerid and r.dateoff is null
join team t on t.teamid=$teamid
where ps.season=2011 and ps.week<=14 and r.teamid is null
group by p.playerid
order by 4 desc;
EOD;

$results = mysqli_query($conn, $sql) or die("Error: " . mysqli_error($conn));
$numResults = mysqli_num_rows($results);
if ($numResults == 0) {
    $results = mysqli_query($conn, $sql2) or die("Error: " . mysqli_error($conn));
}

include "base/menu.php";


print "<table>";
$row = mysqli_fetch_assoc($results);
print "<tr><th colspan=\"2\">${row[name]}</th></tr>";
for ($i=1; $i<=10; $i++) {
    print "<tr><td>$i</td><td>${row[firstname]} ${row[lastname]}</td>";
    print "<td>${row[pos]}</td></tr>";
    //print_r($row);
    $row = mysqli_fetch_assoc($results);

}
print "</table>";


$sql = <<<EOD
SELECT teamid, name
FROM team
WHERE active=1
EOD;
$results = mysqli_query($conn, $sql) or die("Error: " . mysqli_error($conn));

while ($row = mysqli_fetch_assoc($results)) {
    print "<a href=\"autodraft.php?teamid=${row[teamid]}\">${row[name]}</a> - ";
}

include "base/footer.html";
?>
