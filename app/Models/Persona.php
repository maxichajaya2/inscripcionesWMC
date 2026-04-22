<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Persona extends Model
{
    use HasFactory;

    protected $connection = "pgsql";
    protected $table = "persona";

    protected $fillable = [
        'sie_code',
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'id_tipo_documento',
        'documento',
        // 'documento_hash',
        'sexo',
        'fecha_nacimiento',
        'correo',
        // 'correo_hash',
        'celular',
        // 'celular_hash',
        'id_ocupacion',
        'id_direccion',
        'id_nacionalidad',
        'id_empresa',
        'isactive',
    ];

    // 1. ENCRIPTACIÓN AUTOMÁTICA
    // protected $casts = [
    //     'documento' => 'encrypted',
    //     'correo'    => 'encrypted',
    //     'celular'   => 'encrypted',
    // ];


    // protected static function booted()
    // {
    //     static::saving(function ($persona) {
    //         $key = config('app.key');

    //         // Lista de campos que necesitan Hash
    //         $campos = ['documento', 'correo', 'celular'];

    //         foreach ($campos as $campo) {
    //             // Si el campo cambió o es nuevo, regeneramos su hash
    //             if ($persona->isDirty($campo) && !empty($persona->$campo)) {
    //                 $campoHash = $campo . '_hash'; // ej: correo_hash
    //                 $persona->$campoHash = hash_hmac('sha256', $persona->$campo, $key);
    //             }
    //         }
    //     });
    // }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class, 'id_tipo_documento');
    }

    public function direccion(): BelongsTo
    {
        return $this->belongsTo(Direccion::class, "id_direccion", "id");
    }

    public function ocupacion(): BelongsTo
    {
        return $this->belongsTo(Ocupacion::class, "id_ocupacion", "id");
    }
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, "id_empresa", "id");
    }

    public function nacionalidad(): BelongsTo
    {
        // Asumiendo que tu modelo para la tabla pais se llama "Pais"
        return $this->belongsTo(Pais::class, 'id_nacionalidad', 'id');
    }
}
