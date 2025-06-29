// Global to indicate clock should be running
var clockRunning = false;
var areIn = false;

function convertTime(secs) {
    var min = Math.floor(secs / 60);
    var sec = secs % 60;
    var disSec = ""+sec;
    if (disSec.length < 2) {
        disSec = "0"+sec;
    }
    return min+":"+disSec;
}


var clockSpot;
var fullClockSpot;
var logInSpot;
var chatSpot;
var pickSpot;
function ready( ) {
    // Size Main Pick list
    resize();

    // Load Current picks
    $.getJSON("picks.php", loadPicks);
    pickSpot = setInterval(function() {$.getJSON("picks.php", loadPicks);}, 5000);
    // Update chat
    //chatSpot = setInterval(function() {$.get("chat.php", updateMessage);}, 5000);
    // Update clock
    $.getJSON("clockService.php", updateClock);
    fullClockSpot = setInterval(function() {$.getJSON("clockService.php", updateClock);}, 15000);
    clockSpot = setInterval(runClock, 1000);
    // Make sure logged in, if correct
    $.getJSON("checkIn.php", checkIn);
    logInSpot = setInterval(logCall, 10000);
}

// Resizes the player pick list
function resize() {
    $("#draft_picks_container").height($(window).innerHeight()-110);
}


// Populate the player pick list
function loadPicks( data ) {

    var lastUpdated = data["timestamp"];
    // Populate the draft picks
    picks = data["picks"];
    $.each(picks, function(round, pickArray) { 
        $.each(pickArray, function(pick, pickDetails) {
            spot = "pick_"+round+"_"+pick;
            // If the franchise has changed update it
            if ($("#"+spot+" td.franchise").text() != pickDetails["franchise"]["name"]) {
                $("#"+spot+" td.franchise").text(pickDetails["franchise"]["name"]);
            }
            // If there is a player there update it
            if (pickDetails["player"] != null) {
                $("#"+spot+" td.selection").text(pickDetails["player"]["name"]);
            } else if (pickDetails["player"] == null && $("#"+spot+" td.selection").text().trim() !== "") {
                // If No player in data but on page then clear it
                $("#"+spot+" td.selection").text("");
            }
        });
    });

    // Populate current pick
    nextPick = data["nextPick"];
    clockRunning = !JSON.parse(data["paused"]);
    $("#roundNum").text("Round: "+nextPick["round"]);
    $("#pickNum").text("Pick: "+nextPick["pick"]);
    clockVal = nextPick["time"];
    $("#clock").text(convertTime(nextPick["time"]));
    $("#pickTeam").text(nextPick["team"]);
    $("#nextPickTeam").text(data["onDeckPick"]["team"]);
    if (data["nextPick"]["round"] != "01" || data["nextPick"]["pick"] != "01") {
        $("#lastPickPlayer").text(data["lastPick"]["player"]["name"]);
        $("#lastPickTeam").text(data["lastPick"]["franchise"]["name"]);
    }

    if (data["preArray"]) {
        $("#choice").text(data["preArray"][0]);
        $("#clearButton").show();
    } else {
        $("#choice").text("No Current Selection");
        $("#clearButton").hide();
    }
}

// Update chat message
function updateMessage(data) {
    $("#chat_out").html(data);
}


function showChat() {
    //console.log("Show Chat");
    $("#chat_container").toggle();
    if ($("#chat_container").is(":visible")) {
        $("#showChat").text("Hide Chat");
    } else {
        $("#showChat").text("Show Chat");
    }
}


// toggle display of clocks
function switchClocks() {
    $("#clockDisplay").toggle();
    if ($("#clockDisplay").is(":visible")) {
        $("#showClocks").text("Hide Clocks");
    } else {
        $("#showClocks").text("Show Clocks");
    }
}

// Toggle display of rosters
function switchRoster() {
    $("#rosterTeam").toggle();
    if ($("#rosterTeam").is(":visible")) {
        $("#showRoster").text("Hide Rosters");
    } else {
        $("#showRoster").text("Show Rosters");
    }
}

// Retrieve the roster requested
var rightId=0;
function displayRoster(teamid) {
    if (rightId != teamid) {
        $.get("rosterHtml.php", {'teamid': teamid}, showRoster);
    }
    rightId = teamid;
}

// Display selected roster
function showRoster( data) {
    $("#leftRoster").html( $('#rightRoster').html());
    $("#rightRoster").html(data);
}

var clockVal;
var teamList = new Array();
function updateClock(data) {
    // Update the current team on the clock
    $("#pickTeam").text(data["onClock"]);
    //console.log(data);

    // Update the clock of each pick
    var count = 1;
    $.each(data["teamClocks"], function(name, time) {
        teamList[name] = count;
        $("#team"+count).text(name);
        $("#time"+count).text(convertTime(time));
        count++;
    });
    //console.log(teamList);

    // Set clock to current value
    var currentClock = data["currentClock"];
    clockVal = currentClock;
    var clockLine = $("#clock");
    clockLine.text(convertTime(currentClock));
    //runClock();
}


function runClock() {
    if (clockRunning) {
        var clock = $("#clock");
        if (clockVal > 0) {
            clockVal = clockVal - 1;
        } else {
            clock.css("text-decoration", "blink");
        }
        time = convertTime(clockVal);
        clock.text(time);
        $("#time"+teamList[$("#pickTeam").text()]).text(time);


        var nonHigh = "rgb(102,0,0)";
        if (clockVal <= 10) {
            clock.css("color", "red");
        } else if (clockVal > 10 && clock.css("color") != nonHigh) {
            clock.css("color", "#660000");
        }
    }
}


function stopClock() {
    clearInterval(clockSpot);
}

jQuery.fn.center = function () {
    this.css("position", "absolute");
    this.css("top", ($(window).height() - this.height() )/ 2 + $(window).scrollTop() + "px");
    this.css("left", ($(window).width() - this.width() )/ 2 + $(window).scrollLeft() + "px");
    return this;
}


function logCheck() {
    if (areIn) {
        $.post("logout.php", logOut);
        $("#logInMsg").text("");
    } else {
        $("#logInBlock").unbind("click");
        logIn();
    }
}


function logCall() {
    if (areIn) {
        $.get("stillHere.php");
    }
}


function logOut() {
    $("#logInBackground").css({"opacity": "0.7"}).fadeIn("slow");
    logOutBlock = $("#logOutBlock");
    logOutBlock.center().fadeIn("slow").text("Successful Logout").click(hideLogOut);
    $("#logInLine").text("Log In");
    $("#logInList").toggle();
    $("#logInInfo").toggle();
    $("#playerForm").hide();
    $("#myPick").hide();
    areIn = false;
}

function logIn() {
    $("#logInBackground").css({"opacity": "0.7"}).fadeIn("slow");
    $("#logInBlock").center().fadeIn("slow");
}

function hideLogOut() {
    $("#logOutBlock").fadeOut("slow");
    $("#logInBackground").fadeOut("slow");
}

function hideLogIn() {
    $("#logInBlock").fadeOut("slow");
    $("#logInBackground").fadeOut("slow");
}

function doLogOut() {
    console.log("Output");
}

function doLogIn() {
    var formVal = $("#logInForm :input");
    var values = {};
    formVal.each( function() {
            values[this.name] = this.value;
        }
    );
    $.post("loginA.php", values, function(data) {
        code = data["code"];
        if (code == -1) {
            $("#logInMsg").text(data["msg"]);
            areIn = false;
        } else if (code == 1) {
            $("#logInList").toggle();
            $("#logInMsg").text("Successful Login");
            $("#logInBlock").click(hideLogIn);
            checkIn(data);
            //$("#logInLine").text("Log Out");
            //areIn = true;
            //$("#logInInfo").text("Logged in as "+data["results"]["fullname"]).toggle();
        }
    }, "JSON");
}


function checkIn(data) {
    if (data["results"]["isin"]) {
        //$("#logInLine").text("Log Out");
        areIn = true;
        //$("#logInInfo").text("Logged in as "+data["results"]["fullname"]).toggle();
        $("#playerForm").show();
        $("#myPick").show();
        if (data["pre"]) {
            $("#choice").text(data["pre"][0]);
            $("#clearButton").show();
        } else {
            $("#choice").text("No Current Selection");
            $("#clearButton").hide();
        }
        $("#logInCard").hide();
    } else {
        $("#myPick").hide();
        $("#logInCard").show();
    }
}


// Post a chat message
function postMessage() {
    var chatMsg = $("#chat_text_field");
    if (chatMsg.val().length > 0) {
        $.post("postMessage.php", {"message": chatMsg.val(), "league": false}, updateMessage);
        chatMsg.val("");
    }
}


// Clear messages
function clearMessages() {
    $("#chat_out").html("");
}


function showOnly(theForm) {
    var theEl = theForm.form;
    var pos = theEl.pos.value;
    var nfl = theEl.nfl.value;

    $.get("playerList.php", {"nfl":nfl, "pos":pos}, changePlayers);
}

function changePlayers(data) {
    //console.log($("#mySelect"));
    var options = $("#mySelect").get(0).options;
    options.length = 0;
    $.each(data, function(key, value) {
        options[options.length] = new Option(value, key);
    });
}

function makePick() {
    var thisForm = $("#mySelect option:selected");
    var pick = thisForm.val();
    var team = thisForm.text();

    $("#choice").text(team);
    $("#clearButton").show();

    $.post("setPick.php", {"player" : pick}, confirmPick, "json");
    //$.post("selection.php", {"player" : pick}, confirmPick, "json");
}


function confirmPick(data) {
    console.log(data);
    console.log(data["alert"]);
    var msg = data["error"];
    //var msg = data["alert"];
    console.log(msg);
    alert(msg);
}

function buttonClear() {
    $("#clearButton").hide();
    $("#choice").text("No Current Selection");
    $.get("clearSelection.php");
}
