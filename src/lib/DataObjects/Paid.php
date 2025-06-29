<?php
/**
 * Table Definition for paid
 */
require_once 'DB/DataObject.php';

class DataObjects_Paid extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'paid';                // table name
    public $teamid;                          // int(11)  multiple_key
    public $season;                          // int(11)  
    public $previous;                        // real(12)  
    public $entry_fee;                       // real(12)  
    public $late_fee;                        // real(12)  
    public $paid;                            // int(1)  

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
