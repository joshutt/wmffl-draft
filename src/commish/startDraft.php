<?php
require_once "utils/start.php";
require_once "../DraftUtils.php";

// Set every team's clock to draft.clock.allowed
$query = "update config c1 join config c2 on c2.`key`='draft.clock.allowed' set c1.value=c2.value where c1.key like 'draft.team.%'";
mysqli_query($conn, $query) or print('Error: '.mysqli_error($conn));

// Add addTime to first drafter
$query = "update draftpicks dp
join config c1 on c1.key = concat('draft.team.',dp.teamid)
join config c2 on c2.key = 'draft.clock.addTime'
set c1.value=c1.value+c2.value
where dp.season=$currentSeason and dp.round=1 and dp.pick=1";
mysqli_query($conn, $query) or print('Error: '.mysqli_error($conn));

// set the draft.clock.run to true
// set draft.start to true
$query = "update config set value='true' where `key` in ('draft.clock.run', 'draft.start')";
mysqli_query($conn, $query) or print('Error: '.mysqli_error($conn));

// set draft.clock.start to time()
// set draft.full.start to time()
$query = "update config set value=".time()." where `key` in ('draft.clock.start', 'draft.full.start')";
mysqli_query($conn, $query) or print('Error: '.mysqli_error($conn));

// Clear draft pick hold
$query = "delete from draftclockstop where Season=$currentSeason";
mysqli_query($conn, $query) or print('Error: '.mysqli_error($conn));

checkPreselect($currentSeason);