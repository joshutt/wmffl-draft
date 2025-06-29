<?php
/**
 * Table Definition for comments
 */
require_once 'DB/DataObject.php';

class DataObjects_Comments extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'comments';            // table name
    public $comment_id;                      // int(11)  not_null primary_key multiple_key auto_increment
    public $article_id;                      // int(11)  not_null multiple_key
    public $parent_id;                       // int(11)  multiple_key
    public $comment_text;                    // blob(65535)  not_null blob
    public $author_id;                       // int(11)  not_null
    public $date_created;                    // timestamp(19)  not_null binary timestamp
    public $active;                          // int(1)  

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
