<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cupon;
use Illuminate\Http\Request;
use App\Models\Inscripcion;
use App\Models\Niubiz;


class CuponController extends Controller
{
    public function index()
    {
        // Traemos SOLO los cupones que no han sido "eliminados" (is_delete = false)
        // y los ordenamos por el más reciente
        // $cupones = Cupon::where('is_delete', false)->latest()->get();

        // $personas= Cupon::with(['inscritos.persona'])->get();

        $cupones = Cupon::with(['inscritos.persona.tipoDocumento']) // <--- Agregamos .tipoDocumento
            ->where('is_delete', false)
            ->latest()
            ->get();

        return inertia('Admin/Cupones/Index', [
            'cupones' => $cupones
        ]);
    }

    public function show(Request $request, Cupon $cupone)
    {
        // =========================================================================
        // PASO 1: OBTENER SOLO LOS IDs VÁLIDOS (PAGADOS) EVITANDO EL ERROR CROSS-DB
        // =========================================================================
        $todasInscripciones = Inscripcion::where('id_cupon', $cupone->id)
            ->with(['facturacion.cuotas.niubiz' => function ($query) {
                // Pre-filtramos Niubiz
                $query->where('estado', 'pagado')->where('id_evento', 5);
            }])
            ->get();

        // Aplicamos TU filtro en memoria para obtener un arreglo puro de IDs pagados
        $idsPagados = $todasInscripciones->filter(function ($inscripcion) {
            if (!$inscripcion->facturacion || !$inscripcion->facturacion->cuotas) {
                return false;
            }
            return $inscripcion->facturacion->cuotas->contains(fn($c) => $c->niubiz !== null);
        })->pluck('id'); // Extraemos solo los IDs, ej: [14, 25, 33]


        // =========================================================================
        // PASO 2: CREAR LA CONSULTA BASE USANDO SOLO LOS IDs VÁLIDOS
        // =========================================================================
        $queryBase = Inscripcion::whereIn('id', $idsPagados)
            ->with([
                'persona.tipoDocumento',
                'facturacion.tipoDocumentoFacturador',
                'cupon',
                'facturacion.cuotas.niubiz'
            ])
            ->when($request->search, function ($query, $search) {
                $query->whereHas('persona', function ($q) use ($search) {
                    $q->where('nombres', 'like', "%{$search}%")
                        ->orWhere('documento', 'like', "%{$search}%");
                });
            })
            ->orderBy('id', 'desc');


        // =========================================================================
        // PASO 3: DISTRIBUIR LA DATA PARA VUE (TABLA PAGINADA Y MODAL)
        // =========================================================================

        // A) Para la tabla principal (con paginación)
        // Usamos "clone" para no afectar la consulta base
        $inscritos = (clone $queryBase)->paginate(15)->withQueryString();

        // B) Para el Modal de detalles (Mapeo de datos completos)
        $inscripciones = $queryBase->get()->map(function ($inscripcion) {
            $cuotaPagada = $inscripcion->facturacion?->cuotas->first(fn($c) => $c->niubiz !== null);

            return [
                'id' => $inscripcion->id,
                'fecha_registro' => $inscripcion->created_at ? $inscripcion->created_at->format('d/m/Y H:i') : '-',
                'estado_inscripcion' => $inscripcion->isactive,
                'origen' => $inscripcion->origen ?? 'Web',
                'cargo' => $inscripcion->texto_cargo ?? 'No especificado',
                'qr'=> $inscripcion->qr ?? null,
                'cupon_viaje' => $inscripcion->cupon_viaje ?? null,

                // Datos Completos de Persona
                'persona' => $inscripcion->persona,
                'nombres' => trim(($inscripcion->persona?->nombres ?? '') . ' ' . ($inscripcion->persona?->apellidos ?? '')),
                'documento' => $inscripcion->persona?->dni ?? $inscripcion->persona?->documento ?? '-',
                'tipo_documento' => $inscripcion->persona?->tipoDocumento?->name_es ?? 'Sin tipo',
                'email' => $inscripcion->persona?->correo ?? 'Sin correo',

                // Datos de Facturación
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

                'categoria_cursos_viajes' => !empty($inscripcion->id_categoria_cursos_viajes)
                    ? \App\Models\CategoriaCursoViaje::whereIn('id', $inscripcion->id_categoria_cursos_viajes)
                    ->get(['nombre_es', 'tipo'])
                    ->toArray()
                    : [],

                'cupon' => $inscripcion->cupon ? [
                    'codigo' => $inscripcion->cupon->codigo_cupon,
                    'razon_social' => $inscripcion->cupon->razon_social ?? 'Empresa no registrada'
                ] : null,

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

                'estado_pago' => 'PAGADO',
            ];
        })->values();

        return inertia('Admin/Cupones/Detalles', [
            'cupon' => $cupone,
            'inscritos' => $inscritos,
            'inscripciones' => $inscripciones,
            'filters' => $request->only(['search'])
        ]);
    }

    public function store(Request $request)
    {
        // dd($request->all());
        $validated = $request->validate([
            'codigo_cupon' => 'required|string|max:100|unique:pgsql_second.cupones,codigo_cupon',
            'tipo_descuento' => 'required|string|max:50',
            'valor' => 'required|numeric', // Cambié integer por numeric por si usas decimales (ej. porcentajes)
            'razon_social' => 'required|string|max:255', // Ajustado a required como pediste antes
            'tipo_documento' => 'required|string|max:10',
            'num_documento' => 'required|string|max:100',
            'eci_cod' => 'required|string|max:100',
            'limite_usos' => 'required|integer|min:1',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'is_active' => 'boolean',
        ]);

        $validated['usos_actuales'] = 0;
        $validated['is_active'] = $request->is_active ?? false;
        // Por defecto al crear, nos aseguramos de que no esté eliminado
        $validated['is_delete'] = false;

        Cupon::create($validated);

        return redirect()->back();
    }

    public function update(Request $request, Cupon $cupone)
    {
        $validated = $request->validate([
            'codigo_cupon' => 'required|string|max:100|unique:pgsql_second.cupones,codigo_cupon,' . $cupone->id,
            'tipo_descuento' => 'required|string|max:50',
            'valor' => 'required|numeric',
            'razon_social' => 'required|string|max:255',
            'tipo_documento' => 'required|string|max:10',
            'num_documento' => 'required|string|max:100',
            'eci_cod' => 'required|string|max:100',
            'limite_usos' => 'required|integer|min:1',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->is_active ?? false;

        $cupone->update($validated);

        return redirect()->back();
    }

    public function destroy(Cupon $cupone)
    {
        // Eliminación lógica: Cambiamos el estado de is_delete a true
        // Usamos save() directamente en lugar de update() para no lidiar con el $fillable aquí
        $cupone->is_delete = true;
        $cupone->save();

        return redirect()->back();
    }
}
