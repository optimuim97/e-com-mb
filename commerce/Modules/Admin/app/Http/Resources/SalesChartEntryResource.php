<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesChartEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'date'    => $this->date,
            'revenue' => (float) $this->revenue,
            'orders'  => (int) $this->orders,
        ];
    }
}
