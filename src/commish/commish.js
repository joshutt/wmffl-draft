var loginSpot;
var pickSpot;

function setClock() {
    $.getJSON("../picks.php", setA);
    $.getJSON("getLogins.php", loadLogins);
    loginSpot = setInterval(function() {$.getJSON("getLogins.php", loadLogins);}, 5000);
    pickSpot = setInterval(function() {$.getJSON("../picks.php", setA);}, 5000);
}

function setA(data) {
    var paused = data["paused"];
    if (paused == "true") {
	    $("#stClock").text("Start Clock");
        $("#clockStatus").text("Clock is stopped");
    } else {
	    $("#stClock").text("Stop Clock");
        $("#clockStatus").text("Clock is running");
    }

    nextPick = data["nextPick"];
    $("#clock").text(convertTime(nextPick["time"]));
    $("#team").text(nextPick["team"]);

    currentTime = data["timestamp"];
    startTime = data["draftstart"];
    $("#totalTime").text(convertTime(currentTime - startTime));
}


function startClock() {
    $.get("../stopClock.php", {"start":"start"});
    $("#stClock").text("Stop Clock");
    $("#stClock").removeClass("btn-green").addClass("btn-red");
}

function stopClock() {
    $.get("../stopClock.php", {"stop":"stop"});
    $("#stClock").text("Start Clock");
    $("#stClock").removeClass("btn-red").addClass("btn-green");
}

function changeClock() {
    lab = $("#stClock").text();
    if (lab == "Start Clock") {
        startClock();
    } else {
        stopClock();
    }
    $.getJSON("../picks.php", setA);
}

function undoPick() {
    $.get("../undopick.php");
}

function loadLogins(data) {
    logInRows = $('#logins > tbody > tr > td');
    imgRows = $('#logins > tbody > tr > td > img');
    $.each(data, function(id, listArray) {
        if (listArray[4] == 'In') {
            //alert("In");
            imgRows[id].src = 'green.png';
        } else if (listArray[4] == 'Out') {
            //alert("Out");
            imgRows[id].src = 'red.png';
        } else {
            //alert("Other");
        //    imgRows[id].src = 'blah.png';
        }

        logInRows[5*(id+1) - 2].innerText = convertTime(listArray[5]);
        //logInRows[4*(id+1) - 1].innerText = "**"+listArray[5];
    });
}


function autoPick(teamid) {
    posSelect = $('#pickPos').val();
    alert("Auto Draft for team "+teamid+" at Pos: "+posSelect);
    if (posSelect == "*") {
        $.post("autopick.php", {teamid: teamid}, function(data) { alert("ERROR: "+data["error"]); });
    } else {
        $.post("autopick.php", {teamid: teamid, pos: posSelect}, function(data) { alert("ERROR: "+data["error"]); });
    }
    $('#pickPos').val("*");
}


function showMsg(data) {
}


function convertTime(secs) {
    var min = Math.floor(secs / 60);
    var sec = secs % 60;
    var disSec = ""+sec;
    if (disSec.length < 2) {
        disSec = "0"+sec;
    }
    return min+":"+disSec;
}


function startDraft() {
    $.get("startDraft.php");
    alert("Start Draft");
}


function updateUrl() {
    var val = $('#chatUrl').val();
    $.post("updateurl.php", {url: val}, function(data) { alert("URL updated"); });
}
