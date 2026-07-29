<?php

namespace App\Jobs;

use App\Models\HospitalSetting;
use App\Models\MedicalRecord;
use App\Models\MedicalReport;
use App\Models\Staff;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * File & Document Processor: renders the medical report PDF and uploads it
 * to the documents disk (Cloudflare R2, or local in dev), mirroring the
 * lab-report pipeline (see GenerateLabReportDocumentJob). Unlike lab
 * reports, the row itself is always created synchronously in the same
 * request by MedicalReportsApiController::store() — no bus relay needed —
 * so this only needs the report_id to load everything else fresh at the
 * moment it actually runs.
 */
class GenerateMedicalReportDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(private readonly string $reportId) {}

    public function handle(): void
    {
        $report = MedicalReport::findOrFail($this->reportId);
        $record = MedicalRecord::with(['patient', 'doctor.staff'])->find($report->medical_record_id);
        $generatedByStaff = $report->generated_by ? Staff::find($report->generated_by) : null;

        $pdf = Pdf::loadView('pdf.medical-report', [
            'hospital' => HospitalSetting::current(),
            'report' => $report,
            'patient' => $record?->patient,
            'doctorName' => $record?->doctor?->name(),
            'generatedByName' => $generatedByStaff?->fullName(),
        ]);

        $path = "medical-reports/{$report->report_id}.pdf";
        Storage::disk(config('filesystems.documents'))->put($path, $pdf->output());

        $report->update(['report_file_path' => $path]);
    }
}
