<?php

namespace Modules\Review\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'product_id'   => $this->product_id,
            'rating'       => $this->rating,
            'title'        => $this->title,
            'comment'      => $this->comment,
            'status'       => $this->status,
            'helpful_count'=> $this->helpful_count,
            'created_at'   => $this->created_at->format('d/m/Y'),
            'user'         => $this->whenLoaded('user', fn() => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ]),
            // Admin-only fields
            'admin_note'   => $this->when(
                $request->user()?->hasAnyRole(['admin', 'staff']),
                $this->admin_note
            ),
        ];
    }
}
