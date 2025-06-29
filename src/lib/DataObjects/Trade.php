<?php
/**
 * Table Definition for trade
 */
require_once 'DB/DataObject.php';

class DataObjects_Trade extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'trade';               // table name
    public $TradeID;                         // int(11)  not_null primary_key unique_key auto_increment
    public $TeamFromID;                      // int(11)  not_null
    public $TeamToID;                        // int(11)  not_null
    public $PlayerID;                        // int(11)  
    public $Other;                           // blob(65535)  blob
    public $Date;                            // date(10)  not_null binary
    public $TradeGroup;                      // int(11)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
