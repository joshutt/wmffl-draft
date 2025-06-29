<?php

/**

 * Table Definition for NFLTransactions

 */

require_once 'DB/DataObject.php';


class DataObjects_Nfltransactions extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'nfltransactions';     // table name
    public $playerid;                        // int(11)  not_null primary_key
    public $transdate;                       // date(10)  not_null primary_key binary
    public $action;                          // string(7)  not_null enum
    public $team;                            // string(3)  
    public $flag;                            // int(11)  

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE


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






    function getActionFromNum($num) {

        switch($num) {

            case 1: return "Signed"; break;

            case 2: return "Cut"; break;

            case 3: return "IR"; break;

            case 7: return "Trade"; break;

            case 8: return "Draft"; break;

            case 9: return "Retired"; break;

        }

    }



    function getEvent() {

        switch($this->action) {

            case "Signed":

            case "Trade" :

            case "Draft" :

                return "ADD";

                break;

            case "Cut"     :

            case "Retired" :

                return "DROP";

                break;

            default :

                return "NONE";

        }

    }

}

