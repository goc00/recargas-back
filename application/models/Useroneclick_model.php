<?php
class Useroneclick_model extends MY_Model {
	
	private $_table = "useroneclick";
	
    function __construct() {
		parent::__construct();
    }
	
	// Obtiene usuario por idUser
    public function getUserOneclickByIdUser($idUser) {
		$res = $this->db->get_where($this->_table, array("idUser" => $idUser));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
    }
	
	// Crea usuario
	public function create($o) {
		if($this->db->insert($this->_table, $o))
			return $this->db->insert_id();
            
        return NULL;
	}
	
	// Elimina cuenta por idUserOneclick
	public function deleteByIdUserOneclick($idUserOneclick) {
		return $this->db->delete($this->_table, array("idUserOneclick" => $idUserOneclick));
	}
	
}
?>
