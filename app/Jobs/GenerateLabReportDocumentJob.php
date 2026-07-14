<?php

namespace App\Jobs;

use App\Models\HospitalSetting;
use App\Models\LabReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * File & Document Processor: renders the lab report PDF and uploads it to
 * the documents disk (Cloudflare R2, or local in dev), mirrors the result
 * snapshot into MongoDB (lab_report_documents), and records the file path
 * back onto the shared lab_report row.
 */
class GenerateLabReportDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        private readonly string $labReportId,
        private readonly string $testOrderId,
        private readonly string $resultValue,
        private readonly string $resultStatus,
        private readonly ?string $remarks,
        private readonly ?string $enteredByStaffId,
        private readonly string $createdAt,
    ) {}

    public function handle(): void
    {
        DB::connection('mongodb')->table('lab_report_documents')->insert([
            'test_order_id' => $this->testOrderId,
            'result_value'  => $this->resultValue,
            'result_status' => $this->resultStatus,
            'remarks'       => $this->remarks,
            'entered_by'    => $this->enteredByStaffId,
            'created_at'    => $this->createdAt,
        ]);

        $order = DB::connection('pgsql')->table('lab_test_order as o')
            ->join('patient as p', 'p.patient_id', '=', 'o.patient_id')
            ->join('doctor as d', 'd.doctor_id', '=', 'o.doctor_id')
            ->join('staff as ds', 'ds.staff_id', '=', 'd.staff_id')
            ->leftJoin('lab_technician as t', 't.technician_id', '=', 'o.technician_id')
            ->leftJoin('staff as ts', 'ts.staff_id', '=', 't.staff_id')
            ->where('o.test_order_id', $this->testOrderId)
            ->selectRaw("o.*,
                (p.first_name||' '||p.last_name) as patient_name, p.gender as patient_gender, p.date_of_birth as patient_dob,
                (ds.first_name||' '||ds.last_name) as doctor_name,
                (ts.first_name||' '||ts.last_name) as technician_name")
            ->first();

        $pdf = Pdf::loadView('pdf.lab-report', [
            'hospital' => HospitalSetting::current(),
            'order' => $order,
            'labReportId' => $this->labReportId,
            'resultValue' => $this->resultValue,
            'resultStatus' => $this->resultStatus,
            'remarks' => $this->remarks,
            'enteredAt' => $this->createdAt,
            'generatedAt' => $this->createdAt,
        ]);

        $path = "lab-reports/{$this->testOrderId}.pdf";
        Storage::disk(config('filesystems.documents'))->put($path, $pdf->output());

        LabReport::where('lab_report_id', $this->labReportId)->update(['report_file_path' => $path]);
    }
}
