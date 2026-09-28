<?php

namespace Modules\Blog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'title'            => $this->title,
            'slug'             => $this->slug,
            'excerpt'          => $this->excerpt,
            'content'          => $this->when($request->routeIs('blog.show', 'admin.blog.posts.*'), $this->content),
            'cover_image'      => $this->cover_url,
            'video_url'        => $this->video_url,
            'status'           => $this->status,
            'published_at'     => $this->published_at,
            'reading_time'     => $this->reading_time,
            'meta_title'       => $this->meta_title,
            'meta_description' => $this->meta_description,
            'category'         => new PostCategoryResource($this->whenLoaded('category')),
            'author'           => $this->whenLoaded('author', fn() => [
                'id'   => $this->author->id,
                'name' => $this->author->name,
            ]),
            'created_at'       => $this->created_at,
        ];
    }
}
