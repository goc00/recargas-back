<?php
class MY_Model extends CI_Model {
    public function __construct() {
        parent::__construct();
		date_default_timezone_set("America/Santiago");
    }
}
?>