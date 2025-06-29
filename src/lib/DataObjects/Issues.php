<?php
/**
 * Table Definition for issues
 */
require_once 'DB/DataObject.php';

class DataObjects_Issues extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'issues';              // table name
    public $IssueID;                         // int(11)  not_null primary_key multiple_key auto_increment
    public $IssueNum;                        // string(10)  not_null
    public $IssueName;                       // string(40)  not_null
    public $Sponsor;                         // int(11)  not_null
    public $Description;                     // blob(255)  blob
    public $Season;                          // year(4)  not_null unsigned zerofill
    public $Deadline;                        // date(10)  binary
    public $StartDate;                       // date(10)  binary
    public $Result;                          // string(10)  

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
