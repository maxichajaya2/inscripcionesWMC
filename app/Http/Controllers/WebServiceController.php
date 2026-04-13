<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Contacto;
use Illuminate\Contracts\Database\Eloquent\Builder;
use App\Helpers\HtmlHelper;
use App\Models\PorcentajeCuotas;
use App\Models\TipoPago;
use Illuminate\Support\Facades\Http;

class WebServiceController extends Controller
{
    public function __construct()
    {
        $this->url_enviroment = 'KBServiciosIIMPJavaEnvironment';
        $this->url_connection = 'http://200.37.185.5:8080';
        $this->now = Carbon::now()->format('Y-m-d');

        $this->urlPersonValidation = 'https://secure2.iimp.org:8443/KB_WEBASOCJavaEnvironment/rest/validarAsociado';
        $this->url_new_connection = "https://services.iimp.org.pe";
    }

    public function validatePersonMember($id_sie_documento, $numero_documento)
    {

        $data_ws = [
            "tipoDocumento" => $id_sie_documento,
            "numeroDocumento" => $numero_documento
        ];

        $request = Http::post($this->urlPersonValidation, $data_ws);

        if ($request->ok()) {
            $respuesta =   json_decode($request);
            if (isset($respuesta->codeMessage)) {
                if ($respuesta->codeMessage) return true;
            } else {
                return  false;
            }
        } else {
            return false;
        }

        return false;
    }

    public function wsPersona_create_update($persona)
    {
        try {
            $url = "{$this->url_new_connection}/connection.php";

            $ws['ipAddress'] = config('app.valid_ip');
            $ws['accessKey'] = config('app.valid_pass');
            $ws['serviceKey'] = "ws";
            $ws['event'] = config('app.evento');
            $ws['id_event'] = config('app.id_evento');
            $ws['siecode_event'] = config('app.event_code');

            $data_ws = [
                'service'           => "persona_register_update",
                'id_tipo_documento' => $persona->tipoDocumento->sie_code,
                'numero_documento'  => $persona->documento,
                'nombres'           => ($persona->nombres),
                'apellido_paterno'  => ($persona->apellido_paterno),
                'apellido_materno'  => ($persona->apellido_materno),
                'sexo'              => $persona->sexo,
                'fecha_nacimiento'  => $persona->fecha_nacimiento,
                'id_nacionalidad'   => $persona->id_nacionalidad,
                'correo'            => $persona->correo,
                'celular'           => $persona->celular,
                'direccion'         => ($persona->direccion->direccion),
                'empresa_siecode'   => '',
                'id_ocupacion'      => $persona->id_ocupacion,
                'pais'              => $persona->direccion->id_pais,
                'departamento'      => $persona->direccion->id_departamento,
                'provincia'         => $persona->direccion->id_provincia,
                'distrito'          => $persona->direccion->id_distrito,
                'ubigeo'            => '',
                'sie_code_persona'  => $persona->sie_code_persona,
            ];

            $ws['data'] = $data_ws;

            $response = $this->sendWS($url, json_encode($ws));

            if (isset($response->message) && (strpos($response->message, "Success") !== false)) {
                return ['status' => true, 'sie_code' => $response->sie_code];
            } else {
                return ['status' => false];
            }
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            return ['status' => false];
        }
    }

    public function wsInscripcion_create_update($facturacion, $persona, $inscripcion, $niubiz)
    {
        try {
            $url = "{$this->url_new_connection}/connection.php";

            $tipo_pago = TipoPago::find($facturacion->id_tipo_pago);
            $inscripcion->categoria_inscripcion->precio_disponible = $inscripcion->categoria_inscripcion->precio->where('fecha_inicio', '<=', $this->now)->where('fecha_fin', '>=', $this->now)->first();

            if (($persona->id_ocupacion == 2795) && (strlen($inscripcion->texto_cargo)) > 0) { //indice ocupacion no especificada o no se encuentra en el listado
                $cargo = $inscripcion->texto_cargo;
            } else {
                $cargo = $persona->ocupacion->name;
            }

            $data_ws = [];

            if (str_contains($inscripcion->categoria_inscripcion->nombre_es, 'DIA')) {
                $data_ws['lun'] = 0;
                $data_ws['mar'] = 0;
                $data_ws['mie'] = 0;
                $data_ws['jue'] = 0;
                $data_ws['vie'] = 0;
                $data_ws['ficha_condicion'] = '';

                $dias = json_decode($inscripcion->dias, true);

                foreach ($dias as $key => $dia) {
                    if ($dia) {
                        $data_ws[$key] = 1;
                        if ($data_ws['ficha_condicion'] == '') {
                            $data_ws['ficha_condicion'] = strtoupper(substr($key, 0, 2));
                        }
                    }
                }
            } else {
                $data_ws['lun'] = 1;
                $data_ws['mar'] = 1;
                $data_ws['mie'] = 1;
                $data_ws['jue'] = 1;
                $data_ws['vie'] = 1;
                $data_ws['ficha_condicion'] = $inscripcion->categoria_inscripcion->condicion;
            }

            $ws['ipAddress'] = config('app.valid_ip');
            $ws['accessKey'] = config('app.valid_pass');
            $ws['serviceKey'] = "ws";
            $ws['event'] = config('app.evento');
            $ws['id_event'] = config('app.id_evento');
            $ws['siecode_event'] = config('app.event_code');

            $data_ws['service'] = "inscripcion_register_update";
            $data_ws['inscrito_idtipodocumento'] = $persona->tipoDocumento->sie_code;
            $data_ws['inscrito_numerodocumento'] = $persona->documento;

            $data_ws['inscrito_empresa'] = $facturacion->observacion;
            $data_ws['inscrito_cargo'] = $cargo;
            $data_ws['auth_datos'] = $inscripcion->autorizacion_datos > 0 ? 1 : 0;
            $data_ws['pago_estado'] = "PAGADO";
            $data_ws['pago_tipo'] = $tipo_pago->siecode;
            $data_ws['pago_tarjeta'] = $niubiz->card_num;
            $data_ws['pago_orden'] = $niubiz->num_orden;
            $data_ws['pago_transaccion'] = $niubiz->idtransaccion;
            $data_ws['facturacion_razon_social'] = $facturacion->nombre_facturador;
            $data_ws['facturacion_siecode_tipo_documento'] = $facturacion->tipoDocumentoFacturador->sie_code;
            $data_ws['facturacion_numero_documento'] = $facturacion->numero_doc_facturador;
            $data_ws['facturacion_tipo_documentopago'] = $facturacion->tipoDocumentoPago->siecode;
            $data_ws['facturacion_persona'] = $facturacion->responsable_facturador;
            $data_ws['facturacion_telefono'] = $persona->celular;
            $data_ws['facturacion_email'] = $facturacion->correo_facturador;
            $data_ws['facturacion_direccion'] = $facturacion->direccion_facturador;
            $data_ws['facturacion_importe'] = (float)$facturacion->total;
            $data_ws['ficha_tipo'] = 2; //inscripciones individuales
            $data_ws['ficha_control'] = $inscripcion->categoria_inscripcion->control;
            $data_ws['ficha_categoria'] = $inscripcion->categoria_inscripcion->categoria;

            $data_ws['simbolo_moneda'] = $inscripcion->categoria_inscripcion->precio_disponible->moneda->simbolo;
            $data_ws['evento_tipo'] = config('app.event_type');
            $data_ws['evento_codigo'] = config('app.event_code');
            $data_ws['ficha_id'] = $inscripcion->id;

            $ws['data'] = $data_ws;

            $response = $this->sendWS($url, json_encode($ws));

            if (strpos($response->Message, "Success") !== false) {
                return ['status' => true];
            } else {
                return ['status' => false];
            }
        } catch (RequestException $e) {
            return ['status' => false];
        }
    }

    public function wsInscripcion_WMC_2026($facturacion, $persona, $inscripcion, $niubiz, $cupon)
    {
        try {
            /** ******* LOCAL *********/
            // $url = "https://secure2.iimp.org:8443/KBServiciosPruebaIIMPJavaEnvironment/rest/servicioinscripcionwmc";

            /** ******* PRODUCCION *********/
            $url = "https://secure2.iimp.org:8443/KBServiciosIIMPJavaEnvironment/rest/servicioinscripcionwmc";

            $clean = function ($str) {
                $unwanted = array('Š' => 'S', 'š' => 's', 'Ž' => 'Z', 'ž' => 'z', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'A', 'Ç' => 'C', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'B', 'ß' => 'Ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'a', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'o', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'þ' => 'b', 'ÿ' => 'y');
                return strtr($str, $unwanted);
            };

            $monto_descuento = (float)($facturacion->descuento ?? 0);
            $cargo = substr(strtoupper($clean($persona->id_ocupacion == 2795 ? ($inscripcion->texto_cargo ?? 'PARTICIPANTE') : ($persona->ocupacion->name ?? 'PARTICIPANTE'))), 0, 60);

            $tarifas_ws = [];
            $moneda_ws = ($facturacion->id_moneda == 1) ? "US$" : "S/.";
            $hoy = \Carbon\Carbon::now();

            // Variables de días inicializadas (Se sobreescribirán si hay categoría)
            $lunes = $martes = $miercoles = $jueves = $viernes = 1;
            $perfil_real = 6;

            // =========================================================================
            // SOLO SI EXISTE CATEGORÍA (Protección para Viajes)
            // =========================================================================
            if ($inscripcion->id_categoria_inscripcion) {

                $precio_cat = $inscripcion->categoria_inscripcion->precio->filter(function ($p) use ($hoy) {
                    return \Carbon\Carbon::parse($p->fecha_inicio)->startOfDay() <= $hoy && \Carbon\Carbon::parse($p->fecha_fin)->endOfDay() >= $hoy;
                })->first() ?? $inscripcion->categoria_inscripcion->precio->first();

                $perfil_real = ($precio_cat && $precio_cat->pivot) ? $precio_cat->pivot->id_perfil : 6;

                // BLOQUE DE DÍAS 1: Detección para el JSON principal
                if (preg_match('/\bDIA\b/u', strtoupper($inscripcion->categoria_inscripcion->nombre_es))) {
                    $lunes = $martes = $miercoles = $jueves = $viernes = 0;
                    $dias = is_array($inscripcion->dias) ? $inscripcion->dias : json_decode($inscripcion->dias, true);
                    if ($dias) {
                        $lunes = ($dias['lun'] ?? 0) ? 1 : 0;
                        $martes = ($dias['mar'] ?? 0) ? 1 : 0;
                        $miercoles = ($dias['mie'] ?? 0) ? 1 : 0;
                        $jueves = ($dias['jue'] ?? 0) ? 1 : 0;
                        $viernes = ($dias['vie'] ?? 0) ? 1 : 0;
                        foreach ($dias as $key => $v) {
                            if ($v) {
                                $ficha_condicion = strtoupper(substr($key, 0, 2));
                                break;
                            }
                        }
                    }
                }

                // BLOQUE DE DÍAS 2: Inserción en el array de Tarifas
                if (preg_match('/\bDIA\b/u', strtoupper($inscripcion->categoria_inscripcion->nombre_es))) {
                    $dias = is_array($inscripcion->dias) ? $inscripcion->dias : json_decode($inscripcion->dias, true);

                    if ($dias) {
                        foreach ($dias as $key => $v) {
                            if ($v) { // Si el día está marcado (1)
                                $codigo_dia = strtoupper(substr($key, 0, 2)); // LUN -> LU, MAR -> MA
                                $importe_final = (int)($precio_cat->valor ?? 0) - $monto_descuento;
                                $tarifas_ws[] = [
                                    "Control"   => (string)$inscripcion->categoria_inscripcion->control,
                                    "Categoria" => (string)$inscripcion->categoria_inscripcion->categoria,
                                    "Condicion" => $codigo_dia,
                                    "Moneda"    => (string)$moneda_ws,
                                    "Importe"   => (int)($precio_cat->valor ?? 0)
                                ];
                                $monto_descuento = 0;
                            }
                        }
                    }
                } else {
                    // Si NO es por día, se guarda la tarifa normal una sola vez
                    $importe_final = (int)($precio_cat->valor ?? 0) - $monto_descuento;

                    $tarifas_ws[] = [
                        "Control"   => (string)$inscripcion->categoria_inscripcion->control,
                        "Categoria" => (string)$inscripcion->categoria_inscripcion->categoria,
                        "Condicion" => substr((string)$inscripcion->categoria_inscripcion->condicion, 0, 2),
                        "Moneda"    => (string)$moneda_ws,
                        // "Importe"   => (int)($precio_cat->valor ?? 0)
                        "Importe"   => $importe_final > 0 ? $importe_final : 0
                    ];
                }
            } else {
                // SI ES VIAJE: Ponemos los días en 0 (porque no va al congreso)
                $lunes = $martes = $miercoles = $jueves = $viernes = 1;
            }

            // --- LÓGICA DE EXTRAS ---
            $extras_ids = $inscripcion->id_categoria_cursos_viajes;
            if (!empty($extras_ids) && is_array($extras_ids)) {
                $extras_bd = \App\Models\CategoriaCursoViaje::whereIn('id', $extras_ids)->get();
                foreach ($extras_bd as $extra) {
                    $precio_extra = $extra->precios()->where('id_perfil', $perfil_real)->first();
                    if ($precio_extra) {
                        $tarifas_ws[] = [
                            "Control"   => (string)$extra->control,
                            "Categoria" => (string)$extra->categoria,
                            "Condicion" => (string)$extra->condicion,
                            "Moneda"    => (string)$moneda_ws,
                            "Importe"   => (int)$precio_extra->valor
                        ];
                    }
                }
            }

            $data_ws = [
                "TipEvCod"          => 13,
                "EvenCod"           => 1,
                "TipoDocumento"     => (string)($persona->tipoDocumento->sie_code ?? "1"),
                "NumDocumento"      => (string)$persona->documento,
                "Nombres"           => substr(strtoupper($clean($persona->nombres)), 0, 30),
                "ApellidoPaterno"   => substr(strtoupper($clean($persona->apellido_paterno)), 0, 30),
                "ApellidoMaterno"   => substr(strtoupper($clean($persona->apellido_materno ?? " ")), 0, 30),
                "FechaNacimiento"   => (string)$persona->fecha_nacimiento,
                "Sexo"              => (string)$persona->sexo ?? "",
                "IdEmpresa"            => (int)$persona->id_empresa ?? null,
                "Empresa"           => (string)$persona->company ?? "",
                "IdCargo"            => (int)$persona->id_ocupacion ?? null,
                "Cargo"             => (string)$persona->ocupacion ?? "",
                "Pais"              => (int)($persona->direccion->id_pais ?? 75),
                "Departamento"      => (int)($persona->direccion->id_departamento ?? 0),
                "Provincia"         => (int)($persona->direccion->id_provincia ?? 0),
                "Distrito"          => (int)($persona->direccion->id_distrito ?? 0),
                "Direccion"         => substr(strtoupper($clean($persona->direccion->direccion ?? "")), 0, 100),
                "Telefono"          => substr($persona->celular ?? "999999999", 0, 20),
                "Email"             => (string)$persona->correo,
                "Tarifas"           => $tarifas_ws,
                "Lunes"             => $lunes ?? 1,
                "Martes"            => $martes ?? 1,
                "Miercoles"         => $miercoles ?? 1,
                "Jueves"            => $jueves ?? 1,
                "Viernes"           => $viernes ?? 1,
                "AplicaDscto"       => $inscripcion->id_cupon ? true : false,
                "PorcentajeCupon"   => $cupon->valor ?? 0,
                "CodCupon"          => $cupon->codigo_cupon ?? "",
                "CodEmpresaCupon"   => $cupon->eci_cod ?? "",
                "TipoFacturacion"   => (string)($facturacion->tipo_doc_pago == 1 ? "01" : "03"),
                "TipDocFacturacion" => (string)($facturacion->tipoDocumentoFacturador->sie_code ?? "1"),
                "NumDocFacturacion" => (string)$facturacion->numero_doc_facturador,
                "RazonSocial"       => substr(strtoupper($clean($facturacion->nombre_facturador)), 0, 100),
                "DirFacturacion"    => substr(strtoupper($clean($facturacion->direccion_facturador)), 0, 100),
                "NombreContactoFact" => substr(strtoupper($clean($facturacion->responsable_facturador ?? $persona->nombres)), 0, 90),
                "CorreoContactoFact" => (string)$facturacion->correo_facturador,
                "MetodoPago"        => 1,
                "NroTarjeta"        => (string)($niubiz->card_num ?? "0000"),
                "NumeroOrden"       => (string)$niubiz->num_orden,
                "CodigoOperacion"   => (string)$niubiz->idtransaccion,
                "TipoFicha"         => 2
            ];


            \Illuminate\Support\Facades\Log::info("JSON WMC SEND FINAL:", $data_ws);

            $response  = $this->sendWS($url, json_encode($data_ws));

            // dd([
            //     "INFO" => "RESPUESTA CRUDA DEL SERVICIO JAVA",
            //     "URL_POST" => $url,
            //     "JSON_ENVIADO" => $data_ws,
            //     "RESPUESTA_DE_JAVA" => $response, // Aquí verás el QR, SieCode, etc.
            //     "HTTP_CODE" => (isset($response->Response)) ? "Conexión Exitosa" : "Posible Error de Conexión"
            // ]);

            return $response;
        } catch (\Exception $e) {
            return (object)['Response' => (object)['Status' => false, 'Message' => $e->getMessage()]];
        }
    }

    private function validateService($request)
    {
        if ($request->ok()) {
            return true;
        } else {
            return false;
        }
    }

    public function sendWS($url, $data, $header = null, $headername = null)
    {

        if (!is_null($header)) {

            if (is_null($headername)) {
                $headername = "Authorization";
            }
            $content = array(
                $headername . ': ' . $header,
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data)
            );
        } else {
            $content = array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data)
            );
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $content);
        $response = curl_exec($ch);
        curl_close($ch);

        $decoded_response = json_decode($response);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded_response;
        } else {
            return $response;
        }
    }
}
