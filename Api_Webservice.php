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

$datos_xml = simplexml_load_file("Test.xml");

$prueba_xml=<<<XML
<TEST_COMUNICACION>
	<CODIGO_ENTE>001</CODIGO_ENTE>
	<USUARIO></USUARIO>
	<CLAVE></CLAVE>
	<FECHA_TRANSACCION>2017/10/13</FECHA_TRANSACCION>
	<HORA_TRANSACCION>09:39:26</HORA_TRANSACCION>
</TEST_COMUNICACION>
XML;

$prueba_xml = simplexml_load_string($prueba_xml);

///API REQUEST
$request = $_REQUEST["request"];

if($request != ""){
	switch($request){
		case "Consulta":
			//$datos_xml = $_REQUEST["pInOut"];
			API_Obtener_pagos($datos_xml);
			break;
    	case "Pago_Reversion":
			//$datos_xml = $_REQUEST["pInOut"];
			API_Realizar_pagos($datos_xml);
			break;
		case "Test_Comunicacion":
			// $tarea = $_REQUEST["pInOut"];
			API_Test($prueba_xml);
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

	$periodo = 7;
	$anio_periodo = $ClsPer->get_anio_periodo($periodo);
	$anio_actual = date("Y");
	$anio_periodo = ($anio_periodo == "")?$anio_actual:$anio_periodo;
	//// fechas ///
	if($anio_actual == $anio_periodo){
		$mes = date("m"); ///mes de este año para calculo de saldos y moras
		$dia = date("d"); 
		$fini = "01/01/$anio_actual";
		$ffin = "28/$mes/$anio_actual";
		$ffin2 = "28/12/$anio_actual";
	}else{
		$fini = "01/01/$anio_periodo";
		$ffin = "31/12/$anio_periodo";
	}
	
	$objJsonDocument = json_encode($datos_xml);
	$arrOutput = json_decode($objJsonDocument, TRUE);
	$CuiAlumno = $arrOutput['DATO_1'];
	$Codigo_pago = $arrOutput['CODIGO_PAGO'];
	$CodigoUnico = $ClsAlu->get_codigo_alumno($CuiAlumno);

	if ($CodigoUnico != '') {
		$result = $ClsBol->get_boleta_vs_pago2('',3,'',$CodigoUnico,'',$periodo,'',$fini,$ffin,1,4,'',true);
		if ($result == '!E') {
			$result = $ClsBol->get_boleta_vs_pago2('',3,'',$CodigoUnico,'',$periodo,'',$fini,$ffin2,1,4,'',true);
		}
	
		$monto_programdo = 0;
		$monto_pagado = 0;
		$referenciaX;
		if(is_array($result)){
			foreach($result as $row){
				$bolcodigo = $row["bol_codigo"];
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

			$codigo = $ClsBol->max_asignacion_api();
			$codigo++;
			$sql = $ClsBol->insert_asignacion_api($codigo,$Codigo_pago,$bolcodigo,$monto_boleta,0,0,0,0,0,0,1);
			// $sql = $ClsBol->insert_asignacion_api($codigo,$Codigo_pago,$bolcodigo,0,0,0,0,0,0);
			$rs = $ClsBol->exec_sql($sql);

			if($rs == 1){
				$xml=<<<XML
				<RESP_CONSULTA>
					<DATO_RESP_1>$bolNombreAlumno</DATO_RESP_1>
					<DATO_RESP_2>$bolApellidoAlumno</DATO_RESP_2>
					<DATO_RESP_3>$bolcodigo</DATO_RESP_3>
					<DATO_RESP_4>$mons$monto_boleta</DATO_RESP_4>
					<DATO_RESP_5></DATO_RESP_5>
					<DATO_RESP_6></DATO_RESP_6>
					<SALDO>$diferencia</SALDO>
					<TVALIDACION>1</TVALIDACION>
					<CODIGO_RESPUESTA>000</CODIGO_RESPUESTA>
					<DESCRIPCION_RESPUESTA>Consulta Exitosa</DESCRIPCION_RESPUESTA>
				</RESP_CONSULTA>
				XML;
				echo($xml);
			}else{
				$xml=<<<XML
				<RESP_CONSULTA>
					<CODIGO_RESPUESTA>001</CODIGO_RESPUESTA>
					<DESCRIPCION_RESPUESTA>Cliente no existe</DESCRIPCION_RESPUESTA>
				</RESP_CONSULTA>
				XML;
				echo($xml);	   
			}


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

	$objJsonDocument = json_encode($datos_xml);
	$arrOutput = json_decode($objJsonDocument, TRUE);
	$CuiAlumno = $arrOutput['DATO_1'];
	$Codigo_pago = $arrOutput['CODIGO_PAGO'];
	$monto = $arrOutput['MONTO_PAGADO'];
	$fecha = $arrOutput['FECHA_PAGO'];
	$hora = $arrOutput['HORA_PAGO'];
	$agencia = $arrOutput['AGENCIA'];
	$aut_banco = $arrOutput['AUT_BCO'];
	$usr_banco = $arrOutput['USUARIO'];
	$terminal = $arrOutput['TERMINAL'];
	$CodigoUnico = $ClsAlu->get_codigo_alumno($CuiAlumno);

	if ($CodigoUnico != '') {
		$result = $ClsBol->get_asignacion_api($Codigo_pago);
		if(is_array($result)){
			foreach($result as $row){
				$codigoApi = $row["bolasig_codigo"];
				$bolcodigo = $row["bolasig_boleta_codigo"];
			}
		}
		$fechor = "$fecha $hora";
		//En el codigo se define 3, por el numero de las colegiaturas, 1 es el numero del Banco G&T 
		$cue = 3;
		$ban = 1;
		$periodo = 7;
		$codigo = $ClsBan->max_mov_cuenta($cue,$ban);
		$codigo++;

      //Query
		$sql = $ClsBol->update_asignacion_api($codigoApi,$Codigo_pago,$bolcodigo, $monto, $fecha, $agencia, $aut_banco, $usr_banco, $terminal);
		$sql.= $ClsBan->insert_mov_cuenta($codigo,$cue,$ban,'I',$monto,'WS','BOLETA DE PAGO',$bolcodigo,$fecha);
		$sql.= $ClsBan->saldo_cuenta_banco($cue,$ban,$monto,"+");
		$sql.= $ClsBol->insert_pago_boleta_cobro($periodo, $CuiAlumno,0,$bolcodigo,$cue,$ban,0,$bolcodigo,$monto,0,0,0,$fechor,$CodigoUnico);
      	$sql.= $ClsBol->cambia_pagado_boleta_cobro($bolcodigo,1); // si el pago esta enlazado a alguna boleta, le cancela la situacion de pagada
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

 	function API_Test($prueba_xml)
 {
	$objJsonDocument = json_encode($prueba_xml);
	$arrOutput = json_decode($objJsonDocument, TRUE);
	$fecha = $arrOutput['FECHA_TRANSACCION'];
	$hora = $arrOutput['HORA_TRANSACCION'];

	echo('Fecha test: '.$fecha.' hora test: '.$hora);

 }

 function regresa_fecha($Fecha)
{
if ($Fecha<>""){
   $trozos=explode("/",$Fecha,3);
   return $trozos[2]."-".$trozos[1]."-".$trozos[0]; }
else
   {return $Fecha;}
}

?>