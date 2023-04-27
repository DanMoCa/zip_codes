<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class ZipCode extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'zip_code',
        'locality',
        'municipality_id',
    ];

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where('zip_code', $value)->firstOrFail();
    }

    public function settlements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Settlement::class,'zip_code','zip_code');
    }

    public function federal_entity(): hasOneThrough
    {
        return $this->hasOneThrough(FederalEntity::class,Municipality::class,'id','id','municipality_id','federal_entity_id');
    }

    public function municipality(): belongsTo
    {
        return $this->belongsTo(Municipality::class,'municipality_id','id');
    }
}
