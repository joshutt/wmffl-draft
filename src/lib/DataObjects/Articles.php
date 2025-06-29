<?php
/**
 * Table Definition for articles
 */
require_once 'DB/DataObject.php';

class DataObjects_Articles extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'articles';            // table name
    public $articleId;                       // int(11)  not_null primary_key auto_increment
    public $title;                           // string(75)  not_null
    public $link;                            // string(255)  
    public $caption;                         // string(255)  
    public $location;                        // string(50)  
    public $articleText;                     // blob(65535)  blob
    public $displayDate;                     // datetime(19)  not_null binary
    public $active;                          // int(1)  not_null
    public $priority;                        // int(11)  not_null
    public $author;                          // int(11)  

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
