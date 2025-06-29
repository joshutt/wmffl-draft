<?php
/**
 * Table Definition for offeredpicks
 */
require_once 'DB/DataObject.php';

class DataObjects_Offeredpicks extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'offeredpicks';        // table name
    public $OfferID;                         // int(11)  not_null primary_key
    public $TeamFromID;                      // int(11)  not_null primary_key
    public $Season;                          // year(4)  not_null primary_key unsigned zerofill
    public $Round;                           // int(4)  not_null primary_key
    public $OrgTeam;                         // int(11)  

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
