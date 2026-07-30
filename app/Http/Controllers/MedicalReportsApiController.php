<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMedicalReportRequest;
use App\Jobs\GenerateMedicalReportDocumentJob;
use App\Models\MedicalRecord;
use App\Models\MedicalReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Doctor-generated medical reports. Like Prescriptions, the create form
 * hangs off the medical record it summarizes.
 */
class MedicalReportsApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);

        $reports = MedicalReport::with('patient')
            ->orderByDesc('generated_at')
            ->paginate($perPage);

        return response()->json([
            'data' => $reports->getCollection()->map(fn (MedicalReport $r) => $this->present($r))->values(),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'total' => $reports->total(),
                'per_page' => $reports->perPage(),
            ],
        ]);
    }

    public function store(StoreMedicalReportRequest $request, string $recordId): JsonResponse
    {
        $record = MedicalRecord::findOrFail($recordId);
        $data = $request->validated();

        $report = MedicalReport::create([
            'patient_id' => $record->patient_id,
            'medical_record_id' => $record->medical_record_id,
            'report_type' => $data['report_type'],
            'report_content' => $data['report_content'],
            'generated_by' => $request->input('generated_by'),
        ]);

        // The report row above is always committed synchronously; PDF
        // rendering is normally offloaded to the queue. If Redis is down,
        // dispatch() throws before anything is queued, so fall back to
        // generating the PDF inline (same call regenerate() makes manually)
        // rather than returning a report stuck with no file and nothing
        // that will ever retry it.
        try {
            GenerateMedicalReportDocumentJob::dispatch($report->report_id);
        } catch (\RedisException $e) {
            report($e);
            (new GenerateMedicalReportDocumentJob($report->report_id))->handle();
        }

        return response()->json($this->present($report->load('patient')), 201);
    }

    public function status(string $reportId): JsonResponse
    {
        $report = MedicalReport::find($reportId);

        if (! $report) {
            return response()->json(['message' => 'Medical report not found'], 404);
        }

        return response()->json([
            'report_id' => $report->report_id,
            'ready' => (bool) $report->report_file_path,
            'report_file_path' => $report->report_file_path,
        ]);
    }

    public function regenerate(string $reportId): JsonResponse
    {
        $report = MedicalReport::find($reportId);

        if (! $report) {
            return response()->json(['message' => 'Medical report not found'], 404);
        }

        (new GenerateMedicalReportDocumentJob($report->report_id))->handle();

        return response()->json([
            'report_id' => $report->report_id,
            'report_file_path' => $report->refresh()->report_file_path,
        ]);
    }

    private function present(MedicalReport $report): array
    {
        return [
            ...$report->only([
                'report_id', 'patient_id', 'medical_record_id', 'report_type',
                'report_content', 'generated_by', 'generated_at', 'report_file_path',
            ]),
            'patient_name' => $report->patient ? trim($report->patient->first_name . ' ' . $report->patient->last_name) : null,
        ];
    }
}
