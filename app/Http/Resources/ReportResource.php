<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Shapes a Report exactly like the JSON contract the React app expects. */
class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        return [
            'id' => $r->id,
            'platform' => $r->platform,
            'programType' => $r->program_type,
            'bounty' => $r->bounty,
            'asset' => $r->asset,
            'weakness' => $r->weakness,
            'title' => $r->title,
            'summary' => $r->summary,
            'severity' => $r->severity,
            'stepsToReproduce' => $r->steps_to_reproduce,
            'request' => $r->request,
            'response' => $r->response,
            'references' => $r->supporting_references,
            'endpoint' => $r->endpoint,
            'impact' => $r->impact,
            'recommendation' => $r->recommendation,
            'ar' => $r->ar,
            'published' => (bool) $r->published,
            // Only present when the controller loaded them (detail views) / counted them (lists).
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'attachmentsCount' => $this->when(isset($r->attachments_count), fn () => (int) $r->attachments_count),
        ];
    }
}
