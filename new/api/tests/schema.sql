-- Minimal schema for the tables PickService/DraftClockService touch, used
-- only by the local PHPUnit test database (wmffl_test) — not a migration,
-- just enough DDL to exercise the real transaction/locking behavior that
-- can't be meaningfully verified with mocks. Column shapes mirror
-- src/lib/DataObjects/*.php.

DROP TABLE IF EXISTS draftPickHold;
DROP TABLE IF EXISTS draftclockstop;
DROP TABLE IF EXISTS roster;
DROP TABLE IF EXISTS draftpicks;
DROP TABLE IF EXISTS nflrosters;
DROP TABLE IF EXISTS newplayers;
DROP TABLE IF EXISTS teamnames;
DROP TABLE IF EXISTS config;
DROP TABLE IF EXISTS weekmap;
DROP TABLE IF EXISTS user;
DROP TABLE IF EXISTS owners;
DROP TABLE IF EXISTS playerscores;
DROP TABLE IF EXISTS nflbyes;

CREATE TABLE config (
    `key` VARCHAR(255) NOT NULL PRIMARY KEY,
    `value` VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE teamnames (
    teamid INT NOT NULL,
    season SMALLINT UNSIGNED NOT NULL,
    name VARCHAR(50) NOT NULL,
    abbrev VARCHAR(3) NOT NULL,
    divisionId INT NOT NULL DEFAULT 0,
    PRIMARY KEY (teamid, season)
) ENGINE=InnoDB;

CREATE TABLE draftpicks (
    Season SMALLINT UNSIGNED NOT NULL,
    Round INT NOT NULL,
    Pick INT NOT NULL,
    teamid INT NULL,
    orgTeam INT NULL,
    playerid INT NULL,
    pickTime TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (Season, Round, Pick)
) ENGINE=InnoDB;

CREATE TABLE newplayers (
    playerid INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    flmid INT NOT NULL,
    lastname VARCHAR(25) NOT NULL,
    firstname VARCHAR(25) NULL,
    pos VARCHAR(2) NULL,
    team VARCHAR(3) NULL,
    usePos INT NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE nflrosters (
    playerid INT NOT NULL,
    nflteamid VARCHAR(3) NOT NULL,
    dateon DATE NOT NULL,
    dateoff DATE NULL,
    pos VARCHAR(3) NOT NULL,
    PRIMARY KEY (playerid, nflteamid, dateon)
) ENGINE=InnoDB;

CREATE TABLE roster (
    PlayerID INT NOT NULL,
    TeamID INT NOT NULL,
    DateOn DATETIME NOT NULL,
    DateOff DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE draftPickHold (
    teamid INT NOT NULL PRIMARY KEY,
    playerid INT NULL
) ENGINE=InnoDB;

CREATE TABLE draftclockstop (
    season SMALLINT UNSIGNED NOT NULL,
    round INT NOT NULL,
    pick INT NOT NULL,
    timeStopped TIMESTAMP NULL,
    timeStarted TIMESTAMP NULL
) ENGINE=InnoDB;

CREATE TABLE weekmap (
    Season SMALLINT UNSIGNED NOT NULL,
    Week INT NOT NULL,
    StartDate DATETIME NOT NULL,
    EndDate DATETIME NOT NULL,
    ActivationDue DATETIME NULL,
    DisplayDate DATETIME NOT NULL,
    weekname VARCHAR(22) NULL,
    PRIMARY KEY (Season, Week)
) ENGINE=InnoDB;

CREATE TABLE user (
    UserID INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    TeamID INT NULL,
    Username VARCHAR(20) NOT NULL,
    Password VARCHAR(50) NOT NULL,
    Name VARCHAR(50) NULL,
    Email VARCHAR(75) NOT NULL DEFAULT '',
    primaryowner INT NOT NULL DEFAULT 0,
    active CHAR(1) NOT NULL DEFAULT 'Y',
    commish INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE owners (
    teamid INT NOT NULL,
    userid INT NOT NULL,
    season SMALLINT UNSIGNED NOT NULL,
    `primary` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (teamid, userid, season)
) ENGINE=InnoDB;

CREATE TABLE playerscores (
    playerid INT NOT NULL,
    season INT NOT NULL,
    week INT NOT NULL,
    pts INT NULL,
    active INT NULL,
    PRIMARY KEY (playerid, season, week)
) ENGINE=InnoDB;

CREATE TABLE nflbyes (
    season SMALLINT UNSIGNED NOT NULL,
    week INT NOT NULL,
    nflteam VARCHAR(3) NOT NULL,
    PRIMARY KEY (season, nflteam)
) ENGINE=InnoDB;
