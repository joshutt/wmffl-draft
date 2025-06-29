<?
require_once "utils/start.php";

require "DataObjects/Draftpicks.php";

$draftPicks = new DataObjects_Draftpicks;
$draftPicks->Season = $currentSeason;
$draftPicks->orderBy("Round");
$draftPicks->orderBy("Pick");
$draftPicks->find();

?>

<html>
<head>

<title>WMFFL Live Draft</title>
<link href="draft.css" type="text/css" rel="stylesheet" />
<script src="update.js" type="text/javascript" language="javascript"></script>

</head>

<body id="body_ajax_ld" onresize="javascript:resize();" onLoad="javascript:loadStart();">

                <table id="statusTable">
                    <tr class="clockLine">
                        <td width="15%" id="roundNum">Rd</td>
                        <td width="15%" id="pickNum">Pk</td>
                        <td width="15%" id="clock">0:00</td>
                        <td width="55%" id="pickTeam"></td>
                    </tr>
                </table>

<div id="ajax_ld" class="pagebody">
<table width="100%">
    <tbody>
        <tr>
            <td colspan="2">
            </td>
        </tr>

    
        <tr>
            <td width="49%" valign="top">
                <table class="draft_picks_header report" cellspacing="1" align="center">
                    <caption><span>Draft Picks</span></caption>
                    <tbody>
                        <tr>
                            <th class="round">Rd</th>
                            <th class="pick">Pick</th>
                            <th class="franchise">Franchise</th>
                            <th class="selection">Selection</th>
                            <!--<th class="timestamp">Time</th>-->
                        </tr>
                    </tbody>
                </table>

                <div id="draft_picks_container" class="draft_picks_container">
                    <table class="report" cellspacing="1" align="center">
                        <tbody>
<?

while ($draftPicks->fetch()) {
    $roundDist = sprintf("%02d", $draftPicks->Round);
    $pickDist = sprintf("%02d", $draftPicks->Pick);
    $team = $draftPicks->getLink("teamid");
    $player = $draftPicks->getLink("playerid");
    if ($player != null) {
        $playerName = $player->firstname.' '.$player->lastname.' ('.$player->pos.'-'.$player->team.')';
    } else {
        $playerName = "";
    }
    #$teamName = sprintf("%25.25s", $team->Name);
    $teamName = $team->Name;
    if ($draftPicks->Pick % 2 == 0) {
        $row = "eventablerow";
    } else {
        $row = "oddtablerow";
    }
    print <<<EOD
                            <tr id="pick_{$roundDist}_{$pickDist}" class="$row" classname="$row">
                                <td class="round">$roundDist</td>
                                <td class="pick">$pickDist</td>
                                <td class="franchise">$teamName</td>
                                <td class="selection">$playerName</td>
                               <!-- <td class="timestamp"></td>-->
                            </tr>
EOD;
}
?>

                        </tbody>
                    </table>
                </div>
            </td>
            <td width="49%" valign="top">
                <div id="right_side">

                <table id="recentStatus" class="draft_picks_header report">
                    <tbody>
                        <tr><th colspan="3">Recent Status</th></tr>
                        <tr><td class="pickLabel">Last Pick</td><td id="lastPickPlayer"></td><td id="lastPickTeam"></td></tr>
                        <tr><td class="pickLabel">On Deck</td><td id="nextPickTeam"></td></tr>
                    </tbody>
                </table>


                <form id="pickForm" method="post" action="">
                <table class="draft_picks_header report" cellspacing="1" align="center">
                    <tbody>
                        <tr><th colspan="2">Make a Draft Pick</th></tr>
                        <tr class="oddtablerow">
                            <th>Team:</th>
                            <td><select name="team">
                            </select>
                            </td>
                        </tr>
                        <tr class="eventablerow">
                            <th>Show Only:</th>
                            <td><select name="pos" onChange="showOnly(this);">
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
                                    <option value="LA">LA</option>
                                    <option value="MIA">MIA</option>
                                    <option value="MIN">MIN</option>
                                    <option value="NE">NE</option>
                                    <option value="NO">NO</option>
                                    <option value="NYG">NYG</option>
                                    <option value="NYJ">NYJ</option>
                                    <option value="OAK">OAK</option>
                                    <option value="PHI">PHI</option>
                                    <option value="PIT">PIT</option>
                                    <option value="SD">SD</option>
                                    <option value="SEA">SEA</option>
                                    <option value="SF">SF</option>
                                    <option value="TB">TB</option>
                                    <option value="TEN">TEN</option>
                                    <option value="WAS">WAS</option>
                                </select>
                            </td>
                        </tr>
                        <tr class="oddtablerow">
                            <th>Player:</th>
                            <td id="selpla">
                                <select name="player">
                                </select>
                                <input type="button" onClick="makePick();" value="Pick"/>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </form>


                <table class="draft_picks_header report" cellspacing="1" align="center">
                    <tbody>
                        <tr><th colspan="3">Tools</th></tr>
                        <tr class="eventablerow">
                            <td align="center"><a class="tools" onClick="logIn();">Log In</a></td>
                            <td align="center"><a class="tools" onClick="stopClock();">Stop Clock</a></td>
                            <td align="center"><a class="tools" onClick="logOut();">Log Out</a></td>
                        </tr>
                        <tr class="eventablerow">
                            <td align="center"><a class="tools" onClick="switchLoad();">Toggle Update</a></td>
                            <td align="center"><a class="tools" onClick="doUpdate();">Do Update</a></td>
                            <td align="center"><a class="tools" onClick="undoPick();" id="undo"></a></td>
                        </tr>
                        <tr class="oddtablerow" id="logInInfo">
                            <td colspan="3">
                                <form id="logInForm" action="" method="post"><table>
                                    <tbody>
                                        <tr>
                                            <th>Team:</th>
                                            <td><select name="team">
                                                <option value="1">Pretend I'm Not Here</option>
                                                <option value="2">Werewolves</option>
                                                <option value="3">Norsemen</option>
                                                <option value="4">Lindbergh Baby Casserole</option>
                                                <option value="5">Sacks on the Beach</option>
                                                <option value="6">Crusaders</option>
                                                <option value="7">MeggaMen</option>
                                                <option value="8">Fighting Squirrels</option>
                                                <option value="9">Gallic Warriors</option>
                                                <option value="10">Whiskey Tango</option>
                                            </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Password:</th>
                                            <td><input type="password" name="pass"/>                                                <input type="button" onClick="doLogIn();" value="LogIn"/>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table></form>
                            </td>
                        </tr>
                        <tr class="oddtablerow" id="logOutInfo">
                            <td colspan="3">
                                <form id="logOutForm" action=""><table>
                                    <tbody>
                                        <tr>
                                            <td><select name="team">
                                            </select>
                                            <td>
                                            <input type="button" onClick="doLogOut();" value="Log Out"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table></form>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                </div>
            </td>
        </tr>
    </tbody>
</table>
</div>

</body>
</html>
