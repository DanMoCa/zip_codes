<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FederalEntity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'key',
        'name',
        'code'
    ];


}
