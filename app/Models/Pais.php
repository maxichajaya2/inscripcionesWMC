<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pais extends Model
{
    use HasFactory;

    protected $connection = "pgsql";
    protected $table = "pais";


    public function personas()
    {
        // Un país "tiene muchas" personas
        return $this->hasMany(Persona::class, 'id_nacionalidad', 'id');
    }
}
