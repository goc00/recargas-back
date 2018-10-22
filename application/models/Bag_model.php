<?php
class Bag_model extends MY_Model {
	
	private $_table = "bag";
	
    function __construct() {
		parent::__construct();
    }
	
	// Listado de bolsas disponibles
	public function getBags() {
		
		$this->db->order_by("order", "ASC");
		$res = $this->db->get($this->_table);
		if($res->num_rows() > 0) return $res->result();
		else return NULL;
		
	}
	
	
	// Obtiene bolsa por su ID
	public function getBagByIdBag($idBag) {
		
		$res = $this->db->get_where($this->_table, array("idBag" => $idBag));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
		
	}
	
	// Listado de métodos de pago por idBag
	public function getPaymentsByIdBag($idBag) {
		$res = $this->db->get_where("bagpaymenttype", array("idBag" => $idBag));
		if($res->num_rows() > 0) return $res->result();
		else return NULL;
	}
	
}
?>
