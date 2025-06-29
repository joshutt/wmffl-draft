<?php
require_once "utils/start.php";

if (!$isin || $usernum != 2) {
    ?>

    <h2>Please log in to use commish tools</h2>

    <?
    return -1;
}


$chatquery = "select value from config where `key`='draft.hangout.url'";
$cresult = mysqli_query($conn, $chatquery);
$urlInfo = mysqli_fetch_assoc($cresult);
$url = $urlInfo['value'];
?>

<head>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
    <script src="commish.js" type="text/javascript"></script>
    <link rel="stylesheet" href="../bootstrap.min.css"/>
    <link rel="stylesheet" href="../mdb.min.css"/>
    <link href="commish.css" type="text/css" rel="stylesheet"/>
</head>

<body onload="setClock();">

<?php
$query = "select u.Name, t.name, o.primary, c.value, if(c.value > DATE_SUB(now(), INTERVAL 2 MINUTE), 'In', 'Out'), c2.value, t.teamid
from owners o
join user u on o.userid=u.UserID
join teamnames t on o.teamid=t.teamid and t.season=o.season
left join config c on c.key=concat('draft.login.', u.userid)
left join config c2 on c2.key=concat('draft.team.', t.teamid)
where o.season=$currentSeason
order by t.name";

//print $query;
$results = mysqli_query($conn, $query) or die("Unable to do query: " . mysqli_error($conn));
?>

<div class="container mt-5">
    <div class="row">
        <div class="container border border-primary col-6">
            <table id="logins">
                <tr>
                    <th>Name</th>
                    <th>Team</th>
                    <th>In</th>
                    <th>Time</th>
                    <th>Auto Pick</th>
                </tr>
                <?php
                while ($row = mysqli_fetch_array($results)) {
                    if ($row[4] == "In") {
                        $png = "green.png";
                    } else {
                        $png = "red.png";
                    }
                    ?>
                    <tr>
                        <td><?= $row[0] ?></td>
                        <td><?= $row[1] ?></td>
                        <td><img src="<?= $png ?>" height="30px" width="30px"></td>
                        <td></td>
                        <td>
                            <button type="button" class="btn btn-outline-danger" onclick="autoPick(<?= $row[6] ?>)">AUTO</button>
                        </td>
                    </tr>
                <?php } ?>
            </table>

            <select id="pickPos">
                <option value="*">None</option>
                <option value="QB">QB</option>
                <option value="RB">RB</option>
                <option value="WR">WR</option>
                <option value="TE">TE</option>
                <option value="K">K</option>
                <option value="OL">OL</option>
                <option value="DL">DL</option>
                <option value="LB">LB</option>
                <option value="DB">DB</option>
            </select>
        </div>

        <div class="border-primary col-4">
            <div id="clockStatus"><small>Clock is</small></div>
            <div id="team"></div>
            <div id="clock">5:00</div>
            <div id="stClock" class="btn btn-green col-4" onclick="changeClock();">Start Clock</div>
            <div id="undo" class="btn btn-grey col-4" onclick="undoPick();">Undo Pick</div>
            <div class="highlight">Total draft time:
                <div id="totalTime"/>
            </div>
            <div id="startDraft" class="btn btn-green" onclick="startDraft();">Start Draft</div>
        </div>

        <div class="border col m-4">
            <input class="form-control" type="text" id="chatUrl" onchange="updateUrl()" value="<?= $url ?>" />
        </div>
    </div>

</div>

</body>
