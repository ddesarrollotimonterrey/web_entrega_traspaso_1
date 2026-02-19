<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Access-Control-Allow-Origin: *');
date_default_timezone_set("America/La_Paz");
header('Content-Type: application/json; charset=UTF-8');
include "SAPC.php";
$call        = $_GET['nombre'];
$fecha       = date('Y-m-d H:i:s');
$conexionsap = new SAPCON();

switch ($call) {
    case 'login_et_app':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data            = file_get_contents('php://input');
            $decodedObject   = json_decode($data, true);
            $user            = $decodedObject['user'] ?? '';
            $pase            = $decodedObject['pase'] ?? '';
            $canal           = $decodedObject['canal'] ?? ''; // movil | web
            $accesoPermitido = false;
            $url             = $conexionsap->mainUrl . 'Login';
            $data_array      = [
                "UserName"  => $user,
                "Password"  => $pase,
                "CompanyDB" => $conexionsap->server_db,
            ];
            $otroObjeto = [
                "Id"      => 0,
                "Lista"   => [],
                "Estado"  => 0,
                "Mensaje" => "Vacio",
            ];
            $res         = $conexionsap->callApis_sap('POST', $url, $data_array, null);
            $data_arrayy = json_decode($res, true);
            if (isset($data_arrayy['SessionId'])) {
                $datat = $conexionsap->hanacall("\"SP_INT_DATA\"('$user')");
                if (count($datat) > 0) {
                    $datat[0]['SessionId'] = $data_arrayy['SessionId'];
                    if ($canal === 'movil' && in_array($datat[0]['TIPOENTREGA'], [1, 2])) {
                        $accesoPermitido = true;
                    }
                    if ($canal === 'web' && in_array($datat[0]['TIPOENTREGA'], [3, 4, 5])) {
                        $accesoPermitido = true;
                    }
                    if (! $accesoPermitido) {
                        $otroObjeto = [
                            "Id"      => 0,
                            "Lista"   => $datat,
                            "Estado"  => 0,
                            "Mensaje" => "Acceso denegado. No tiene permisos para ingresar al sistema.",
                        ];
                    } else {
                        $otroObjeto = [
                            "Id"      => 0,
                            "Lista"   => $datat,
                            "Estado"  => 1,
                            "Mensaje" => "Bienvenid@, " . $datat[0]['PROPIETARIO'],
                        ];
                    }
                }
            } elseif (isset($data_arrayy['error'])) {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => [],
                    "Estado"  => 0,
                    "Mensaje" => $data_arrayy['error']['message'],
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'existe_documento_en_tabla':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data          = file_get_contents('php://input');
            $otroObjeto    = [];
            $decodedObject = json_decode($data, true);
            $doc           = $decodedObject['U_n_documento'];
            $perfil        = $decodedObject['perfil'];
            $balanza       = $decodedObject['balanza'];
            $almacen       = $decodedObject['almacen'];
            $datat         = [];

            if (! preg_match('/^\d{9}$/', $doc)) {
                echo json_encode([
                    "Id"      => 0,
                    "Lista"   => [],
                    "Estado"  => 0,
                    "Mensaje" => "El número de documento debe tener exactamente 9 dígitos numéricos",
                ]);
                return;
            }

            $datat = $conexionsap->hanacall("SP_ENT_MAIN2($doc)");
            if (count($datat) > 0) {
                $ALMACEN_D = $datat[0]['ALMACEN_D'];
                if ($ALMACEN_D === $almacen) {
                    $cant_body = $conexionsap->query("CALL `pa_consulta_por_perfil`('$doc', $perfil, '$balanza')");
                    if (count($cant_body) > 0) {
                        $otroObjeto = [
                            "Id"      => 0,
                            "Lista"   => $cant_body,
                            "Estado"  => 1,
                            "Mensaje" => "",
                        ];
                    }
                } else {
                    $otroObjeto = [
                        "Id"      => 0,
                        "Lista"   => [],
                        "Estado"  => 0,
                        "Mensaje" => "El Documento no pertenece a este almacen",
                    ];
                }
            } else {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => [],
                    "Estado"  => 0,
                    "Mensaje" => "El documento no exite !!!",
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'lista_almacenes':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $otroObjeto = [];
            $datat      = $conexionsap->hanacall("\"SBO_LOGISTICA_ALMACEN\"()");
            if (count($datat) > 0) {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => $datat,
                    "Estado"  => 1,
                    "Mensaje" => "",
                ];
            } else {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => $data,
                    "Estado"  => 0,
                    "Mensaje" => "Lista Vacia",
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'lista_meses':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $otroObjeto = [
                "Id"      => 0,
                "Lista"   => [],
                "Estado"  => 0,
                "Mensaje" => "Lista Vacia",
            ];
            $datat = $conexionsap->query("select * from view_lista_meses");
            if (count($datat) > 0) {
                foreach ($datat as &$row) {
                    $row['value'] = (int) $row['value']; // 👈 FORZAR A INT
                }
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => $datat,
                    "Estado"  => 1,
                    "Mensaje" => "",
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'informacion_SP_INT_DATA':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $DocEntry = 'JMAMANI';
            $datat    = $conexionsap->hanacall("\"SP_INT_DATA\"('$DocEntry')");
            echo json_encode($datat);
        }
        break;

    case 'SP_ENT_MAIN5_REP':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $datat = $conexionsap->hanacall("\"SP_ENT_MAIN5_REP\"('ALM-V-07')");
            echo json_encode($datat);
        }
        break;

    case 'FN_EXXIS_ESTADO':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $DocEntry = 1983460;
            $data     = $conexionsap->hanacall("\"SP_INT_VALIDAR_FEX\"($DocEntry)");
            echo json_encode($data);
        }
        break;

    case 'login_entrega1':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data          = file_get_contents('php://input');
            $otroObjeto    = [];
            $decodedObject = json_decode($data, true);
            $user          = $decodedObject['user'];
            $pase          = $decodedObject['pase'];
            $url           = $conexionsap->mainUrl . 'Login';
            $data_array    = ["UserName" => $user, "Password" => $pase, "CompanyDB" => $conexionsap->server_db];
            $res           = $conexionsap->callApis_sap('POST', $url, $data_array, null);
            $data_arrayy   = json_decode($res, true);
            echo json_encode($data_arrayy);
            return;
        }
        break;

    case 'lista_estado_material':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $otroObjeto        = [];
            $lista_version_app = $conexionsap->query_importaciones("SELECT * from estado_proyecto cc order by cc.Id desc");
            if (count($lista_version_app) > 0) {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => ($lista_version_app),
                    "Estado"  => 1,
                    "Mensaje" => "",
                ];
            } else {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => ($lista_version_app),
                    "Estado"  => 0,
                    "Mensaje" => "Lista Vacia",
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;
    case 'ultima_version_app':
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $otroObjeto        = [];
            $lista_version_app = $conexionsap->query("SELECT cc.Id, cc.Version, cc.FechaRegistro, cc.Estado FROM versionapp_1 cc ORDER BY cc.FechaRegistro DESC LIMIT 1");
            if (count($lista_version_app) > 0) {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => ($lista_version_app),
                    "Estado"  => 1,
                    "Mensaje" => "",
                ];
            } else {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => ($lista_version_app),
                    "Estado"  => 0,
                    "Mensaje" => "Lista Vacia",
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'actualizar_token_id':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data          = file_get_contents('php://input');
            $otroObjeto    = [];
            $decodedObject = json_decode($data, true);
            $person        = $decodedObject['person'];
            $Ultimaversion = $decodedObject['Ultimaversion'];
            $pushToken     = $decodedObject['pushToken'];
            $cant_body     = $conexionsap->query("insert INTO log_ingreso (person, ultimaversion, tokenpush, fecharegistro) VALUES ('$person', '$Ultimaversion', '$pushToken', '$fecha');");
            if ($cant_body > 0) {
                $otroObjeto = [
                    "Id"      => $cant_body,
                    "Estado"  => 1,
                    "Mensaje" => "Registrado Correctamente",
                ];
            } else {
                $otroObjeto = [
                    "Id"      => $cant_body,
                    "Estado"  => 0,
                    "Mensaje" => "Error al Registrar",
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'listar_notificacion_x_usuario_cantidad':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data          = file_get_contents('php://input');
            $otroObjeto    = [];
            $decodedObject = json_decode($data, true);
            $person        = $decodedObject['usuario'];
            $cant_body     = $conexionsap->query("SELECT COUNT(*)  AS cantidad FROM lognotificacion cc  WHERE cc.Usuario='$person' AND cc.NotificacionRecibida=1 AND cc.Visto=0  ORDER BY cc.FechaRegistro desc");
            if ($cant_body > 0) {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => json_encode($cant_body),
                    "Estado"  => 1,
                    "Mensaje" => "",
                ];
            } else {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => [],
                    "Estado"  => 0,
                    "Mensaje" => "Error al devolver Lista",
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'getData_valii':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data          = file_get_contents('php://input');
            $decodedObject = json_decode($data, true);
            $usuario       = $decodedObject['usuario'];
            $otroObjeto    = [];
            $datat         = $conexionsap->hanacall("\"SP_INT_DATA\"('$usuario')");
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
                    "Lista"   => json_encode($datat),
                    "Estado"  => 0,
                    "Mensaje" => "Lista Vacia",
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'lista_cliente_x_promotor_servicio':
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $id_empleado = $_GET['id_empleado'];
            $limit       = $_GET['limit'];
            $limitt      = $_GET['limitt'];
            $valor       = $_GET['valor'];
            $array       = [];
            $res         = $conexionsap->hanacall("\"SBO_XMOBILE_CLIENTES\"('$id_empleado','$valor','$limit')");
            if ($limit == count($res)) {
                foreach ($res as $row) {
                    $array[] = $row;
                }
                $invertir_array = array_reverse($array, true);
                $salida         = array_slice($invertir_array, 0, $limitt);
                echo json_encode($salida);
            } else {
                $difer = $limit - count($res);
                if ($difer < $limitt) {

                    foreach ($res as $row) {
                        $array[] = $row;
                    }
                    $difer          = $limitt - $difer;
                    $invertir_array = array_reverse($array, true);
                    $salida         = array_slice($invertir_array, 0, $difer);
                    echo json_encode($salida);
                }
            }
            return;
        }
        break;

    case 'eraser1':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data          = file_get_contents('php://input');
            $decodedObject = json_decode($data, true);
            $otroObjeto    = [];
            $codigo_user   = $decodedObject['codigo_user'];
            $sucusal       = $decodedObject['sucusal'];
            $tipo          = $decodedObject['tipo'];
            $limit         = $decodedObject['limit'];
            $limitt        = $decodedObject['limitt'];
            $valor         = $decodedObject['valor'];
            $datat         = [];

            if ($tipo == 'U') {
                $query = 'select "Code",a."U_n_documento" , a."U_n_ticket_balanza" , a."U_kg_balanza", a."U_chofer", a."U_despachador" ,a."U_fecha_salida", a."U_hora_salida",a."U_usuario_creacion", a."U_usuario_sucursal",
          a."U_fecha_creacion", a."U_hora_creacion", a."U_forma_envio" ,  a."U_tipo_documento", a."U_cod_despachador", a."U_cod_usuario",a."U_almacen", a."U_Parcial", a."U_baja" as "estado"
          from "@PORTERIA" a where  a."U_n_documento"  like \'\'%' . $valor . '%\'\'  and   a."U_cod_usuario"=  \'\'' . $codigo_user . '\'\'   and   a."U_usuario_sucursal"=\'\'' . $sucusal . '\'\'
          order by a."U_fecha_creacion" desc,a."U_hora_creacion" desc  LIMIT \'\'' . $limitt . '\'\' OFFSET \'\'' . $limit . '\'\'';
            }

            $datat = $conexionsap->hanaquery($query);
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
                    "Lista"   => json_encode($datat),
                    "Estado"  => 0,
                    "Mensaje" => "Lista Vacia",
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'eraser2':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data          = file_get_contents('php://input');
            $decodedObject = json_decode($data, true);
            $otroObjeto    = [];
            $codigo_user   = $decodedObject['codigo_user'];
            $sucusal       = $decodedObject['sucusal'];
            $fecha_i       = $decodedObject['fecha_i'];
            $fecha_f       = $decodedObject['fecha_f'];
            $tipo          = $decodedObject['tipo'];
            $limit         = $decodedObject['limit'];
            $limitt        = $decodedObject['limitt'];
            $valor         = $decodedObject['valor'];
            $datat         = [];
            if ($tipo == 'U') {
                $query = 'select "Code",a."U_n_documento" , a."U_n_ticket_balanza" , a."U_kg_balanza", a."U_chofer", a."U_despachador" ,a."U_fecha_salida", a."U_hora_salida",a."U_usuario_creacion", a."U_usuario_sucursal",
          a."U_fecha_creacion", a."U_hora_creacion", a."U_forma_envio" ,  a."U_tipo_documento", a."U_cod_despachador", a."U_cod_usuario",a."U_almacen", a."U_Parcial", a."U_baja" as "estado"
          from "@PORTERIA" a where  a."U_n_documento"  like \'\'%' . $valor . '%\'\'  and   a."U_cod_usuario"=  \'\'' . $codigo_user . '\'\'   and   a."U_usuario_sucursal"=\'\'' . $sucusal . '\'\'
          and TO_DATE(a."U_fecha_creacion")  between \'\'' . $fecha_i . '\'\'  and \'\'' . $fecha_f . '\'\'
          order by a."U_fecha_creacion" desc,a."U_hora_creacion" desc  LIMIT \'\'' . $limitt . '\'\' OFFSET \'\'' . $limit . '\'\'';
            }

            $datat = $conexionsap->hanaquery($query);

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
                    "Lista"   => json_encode($datat),
                    "Estado"  => 0,
                    "Mensaje" => "Lista Vacia",
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'buscar_documento':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data             = file_get_contents('php://input');
            $decodedObject    = json_decode($data, true);
            $U_tipo_documento = $decodedObject['U_tipo_documento'];
            $U_n_documento    = $decodedObject['U_n_documento'];
            $otroObjeto       = [];
            $datat            = [];
            $query            = "select * from \"@PORTERIA\" where \"U_tipo_documento\"=$U_tipo_documento and \"U_n_documento\"=$U_n_documento";
            $datat            = $conexionsap->hanaquery($query);
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
                    "Lista"   => json_encode($datat),
                    "Estado"  => 0,
                    "Mensaje" => "Lista Vacia",
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'find_log_porteria':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data             = file_get_contents('php://input');
            $decodedObject    = json_decode($data, true);
            $U_tipo_documento = $decodedObject['U_tipo_documento'];
            $U_n_documento    = $decodedObject['U_n_documento'];

            $otroObjeto = [];
            $datat      = [];
            if (is_numeric($U_n_documento)) {

                if (strlen($U_n_documento) === 9) {
                    $datat = [];
                    $datat = $conexionsap->query("SELECT cc.appVersion, cc.despachador, cc.CODI, cc.RAMA, cc.SUCURSAL, cc.TIPO, cc.OWNER, cc.CODEV, cc.MEMO, cc.person,cc.cantidad_total, logArray FROM log_app_entrega_porteria cc
                            WHERE cc.U_n_documento='$U_n_documento' AND cc.U_tipo_documento=$U_tipo_documento ORDER BY cc.FechaRegistro DESC LIMIT 1");
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
                            "Lista"   => json_encode($datat),
                            "Estado"  => 0,
                            "Mensaje" => "El documento no exite o no es un documento comercial !!!",
                        ];
                    }
                } else {
                    $otroObjeto = [
                        "Id"      => 0,
                        "Lista"   => json_encode($datat),
                        "Estado"  => 0,
                        "Mensaje" => "El valor no tiene exactamente 9 dígitos. 4 ",
                    ];
                }
            } else {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => json_encode($datat),
                    "Estado"  => 0,
                    "Mensaje" => "El valor no es un número.",
                ];
            }

            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'find_log_traspasos':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data             = file_get_contents('php://input');
            $decodedObject    = json_decode($data, true);
            $U_tipo_documento = $decodedObject['U_tipo_documento'];
            $U_n_documento    = $decodedObject['U_n_documento'];
            $otroObjeto       = [];
            $datat            = [];
            if (is_numeric($U_n_documento)) {

                if (strlen($U_n_documento) === 9) {
                    $datat = [];
                    $datat = $conexionsap->query("SELECT cc.appVersion, cc.despachador, cc.CODI, cc.RAMA, cc.SUCURSAL, cc.TIPO, cc.OWNER, cc.CODEV, cc.MEMO, cc.person, cc.item_array ,
                    cc.existen_items_nocompletados
                    FROM log_app_traspasos cc
                            WHERE cc.U_n_documento='$U_n_documento' AND cc.U_tipo_documento=$U_tipo_documento ORDER BY cc.FechaRegistro DESC LIMIT 1");
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
                            "Lista"   => json_encode($datat),
                            "Estado"  => 0,
                            "Mensaje" => "El documento no exite o no es un documento comercial !!!",
                        ];
                    }
                } else {
                    $otroObjeto = [
                        "Id"      => 0,
                        "Lista"   => json_encode($datat),
                        "Estado"  => 0,
                        "Mensaje" => "El valor no tiene exactamente 9 dígitos. 5 ",
                    ];
                }
            } else {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => json_encode($datat),
                    "Estado"  => 0,
                    "Mensaje" => "El valor no es un número.",
                ];
            }

            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'find_log':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data             = file_get_contents('php://input');
            $decodedObject    = json_decode($data, true);
            $U_tipo_documento = $decodedObject['U_tipo_documento'];
            $U_n_documento    = $decodedObject['U_n_documento'];
            $otroObjeto       = [];
            $datat            = [];
            if (is_numeric($U_n_documento)) {

                if (strlen($U_n_documento) === 9) {
                    $datat = [];
                    $datat = $conexionsap->query("SELECT cc.appVersion, cc.despachador, cc.CODI, cc.RAMA, cc.SUCURSAL, cc.TIPO, cc.OWNER, cc.CODEV, cc.MEMO, cc.person, cc.item_array , cc.logArray FROM log_app_entrega cc
                            WHERE cc.U_n_documento='$U_n_documento' AND cc.U_tipo_documento=$U_tipo_documento ORDER BY cc.FechaRegistro DESC LIMIT 1");
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
                            "Lista"   => json_encode($datat),
                            "Estado"  => 0,
                            "Mensaje" => "El documento no exite o no es un documento comercial !!!",
                        ];
                    }
                } else {
                    $otroObjeto = [
                        "Id"      => 0,
                        "Lista"   => json_encode($datat),
                        "Estado"  => 0,
                        "Mensaje" => "El valor no tiene exactamente 9 dígitos. 5 ",
                    ];
                }
            } else {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => json_encode($datat),
                    "Estado"  => 0,
                    "Mensaje" => "El valor no es un número.",
                ];
            }

            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'find_vali':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data             = file_get_contents('php://input');
            $decodedObject    = json_decode($data, true);
            $U_tipo_documento = $decodedObject['U_tipo_documento'];
            $U_n_documento    = $decodedObject['U_n_documento'];
            $otroObjeto       = [];
            $datat            = [];
            if (is_numeric($U_n_documento)) {

                if (strlen($U_n_documento) === 9) {

                    $datat = [];
                    $datat = $conexionsap->hanacall("SP_ENT_MAIN$U_tipo_documento($U_n_documento)");
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
                            "Lista"   => ($datat),
                            "Estado"  => 1,
                            "Mensaje" => "",
                        ];
                    } else {
                        $otroObjeto = [
                            "Id"      => 0,
                            "Lista"   => ($datat),
                            "Estado"  => 0,
                            "Mensaje" => "El documento no exite o no es un documento comercial !!!",
                        ];
                    }
                } else {
                    $otroObjeto = [
                        "Id"      => 0,
                        "Lista"   => ($datat),
                        "Estado"  => 0,
                        "Mensaje" => "El valor no tiene exactamente 9 dígitos. 6 ",
                    ];
                }
            } else {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => ($datat),
                    "Estado"  => 0,
                    "Mensaje" => "El valor no es un número.",
                ];
            }

            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'find':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data             = file_get_contents('php://input');
            $decodedObject    = json_decode($data, true);
            $U_tipo_documento = $decodedObject['U_tipo_documento'];
            $U_n_documento    = $decodedObject['U_n_documento'];
            $otroObjeto       = [];
            $datat            = [];
            if (is_numeric($U_n_documento)) {

                if (strlen($U_n_documento) === 9) {

                    $datat = [];
                    $datat = $conexionsap->hanacall("SP_ENT_MAIN$U_tipo_documento($U_n_documento)");
                    if (count($datat) > 0) {
                        for ($ilc = 0; $ilc < count($datat); $ilc++) {
                            $codigo_items                                         = $datat[$ilc]['CODIGO_ITEM'];
                            $textoABuscar                                         = "SRV";
                            $datat[$ilc]['posi']                                  = $ilc + 1;
                            $datat[$ilc]['campo_select_activado']                 = 1;
                            $datat[$ilc]['U_fecha_actual']                        = '';
                            $datat[$ilc]['Id_tipoentrega']                        = '3';
                            $datat[$ilc]['Color_id_tipoentrega']                  = '';
                            $datat[$ilc]['Detalle_id_tipoentrega']                = '';
                            $datat[$ilc]['User_despachador']                      = '';
                            $datat[$ilc]['Nombre_despachador']                    = '';
                            $datat[$ilc]['IdTipoRegularizacion']                  = 0;
                            $datat[$ilc]['DetalleTipoRegularizacion']             = '';
                            $datat[$ilc]['NumeroDocumentoR']                      = '';
                            $datat[$ilc]['canEditComentario']                     = true;
                            $datat[$ilc]['U_fecha_registro_x_items_regularizado'] = '';
                            $datat[$ilc]['U_fecha_registro_x_items']              = '';
                            $datat[$ilc]['Comentario_id_tipoentrega']             = '';
                            $datat[$ilc]['Cantidad_id_tipoentrega']               = 0;
                            $datat[$ilc]['IdEstadoItems']                         = 0;
                            $datat[$ilc]['DetalleEstadoItems']                    = '';

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
                            "Lista"   => json_encode($datat),
                            "Estado"  => 0,
                            "Mensaje" => "El documento no exite o no es un documento comercial !!!",
                        ];
                    }
                } else {
                    $otroObjeto = [
                        "Id"      => 0,
                        "Lista"   => json_encode($datat),
                        "Estado"  => 0,
                        "Mensaje" => "El valor no tiene exactamente 9 dígitos. 6 ",
                    ];
                }
            } else {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => json_encode($datat),
                    "Estado"  => 0,
                    "Mensaje" => "El valor no es un número.",
                ];
            }

            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'lista_tipo_traspaso':
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $otroObjeto = [];
            $cant_body  = $conexionsap->query("CALL `lista_tipotraspasos`()");
            if ($cant_body > 0) {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => json_encode($cant_body),
                    "Estado"  => 1,
                    "Mensaje" => "",
                ];
            } else {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => [],
                    "Estado"  => 0,
                    "Mensaje" => "Error al devolver Lista",
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'lista_regularizacion':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $otroObjeto = [];
            $cant_body  = $conexionsap->query("CALL `lista_regularizacionn`()");
            if ($cant_body > 0) {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => ($cant_body),
                    "Estado"  => 1,
                    "Mensaje" => "",
                ];
            } else {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => [],
                    "Estado"  => 0,
                    "Mensaje" => "Error al devolver Lista",
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'lista_comentarios':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $otroObjeto = [];
            $cant_body  = $conexionsap->query("CALL `lista_comentarioss`()");
            if ($cant_body > 0) {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => ($cant_body),
                    "Estado"  => 1,
                    "Mensaje" => "",
                ];
            } else {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => [],
                    "Estado"  => 0,
                    "Mensaje" => "Error al devolver Lista",
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'lista_tipo_entrega':
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $otroObjeto = [];
            $cant_body  = $conexionsap->query("SELECT cc.Id, cc.Detalle, cc.Estado, cc.FechaRegistro, cc.Color FROM tipoentrega  cc WHERE cc.Estado=1  ORDER BY cc.FechaRegistro DESC");
            if ($cant_body > 0) {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => json_encode($cant_body),
                    "Estado"  => 1,
                    "Mensaje" => "",
                ];
            } else {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => [],
                    "Estado"  => 0,
                    "Mensaje" => "Error al devolver Lista",
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'registrar_informacion_n_nota_prueba':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data             = file_get_contents('php://input');
            $decodedObject    = json_decode($data, true);
            $U_n_documento    = $decodedObject['U_n_documento'];
            $U_tipo_documento = $decodedObject['U_tipo_documento'];
            $appVersion       = $decodedObject['appVersion'];
            $despachador      = $decodedObject['despachador'];
            $CODI             = $decodedObject['CODI'];
            $RAMA             = $decodedObject['RAMA'];
            $SUCURSAL         = $decodedObject['SUCURSAL'];
            $TIPO             = $decodedObject['TIPO'];
            $OWNER            = $decodedObject['OWNER'];
            $CODEV            = $decodedObject['CODEV'];
            $MEMO             = $decodedObject['MEMO'];
            $person           = $decodedObject['person'];
            $pase             = $decodedObject['pase'];
            $item_array       = json_encode($decodedObject['item_array']);
            $endData          = json_encode($decodedObject['endData']);

            $otroObjeto  = [];
            $vali_rsap   = 1;
            $user        = $person;
            $url         = $conexionsap->mainUrl . 'Login';
            $data_array  = ["UserName" => $user, "Password" => $pase, "CompanyDB" => $conexionsap->server_db];
            $res         = $conexionsap->callApis_sap('POST', $url, $data_array, null);
            $data_arrayy = json_decode($res, true);
            if (isset($data_arrayy['SessionId'])) {
                $idSesion = $data_arrayy['SessionId'];
                $url      = $conexionsap->mainUrl . 'DeliveryNotes';

                $ultimo_enddata = $conexionsap->query("SELECT cc.endData FROM log_app_entrega cc  WHERE cc.U_n_documento='$U_n_documento' ORDER BY cc.FechaRegistro DESC  LIMIT 1");
                if (count($ultimo_enddata) > 0) {
                    $array1 = json_decode($ultimo_enddata[0]['endData'], true);
                    if (count($array1) > 0) {
                        $arrayExcluir = $array1[0]['DocumentLines'];
                        if (count($decodedObject['endData']) > 0) {
                            $arrayPrincipal = $decodedObject['endData'][0]['DocumentLines'];
                            $arrayUnicos    = $conexionsap->getUniqueObjects($arrayPrincipal, $arrayExcluir);
                            if (count($arrayUnicos) > 0) {
                                $mergedJson                                   = json_encode($arrayUnicos);
                                $decodedObject['endData'][0]['DocumentLines'] = $arrayUnicos;
                                $mergedJson                                   = json_encode($decodedObject['endData'][0]);
                            } else {
                                $decodedObject['endData'] = $arrayUnicos;
                                $mergedJson               = json_encode($decodedObject['endData']);
                            }
                        }
                    }
                }

                if (count($decodedObject['endData']) > 0) {
                    $mergedJson                = json_encode($decodedObject['endData'][0]);
                    $log_insert_delivery_notas = $conexionsap->query("insert INTO log_ingreso_delivery_notas (Documento, JSON, FechaRegistro)    VALUES ('$U_n_documento','$mergedJson' ,'$fecha');");
                    if ($log_insert_delivery_notas > 0) {

                        $res = $conexionsap->callApis_sap('POST', $url, $decodedObject['endData'][0], $idSesion);
                        $res = json_decode($res, true);
                        if (isset($res['error'])) {
                            $vali_rsap  = 0;
                            $otroObjeto = [
                                "Id"      => 3,
                                "Lista"   => [],
                                "Estado"  => 0,
                                "Mensaje" => $res['error'],
                            ];

                            $EROC      = json_encode($otroObjeto);
                            $cant_body = $conexionsap->query("insert INTO log_app_entrega_error (U_n_documento, U_tipo_documento, appVersion,despachador,CODI,RAMA,SUCURSAL,TIPO,OWNER, CODEV,MEMO,person,item_array,FechaRegistro)  VALUES ('$U_n_documento', '$U_tipo_documento', '$appVersion','$despachador', '$CODI','$RAMA','$SUCURSAL','$TIPO','$OWNER','$CODEV','$MEMO','$person','$EROC','$fecha');");

                            if ($cant_body > 0) {
                                echo json_encode($otroObjeto);
                                return;
                            }
                        } else {
                        }
                    }
                } else {
                    $mergedJson = json_encode($decodedObject['endData']);
                }

                if ($vali_rsap == 1) {

                    $cant_body = $conexionsap->query("insert INTO log_app_entrega (U_n_documento, U_tipo_documento, appVersion,despachador,CODI,RAMA,SUCURSAL,TIPO,OWNER, CODEV,MEMO,person,item_array,FechaRegistro,endData,logArray, ult_endData)     VALUES ('$U_n_documento', '$U_tipo_documento', '$appVersion','$despachador', '$CODI','$RAMA','$SUCURSAL','$TIPO','$OWNER','$CODEV','$MEMO','$person','$item_array','$fecha','$endData','$item_array','$mergedJson');");
                    if ($cant_body > 0) {

                        $posi1l = 0;
                        $posi2l = 0;

                        $cant_bodyy = $conexionsap->query("SELECT * FROM log_proceso cc WHERE cc.U_n_documento='$U_n_documento'AND DATE(cc.FechaRegistro) = CURDATE()");
                        if (count($cant_bodyy) > 0) {
                            $cant_bodyyy = $conexionsap->query("SELECT * FROM log_proceso cc WHERE cc.U_n_documento='$U_n_documento'AND DATE(cc.FechaRegistro) = CURDATE() and   cc.Posicion=3 order by cc.FechaRegistro desc limit 1");
                            if (count($cant_bodyyy) > 0) {
                                $posi1l = 1;
                                $posi2l = 2;
                            } else {
                                $posi1l = 2;
                                $posi2l = 2;
                            }
                        } else {
                            $cant_bodyyyyy = $conexionsap->query("SELECT * FROM log_proceso cc WHERE cc.U_n_documento='$U_n_documento'  order by cc.FechaRegistro desc limit 1");
                            if (count($cant_bodyyyyy) > 0) {
                                $cant_bodyyyy = $conexionsap->query("SELECT * FROM log_proceso cc WHERE cc.U_n_documento='$U_n_documento'AND cc.Posicion=3 order by cc.FechaRegistro desc limit 1");
                                if (count($cant_bodyyyy) > 0) {
                                    $posi1l = 1;
                                    $posi2l = 1;
                                } else {
                                    $posi1l = 2;
                                    $posi2l = 1;
                                }
                            } else {
                                $posi1l = 1;
                                $posi2l = 1;
                            }
                        }

                        $cant_body_i = $conexionsap->query("insert INTO log_proceso (U_n_documento, U_tipo_documento,FechaRegistro,Usuario, Posicion, PosicionXFecha, despachador,RAMA,SUCURSAL,item_array, logArray)  VALUES ('$U_n_documento', '$U_tipo_documento', '$fecha','$person',$posi1l,$posi2l, '$despachador','$RAMA','$SUCURSAL','$item_array','$item_array');");
                        if ($cant_body_i > 0) {

                            $json      = $conexionsap->hanacall("SBO_ENTREGAS_TRACKING('$U_n_documento',$U_tipo_documento,'$date', '$hora','$person',$posi1l,$posi2l,'$RAMA', '$SUCURSAL','$item_array','$item_array','')");
                            $resultado = $json[0]['Resultado'];

                            if ($resultado == 1) {
                                $otroObjeto = [
                                    "Id"      => 0,
                                    "Lista"   => json_encode($cant_body),
                                    "Estado"  => 1,
                                    "Mensaje" => "Registrado Correctamente !!!",
                                ];
                            } else {
                                $otroObjeto = [
                                    "Id"      => 5,
                                    "Lista"   => [],
                                    "Estado"  => 0,
                                    "Mensaje" => "Nose pudo registrar el traking en SAP,  pero se realizo la transcacción correctamente !!!!",
                                ];
                            }
                        }
                    } else {
                        $otroObjeto = [
                            "Id"      => 1,
                            "Lista"   => [],
                            "Estado"  => 0,
                            "Mensaje" => "Error de Registro",
                        ];
                    }
                }
            } else {
                if (isset($data_arrayy['error'])) {
                    $otroObjeto = [
                        "Id"      => 2,
                        "Lista"   => [],
                        "Estado"  => 0,
                        "Mensaje" => $data_arrayy['error'],
                    ];
                }
            }

            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'registrar_informacion_n_nota':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data             = file_get_contents('php://input');
            $decodedObject    = json_decode($data, true);
            $U_n_documento    = $decodedObject['U_n_documento'];
            $U_tipo_documento = $decodedObject['U_tipo_documento'];
            $appVersion       = $decodedObject['appVersion'];
            $despachador      = $decodedObject['despachador'];
            $CODI             = $decodedObject['CODI'];
            $RAMA             = $decodedObject['RAMA'];
            $SUCURSAL         = $decodedObject['SUCURSAL'];
            $TIPO             = $decodedObject['TIPO'];
            $OWNER            = $decodedObject['OWNER'];
            $CODEV            = $decodedObject['CODEV'];
            $MEMO             = $decodedObject['MEMO'];
            $person           = $decodedObject['person'];
            $pase             = $decodedObject['pase'];
            $item_array       = json_encode($decodedObject['item_array']);
            $endData          = json_encode($decodedObject['endData']);
            $item_array_1     = $decodedObject['item_array'];

            if (count($item_array_1) > 0) {
                for ($ilcg = 0; $ilcg < count($item_array_1); $ilcg++) {
                    $Id_tipoentrega_   = $item_array_1[$ilcg]['Id_tipoentrega'];
                    $User_despachador_ = $item_array_1[$ilcg]['User_despachador'];
                    if ($Id_tipoentrega_ == 2 && $User_despachador_ == '') {
                        $item_array_1[$ilcg]["Id_tipoentrega"]         = "1";
                        $item_array_1[$ilcg]["User_despachador"]       = "";
                        $item_array_1[$ilcg]["Nombre_despachador"]     = "";
                        $item_array_1[$ilcg]["campo_select"]           = "0";
                        $item_array_1[$ilcg]["campo_select_color"]     = "";
                        $item_array_1[$ilcg]["Color_id_tipoentrega"]   = "#3dca2f";
                        $item_array_1[$ilcg]["Detalle_id_tipoentrega"] = "Entrega Total";
                    }
                }

                $item_array  = json_encode($item_array_1);
                $otroObjeto  = [];
                $vali_rsap   = 1;
                $user        = $person;
                $url         = $conexionsap->mainUrl . 'Login';
                $data_array  = ["UserName" => $user, "Password" => $pase, "CompanyDB" => $conexionsap->server_db];
                $res         = $conexionsap->callApis_sap('POST', $url, $data_array, null);
                $data_arrayy = json_decode($res, true);
                if (isset($data_arrayy['SessionId'])) {
                    $idSesion = $data_arrayy['SessionId'];
                    $url      = $conexionsap->mainUrl . 'DeliveryNotes';

                    $ultimo_enddata = $conexionsap->query("SELECT cc.endData FROM log_app_entrega cc  WHERE cc.U_n_documento='$U_n_documento' ORDER BY cc.FechaRegistro DESC  LIMIT 1");
                    if (count($ultimo_enddata) > 0) {
                        $array1 = json_decode($ultimo_enddata[0]['endData'], true);
                        if (count($array1) > 0) {
                            $arrayExcluir = $array1[0]['DocumentLines'];
                            if (count($decodedObject['endData']) > 0) {
                                $arrayPrincipal = $decodedObject['endData'][0]['DocumentLines'];
                                $arrayUnicos    = $conexionsap->getUniqueObjects($arrayPrincipal, $arrayExcluir);
                                if (count($arrayUnicos) > 0) {
                                    $mergedJson                                   = json_encode($arrayUnicos);
                                    $decodedObject['endData'][0]['DocumentLines'] = $arrayUnicos;
                                    $mergedJson                                   = json_encode($decodedObject['endData'][0]);
                                } else {
                                    $decodedObject['endData'] = $arrayUnicos;
                                    $mergedJson               = json_encode($decodedObject['endData']);
                                }
                            }
                        }
                    }

                    if (count($decodedObject['endData']) > 0) {
                        $mergedJson                = json_encode($decodedObject['endData'][0]);
                        $log_insert_delivery_notas = $conexionsap->query("insert INTO log_ingreso_delivery_notas (user,Documento, JSON, FechaRegistro)
                 VALUES ('$person','$U_n_documento','$mergedJson' ,'$fecha');");
                        if ($log_insert_delivery_notas > 0) {

                            $res = $conexionsap->callApis_sap('POST', $url, $decodedObject['endData'][0], $idSesion);
                            $res = json_decode($res, true);
                            if (isset($res['error'])) {
                                $vali_rsap  = 0;
                                $otroObjeto = [
                                    "Id"      => 3,
                                    "Lista"   => [],
                                    "Estado"  => 0,
                                    "Mensaje" => $res['error'],
                                ];

                                $EROC      = json_encode($otroObjeto);
                                $cant_body = $conexionsap->query("insert INTO log_app_entrega_error (U_n_documento, U_tipo_documento, appVersion,despachador,CODI,RAMA,SUCURSAL,TIPO,OWNER, CODEV,MEMO,person,item_array,FechaRegistro,RegistroSap)
                    VALUES ('$U_n_documento', '$U_tipo_documento', '$appVersion','$despachador', '$CODI','$RAMA','$SUCURSAL','$TIPO','$OWNER','$CODEV','$MEMO','$person','$EROC','$fecha',0);");

                                if ($cant_body > 0) {
                                    $vali_rsap = 1;
                                    if ($vali_rsap == 1) {

                                        $cant_body = $conexionsap->query("insert INTO log_app_entrega (U_n_documento, U_tipo_documento, appVersion,despachador,CODI,RAMA,SUCURSAL,TIPO,OWNER, CODEV,MEMO,person,item_array,FechaRegistro,endData,logArray, ult_endData)
                          VALUES ('$U_n_documento', '$U_tipo_documento', '$appVersion','$despachador', '$CODI','$RAMA','$SUCURSAL','$TIPO','$OWNER','$CODEV','$MEMO','$person','$item_array','$fecha','$endData','$item_array','$mergedJson');");
                                        if ($cant_body > 0) {

                                            $posi1l = 0;
                                            $posi2l = 0;

                                            $cant_bodyy = $conexionsap->query("SELECT * FROM log_proceso cc WHERE cc.U_n_documento='$U_n_documento'AND DATE(cc.FechaRegistro) = CURDATE()");
                                            if (count($cant_bodyy) > 0) {
                                                $cant_bodyyy = $conexionsap->query("SELECT * FROM log_proceso cc WHERE cc.U_n_documento='$U_n_documento'AND DATE(cc.FechaRegistro) = CURDATE() and   cc.Posicion=3 order by cc.FechaRegistro desc limit 1");
                                                if (count($cant_bodyyy) > 0) {
                                                    $posi1l = 1;
                                                    $posi2l = 2;
                                                } else {
                                                    $posi1l = 2;
                                                    $posi2l = 2;
                                                }
                                            } else {
                                                $cant_bodyyyyy = $conexionsap->query("SELECT * FROM log_proceso cc WHERE cc.U_n_documento='$U_n_documento'  order by cc.FechaRegistro desc limit 1");
                                                if (count($cant_bodyyyyy) > 0) {
                                                    $cant_bodyyyy = $conexionsap->query("SELECT * FROM log_proceso cc WHERE cc.U_n_documento='$U_n_documento'AND cc.Posicion=3 order by cc.FechaRegistro desc limit 1");
                                                    if (count($cant_bodyyyy) > 0) {
                                                        $posi1l = 1;
                                                        $posi2l = 1;
                                                    } else {
                                                        $posi1l = 2;
                                                        $posi2l = 1;
                                                    }
                                                } else {
                                                    $posi1l = 1;
                                                    $posi2l = 1;
                                                }
                                            }

                                            $cant_body_i = $conexionsap->query("insert INTO log_proceso (U_n_documento, U_tipo_documento,FechaRegistro,Usuario, Posicion, PosicionXFecha, despachador,RAMA,SUCURSAL,item_array, logArray)
                            VALUES ('$U_n_documento', '$U_tipo_documento', '$fecha','$person',$posi1l,$posi2l, '$despachador','$RAMA','$SUCURSAL','$item_array','$item_array');");
                                            if ($cant_body_i > 0) {

                                                $json      = $conexionsap->hanacall("SBO_ENTREGAS_TRACKING('$U_n_documento',$U_tipo_documento,'$date', '$hora','$person',$posi1l,$posi2l,'$RAMA', '$SUCURSAL','$item_array','$item_array','')");
                                                $resultado = $json[0]['Resultado'];

                                                if ($resultado == 1) {
                                                    $otroObjeto = [
                                                        "Id"      => 0,
                                                        "Lista"   => json_encode($cant_body),
                                                        "Estado"  => 1,
                                                        "Mensaje" => "Registrado Correctamente !!!",
                                                    ];
                                                } else {
                                                    $otroObjeto = [
                                                        "Id"      => 5,
                                                        "Lista"   => [],
                                                        "Estado"  => 0,
                                                        "Mensaje" => "Nose pudo registrar el traking en SAP,  pero se realizo la transcacción correctamente !!!!",
                                                    ];
                                                }
                                            }
                                        } else {
                                            $otroObjeto = [
                                                "Id"      => 1,
                                                "Lista"   => [],
                                                "Estado"  => 0,
                                                "Mensaje" => "Error de Registro",
                                            ];
                                        }
                                    }
                                }
                            } else {
                                $EROCC     = json_encode($res);
                                $cant_body = $conexionsap->query("insert INTO log_app_entrega_error (U_n_documento, U_tipo_documento, appVersion,despachador,CODI,RAMA,SUCURSAL,TIPO,OWNER, CODEV,MEMO,person,item_array,FechaRegistro,RegistroSap)
                    VALUES ('$U_n_documento', '$U_tipo_documento', '$appVersion','$despachador', '$CODI','$RAMA','$SUCURSAL','$TIPO','$OWNER','$CODEV','$MEMO','$person','$EROCC','$fecha',1);");
                                $vali_rsap = 1;
                                if ($vali_rsap == 1) {

                                    $cant_body = $conexionsap->query("insert INTO log_app_entrega (U_n_documento, U_tipo_documento, appVersion,despachador,CODI,RAMA,SUCURSAL,TIPO,OWNER, CODEV,MEMO,person,item_array,FechaRegistro,endData,logArray, ult_endData)
                      VALUES ('$U_n_documento', '$U_tipo_documento', '$appVersion','$despachador', '$CODI','$RAMA','$SUCURSAL','$TIPO','$OWNER','$CODEV','$MEMO','$person','$item_array','$fecha','$endData','$item_array','$mergedJson');");
                                    if ($cant_body > 0) {

                                        $posi1l = 0;
                                        $posi2l = 0;

                                        $cant_bodyy = $conexionsap->query("SELECT * FROM log_proceso cc WHERE cc.U_n_documento='$U_n_documento'AND DATE(cc.FechaRegistro) = CURDATE()");
                                        if (count($cant_bodyy) > 0) {
                                            $cant_bodyyy = $conexionsap->query("SELECT * FROM log_proceso cc WHERE cc.U_n_documento='$U_n_documento'AND DATE(cc.FechaRegistro) = CURDATE() and   cc.Posicion=3 order by cc.FechaRegistro desc limit 1");
                                            if (count($cant_bodyyy) > 0) {
                                                $posi1l = 1;
                                                $posi2l = 2;
                                            } else {
                                                $posi1l = 2;
                                                $posi2l = 2;
                                            }
                                        } else {
                                            $cant_bodyyyyy = $conexionsap->query("SELECT * FROM log_proceso cc WHERE cc.U_n_documento='$U_n_documento'  order by cc.FechaRegistro desc limit 1");
                                            if (count($cant_bodyyyyy) > 0) {
                                                $cant_bodyyyy = $conexionsap->query("SELECT * FROM log_proceso cc WHERE cc.U_n_documento='$U_n_documento'AND cc.Posicion=3 order by cc.FechaRegistro desc limit 1");
                                                if (count($cant_bodyyyy) > 0) {
                                                    $posi1l = 1;
                                                    $posi2l = 1;
                                                } else {
                                                    $posi1l = 2;
                                                    $posi2l = 1;
                                                }
                                            } else {
                                                $posi1l = 1;
                                                $posi2l = 1;
                                            }
                                        }

                                        $cant_body_i = $conexionsap->query("insert INTO log_proceso (U_n_documento, U_tipo_documento,FechaRegistro,Usuario, Posicion, PosicionXFecha, despachador,RAMA,SUCURSAL,item_array, logArray)
                        VALUES ('$U_n_documento', '$U_tipo_documento', '$fecha','$person',$posi1l,$posi2l, '$despachador','$RAMA','$SUCURSAL','$item_array','$item_array');");
                                        if ($cant_body_i > 0) {

                                            $json      = $conexionsap->hanacall("SBO_ENTREGAS_TRACKING('$U_n_documento',$U_tipo_documento,'$date', '$hora','$person',$posi1l,$posi2l,'$RAMA', '$SUCURSAL','$item_array','$item_array','')");
                                            $resultado = $json[0]['Resultado'];

                                            if ($resultado == 1) {
                                                $otroObjeto = [
                                                    "Id"      => 0,
                                                    "Lista"   => json_encode($cant_body),
                                                    "Estado"  => 1,
                                                    "Mensaje" => "Registrado Correctamente !!!",
                                                ];
                                            } else {
                                                $otroObjeto = [
                                                    "Id"      => 5,
                                                    "Lista"   => [],
                                                    "Estado"  => 0,
                                                    "Mensaje" => "Nose pudo registrar el traking en SAP,  pero se realizo la transcacción correctamente !!!!",
                                                ];
                                            }
                                        }
                                    } else {
                                        $otroObjeto = [
                                            "Id"      => 1,
                                            "Lista"   => [],
                                            "Estado"  => 0,
                                            "Mensaje" => "Error de Registro",
                                        ];
                                    }
                                }
                            }
                        }
                    } else {
                        $mergedJson = json_encode($decodedObject['endData']);
                        if ($vali_rsap == 1) {

                            $cant_body = $conexionsap->query("insert INTO log_app_entrega (U_n_documento, U_tipo_documento, appVersion,despachador,CODI,RAMA,SUCURSAL,TIPO,OWNER, CODEV,MEMO,person,item_array,FechaRegistro,endData,logArray, ult_endData)
                    VALUES ('$U_n_documento', '$U_tipo_documento', '$appVersion','$despachador', '$CODI','$RAMA','$SUCURSAL','$TIPO','$OWNER','$CODEV','$MEMO','$person','$item_array','$fecha','$endData','$item_array','$mergedJson');");
                            if ($cant_body > 0) {

                                $posi1l = 0;
                                $posi2l = 0;

                                $cant_bodyy = $conexionsap->query("SELECT * FROM log_proceso cc WHERE cc.U_n_documento='$U_n_documento'AND DATE(cc.FechaRegistro) = CURDATE()");
                                if (count($cant_bodyy) > 0) {
                                    $cant_bodyyy = $conexionsap->query("SELECT * FROM log_proceso cc WHERE cc.U_n_documento='$U_n_documento'AND DATE(cc.FechaRegistro) = CURDATE() and   cc.Posicion=3 order by cc.FechaRegistro desc limit 1");
                                    if (count($cant_bodyyy) > 0) {
                                        $posi1l = 1;
                                        $posi2l = 2;
                                    } else {
                                        $posi1l = 2;
                                        $posi2l = 2;
                                    }
                                } else {
                                    $cant_bodyyyyy = $conexionsap->query("SELECT * FROM log_proceso cc WHERE cc.U_n_documento='$U_n_documento'  order by cc.FechaRegistro desc limit 1");
                                    if (count($cant_bodyyyyy) > 0) {
                                        $cant_bodyyyy = $conexionsap->query("SELECT * FROM log_proceso cc WHERE cc.U_n_documento='$U_n_documento'AND cc.Posicion=3 order by cc.FechaRegistro desc limit 1");
                                        if (count($cant_bodyyyy) > 0) {
                                            $posi1l = 1;
                                            $posi2l = 1;
                                        } else {
                                            $posi1l = 2;
                                            $posi2l = 1;
                                        }
                                    } else {
                                        $posi1l = 1;
                                        $posi2l = 1;
                                    }
                                }

                                $cant_body_i = $conexionsap->query("insert INTO log_proceso (U_n_documento, U_tipo_documento,FechaRegistro,Usuario, Posicion, PosicionXFecha, despachador,RAMA,SUCURSAL,item_array, logArray)
                      VALUES ('$U_n_documento', '$U_tipo_documento', '$fecha','$person',$posi1l,$posi2l, '$despachador','$RAMA','$SUCURSAL','$item_array','$item_array');");
                                if ($cant_body_i > 0) {

                                    $json      = $conexionsap->hanacall("SBO_ENTREGAS_TRACKING('$U_n_documento',$U_tipo_documento,'$date', '$hora','$person',$posi1l,$posi2l,'$RAMA', '$SUCURSAL','$item_array','$item_array','')");
                                    $resultado = $json[0]['Resultado'];

                                    if ($resultado == 1) {
                                        $otroObjeto = [
                                            "Id"      => 0,
                                            "Lista"   => json_encode($cant_body),
                                            "Estado"  => 1,
                                            "Mensaje" => "Registrado Correctamente !!!",
                                        ];
                                    } else {
                                        $otroObjeto = [
                                            "Id"      => 5,
                                            "Lista"   => [],
                                            "Estado"  => 0,
                                            "Mensaje" => "Nose pudo registrar el traking en SAP,  pero se realizo la transcacción correctamente !!!!",
                                        ];
                                    }
                                }
                            } else {
                                $otroObjeto = [
                                    "Id"      => 1,
                                    "Lista"   => [],
                                    "Estado"  => 0,
                                    "Mensaje" => "Error de Registro",
                                ];
                            }
                        }
                    }
                } else {
                    if (isset($data_arrayy['error'])) {
                        $otroObjeto = [
                            "Id"      => 2,
                            "Lista"   => [],
                            "Estado"  => 0,
                            "Mensaje" => $data_arrayy['error'],
                        ];
                    }
                }
            }

            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'registrar_informacion_n_nota_traspaso':

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(["Estado" => 0, "Mensaje" => "Método no permitido"]);
            return;
        }

        try {

            $data    = file_get_contents('php://input');
            $decoded = json_decode($data, true);

            if (! $decoded) {
                throw new Exception("JSON inválido");
            }

            // =========================
            // Validación de campos obligatorios
            // =========================
            $required = [
                'U_n_documento',
                'U_tipo_documento',
                'appVersion',
                'despachador',
                'CODI',
                'RAMA',
                'SUCURSAL',
                'TIPO',
                'OWNER',
                'CODEV',
                'MEMO',
                'person',
                'pase',
                'item_array',
                'existen_items_nocompletados',
                'tipo_usuario',
            ];

            foreach ($required as $campo) {
                if (! isset($decoded[$campo])) {
                    throw new Exception("Falta el campo: $campo");
                }
            }

            $fecha = date('Y-m-d H:i:s');

            extract($decoded);

            $itemsModificados = $decoded['itemsModificados'] ?? [];
            $item_array_1     = $decoded['item_array'];

            $appVersion  = $decoded['appVersion'];
            $appVersion1 = $decoded['appVersion1'];
            if (! is_array($item_array_1) || count($item_array_1) === 0) {
                throw new Exception("No existen ítems para registrar");
            }

            // =========================
            // Marcar fecha por ítems modificados
            // =========================
            $fecha_primero = $item_array_1[0]['FECHA'] ?? null;
            if (! empty($itemsModificados)) {
                foreach ($item_array_1 as &$item) {
                    if (
                        isset($item['CODIGO_ITEM']) &&
                        in_array($item['CODIGO_ITEM'], $itemsModificados)
                    ) {
                        $item['U_fecha_registro_x_items'] = $fecha;
                    }
                }
                unset($item);
            }

            $item_array_json          = json_encode($item_array_1, JSON_UNESCAPED_UNICODE);
            $item_array_json_version  = json_encode($appVersion, JSON_UNESCAPED_UNICODE);
            $item_array_json_version1 = json_encode($appVersion1, JSON_UNESCAPED_UNICODE);

            $url   = $conexionsap->mainUrl . 'Login';
            $login = $conexionsap->callApis_sap(
                'POST',
                $url,
                [
                    "UserName"  => $person,
                    "Password"  => $pase,
                    "CompanyDB" => $conexionsap->server_db,
                ],
                null
            );

            $loginResp = json_decode($login, true);

            if (! isset($loginResp['SessionId'])) {
                throw new Exception($loginResp['error'] ?? 'Error login SAP');
            }

            // =========================
            // VALIDAR EXISTENCIA DOCUMENTO
            // =========================
            $fecha_inicio = '';
            $id_lugar     = 2;
            $existe       = $conexionsap->query("CALL EXISTE_DOCUMENTO_TRASPASO('$U_n_documento')");
            if (count($existe) === 0) {
                $fecha_inicio = $fecha;
                $id_lugar     = 1;
            }

            // =========================
            // INSERT LOG
            // =========================

            $insert = "
       insert into log_app_traspasos
        (U_n_documento,U_tipo_documento,appVersion,aux_array,despachador,CODI,RAMA,SUCURSAL,TIPO,OWNER,CODEV,MEMO,person,item_array,FechaRegistro,Fecha_Inicio,existen_items_nocompletados,Tipo_usuario,Id_lugar,FechaRegistroSAP)
        VALUES
        ('$U_n_documento','$U_tipo_documento','$item_array_json_version','$item_array_json_version1','$despachador','$CODI','$RAMA','$SUCURSAL','$TIPO','$OWNER','$CODEV','$MEMO','$person','$item_array_json','$fecha','$fecha_inicio',$existen_items_nocompletados, '$tipo_usuario',$id_lugar,'$fecha_primero')
    ";

            $idInsert = $conexionsap->query($insert);

            if (! $idInsert) {
                throw new Exception("No se pudo registrar el log");
            } else {
                $sqlSP = "CALL sp_insertar_anio_gestion_rt('$U_n_documento', @resultado)";
                $conexionsap->query($sqlSP);
                $res = $conexionsap->query("SELECT @resultado AS id_generado");
                if ($res instanceof mysqli_result) {
                    $row         = $res->fetch_assoc();
                    $id_generado = (int) $row['id_generado'];

                }
            }

            if ($existen_items_nocompletados === 0 && in_array($tipo_usuario, [2, 6])) {
                $id_lugar = 3;
                $conexionsap->query("update log_app_traspasos SET Fecha_Fin='$fecha' , Id_lugar=$id_lugar WHERE Id=$idInsert");
            }

            echo json_encode([
                "Estado"  => 1,
                "Mensaje" => "Registrado correctamente",
                "Id"      => $idInsert,
            ]);
            return;
        } catch (Exception $e) {

            echo json_encode([
                "Estado"  => 0,
                "Mensaje" => $e->getMessage(),
            ]);
            return;
        }

        break;

    case 'registrar_informacion_n_nota_porteria':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data             = file_get_contents('php://input');
            $decodedObject    = json_decode($data, true);
            $U_n_documento    = $decodedObject['U_n_documento'];
            $U_tipo_documento = $decodedObject['U_tipo_documento'];
            $appVersion       = $decodedObject['appVersion'];
            $despachador      = $decodedObject['despachador'];
            $CODI             = $decodedObject['CODI'];
            $RAMA             = $decodedObject['RAMA'];
            $SUCURSAL         = $decodedObject['SUCURSAL'];
            $TIPO             = $decodedObject['TIPO'];
            $OWNER            = $decodedObject['OWNER'];
            $CODEV            = $decodedObject['CODEV'];
            $MEMO             = $decodedObject['MEMO'];
            $person           = $decodedObject['person'];
            $pase             = $decodedObject['pase'];
            $ticketbalanza    = $decodedObject['ticketbalanza'];
            $chofer           = $decodedObject['chofer'];
            $peso_balanza     = $decodedObject['peso_balanza'];
            $forma_de_envio   = $decodedObject['forma_de_envio'];
            $almacen          = $decodedObject['almacen'];
            $parcial          = $decodedObject['parcial'];
            $endData          = json_encode($decodedObject['endData']);
            $cantidad_total   = $decodedObject['cantidad_total'];
            $item_array       = json_encode($decodedObject['item_array']);

            $otroObjeto  = [];
            $vali_rsap   = 1;
            $vali_port   = 1;
            $user        = $person;
            $url         = $conexionsap->mainUrl . 'Login';
            $data_array  = ["UserName" => $user, "Password" => $pase, "CompanyDB" => $conexionsap->server_db];
            $res         = $conexionsap->callApis_sap('POST', $url, $data_array, null);
            $data_arrayy = json_decode($res, true);
            if (isset($data_arrayy['SessionId'])) {

                $idSesion = $data_arrayy['SessionId'];
                $url      = $conexionsap->mainUrl . 'DeliveryNotes';

                $ultimo_enddata = $conexionsap->query("SELECT cc.endData FROM log_app_entrega_porteria cc  WHERE cc.U_n_documento='$U_n_documento' ORDER BY cc.FechaRegistro DESC  LIMIT 1");
                if (count($ultimo_enddata) > 0) {
                    $array1 = json_decode($ultimo_enddata[0]['endData'], true);
                    if (count($array1) > 0) {
                        $arrayExcluir = $array1[0]['DocumentLines'];
                        if (count($decodedObject['endData']) > 0) {
                            $arrayPrincipal = $decodedObject['endData'][0]['DocumentLines'];
                            $arrayUnicos    = $conexionsap->getUniqueObjects($arrayPrincipal, $arrayExcluir);
                            if (count($arrayUnicos) > 0) {
                                $mergedJson                                   = json_encode($arrayUnicos);
                                $decodedObject['endData'][0]['DocumentLines'] = $arrayUnicos;
                                $mergedJson                                   = json_encode($decodedObject['endData'][0]);
                            } else {
                                $decodedObject['endData'] = $arrayUnicos;
                                $mergedJson               = json_encode($decodedObject['endData']);
                            }
                        }
                    }
                }

                if (count($decodedObject['endData']) > 0) {
                    $mergedJson = json_encode($decodedObject['endData'][0]);
                    $res        = $conexionsap->callApis_sap('POST', $url, $decodedObject['endData'][0], $idSesion);
                    $res        = json_decode($res, true);
                    if (isset($res['error'])) {
                        $vali_rsap  = 0;
                        $otroObjeto = [
                            "Id"      => 3,
                            "Lista"   => [],
                            "Estado"  => 0,
                            "Mensaje" => $res['error'],
                        ];
                        echo json_encode($otroObjeto);
                        return;
                    }
                } else {
                    $mergedJson = json_encode($decodedObject['endData']);
                }

                if ($vali_rsap == 1) {

                    $preNew = $conexionsap->hanaquery("select count(*) as MAX from \"@PORTERIA\" where \"U_usuario_sucursal\"=''$SUCURSAL''");
                    $max    = intval($preNew[0]['MAX']);
                    $max++;
                    $codi    = $CODI;
                    $branch  = $RAMA;
                    $newCode = "{$branch}-{$codi}-{$max}";

                    $FTY1             = 'SELECT SUM(T1."OpenQty") as "pendiente" FROM OINV T0   INNER JOIN INV1 T1 ON T0."DocEntry" = T1."DocEntry"  WHERE T0."DocNum" = \'\'' . $U_n_documento . '\'\' AND T0."DocSubType" <>  \'\'DN\'\'';
                    $res1             = $conexionsap->hanaquery($FTY1);
                    $pendientes_total = $res1[0]['pendiente'];

                    $newPorteria = [
                        "Code"               => $newCode,
                        "Name"               => $newCode,
                        "U_tipo_documento"   => $U_tipo_documento,
                        "U_n_documento"      => $U_n_documento,
                        "U_n_entrega"        => 0,
                        "U_n_ticket_balanza" => $ticketbalanza,
                        "U_kg_balanza"       => $peso_balanza,
                        "U_chofer"           => $chofer,
                        "U_despachador"      => '',
                        "U_cod_despachador"  => '',
                        "U_fecha_salida"     => $date,
                        "U_hora_salida"      => $horaa,
                        "U_usuario_creacion" => $despachador,
                        "U_cod_usuario"      => $CODI,
                        "U_usuario_sucursal" => $SUCURSAL,
                        "U_fecha_creacion"   => $date,
                        "U_hora_creacion"    => $hora,
                        "U_ent_sal"          => "1",
                        "U_forma_envio"      => $forma_de_envio,
                        "U_baja"             => 1,
                        "U_observacion"      => '',
                        "U_almacen"          => $almacen,
                        "U_doc_reemplazo"    => "0",
                        "U_anulado_por"      => 0,
                        "U_pendiente"        => $pendientes_total,
                        "U_Parcial"          => $parcial,
                        "U_ip"               => '',
                        "U_device"           => 'Movil',
                    ];

                    $url  = $conexionsap->mainUrl . 'U_PORTERIA';
                    $res1 = $conexionsap->callApis_sap('POST', $url, $newPorteria, $idSesion);
                    $res1 = json_decode($res1, true);
                    if (isset($res1['error'])) {
                        $vali_port  = 0;
                        $otroObjeto = [
                            "Id"      => 3,
                            "Lista"   => [],
                            "Estado"  => 0,
                            "Mensaje" => $res1['error'],
                        ];
                        echo json_encode($otroObjeto);
                        return;
                    }

                    if ($vali_port == 1) {
                        $JSON_PORTERIA_SAPP = json_encode($newPorteria);
                        $cant_body          = $conexionsap->query("insert INTO log_app_entrega_porteria (U_n_documento, U_tipo_documento, appVersion,despachador,CODI,RAMA,SUCURSAL,TIPO,OWNER, CODEV,MEMO,person,FechaRegistro,endData, ult_endData,JSON_PORTERIA_SAP, cantidad_total,logArray) VALUES ('$U_n_documento', '$U_tipo_documento', '$appVersion','$despachador', '$CODI','$RAMA','$SUCURSAL','$TIPO','$OWNER','$CODEV','$MEMO','$person','$fecha','$endData','$mergedJson','$JSON_PORTERIA_SAPP', $cantidad_total, '$item_array');");
                        if ($cant_body > 0) {

                            $cant_body111 = $conexionsap->query("SELECT cc.Id, cc.Documento, cc.FechaRegistro, cc.Usuario, cc.Permitir FROM activar_salida_logistico cc WHERE cc.Documento='$U_n_documento'  ORDER BY cc.FechaRegistro DESC ");
                            if (count($cant_body111) > 0) {
                                $cant_body_117 = $conexionsap->query("update activar_salida_logistico cc set  cc.FechaModificacion='$fecha', cc.Permitir=0 where cc.Documento='$U_n_documento' ");
                                if (($cant_body_117) > 0) {

                                    $datat_act = [];
                                    $datat_act = $conexionsap->query("SELECT cc.appVersion, cc.despachador, cc.CODI, cc.RAMA, cc.SUCURSAL, cc.TIPO, cc.OWNER, cc.CODEV, cc.MEMO, cc.person, cc.item_array , cc.logArray FROM log_app_entrega cc  WHERE cc.U_n_documento='$U_n_documento' AND cc.U_tipo_documento=$U_tipo_documento ORDER BY cc.FechaRegistro DESC LIMIT 1");
                                    if (count($datat_act) > 0) {
                                        $lista_log_array = [];
                                        for ($ilc = 0; $ilc < count($datat_act); $ilc++) {
                                            $lista_log_array     = $datat_act[$ilc]['logArray'];
                                            $lista_log_array_aux = json_decode($lista_log_array, true);
                                            for ($ilcc = 0; $ilcc < count($lista_log_array_aux); $ilcc++) {
                                                $Id_tipoentrega_v = $lista_log_array_aux[$ilcc]["Id_tipoentrega"];
                                                if ($Id_tipoentrega_v == 2) {
                                                    $lista_log_array_aux[$ilcc]["Id_tipoentrega"]         = "1";
                                                    $lista_log_array_aux[$ilcc]["User_despachador"]       = "";
                                                    $lista_log_array_aux[$ilcc]["Nombre_despachador"]     = "";
                                                    $lista_log_array_aux[$ilcc]["campo_select"]           = "0";
                                                    $lista_log_array_aux[$ilcc]["campo_select_color"]     = "";
                                                    $lista_log_array_aux[$ilcc]["Color_id_tipoentrega"]   = "#3dca2f";
                                                    $lista_log_array_aux[$ilcc]["Detalle_id_tipoentrega"] = "Entrega Total";
                                                }
                                            }
                                            $item_array_act = json_encode($lista_log_array_aux);
                                            $cant_body_act  = $conexionsap->query("update log_app_entrega cc set  cc.FechaModificacion='$fecha',  cc.logArray='$item_array_act' where   cc.U_n_documento='$U_n_documento' AND cc.U_tipo_documento=$U_tipo_documento ORDER BY cc.FechaRegistro DESC LIMIT 1 ");
                                            if ($cant_body_act > 0) {

                                                $result = $conexionsap->enviar_correo_prueba($U_n_documento, 1);
                                                $result = json_encode($result);
                                                if ($result == 1) {

                                                    $cant_body_i = $conexionsap->query("insert INTO log_proceso (U_n_documento, U_tipo_documento,FechaRegistro,Usuario, Posicion, PosicionXFecha, despachador,RAMA,SUCURSAL,item_array, logArray,EnvioCorreo)  VALUES ('$U_n_documento', '$U_tipo_documento', '$fecha','$person',3,3, '$despachador','$RAMA','$SUCURSAL','$JSON_PORTERIA_SAPP','$JSON_PORTERIA_SAPP','$result');");
                                                    if ($cant_body_i > 0) {

                                                        $json      = $conexionsap->hanacall("SBO_ENTREGAS_TRACKING('$U_n_documento',$U_tipo_documento,'$date', '$hora','$person',3,3,'$RAMA', '$SUCURSAL','$JSON_PORTERIA_SAPP','$JSON_PORTERIA_SAPP','$result')");
                                                        $resultado = $json[0]['Resultado'];

                                                        if ($resultado == 1) {
                                                            $otroObjeto = [
                                                                "Id"      => 0,
                                                                "Lista"   => json_encode($cant_body),
                                                                "Estado"  => 1,
                                                                "Mensaje" => "Registrado Correctamente !!!",
                                                            ];
                                                        } else {
                                                            $otroObjeto = [
                                                                "Id"      => 5,
                                                                "Lista"   => [],
                                                                "Estado"  => 0,
                                                                "Mensaje" => "Nose pudo registrar el traking en SAP,  pero se realizo la transcacción correctamente !!!!",
                                                            ];
                                                        }
                                                    }
                                                } else {
                                                    $otroObjeto = [
                                                        "Id"      => 4,
                                                        "Lista"   => [],
                                                        "Estado"  => 0,
                                                        "Mensaje" => "Nose pudo enviar el correo pero se realizo la transcacción correctamente !!!!",
                                                    ];
                                                }
                                            }
                                        }
                                    }
                                }
                            } else {

                                $result = $conexionsap->enviar_correo_prueba($U_n_documento, 1);
                                $result = json_encode($result);
                                if ($result == 1) {

                                    $cant_body_i = $conexionsap->query("insert INTO log_proceso (U_n_documento, U_tipo_documento,FechaRegistro,Usuario, Posicion, PosicionXFecha, despachador,RAMA,SUCURSAL,item_array, logArray,EnvioCorreo)  VALUES ('$U_n_documento', '$U_tipo_documento', '$fecha','$person',3,3, '$despachador','$RAMA','$SUCURSAL','$JSON_PORTERIA_SAPP','$JSON_PORTERIA_SAPP','$result');");
                                    if ($cant_body_i > 0) {

                                        $json      = $conexionsap->hanacall("SBO_ENTREGAS_TRACKING('$U_n_documento',$U_tipo_documento,'$date', '$hora','$person',3,3,'$RAMA', '$SUCURSAL','$JSON_PORTERIA_SAPP','$JSON_PORTERIA_SAPP','$result')");
                                        $resultado = $json[0]['Resultado'];

                                        if ($resultado == 1) {
                                            $otroObjeto = [
                                                "Id"      => 0,
                                                "Lista"   => json_encode($cant_body),
                                                "Estado"  => 1,
                                                "Mensaje" => "Registrado Correctamente !!!",
                                            ];
                                        } else {
                                            $otroObjeto = [
                                                "Id"      => 5,
                                                "Lista"   => [],
                                                "Estado"  => 0,
                                                "Mensaje" => "Nose pudo registrar el traking en SAP,  pero se realizo la transcacción correctamente !!!!",
                                            ];
                                        }
                                    }
                                } else {
                                    $otroObjeto = [
                                        "Id"      => 4,
                                        "Lista"   => [],
                                        "Estado"  => 0,
                                        "Mensaje" => "Nose pudo enviar el correo pero se realizo la transcacción correctamente !!!!",
                                    ];
                                }
                            }
                        } else {
                            $otroObjeto = [
                                "Id"      => 1,
                                "Lista"   => [],
                                "Estado"  => 0,
                                "Mensaje" => "Error de Registro",
                            ];
                        }
                    }
                }
            } else {
                if (isset($data_arrayy['error'])) {
                    $otroObjeto = [
                        "Id"      => 2,
                        "Lista"   => [],
                        "Estado"  => 0,
                        "Mensaje" => $data_arrayy['error'],
                    ];
                }
            }

            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'getTicket':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data          = file_get_contents('php://input');
            $decodedObject = json_decode($data, true);
            $nro           = $decodedObject['nro'];
            $sucursal      = $decodedObject['sucursal'];
            $otroObjeto    = [];
            $datat         = [];
            $datat         = $conexionsap->hanaquery('select "U_NombreChofer","U_Neto" from "@BALANZA" where "U_NroTicket"=\'\'' . $nro . '\'\' and "U_Sucursal"=\'\'' . $sucursal . '\'\'');
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
                    "Lista"   => json_encode($datat),
                    "Estado"  => 0,
                    "Mensaje" => "Número inexistente !!!",
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'balanza':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data          = file_get_contents('php://input');
            $decodedObject = json_decode($data, true);
            $nro           = $decodedObject['nro'];
            $sucursal      = $decodedObject['sucursal'];
            $otroObjeto    = [];
            $datat         = [];
            $datat         = $conexionsap->hanaquery('select * from "@BALANZA"  limit 10');
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
                    "Lista"   => json_encode($datat),
                    "Estado"  => 0,
                    "Mensaje" => "El documento información !!!",
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'Insertar_documento_logistico':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data          = file_get_contents('php://input');
            $decodedObject = json_decode($data, true);
            $Documento     = $decodedObject['Documento'];
            $Usuario       = $decodedObject['Usuario'];
            $Permitir      = $decodedObject['Permitir'];
            $otroObjeto    = [];
            $cant_body1    = $conexionsap->query("SELECT cc.Id, cc.Documento, cc.FechaRegistro, cc.Usuario, cc.Permitir
        FROM activar_salida_logistico cc WHERE cc.Documento='$Documento'
        ORDER BY cc.FechaRegistro DESC ");
            if (count($cant_body1) > 0) {
                $cant_body = $conexionsap->query("update activar_salida_logistico cc set  cc.FechaModificacion='$fecha', cc.Usuario='$Usuario', cc.Permitir='$Permitir' where cc.Documento='$Documento' ");
                if (($cant_body) > 0) {
                    $otroObjeto = [
                        "Id"      => 0,
                        "Lista"   => json_encode($cant_body),
                        "Estado"  => 1,
                        "Mensaje" => "Actualizado correctamente ",
                    ];
                } else {
                    $otroObjeto = [
                        "Id"      => 0,
                        "Lista"   => [],
                        "Estado"  => 0,
                        "Mensaje" => "Nose pudo actualizar !!!",
                    ];
                }
            } else {
                $cant_body = $conexionsap->query("insert INTO activar_salida_logistico (Documento,FechaRegistro, Usuario, Permitir) VALUES('$Documento', '$fecha', '$Usuario', '$Permitir') ");
                if (($cant_body) > 0) {
                    $otroObjeto = [
                        "Id"      => 0,
                        "Lista"   => json_encode($cant_body),
                        "Estado"  => 1,
                        "Mensaje" => "Registrado Correctamente",
                    ];
                } else {
                    $otroObjeto = [
                        "Id"      => 0,
                        "Lista"   => [],
                        "Estado"  => 0,
                        "Mensaje" => "Nose pudo registrar !!!",
                    ];
                }
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'documento_activo_por_logistica_existe':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data          = file_get_contents('php://input');
            $decodedObject = json_decode($data, true);
            $U_n_documento = $decodedObject['U_n_documento'];
            $otroObjeto    = [];
            $cant_body     = $conexionsap->query("SELECT cc.Id, cc.Documento, cc.FechaRegistro, cc.Usuario, cc.Permitir,
         if(cc.Permitir=1, 'Permitido', 'Negado')  as estado_permiso,dd.Detalle
                         FROM activar_salida_logistico cc
                            INNER JOIN color_salida dd ON dd.Permitido= cc.Permitir AND dd.Estado=1
                         WHERE cc.Documento='$U_n_documento'
                         ORDER BY cc.FechaRegistro DESC ");
            if (count($cant_body) > 0) {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => json_encode($cant_body),
                    "Estado"  => 1,
                    "Mensaje" => "",
                ];
            } else {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => [],
                    "Estado"  => 0,
                    "Mensaje" => "Sin ingreso a Almacen !!",
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'documento_activo_por_logistica':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data          = file_get_contents('php://input');
            $decodedObject = json_decode($data, true);
            $U_n_documento = $decodedObject['U_n_documento'];
            $otroObjeto    = [];
            $cant_body     = $conexionsap->query("SELECT cc.Id, cc.Documento, cc.FechaRegistro, cc.Usuario, cc.Permitir,  if(cc.Permitir=1, 'Permitido', 'Negado')  as estado_permiso, dd.Detalle FROM activar_salida_logistico cc
                          INNER JOIN color_salida dd ON dd.Permitido= cc.Permitir AND dd.Estado=1
                         WHERE cc.Documento='$U_n_documento' AND cc.Permitir=1
                         ORDER BY cc.FechaRegistro DESC ");
            if (count($cant_body) > 0) {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => json_encode($cant_body),
                    "Estado"  => 1,
                    "Mensaje" => "",
                ];
            } else {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => [],
                    "Estado"  => 0,
                    "Mensaje" => "No documento no esta permitido para salir !!!",
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'lista_documento_activo_por_logistica':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data          = file_get_contents('php://input');
            $decodedObject = json_decode($data, true);
            $USUARIO       = $decodedObject['USUARIO'];
            $otroObjeto    = [];
            $cant_body     = $conexionsap->query("SELECT cc.Id, cc.Documento, cc.FechaRegistro, cc.Usuario, cc.Permitir, if(cc.Permitir=1, 'Permitido', 'Negado')  as estado_permiso, dd.Detalle FROM activar_salida_logistico cc
           INNER JOIN color_salida dd ON dd.Permitido= cc.Permitir AND dd.Estado=1 WHERE  cc.Usuario='$USUARIO' oRDER BY cc.FechaRegistro DESC  ");
            if (count($cant_body) > 0) {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => json_encode($cant_body),
                    "Estado"  => 1,
                    "Mensaje" => "",
                ];
            } else {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => [],
                    "Estado"  => 0,
                    "Mensaje" => "Sin Datos !!",
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    // case 'enviar_almacen_origen_regularizacion':
    //     if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    //         $data = file_get_contents('php://input');
    //         $decodedObject = json_decode($data, true);

    //         $nota = $decodedObject['nota'] ?? null;

    //         // Estructura de retorno por defecto
    //         $otroObjeto = [
    //             "Id"      => 0,
    //             "Lista"   => 0,
    //             "Estado"  => 0,
    //             "Mensaje" => "",
    //         ];

    //         if (!$nota) {
    //             $otroObjeto['Mensaje'] = "Falta el número de nota.";
    //             echo json_encode($otroObjeto);
    //             return;
    //         }

    //         try {
    //             // Llamar al procedimiento almacenado y guardar resultado en variable
    //             $cant_body = $conexionsap->query("CALL sp_insertar_envio_almacen_origen('$nota', @resultado)");

    //             // Obtener el valor de salida
    //             $res = $conexionsap->query("SELECT @resultado AS resultado");
    //             $row = $res->fetch_assoc();

    //             if ($row['resultado'] == 1) {
    //                 $otroObjeto['Lista']  = 1; // por ejemplo, número de registros insertados
    //                 $otroObjeto['Estado'] = 1;
    //                 $otroObjeto['Mensaje'] = "Notificación enviada al almacén origen para regularización de los items observados.";
    //             } else {
    //                 $otroObjeto['Mensaje'] = "No se pudo enviar la notificación.";
    //             }
    //         } catch (Exception $e) {
    //             $otroObjeto['Mensaje'] = "Error al procesar la solicitud: " . $e->getMessage();
    //         }

    //         echo json_encode($otroObjeto);
    //         return;
    //     }
    //     break;

    case 'actualizar_documento_pendientes_traspasos':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data             = file_get_contents('php://input');
            $decodedObject    = json_decode($data, true);
            $U_n_documento    = $decodedObject['U_n_documento'] ?? null;
            $item_array       = $decodedObject['item_array'] ?? null;
            $itemsModificados = $decodedObject['modificaciones'] ?? [];

            $valiar_completos_traspaso = $conexionsap->query("CALL `validar_completo_traspaso`('$U_n_documento')");
            if (count($valiar_completos_traspaso) > 0) {
                $cant_body     = $valiar_completos_traspaso[0]['Id'];
                $items_arraybb = $valiar_completos_traspaso[0]['item_array'];

                if (! empty($itemsModificados)) {
                    foreach ($item_array as &$item) {
                        if (
                            isset($item['CODIGO_ITEM']) &&
                            in_array($item['CODIGO_ITEM'], $itemsModificados)
                        ) {
                            $item['U_fecha_registro_x_items_regularizado'] = $fecha;
                        }
                    }
                    unset($item);
                }

                $array_log_actualizado = json_encode($item_array, JSON_UNESCAPED_UNICODE);

                $dddd = $conexionsap->query("update log_app_traspasos SET Array_Log_anterior='$items_arraybb',item_array='$array_log_actualizado' , FechaModificacion='$fecha' WHERE Id = $cant_body");

                //             if (count($dddd) > 0) {
                $otroObjeto = [
                    "Id"      => $dddd,
                    "Lista"   => $array_log_actualizado,
                    "Estado"  => 1,
                    "Mensaje" => "Actualizado la Información",
                ];
                // } else {
                //     $otroObjeto = [
                //         "Id"      => 0,
                //         "Lista"   => [],
                //         "Estado"  => 0,
                //         "Mensaje" => "No se Actualizado la Información",
                //     ];
                // }
                echo json_encode($otroObjeto);
                return;
            }
        }
        break;

    case 'lista_documento_pendientes_traspasos':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data          = file_get_contents('php://input');
            $decodedObject = json_decode($data, true);

            $almacen_d  = $decodedObject['almacen_d'] ?? null;
            $otroObjeto = [];
            $cant_body  = $conexionsap->query("CALL `todos_traspasos_sin_filtro`('$almacen_d')");
            if (count($cant_body) > 0) {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => $cant_body,
                    "Estado"  => 1,
                    "Mensaje" => "",
                ];
            } else {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => [],
                    "Estado"  => 0,
                    "Mensaje" => "Sin Datos !!",
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'lista_documento_pendientes_traspasos_total':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data          = file_get_contents('php://input');
            $decodedObject = json_decode($data, true);

            $almacen_d  = $decodedObject['almacen_d'] ?? null;
            $otroObjeto = [];
            $cant_body  = $conexionsap->query("CALL `todos_traspasos_sin_filtro_total`");
            if (count($cant_body) > 0) {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => $cant_body,
                    "Estado"  => 1,
                    "Mensaje" => "",
                ];
            } else {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => [],
                    "Estado"  => 0,
                    "Mensaje" => "Sin Datos !!",
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'lista_gestion_traspaso':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data          = file_get_contents('php://input');
            $decodedObject = json_decode($data, true);
            $otroObjeto    = [];
            $cant_body     = $conexionsap->query("CALL `lista_gestion_traspaso`()");
            if (count($cant_body) > 0) {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => $cant_body,
                    "Estado"  => 1,
                    "Mensaje" => "",
                ];
            } else {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => [],
                    "Estado"  => 0,
                    "Mensaje" => "Sin Datos !!",
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;
    case 'reporte_inventario':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data          = file_get_contents('php://input');
            $decodedObject = json_decode($data, true);
            $f_inicio      = $decodedObject['f_inicio'] ?? null;
            $f_fin         = $decodedObject['f_fin'] ?? null;
            $Almacen       = $decodedObject['Almacen'] ?? null;
            $otroObjeto    = [
                "Id"      => 0,
                "Lista"   => [],
                "Estado"  => 0,
                "Mensaje" => "Sin Datos !!",
            ];

            if (is_array($Almacen)) {
                $Almacen = implode(',', $Almacen); // 👈 convierte array a string
            }
            $cant_body = $conexionsap->query("CALL `pa_perfil_inventario`('$f_inicio', '$f_fin', '$Almacen')");
            if (count($cant_body) > 0) {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => $cant_body,
                    "Estado"  => 1,
                    "Mensaje" => $Almacen,
                ];
            }
            echo json_encode($otroObjeto);
            return;
        }
        break;

    case 'find_log_validar':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data             = file_get_contents('php://input');
            $decodedObject    = json_decode($data, true);
            $U_tipo_documento = $decodedObject['U_tipo_documento'];
            $U_n_documento    = $decodedObject['U_n_documento'];
            $otroObjeto       = [];
            $datat            = [];
            if (is_numeric($U_n_documento)) {

                if (strlen($U_n_documento) === 9) {
                    $datat = [];
                    $datat = $conexionsap->query("SELECT cc.appVersion, cc.despachador, cc.CODI, cc.RAMA, cc.SUCURSAL, cc.TIPO, cc.OWNER, cc.CODEV, cc.MEMO, cc.person, cc.item_array , cc.logArray FROM log_app_entrega cc
                            WHERE cc.U_n_documento='$U_n_documento' AND cc.U_tipo_documento=$U_tipo_documento ORDER BY cc.FechaRegistro DESC LIMIT 1");
                    if (count($datat) > 0) {
                        $lista_log_array = [];
                        $lista_uaxx      = [];
                        for ($ilc = 0; $ilc < count($datat); $ilc++) {
                            $lista_log_array     = $datat[$ilc]['logArray'];
                            $lista_log_array_aux = json_decode($lista_log_array, true); // Convertir a array asociativo
                            for ($ilcc = 0; $ilcc < count($lista_log_array_aux); $ilcc++) {
                                $Id_tipoentrega_v = $lista_log_array_aux[$ilcc]["Id_tipoentrega"];
                                if ($Id_tipoentrega_v == 2) {
                                    $lista_log_array_aux[$ilcc]["Id_tipoentrega"]         = "1";
                                    $lista_log_array_aux[$ilcc]["User_despachador"]       = "";
                                    $lista_log_array_aux[$ilcc]["Nombre_despachador"]     = "";
                                    $lista_log_array_aux[$ilcc]["campo_select"]           = "0";
                                    $lista_log_array_aux[$ilcc]["campo_select_color"]     = "";
                                    $lista_log_array_aux[$ilcc]["Color_id_tipoentrega"]   = "#3dca2f";
                                    $lista_log_array_aux[$ilcc]["Detalle_id_tipoentrega"] = "Entrega Total";
                                }
                            }
                        }
                        $item_array = json_encode($lista_log_array_aux);
                        $cant_body  = $conexionsap->query("update log_app_entrega cc set  cc.FechaModificacion='$fecha',  cc.logArray='$item_array' where  cc.U_n_documento='$U_n_documento' AND cc.U_tipo_documento=$U_tipo_documento ORDER BY cc.FechaRegistro DESC LIMIT 1 ");
                        $otroObjeto = [
                            "Id"      => 0,
                            "Lista"   => $cant_body,
                            "Estado"  => 1,
                            "Mensaje" => "",
                        ];
                    } else {
                        $otroObjeto = [
                            "Id"      => 0,
                            "Lista"   => json_encode($datat),
                            "Estado"  => 0,
                            "Mensaje" => "El documento no exite o no es un documento comercial !!!",
                        ];
                    }
                } else {
                    $otroObjeto = [
                        "Id"      => 0,
                        "Lista"   => json_encode($datat),
                        "Estado"  => 0,
                        "Mensaje" => "El valor no tiene exactamente 9 dígitos. 7 ",
                    ];
                }
            } else {
                $otroObjeto = [
                    "Id"      => 0,
                    "Lista"   => json_encode($datat),
                    "Estado"  => 0,
                    "Mensaje" => "El valor no es un número.",
                ];
            }

            echo json_encode($otroObjeto);
            return;
        }
        break;
}
