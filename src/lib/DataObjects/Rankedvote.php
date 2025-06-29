<?php
/**
 * Table Definition for rankedvote
 */
require_once 'DB/DataObject.php';

class DataObjects_Rankedvote extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'rankedvote';          // table name
    public $issueid;                         // int(11)  not_null primary_key
    public $choice;                          // string(50)  not_null primary_key
    public $teamid;                          // int(11)  not_null primary_key
    public $rank;                            // int(11)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
