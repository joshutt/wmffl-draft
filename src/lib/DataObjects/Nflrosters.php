<?php
/**
 * Table Definition for nflrosters
 */
require_once 'DB/DataObject.php';

class DataObjects_Nflrosters extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'nflrosters';          // table name
    public $playerid;                        // int(11)  not_null primary_key multiple_key
    public $nflteamid;                       // string(3)  not_null primary_key
    public $dateon;                          // date(10)  not_null primary_key binary
    public $dateoff;                         // date(10)  binary
    public $pos;                             // string(3)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function save() {
        if ($this->N == 0) {
            $this->insert();
        } else {
            $this->update();
        }
    }
}
