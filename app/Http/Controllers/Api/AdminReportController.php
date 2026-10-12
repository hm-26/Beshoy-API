<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AdminReportController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ReportResource::collection(Report::orderBy('id')->get()); // includes drafts
    }

    public function show(Report $report): ReportResource
    {
        return new ReportResource($report);
    }

    public function store(ReportRequest $request): JsonResponse
    {
        $report = Report::create($request->toModelAttributes());

        return (new ReportResource($report->fresh()))->response()->setStatusCode(201);
    }

    public function update(ReportRequest $request, Report $report): ReportResource
    {
        $report->update($request->toModelAttributes());

        return new ReportResource($report->fresh());
    }

    public function destroy(Report $report): Response
    {
        $report->delete();

        return response()->noContent();
    }
}
