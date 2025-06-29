<?php
/**
 * Table Definition for ballot
 */
require_once 'DB/DataObject.php';

class DataObjects_Ballot extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'ballot';              // table name
    public $TeamID;                          // int(11)  not_null primary_key
    public $IssueID;                         // int(11)  not_null primary_key
    public $Result;                          // int(4)  
    public $Vote;                            // string(7)  enum

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
