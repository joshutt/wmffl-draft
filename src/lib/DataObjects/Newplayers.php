<?php

/**

 * Table Definition for newplayers

 */

require_once 'DB/DataObject.php';


class DataObjects_Newplayers extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'newplayers';          // table name
    public $playerid;                        // int(11)  not_null primary_key auto_increment
    public $flmid;                           // int(11)  not_null unique_key
    public $lastname;                        // string(25)  not_null
    public $firstname;                       // string(25)  
    public $pos;                             // string(2)  enum
    public $team;                            // string(3)  
    public $number;                          // int(11)  
    public $retired;                         // year(4)  unsigned zerofill
    public $height;                          // int(11)  
    public $weight;                          // int(11)  
    public $dob;                             // date(10)  binary
    public $draftTeam;                       // string(3)  
    public $draftYear;                       // year(4)  unsigned zerofill
    public $draftRound;                      // int(11)  
    public $draftPick;                       // int(11)  
    public $active;                          // int(1)  not_null
    public $usePos;                          // int(4)  not_null
    public $nflid;                           // int(11)  
    public $nfldb_id;                        // string(10)  unique_key

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    var $transactions;

    function loadTransaction() {
        $transactions = array();
        $trans = new DataObjects_NFLTransactions;
        $trans->playerid = $playerid;
        $trans->orderBy("transdate");
        $trans->find();
        while($trans->fetch()) {
            $transactions[] = $trans;
        }
    }

    /* convert a position number into a code */
    function getPosFromNum($num) {
        switch($num) {
            case 0: return "None"; break;
            case 1: return "HC"; break;
            case 2: return "QB"; break;
            case 3:
            case 14:
                return "RB"; break;
            case 4: return "WR"; break;
            case 5: return "TE"; break;
            case 6: return "K"; break;
            case 8: return "OL"; break;
            case 12: return "LB"; break;
            case 13:
            case 15:
            case 16:
                return "DB"; break;
            case 17:
            case 18:
                return "DL"; break;
            default:
                return ""; break;
        }
    }



    function getTeamFromNum($num) {

        switch($num) {

            case 1: return 'BUF'; break;

            case 2: return 'IND'; break;

            case 3: return 'MIA'; break;

            case 4: return 'NE'; break;

            case 5: return 'NYJ'; break;

            case 6: return 'CIN'; break;

            case 7: return 'CLE'; break;

            case 8: return 'TEN'; break;

            case 9: return 'JAC'; break;

            case 10: return 'PIT'; break;

            case 11: return 'DEN'; break;

            case 12: return 'KC'; break;

            case 13: return 'OAK'; break;

            case 14: return 'SD'; break;

            case 15: return 'SEA'; break;

            case 16: return 'DAL'; break;

            case 17: return 'NYG'; break;

            case 18: return 'PHI'; break;

            case 19: return 'ARI'; break;

            case 20: return 'WAS'; break;

            case 21: return 'CHI'; break;

            case 22: return 'DET'; break;

            case 23: return 'GB'; break;

            case 24: return 'MIN'; break;

            case 25: return 'TB'; break;

            case 26: return 'ATL'; break;

            case 27: return 'CAR'; break;

            case 28: return 'LA'; break;

            case 29: return 'NO'; break;

            case 30: return 'SF'; break;

            case 31: return 'BAL'; break;

            case 32: return 'HOU'; break;

        }

    }

}

