<?php
/**
 * Table Definition for tmp_players
 */
require_once 'DB/DataObject.php';

class DataObjects_Tmp_players extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'tmp_players';         // table name
    public $playerid;                        // int(11)  not_null primary_key
    public $season;                          // year(4)  not_null primary_key unsigned zerofill
    public $pts;                             // int(11)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
