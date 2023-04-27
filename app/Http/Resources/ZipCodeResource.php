<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZipCodeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'zip_code' => $this->zip_code,
            'locality' =>  $this->locality,
            'federal_entity' => FederalEntityResource::make($this->federal_entity),
            'settlements' => SettlementResource::collection($this->settlements),
            'municipality' => MunicipalityResource::make($this->municipality)
        ];
    }
}
