<?php
class User_model extends MY_Model {
	
	private $_table = "user";
	
    function __construct() {
		parent::__construct();
    }
	

	// Obtiene usuario por ANI en la whiteList
    public function getUserBywhiteList($ani) {
		return $this->_getUserwhiteList("ani", $ani);
		}
		
		// Crea usuario
		public function addUserNotlogin($o) {
			if($this->db->insert('usernotlogin', $o))
				return $this->db->insert_id();
							
					return NULL;
		}
		// Obtiene usuario por ANI
    public function getUserByAni($ani) {
			return $this->_getUser("ani", $ani);
			}
	
	// Obtiene usuario por idUser
    public function getUserByIdUser($idUser) {
		return $this->_getUser("idUser", $idUser);
    }
	
	// Obtiene usuario por vendorId
    public function getUserByVendorId($vendorId) {
		return $this->_getUser("vendorId", $vendorId);
    }
	
	// Crea usuario
	public function createUser($o) {
		if($this->db->insert($this->_table, $o))
			return $this->db->insert_id();
            
        return NULL;
	}
	
	// Actualiza el atributo que sea del usuario
	public function updateAttributeUser($idUser, $param, $value) {
		
		$data = array($param => $value,
						"modificationDate" => date("Y-m-d H:i:s"));
		$this->db->where("idUser", $idUser);
		
		return $this->db->update($this->_table, $data);
		
	}
	
	
	// Para buscar usuario en función del atributo que llega por parámetro
	private function _getUser($param, $value) {
		
		$res = $this->db->get_where($this->_table, array($param => $value));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
		
	}

		// Para buscar usuario en función del atributo que llega por parámetro a la lista blanca
		private function _getUserwhiteList($param, $value) {
			$res = $this->db->get_where("whitelist", array("ani" => $value));
			
			if($res->num_rows() > 0) return $res->row();
			else return NULL;
			
		}
	
	
}
?>
