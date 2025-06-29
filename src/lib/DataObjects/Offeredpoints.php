<?php
/**
 * Table Definition for offeredpoints
 */
require_once 'DB/DataObject.php';

class DataObjects_Offeredpoints extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'offeredpoints';       // table name
    public $OfferID;                         // int(11)  not_null primary_key
    public $TeamFromID;                      // int(11)  not_null primary_key
    public $Season;                          // year(4)  not_null primary_key unsigned zerofill
    public $Points;                          // int(4)  not_null primary_key

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
