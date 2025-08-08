<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WoocommerceSyncLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'syncType' => $this->sync_type,
            'operationType' => $this->operation_type,
            'data' => $this->data,
            'details' => $this->details,
            'createdBy' => $this->user->name,
            'createdAt' => $this->created_at,
        ];
    }
}
