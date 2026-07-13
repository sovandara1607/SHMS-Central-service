<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateLabReportDocumentJob;
use App\Models\LabReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Synchronous half of the File & Document Processor: the Application Server
 * calls this over REST when it needs an immediate answer (status check, or
 * a manual "regenerate PDF" action a staff member is actively waiting on).
 * Routine generation on result-entry stays async over the Redis bus — see
 * RelayBusJobs / GenerateLabReportDocumentJob.
 */
class LabReportApiController extends Controller
{
    public function status(string $labReportId): JsonResponse
    {
        $report = LabReport::find($labReportId);

        if (! $report) {
            return response()->json(['message' => 'Lab report not found'], 404);
        }

        return response()->json([
            'lab_report_id' => $report->lab_report_id,
            'ready' => (bool) $report->report_file_path,
            'report_file_path' => $report->report_file_path,
        ]);
    }

    public function regenerate(string $labReportId): JsonResponse
    {
        $report = LabReport::find($labReportId);

        if (! $report) {
            return response()->json(['message' => 'Lab report not found'], 404);
        }

        $result = DB::connection('pgsql')->table('lab_test_result')
            ->where('test_order_id', $report->test_order_id)
            ->latest('entered_at')
            ->first();

        if (! $result) {
            return response()->json(['message' => 'No lab test result found for this report'], 422);
        }

        (new GenerateLabReportDocumentJob(
            labReportId: $report->lab_report_id,
            testOrderId: $report->test_order_id,
            resultValue: $result->result_value,
            resultStatus: $result->result_status,
            remarks: $result->remarks,
            enteredByStaffId: $result->entered_by,
            createdAt: now()->toIso8601String(),
        ))->handle();

        return response()->json([
            'lab_report_id' => $report->lab_report_id,
            'report_file_path' => $report->refresh()->report_file_path,
        ]);
    }
}
