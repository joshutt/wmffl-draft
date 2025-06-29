<?php
/**
 * Table Definition for draftvote
 */
require_once 'DB/DataObject.php';

class DataObjects_Draftvote extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'draftvote';           // table name
    public $userid;                          // int(11)  not_null primary_key
    public $season;                          // year(4)  not_null primary_key unsigned zerofill
    public $lastUpdate;                      // datetime(19)  binary

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
