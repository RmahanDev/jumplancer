<?php

namespace App\Http\Resources;

use App\Models\LearningContent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LearningContent
 */
class LearningContentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content_type' => $this->content_type->value,
            'audience' => $this->audience->value,
            'purpose' => $this->purpose->value,
            'body' => $this->body,
            'media_url' => $this->media_url,
            'is_published' => $this->is_published,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name] : null),
            'author' => $this->whenLoaded('author', fn () => UserResource::summary($this->author)),
        ];
    }
}
