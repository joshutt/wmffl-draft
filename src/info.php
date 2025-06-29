<?php 
//putenv("TZ=US/Eastern");

class GenerateInfo {
    
    public function getDate() {
        return date("D M j G:i:s Y");
    }

    public function getInfo() {
        phpinfo();
    }

    public function getTime() {
        return microtime(true);
    }

}

$info = new GenerateInfo;
print $info->getDate();
$info->getInfo();
print $info->getTime();

?>
