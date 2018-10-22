<?php
class Sms_model extends MY_Model {
	
	private $_table = "sms";
	
    function __construct() {
		parent::__construct();
    }
	
	// Registra SMS
	public function createSms($o) {
		if($this->db->insert($this->_table, $o))
			return $this->db->insert_id();
            
        return NULL;
	}
	
	
	public function update($idSms, $o) {
		
		$o->modificationDate = date("Y-m-d H:i:s");
		
		$this->db->where("idSms", $idSms);
		
		return $this->db->update($this->_table, $o);
	}
	
	
}
?>
