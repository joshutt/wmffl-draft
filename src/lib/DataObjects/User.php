<?php
/**
 * Table Definition for user
 */
require_once 'DB/DataObject.php';

class DataObjects_User extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'user';                // table name
    public $UserID;                          // int(11)  not_null primary_key auto_increment
    public $TeamID;                          // int(11)  
    public $Username;                        // string(20)  not_null unique_key
    public $Password;                        // string(50)  not_null
    public $Name;                            // string(50)  
    public $Email;                           // string(75)  not_null
    public $primaryowner;                    // int(1)  not_null
    public $lastlog;                         // datetime(19)  binary
    public $blogaddress;                     // string(75)  
    public $active;                          // string(1)  not_null enum
    public $commish;                         // int(4)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
