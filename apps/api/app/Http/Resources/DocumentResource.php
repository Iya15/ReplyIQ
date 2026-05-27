<?php

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Document $doc */
        $doc = $this->resource;

        return [
            'id' => $doc->id,
            'title' => $doc->title,
            'source_type' => $doc->source_type->value,
            'status' => $doc->status->value,
            'error_message' => $doc->error_message,
            'char_count' => $doc->char_count,
            'chunk_count' => $doc->chunk_count,
            'created_at' => $doc->created_at,
            'processed_at' => $doc->processed_at,
        ];
    }
}
