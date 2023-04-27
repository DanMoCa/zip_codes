<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Municipality extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'key',
        'federal_entity_key',
        'name'
    ];
}
