<?php
class Trx_model extends MY_Model {
	
	private $_table = "trx";
	
    function __construct() {
		parent::__construct();
    }
	

	// Nuevo registro (compra de bolsa)
    public function create($o) {
		if($this->db->insert($this->_table, $o))
			return $this->db->insert_id();
            
        return NULL;
    }
	
	
	// Obtiene transacción por trx
    public function getTransactionByTrx($trx) {
		$this->db->select("t.*, b.value");
		$this->db->from("trx t");
		$this->db->join("bag b", "t.idBag = b.idBag");
		$this->db->where(array("t.trx" => $trx));
		
		$res = $this->db->get();
		
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
    }
	
	
	// Obtiene transacción con idTrx
	public function getTrxByIdTrx($idTrx) {
		$this->db->select("t.*, b.value");
		$this->db->from("trx t");
		$this->db->join("bag b", "t.idBag = b.idBag");
		$this->db->where(array("t.idTrx" => $idTrx));
		
		$res = $this->db->get();
		
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
    }
	
	
	// Obtiene transacciones por usuario, rango de fecha y estado
	public function getTotalPurchasesByUser($idUser, $fStart, $fEnd, $idTrxState) {
		$this->db->select("t.*, b.name, b.value");
		$this->db->from("trx t, bag b");
		$this->db->where("t.idUser = $idUser
							AND t.idTrxState = $idTrxState
							AND t.idBag = b.idBag
							AND (t.creationDate BETWEEN '$fStart' AND '$fEnd')");
		
		$res = $this->db->get();
		
		if($res->num_rows() > 0) return $res->result();
		else return NULL;
	}
	
	// Obtiene transacciones por usuario, estado  e id de bolsa
	public function getTotalPurchasesIDbagByUser($idUser, $idBag, $idTrxState) {
		$res = $this->db->get_where($this->_table, array(
			"idUser" => $idUser,
			"idTrxState" => $idTrxState,
			"idBag" => $idBag)
		);
		
	   if($res->num_rows() > 0) return $res->result();
		else return NULL;
	}
	
	// Todas las transacciones que no han finalizado
	public function getAllIncompleteTrxs() {
		$this->db->select("t.*, u.ani, b.name, b.value, ts.name as 'stateName', ts.idTypeState as 'stateId', ts.description as 'stateDescription'");
		$this->db->from("trx t, bag b, user u, trxstate ts");
		$this->db->where("  t.idBag = b.idBag
							AND t.idUser = u.idUser
							AND t.idTrxState = ts.idTrxState ORDER BY DATE(t.creationDate) DESC");
		
		$res = $this->db->get();
		
		if($res->num_rows() > 0) return $res->result();
		else return NULL;
	}
	
	// Buscar Transacciones por palabra clave
	public function searchAllTrxs($key) {

		$this->db->select("t.*, u.ani, b.name, b.value, ts.name as 'stateName', ts.idTypeState as 'stateId', ts.description as 'stateDescription'");
		$this->db->from("trx t, bag b, user u, trxstate ts");
		$this->db->where("  t.idBag = b.idBag
							AND t.idUser = u.idUser
							AND t.idTrxState = ts.idTrxState
							AND u.ani = ".$key."");
		
		$res = $this->db->get();
		
		if($res->num_rows() > 0) return $res->result();
		else return NULL;
    }
	
	// Transacciones en función del estado
	public function getAllTrxsByState($idTrxState) {
		$this->db->select("t.*, u.ani, b.name, b.value, ts.name as 'stateName', ts.description as 'stateDescription'");
		$this->db->from("trx t, bag b, trxstate ts, user u");
		$this->db->where(" t.idTrxState = $idTrxState
							AND t.idBag = b.idBag
							AND t.idTrxState = ts.idTrxState
							AND t.idUser = u.idUser");
		
		$res = $this->db->get();
		
		if($res->num_rows() > 0) return $res->result();
		else return NULL;
    }
	
	
	
	// Obtiene transacción por id del usuario y código promocional
    public function getTrxByIdUserAndCode($idUser, $code, $idTrxState) {
		
		$res = $this->db->get_where($this->_table, array(
															"idUser" => $idUser,
															"idTrxState" => $idTrxState,
															"code" => $code)
														);
		
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
    }
	
	
	// Actualiza estado de transacción
	public function updateStateTrx($idTrx, $idTrxState) {
		
		$data = array("idTrxState" => $idTrxState, "modificationDate" => date("Y-m-d H:i:s"));
		$this->db->where("idTrx", $idTrx);
		
		return $this->db->update($this->_table, $data);
		
	}
	
	
	/**
	 * Actualiza cualquier campo de la transacción
	 */
	public function updateTrx($idTrx, $field, $value) {
		
		$data = array($field => $value,
						"modificationDate" => date("Y-m-d H:i:s"));
						
		$this->db->where("idTrx", $idTrx);
		
		return $this->db->update($this->_table, $data);
		
	}
	
	
	/**
	 * Transacciones
	 */
	function inicioTrx() {
		$this->db->trans_begin();
	}
	function commitTrx() {
		$this->db->trans_commit();
	}
	function rollbackTrx() {
		$this->db->trans_rollback();
	}
	
}
?>
