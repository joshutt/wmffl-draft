function resize() {
    var container = document.getElementById("draft_picks_container");
    var windowHeight = this.innerHeight;
  
    if( typeof( window.innerHeight ) == 'number' ) {
        //Non-IE
        windowHeight = window.innerHeight;
    } else if( document.documentElement && document.documentElement.clientHeight ) {
        //IE 6+ in 'standards compliant mode'
        windowHeight = document.documentElement.clientHeight;
    } else if( document.body && document.body.clientHeight ) {
        //IE 4 compatible
        windowHeight = document.body.clientHeight;
    }

    
    var newHeight = windowHeight - 117;
    container.style.height = newHeight+"px";


    var rSize = document.getElementById("rosterList");
    var outerSize = document.getElementById("rosterTeam_container");

    var oTop = outerSize.offsetTop;
    var oHeight = outerSize.offsetHeight;

    if (oTop+oHeight > newHeight) {
        rSize.style.height = (newHeight-oTop)+"px";
    } else {
        rSize.style.height = "";
    }

/*
    var roster = document.getElementById("rosters");
    var rosterHeight = windowHeight - 400;
    roster.style.height = rosterHeight+"px";
    roster.style.top = "600px";
    */
}


var currentServerTime = 0;
var stopRepeat = 0;
var currentClockTime = 0;
var clockRun = true;
var endFlag = false;
var logInDisplay = false;
var logOutDisplay = false;


function convertTime(secs) {
    var min = Math.floor(secs / 60);
    var sec = secs % 60;
    var disSec = ""+sec;
    if (disSec.length < 2) {
        disSec = "0"+sec;
    }
    return min+":"+disSec;
}


function updateClock() {
    if (endFlag) {
        return;
    }
    
    if (clockRun) {
	 //   alert(prop);
	 //   alert(clockInfo.teamClocks[prop]);
        var clockTime = document.getElementById("clock");
        clockTime.innerHTML = convertTime(currentClockTime);
        //clockTime.textContent = convertTime(currentClockTime);
        currentClockTime = currentClockTime - 1;
        if (currentClockTime < 0) {
            currentClockTime = 0;
            clockTime.style.textDecoration = "blink";
        } else {
            clockTime.style.textDecoration = "";
        }

        if (currentClockTime <= 15) {
            clockTime.style.color = "red";
        } else {
            clockTime.style.color = "";
        }
    } else {
        var clockTime = document.getElementById("clock");
        clockTime.innerHTML = "Stopped";
        //clockTime.textContent = "Stopped";
        clockTime.style.textDecoration = "";
        clockTime.style.color = "Red";
    }
    setTimeout('updateClock()', 1000);
}


function stopClock() {
    var clockTime = document.getElementById("clock");
    clockTime.textContent = convertTime(currentClockTime);
    clockTime.innerHTML = convertTime(currentClockTime);
    clockTime.style.textDecoration = "";
    clockTime.style.color = "";

    var clockLabel = document.getElementById("stclock");

    if (clockRun) {
        clockRun = false;
        clockLabel.textContent = "Start Clock";
        makeHttpRequest("stopClock.php?stop");
        postMessage2("*** Commissioner Stopped the Clock ***", true);
    } else {
        clockRun = true;
        clockLabel.textContent = "Stop Clock";
        makeHttpRequest("stopClock.php?start");
        postMessage2("*** Commissioner Started the Clock ***", true);
    }
}

function startClock() {
    clockRun = true;

    var clockLabel = document.getElementById("stclock");
    clockLabel.textContent = "Stop Clock";

    makeHttpRequest("stopClock.php?start");
}

function switchLoad() {
    stopRepeat =  1 - stopRepeat;
}

function doUpdate() {
    makeHttpRequest('picks.php', 'parsePicks', 1);
}


function logIn() {
    var extraInfo = document.getElementById("logInInfo");
    if (!logInDisplay) {
        if (extraInfo.style.setAttribute) {
            extraInfo.style.setAttribute('display', 'block');
        } else {
            extraInfo.style.display = 'table-row';
        }
        logInDisplay = true;
    } else {
        if (extraInfo.style.setAttribute) {
            extraInfo.style.setAttribute('display', 'none');
        } else {
            extraInfo.style.display = 'none';
        }
        logInDisplay = false;
    }
}


function logOut() {
    var extraInfo = document.getElementById("logOutInfo");
    if (!logOutDisplay) {
        makeHttpRequest('login.php', 'showLogout', 0);
        if (extraInfo.style.setAttribute) {
            extraInfo.style.setAttribute('display', 'block');
        } else {
            extraInfo.style.display = 'table-row';
        }
        logOutDisplay = true;
    } else {
        if (extraInfo.style.setAttribute) {
            extraInfo.style.setAttribute('display', 'none');
        } else {
            extraInfo.style.display = 'none';
        }
        logOutDisplay = false;
    }
}


function showLogout(responseText) {
    var form = document.getElementById("loutTeam");
    form.innerHTML = "<select name=\"team\">"+responseText+"</select>";
}

var enableUndo = false;
var globalTeam = 0;

function doLogIn() {
    var form = document.getElementById("logInForm");
    var team = form.team.value;
    var pass = form.pass.value;
    
    // Make log in request, which resets logged in list
    var url = 'login.php';
    var params = 'team='+team+'&pass='+pass;
    makeHttpRequest(url, 'postLogin', 0, params);
    
    // Hide login again
    logIn();
    form.pass.value = '';
    if (team == 2) {
        enableUndo = true;
    } else {
        enableUndo = false;
    }
    globalTeam = team;
}


function doLogOut() {
    var form = document.getElementById("logOutForm");
    var team = form.team.value;
    var url = 'logout.php?team='+team;
    makeHttpRequest(url, 'postLogin', 0);
    logOut();
    if (team == 2) {
        enableUndo = false;
    }
}


function postLogin(responseText) {
    if (responseText.substr(0, 5) == "ERROR") {
        alert (responseText.substr(6));
        return;
    }
    var form = document.getElementById("pickFormTeam");
    form.innerHTML = "<select name=\"team\">"+responseText+"</select>";

    var undo = document.getElementById("undo");
    var comRow = document.getElementById("commishRow");
    if (enableUndo) {
        undo.innerHTML = "Undo Last Pick";
        undo.onClick = "undoPick()";
        comRow.style.display = "table-row";
    } else {
        undo.innerHTML = "";
        undo.onClick = "";
        comRow.style.display = "none";
    }
}


function makeHttpRequest(url, callback_function, returnXML, post) {

    var httpRequest = false;

    if (window.XMLHttpRequest) {
        httpRequest = new XMLHttpRequest();
        if (httpRequest.overrideMimeType) {
            httpRequest.overrideMimeType('text/xml');
        }
    } else if (window.ActiveXObject) {
        try {
            httpRequest = new ActiveXObject("Msxml2.XMLHTTP");
        } catch (e) {
            try {
                httpRequest = new ActiveXObject("Microsoft.XMLHTTP");
            } catch (e) {}
        }
    }

    if (!httpRequest) {
        alert ('Unfortunately your browser doesn\'t support this feature.');
        return false;
    }
    if (callback_function) {
        httpRequest.onreadystatechange = function() {
            if (httpRequest.readyState == 4) {
                if (httpRequest.status == 200) {
                    if (returnXML) {
                        eval(callback_function + '(httpRequest.responseXML)');
                    } else {
                        eval(callback_function + '(httpRequest.responseText)');
                    }
                }
            }
        }
    }

    if (post) {
        httpRequest.open('POST', url, true);
        httpRequest.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        httpRequest.send(post);

    } else {
        httpRequest.open('GET', url, true);
        httpRequest.send(null);
	//alert(request.responseText);
    }
}


function parsePicks(draftResultsXML) {
    if (typeof draftResultsLastUpdated == "undefined") {
        draftResultsLastUpdated = currentServerTime;
    }
    
    if (typeof lastPickTime == "undefined") {
        lastPickTime = 0;
    }
    if (typeof draftPaused == "undefined") {
        draftPaused = 0;
    }
    if (typeof draftResumed == "undefined") {
        draftResumed = 0;
    }
    if (typeof draftOver == "undefined") {
        draftOver = 0;
    }

    var draftResults = draftResultsXML.getElementsByTagName("draftResults");
    var thisLastUpdated = draftResults[0].getAttribute("timestamp");

    draftPaused = draftResults[0].getAttribute("paused");
    draftResumed = draftResults[0].getAttribute("resumed");
    draftOver = draftResults[0].getAttribute("over");

    if (draftOver == "true") {
        endFlag = true;
        stopRepeat = 1;
    }

    if (draftPaused == "true") {
        clockRun = false;
    } else {
        clockRun = true;
    }
    
    var lastPickPlayer = null;
    var lastPickTeam = null;
    var nextPickTeamName = null;
    var onClockTeam = null;
    
    if (thisLastUpdated > draftResultsLastUpdated) {
        var draftPicks = draftResultsXML.getElementsByTagName("draftPick");
        for (var i=0; i<draftPicks.length; i++) {
            var thisPickTimestamp = draftPicks[i].getAttribute("timestamp");
            if (thisPickTimestamp > lastPickTime) {
                lastPickTime = thisPickTimestamp;
            }
            var pick = "pick_"+draftPicks[i].getAttribute("round")+"_"+draftPicks[i].getAttribute("pick");
            var currentRow = document.getElementById(pick);
            if (thisPickTimestamp && thisPickTimestamp > draftResultsLastUpdated) {
                if (currentRow) {
                    var currentClass = currentRow.getAttribute("className");
                    if (currentClass == null || currentClass.length == 0) {
                        currentClass = currentRow.getAttribute("class");
                    }
                    currentRow.setAttribute("className", "warning "+currentClass);
                    currentRow.setAttribute("class", "warning "+currentClass);
                    
                    var cells = currentRow.getElementsByTagName("td");

                    franchise = null;
                    player = null;
                    for (var j=0; j<draftPicks[i].childNodes.length; j++) {
                        if (draftPicks[i].childNodes[j].nodeType!=1) {
                            continue;
                        }
                        if (draftPicks[i].childNodes[j].tagName == 'player') {
                            player = draftPicks[i].childNodes[j];
                        } else if (draftPicks[i].childNodes[j].tagName == 'franchise') {
                            franchise = draftPicks[i].childNodes[j];
                        }
                    }

                    var playerDisplay = player.childNodes[0].nodeValue;
                    // Play audio clip here

                    cells[3].innerHTML = playerDisplay;
                    thisPick = pick;

                    lastPickPlayer = playerDisplay;
                    lastPickTeam = franchise.childNodes[0].nodeValue;
                    //lastPickTeam = franchise.textContent;
                }
            } else if (!thisPickTimestamp && currentRow) {
                var cells = currentRow.getElementsByTagName("td");
                cells[3].innerHTML = "";
            } else if (currentRow) {
                var newClass = ((i%2) == 0 ? "oddtablerow" : "eventablerow");
                currentRow.setAttribute("className", newClass);
                currentRow.setAttribute("class", newClass);
            }
            
        }

        onClock = draftResultsXML.getElementsByTagName("nextPick");
        onClockTeam = onClock[0].firstChild.childNodes[0].nodeValue;
        onClockRd = onClock[0].getAttribute("round");
        onClockPk = onClock[0].getAttribute("pick");
        onClockCl = onClock[0].getAttribute("time");
        onDeck = draftResultsXML.getElementsByTagName("onDeck");
        onDeckTeam = onDeck[0].firstChild.childNodes[0].nodeValue;
        currentClockTime = parseInt(onClockCl);


        var pickPlayer = document.getElementById("lastPickPlayer");
        //pickPlayer.textContent = lastPickPlayer;
        pickPlayer.innerHTML= lastPickPlayer;
        var pickTeam = document.getElementById("lastPickTeam");
        pickTeam.innerHTML = lastPickTeam;
        var nextTeam = document.getElementById("nextPickTeam");
        nextTeam.innerHTML = onDeckTeam;
        var onClock = document.getElementById("pickTeam");
        onClock.innerHTML = onClockTeam;
        var clockRound = document.getElementById("roundNum");
        clockRound.innerHTML = "Rd "+parseInt(onClockRd, 10);
        var clockPick = document.getElementById("pickNum");
        clockPick.innerHTML = "Pk "+parseInt(onClockPk, 10);
        var clockTime = document.getElementById("clock");
        clockTime.innerHTML = convertTime(parseInt(onClockCl));
        clockTime.textContent = convertTime(parseInt(onClockCl));

        draftResultsLastUpdated = thisLastUpdated;

        if (thisLastUpdated > currentServerTime) {
            currentServerTime = thisLastUpdated;
        }
    }
    
    var url = "picks.php";
    if (!stopRepeat) {
        setTimeout("makeHttpRequest('"+url+"', 'parsePicks', 1)", 5000);
    }
    url = null;
}


function undoPick() {
    makeHttpRequest('undopick.php', 'doClear', 0);
}

function doClear() {
    postMessage2("*** Last Pick Was Undone ***", true);
}


function showOnly(thisEl) {
    var thisForm = thisEl.form;
    var pos = thisForm.pos.value;
    var nfl = thisForm.nfl.value;
    var player = thisForm.player;

    // TODO: Do a query here
    var url = "playerList.php?nfl="+nfl+"&pos="+pos;
    makeHttpRequest(url, 'changePlayers', 0);
}


function makePick() {
    var thisForm = document.getElementById("pickForm");
    var pick = thisForm.player.value;
    var team = thisForm.team.value;
    var playerName = thisForm.player.item(thisForm.player.selectedIndex).text;
    var teamName = thisForm.team.item(thisForm.team.selectedIndex).text;

    // TODO: Need to send pick to server
    var url = "selection.php?team="+team+"&player="+pick;
    makeHttpRequest(url, 'confirmPick', 0);
}


function confirmPick(responseText) {
    var wholeThing = JSON.parse(responseText);

    alert(wholeThing.alert);
    if (wholeThing.code) {
        postMessage2("*** "+wholeThing.msg+" ***", true);
    }
}

function changePlayers(responseText) {
    var thisForm = document.getElementById("pickForm");
//    thisForm.player.innerHTML = responseText;

    var button = "<input type=\"button\" onClick=\"makePick();\" value=\"Pick\"/>";
    var selpla = document.getElementById("selpla");
    selpla.innerHTML = "<select name=\"player\">" + responseText + "</select>" + button;
}

function submitenter(myfield, e) {
    var keycode;
    if (window.event) {
        keycode = window.event.keyCode;
    } else if (e) {
        keycode = e.which;
    } else {
        return true;
    }

    if (keycode == 13) {
        //myfield.form.submit();
        doLogIn();
        return false;
    } else {
        return true;
    }
}


var rightId = 0;
function displayRoster(teamid) {
    if (rightId != teamid) {
        makeHttpRequest('rosterHtml.php?teamid='+teamid, 'showRoster', 0);
    }
    rightId = teamid;
}

function showRoster(responseText) {
    document.getElementById("leftRoster").innerHTML = document.getElementById("rightRoster").innerHTML;
    document.getElementById("rightRoster").innerHTML = responseText;
}

function switchRoster() {
    var rostElement = document.getElementById("rosterTeam");
    var display = rostElement.style.display;
    if (display != "table" && display != "block") {
        if (rostElement.style.setAttribute) {
            rostElement.style.setAttribute('display', 'block');
        } else {
            rostElement.style.display = 'table';
        }
        document.getElementById("showRoster").innerHTML = "Hide Rosters";

        var otherDisplay = document.getElementById("chat_table");
        if (otherDisplay.style.display == "table" || otherDisplay.style.display == "block") {
            showChat();
        }
    } else {
        if (rostElement.style.setAttribute) {
            rostElement.style.setAttribute('display', 'none');
        } else {
            rostElement.style.display = 'none';
        }
        document.getElementById("showRoster").innerHTML = "Show Rosters";
    }
}

function switchClocks() {
    var clockElement = document.getElementById("clockDisplay");
    var display = clockElement.style.display;
    if (display != "table" && display != "block") {
        if (clockElement.style.setAttribute) {
            clockElement.style.setAttribute('display', 'block');
        } else {
            clockElement.style.display = 'table';
        }
        document.getElementById("showClocks").innerHTML = "Hide Clocks";

        var otherDisplay = document.getElementById("chat_table");
        if (otherDisplay.style.display == "table" || otherDisplay.style.display == "block") {
            showChat();
        }
    } else {
        if (clockElement.style.setAttribute) {
            clockElement.style.setAttribute('display', 'none');
        } else {
            clockElement.style.display = 'none';
        }
        document.getElementById("showClocks").innerHTML = "Show Clocks";
    }
}


function showChat() {
    var rostElement = document.getElementById("chat_table");
    var display = rostElement.style.display;
    if (display != "table" && display != "block") {
        if (rostElement.style.setAttribute) {
            rostElement.style.setAttribute('display', 'block');
        } else {
            rostElement.style.display = 'table';
        }
        document.getElementById("showChat").innerHTML = "Hide Chat";
        
        var otherDisplay = document.getElementById("rosterTeam");
        if (otherDisplay.style.display == "table" || otherDisplay.style.display == "block") {
            switchRoster();
        }
    } else {
        if (rostElement.style.setAttribute) {
            rostElement.style.setAttribute('display', 'none');
        } else {
            rostElement.style.display = 'none';
        }
        document.getElementById("showChat").innerHTML = "Show Chat";
    }
}


var chatClear = true;
var chatLast = 23;

function postMessage() {
    var field = document.getElementById("chat_text_field");
    /*
    if (field.value.length > 0) {
        var params = "last="+chatLast+"&message="+escape(field.value);
        makeHttpRequest('postMessage.php', 'updateMessage', 0, params);
        field.value='';
    }
    */
    postMessage2(field.value, false);
    field.value='';
}

function postMessage2(msg, leagueMessage) {
    if (msg.length > 0) {
        var params = "last="+chatLast+"&message="+escape(msg)+"&league="+leagueMessage;
        makeHttpRequest('postMessage.php', 'updateMessage', 0, params);
    }

}

function clearMessages() {
    makeHttpRequest('getLastChat.php', 'updateChatLast', 0);
    //makeHttpRequest('chat.php?last='+chatLast, 'updateMessage', 0);
}

function updateChatLast(responseText) {
    chatLast = responseText;
    makeHttpRequest('chat.php?last='+chatLast, 'updateMessage', 0);
}

function updateMessage(responseText) {
    var chat = document.getElementById("chat_out");
    chat.innerHTML = responseText;
    //document.getElementById("chat").innerHTML = responseText;
    if (chatClear) {
        chatClear = false;
        setTimeout("holdMessage()", 5000);
    }
}

function holdMessage() {
    chatClear = true;
    makeHttpRequest('chat.php?last='+chatLast, 'updateMessage', 0);
}

function pullClock(responseText) {
    var clockInfo = JSON.parse(responseText);
    currentClockTime = clockInfo.currentClock;

	i = 0;
	for(var prop in clockInfo.teamClocks) {
	    i++;
	    var teamName = document.getElementById("team"+i);
	    var timeSpot = document.getElementById("time"+i);
            teamName.innerHTML=prop;
	    timeSpot.innerHTML=convertTime(clockInfo.teamClocks[prop]);
	}
    
  //  makeHttpRequest('picks.php', 'parsePicks', 1);
    setTimeout("makeHttpRequest('clockService.php', 'pullClock')", 5000);
}


window.onload = function() {
    // Resize window
//    resize();

    // Get current picks
//    currentServerTime = 0;
//    stopRepeat = 0;
//    makeHttpRequest('picks.php', 'parsePicks', 1);

    // Update players list based on change
//    var url = "playerList.php?nfl=*&pos=*";
//    makeHttpRequest(url, 'changePlayers', 0);

    // Get the chat up to date
//    makeHttpRequest('chat.php?last='+chatLast, 'updateMessage', 0);

    // Set the close
//    makeHttpRequest('clockService.php', 'pullClock');
//    updateClock();

    // Set any logins
//    makeHttpRequest('login.php', 'postLogin', 0);
}
