<?php
/**
 * Table Definition for chat
 */
require_once 'DB/DataObject.php';

class DataObjects_Chat extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'chat';                // table name
    public $messageId;                       // int(11)  not_null primary_key auto_increment
    public $userid;                          // int(11)  not_null
    public $message;                         // string(255)  not_null
    public $time;                            // timestamp(19)  not_null binary timestamp

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
