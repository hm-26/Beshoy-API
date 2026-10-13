<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Support\AutoIncrement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AdminReportController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ReportResource::collection(Report::withCount('attachments')->orderBy('id')->get());
    }

    public function show(Report $report): ReportResource
    {
        return new ReportResource($report->load('attachments'));
    }

    public function store(ReportRequest $request): JsonResponse
    {
        $report = Report::create($request->toModelAttributes());

        return (new ReportResource($report->fresh('attachments')))->response()->setStatusCode(201);
    }

    public function update(ReportRequest $request, Report $report): ReportResource
    {
        $report->update($request->toModelAttributes());

        return new ReportResource($report->fresh('attachments'));
    }

    public function destroy(Report $report): Response
    {
        $report->delete();

        // Hand freed ids back out: deleting #8 and #9 means the next report is #8, not #10.
        AutoIncrement::sync($report->getTable());

        return response()->noContent();
    }
}
