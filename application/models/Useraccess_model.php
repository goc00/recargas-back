<?php
class Useraccess_model extends MY_Model {
	
	private $_table = "useraccess";
	
    function __construct() {
		parent::__construct();
    }
	
	// Crea registro
	public function create($o) {
		if($this->db->insert($this->_table, $o))
			return $this->db->insert_id();
            
        return NULL;
	}
	
	// Actualiza información de useraccess
	public function update($idUserAccess, $data) {

		$this->db->where("idUserAccess", $idUserAccess);
	
		return $this->db->update($this->_table, $data);
	}
	
	
}
?>
