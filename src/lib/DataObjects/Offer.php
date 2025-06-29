<?php
/**
 * Table Definition for offer
 */
require_once 'DB/DataObject.php';

class DataObjects_Offer extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'offer';               // table name
    public $OfferID;                         // int(11)  not_null primary_key auto_increment
    public $TeamAID;                         // int(11)  not_null
    public $TeamBID;                         // int(11)  not_null
    public $Status;                          // string(9)  enum
    public $Date;                            // datetime(19)  not_null binary
    public $LastOfferID;                     // int(11)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
