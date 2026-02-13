<?php
date_default_timezone_set("America/La_Paz");
require_once dirname(__FILE__) . '/class/class.phpmailer.php';
require_once dirname(__FILE__) . '/lib/Exception.php';
require_once dirname(__FILE__) . '/lib/PHPMailer.php';
require_once dirname(__FILE__) . '/lib/SMTP.php';

class SAPCON
{

    public $DB_HOST_qrr = 'localhost';
    public $DB_USER_qrr = 'root';
    public $DB_PASS_qrr = '123456789-';
    public $DB_NAME_qrr = 'entrega_db';
    public $DB_NAME_importaciones = 'importaciones';
    public $mainUrl     = 'https://sapb1:50000/b1s/v2/';
  //  public $server_db   = 'SBO_MONTERREY';
        public $server_db   = 'TEST_MONTERREY';
    public $url_archivo = 'http://app-web-mty/archivo_promotores/';

    public function getArray($odbc_result)
    {
        $array = [];
        while ($row = odbc_fetch_array($odbc_result)) {
            $array[] = $row;
        }
        return $array;
    }

    public function hanas($sql)
    {
        $servername = "hanab1:30015";
        $username   = "INTEGRATOR";
        $password   = "Lo%10Mandamiento%";
        $conn       = odbc_connect("Driver=HDBODBC;ServerNode=$servername;Database='$this->server_db';CHAR_AS_UTF8=1", $username, $password);
        if (! $conn) {
            exit("Connection Failed: " . odbc_errormsg());
        }
        $odbc_result = odbc_exec($conn, $sql);
        if (! $odbc_result) {
            odbc_close($conn);
            exit("SQL Error: " . odbc_errormsg($conn));
        }
        $result = $this->getArray($odbc_result);
        odbc_close($conn);
        return $result;
    }

    public function callApis_sap($type, $url, $params, $idSesion)
    {
        if ($idSesion != '') {
            $header = ['Content-type: application/json;odata=minimalmetadata;charset=utf8\r\n', "Cookie: B1SESSION=$idSesion; ROUTEID=.node0"];
        } else {
            $header = ['Content-type: application/json;odata=minimalmetadata;charset=utf8\r\n'];
        }
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => $type,
            CURLOPT_POSTFIELDS     => json_encode($params),
            CURLOPT_HTTPHEADER     => $header,
        ]);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        $response = curl_exec($curl);
        return $response;
    }

    public function hanaquery($sql)
    {
        $res = $this->hanas("CALL \"" . $this->server_db . "\".\"SP_INT_QUERY\"('$sql');");
        return $res;
    }

    public function hanacall($sql)
    {
        $res = $this->hanas("CALL \"" . $this->server_db . "\".$sql"); // Reemplazar por el nombre correcto
        return $res;
    }

    public function getUniqueObjects($arrayPrincipal, $arrayExcluir)
    {
        $uniqueArray = [];
        foreach ($arrayPrincipal as $item) {
            $found = false;
            foreach ($arrayExcluir as $excludeItem) {
                if ($item['LineNum'] === $excludeItem['LineNum']) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $uniqueArray[] = $item;
            }
        }

        return $uniqueArray;
    }

    public function find_document($tipo_documento, $n_documento)
    {
        $datat      = [];
        $otroObjeto = [];
        if (! is_numeric($n_documento)) {
            return json_encode([
                "Id"      => 0,
                "Lista"   => json_encode([]),
                "Estado"  => 0,
                "Mensaje" => "El valor no es un número."
            ]);
        }
        if (strlen($n_documento) !== 9) {
            return json_encode([
                "Id"      => 0,
                "Lista"   => json_encode([]),
                "Estado"  => 0,
                "Mensaje" => "El valor no tiene exactamente 9 dígitos. 1 "
            ]);
        }
        $datat = $this->hanacall("SP_ENT_MAIN$tipo_documento($n_documento)");

        if (count($datat) > 0) {
            for ($ilc = 0; $ilc < count($datat); $ilc++) {
                $codigo_items                      = $datat[$ilc]['CODIGO_ITEM'];
                $textoABuscar                      = "SRV";
                $datat[$ilc]['posi']               = $ilc + 1;
                $datat[$ilc]['Id_tipoentrega']     = '1';
                $datat[$ilc]['User_despachador']   = '';
                $datat[$ilc]['Nombre_despachador'] = '';
                if (strpos($codigo_items, $textoABuscar) !== false) {
                    $datat[$ilc]['campo_select']       = '1';
                    $datat[$ilc]['campo_select_color'] = '2px solid red';
                    $datat[$ilc]['es_servicio']        = '1';
                } else {
                    $datat[$ilc]['campo_select']       = '0';
                    $datat[$ilc]['campo_select_color'] = '';
                    $datat[$ilc]['es_servicio']        = '0';
                }
            }
            $otroObjeto = [
                "Id"      => 0,
                "Lista"   => json_encode($datat),
                "Estado"  => 1,
                "Mensaje" => "",
            ];
        } else {
            $otroObjeto = [
                "Id"      => 0,
                "Lista"   => json_encode([]),
                "Estado"  => 0,
                "Mensaje" => "El documento no existe o no es un documento comercial !!!"
            ];
        }
        return json_encode($otroObjeto);
    }

    public function find_log_documento($tipo_documento, $n_documento)
    {
        $datat      = [];
        $otroObjeto = [];
        if (! is_numeric($n_documento)) {
            return json_encode([
                "Id"      => 0,
                "Lista"   => json_encode([]),
                "Estado"  => 0,
                "Mensaje" => "El valor no es un número."
            ]);
        }
        if (strlen($n_documento) !== 9) {
            return json_encode([
                "Id"      => 0,
                "Lista"   => json_encode([]),
                "Estado"  => 0,
                "Mensaje" => "El valor no tiene exactamente 9 dígitos. 2 "
            ]);
        }
        $datat = $this->query("SELECT cc.appVersion, cc.despachador, cc.CODI, cc.RAMA, cc.SUCURSAL,
                          cc.TIPO, cc.OWNER, cc.CODEV, cc.MEMO, cc.person,
                          cc.item_array, cc.logArray
                   FROM log_app_entrega cc
                   WHERE cc.U_n_documento = '$n_documento'
                     AND cc.U_tipo_documento = $tipo_documento
                   ORDER BY cc.FechaRegistro DESC
                   LIMIT 1");
        if (count($datat) > 0) {
            $otroObjeto = [
                "Id"      => 0,
                "Lista"   => json_encode($datat),
                "Estado"  => 1,
                "Mensaje" => "",
            ];
        } else {
            $otroObjeto = [
                "Id"      => 0,
                "Lista"   => json_encode([]),
                "Estado"  => 0,
                "Mensaje" => "El documento no existe o no es un documento comercial !!!"
            ];
        }
        return json_encode($otroObjeto);
    }

    public function find_log_porteria_documento($tipo_documento, $n_documento)
    {
        $datat      = [];
        $otroObjeto = [];
        if (! is_numeric($n_documento)) {
            return json_encode([
                "Id"      => 0,
                "Lista"   => json_encode([]),
                "Estado"  => 0,
                "Mensaje" => "El valor no es un número."
            ]);
        }
        if (strlen($n_documento) !== 9) {
            return json_encode([
                "Id"      => 0,
                "Lista"   => json_encode([]),
                "Estado"  => 0,
                "Mensaje" => "El valor no tiene exactamente 9 dígitos. 3 "
            ]);
        }
        $datat = $this->query("SELECT cc.appVersion, cc.despachador, cc.CODI, cc.RAMA, cc.SUCURSAL,
                          cc.TIPO, cc.OWNER, cc.CODEV, cc.MEMO, cc.person,
                          cc.cantidad_total, cc.logArray , cc.FechaRegistro
                   FROM log_app_entrega_porteria cc
                   WHERE cc.U_n_documento = '$n_documento'
                     AND cc.U_tipo_documento = $tipo_documento
                   ORDER BY cc.FechaRegistro DESC
                   LIMIT 1");
        if (count($datat) > 0) {
            $otroObjeto = [
                "Id"      => 0,
                "Lista"   => json_encode($datat),
                "Estado"  => 1,
                "Mensaje" => "",
            ];
        } else {
            $otroObjeto = [
                "Id"      => 0,
                "Lista"   => json_encode([]),
                "Estado"  => 0,
                "Mensaje" => "El documento no existe o no es un documento comercial !!!"
            ];
        }
        return json_encode($otroObjeto);
    }

    public function is_valid_email($str)
    {
        $matches = null;
        return (1 === preg_match('/^[A-z0-9\\._-]+@[A-z0-9][A-z0-9-]*(\\.[A-z0-9_-]+)*\\.([A-z]{2,6})$/', $str, $matches));
    }

    public function enviar_correo_prueba($nota, $tipo_doc)
    {
        $envioo  = 0;
        $message = $this->generar_correo($nota, $tipo_doc);
        $res     = $this->hanacall("\"SBO_NOTA_EMAIL\"('$nota')");
        for ($ieh = 0; $ieh < count($res); $ieh++) {
            $Email         = $res[$ieh]['EMail'];
            $NombreUsuario = $res[$ieh]['Usuario'];
            if ($this->is_valid_email($Email)) {
                $mail = new PHPMailer;
                $mail->IsSMTP();
                $mail->CharSet    = 'UTF-8';
                $mail->Encoding   = 'base64'; //Sets Mailer to send message using SMTP
                $mail->Host       = 'mail.monterreysrl.com.bo';
                $mail->Port       = 587;                              //Sets the default SMTP server port
                $mail->SMTPAuth   = true;                             //Sets SMTP authentication.
                $mail->Username   = 'soportetic@monterreysrl.com.bo'; //Sets SMTP username
                $mail->Password   = 'sssTTT765';                      //Sets SMTP password
                $mail->SMTPSecure = '';                               //Sets connection prefix. Options are "", "ssl" or "tls"
                $mail->From       = 'soportetic@monterreysrl.com.bo'; //Sets the From email address for the message
                $mail->FromName   = 'Detalle Nota - ' . $nota;        //Sets the From name of the message
                $mail->AddAddress($Email, $NombreUsuario);            //Adds a "To" address
                $mail->WordWrap = 50;
                $mail->IsHTML(true);                        //Sets message type to HTML
                $mail->Subject = 'Detalle Nota - ' . $nota; //Sets the Subject of the message
                $mail->Body    = $message;
                if (! $mail->send()) {
                    $fecha = date('Y-m-d H:i:s');
                    $this->query("insert into log_correo(FechaRegistro, Email, Mensaje, Usuario, Documento,Estado) values  ('$fecha','$Email','$message','$NombreUsuario','$nota',0)");
                } else {
                    $envioo = 1;
                    $fecha  = date('Y-m-d H:i:s');
                    $this->query("insert into log_correo(FechaRegistro, Email, Mensaje, Usuario, Documento,Estado) values   ('$fecha','$Email','$message','$NombreUsuario','$nota',1)");
                }
            } else {
                $fecha = date('Y-m-d H:i:s');
                $this->query("insert into sin_correo( Usuario, Correo, FR) values   ('$NombreUsuario','$Email','$fecha')");
            }
        }
        return $envioo;
    }

    public function detalle_documento_ultimoo($tipo_documento, $n_documento)
    {
        $fecha_registro_porteria = '';
        $api_lista_detalle_find  = [];
        $suma_peso               = 0;
        $U_tipo_documento        = 1;
        $U_n_documento           = $n_documento;
        $data_find               = $this->find_document($U_tipo_documento, $U_n_documento);
        $data_find               = json_decode($data_find, true);
        if ($data_find['Estado'] == 1) {
            $items_estado         = $this->query("SELECT cc.Id, cc.Detalle, cc.Estado, cc.FechaRegistro, cc.Color FROM tipoentrega  cc WHERE cc.Estado=1  ORDER BY cc.FechaRegistro DESC");
            $lista_documento_find = json_decode($data_find['Lista'], true);
            $forma_de_envio       = $lista_documento_find[0]['FORMA_ENVIO'];
            $almacen              = $lista_documento_find[0]['ALMACEN'];
            $data_find_log        = $this->find_log_documento($U_tipo_documento, $U_n_documento);
            $data_find_log        = json_decode($data_find_log, true);
            if ($data_find_log['Estado'] == 1) {
                $lista_documento_find_log  = json_decode($data_find_log['Lista'], true);
                $lista_documento_find_logg = json_decode(($lista_documento_find_log[0]['logArray']), true);
                $datat_fecha_fin           = $this->query("SELECT cc.FechaRegistro FROM log_app_entrega_porteria cc WHERE cc.U_n_documento = '$n_documento' AND cc.U_tipo_documento = $tipo_documento ORDER BY cc.FechaRegistro DESC  LIMIT 1");
                foreach ($lista_documento_find as $elementt) {
                    if (count($datat_fecha_fin) > 0) {
                        $elementt['U_fecha_fin_porteria'] = $datat_fecha_fin[0]['FechaRegistro'];
                    } else {
                        $elementt['U_fecha_fin_porteria'] = 'Sin Fecha salida por porteria';
                    }
                    $elementt['U_fecha_creacionnn'] = date('F j, Y', strtotime($elementt['FECHA']));
                    $elementt['forma']              = $elementt['FORMA_ENVIO'] == 1 ? 'ENTREGA EN ALMACEN' : 'ENTREGA EN OBRA';
                    $elementt['U_fecha_actual']     = date('F j, Y, H:i:s');
                    $elementt['PENDIENTE_aux']      = floatval($elementt['PENDIENTE']);
                    $elementt['Cargado']            = floatval($elementt['CANTIDAD']) - floatval($elementt['PENDIENTE']);
                    $elementt['peso_aux']           = (floatval($elementt['PENDIENTE']) * floatval($elementt['PESO'])) / floatval($elementt['CANTIDAD']);
                    $find_log_doc                   = null;
                    if (is_array($lista_documento_find_logg) || is_object($lista_documento_find_logg)) {
                        foreach ($lista_documento_find_logg as $log_doc) {
                            if (is_array($log_doc) && $log_doc['CODIGO_ITEM'] === $elementt['CODIGO_ITEM']) {
                                $find_log_doc = $log_doc;
                                break;
                            } elseif (is_object($log_doc) && $log_doc->CODIGO_ITEM === $elementt['CODIGO_ITEM']) {
                                $find_log_doc = $log_doc;
                                break;
                            }
                        }
                    }
                    if ($find_log_doc) {
                        $userFound = null;
                        foreach ($items_estado as $user) {
                            if ((int) $user['Id'] === (int) $find_log_doc['Id_tipoentrega']) {
                                $userFound = $user;
                                break;
                            }
                        }
                        if ($userFound) {
                            $elementt['Color_id_tipoentrega']   = $find_log_doc['campo_select'] == '0' ? 'red' : $userFound['Color'];
                            $elementt['Detalle_id_tipoentrega'] = $find_log_doc['campo_select'] == '0' ? 'Pendiente' : $userFound['Detalle'];
                            $elementt['Id_tipoentrega']         = $find_log_doc['Id_tipoentrega'];
                            $elementt['User_despachador']       = $find_log_doc['User_despachador'];
                            $elementt['Nombre_despachador']     = $find_log_doc['Nombre_despachador'];
                            $elementt['campo_select']           = $find_log_doc['campo_select'];
                            $elementt['campo_select_color']     = $find_log_doc['campo_select_color'];
                            $elementt['campo_select_activado']  = $elementt['Id_tipoentrega'] == '2' ? 0 : 1;
                        }
                    }
                    $api_lista_detalle_find[] = $elementt;
                }

                $api_lista_detalle_find = array_values($api_lista_detalle_find); // Para asegurar índices consecutivos
                usort($api_lista_detalle_find, function ($a, $b) {
                    return $a['posi'] - $b['posi'];
                });
                $lista_find_info_servicios = array_filter($api_lista_detalle_find, function ($objeto) {
                    return $objeto['es_servicio'] === '1';
                });

                $lista_find_info_parcial_otros_despachadores = array_filter($api_lista_detalle_find, function ($objeto) {
                    return $objeto['es_servicio'] === '0' && $objeto['Id_tipoentrega'] === '2';
                });

                $lista_find_info_no_es_parcial = array_filter($api_lista_detalle_find, function ($objeto) {
                    return $objeto['campo_select'] === '1' && $objeto['es_servicio'] === '0' && $objeto['Id_tipoentrega'] === '1';
                });

                $data_find_log_poteria = json_decode($this->find_log_porteria_documento($U_tipo_documento, $U_n_documento), true);
                if ($data_find_log_poteria['Estado'] == 1) {
                    $data_find_log_poteria_front = json_decode($data_find_log_poteria['Lista'], true);
                    $cantidad_total_front        = $data_find_log_poteria_front[0]['cantidad_total'];
                    $fecha_registro_porteria     = $data_find_log_poteria_front[0]['FechaRegistro'];
                    $aux_porteria_log_array      = json_decode($data_find_log_poteria_front[0]['logArray'], true);
                    $objetosUnicosArray1         = array_filter($lista_find_info_no_es_parcial, function ($obj1) use ($aux_porteria_log_array) {
                        foreach ($aux_porteria_log_array as $obj2) {
                            if ($obj1['CODIGO_ITEM'] === $obj2['CODIGO_ITEM']) {
                                return false;
                            }
                        }
                        return true;
                    });

                    $api_lista_detalle_find = array_merge($api_lista_detalle_find, $lista_find_info_parcial_otros_despachadores);
                    if (count($objetosUnicosArray1) > 0) {
                        $api_lista_detalle_find = array_merge($api_lista_detalle_find, $objetosUnicosArray1);
                    }
                    usort($api_lista_detalle_find, function ($a, $b) {
                        return $a['posi'] - $b['posi'];
                    });
                } elseif ($data_find_log_poteria['Estado'] == 0) {
                    $api_lista_detalle_find = array_merge(
                        $api_lista_detalle_find,
                        $lista_find_info_servicios,
                        $lista_find_info_parcial_otros_despachadores,
                        $lista_find_info_no_es_parcial
                    );
                    usort($api_lista_detalle_find, function ($a, $b) {
                        return $a['posi'] - $b['posi'];
                    });
                }
            }
        } else {

            $datat_fecha_fin      = $this->query("SELECT cc.FechaRegistro FROM log_app_entrega_porteria cc WHERE cc.U_n_documento = '$n_documento'   AND cc.U_tipo_documento = $tipo_documento ORDER BY cc.FechaRegistro DESC  LIMIT 1");
            $lista_documento_find = $lista_documento_find ?? [];

            foreach ($lista_documento_find as $elementt) {

                if (count($datat_fecha_fin) > 0) {
                    $elementt['U_fecha_fin_porteria'] = $datat_fecha_fin[0]['FechaRegistro'];
                } else {
                    $elementt['U_fecha_fin_porteria'] = 'Sin Fecha salida por porteria';
                }

                $elementt['U_fecha_creacionnn'] = date('F j, Y', strtotime($elementt['FECHA']));
                $elementt['forma']              = $elementt['FORMA_ENVIO'] == 1 ? 'ENTREGA EN ALMACEN' : 'ENTREGA EN OBRA';
                $elementt['U_fecha_actual']     = date('F j, Y, H:i:s');
                $elementt['PENDIENTE_aux']      = floatval($elementt['PENDIENTE']);
                $elementt['Cargado']            = floatval($elementt['CANTIDAD']) - floatval($elementt['PENDIENTE']);
                $elementt['peso_aux']           = (floatval($elementt['PENDIENTE']) * floatval($elementt['PESO'])) / floatval($elementt['CANTIDAD']);
                $find_log_doc                   = null;
                $lista_documento_find_logg      = $lista_documento_find_logg ?? [];

                if (is_array($lista_documento_find_logg) || is_object($lista_documento_find_logg)) {
                    foreach ($lista_documento_find_logg as $log_doc) {
                        if (is_array($log_doc) && $log_doc['CODIGO_ITEM'] === $elementt['CODIGO_ITEM']) {
                            $find_log_doc = $log_doc;
                            break;
                        } elseif (is_object($log_doc) && $log_doc->CODIGO_ITEM === $elementt['CODIGO_ITEM']) {
                            $find_log_doc = $log_doc;
                            break;
                        }
                    }
                }

                if ($find_log_doc) {
                    $userFound = null;

                    $items_estado = $items_estado ?? [];
                    foreach ($items_estado as $user) {
                        if ((int) $user['Id'] === (int) $find_log_doc['Id_tipoentrega']) {
                            $userFound = $user;
                            break;
                        }
                    }
                    if ($userFound) {
                        $elementt['Color_id_tipoentrega']   = $find_log_doc['campo_select'] == '0' ? 'red' : $userFound['Color'];
                        $elementt['Detalle_id_tipoentrega'] = $find_log_doc['campo_select'] == '0' ? 'Pendiente' : $userFound['Detalle'];
                        $elementt['Id_tipoentrega']         = $find_log_doc['Id_tipoentrega'];
                        $elementt['User_despachador']       = $find_log_doc['User_despachador'];
                        $elementt['Nombre_despachador']     = $find_log_doc['Nombre_despachador'];
                        $elementt['campo_select']           = $find_log_doc['campo_select'];
                        $elementt['campo_select_color']     = $find_log_doc['campo_select_color'];
                        $elementt['campo_select_activado']  = $elementt['Id_tipoentrega'] == '2' ? 0 : 1;
                    }
                }
                $api_lista_detalle_find[] = $elementt;
            }

            $api_lista_detalle_find = array_values($api_lista_detalle_find); // Para asegurar índices consecutivos
            usort($api_lista_detalle_find, function ($a, $b) {
                return $a['posi'] - $b['posi'];
            });
            $lista_find_info_servicios = array_filter($api_lista_detalle_find, function ($objeto) {
                return $objeto['es_servicio'] === '1';
            });

            $lista_find_info_parcial_otros_despachadores = array_filter($api_lista_detalle_find, function ($objeto) {
                return $objeto['es_servicio'] === '0' && $objeto['Id_tipoentrega'] === '2';
            });

            $lista_find_info_no_es_parcial = array_filter($api_lista_detalle_find, function ($objeto) {
                return $objeto['campo_select'] === '1' && $objeto['es_servicio'] === '0' && $objeto['Id_tipoentrega'] === '1';
            });

            $api_lista_detalle_find = array_filter($api_lista_detalle_find, function ($objeto) {
                return $objeto['campo_select'] === '0' && $objeto['es_servicio'] === '0' && $objeto['Id_tipoentrega'] === '1';
            });

            $data_find_log_poteria = json_decode($this->find_log_porteria_documento($U_tipo_documento, $U_n_documento), true);
            if ($data_find_log_poteria['Estado'] == 1) {
                $data_find_log_poteria_front = json_decode($data_find_log_poteria['Lista'], true);
                $cantidad_total_front        = $data_find_log_poteria_front[0]['cantidad_total'];
                $fecha_registro_porteria     = $data_find_log_poteria_front[0]['FechaRegistro'];
                $aux_porteria_log_array      = json_decode($data_find_log_poteria_front[0]['logArray'], true);
                $objetosUnicosArray1         = array_filter($lista_find_info_no_es_parcial, function ($obj1) use ($aux_porteria_log_array) {
                    foreach ($aux_porteria_log_array as $obj2) {
                        if ($obj1['CODIGO_ITEM'] === $obj2['CODIGO_ITEM']) {
                            return false;
                        }
                    }
                    return true;
                });

                $api_lista_detalle_find = array_merge($api_lista_detalle_find, $lista_find_info_parcial_otros_despachadores);
                if (count($objetosUnicosArray1) > 0) {
                    $api_lista_detalle_find = array_merge($api_lista_detalle_find, $objetosUnicosArray1);
                }
                usort($api_lista_detalle_find, function ($a, $b) {
                    return $a['posi'] - $b['posi'];
                });
            } elseif ($data_find_log_poteria['Estado'] == 0) {
                $api_lista_detalle_find = array_merge(
                    $api_lista_detalle_find,
                    $lista_find_info_servicios,
                    $lista_find_info_parcial_otros_despachadores,
                    $lista_find_info_no_es_parcial
                );
                usort($api_lista_detalle_find, function ($a, $b) {
                    return $a['posi'] - $b['posi'];
                });
            }
        }

        return json_encode($api_lista_detalle_find);
    }

    public function generar_correo($nota, $tipo_doc)
    {
        $api_lista_detalle_find = $this->detalle_documento_ultimoo($tipo_doc, $nota);
        $api_lista_detalle_find = json_decode($api_lista_detalle_find, true);
        $message = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Documento</title>
    <style>
        body {
            font-family: "Segoe UI", "Arial", sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
            color: rgb(34, 34, 34);
            line-height: 24px;
            font-size: 16px;
            font-weight: 400;
        }


        .company-name {
            color: white;
            font-weight: bold;
            font-size: 1.2em;
            margin-bottom: 5px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 10px;
        }

        .card-header {
            background-color: rgb(160, 28, 29);
            color: #fff;
            padding: 12px;
            font-size: 14pt;
            font-weight: bold;
            text-align: center;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .card-body {
            padding: 12px;
            font-size: 16px;
            font-weight: 400;
        }

        .store-name,
        .field-title {
            font-weight: 700;
            margin: 5px 0;
            text-decoration: none;
            font-size: 14px;
        }

        .store-name_ {
            margin: 10px 0px;
            20px;
            0px
        }

        .campo_2 {
            font-weight: bold;
        }

        .campo_4 {
            font-size: 14pt;
            font-weight: bold;
        }

        .field-title {
            color: #2a2a2a;
            font-weight: bold;
        }

        .golden-text {
            color: goldenrod;
        }

        .blue-text {
            color: blue;
        }

        .green-text {
            color: green;
        }

        .chocolate-text {
            color: chocolate;
        }

        .brown-text {
            color: brown;
        }

        .darkgreen-text {
            color: darkgreen;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .col-lg-7,
        .col-lg-9,
        .col-lg-3,
        .col-lg-12 {
            padding: 5px;
        }

        .col-lg-7,
        .col-lg-9 {
            flex: 70%;
        }

        .col-lg-3 {
            flex: 30%;
        }

        p {
            line-height: 1.5;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        td {
            padding: 8px;
            border-right: 1px solid #ddd;
        }

        td:last-child {
            border-right: none;
        }

        .card-body table {
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .header {

            padding: 10px 0;
        }

        .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        .header-logo img {
            height: 50px;
        }

        .header-contact {
            display: flex;
            align-items: center;
        }

        .header-sucursales a {
            color: white;
            text-decoration: none;
            margin-right: 20px;
            font-size: 14px;
        }

        .header-whatsapp a {
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        .header-whatsapp i {
            margin-right: 5px;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-contact {
                margin-top: 10px;
            }

            .header-logo {
                margin-bottom: 10px;
                background-color: white;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="col-lg-12">
            <div class="card">
                ' . ((! empty($api_lista_detalle_find)) ? '
                <div class="header">
                    <div class="container">
                        <div class="header-logo" style="background-color:rgb(160, 28, 29);">
                            <span class="company-name">Monterrey SRL</span>
                        </div>
                        <div class="header-contact" style="background-color: rgb(160, 28, 29);">
                            <div class="header-sucursales">
                                <a href="https://www.monterrey.com.bo/contactanos/sucursales">
                                    <i class="fa fa-map-marker"></i> Conoce nuestras <strong>Sucursales</strong>
                                </a>
                            </div>
                            <div class="header-whatsapp" style="background-color: rgb(160, 28, 29);">
                                <a href="https://api.whatsapp.com/send?phone=59171300033" target="_blank">
                                    <i class="fa fa-whatsapp"></i> Whatsapp <strong>713-00033</strong>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-header">
                    <p class="campo_2 store-name">
                        <span class="document-data">Datos del Documento Orden de Carga - ' . $nota . '</span>
                    </p>
                </div>' : '') . '<div class="card-body" style="width: 100%; overflow-x: auto;">
                    <div class="row flex_row">
                        <div class="col-lg-12">
                            ' . (empty($api_lista_detalle_find) ? '
                            <p>No hay datos disponibles para mostrar.</p>
                            ' : '
                            <p class="store-name golden-text field-title">Cliente: ' .
                                htmlspecialchars($api_lista_detalle_find[0]['CODIGO']) . '/' .
                                htmlspecialchars($api_lista_detalle_find[0]['CLIENTE']) . '</p>
                            <p class="store-name blue-text field-title">Vendedor: ' .
                                htmlspecialchars($api_lista_detalle_find[0]['VENDEDOR']) . '</p>
                            <p class="store-name chocolate-text field-title">Fecha de Retiro: ' .
                                htmlspecialchars($api_lista_detalle_find[0]['U_fecha_fin_porteria']) . '</p>
                            <p class="store-name green-text field-title">Sucursal de Retiro: ' .
                                htmlspecialchars($api_lista_detalle_find[0]['DIRECCION']) . ' - ' .
                                htmlspecialchars($api_lista_detalle_find[0]['ALMACEN']) . '</p>
                            ') . '
                        </div>
                    </div>
                </div>
            </div>
        </div>';

        $message .= '<div class="col-lg-12">
            <div class="card">' . ((! empty($api_lista_detalle_find) && count($api_lista_detalle_find) > 0) ? '
                <div class="card-header">
                    <p class="campo_2 store-name">Lista de Ítems</p>
                </div>' : '');
                foreach ($api_lista_detalle_find as $i => $item) {
                $message .= '
                <div class="card-body" style="width: 100%; overflow-x: auto; margin: 0; padding: 0;">
                    <table style="width: 100%; border-collapse: collapse; margin: 0; padding: 0;">
                        <tr>
                            <td style="width: 70%; vertical-align: top; padding: 0;">
                                <p class="campo_2 store-name">Detalle</p>
                                <p class="store-name golden-text"># ' . ($i + 1) . ' - Código: ' .
                                    htmlspecialchars($item['CODIGO_ITEM']) . ' - M.E.: ' .
                                    htmlspecialchars($item['MODO_ENTREGA']) . '</p>
                                <p class="store-name" style="white-space: pre-line;">Descripción: ' .
                                    htmlspecialchars($item['DESCRIPCION']) . '</p>
                                <p class="store-name blue-text">Cantidad: ' . htmlspecialchars($item['CANTIDAD']) . ' -
                                    U.M.: ' . htmlspecialchars($item['MEDIDA']) . '</p>
                                <p class="store-name blue-text" style="white-space: pre-line; ">Por Cargar: ' .
                                    htmlspecialchars($item['PENDIENTE_aux']) . ' - Peso: ' .
                                    htmlspecialchars($item['peso_aux']) . ' Kgs.</p>
                                <p class="store-name blue-text" style="white-space: pre-line; margin-bottom: 10px;">
                                    Cargado: ' . htmlspecialchars($item['Cargado']) . '</p>
                                <!-- Añadido margen inferior -->
                            </td>
                            <td style="width: 30%; vertical-align: top; padding: 0;">
                                <p class="campo_2 store-name" style="margin: 0;">Tipo de Entrega</p>
                                <p class="store-name campo_3"
                                    style="color: ' . htmlspecialchars($item['Color_id_tipoentrega']) . ';">' .
                                    htmlspecialchars($item['Detalle_id_tipoentrega']) . '</p>
                            </td>
                        </tr>
                    </table>
                </div>';
                }
                $message .= '
            </div>
        </div>';
        return $message;
    }
 public function query_importaciones($sql)
    {
        $conn = mysqli_connect($this->DB_HOST_qrr, $this->DB_USER_qrr, $this->DB_PASS_qrr, $this->DB_NAME_importaciones);
        if ($conn->connect_error) {
            trigger_error('Database connection failed: ' . $conn->connect_error, E_USER_ERROR);
        }
     //   mysqli_query($conn, "SET NAMES 'utf8mb4'");


         mysqli_set_charset($conn, "utf8mb4");

        if (strpos($sql, 'insert') !== false) {
            mysqli_query($conn, $sql);
            $last_id = mysqli_insert_id($conn);
            return $last_id;
        }
        if (strpos($sql, 'update') !== false | strpos($sql, 'delete') !== false) {
            $result   = $conn->query($sql);
            $affected = mysqli_affected_rows($conn);
            return $affected;
        }
        $result = mysqli_query($conn, $sql);
        $arr    = [];
        if ($result === false) {
            trigger_error('Wrong SQL: ' . $sql . ' Error: ' . $conn->error, E_USER_ERROR);
        } else {
            $result->data_seek(0);
            while ($row = $result->fetch_assoc()) {
                $arr[] = $row;
            }
        }
        return $arr;
    }
    public function query($sql)
    {
        $conn = mysqli_connect($this->DB_HOST_qrr, $this->DB_USER_qrr, $this->DB_PASS_qrr, $this->DB_NAME_qrr);
        if ($conn->connect_error) {
            trigger_error('Database connection failed: ' . $conn->connect_error, E_USER_ERROR);
        }
     //   mysqli_query($conn, "SET NAMES 'utf8mb4'");


         mysqli_set_charset($conn, "utf8mb4");

        if (strpos($sql, 'insert') !== false) {
            mysqli_query($conn, $sql);
            $last_id = mysqli_insert_id($conn);
            return $last_id;
        }
        if (strpos($sql, 'update') !== false | strpos($sql, 'delete') !== false) {
            $result   = $conn->query($sql);
            $affected = mysqli_affected_rows($conn);
            return $affected;
        }
        $result = mysqli_query($conn, $sql);
        $arr    = [];
        if ($result === false) {
            trigger_error('Wrong SQL: ' . $sql . ' Error: ' . $conn->error, E_USER_ERROR);
        } else {
            $result->data_seek(0);
            while ($row = $result->fetch_assoc()) {
                $arr[] = $row;
            }
        }
        return $arr;
    }
}
