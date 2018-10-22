<?php
/*
+----------------------------------+
| Creado por Gastón Orellana 	   |
|----------------------------------|
| Carga bolsas de recargo v1.0     |
+----------------------------------+
*/

class Loadbag extends Thread {
	
	private $serviceBag;
	private $userServiceBag;
	private $passServiceBag;
	private $ani;
	private $bag;

	public function __construct($params) {
		
		$this->serviceBag = $params["serviceBag"];
		$this->userServiceBag = $params["userServiceBag"];
		$this->passServiceBag = $params["passServiceBag"];
		$this->ani = $params["ani"];
		$this->bag = $params["bag"];
		
	}
	
	public function run() {
		
		try {
	
			// Genera token de autentificación
			$credentials = $this->userServiceBag.":".$this->passServiceBag;
			$token = base64_encode($credentials);
			
			// Cabeceras
			$headers = array(
				'Accept: application/json',
				'Content-Type: application/json',
				'Authorization: Basic '.$token
			);

			// Crea objeto para pasar a servicio (como JSON)
			$o = new stdClass();
			$o->id = $this->ani;
			$o->type = "mobile";
			$o->contractType = "Prepago";
			$o->offer = $this->bag;
			$o->service = "bolsa";
			$o->mail = "-";
			$o->glosa = "-";
			$o->precio = "-";
			$o->flujo = "gift";

			$curl = curl_init($this->serviceBag);
			
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($curl, CURLOPT_POST, TRUE);
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($o));
			
			$exec = curl_exec($curl);
			
			$errors = curl_error($curl);
			$response = curl_getinfo($curl, CURLINFO_HTTP_CODE);

			curl_close($curl);
			
			if(empty($exec)) throw new Exception("No hubo respuesta del servicio de carga de bolsa", 1000);
			
			$asObj = json_decode($exec);
			$oStatus = $asObj->estado;
			if((int)$oStatus->codigoEstado != 200) throw new Exception($oStatus->glosaEstado, 1001);
			
			// Bolsa cargada satisfactoriamente
			//$oDatos = $asObj->datos;
			//log_message("debug", "CULMINÓ PROCESO EFECTIVO DE CARGA");
			return TRUE;
			
		} catch(Exception $e) {
			
			//log_message("error", __METHOD__ . " -> " . $e->getMessage());
			
			
		}
		
		return FALSE;

	}
	
}

?>