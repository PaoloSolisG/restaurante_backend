<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'productos';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'id_categoria',
        'precio',
        'imagen',
        'activo'
    ];

    protected $appends = ['imagen_url'];

    public function getImagenUrlAttribute(): ?string
    {
        if (!$this->imagen) return null;
        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->imagen);
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria');
    }
}
