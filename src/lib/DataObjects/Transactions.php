<?php
/**
 * Table Definition for transactions
 */
require_once 'DB/DataObject.php';

class DataObjects_Transactions extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'transactions';        // table name
    public $TransactionID;                   // int(11)  not_null primary_key auto_increment
    public $TeamID;                          // int(11)  not_null
    public $PlayerID;                        // int(11)  not_null
    public $Method;                          // string(5)  not_null enum
    public $Date;                            // datetime(19)  not_null binary

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
