<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Listado de métodos disponibles para consumir como servicios REST
 * Desarrollado por: Gastón Orellana C.
 * Fecha: 26/01/2017
 *
 * Métodos disponibles:
 * --------------------
 * IsAniValidMovistar()
 * GetUserByAni()
*/
class Core extends MY_Controller {

	// ID operadores
	const ID_OPERATOR_MOVISTAR						= 1;
	const ID_OPERATOR_ENTEL							= 2;
					
	const ACTIVE_USER								= 1; // Activo
	const NO_CONFIRMED_USER							= 2; // Sin confirmar
	const INACTIVE_USER								= 3; // Inactivo
	const DISABLED_USER								= 4; // Deshabilitado
					
	const LENGTH_CODE								= 4; // Número de caracteres
			
	// Estados de transacción				
	const NEW_TRX									= 1; // Nueva transacción
	const CANCEL_TRX								= 2; // Transacción anulada o cancelada
	const ERR_TRX									= 3; // Transacción ERROR
	const OK_TRX									= 4; // Transacción OK				
	const TRY_LOAD_BAG								= 6; // Intento de carga de bolsa
	const NO_USER_LOAD_BAG							= 7; // No se pudo identificar usuario cargando bolsa
	const INACTIVE_USER_LOAD_BAG					= 8; // El usuario está inactivo cargando bolsa
	const NO_BAG_LOAD_BAG							= 9; // No se pudo determinar la bolsa en la trx
	const FAILED_LOAD_BAG							= 10; // Fallo en la carga de la bolsa
	const FAILED_PAYMENT_TRX						= 17; // Falló el pago de la bolsa
	const OK_PAYMENT_TRX							= 18; // Pago de bolsa realizado correctamente
	const AWAITING_LOAD_BAG							= 19; // Pago OK, será procesado por el inyector de carga de bolsas
	const PROCESSING_LOAD_BAG						= 20; // La bolsa está siendo procesada por el inyector
	
	const NO_SEGMENT_CLIENT							= 21; 
	const REACHED_MAX_LOADS							= 22;
	
	const RESOLVING_NO_SEGMENT_CLIENT				= 23; 

	public function __construct() {
		
		parent::__construct();
		
		$this->load->helper('crypto');
		$this->load->helper('string');
		$this->load->model('user_model', '', TRUE);
		$this->load->model('useroneclick_model', '', TRUE);
		$this->load->model('useraccess_model', '', TRUE);
		$this->load->model('sms_model', '', TRUE);
		$this->load->model('bag_model', '', TRUE);
		$this->load->model('trx_model', '', TRUE);
		$this->load->model('trxstate_model', '', TRUE);
		$this->load->model('bagpaymenttype_model', '', TRUE);
	}
/*public function index(){
	echo "hola mundo";
}*/
	/**
	 * Obtiene valor de parámetro de configuración
	 */
	public function GetConfigItem() {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		$salida->result = NULL;
		
		try {
			
			$salida->result = $this->config->item($this->input->post("name"));
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		$this->outputJson($salida);
		
	}
	
	
	
	/**
	 * Recibe y verifica contra servicio telcordia si ANI es Movistar o no
	 * 
	 */
	public function IsAniValidMovistar() {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->result = NULL;
		$salida->message = "";
		
		try {
			
			$ani = trim($this->input->post("ani"));
			log_message("debug", "REQUEST IsAniValidMovistar(): ".print_r($ani, TRUE));
			
			// Verifica si el ANI es Movistar o no
			$urlValidateAni = $this->config->item("ValidateAniService");
			$urlValidateAni = str_replace("{NROMOVIL}", $ani, $urlValidateAni);

			$doGet = $this->doGet($urlValidateAni);
			if(empty($doGet)) throw new Exception("No se pudo procesar la solicitud. Por favor, inténtalo nuevamente", 1001);
			
			// Si llega acá, hay respuesta concreta, así que convierte a objeto la respuesta JSON
			$oGet = json_decode($doGet);
			$idOperator = $oGet->rows[0]->id_operador;
			$nameOperator = $oGet->rows[0]->nombre_operador;
			/*$idOperator = 2;
			$nameOperator = "TEST_OPE";*/
			
			if((int)$idOperator != self::ID_OPERATOR_MOVISTAR) {
				
				// No hay error, pero el usuario no es Movistar
				// Crea un registro para posteriormente solicitar email
				$o = new stdClass();
				$o->idOperatorNoUser = $idOperator;
				$o->aniNoUser = $ani;
				$o->nameOperatorNoUser = $nameOperator;
				$o->isUser = 0;
				$o->creationDate = date("Y-m-d H:i:s");
				$res = $this->useraccess_model->create($o);
				
				// No se pudo registrar la información
				if(is_null($res)) throw new Exception("No se pudo registrar al usuario", 1002);
					
				// En este punto, info del usuario insertada correctamente
				$salida->code = 1; // Significa que es otro operador
				$salida->result = $res; // ID del acceso relacionado
				
				throw new Exception("¡Ups!, pronto tú también podrás usar GigaGo", 1);
			}
			
			$salida->code = 0; // Usuario "OK", es Movistar
			$salida->message = "OK";
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", "ERROR IsAniValidMovistar() -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE IsAniValidMovistar(): ".print_r($salida, TRUE));
		$this->outputJson($salida);
	}
	
	
	/**
	 * Actualiza el acceso del usuario con el correo recibido
	 * 
	 */
	public function UpdateUserAccessWithEmail() {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->result = NULL;
		$salida->message = "";
		
		try {
			
			$idUserAccess = trim($this->input->post("idUserAccess"));
			$emailNoUser = trim($this->input->post("emailNoUser"));
		
			// Intenta hacer update al acceso con el email
			$o = new stdClass();
			$o->emailNoUser = $emailNoUser;
			$res = $this->useraccess_model->update($idUserAccess, $o);
			
			if(empty($res)) throw new Exception("No se pudo actualizar el acceso con el email proporcionado", 1001);
			
			$salida->code = 0; // Usuario "OK", es Movistar
			$salida->message = "OK";
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", "ERROR " . __METHOD__ . " > " .$e->getMessage());
		}
		

		$this->outputJson($salida);
	}
	
	
	
	/**
	 * Obtiene usuario por su ANI
	 * 
	 */
	public function GetUserByAni() {
		
		
		$salida = new stdClass();
		$salida->code = 0;
		$salida->result = 0;
	
		try {
			
			$ani = trim($this->input->post("ani"));
			log_message("debug", "REQUEST GetUserByAni(): ".print_r($_POST, TRUE));
			
			if(empty($ani)) throw new Exception("No se pudo procesar la solicitud. Por favor, inténtalo nuevamente", 1001);
		
			$oUser = $this->user_model->getUserByAni($ani);
           
			// Evalúa si el usuario está activo o no
			if(!is_null($oUser)) {
				$oUser->isActive = $oUser->idUserState == self::ACTIVE_USER ? 1 : 0;
				$oUser->must2BeActived = $oUser->idUserState == self::NO_CONFIRMED_USER ? 1 : 0;
			}
			
 			$salida->result = $oUser;
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			log_message("error", "ERROR GetUserByAni() -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE GetUserByAni(): ".print_r($salida, TRUE));
		$this->outputJson($salida);
	}
	

    	/**
	 * Obtiene usuario por su ANI lista blanca
	 * 
	 */
	public function GetUserByAniwhiteList() {
		
		
		$salida = new stdClass();
		$salida->code = 0;
		$salida->result = 0;
	
		try {
			
			
			$ani = trim($this->input->post("ani"));
			log_message("debug", "REQUEST GetUserByAniwhiteList(): ".print_r($_POST, TRUE));
			
			if(empty($ani)) throw new Exception("No se pudo procesar la solicitud. Por favor, inténtalo nuevamente", 1001);
			$oUserWhiteList = $this->user_model->getUserBywhiteList($ani);
	
			$salida->result = $oUserWhiteList;
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			log_message("error", "ERROR GetUserByAniwhiteList() -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE GetUserByAniwhiteList(): ".print_r($salida, TRUE));
		$this->outputJson($salida);
	}

	/**
	 * Obtiene usuario por idUser
	 * Valida también que esté activo o no
	 *
	 * @return json 
	 */
	public function GetUserByIdUser() {
		
		
		$salida = new stdClass();
		$salida->code = 0;
		$salida->result = 0;
		
		try {
			
			$idUser = trim($this->input->post("idUser"));
			log_message("debug", "REQUEST ". __METHOD__ .": ".print_r($_POST, TRUE));
			
			if(empty($idUser)) throw new Exception("No se pudo procesar la solicitud. Por favor, inténtalo nuevamente", 1001);
			
			$oUser = $this->user_model->getUserByIdUser($idUser);

			// Evalúa si el usuario está activo o no
			if(!is_null($oUser)) {
				$oUser->isActive = $oUser->idUserState == self::ACTIVE_USER ? 1 : 0;
				$oUser->must2BeActived = $oUser->idUserState == self::NO_CONFIRMED_USER ? 1 : 0;
			}
			
			$salida->result = $oUser;
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
	}
	
	
	/**
	 * Obtiene usuario por vendorId
	 *
	 * @return json 
	 */
	public function getUserByVendorId() {
		
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "No actions";
		$salida->result = NULL;
		
		try {
			
			$vendorId = trim($this->input->post("vendorId"));
			log_message("debug", "REQUEST ". __METHOD__ .": ".print_r($_POST, TRUE));
			if(empty($vendorId)) throw new Exception("No se pudo identificar la instalación", 1000);
			
			// Busca usuario por el vendorId
			$oUser = $this->user_model->getUserByVendorId($vendorId);

			// Evalúa si el usuario está activo o no
			if(is_null($oUser)) throw new Exception("No se pudo identificar el usuario", 1001);
			if($oUser->idUserState != self::ACTIVE_USER) throw new Exception("El usuario no se encuentra activo", 1002);
			
			// Está todo OK, lo devuelve como resultado
			$salida->code = 0;
			$salida->result = $oUser;
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
	}
	
	
	/**
	 * Actualiza cualquier atributo del usuario
	 *
	 * @return json 
	 */
	public function updateAttributeUser() {
		
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "No actions";
		$salida->result = NULL;
		
		try {
			
			$idUser = trim($this->input->post("idUser"));
			$param = trim($this->input->post("param"));
			$value = trim($this->input->post("value"));
			log_message("debug", "REQUEST ". __METHOD__ .": ".print_r($_POST, TRUE));
			if(empty($idUser)) throw new Exception("No se pudo determinar el identificador del dispositivo", 1000);
			if(empty($param)) throw new Exception("No se pudo determinar el atributo a actualizar", 1000);
			if(empty($value)) throw new Exception("No se pudo determinar el valor del atributo a actualizar", 1000);
			
			// Actualiza el valor
			$res = $this->user_model->updateAttributeUser($idUser, $param, $value);

			if(!($res)) {
				$salida->result = FALSE;
				throw new Exception("No se pudo identificar el usuario", 1001);
			}
			
			$salida->code = 0;
			$salida->result = TRUE;
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
	}
	
	
	/**
	 * Crea usuario
	 * 
	 */
	public function CreateUser() {
		
		
		$salida = new stdClass();
		$salida->code = 0;
		$salida->result = 0;
		
		try {
			
			$ani = trim($this->input->post("ani"));
			log_message("debug", "REQUEST ". __METHOD__ .": ".print_r($_POST, TRUE));
			
			if(empty($ani)) throw new Exception("No se pudo procesar la solicitud. Por favor, inténtalo nuevamente", 1001);
		
			// Completa atributos de Usuario
			$o = new stdClass();
			$o->idUserState = self::NO_CONFIRMED_USER; // sin confirmar
			$o->idOperatorCountry = self::ID_OPERATOR_MOVISTAR;
			$o->ani = $ani;
			$o->email = $ani."_recargas@3gmotion.com";
			$o->authorizationCode = $this->CreateAuthorizationCode(TRUE)->result;
			$o->creationDate = date("Y-m-d H:i:s");

			// Crea usuario
			$res = $this->user_model->createUser($o);
			if(is_null($res)) throw new Exception("No se pudo crear el usuario en el sistema", 1002);
			
			// Todo OK
			$o->idUser = $res;
			$salida->result = $o; // devuelve
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
	}
	

	/**
	 * Inserta ani en lista de usuarios no login
	 * 
	 */
	public function AddUserNotlogin() {
		
		
		$salida = new stdClass();
		$salida->code = 0;
		$salida->result = 0;
		
		try {
			
			$ani = trim($this->input->post("ani"));
			log_message("debug", "REQUEST ". __METHOD__ .": ".print_r($_POST, TRUE));
			
			if(empty($ani)) throw new Exception("No se pudo procesar la solicitud. Por favor, inténtalo nuevamente", 1001);
		
			// Completa atributos de Usuario
			$o = new stdClass();
			
			$o->ani = $ani;
		   

			// Crea usuario
			$res = $this->user_model->addUserNotlogin($o);
			if(is_null($res)) throw new Exception("No se pudo crear el usuario en el sistema", 1002);
			
			// Todo OK
			$o->idUser = $res;
			$salida->result = $o; // devuelve
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
	}
	
	/**
	 * Activa usuario (lo pasa a estado = 1)
	 * 
	 */
	public function ActiveUser() {
		
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		//$salida->result = NULL;
		
		try {
			
			$idUser = trim($this->input->post("idUser"));
			log_message("debug", "REQUEST ". __METHOD__ .": ".print_r($_POST, TRUE));
			
			if(empty($idUser)) throw new Exception("No se pudo procesar la solicitud. Por favor, inténtalo nuevamente", 1001);

			// Actualiza el estado del usuario
			$res = $this->user_model->updateAttributeUser($idUser, "idUserState", self::ACTIVE_USER);
			if(!$res) throw new Exception("No se pudo actualizar el estado del usuario", 1002);
			
			// OK
			$salida->code = 0;
			//$salida->result = $o; // devuelve
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getCode();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
	}
	
	
	/**
	 * Crea código de autorización
	 * 
	 */
	public function CreateAuthorizationCode($noJson = NULL) {
		
		
		$salida = new stdClass();
		$salida->code = 0;
		$salida->result = 0;
		
		try {

			$salida->result = random_string("numeric", self::LENGTH_CODE);
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		
		if(is_null($noJson)) $this->outputJson($salida);
		else return $salida;
	}
	
	
	/**
	 * Registra acceso del usuario al sistema
	 * 
	 */
	public function CreateUserAccess() {
		
		// useraccess_model
		$salida = new stdClass();
		$salida->code = 0;
		$salida->result = 0;
		
		try {
			
			// POST
			$idUser = trim($this->input->post("idUser"));
			log_message("debug", "REQUEST ". __METHOD__ .": ".print_r($_POST, TRUE));
			
			if(empty($idUser)) throw new Exception("No se han recibido los valores necesarios para la operación", 1000);
			
			$o = new stdClass();
			$o->idUser = $idUser;
			$o->isUser = 1;
			$o->creationDate = date("Y-m-d H:i:s");
			
			$res = $this->useraccess_model->create($o);
			if(is_null($res)) throw new Exception("No se pudo registrar el acceso del usuario", 1001);
			
			$salida->result = $res;

		} catch(Exception $e) {
			$salida->code = $e->getCode();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		
		$this->outputJson($salida);
	}
	

	/**
	 * Permite codificar/decodificar el contenido en el mismo ambiente, con esto se aumenta
	 * sustancialmente la seguridad al ser solo el módulo de pines quien "sabe"
	 * efectivamente los valores concretos
	 */
	public function DecodeData() {
		
		$salida = new stdClass();
		$salida->code = 0;
		$salida->result = 0;
		
		try {
			
			// POST
			$data = trim($this->input->post("data"));
			log_message("debug", "REQUEST ". __METHOD__ .": ".print_r($_POST, TRUE));
			
			if(empty($data)) throw new Exception("No se han recibido los valores necesarios para la operación", 1001);

			$salida->result = decode_url($data);

		} catch(Exception $e) {
			$salida->code = $e->getCode();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		
		$this->outputJson($salida);
		
	}
	public function EncodeData() {
		
		$salida = new stdClass();
		$salida->code = 0;
		$salida->result = 0;
		
		try {
			
			// POST
			$data = trim($this->input->post("data"));
			log_message("debug", "REQUEST ". __METHOD__ .": ".print_r($_POST, TRUE));
			
			if(empty($data)) throw new Exception("No se han recibido los valores necesarios para la operación", 1001);

			$salida->result = encode_url($data);

		} catch(Exception $e) {
			$salida->code = $e->getCode();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		
		$this->outputJson($salida);
		
	}
	
	
	
	/**
	 * Permite codificar/decodificar contra el motor de pines
	 */
	public function DecodeDataPines() {
		
		$salida = new stdClass();
		$salida->code = 0;
		$salida->result = 0;
		
		try {
			
			// POST
			$data = trim($this->input->post("data"));
			log_message("debug", "REQUEST ". __METHOD__ .": ".print_r($_POST, TRUE));
			
			if(empty($data)) throw new Exception("No se han recibido los valores necesarios para la operación", 1001);

			$salida->result = decode_url($data);

		} catch(Exception $e) {
			$salida->code = $e->getCode();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		
		$this->outputJson($salida);
		
	}
	public function EncodeDataPines() {
		
		$salida = new stdClass();
		$salida->code = 0;
		$salida->result = 0;
		
		try {
			
			// POST
			$data = trim($this->input->post("data"));
			log_message("debug", "REQUEST ". __METHOD__ .": ".print_r($_POST, TRUE));
			
			if(empty($data)) throw new Exception("No se han recibido los valores necesarios para la operación", 1001);

			$salida->result = encode_url($data);

		} catch(Exception $e) {
			$salida->code = $e->getCode();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		
		$this->outputJson($salida);
		
	}
	
	
	
	
	/**
	 * Envío de MT a través de SecureServices
	 * 
	 */
	public function SendMt() {
		
		$salida = new stdClass();
		$salida->code = 0;
		$salida->result = 0;
		$idSms = 0;
		
		try {
		
			$xml =	'<request>'.
						'<transaction>{TRX_CLIENT}</transaction>'.
						'<user>'.
							'<login>{LOGIN_3G_MOTION}</login>'.
							'<pwd>{PASS_3G_MOTION}</pwd>'.
						'</user>'.
						'<msg>'.
							'<format>{FORMAT_MSG}</format>'.
							'<text><![CDATA[{TEXT_MSG}]]></text>'.
							'<url>{URL_MSG}</url>'.
							'<nc>{NC_MSG}</nc>'.
						'</msg>'.
						'<phone>'.
							'<ani>{ANI_PHONE}</ani>'.
							'<op>{OP_PHONE}</op>'.
						'</phone>'.
					'</request>';

			log_message("debug", "REQUEST ". __METHOD__ .": ".print_r($_POST, TRUE));
			
			$ani = trim($this->input->post("ani"));
			$text = trim($this->input->post("text"));
			$trx = trim($this->input->post("trx"));
			
			if(empty($ani)) throw new Exception("No se ha definido ningún ANI para la acción", 1001);
			if(empty($text)) throw new Exception("No se ha definido ningún texto", 1001);
			if(empty($trx)) throw new Exception("No se ha definido ninguna transacción para la acción", 1001);

			// Reemplazo de valores para completar XML
			$xml = str_replace("{TRX_CLIENT}", $trx, $xml);
			$xml = str_replace("{LOGIN_3G_MOTION}", $this->config->item("UserSecureServiceMt"), $xml);
			$xml = str_replace("{PASS_3G_MOTION}", $this->config->item("PassSecureServiceMt"), $xml);
			$xml = str_replace("{FORMAT_MSG}", $this->config->item("FormatMsgSecureServiceMt"), $xml);
			$xml = str_replace("{TEXT_MSG}", $text, $xml);
			$xml = str_replace("{URL_MSG}", "", $xml);
			$xml = str_replace("{NC_MSG}", $this->config->item("NcMsgSecureServiceMt"), $xml);
			$xml = str_replace("{ANI_PHONE}", $ani, $xml);
			$xml = str_replace("{OP_PHONE}", 0, $xml);
			
			// Registra el envío en etapa inicial
			$sms = new stdClass();
			$sms->ani = $ani;
			$sms->trxClient = $trx;
			$sms->text = $text;
			$sms->creationDate = date("Y-m-d H:i:s");
			$idSms = $this->sms_model->createSms($sms);
			
			$ch = curl_init($this->config->item("SecureServiceMt"));
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
			curl_setopt($ch, CURLOPT_POSTFIELDS, "$xml");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			
			$output = curl_exec($ch);
			curl_close($ch);
			$xmlResponse = simplexml_load_string($output);

			if(empty($xmlResponse)) throw new Exception("No se pudo procesar la solicitud al servicio de envío de MT", 1002);
			
			$code = (int)$xmlResponse->code;
			if($code != 0) throw new Exception("Error en Services/MT (cód. $cod) -> " . (string)$xmlResponse->description, $code);
			
			// Todo OK, envía cód. autorización de envío
			$salida->result = (string)$xmlResponse->transaction;
			$o = new stdClass();
			$o->codeRes = $code;
			$o->trxRes = $salida->result;
			$o->descriptionRes = (string)$xmlResponse->description;
			$this->sms_model->update($idSms, $o);

		} catch(Exception $e) {
			$salida->code = $e->getCode();
			if($idSms != 0) {
				$o = new stdClass();
				$o->codeRes = $salida->code;
				$o->descriptionRes = $e->getMessage();
				$this->sms_model->update($idSms, $o);
			}
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		
		$this->outputJson($salida);
		
	}
	
	
	/**
	 * Listado de bolsas
	 *
	 * @return json 
	 */
	public function GetListBags() {
		
		$salida = new stdClass();
		$salida->code = 0;
		$salida->result = NULL;
		
		try {

			$salida->result = $this->bag_model->getBags();
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
	}
	
	
	/**
	 * Listado de métodos de pago por comercio
	 * Petición a API de motor de pago
	 * Recibe el comercio para obtener listado desde método de pago
	 *
	 * @return json 
	 */
	public function GetListPaymentMethods() {
		
		$salida = new stdClass();
		$salida->code = 0;
		$salida->result = NULL;
		
		try {

			$post = array(
				"CommerceID" => $this->config->item("CommercePe3g")
			);
			
			$doPost = $this->_doPost2ExtEngine($this->config->item("ApiListPaymentMethodsPe3g"), $post);
			if($doPost->code != 0) throw new Exception($doPost->message, $doPost->code);
			
			// Todo OK
			$salida->result = $doPost->result;
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
	}
	
	/**
	 * Obtiene listado de métodos de pago para bolsa específica
	 *
	 * @return json 
	 */
	public function GetPaymentsForBag() {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		$salida->result = NULL;
		
		try {
			
			$idBag = trim($this->input->post("idBag"));
			log_message("debug", "REQUEST ". __METHOD__ .": ".print_r($_POST, TRUE));
			
			if(empty($idBag)) throw new Exception("No se ha podido determinar la bolsa para el listado de opciones de pago", 1000);
			
			$res = $this->bag_model->getPaymentsByIdBag($idBag);
			if(is_null($res)) throw new Exception("No hay opciones de pago disponible para la bolsa seleccionada", 1001);
			
			$salida->code = 0;
			$salida->result = $res;
			
			
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
	}
	
	
	
	/**
	 * Obtiene el detalle de una trx oneclick desde la API de motor de pagos
	 *
	 * @return json 
	 */
	public function GetTrxDetailsByCodExternal() {
		
		$salida = new stdClass();
		$salida->code = 0;
		$salida->message = "";
		$salida->result = NULL;
		
		try {
			
			$post = array(
				"codExternal" => trim($this->input->post("codExternal"))
			);
			
			$doPost = $this->_doPost2ExtEngine($this->config->item("ApiGetTrxDetailsByCodExternalPe3g"), $post);
	
			if($doPost->code != 0) throw new Exception($doPost->message, $doPost->code);
			
			// Todo OK
			$salida->result = $doPost->result;
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
	}
	

	/**
	 * Obtiene bolsa por id
	 *
	 * @return json 
	 */
	public function GetBagByIdBag() {
		
		$salida = new stdClass();
		$salida->code = 0;
		$salida->result = 0;
		
		try {
			
			$idBag = trim($this->input->post("idBag"));
			log_message("debug", "REQUEST ". __METHOD__ .": ".print_r($_POST, TRUE));
			
			if(empty($idBag)) throw new Exception("No se pudo procesar la solicitud. Por favor, inténtalo nuevamente", 1001);
			
			$oBag = $this->bag_model->getBagByIdBag($idBag);
			$salida->result = $oBag;
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
		
	}
	
	
	/**
	 * Compra de bolsa
	 * Recibe id de usuario y bolsa obviamente
	 *
	 * @return json 
	 */
	public function BuyBag() {
		
		$salida = new stdClass();
		$salida->code = 0;
		$salida->result = 0;
		
		try {
			
			$idUser = trim($this->input->post("idUser"));
			$idBag = trim($this->input->post("idBag"));
			$code = trim($this->input->post("code")); // Puede llegar como NULL
			log_message("debug", "REQUEST ". __METHOD__ .": ".print_r($_POST, TRUE));
			
			if(empty($idUser)) throw new Exception("No se ha recibido información sobre el usuario", 1001);
			if(empty($idBag)) throw new Exception("No se ha recibido información sobre la bolsa a comprar", 1001);
			
			$oBag = $this->bag_model->getBagByIdBag($idBag);
	
			// Si está la bolsa, la intenta adquirir
			// Crea transacción
			$o = new stdClass();
			$o->idUser = $idUser;
			$o->idBag = $idBag;
			$o->idTrxState = self::NEW_TRX;
			$o->trx = md5($idUser.$idBag.str_replace(".", "", microtime(TRUE)));
			if(!is_null($code)) $o->code = $code;
			$o->creationDate = date("Y-m-d H:i:s");
			
			$idTrx = $this->trx_model->create($o);
			if(is_null($idTrx)) throw new Exception("No se pudo completar la operación", 1002);
			
			$salida->result = $o->trx; // devuelve el token de la compra
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
		
	}
	
	
	
	/**
	 * Obtiene transacción en función del trx
	 *
	 * @return json 
	 */
	public function GetTransactionByTrx() {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->result = NULL;
		
		try {
			
			$trx = trim($this->input->post("trx"));
			log_message("debug", "REQUEST ". __METHOD__ .": ".print_r($_POST, TRUE));
			
			if(empty($trx)) throw new Exception("No se pudo procesar la solicitud. Por favor, inténtalo nuevamente", 1001);
			
			$oTrx = $this->trx_model->getTransactionByTrx($trx);
			if(!is_null($oTrx)) {
				
				switch($oTrx->idTrxState) {
					// El inyector completó el proceso totalmente
					case self::OK_TRX:
						$oTrx->glosaState = "¡La carga de tu bolsa ha finalizado exitosamente!";
						$oTrx->ok = 1;
						break;
					// En cualquiera de estos casos, el inyector está trabajando sobre la transacción
					// Así que no hay error y la transacción sigue siendo válida
					case self::AWAITING_LOAD_BAG:
					case self::PROCESSING_LOAD_BAG:
						$oTrx->glosaState = "¡Proceso finalizado satisfactoriamente!. Dentro de los siguientes segundos recibirás una confirmación con el cargo de tu bolsa";
						$oTrx->ok = 1;
						break;
					// Cualquier valor distinto, se considera error
					default:
						$oState = $this->trxstate_model->getByIdTrxState($oTrx->idTrxState);
						$detail = is_null($oTrx->glosaResExternal) ? "Error interno en plataforma" : $oTrx->glosaResExternal;
						$oTrx->glosaState = $oState->description
												."<br />".$detail
												."<br /><span style='color:#ffcc00'>Ponte en contacto con <b>regulacion@gigago.la</b> por favor</span>";
						$oTrx->ok = 0;
						break;
				} 
				
			}
			$salida->code = 0;
			$salida->result = $oTrx;
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
		
	}
	

	

	
	
	/**
	 * Integración efectiva contra método de pago para el inicio de una
	 * transacción de pago
	 *
	 * @return json 
	 */
	public function MakePaymentTrx() {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		$salida->result = NULL;
		
		try {
			
			$trxEncoded = trim($this->input->post("trx"));
			$urlOk = trim($this->input->post("urlOk"));
			$urlError = trim($this->input->post("urlError"));
			
			if(empty($trxEncoded) || empty($urlOk) || empty($urlError))
				throw new Exception("No se pudo procesar la solicitud. Por favor, inténtalo nuevamente", 1001);
			
			// Desencripta el trx
			$trx = decode_url($trxEncoded);
			
			// Obtiene los datos de la trx
			$oTrx = $this->trx_model->getTransactionByTrx($trx);
			if(empty($oTrx)) throw new Exception("No se pudo procesar la información de la transacción", 1002);
			
			$post = array(
				"idUserExternal"	=> $oTrx->idUser,
				"codExternal"		=> $trx,
				"urlOk"				=> $urlOk,							// Recargas
				"urlError"			=> $urlError,						// Recargas
				"urlNotify"			=> base_url()."core/notifyPayment",	// Recargas MW, acá se notifican los pagos
				"commerceID"		=> $this->config->item("CommercePe3g"),
				"amount"			=> $oTrx->value
			);
	       
			// Llamado a API de Motor de Pagos
			$res = $this->_doPost2ExtEngine($this->config->item("ApiInitTransaction"),
											$post); // hay retorno
				if($res->code != 0) throw new Exception($res->message, 1003);
			
			// TODO OK
			$salida->code = 0;
			$salida->result = $res->result;
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		$this->outputJson($salida);

	}
	
	/**
	 * Formulario de motor de pagos
	 *
	 * @return void 
	 */
	public function ShowPaymentChannels() {

		try {
			
			$paymentTrx = trim($this->input->post("paymentTrx")); // Es la trx del motor de pagos, NO de recargas
			$trx = trim($this->input->post("trx"));
			if(empty($paymentTrx) || empty($trx)) throw new Exception("No se pudo procesar la solicitud. Por favor, inténtalo nuevamente", 1001);
			
			$trx = decode_url($trx);
			
			// Se trae la oTrx para obtener datos
			$oTrx = $this->trx_model->getTransactionByTrx($trx);
			if(empty($oTrx)) throw new Exception("No se pudo procesar la información de la transacción", 1002);
			
			$post = array(
				"trx"	=> $paymentTrx
			);
			
			// Busca canales de pago configurados para el producto (bolsa)
			$paymentsForBag = $this->bag_model->getPaymentsByIdBag($oTrx->idBag);
			$arr = array();
			if(!is_null($paymentsForBag)) {
				foreach($paymentsForBag as $o) {
					$arr[] = $o->idPaymentType;
				}
				
				$post["opts"] = json_encode($arr);
			}
	        
			// Llamado a API de Motor de Pagos
			/*$this->_doPost2ExtEngineDos($this->config->item("ApiShowPaymentForm"),
											$post,
											FALSE); // SIN retorno*/
			
		} catch(Exception $e) {
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}

	}
	
	
	
	
	
	/**
	 * Recibe la respuesta desde el motor de pago, por si se realizó correctamente o no la transacción
	 * Si la respuesta es correcta, DEBE HACER LA CARGA DE BOLSA AL USUARIO
	 *
	 * @return void 
	 */
	public function notifyPayment() {

		$error = FALSE;

		try {
			
			// Recibe como string JSON la notificación del motor de pagos
			$json = file_get_contents('php://input');
			if(empty($json)) throw new Exception("No se obtuvo ninguna notificación desde el Motor de Pago", 1001);
			
			// Convierte a objeto
			$json = json_decode($json);

			$res = (int)trim($json->result); // resultado de transacción desde método pago (0 o 1)
			$trx = trim($json->codExternal); // trx de la transacción
			$message = trim($json->message);
			
			if(empty($trx) || ($res != 0 && $res != 1))
				throw new Exception("No se pudo obtener/procesar la información desde el motor de pagos", 1001);
			
			// Obtiene el oTrx
			$oTrx = $this->trx_model->getTransactionByTrx($trx);
			if(empty($oTrx)) throw new Exception("No se pudo determinar la transacción", 1002);
			
			// Resuelve respuesta desde el motor de pagos
			if($res == 1) {
				
				// El pago está correcto 
				$this->trx_model->updateStateTrx($oTrx->idTrx, self::OK_PAYMENT_TRX);
				
				// Carga bolsa
				// Se implementa mejora, haciendo que el proceso de carga de bolsa sea asíncrono
				// con esto podemos recibir y enviar, sin influir sobre el tiempo de ejecución
				// del flujo de compra.
				//$start = microtime(TRUE);
				$loadBag = $this->_loadBagProcess($oTrx);
				//$timeElapsed = microtime(true) - $start; // segundos
				//log_message("debug", "CARGA DE BOLSA -> TIEMPO TRANSCURRIDO: $timeElapsed");	
				
				//if($loadBag->code != 0) throw new Exception($loadBag->message, 1003);
				
			} else {
				// No se pudo procesar el pago
				$this->trx_model->updateStateTrx($oTrx->idTrx, self::FAILED_PAYMENT_TRX);
				
			}
			
		} catch(Exception $e) {
			log_message("error", __METHOD__ . " -> (". $e->getCode() .")" . $e->getMessage());
			$error = TRUE;
		}
		
		$salida = $error ? "ERROR" : "OK";
		echo $salida; // retorna valor para notificar a motor que se recibió respuesta
	
	}
	
	
	/**
	 * Se trae la última, si existe, campaña activa
	 * Utiliza el motor de pines para el manejo de campañas
	 *
	 * @return json 
	 */
	public function GetActiveCampaign() {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		$salida->result = NULL;
		
		try {
			
			$format = "Y-m-d H:i:s";
			
			// Busca campaña activa en motor de pines, respecto a su identificador
			$params = array(
						"IdCpCountry" => $this->config->item("ApiPinesCpCountry")
					);
			$res = $this->_doPost2ExtEngine($this->config->item("ApiPinesLastActiveCampaign"), $params);
			if($res->code != 0) throw new Exception($res->message, 1001);

			$salida->code = 0;
			$salida->result = $res->result;
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
		
	}
	
	/**
	 * Obtiene campaña por su código
	 *
	 * @return json 
	 */
	public function GetCampaignByCode() {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		$salida->result = NULL;
		
		try {
			
			$format = "Y-m-d H:i:s";
			
			$idUser = trim($this->input->post("idUser"));
			$code = trim($this->input->post("code"));
			//$idUser = 12;
			//$code = "DESC_20_MOVISTAR";
			
			if(empty($idUser)) throw new Exception("No se ha podido identificar al usuario para procesar", 1009);
			if(empty($code)) throw new Exception("No se ha recibido ningún código para procesar", 1009);

			// Busca campaña por código en el motor de pines
			$params = array(
						"IdCpCountry" => $this->config->item("ApiPinesCpCountry"),
						"Code" => $code
					);
			$res = $this->_doPost2ExtEngine($this->config->item("ApiPinesGetCampaignByCode"), $params);
			if($res->code != 0) throw new Exception($res->message, 1001);
			
			// La campaña en sí cumple todos los requisitos, por lo tanto, corresponde
			// verificar si el usuario ya ha adquirido el código o no
			// Solo se puede uno por persona
			$oTrx = $this->trx_model->getTrxByIdUserAndCode($idUser, $code, self::OK_TRX);
			if(!is_null($oTrx)) throw new Exception("Lo sentimos, pero ya has utilizado este código", 1002);
			
			$salida->code = 0;
			$salida->result = $res->result;
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
		
	}
	
	
	/**
	* Busca el total de transacciones por usuario y fecha
	*/
	public function GetTotalPurchasesByUser() {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		$salida->result = NULL;
		
		try {
			
			$format = "Y-m-d";
			
			$idUser = trim($this->input->post("idUser"));
			$fStart = trim($this->input->post("fStart"));
			$fEnd = trim($this->input->post("fEnd"));
			//$idUser = 12;
			//$fStart = "2017-08-14 00:00:00";
			//$fEnd = "2017-08-14 23:59:59";
			
			if(empty($idUser)) throw new Exception("No se ha podido identificar al usuario para procesar", 1001);
			if(empty($fStart)) throw new Exception("No se ha determinado el rango de fecha a evaluar", 1001);
			if(empty($fEnd)) throw new Exception("No se ha determinado el rango de fecha a evaluar", 1001);

			// Busca todas las transacciones para el usuario y determinado rango de fechas
			$oTrxs = $this->trx_model->getTotalPurchasesByUser($idUser, $fStart, $fEnd, self::OK_TRX);
			if(is_null($oTrxs)) throw new Exception("Por el momento no hay transacciones activas", 1002);
			
			$salida->code = 0;
			$salida->result = $oTrxs;
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
		
	}
	
	
	/**
	* Todas las transacciones incompletas
	*/
	public function GetAllIncompleteTrxs() {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		$salida->result = NULL;
		
		try {
			
			// Busca todas las transacciones para el usuario y determinado rango de fechas
			$oTrxs = $this->trx_model->getAllIncompleteTrxs(self::OK_TRX);
			if(is_null($oTrxs)) throw new Exception("Por el momento no hay transacciones incompletas", 1002);
			
			$salida->code = 0;
			$salida->result = $oTrxs;
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
		
	}

	public function SearchAllTrxs() {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		$salida->result = NULL;
		
		try {
			
			// Busca todas las transacciones para el usuario y determinado rango de fechas
			$key = trim($this->input->post("key"));
			$oTrxs = $this->trx_model->searchAllTrxs($key);
			if(is_null($oTrxs)) throw new Exception("Por el momento no hay transacciones incompletas", 1002);
			
			$salida->code = 0;
			$salida->result = $oTrxs;
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
		
	}
	
	
	/**
	* Todas las transacciones incompletas
	*/
	public function GetAllCompleteTrxs() {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		$salida->result = NULL;
		
		try {
			
			// Busca todas las transacciones para el usuario y determinado rango de fechas
			$oTrxs = $this->trx_model->getAllTrxsByState(self::OK_TRX);
			if(is_null($oTrxs)) throw new Exception("Por el momento no hay transacciones", 1002);
			
			foreach($oTrxs as $o) {
				
				$post = new stdClass();
				$post->codExternal = $o->trx;
				
				$doPost = $this->_doPost2ExtEngine($this->config->item("ApiGetTrxDetailsByCodExternalPe3g"), $post);
				if($doPost->code != 0) throw new Exception($doPost->message, $doPost->code);
				
				// Todo OK
				
				$trx = $doPost->result;
				$o->namePaymentType = $trx->namePaymentType;
				
			}
			
			$salida->code = 0;
			$salida->result = $oTrxs;
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
		
	}
	
	
	/**
	 * Modifica estado de transacción
	 */
	 
	public function UpdateTrxStateReverse() {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		$salida->result = NULL;
		
		try {
			
			
			$idTrx = trim($this->input->post("idTrx"));
			
			$res = $this->trx_model->updateStateTrx($idTrx, self::RESOLVING_NO_SEGMENT_CLIENT);
			if(!$res) throw new Exception("No se pudo actualizar el estado de la transacción", 1001);
			
			$salida->code = 0;
			$salida->message = "OK";
			$salida->result = TRUE;
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
		
	}
	
	/**
	 * Modifica estado de transacción, la devuelve a espera de carga de bolsa
	 */
	public function UpdateTrxStateReprocess() {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		$salida->result = NULL;
		
		try {
			
			// Parámetro de identificación de la transacción
			$idTrx = trim($this->input->post("idTrx"));
			
			// Obtiene objeto transacción
			$oTrx = $this->trx_model->getTrxByIdTrx($idTrx);
			if(is_null($oTrx)) throw new Exception("No se pudo determinar la transacción", 1002);

			// Si está OK, intenta aplicar reproceso
			$res = $this->_reprocess($oTrx);
			if($res->code != 0) throw new Exception($res->message, 1003);
			
			// OK
			$salida->code = 0;
			$salida->message = "OK";
			$salida->result = TRUE;
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
		
	}
	
	/**
	 * Modifica estado de transacción, pero solo permite el día siguiente a
	 * la fecha de creación de la transacción
	 */
	public function UpdateTrxStateReprocessAfter() {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		$salida->result = NULL;
		
		try {
			
			// Parámetro de identificación de la transacción
			$idTrx = trim($this->input->post("idTrx"));
	
			// Obtiene objeto transacción
			$oTrx = $this->trx_model->getTrxByIdTrx($idTrx);
			if(is_null($oTrx)) throw new Exception("No se pudo determinar la transacción", 1002);
			
			// Con la transacción, verifica si está en el mismo día o no
			$creationDate = date("Y-m-d", strtotime($oTrx->creationDate));
			$today = date("Y-m-d");
			if($creationDate == $today) throw new Exception("No puedes reprocesar la transacción el mismo día de creación", 1004);
			
			// Si está OK, intenta aplicar reproceso
			$res = $this->_reprocess($oTrx);
			if($res->code != 0) throw new Exception($res->message, 1003);
			
			// OK
			$salida->code = 0;
			$salida->message = "OK";
			$salida->result = TRUE;
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
		
	}
	
	
	private function _reprocess($oTrx) {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		
		try {
			
			$idTrx = $oTrx->idTrx;
			
			// Incrementa intentos de reproceso
			$res = $this->trx_model->updateTrx($idTrx, "attemptsNumber", (int)$oTrx->attemptsNumber + 1);
			if(!$res) throw new Exception("No se pudo actualizar el número de intentos de reproceso de la transacción", 1003);
			
			// Actualiza estado para que lo vuelva a tomar el inyector
			$res = $this->trx_model->updateStateTrx($idTrx, self::AWAITING_LOAD_BAG);
			if(!$res) throw new Exception("No se pudo actualizar el estado de la transacción", 1001);
			
			$salida->code = 0;
			$salida->message = "OK";
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		return $salida;
		
	}
	
	
	
	
	/**
	* Verifica si es posible adquirir una bolsa respecto al total permitido
	*/
	public function AllowBuyBag() {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		$salida->result = NULL;
		
		try {
			
			$format = "Y-m-d";
			
			$idUser = trim($this->input->post("idUser"));
			$fStart = trim($this->input->post("fStart"));
			$fEnd = trim($this->input->post("fEnd"));

			if(empty($idUser)) throw new Exception("No se ha podido identificar al usuario para procesar", 1001);
			if(empty($fStart)) throw new Exception("No se ha determinado el rango de fecha a evaluar", 1001);
			if(empty($fEnd)) throw new Exception("No se ha determinado el rango de fecha a evaluar", 1001);
			
			// Busca todas las transacciones para el usuario y determinado rango de fechas
			$oTrxs = $this->trx_model->getTotalPurchasesByUser($idUser, $fStart, $fEnd, self::OK_TRX);
			//$idBag=32;
			//$oTrxIdbag =  $this->trx_model->getTotalPurchasesIDbagByUser($idUser, $idBag, self::OK_TRX);
			$allow = NULL; // Si se mantiene, sucedió un error
			
			if(is_null($oTrxs) ) {
				// No hay transacciones, permite de inmediato
				$allow = TRUE;
				
			} else {
				// Encuentra transacciones, valida contra total
				// Máximo de compra de bolsas permitido
				$maxBags = $this->config->item("MaxBagsPurchased");
				
				if(count($oTrxs) < $maxBags) {
					$allow = TRUE;
				} else {
					$salida->message = "Increíble, ¡has llegado al límite de $maxBags compra(s) al día!";
					$allow = FALSE;
				}
			}
			
			if(!is_null($allow)) {
				
				$salida->code = 0;
				$salida->result = $allow;
				
			} else {
				// Sucedió un error
				throw new Exception("No se pudo determinar el número de compras del usuario", 1002);
			}
	
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
		
	}
	
	
	/**
	* Envía correo
	*/
	public function SendEmail() {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		
		try {
			
			//$emails = array('gorellana@3gmotion.com');
			
			$subject = trim($this->input->post("subject"));
			$message = trim($this->input->post("message"));
			$to = trim($this->input->post("to"));

			$this->load->library('email');
			
			$this->email->initialize(array(
			  'protocol' => 'smtp',
			  'smtp_host' => 'ssl://smtp.gmail.com',
			  'smtp_user' => 'feedreports2@3gmotion.com',
			  'smtp_pass' => '123456=ABC',
			  'smtp_port' => 465,
			  'crlf' => "\r\n",
			  'newline' => "\r\n",
			  '_smtp_auth' => TRUE
			));

			// Se envía correo a los definidos
			$this->email->from('feedreports2@3gmotion.com', 'GigaGo Alerts');
			$this->email->to($to);
			$this->email->subject($subject);
			$this->email->message($message);
			//$this->email->attach($archivo);
			
			$this->email->send();
			
			// Email sent correctly
			$salida->code = 0;
			$salida->message = "OK";

		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		$this->outputJson($salida);
		
	}
	
	
	
	// ------------------------------ ADMIN ------------------------------
	
	/**
	 * Vincula métodos de pago a bolsa seleccionada
	 *
	 * @return json 
	 */
	public function CreatePts4Bag() {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		$salida->result = NULL;
		
		$hayTrx = FALSE;
		
		try {
			
			$idBag = trim($this->input->post("idBag"));
			$pts = trim($this->input->post("pts")); // SI puede llegar vacío, estaría desvinculando.
			
			log_message("debug", "REQUEST ". __METHOD__ .": ".print_r($_POST, TRUE));
			
			if(empty($idBag)) throw new Exception("No se pudo procesar la solicitud. Por favor, inténtalo nuevamente", 1001);
			
			// Actualiza valores
			// Proceso a través de transacción
			$this->trx_model->inicioTrx();
			$hayTrx = TRUE;
			
			// Limpia registros relacionados a la bolsa
			$this->bagpaymenttype_model->deletePts4IdBag($idBag);
			
			if(!empty($pts)) {
				
				$pts = explode(",", $pts);
				
				foreach($pts as $idPaymentType) {
					
					$res = $this->bagpaymenttype_model->createPt4Bag($idBag, $idPaymentType);
					if(!$res) throw new Exception("No se pudo completar el proceso de vinculación de pagos a la bolsa seleccionada", 1002);
					
				}
			}
			
			// OK
			$this->trx_model->commitTrx();
			$salida->code = 0;
					
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			
			if($hayTrx) $this->trx_model->rollbackTrx();
			
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
		
	}
	

	
	
	/**
	* Reversa de pago
	*/
	public function ReverseTrx() {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		$salida->result = NULL;
		
		try {
			
			$format = "Y-m-d";
			
			$buyOrder = trim($this->input->post("buyOrder"));

			if(empty($buyOrder)) throw new Exception("No se ha podido identificar al usuario para procesar", 1001);
	
			$post = array(
				"buyOrder" => $buyOrder
			);
			
			$doPost = $this->_doPost2ExtEngine($this->config->item("ApiListPaymentMethodsPe3g"), $post);
			if($doPost->code != 0) throw new Exception($doPost->message, $doPost->code);
			
			// Todo OK
			$salida->message = $doPost->message;
			$salida->result = $doPost->result;
	
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", "ERROR ". __METHOD__ ." -> ".$e->getMessage());
		}
		
		log_message("debug", "RESPONSE ". __METHOD__ .": ".print_r($salida, TRUE));
		$this->outputJson($salida);
		
	}
	
	
	
	
	
	// ------------------------------ PRIVATE METHODS ------------------------------------
	
	/**
	 * Realiza todos los procesos para la carga efectiva de la bolsa
	 * Recibe el objeto transacción
	 *
	 * @return boolean 
	 */
	private function _loadBagProcess($oTrx) {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		
		try {
			
		
			$idTrx = $oTrx->idTrx;
			
			// Transacción OK (cargo de dinero)
			// Avanza estado a intento de carga de bolsa
			//$this->trx_model->updateStateTrx($idTrx, self::TRY_LOAD_BAG);
			
			// Intento efectivo de carga de bolsa
			// Busco usuario para obtener ANI sobre el que se cargará la bolsa
			$oUser = $this->user_model->getUserByIdUser($oTrx->idUser);

			// Evalúa si el usuario está activo o no
			if(!is_null($oUser)) {
			
				// El usuario existe, verifica que esté activo
				if($oUser->idUserState == self::ACTIVE_USER) {
					
					
					// Busca la bolsa de la transacción
					$oBag = $this->bag_model->getBagByIdBag($oTrx->idBag);
					if(!is_null($oBag)) {
						
						$ani = $oUser->ani;	// ANI usuario
						$bag = $oBag->code; // Código de bolsa a cargar
						
						// Actualiza a estado de en proceso
						$this->trx_model->updateStateTrx($idTrx, self::AWAITING_LOAD_BAG);
						
						// Ahora el proceso culmina en esta etapa, porque la carga de bolsas la hace el servicio
						// programado GigagoInyector, que maneja peticiones concurrentes y resuelve a través
						// de multi-thread las N potenciales 
						//$this->_loadBag($ani, $bag);
						$salida->code = 0;
						$salida->message = "Proceso finalizado satisfactoriamente. Dentro de los siguientes segundos recibirás una confirmación con el cargo de tu bolsa";
						
					} else {
						$this->trx_model->updateStateTrx($idTrx, self::NO_BAG_LOAD_BAG);
						throw new Exception("No se pudo identificar la bolsa id ".$oTrx->idBag." a cargar (transacción id $idTrx)", 1005);
					}
					
					
				} else {
					// Usuario no activo
					$this->trx_model->updateStateTrx($idTrx, self::INACTIVE_USER_LOAD_BAG);
					throw new Exception("El usuario ".$oUser->idUser." se encuentra inactivo (transacción id $idTrx)", 1004);
				}
				
			} else {
				// No se pudo identificar al usuario de la transacción
				$this->trx_model->updateStateTrx($idTrx, self::NO_USER_LOAD_BAG);
				throw new Exception("No se pudo identificar al usuario ".$oUser->idUser." en la transacción id $idTrx", 1003);
			}
					
		
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
		}

		return $salida;
		
	}
	
	/**
	 * Consumo de servicios externos (motor de pagos, pines)
	 */
/*	private function _doPost2ExtEngine($service, $arr, $return = TRUE) {
		
		$curl = curl_init($service);
		
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, $return);
		curl_setopt($curl, CURLOPT_POST, TRUE);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $arr);
		
		$exec = curl_exec($curl);
		curl_close($curl);
		
		if($return) {
			
			$salida = new stdClass();
			$salida->code = -1;
			$salida->message = "";
			$salida->result = NULL;
	
			try {
				
				if(empty($exec)) throw new Exception("No se obtuvo ninguna respuesta desde el servicio", 1000);

				$obj = json_decode($exec);
				$salida->code = $obj->code;
				$salida->message = $obj->message;
				$salida->result = $obj->result;
				
			} catch(Exception $e) {
				$salida->code = $e->getCode();
				$salida->message = $e->getMessage();
			}

			return $salida;
		}
		
		return;
		
	}*/

	/**
	 * Consumo de servicios externos nueva apigateway
	 */
	private function _doPost2ExtEngine($service, $arr, $return = TRUE) {
		
			
		$url = $service;
		$arr		= json_encode($arr);
		$request	= curl_init();
		$header     = array(
					'cache-control: no-cache',
					 $this->config->item("Api"),
					'Content-Type: application/json; charset=utf-8'
				);
				$setup		= array(
					CURLOPT_URL				=> $url,
					CURLOPT_HTTPHEADER		=> $header,
					CURLOPT_POST			=> true,
					CURLOPT_POSTFIELDS		=> $arr,
					CURLOPT_RETURNTRANSFER	=> $return,
					CURLOPT_VERBOSE			=> true,
					CURLOPT_SSL_VERIFYPEER	=> false,
					CURLOPT_SSL_VERIFYHOST	=> false,
				);
				
				curl_setopt_array( $request, $setup );
				$exec = curl_exec($request);
			
		
		if($return) {
			
			$salida = new stdClass();
			$salida->code = -1;
			$salida->message = "";
			$salida->result = NULL;
	
			try {
				
				if(empty($exec)) throw new Exception("No se obtuvo ninguna respuesta desde el servicio", 1000);

				$obj = json_decode($exec); 
				$salida->code = 0;
				$salida->message = $obj->context;
				$salida->result = $obj->data->url;
				
		
			} catch(Exception $e) {
				$salida->code = $e->getCode();
				$salida->message = $e->getMessage();
			}

			return $salida;
		}
		
		return;
		
		
		
		
	}
	
}
