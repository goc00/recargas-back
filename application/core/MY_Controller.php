<?php
class MY_Controller extends CI_Controller {

	function __construct() {
		parent::__construct();
		date_default_timezone_set("America/Santiago");
	}
	
	
	// Peticiones POST
	protected function doPost($host, $method, $params) {
		
		$curl = curl_init($host.$method);
		
		$data_string = (array)$params;
	
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
		
		$exec = curl_exec($curl);
		curl_close($curl);

		return $exec; 	
	}
	
	// Petición GET
	protected function doGet($url) {
		
		$curl = curl_init();
		
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => $url
		));
		
		$exec = curl_exec($curl);
		curl_close($curl);

		return $exec; 	
	}
	
	
	protected function post() {
		return file_get_contents("php://input");
	}
	
	protected function outputJson($data) {
		$this->output
				->set_content_type('application/json')
				->set_output(json_encode($data));
	}
	
	
	/**
	 * Lanza exception
	 */
	protected function exception($mensaje, $num) {
		throw new Exception($mensaje, $num);
	}
	
}

