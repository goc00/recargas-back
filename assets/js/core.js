/* ----------------------------------------------
 * Desarrollado por Gastón Orellana C.
 * ---------------------------------------------- */
$(document).ready(function() {
	
	$("#frmValidateAni").submit(function() {
		
		var frm = $(this);
		var obj = {
					"ani" : $("#ani_txt").val()
				};
		
		var func = function($data) {
			if($data.code == 0) {
				alert($data.result);
			} else {
				alert($data.message);
			}
		};
		
		doPost(frm.attr("action"), obj, func);
		
		return false;
	});
	
	// Función para envío POST mediant JSON
	function doPost($action, $obj, $function) {
		$.post(
			$action,
			$obj,
			function(data) { $function(data) },
			"json"
		);
	}
	
	
	
	// Update details
	/*$(document).on("click", "input[id*='actualizar_']", function() {
		var $id = $(this).attr("id");
		var arr = $id.split("_");
		var id = arr[arr.length-1];
		var $form = $("#frm_detalle_seccion");
		var $ref = $(this);
		
		$form.trigger("submit", [$ref.attr("id"), id]);
	});
	$("#frm_detalle_seccion").submit(function(e, from, id) {
		
		var ref = $(this);
		var options = { 
			data:	{
						"id" : id,
						"tipo" : $("#tipo_"+id+" option:selected").val(),
						"contenido" : $("#contenido_"+id).val(),
						"extra" : $("#extra_"+id).val(),
						"userfile" : $("#userfile"+id).val()
					},
			type: 'post',
			dataType: 'json',
			success: function(data) {
				alert(data.msg);
				if(data.exito == "si") {
					//var _parent = ref.parent().parent();
					location.reload();
				}
			},  // post-submit callback 
			error: function(xhr, status, error) {
				//handleError(xhr, status, error);
				console.log(error);
			}
			// $.ajax options can be used here too, for example: 
			//timeout:   3000 
		}; 
		
		
		
		$(this).ajaxSubmit(options);
		
		return false;
	});*/

	
});