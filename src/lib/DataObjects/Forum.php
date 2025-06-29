<?php
/**
 * Table Definition for forum
 */
require_once 'DB/DataObject.php';

class DataObjects_Forum extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'forum';               // table name
    public $forumid;                         // int(11)  not_null primary_key auto_increment
    public $userid;                          // int(11)  not_null
    public $title;                           // string(255)  not_null
    public $body;                            // blob(65535)  not_null blob
    public $createTime;                      // datetime(19)  not_null binary

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
