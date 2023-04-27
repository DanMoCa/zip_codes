<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreZipCodeRequest;
use App\Http\Requests\UpdateZipCodeRequest;
use App\Http\Resources\ZipCodeResource;
use App\Models\ZipCode;

class ZipCodeController extends Controller
{
    public function __invoke(ZipCode $zip_code)
    {
        $zip_code = $zip_code->loadMissing(['settlements','municipality','federal_entity','settlements.settlement_type']);
        return ZipCodeResource::make($zip_code);
    }
}
