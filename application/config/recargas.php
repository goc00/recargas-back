<?php
/*
|-----------------------------------------------------------------------------
| SIMULA carga OK de BOLSAS (SOLO para pruebas)
|-----------------------------------------------------------------------------
*/
$config['SimulateOkBag']				= TRUE;
/*
|-----------------------------------------------------------------------------
| Servicio para obtener el operador al que pertenece un ANI (contra Telcordia)
|-----------------------------------------------------------------------------
*/
$config['ValidateAniService']			= 'http://146.82.89.83/le/WEBSITES/validacion_ani_operador.asp?ani={NROMOVIL}';
/*
|-----------------------------------------------------------------------------
| Envía MT a través de SecureServices
|-----------------------------------------------------------------------------
*/
$config['SecureServiceMt']				= 'https://secure.3gmotion.com/Services/MT';
$config['SecureServiceBilling']			= 'https://secure.3gmotion.com/Services/Billing';

$config['CodeBilling']					= 'CLMOBI1001';
$config['Login3GBilling']				= 'demo';
$config['Pass3GBilling']				= '3TCodoDV';

$config['UserSecureServiceMt']			= 'srecarga';
$config['PassSecureServiceMt']			= 'srecarga$$';
$config['FormatMsgSecureServiceMt']		= '0'; // (0:Sms /1:Wap Push)
$config['NcMsgSecureServiceMt']			= '6222';
/*
|-----------------------------------------------------------------------------
| Motor de pagos
|-----------------------------------------------------------------------------
*/
$config['HostPe3g']								= 'https://dev-api.digevo.com/payment/v2/'; // Host API de motor de pago
$config['CommercePe3g']							= '100005'; // ID de comercio en motor de pagos
$config['ApiInitTransaction']					= $config['HostPe3g'].'transactions';
$config['ApiShowPaymentForm']					= $config['HostPe3g'].'channels';
$config['ApiListPaymentMethodsPe3g']			= $config['HostPe3g'].'ListPaymentMethods';
$config['ApiGetTrxDetailsByCodExternalPe3g']	= $config['HostPe3g'].'GetTrxDetailsByCodExternal';
$config['ApiGetTrxOneclickByBuyOrder']			= $config['HostPe3g'].'GetTrxOneclickByBuyOrder';
$config['ApiReverseTrxOneclick']				= $config['HostPe3g'].'ReverseTrxOneclick';
$config['Api']                                  = 'x-api-key: 5sBVv4SVyS4mTdFDWfruF3yv3PXz3APNaOVrkTCK';

/*
|-----------------------------------------------------------------------------
| Motor de pines (para manejo de campañas, descuentos, etcétera)
|-----------------------------------------------------------------------------
*/
$config['ApiPines']								= 'http://localhost/pines/api/';
$config['ApiPinesCpCountry']					= 5; // Recargas - Chile en motor de pines
$config['ApiPinesLastActiveCampaign']			= $config['ApiPines'].'LastActiveCampaign';
$config['ApiPinesGetCampaignByCode']			= $config['ApiPines'].'GetCampaignByCode';
$config['ApiPinesEncodeData']					= $config['ApiPines'].'EncodeData';
$config['ApiPinesDecodeData']					= $config['ApiPines'].'DecodeData';
/*
|-----------------------------------------------------------------------------
| Carga de bolsas
|-----------------------------------------------------------------------------
*/
$config['MaxBagsPurchased']					= 1;
$config['ServiceBag']						= 'https://api.movistar.cl/product/V2/vas?apikey=OlqrtouruQNV9xbw1wQGDHfkTIwob1bY';
$config['UserServiceBag']					= '3gmotion';
$config['PassServiceBag']					= '3gmotion@telefonica.com';
/*
|-----------------------------------------------------------------------------
| Formulario de contacto
|-----------------------------------------------------------------------------
*/
$config['ContactEmail']						= 'contacto@gigago.la';
$config['BEmail']						    = 'boletas@gigago.la';
$config['ContactSubject']					= 'Soporte GigaGo';
