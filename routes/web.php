<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\InscripcionController;
use App\Http\Controllers\DocumentosController;
use App\Http\Controllers\DocumentApiController;
use App\Http\Controllers\PadreController;
use App\Http\Controllers\NiubizController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Asociados\AsociadosController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\CuponController;
use App\Http\Controllers\Admin\InscritosController;
use App\Http\Controllers\Admin\MultieventoController;
use App\Http\Controllers\Admin\SieController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/


// --- RUTAS DE ADMINISTRACIÓN ---
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/index', [AdminController::class, 'index'])->name('admin.index');
    // Rutas para el CRUD de Roles
    Route::get('/admin/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::post('/admin/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::put('/admin/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/admin/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

    Route::resource('usuarios', UserController::class)->except(['create', 'show', 'edit']);
    Route::resource('cupones', CuponController::class)->except(['create', 'edit']);
    Route::get('/inscritos', [InscritosController::class, 'index'])->name('inscritos.index');
    Route::get('/cupones/{cupone}', [CuponController::class, 'show'])->name('cupones.show');
    Route::get('/cupones/{cupon}/usos', [CuponController::class, 'getUsos'])->name('cupones.usos');

     // Envío Masivo a la API
    Route::get('/multieventos/inscritos', [MultieventoController::class, 'index'])->name('multieventos.index');
    Route::post('/multieventos/enviar-api', [MultieventoController::class, 'enviarApi'])->name('multieventos.enviar-api');

      // Envío Masivo  SIE
    Route::get('/sie/inscritos', [SieController::class, 'index'])->name('sie.index');
    Route::post('/sie/enviar-api', [SieController::class, 'enviarApi'])->name('sie.enviar-api');
});

// --- RUTAS DE ASOCIADOS ---
Route::middleware(['auth', 'role:asociado|admin'])->group(function () {
    Route::get('/asociados/index', [AsociadosController::class, 'index'])->name('asociados.index');
    Route::resource('cupones', CuponController::class)->except(['create', 'edit']);
    Route::get('/inscritos', [InscritosController::class, 'index'])->name('inscritos.index');
    Route::get('/cupones/{cupone}', [CuponController::class, 'show'])->name('cupones.show');
    Route::get('/cupones/{cupon}/usos', [CuponController::class, 'getUsos'])->name('cupones.usos');
});

// --- RUTAS PÚBLICAS / INSCRIPCIÓN ---
Route::get('/', [InscripcionController::class, 'index'])->name('inscripcion.index');

Route::post('/padre/departamentos', [PadreController::class, 'getDepartamentos'])->name('padre.departamentos');
Route::post('/padre/provincias', [PadreController::class, 'getProvincias'])->name('padre.provincias');
Route::post('/padre/distritos', [PadreController::class, 'getDistritos'])->name('padre.distritos');

Route::get('/registro/autor', [InscripcionController::class, 'autor'])->name('inscripcion.autor');
Route::get('/registro/participante', [InscripcionController::class, 'participante'])->name('inscripcion.participante');
Route::get('/registro/cursosviajes', [InscripcionController::class, 'cursosViajes'])->name('inscripcion.cursosviajes');

Route::get('/acompanante', [InscripcionController::class, 'acompanante'])->name('inscripcion.acompanante');

// --- PASARELA Y PAGOS ---
Route::post('/pago/getform', [InscripcionController::class, 'getForm'])->name('niubiz.getform');
Route::post('/pago/getform/niubiz/{id}/{order}', [InscripcionController::class, 'niubizPayment'])->where('id','[0-9]+');
Route::get('/pago/confirmar/{id}', [InscripcionController::class, 'confirmPayment'])->name('inscripcion.extemin')->where('id','[0-9]+');
Route::get('/pago/error/{id}', [PadreController::class, 'geterror'])->name('pago.geterror');

// --- API Y VALIDACIONES ---
Route::post('/api/validatepersonsoc', [DocumentApiController::class, 'validatePersonSoc'])->name('api.validatepersonsoc');
Route::post('/api/getperson', [DocumentApiController::class, 'getPersonData'])->name('api.getdataperson');
Route::post('/api/getempresa', [DocumentApiController::class, 'getEmpresaData'])->name('api.getempresa');
Route::post('/api/document', [DocumentApiController::class, 'getData'])->name('api.document');

// --- NIUBIZ TEST ---
Route::get('/niubiz/test', [NiubizController::class, 'index'])->name('niubiz.payment');
Route::post('/niubiz-respuesta', function () {
    return response()->noContent();
})->name('niubiz.dummy');

// --- RUTAS DE PERFIL (ESTAS SON LAS QUE FALTABAN) ---
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
