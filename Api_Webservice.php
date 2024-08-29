<?php
// libxml_use_internal_errors(TRUE);
error_reporting(E_ALL);
ob_start();
header("Cache-control: private, no-cache");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Pragma: no-cache");
header("Cache: no-cahce");
ini_set('max_execution_time', 90000);
ini_set("memory_limit", -1);

include_once('../../SISTEM/Clases/ClsAlumno.php');
include_once('../../SISTEM/Clases/ClsBoletaCobro.php');
include_once('../../SISTEM/Clases/ClsPeriodoFiscal.php');
include_once('../../SISTEM/Clases/ClsBanco.php');

header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
header("Access-Control-Allow-Origin: *");

// $datos_xml = simplexml_load_file("Test.xml");

// $prueba_xml=<<<XML
// <TEST_COMUNICACION>
// 	<CODIGO_ENTE>001</CODIGO_ENTE>
// 	<USUARIO></USUARIO>
// 	<CLAVE></CLAVE>
// 	<FECHA_TRANSACCION>2017/10/13</FECHA_TRANSACCION>
// 	<HORA_TRANSACCION>09:39:26</HORA_TRANSACCION>
// </TEST_COMUNICACION>
// XML;

// $prueba_xml = simplexml_load_string($prueba_xml);

///API REQUEST
$request = $_REQUEST["request"];

if($request != ""){
	switch($request){
		case "Consulta":
			$datos_xml = $_REQUEST["PinOut"];
			API_Obtener_pagos($datos_xml);
			break;
		case "Pago_Reversion":
			$datos_xml = $_REQUEST["PinOut"];
			API_Realizar_pagos($datos_xml);
			break;
		case "Test_Comunicacion":
			$datos_xml = $_REQUEST["PinOut"];
			API_Test($datos_xml);
			break;	
		default:
			$xml=<<<XML
			<RESP_CONSULTA>
				<CODIGO_RESPUESTA>001</CODIGO_RESPUESTA>
				<DESCRIPCION_RESPUESTA>Cliente no existe</DESCRIPCION_RESPUESTA>
			</RESP_CONSULTA>
			XML;
			echo($xml);
	}
}else{
	//devuelve un mensaje de manejo de errores
	$xml=<<<XML
	<RESP_CONSULTA>
		<CODIGO_RESPUESTA>001</CODIGO_RESPUESTA>
		<DESCRIPCION_RESPUESTA>Cliente no existe</DESCRIPCION_RESPUESTA>
	</RESP_CONSULTA>
	XML;
	echo($xml);
}

////////////////////////////////////////////////// FUNCIONES Y CONSULTAS ////////////////////////////////////////////////////

function API_Obtener_pagos($datos_xml){
	/////// PROGRAMADO ////////
	$ClsPer = new ClsPeriodoFiscal();
	$ClsBol = new ClsBoletaCobro();
	$ClsAlu = new ClsAlumno();
	$datos_xml = simplexml_load_string($datos_xml);

	$periodo = $ClsPer->get_periodo_activo();
	$anio_periodo = $ClsPer->get_anio_periodo($periodo);
	$anio_actual = date("Y");
	$anio_periodo = ($anio_periodo == "")?$anio_actual:$anio_periodo;
	//// fechas ///
	if($anio_actual == $anio_periodo){
		$mes = date("m"); ///mes de este año para calculo de saldos y moras
		$mes2 = $mes+1; ///mes de este año para calculo de saldos y moras
		$dia = date("d"); 
		$fini = "01/01/$anio_actual";
		$ffin = "28/$mes/$anio_actual";
		$ffin2 = "28/$mes2/$anio_actual";
	}else{
		$fini = "01/01/$anio_periodo";
		$ffin = "31/12/$anio_periodo";
	}
	
	$objJsonDocument = json_encode($datos_xml);
	$arrOutput = json_decode($objJsonDocument, TRUE);
	$bolcodigo = $arrOutput['DATO_1'];
	$CuiAlumno = $arrOutput['DATO_2'];
	$division = $arrOutput['DATO_3'];
	$Codigo_pago = $arrOutput['CODIGO_PAGO'];
	$CodigoUnico = $ClsAlu->get_codigo_alumno($CuiAlumno);

	if ($CodigoUnico != '' || $bolcodigo !='' ) {
		$result = $ClsBol->get_boleta_vs_pago2($bolcodigo,$division,'',$CodigoUnico,'',$periodo,'',$fini,$ffin,1,2,'',true);
		if ($result == '!E') {
			$result = $ClsBol->get_boleta_vs_pago2($bolcodigo,$division,'',$CodigoUnico,'',$periodo,'','','',1,4,'',true);
		}
	
		$monto_programdo = 0;
		$monto_pagado = 0;
		$referenciaX;
		if(is_array($result)){
			foreach($result as $row){
				$bolcodigo = $row["bol_codigo"];
				$boldivision = $row["bol_division"];
				$CodigoUnico = $row["bol_alumno"];
				$CuiAlumno = $row["bol_alumno_cui"];
				// $CuiAlumno = ($CuiAlumno == '') ? 0: $CuiAlumno;
				$bolNombreAlumno = $row["alu_nombre"];
				$bolApellidoAlumno = $row["alu_apellido"];
				if($bolcodigo != $referenciaX){
					$monto_programdo+= $row["bol_monto"];
					$monto_boleta= $row["bol_monto"];
					$fecha_programdo = $row["bol_fecha_pago"];
					$referenciaX = $bolcodigo;
				}
				$pago =  $ClsBol->get_pago($bolcodigo,'',$pensum,$CuiAlumno,$ClsPer);
				if(is_array($pago)){
					$x =0;
					foreach($pago as $rowPago){
						if($pago != ""){				
							if($bolcodigo != $referenciaX){
								$fecha_pago = $rowPago["pag_fechor"];
							}
							$monto_pagado+= $rowPago["pag_total"]; /// monto registrado en la boleta, ya con descuento
						}
						$mons = $rowPago["bol_simbolo_moneda"];
					}
				}

				$valor_programado = $mons .". ".number_format($monto_programdo, 2, '.', ',');
				$diferencia = $monto_programdo - $monto_pagado;
				$diferencia = round($diferencia, 2);
			}
			$pagos_pentientes = $diferencia/$monto_boleta;

			$xml=<<<XML
			<RESP_CONSULTA>
				<DATO_RESP_1>$bolNombreAlumno</DATO_RESP_1>
				<DATO_RESP_2>$bolApellidoAlumno</DATO_RESP_2>
				<DATO_RESP_3>$bolcodigo</DATO_RESP_3>
				<DATO_RESP_4>$mons$monto_boleta</DATO_RESP_4>
				<DATO_RESP_5>$pagos_pentientes pagos pendientes.</DATO_RESP_5>
				<DATO_RESP_6></DATO_RESP_6>
				<SALDO>$diferencia</SALDO>
				<TVALIDACION>3</TVALIDACION>
				<CODIGO_RESPUESTA>000</CODIGO_RESPUESTA>
				<DESCRIPCION_RESPUESTA>Consulta Exitosa</DESCRIPCION_RESPUESTA>
			</RESP_CONSULTA>
			XML;
			echo($xml);

			$codigo = $ClsBol->max_asignacion_api();
			$codigo++;
			$bolcodigo++;
			$bolcodigo -= $pagos_pentientes;
			$sql = $ClsBol->insert_asignacion_api($codigo,$Codigo_pago,$bolcodigo,$boldivision,$CodigoUnico,$CuiAlumno,$monto_boleta,0,0,0,0,0,0,0);
			$rs = $ClsBol->exec_sql($sql);
		}
	}else{
			$xml=<<<XML
			<RESP_CONSULTA>
				<CODIGO_RESPUESTA>001</CODIGO_RESPUESTA>
				<DESCRIPCION_RESPUESTA>Cliente no existe</DESCRIPCION_RESPUESTA>
			</RESP_CONSULTA>
			XML;
			echo($xml);
			// die();
	}
}

function API_Realizar_pagos($datos_xml){
	$ClsBol = new ClsBoletaCobro();
	$ClsAlu = new ClsAlumno();
	$ClsBan = new ClsBanco();
	$ClsPer = new ClsPeriodoFiscal();
	$datos_xml = simplexml_load_string($datos_xml);


	$objJsonDocument = json_encode($datos_xml);
	$arrOutput = json_decode($objJsonDocument, TRUE);
	$bolcodigo = $arrOutput['DATO_1'];
	$CuiAlumno = $arrOutput['DATO_2'];
	$Codigo_pago = $arrOutput['CODIGO_PAGO'];
	$monto = $arrOutput['MONTO_PAGADO'];
	$fecha = $arrOutput['FECHA_PAGO'];
	$hora = $arrOutput['HORA_PAGO'];
	$agencia = $arrOutput['AGENCIA'];
	$aut_banco = $arrOutput['AUT_BCO'];
	$usr_banco = $arrOutput['USUARIO'];
	$terminal = $arrOutput['TERMINAL'];

	$CodigoUnico = $ClsAlu->get_codigo_alumno($CuiAlumno);

	if ($bolcodigo !='' || $CodigoUnico != '') {
		$result = $ClsBol->get_asignacion_api($Codigo_pago);
		if(is_array($result)){
			foreach($result as $row){
				$codigoApi = $row["bolasig_codigo"];
				$bolcodigo = $row["bolasig_boleta_codigo"];
				$cue = $row["bolasig_cuenta"];
				$CodigoUnico = $row["bolasig_codigo_alumno"];
				$CuiAlumno = $row["bolasig_cui_alumno"];
				$bol_monto = $row["bolasig_monto"];
				$bol_pagos_pendientes = $row["bolasig_situacion"];
			}
		}
		$fechor = "$fecha $hora";
		//El van 1 es el numero del Banco G&T. 
		$ban = 1;
		//La Cue 1 es el numero de la cuenta a la que debe ir.
		$cue = 1; // Comentar si cada division tiene su propia cuenta
		$periodo = $ClsPer->get_periodo_activo();
		$codigo = $ClsBan->max_mov_cuenta($cue,$ban);
		$codigo++;
		$sql = $ClsBol->update_asignacion_api($codigoApi,$Codigo_pago,$bolcodigo,$bol_monto,$monto,$fecha,$agencia,$aut_banco,$usr_banco,$terminal,1);
		$cantidad_pagos = $monto/$bol_monto;

		if ($monto%$bol_monto == 0) {
			for ($i=0; $i < $cantidad_pagos; $i++) { 
			$sql.= $ClsBan->insert_mov_cuenta($codigo,$cue,$ban,'I',$bol_monto,'DP','BOLETA DE PAGO WS MULTI',$bolcodigo,$fecha);
			$sql.= $ClsBan->saldo_cuenta_banco($cue,$ban,$bol_monto,"+");
			$sql.= $ClsBol->insert_pago_boleta_cobro($periodo, $CodigoUnico, $CuiAlumno,0,$bolcodigo,$cue,$ban,0,$bolcodigo,$bol_monto,0,0,0,$fechor);
			$sql.= $ClsBol->cambia_pagado_boleta_cobro($bolcodigo,1);
			$bolcodigo++;
			$codigo++;
			}
		}else{
			$sql.= $ClsBan->insert_mov_cuenta($codigo,$cue,$ban,'I',$monto,'DP','BOLETA DE PAGO WS SINGLE',$bolcodigo,$fecha);
			$sql.= $ClsBan->saldo_cuenta_banco($cue,$ban,$monto,"+");
			$sql.= $ClsBol->insert_pago_boleta_cobro($periodo, $CodigoUnico, $CuiAlumno,0,$bolcodigo,$cue,$ban,0,$bolcodigo,$monto,0,0,0,$fechor);
			$sql.= $ClsBol->cambia_pagado_boleta_cobro($bolcodigo,1);
		}

		$rs = $ClsBol->exec_sql($sql);

		if($rs == 1){
			$xml=<<<XML
			<RESP_PAGOREVERSION>
				<CODIGO_RESPUESTA>000</CODIGO_RESPUESTA>
				<DESCRIPCION_RESPUESTA>Operacion Exitosa</DESCRIPCION_RESPUESTA>
			</RESP_PAGOREVERSION>
			XML;
			echo($xml);
		}else{
			$xml=<<<XML
			<RESP_PAGOREVERSION>
				<CODIGO_RESPUESTA>$Codigo_pago</CODIGO_RESPUESTA>
				<DESCRIPCION_RESPUESTA>Codigo de cliente no existe</DESCRIPCION_RESPUESTA>
			</RESP_PAGOREVERSION>
			XML;
			echo($xml);
		}
	}else{
		$xml=<<<XML
		<RESP_PAGOREVERSION>
			<CODIGO_RESPUESTA>$Codigo_pago</CODIGO_RESPUESTA>
			<DESCRIPCION_RESPUESTA>Codigo de cliente no existe</DESCRIPCION_RESPUESTA>
		</RESP_PAGOREVERSION>
		XML;
		echo($xml);
		// die();
	}

}

function API_Test($datos_xml){
	$datos_xml = simplexml_load_string($datos_xml);

	$objJsonDocument = json_encode($datos_xml);
	$arrOutput = json_decode($objJsonDocument, TRUE);
	$fecha = $arrOutput['FECHA_TRANSACCION'];
	$hora = $arrOutput['HORA_TRANSACCION'];

	echo('Fecha test: '.$fecha.' hora test: '.$hora);

}

?>