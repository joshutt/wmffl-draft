<?php
/**
 * Table Definition for offeredplayers
 */
require_once 'DB/DataObject.php';

class DataObjects_Offeredplayers extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'offeredplayers';      // table name
    public $OfferID;                         // int(11)  not_null primary_key
    public $TeamFromID;                      // int(11)  not_null primary_key
    public $PlayerID;                        // int(11)  not_null primary_key

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
