<?php
/**
 * Table Definition for config
 */
require_once 'DB/DataObject.php';

class DataObjects_Config extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'config';              // table name
    public $key;                             // string(255)  not_null primary_key
    public $value;                           // string(255)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
