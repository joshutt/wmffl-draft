<?
require_once "utils/start.php";

$season = $currentSeason;
if ($isin && $teamnum==2) {
    
    $sql = "select * from draftpicks where season = $season and playerid is not null order by Round desc, Pick desc limit 1";
    $results = mysqli_query($conn, $sql);
    $picks = mysqli_fetch_array($results);

    $playerid = $picks['playerid'];
    $update = "update draftpicks set playerid=null where season=$season and playerid=$playerid";
    $delete = "delete from roster where dateoff is null and playerid=$playerid";

    mysqli_query($conn, $update) or die("Dead on update: " . mysqli_error($conn));
    mysqli_query($conn, $delete) or die("Dead on delete: " . mysqli_error($conn));
}

?>
