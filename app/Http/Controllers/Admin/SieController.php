<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inscripcion;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;


class SieController extends Controller
{
    public function index()
    {
        // 1. OPTIMIZACIÓN CRÍTICA: Traemos todas las categorías a memoria UNA SOLA VEZ.
        // Esto evita hacer una consulta a la base de datos por cada inscrito dentro del map().
        $todasLasCategorias = \App\Models\CategoriaCursoViaje::select('id', 'nombre_es', 'tipo')
            ->get()
            ->keyBy('id');

        // 2. CONSULTA PRINCIPAL
        $inscripciones = Inscripcion::with([
            'persona.tipoDocumento',
            'facturacion.tipoDocumentoFacturador',
            'cupon',
            'facturacion.cuotas.niubiz' => function ($query) {
                // Filtramos para que solo traiga los registros pagados de Niubiz y del evento 6
                $query->where('estado', 'pagado')
                    ->where('id_evento', 5);
            }
        ])
            ->orderBy('id', 'desc')
            ->get() // <-- Traemos los datos primero

            // 3. MAGIA AQUÍ: Filtramos la colección en memoria para evitar el error de PostgreSQL
            ->filter(function ($inscripcion) {
                // Si no tiene facturación o cuotas, lo descartamos
                if (!$inscripcion->facturacion || !$inscripcion->facturacion->cuotas) {
                    return false;
                }
                // Nos quedamos SOLO con los que tengan al menos una cuota con registro en Niubiz
                return $inscripcion->facturacion->cuotas->contains(fn($c) => $c->niubiz !== null);
            })

            // 4. MAPEO DE DATOS PARA VUE
            ->map(function ($inscripcion) use ($todasLasCategorias) {
                // Buscamos la primera cuota que tenga un registro de Niubiz
                $cuotaPagada = $inscripcion->facturacion?->cuotas->first(fn($c) => $c->niubiz !== null);
                $pagoNiubiz = $cuotaPagada?->niubiz;

                // --- LÓGICA DE TARJETA CON TRIM() PARA EVITAR ESPACIOS EN BLANCO ---
                $cardNum = trim($pagoNiubiz?->card_num ?? '');
                $marcaTarjeta = 'Otra/Desconocida';

                if ($cardNum) {
                    if (str_starts_with($cardNum, '4')) {
                        $marcaTarjeta = 'Visa';
                    } elseif (str_starts_with($cardNum, '5') || str_starts_with($cardNum, '2')) {
                        $marcaTarjeta = 'Mastercard';
                    } elseif (str_starts_with($cardNum, '34') || str_starts_with($cardNum, '37')) {
                        $marcaTarjeta = 'American Express';
                    } elseif (str_starts_with($cardNum, '36') || str_starts_with($cardNum, '38')) {
                        $marcaTarjeta = 'Diners Club';
                    } elseif (str_starts_with($cardNum, '62')) {
                        $marcaTarjeta = 'UnionPay';
                    }
                }
                // -------------------------------------------------------------------

                // --- LÓGICA OPTIMIZADA DE CURSOS/VIAJES (Sin tocar la BD) ---
                $cursosViajes = [];
                if (!empty($inscripcion->id_categoria_cursos_viajes)) {
                    foreach ($inscripcion->id_categoria_cursos_viajes as $idCurso) {
                        if (isset($todasLasCategorias[$idCurso])) {
                            $cursosViajes[] = $todasLasCategorias[$idCurso];
                        }
                    }
                }
                // -------------------------------------------------------------------

                return [
                    'id' => $inscripcion->id,
                    'fecha_registro' => $inscripcion->created_at ? $inscripcion->created_at->format('d/m/Y H:i') : '-',
                    'estado_inscripcion' => $inscripcion->isactive,
                    'origen' => $inscripcion->origen ?? 'Web',
                    'cargo' => $inscripcion->texto_cargo ?? 'No especificado',
                    'qr' => $inscripcion->qr ?? null,
                    'cupon_viaje' => $inscripcion->cupon_viaje ?? null,
                    'envio_sie' => (bool) $inscripcion->envio_sie,

                    // Variables de tarjeta
                    'marca_tarjeta' => $marcaTarjeta,
                    'numero_tarjeta' => $cardNum,

                    // Datos Completos de Persona
                    'persona' => $inscripcion->persona,
                    'nombres' => trim(($inscripcion->persona?->nombres ?? '') . ' ' . ($inscripcion->persona?->apellidos ?? '')),
                    'documento' => $inscripcion->persona?->dni ?? $inscripcion->persona?->documento ?? '-',
                    'tipo_documento' => $inscripcion->persona?->tipoDocumento?->name_es ?? 'Sin tipo',
                    'email' => $inscripcion->persona?->correo ?? 'Sin correo',

                    // Datos de Facturación Detallados
                    'facturacion' => [
                        'monto_total' => $inscripcion->facturacion?->total ?? 0,
                        'sub_total' => $inscripcion->facturacion?->sub_total ?? 0,
                        'igv' => $inscripcion->facturacion?->IGV ?? 0,
                        'razon_social' => $inscripcion->facturacion?->nombre_facturador ?? '-',
                        'ruc' => $inscripcion->facturacion?->numero_doc_facturador ?? '-',
                        'direccion' => $inscripcion->facturacion?->direccion_facturador ?? '-',
                        'correo_facturador' => $inscripcion->facturacion?->correo_facturador ?? '-',
                        'tipo_documento' => $inscripcion->facturacion?->tipoDocumentoFacturador?->name_es ?? 'RUC',
                    ],

                    'categoria_inscripcion' => $inscripcion->categoria_inscripcion ? [
                        'nombre_es' => $inscripcion->categoria_inscripcion->nombre_es
                    ] : null,

                    // Usamos el arreglo optimizado en memoria
                    'categoria_cursos_viajes' => $cursosViajes,

                    // Cupon
                    'cupon' => $inscripcion->cupon ? [
                        'codigo' => $inscripcion->cupon->codigo_cupon,
                        'razon_social' => $inscripcion->cupon->razon_social ?? 'Empresa no registrada'
                    ] : null,

                    // Pagos
                    'pagos' => $inscripcion->facturacion?->cuotas
                        ->filter(fn($cuota) => $cuota->niubiz !== null)
                        ->map(function ($cuota) {
                            return [
                                'cuota_id' => $cuota->id,
                                'estado_niubiz' => $cuota->niubiz->estado,
                                'transaccion_id' => $cuota->niubiz->id,
                                'info_pago' => $cuota->informacion,
                                'respuesta_api' => $cuota->respuesta_api,
                            ];
                        })->values(),

                    // Estado simplificado para la tabla
                    'estado_pago' => 'PAGADO', // Como ya filtramos los pendientes arriba, aquí todos son PAGADOS
                ];
            })
            ->values(); // <-- IMPORTANTE: values() reordena los índices tras el filter() inicial


        return \Inertia\Inertia::render('Admin/Sie/Index', [
            'inscritos' => $inscripciones
        ]);
    }

    public function enviarApi(Request $request)
    {
        // 1. Validamos que nos lleguen los datos completos (JSON) desde Vue
        $request->validate([
            'inscritos_data'      => 'required|array',
            'inscritos_data.*.id' => 'required|integer'
        ]);

        $inscritosData = $request->inscritos_data;
        $totalSeleccionados = count($inscritosData);

        $enviados = 0;
        $errores = 0;
        $omitidos = 0;
        $yaSincronizados = 0;

        // 2. Iteramos sobre cada objeto JSON recibido
        foreach ($inscritosData as $inscritoArray) {

            // Convertimos el array a un objeto para acceder fácilmente ($inscrito->id)
            $inscrito = json_decode(json_encode($inscritoArray));

            // Cargar el modelo REAL de Eloquent con sus relaciones para evitar consultas extra (Eager Loading)
            $inscripcionDb = \App\Models\Inscripcion::with(['facturacion.cuotas', 'persona', 'cupon'])
                ->find($inscrito->id);

            if (!$inscripcionDb) {
                $errores++;
                continue;
            }

            // Evitar reprocesar los que ya se enviaron
            if ($inscripcionDb->envio_sie) {
                $yaSincronizados++;
                continue;
            }

            try {
                // Validamos que tenga categoría (basado en el JSON)
                if (isset($inscrito->categoria_inscripcion) && !empty($inscrito->categoria_inscripcion)) {

                    // 3. Extraemos las variables DIRECTO DEL MODELO DE BASE DE DATOS
                    $facturacion = $inscripcionDb->facturacion;
                    $persona     = $inscripcionDb->persona;
                    $cupon       = $inscripcionDb->cupon;

                    // Obtenemos el pago de Niubiz desde las relaciones del modelo
                    $cuotaPagada = $facturacion ? $facturacion->cuotas->first(function ($c) {
                        return $c->niubiz !== null;
                    }) : null;
                    $niubiz      = $cuotaPagada ? $cuotaPagada->niubiz : null;

                    // 4. Ejecutar el servicio de WMC 2026 usando los modelos completos
                    $service_wmc = app(\App\Http\Controllers\WebServiceController::class)
                        ->wsInscripcion_WMC_2026($facturacion, $persona, $inscripcionDb, $niubiz, $cupon);

                    // 5. Procesar la respuesta del servicio (QR, etc.)
                    if (isset($service_wmc->Response) && $service_wmc->Response->Status === true) {
                        $inscripcionDb->qr = (string)$service_wmc->Response->QR;
                        $inscripcionDb->ws_status = true;
                        $inscripcionDb->envio_sie = true; // Éxito en el envío

                        // REGLA DE ORO: Cupón de hospedaje
                        if ($inscripcionDb->id_categoria_inscripcion !== null) {
                            $inscripcionDb->cupon_viaje = (string)$service_wmc->Response->Codigo;
                        } else {
                            $inscripcionDb->cupon_viaje = null;
                        }

                        $enviados++;
                        \Illuminate\Support\Facades\Log::info("Éxito WS WMC 2026 (ID: {$inscrito->id})");
                    } else {
                        $inscripcionDb->ws_status = false;
                        $inscripcionDb->envio_sie = false;
                        $errores++;
                        \Illuminate\Support\Facades\Log::error("ERROR SIE WMC 2026 (ID: {$inscrito->id})", (array)$service_wmc);
                    }

                    // --- LÓGICA ADICIONAL SOLICITADA ---

                    // A. Fallback de categoría si es null
                    if ($inscripcionDb->id_categoria_inscripcion == null) {
                        $inscripcionDb->id_categoria_inscripcion = $inscripcionDb->id_perfil;
                    }

                    // B. Guardamos los cambios de la inscripción
                    $inscripcionDb->save();

                    // C. ENVIAR CORREO SIEMPRE (Independiente de si el WS falló o no)
                    try {
                        if ($persona && $persona->correo) {
                            \Illuminate\Support\Facades\Mail::to($persona->correo)
                                ->send(new \App\Mail\MailInscripcion($inscripcionDb, $niubiz));
                        }
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Error enviando correo a {$persona->correo}: " . $e->getMessage());
                    }
                } else {
                    \Illuminate\Support\Facades\Log::warning("WS WMC 2026 omitido: categoría vacía (ID: {$inscrito->id})");
                    $omitidos++;
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Excepción Crítica (ID: {$inscrito->id}): " . $e->getMessage());
                $errores++;
            }
        }

        // 6. Feedback al usuario
        $mensaje = "Proceso completado. Procesados: {$enviados}.";
        if ($yaSincronizados > 0) $mensaje .= " ({$yaSincronizados} omitidos por estar ya en SIE).";
        if ($errores > 0) $mensaje .= " Hubo {$errores} errores detectados.";

        return ($enviados === 0 && $errores > 0)
            ? redirect()->back()->with('error', $mensaje)
            : redirect()->back()->with('success', $mensaje);
    }
}
