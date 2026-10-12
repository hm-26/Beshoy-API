<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Public, read-only. Drafts (published = false) are never exposed here. */
class ReportController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ReportResource::collection(Report::published()->withCount('attachments')->orderBy('id')->get());
    }

    public function show(int $id): ReportResource
    {
        return new ReportResource(Report::published()->with('attachments')->findOrFail($id));
    }
}
