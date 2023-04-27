<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Settlement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'key',
        'zip_code',
        'name',
        'zone_type',
        'settlement_type_id'
    ];

    public function settlement_type(): belongsTo
    {
        return $this->belongsTo(SettlementType::class);
    }
}
