<?php
class Trxstate_model extends MY_Model {
	
	private $_table = "trxstate";
	
    function __construct() {
		parent::__construct();
    }
	
	
    public function getByIdTrxState($idTrxState) {
		
		$res = $this->db->get_where($this->_table, array("idTrxState" => $idTrxState));
		
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
    }

}
?>
