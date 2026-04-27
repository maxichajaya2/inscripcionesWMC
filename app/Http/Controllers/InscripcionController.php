<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Carbon\Carbon;
use App\Models\Inscripcion;
use App\Models\Persona;
use App\Models\Ocupacion;
use App\Models\Direccion;
use App\Models\Cuota;
use App\Models\Facturacion;
use App\Models\Pasarela;
use App\Models\Niubiz;
use App\Models\CategoriaInscripcion;
use App\Mail\MailInscripcion;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\CategoriaCursoViaje;
use App\Models\Precio;
use App\Models\Autor;
use App\Models\Cupon;

class InscripcionController extends Controller
{
    public function __construct()
    {
        $this->now = Carbon::now()->format('Y-m-d');
    }

    public function index()
    {
        $categorias = CategoriaInscripcion::query()
            ->where('isactive', true)
            ->where('id', '!=', 40)
            ->where(function ($query) {
                $query->where('nombre_en', 'LIKE', '%AUTHOR%')
                    ->orWhere('nombre_en', 'LIKE', '%PARTICIPANT%');
            })
            ->orderBy('orden_es', 'ASC')
            ->get();

        // dd($categorias);
        foreach ($categorias as $categoria) {
            // 1. Buscamos el precio vigente
            $precioVigente = $categoria->precio->filter(function ($p) {
                return Carbon::parse($p->fecha_inicio)->startOfDay() <= Carbon::now()
                    && Carbon::parse($p->fecha_fin)->endOfDay() >= Carbon::now();
            })->first() ?? $categoria->precio->first();

            $categoria->precio_disponible = $precioVigente;

            // 2. EXTRAEMOS EL ID_PERFIL DE LA TABLA PIVOTE (intermedia)
            // Ahora ya no será null, vendrá de la tabla detalle_categoria
            $categoria->id_perfil = $precioVigente ? $precioVigente->pivot->id_perfil : null;

            // 3. Definimos el grupo para la UI
            $categoria->grupo = str_contains(strtoupper($categoria->nombre_en), 'AUTHOR') ? 'autor' : 'participante';
        }


        //  dd($categorias->toArray());
        $title = "Registration WMC 2026";

        return Inertia::render('Inscripcion/Index', compact('categorias', 'title'));
    }

    public function autor(Request $request)
    {
        return $this->renderInscripcion($request, '%AUTHOR%', "Author with special rate");
    }

    public function participante(Request $request)
    {
        return $this->renderInscripcion($request, '%PARTICIPANT%', "General Attendee");
    }

    public function cursosViajes(Request $request)
    {

        return $this->renderInscripcion($request, '%PARTICIPANT%', "Tours & Courses");
    }
    private function renderInscripcion(Request $request, string $filtro, string $defaultTitle)
    {
        $section = $request->query('section', 'inscripciones');
        $perfil_id = $request->query('profile');
        $course   = $request->query('course');
        $perfilesPermitidos = [1, 2, 3, 5, 6, 7];
        $now = Carbon::now();

        // Función anónima para reutilizar la lógica de precios vigentes
        $filtroPrecios = function ($query) {
            $query->where('precio.fecha_inicio', '<=', $this->now)
                ->where('precio.fecha_fin', '>=', $this->now)
                ->where('precio.isactive', true); // <--- CAMBIO AQUÍ
        };
        // 1. Cargamos los Perfiles Principales (Inscripción al Congreso)
        $categorias = CategoriaInscripcion::with(['precio' => $filtroPrecios])
            ->where('nombre_en', 'LIKE', $filtro)
            ->where('isactive', true)
            ->orderBy('orden_es', 'ASC')
            ->get()
            ->map(function ($cat) {
                $cat->precio_disponible = $cat->precio->first();
                return $cat;
            });

        $cupones = Cupon::query()
            ->where('is_active', true) // Según tu imagen es 'is_active'
            ->where('fecha_inicio', '<=', $now) // Debe haber empezado (inicio menor o igual a hoy)
            ->where('fecha_fin', '>=', $now)   // No debe haber terminado (fin mayor o igual a hoy)
            // ->whereRaw('usos_actuales < limite_usos') // Validación extra de stock
            ->orderBy('razon_social', 'asc')
            ->get();

        $autores = [];

        if ($perfil_id == 1 || $perfil_id == 2 || $perfil_id == 3 || $perfil_id == 4) {
            $autores = Autor::orderBy('correlativo', 'ASC')->get();
        }

        $perfilesPermitidos = [1, 2, 3, 5, 6, 7];

        // 2. Adicionales (Cursos/Tours) con validación de perfiles
        if (!in_array($perfil_id, $perfilesPermitidos)) {
            $adicionales = collect(); // Si no es perfil permitido, enviamos lista vacía
        } else {
            $adicionales = CategoriaCursoViaje::with(['precios' => $filtroPrecios])
                ->where('isactive', true)
                ->orderBy('nombre_en', 'ASC')
                ->get()
                ->map(function ($item) use ($perfil_id) {
                    // Buscamos el precio que coincida con el perfil en la tabla pivote
                    $precioEspecifico = $item->precios->first(function ($p) use ($perfil_id) {
                        return $p->pivot->id_perfil == $perfil_id;
                    });

                    // Asignamos el precio. Si no existe para ese perfil, será null
                    $item->precio_disponible = $precioEspecifico;
                    return $item;
                })
                // Solo dejamos los cursos que SI tienen precio para ese perfil
                ->filter(fn($item) => $item->precio_disponible !== null)
                ->values();
        }

        // dd($adicionales->toArray());


        $title = ($section === 'viajes') ? "Tours & Courses" : $defaultTitle;
        $componente = ($section === 'viajes') ? 'Inscripcion/InicioCursoViaje' : 'Inscripcion/Inicio';

        // return Inertia::render($componente, compact('categorias', 'adicionales', 'title', 'section',));
        return Inertia::render($componente, [
            'categorias' => $categorias,
            'adicionales' => $adicionales,
            'title' => $title,
            'section' => $section,
            'cupones' => $cupones,
            'perfil_id' => (int) $perfil_id,
            'course'  => (int)$course,
            'autores' => $autores
        ]);
    }

    public function getForm(Request $request)
    {

        // 1. Validaciones iniciales

        $this->validateRequest($request);

        // 2. Obtener / Guardar Persona y Dirección
        $persona = $this->handlePersona($request);

        // 3. Obtener Categoría
        $categoria = CategoriaInscripcion::findOrFail($request->input('selected_categoria'));

        // 4. Calcular Total (Precio base + Días + Extras)
        // Devuelve un array con [total, array_nombres_extras, json_dias]
        $calculo = $this->calculateTotal($request, $categoria);

        $total = $calculo['total'];
        $nombres_extras = $calculo['nombres_extras'];
        $dias_json = $calculo['dias_json'];
        $descuento = $calculo['descuento_total'] ?? 0;

        // 5. Crear Facturación y Cuota
        $facturacion = $this->createFacturacion($request, $persona, $categoria, $total, $nombres_extras, $descuento);

        // dd($facturacion);

        // 6. Crear Inscripción (y subir archivo)
        $inscripcion = $this->createInscripcion($request, $persona, $categoria, $facturacion, $dias_json);


        // 7. Generar respuesta de Niubiz
        // dd($this->generateNiubizResponse($persona, $inscripcion, $facturacion));
        return $this->generateNiubizResponse($persona, $inscripcion, $facturacion, $descuento);
    }

    // =========================================================================
    // MÉTODOS PRIVADOS (HELPER FUNCTIONS)
    // =========================================================================

    private function validateRequest(Request $request)
    {
        if (!str_contains($request->headers->get('referer'), 'registro') || (csrf_token() === null)) {
            abort(403, 'Unauthorized POST request.');
        }

        $id_tipo_documento = $request->input('id_tipo_documento') ?? $request->input('tipo_doc');
        $documento = trim($request->input('documento') ?? '');

        if (empty($id_tipo_documento) || empty($documento)) {
            abort(response()->json(['status' => false, 'message' => 'Document info missing'], 400));
        }
    }

    private function parseNull($value)
    {
        return ($value === 'null' || $value === '' || !$value || $value == 0) ? null : (int)$value;
    }

    private function handlePersona(Request $request)
    {
        // 1. Resolver Ocupación (Previniendo error de bigint)
        $cargo = $request->input('cargo', '');
        $id_ocupacion_input = $request->input('id_ocupacion');

        $ocupacion_obj = null;
        if (!empty($id_ocupacion_input)) {
            // Si el frontend envía un ID, lo usamos (forzando casting a int)
            $ocupacion_obj = Ocupacion::find((int)$id_ocupacion_input);
        }

        // Si no se encontró por ID, buscamos por nombre
        if (!$ocupacion_obj && !empty($cargo)) {
            $ocupacion_obj = Ocupacion::where('name', 'LIKE', '%' . trim($cargo) . '%')
                ->first();
        }

        $id_ocupacion = $ocupacion_obj ? $ocupacion_obj->id : 2795; // 2795 es el fallback

        // 2. Buscar o Instanciar Persona (Usando el documento real)
        $documentoInput = trim($request->input('documento'));
        $tipoDocumentoId = $request->input('id_tipo_documento') ?? $request->input('tipo_doc');

        // Buscamos la persona. Nota: Si usas encriptación, Eloquent se encarga del casting si el modelo está bien configurado.
        $persona = Persona::where('id_tipo_documento', $tipoDocumentoId)
            ->where('documento', $documentoInput)
            ->firstOrNew();

        // 3. Guardar / Actualizar Dirección
        $direccion = ($persona->id_direccion > 0) ? Direccion::find($persona->id_direccion) : new Direccion;

        $direccion->id_pais = $request->input('pais');

        // Validamos nulos para Postgres
        $direccion->id_departamento = $this->parseNull($request->input('departamento'));
        $direccion->id_provincia    = $this->parseNull($request->input('provincia'));
        $direccion->id_distrito     = $this->parseNull($request->input('distrito'));

        $direccion->direccion = trim($request->input('direccionPersona', ''));
        $direccion->save();

        // 4. Actualizar Datos de la Persona con lo que viene del Formulario
        $persona->id_direccion     = $direccion->id;
        $persona->id_ocupacion     = $id_ocupacion;
        $persona->ocupacion = $ocupacion_obj->name ?? $cargo ?? '';
        $persona->nombres          = trim($request->input('nombres'));
        $persona->apellido_paterno  = trim($request->input('apellido_paterno'));
        $persona->apellido_materno  = $request->input('apellido_materno', '');
        $persona->correo           = trim($request->input('correo'));
        $persona->celular          = trim($request->input('celular'));
        $persona->sexo             = $request->input('sexo');
        $persona->id_nacionalidad  = $request->input('nacionalidad', $request->input('pais'));
        $persona->empresa          = trim($request->input('empresa'));

        // Si es nueva persona, asignamos los campos clave
        if (!$persona->exists) {
            $persona->id_tipo_documento = $tipoDocumentoId;
            $persona->documento = $documentoInput;
        }

        // Fecha de nacimiento segura
        if ($request->filled('fecha_nacimiento')) {
            try {
                $persona->fecha_nacimiento = Carbon::parse($request->input('fecha_nacimiento'))->format('Y-m-d');
            } catch (\Exception $e) {
                Log::error("Error parseando fecha en handlePersona: " . $e->getMessage());
            }
        }


        $persona->save();

        return $persona;
    }


    private function calculateTotal(Request $request, $categoria)
    {
        $hoy = Carbon::now();
        $total_inscripcion = 0.0;
        $total_extras = 0.0;
        $nombres_extras = [];
        // $dias_json = '{"lun":1,"mar":1,"mie":1,"jue":1,"vie":1}';
        $dias_json = null;
        $descuento_monto = 0.0;

        // 1. OBTENER PRECIO DE LA CATEGORÍA (Relación: precio)
        $precio_base_obj = $categoria->precio->first(function ($p) use ($hoy) {
            return $hoy->between(
                Carbon::parse($p->fecha_inicio)->startOfDay(),
                Carbon::parse($p->fecha_fin)->endOfDay()
            );
        }) ?? $categoria->precio->first();

        $valor_unitario_cat = $precio_base_obj ? (float)$precio_base_obj->valor : 0;

        if ($request->filled('id_cupon') && $request->input('id_cupon') !== 'null' && $request->input('section') !== 'viajes') {
            $cupon = \App\Models\Cupon::where('id', (int)$request->input('id_cupon'))
                ->where('codigo_cupon', $request->input('codigo_cupon'))
                ->where('is_active', true)
                ->first();

            // if ($cupon) {
            //     $descuento_monto = ($valor_unitario_cat * ($cupon->valor / 100));
            //     $valor_unitario_cat = $valor_unitario_cat - $descuento_monto;
            // }
            if ($cupon) {
                // 1. Leemos el tipo de descuento (por defecto asumimos porcentaje por seguridad)
                $tipoDescuento = $cupon->tipo_descuento ?? 'porcentaje';

                if (strtolower($tipoDescuento) === 'fijo') {
                    // LÓGICA FIJA: El monto de descuento es directamente el valor del cupón
                    $descuento_monto = (float)$cupon->valor;
                } else {
                    // LÓGICA PORCENTAJE: La matemática original que ya tenías
                    $descuento_monto = ($valor_unitario_cat * ((float)$cupon->valor / 100));
                }

                // 2. Seguridad crucial: Evitar que el descuento sea mayor al precio (totales negativos)
                $descuento_monto = min($descuento_monto, $valor_unitario_cat);

                // 3. Restamos el descuento calculado al valor de la categoría
                $valor_unitario_cat = $valor_unitario_cat - $descuento_monto;
            }
        }

        // 2. LÓGICA DE INSCRIPCIÓN: ¿Paga entrada al congreso o solo adicionales?
        if ($request->input('section') === 'viajes') {
            // Si viene de la pestaña de tours, la inscripción base es CERO
            $total_inscripcion = 0.0;
        } else {

            $nombre_en = strtoupper($categoria->nombre_en);
            $nombre_es = strtoupper($categoria->nombre_es);

            // Si contiene STUDENT o ESTUDIANTE, NUNCA es por día (es Full Event)
            $es_estudiante = str_contains($nombre_en, 'STUDENT') || str_contains($nombre_es, 'ESTUDIANTE');

            // Es por día SOLO si tiene la palabra "DAY" o "DIA" pero NO es estudiante
            $es_por_dia = !$es_estudiante && (str_contains($nombre_en, ' DAY') || str_contains($nombre_es, ' DIA'));

            if ($es_por_dia) {
                $selectedDays = $request->input('selectedDays', []);
                if (is_string($selectedDays)) $selectedDays = explode(',', $selectedDays);

                $total_inscripcion = count($selectedDays) * $valor_unitario_cat;

                $dias_array = ["lun" => 0, "mar" => 0, "mie" => 0, "jue" => 0, "vie" => 0];
                foreach ($selectedDays as $sd) {
                    $dia_key = strtolower(trim($sd));
                    if (array_key_exists($dia_key, $dias_array)) $dias_array[$dia_key] = 1;
                }
                $dias_json = json_encode($dias_array);
            } else {
                // Caso Estudiantes, Autores o Full Pass
                $total_inscripcion = $valor_unitario_cat;
                $dias_json = null; // Mantenemos null para que no se guarde "lun:1, mar:1..."
            }
        }

        // 3. LÓGICA DE EXTRAS: Cursos y Tours (Relación: precios en tabla CategoriaCursoViaje)
        $extras_ids = json_decode($request->input('extras_seleccionados'), true);
        $perfil_id = $request->input('profile');

        if (!empty($extras_ids) && is_array($extras_ids)) {
            // Traemos los extras pero filtrando la relación 'precios' desde la consulta
            $extras_bd = \App\Models\CategoriaCursoViaje::whereIn('id', $extras_ids)
                ->with(['precios' => function ($query) use ($hoy, $perfil_id) {
                    $query->where('fecha_inicio', '<=', $hoy)
                        ->where('fecha_fin', '>=', $hoy)
                        ->where('detalle_categoria_cursos_viajes.id_perfil', $perfil_id); // Filtro directo en el pivot
                }])
                ->get();

            foreach ($extras_bd as $extra) {
                // Como ya filtramos en la consulta, el primero es el correcto
                $precio_obj = $extra->precios->first();

                if ($precio_obj) {
                    $total_extras += (float)$precio_obj->valor;
                    $nombres_extras[] = $extra->nombre_en;
                }
            }
        }

        // 4. SUMA FINAL
        // dd($total_inscripcion, $total_extras);
        $total_final = $total_inscripcion + $total_extras;

        // AUDITORÍA: Si sale 1800, verás por qué en storage/logs/laravel.log
        Log::warning("AUDITORÍA DE PAGO WMC", [
            'section_recibida' => $request->input('section'),
            'monto_inscripcion' => $total_inscripcion,
            'monto_extras' => $total_extras,
            'total_calculado' => $total_final,
            'extras_detectados' => $nombres_extras
        ]);

        return [
            'total' => $total_final,
            'nombres_extras' => $nombres_extras,
            'descuento_total' => round($descuento_monto, 2),
            'dias_json' => $dias_json,
            'moneda' => $precio_base_obj ? $precio_base_obj->id_moneda : 1
        ];
    }

    private function createFacturacion(Request $request, $persona, $categoria, $total, $nombres_extras, $descuento = 0)
    {
        // Recalcular moneda si es necesario o pasarla desde calculateTotal
        // Aquí asumimos que usamos la moneda del precio base de la categoría para simplificar
        $precio_base = $categoria->precio->first();

        $IGV = round(($total * 0.18), 2);

        $facturacion = new Facturacion;
        $facturacion->id_tipo_servicio = 4;
        $facturacion->id_moneda = $precio_base->moneda->id ?? 1;
        $facturacion->id_tipo_pago = $request->input('selectTipoPago');
        $facturacion->tipo_doc_pago = $request->input('selectTipoDocPago');
        $facturacion->id_tipo_doc_facturador = $request->input('tipoDocumentoEmpresa');
        $facturacion->numero_doc_facturador = trim($request->input('documentoEmpresa'));
        $facturacion->nombre_facturador = trim($request->input('razonSocial'));
        $facturacion->direccion_facturador = trim($request->input('direccionEmpresa'));
        $facturacion->responsable_facturador = trim($request->input('responsable'));
        $facturacion->correo_facturador = trim($request->input('correo_facturador'));
        $facturacion->id_comprador = $persona->id;
        $facturacion->tipo_comprador = 'persona';
        $facturacion->IGV = $IGV;
        $facturacion->sub_total = floatval($total) - $IGV;
        $facturacion->detraccion = 0;
        $facturacion->descuento  = $descuento;
        $facturacion->total = $total;

        $obs_extras = count($nombres_extras) > 0 ? " | Extras: " . implode(', ', $nombres_extras) : "";
        $facturacion->observacion = trim($request->input('empresa', '')) . $obs_extras;
        $facturacion->save();

        // Crear Cuota vinculada (Es parte de la facturación)
        $cuota = new Cuota;
        $cuota->id_facturacion = $facturacion->id;
        $cuota->estado_pago = 'PENDIENTE';
        $cuota->isactive = true;
        $cuota->informacion = json_encode([
            "cuota" => "1",
            "valor" => (string)$total,
            "porcentaje" => "100",
            "estado_pago" => false
        ]);
        $cuota->save();

        return $facturacion;
    }

    private function createInscripcion(Request $request, $persona, $categoria, $facturacion, $dias_json)
    {
        $extras_ids = json_decode($request->input('extras_seleccionados'), true);


        // dd([
        //     'MENSAJE' => 'REVISANDO DATA EN CREATE_INSCRIPCION',
        //     'CATEGORIA_BASE' => $categoria->id,
        //     'EXTRAS_RECIBIDOS_JSON' => $request->input('extras_seleccionados'),
        //     'EXTRAS_DECODIFICADOS' => $extras_ids,
        //     'PERSONA_ID' => $persona->id,
        //     'FACTURACION_ID' => $facturacion->id
        // ]);

        $inscripcion = new Inscripcion;
        $inscripcion->id_persona = $persona->id;
        if ($request->input('section') === 'viajes') {
            $inscripcion->id_categoria_inscripcion = null; // No asignamos categoría de inscripción para tours/cursos
        } else {
            $inscripcion->id_categoria_inscripcion = $categoria->id;
        }
        $inscripcion->id_categoria_cursos_viajes = json_decode($request->input('extras_seleccionados'), true);
        $inscripcion->id_facturacion = $facturacion->id;
        $inscripcion->usuario_creacion = $persona->id;
        $inscripcion->origen = 'web';
        $inscripcion->texto_cargo = $request->input('cargo', '');
        $inscripcion->dias = $dias_json;
        $inscripcion->autorizacion_datos = $request->input('auth', false);
        $idCupon = $request->input('id_cupon');
        $inscripcion->id_cupon = ($idCupon === 'null' || empty($idCupon)) ? null : $idCupon;

        if ($request->hasFile('uploadDocument')) {
            $file = $request->file('uploadDocument');
            $name = 'insc_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move(storage_path('app/public/documents'), $name);
            $inscripcion->document_path = asset('storage/documents/' . $name);
        }

        $inscripcion->id_perfil = $request->input('category');

        $inscripcion->save();

        // dd('INSCRIPCIÓN CREADA CORRECTAMENTE', [
        //     'INSCRIPCION_ID' => $inscripcion->id,
        //     'CATEGORIA_ID' => $categoria->id,
        //     'EXTRAS_IDS' => $extras_ids,
        //     'PERSONA_ID' => $persona->id,
        //     'FACTURACION_ID' => $facturacion->id
        // ]);

        return $inscripcion;
    }

    private function generateNiubizResponse($persona, $inscripcion, $facturacion, $descuento = 0)
    {
        try {
            $formNiubiz = app(\App\Http\Controllers\NiubizController::class)->getForm(
                $persona,
                $inscripcion,
                $facturacion,
                $descuento,
                url()->previous(),
                url()->current()
            );

            // Actualizamos la cuota con el token de respuesta (k)
            $cuota = $facturacion->cuotas->first();
            $cuota->respuesta_api = $formNiubiz->k;
            $cuota->update();

            return response()->json([
                'status' => true,
                'formulario' => json_decode(base64_decode($formNiubiz->frm)),
                'total_real' => $facturacion->total,
                'descuento' => $descuento
            ]);
        } catch (\Exception $e) {
            Log::error("Error en Niubiz: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Error al contactar pasarela'], 500);
        }
    }

    public function niubizPayment($id, $order)
    {

        // dd('NIUBIZ PAYMENT LLEGÓ CORRECTAMENTE', ['id' => $id, 'order' => $order]);
        $facturacion = Facturacion::findOrFail($id);
        $cuota = $facturacion->cuotas->first();

        $transactiontoken = $_POST['transactionToken'] ?? null;

        if (!$transactiontoken) {
            return redirect('/')->with('error', 'Token de transacción no encontrado.');
        }

        $respuesta = app(\App\Http\Controllers\NiubizController::class)->authorization($cuota->respuesta_api, $facturacion->total, $transactiontoken, $order);

        // $respuesta = '{
        //     "header": {
        //         "ecoreTransactionUUID": "3746e2a1-19bb-4251-b920-f7d2cc7c7c6e",
        //         "ecoreTransactionDate": 1749744006879,
        //         "millis": 958
        //     },
        //     "fulfillment": {
        //         "channel": "web",
        //         "merchantId": "456879853",
        //         "terminalId": "00000001",
        //         "captureType": "manual",
        //         "countable": true,
        //         "fastPayment": false,
        //         "signature": "3746e2a1-19bb-4251-b920-f7d2cc7c7c6e"
        //     },
        //     "order": {
        //         "tokenId": "3624210E49BA4F80A4210E49BA4F80E0",
        //         "purchaseNumber": "8291",
        //         "amount": 2200,
        //         "installment": 0,
        //         "currency": "USD",
        //         "authorizedAmount": 2200,
        //         "authorizationCode": "091800",
        //         "actionCode": "000",
        //         "traceNumber": "31645",
        //         "transactionDate": "250612110006",
        //         "transactionId": "993211570048581"
        //     },
        //     "dataMap": {
        //         "TERMINAL": "00000001",
        //         "BRAND_ACTION_CODE": "00",
        //         "BRAND_HOST_DATE_TIME": "201222141839",
        //         "TRACE_NUMBER": "31645",
        //         "CARD_TYPE": "D",
        //         "ECI_DESCRIPTION": "Transaccion no autenticada pero enviada en canal seguro",
        //         "SIGNATURE": "3746e2a1-19bb-4251-b920-f7d2cc7c7c6e",
        //         "CARD": "447411******2240",
        //         "MERCHANT": "109705108",
        //         "STATUS": "Authorized",
        //         "ACTION_DESCRIPTION": "Aprobado y completado con exito",
        //         "ID_UNICO": "993211570048581",
        //         "AMOUNT": "1900.0",
        //         "AUTHORIZATION_CODE": "091800",
        //         "YAPE_ID": "",
        //         "CURRENCY": "0604",
        //         "TRANSACTION_DATE": "250612110006",
        //         "ACTION_CODE": "000",
        //         "CVV2_VALIDATION_RESULT": "M",
        //         "ECI": "07",
        //         "ID_RESOLUTOR": "420201222142237",
        //         "BRAND": "visa",
        //         "ADQUIRENTE": "570002",
        //         "BRAND_NAME": "VI",
        //         "PROCESS_CODE": "000000",
        //         "TRANSACTION_ID": "993211570048581"
        //     }
        // }';

        $filtered_response = app(\App\Http\Controllers\NiubizController::class)->filterResponse($respuesta);

        $pasarela = Pasarela::where('id_evento', config('app.id_evento'))->where('codigo_tipo_pago', 'niubiz_tarjeta')->first();
        $niubiz = new Niubiz;

        // dd($filtered_response);
        // --- BLOQUE DE ERROR O DENEGADO ---
        if (isset($filtered_response['errorcode']) || is_null($filtered_response['transactionId']) || $filtered_response['transactionId'] == "") {


            $niubiz->num_orden = $order;
            $niubiz->codigo_tipo_pago = 'niubiz_tarjeta';

            // Importante: Guardamos el ACTION_CODE para mostrarlo en la vista de error
            $niubiz->estado = isset($filtered_response['ACTION_CODE']) ? $filtered_response['ACTION_CODE'] : ($filtered_response['errorcode'] ?? '666');

            $niubiz->monto = $facturacion->total;
            $niubiz->id_evento = config('app.id_evento');
            $niubiz->id_pasarela = $pasarela->id;
            $niubiz->id_compra = $cuota->id;

            // Detalle del error literal de Niubiz
            $niubiz->detalle = $filtered_response['ACTION_DESCRIPTION'] ?? "Transaction declined";

            $niubiz->fecha = date('Y-m-d');
            $niubiz->hora = date('H:i:s');
            $niubiz->save();

            return redirect('/pago/error/' . $niubiz->id);
        } else {
            // --- BLOQUE DE ÉXITO ---
            $niubiz->num_orden = $order;
            $niubiz->card_num = $filtered_response['CARD'] ?? '****';
            $niubiz->idtransaccion = $filtered_response['transactionId'];
            $niubiz->id_compra = $cuota->id;
            $niubiz->fecha = $filtered_response['date'] ?? date('Y-m-d');
            $niubiz->hora = $filtered_response['time'] ?? date('H:i:s');
            $niubiz->monto = $facturacion->total;

            // Capturar AUTHORIZATION CODE de la respuesta original
            $res_original = json_decode($respuesta);
            // Lo guardamos en 'detalle' para que la pantalla de confirmación lo muestre
            $niubiz->detalle = $res_original->dataMap->AUTHORIZATION_CODE ?? '000000';

            $niubiz->id_evento = config('app.id_evento');
            $niubiz->id_pasarela = $pasarela->id;
            $niubiz->codigo_tipo_pago = 'niubiz_tarjeta';
            // --- NUEVO: GUARDAR MARCA Y TIPO DE TARJETA ---
            $niubiz->marca_tarjeta = $res_original->dataMap->BRAND ?? null;       // Retorna "visa", "mastercard", etc.
            $niubiz->tipo_tarjeta  = $res_original->dataMap->CARD_TYPE ?? null;   // Retorna "C" (Crédito) o "D" (Débito)
            $niubiz->estado = 'pagado';
            $niubiz->save();

            // Actualización de Cuota
            $cuota->informacion = json_encode([
                "cuota" => "1",
                "valor" => (string)$facturacion->total,
                "porcentaje" => "100",
                "estado_pago" => true
            ]);
            $cuota->estado_pago = 'PAGADO';
            $cuota->update();

            // Actualización de Inscripción
            $inscripcion = Inscripcion::where('id_facturacion', $facturacion->id)->first();
            $inscripcion->observacion = "Pagada Niubiz ID: " . $niubiz->id;
            $inscripcion->update();

            $cupon = null;
            // =======================================================
            if ($inscripcion->id_cupon) {
                // Buscamos el cupón por ID
                $cupon = \App\Models\Cupon::find($inscripcion->id_cupon);

                if ($cupon) {
                    // Aumentamos los usos actuales
                    $cupon->increment('usos_actuales');

                    // Opcional: Si quieres que el límite de usos disminuya (aunque usualmente se compara usos_actuales vs limite)
                    // $cupon->decrement('limite_usos');

                    Log::info("Cupón ID {$cupon->id} actualizado. Usos actuales: {$cupon->usos_actuales}");

                    // Opcional: Desactivar cupón si llegó al límite automáticamente
                    if ($cupon->usos_actuales >= $cupon->limite_usos) {
                        $cupon->save();
                    }
                }
            }

            $persona = Persona::find($inscripcion->id_persona);



            // dd('LLEGÓ A GENERAR EL SERVICIO WMC', [
            //     'FACTURACION' => $facturacion,
            //     'PERSONA' => $persona,
            //     'INSCRIPCION' => $inscripcion,
            //     'NIUBIZ' => $niubiz
            // ]);


            $service_wmc = app(\App\Http\Controllers\WebServiceController::class)
                ->wsInscripcion_WMC_2026($facturacion, $persona, $inscripcion, $niubiz, $cupon);
            //  dd($service_wmc);

            // $service_wmc->Response->Status = false;
            if (isset($service_wmc->Response) && $service_wmc->Response->Status === true) {
                $inscripcion->qr = (string)$service_wmc->Response->QR;
                $inscripcion->ws_status = true; // Campo nuevo
                if ($inscripcion->id_categoria_inscripcion !== null) {
                    $inscripcion->cupon_viaje = (string)$service_wmc->Response->Codigo;
                } else {
                    $inscripcion->cupon_viaje = null; // Forzamos null en BD para viajes
                }
            } else {
                // Si falla el servicio, registramos el error pero no matamos el proceso
                $inscripcion->ws_status = false;
                Log::error("ERROR SIE WMC para Inscripcion ID: " . $inscripcion->id, (array)$service_wmc);
            }

            // WS DE MULTIEVENTOS
            try {
                // Validamos que el ID de categoría no sea nulo o vacío antes de proceder
                if (!empty($inscripcion->id_categoria_inscripcion)) {

                    // 1. Guardamos lo que nos responde tu función en una variable
                    $wsResponse = app(\App\Http\Controllers\WebServiceController::class)
                        ->wsMultieventos_WMC($facturacion, $persona, $inscripcion, $niubiz);

                    // 2. Evaluamos si el Web Service reportó un error (400, 401, 409, etc.)
                    if (isset($wsResponse['success']) && $wsResponse['success'] === false) {
                        Log::error("Error API WS Multieventos (Inscripción ID: {$inscripcion->id}) -> Código: {$wsResponse['code']} | Detalle: {$wsResponse['message']}");
                    } else {
                        // Si todo fue bien (200 o 201)
                        Log::info("Éxito WS Multieventos (Inscripción ID: {$inscripcion->id}) -> " . ($wsResponse['message'] ?? 'Procesado correctamente'));
                    }
                } else {
                    // Opcional: Registrar en el log que se saltó el envío por falta de categoría
                    Log::warning("WS Multieventos omitido: id_categoria_inscripcion está vacío para la Inscripción ID: {$inscripcion->id}");
                }
            } catch (\Exception $e) {
                // 3. Este catch atrapará errores críticos (ej. fallo interno de PHP o caída de cURL)
                Log::error("Excepción Crítica en WS Multieventos (Inscripción ID: {$inscripcion->id}) -> Error: " . $e->getMessage());
            }

            $inscripcion->save();

            if ($inscripcion->id_categoria_inscripcion == null) {
                $inscripcion->id_categoria_inscripcion = $inscripcion->id_perfil;
            }
            // Guardamos los cambios (ya sea que tenga QR o que solo guardemos el status false)
            // $inscripcion->save();

            // ENVIAR CORREO SIEMPRE
            try {
                // El Mailable debe estar preparado para recibir un $inscripcion->qr nulo
                Mail::to($persona->correo)->send(new \App\Mail\MailInscripcion($inscripcion, $niubiz));
            } catch (\Exception $e) {
                Log::error("Error enviando correo: " . $e->getMessage());
            }

            return redirect('/pago/confirmar/' . $inscripcion->id);
        }
    }

    public function confirmPayment($id)
    {

        $inscripcion = Inscripcion::find($id);
        $categoria = $inscripcion->categoria_inscripcion;
        $facturacion = $inscripcion->facturacion;
        $persona = $inscripcion->persona;
        $persona->nombre_completo = trim($persona->nombres . " " . $persona->apellido_paterno . " " . $persona->apellido_materno);
        $documento_persona = $persona->tipoDocumento;
        $documento_empresa = $facturacion->tipoDocumentoFacturador;
        $tipo_doc_pago = $facturacion->tipoDocumentoPago;
        $tipo_pago = $facturacion->tipoPago;
        $cuota = $facturacion->cuotas->first();

        if ($facturacion->id_tipo_pago == 3) { //tarjeta
            $pago = Niubiz::where('id_compra', $cuota->id)->first();
            $pago->digitos = substr($pago->card_num, -8);
        }

        return Inertia::render('Inscripcion/Confirmacion', compact('facturacion', 'pago', 'persona', 'categoria', 'documento_persona', 'documento_empresa', 'tipo_doc_pago', 'tipo_pago'));
    }
}
