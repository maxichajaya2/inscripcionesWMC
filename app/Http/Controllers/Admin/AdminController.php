<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Cupon;
// use Spatie\Permission\Models\Role;
use App\Models\Role;
use Illuminate\Http\Request;
use App\Models\Inscripcion;

class AdminController extends Controller
{
    public function index()
    {

        $inscripcion = Inscripcion::with(['persona', 'facturacion'])->first();

        // 1. Estadísticas Generales (Tarjetas)
        $stats = [
            'total_usuarios' => User::count(),
            'total_roles' => Role::count(),
            'cupones_activos' => Cupon::where('is_active', true)
                ->where('fecha_fin', '>', now())
                ->where('fecha_inicio', '<=', now())
                ->where('is_delete', false)
                ->count(),
            'usos_totales_cupones' => Cupon::where('is_active', true)
                ->where('is_delete', false)
                ->where('fecha_inicio', '<=', now())
                ->where('fecha_fin', '>', now())
                ->sum('usos_actuales'),
        ];

        // 2. Actividad Reciente: Últimos 5 usuarios registrados
        $ultimosUsuarios = User::with('roles')
            ->latest()
            ->take(5)
            ->get();

        // 3. Actividad Reciente: Últimos 5 cupones creados
        $ultimosCupones = Cupon::latest()
            ->where('is_active', true)
            ->where('fecha_fin', '>', now())
            ->where('fecha_inicio', '<=', now())
            ->where('is_delete', false)
            ->take(5)
            ->get();


        return inertia('Admin/Index', [
            'stats' => $stats,
            'ultimosUsuarios' => $ultimosUsuarios,
            'ultimosCupones' => $ultimosCupones,
        ]);
    }
}
