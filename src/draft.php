<?php
require_once "utils/start.php";

require "DataObjects/Draftpicks.php";

$draftPicks = new DataObjects_Draftpicks;
$draftPicks->Season = $currentSeason;
$draftPicks->orderBy("Round");
$draftPicks->orderBy("Pick");
$draftPicks->find();

$chatquery = "select value from config where `key`='draft.hangout.url'";
$cresult = mysqli_query($conn, $chatquery);
$urlInfo = mysqli_fetch_assoc($cresult);
$url = $urlInfo['value'];

?>

<html>
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <link rel="icon" href="/images/logo3.png" type="image/png">
    <title>WMFFL Live Draft</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.6.0/css/font-awesome.min.css">
    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="bootstrap.min.css"/>
    <link rel="stylesheet" href="mdb.min.css"/>
    <link href="draft.css" type="text/css" rel="stylesheet"/>

    <!-- Latest compiled and minified JavaScript -->
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
    <script src="update.jquery.js" type="text/javascript"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"
            integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa"
            crossorigin="anonymous"></script>
</head>

<body onresize="resize()" onLoad="$(ready)">

<nav class="mt-2">
    <div class="container-fluid">
        <div class="col-12 border border-1 border-wm">
            <div id="clockDiv" class="row">
                <div id="roundNum" class="col-xl-2 col-lg-3 col-5 font-weight-bold text-large">Round:</div>
                <div id="pickNum" class="col-lg-2 col-4 font-weight-bold text-large">Pick:</div>
                <div id="clock" class="col-lg-2 col-3 font-weight-bold text-large">0:00</div>
                <div id="pickTeam" class="col-xl-6 col-lg-5 font-weight-bold text-large"></div>
            </div>
        </div>
    </div>
</nav>

<main class="mt-1">
    <div class="container-fluid">
        <div class="row flex-md-row-reverse mx-0 py-1">
            <div class="col-lg-8 col-md-6">
                <div class="row mx-0">
                <div class="col-lg-5 col-md-12 px-0">
                    <div class="card" id="logInCard">
                        <div class="card-header bg-primary py-0">Log In</div>
                        <div class="card-body">
                            <form id="logInForm" method="post" onSubmit="doLogIn(); return false;">
                                <p><label class="logInLabel">User:</label>
                                    <input type="text" name="user"/></p><br/>
                                <p><label class="logInLabel">Password:</label>
                                    <input type="password" name="pass"></p><br/>
                                <p><input type="button" onClick="doLogIn();" value="Log In"/></p>
                            </form>
                        </div>
                    </div>
                    <div id="myPick" class="card">
                        <div class="card-header bg-primary py-0">My Pick</div>
                        <div class="card-body p-2 text-center">
                            <div id="choice">No Current Selection</div>
                            <div id="clearButton">
                                <button type="button" class="btn btn-outline-warning btn-sm" onclick="buttonClear()">
                                    Clear
                                </button>
                            </div>
                            <form id="pickForm" method="post" action="">
                                <div id="pickFilter">
                                    <div id="filterLabel"><label>Filter:</label></div>
                                    <div id="filterSelect">
                                        <select name="pos" onChange="showOnly(this);">
                                            <option value="*">All</option>
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
                                        <select name="nfl" onChange="showOnly(this);">
                                            <option value="*">All</option>
                                            <option value="ARI">ARI</option>
                                            <option value="ATL">ATL</option>
                                            <option value="BAL">BAL</option>
                                            <option value="BUF">BUF</option>
                                            <option value="CAR">CAR</option>
                                            <option value="CHI">CHI</option>
                                            <option value="CIN">CIN</option>
                                            <option value="CLE">CLE</option>
                                            <option value="DAL">DAL</option>
                                            <option value="DEN">DEN</option>
                                            <option value="DET">DET</option>
                                            <option value="GB">GB</option>
                                            <option value="HOU">HOU</option>
                                            <option value="IND">IND</option>
                                            <option value="JAC">JAC</option>
                                            <option value="KC">KC</option>
                                            <option value="LAC">LAC</option>
                                            <option value="LAR">LAR</option>
                                            <option value="LV">LV</option>
                                            <option value="MIA">MIA</option>
                                            <option value="MIN">MIN</option>
                                            <option value="NE">NE</option>
                                            <option value="NO">NO</option>
                                            <option value="NYG">NYG</option>
                                            <option value="NYJ">NYJ</option>
                                            <option value="PHI">PHI</option>
                                            <option value="PIT">PIT</option>
                                            <option value="SEA">SEA</option>
                                            <option value="SF">SF</option>
                                            <option value="TB">TB</option>
                                            <option value="TEN">TEN</option>
                                            <option value="WAS">WAS</option>
                                        </select>
                                        <button type="button" onclick="makePick()" class="btn btn-outline-warning btn-sm">Pick</button>
                                    </div>
                                </div>
                                    <div id="selpla">
                                        <select name="player" id="mySelect" size="25">
                                        </select>
                                    </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7 col-md-12 pr-0">
                    <div class="card">
                        <span class="text-center">Join the Google <a href="https://<?= $url ?>">Hangout</a></span>
                    </div>

                    <div class="card mb-2">
                        <div class="card-header bg-primary py-0">Recent Status</div>
                        <div class="card-body container-fluid">
                            <div class="row mx-0">
                                <div class="col-3 font-weight-bold px-0">Last Pick</div>
                                <div class="col-5 px-0" id="lastPickPlayer"></div>
                                <div class="col-4 px-0" id="lastPickTeam"></div>
                            </div>
                            <div class="row mx-0">
                                <div class="col-3 font-weight-bold px-0">On Deck</div>
                                <div class="col-9 px-0" id="nextPickTeam"></div>
                            </div>
                        </div>
                    </div>

                    <div id="clockTeam_container" class="card mb-2">
                        <div class="card-header bg-primary py-0">Clocks</div>
                        <div class="card-body container-fluid py-0">
                            <div class="row">
                                <table class="">
                                    <tbody>
                                    <tr>
                                        <td valign="top" id="leftClock">
                                            <table class="table-striped">
                                                <tr class="">
                                                    <td class="team text-small" id="team1"></td>
                                                    <td class="pos text-small" id="time1"></td>
                                                </tr>
                                                <tr class="">
                                                    <td class="team text-small" id="team2"></td>
                                                    <td class="pos text-small" id="time2"></td>
                                                </tr>
                                                <tr class="">
                                                    <td class="team text-small" id="team3"></td>
                                                    <td class="pos text-small" id="time3"></td>
                                                </tr>
                                                <tr class="">
                                                    <td class="team text-small" id="team4"></td>
                                                    <td class="pos text-small" id="time4"></td>
                                                </tr>
                                            </table>
                                        </td>
                                        <td valign="top" id="rightClock">
                                            <table class="table-striped">
                                                <tr class="">
                                                    <td class="team text-small" id="team5"></td>
                                                    <td class="pos text-small" id="time5"></td>
                                                </tr>
                                                <tr class="">
                                                    <td class="team text-small" id="team6"></td>
                                                    <td class="pos text-small" id="time6"></td>
                                                </tr>
                                                <tr class="">
                                                    <td class="team text-small" id="team7"></td>
                                                    <td class="pos text-small" id="time7"></td>
                                                </tr>
                                                <tr class="">
                                                    <td class="team text-small" id="team8"></td>
                                                    <td class="pos text-small" id="time8"></td>
                                                </tr>
                                            </table>
                                        </td>
                                        <td valign="top" id="rightClock">
                                            <table class="table-striped">
                                                <tr class="">
                                                    <td class="team text-small" id="team9"></td>
                                                    <td class="pos text-small" id="time9"></td>
                                                </tr>
                                                <tr class="">
                                                    <td class="team text-small" id="team10"></td>
                                                    <td class="pos text-small" id="time10"></td>
                                                </tr>
                                                <tr class="">
                                                    <td class="team text-small" id="team11"></td>
                                                    <td class="pos text-small" id="time11"></td>
                                                </tr>
                                                <tr class="">
                                                    <td class="team text-small" id="team12"></td>
                                                    <td class="pos text-small" id="time12"></td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card d-none d-lg-block">
                        <div class="card-header bg-primary py-0">Rosters</div>
                        <div class="card-body">
                            <div id="teamAbb" class="text-center">
                            <a onClick="displayRoster('2')" class="teamRoster">AE</a> -
                            <a onClick="displayRoster('6')" class="teamRoster">CRU</a> -
                            <a onClick="displayRoster('1')" class="teamRoster">FBB</a> -
                            <a onClick="displayRoster('8')" class="teamRoster">FS</a> -
                            <a onClick="displayRoster('9')" class="teamRoster">GW</a> -
                            <a onClick="displayRoster('7')" class="teamRoster">MM</a> -
                            <a onClick="displayRoster('3')" class="teamRoster">NOR</a> -
                            <a onClick="displayRoster('5')" class="teamRoster">SOB</a> -
                            <a onClick="displayRoster('4')" class="teamRoster">STA</a> -
                            <a onClick="displayRoster('12')" class="teamRoster">RL</a> -
                            <a onClick="displayRoster('13')" class="teamRoster">TR</a> -
                            <a onClick="displayRoster('10')" class="teamRoster">TMC</a>
                            </div>
                            <table width="100%">
                                <tbody>
                                <tr><td valign="top" id="leftRoster" width="50%">
                                    </td><td valign="top" id="rightRoster" width="50%">
                                    </td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 col-lg-pull-8 px-0 card tableList">

                <table class="table-striped">
                    <thead>
                    <tr>
                        <th class="round text-small text-center px-2">Rd</th>
                        <th class="pick text-small text-center px-2">Pick</th>
                        <th class="franchise text-small text-center px-2">Franchise</th>
                        <th class="selection text-small text-center px-2">Selection</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?

                    while ($draftPicks->fetch()) {
                        $roundDist = sprintf("%02d", $draftPicks->Round);
                        $pickDist = sprintf("%02d", $draftPicks->Pick);
                        $team = $draftPicks->getLink("teamid");
                        $player = $draftPicks->getLink("playerid");
                        if ($player != null) {
                            $playerName = $player->firstname . ' ' . $player->lastname . ' (' . $player->pos . '-' . $player->team . ')';
                        } else {
                            $playerName = "";
                        }

                        //error_log(print_r($team, true));
                        $teamName = $team->Name;
                        print <<<EOD
                            <tr id="pick_{$roundDist}_{$pickDist}">
                                <td class="round text-small px-2">$roundDist</td>
                                <td class="pick text-small px-2">$pickDist</td>
                                <td class="franchise text-small px-2">$teamName</td>
                                <td class="selection text-small px-2">$playerName</td>
                               <!-- <td class="timestamp"></td>-->
                            </tr>
EOD;
                    }
                    ?>

                    </tbody>
                </table>


            </div>
        </div>

    </div>
</main>

</body>
</html>
