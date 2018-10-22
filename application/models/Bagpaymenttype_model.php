<?php
class Bagpaymenttype_model extends MY_Model {
	
	private $_table = "bagpaymenttype";
	
    function __construct() {
		parent::__construct();
    }
	
	// Listado de bolsas disponibles
	// int, Array
	public function createPt4Bag($idBag, $idPaymentType) {
		
		$o = new stdClass();
		$o->idBag = $idBag;
		$o->idPaymentType = $idPaymentType;
		$o->creationDate = date("Y-m-d H:i:s");
		
		if($this->db->insert($this->_table, $o))
			return $this->db->insert_id();
            
        return NULL;
		
	}
	
	// Elimina los registros relacionado a la bolsa
	public function deletePts4IdBag($idBag) {
		return $this->db->delete($this->_table, array("idBag" => $idBag));
	}

}
?>
