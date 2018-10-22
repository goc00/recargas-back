<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Recargas v0.1</title>
	<link rel="stylesheet" href="<?= base_url() ?>assets/css/theme.css" />
	<script src="<?= base_url() ?>assets/js/jquery-3.1.1.min.js"></script>
	<script src="<?= base_url() ?>assets/js/core.js"></script>
</head>
<body>

<div>
	<div class="modal">
		<div></div>
		<div></div>
	</div>
	
	<div class="titulo">Registro</div>
	<div class="icono_registro"><img src="<?= base_url() ?>assets/images/icono1_03.png" /></div>
	<div class="label_input">Ingresa tu n&uacute;mero de tel&eacute;fono</div>
	
	<form id="frmValidateAni" action="<?= base_url() ?>core/validateAniAction" method="post">
		<div class="ani_input"><input type="text" id="ani_txt" /></div>
		<div class="aceptar_btn"><button>Aceptar</button></div>
	</form>
</div>

</body>
</html>