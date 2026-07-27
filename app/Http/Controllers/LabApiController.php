<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLabOrderRequest;
use App\Http\Requests\StoreLabResultRequest;
use App\Http\Requests\UpdateLabOrderStatusRequest;
use App\Http\Requests\UpdateLabResultRequest;
use App\Models\Laboratory;
use App\Models\LabReport;
use App\Models\LabTestOrder;
use App\Models\LabTestResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LabApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', '');

        $orders = DB::table('lab_test_order as o')
            ->join('patient as p', 'p.patient_id', '=', 'o.patient_id')
            ->join('doctor as d', 'd.doctor_id', '=', 'o.doctor_id')
            ->join('staff as s', 's.staff_id', '=', 'd.staff_id')
            ->leftJoin('lab_technician as t', 't.technician_id', '=', 'o.technician_id')
            ->leftJoin('staff as ts', 'ts.staff_id', '=', 't.staff_id')
            ->when($status !== '', fn ($q) => $q->where('o.status', $status))
            ->orderByDesc('o.order_date')
            ->selectRaw("o.*, (p.first_name||' '||p.last_name) as patient_name, (s.first_name||' '||s.last_name) as doctor_name, (ts.first_name||' '||ts.last_name) as technician_name")
            ->paginate((int) $request->query('orders_per_page', 20), ['*'], 'orders_page', (int) $request->query('orders_page', 1));

        $results = DB::table('lab_test_result as r')
            ->join('lab_test_order as o', 'o.test_order_id', '=', 'r.test_order_id')
            ->join('patient as p', 'p.patient_id', '=', 'o.patient_id')
            ->leftJoin('lab_technician as t', 't.technician_id', '=', 'r.entered_by')
            ->leftJoin('staff as ts', 'ts.staff_id', '=', 't.staff_id')
            ->orderByDesc('r.entered_at')
            ->selectRaw("r.*, o.test_name, o.test_order_id, (p.first_name||' '||p.last_name) as patient_name, (ts.first_name||' '||ts.last_name) as technician_name")
            ->paginate((int) $request->query('results_per_page', 20), ['*'], 'results_page', (int) $request->query('results_page', 1));

        $reports = DB::table('lab_report as lr')
            ->join('lab_test_order as o', 'o.test_order_id', '=', 'lr.test_order_id')
            ->join('patient as p', 'p.patient_id', '=', 'lr.patient_id')
            ->leftJoin('staff as s', 's.staff_id', '=', 'lr.generated_by')
            ->orderByDesc('lr.generated_at')
            ->selectRaw("lr.*, o.test_name, (p.first_name||' '||p.last_name) as patient_name, (s.first_name||' '||s.last_name) as generated_by_name")
            ->paginate((int) $request->query('reports_per_page', 20), ['*'], 'reports_page', (int) $request->query('reports_page', 1));

        $stats = [
            'pending'     => LabTestOrder::where('status', 'pending')->count(),
            'in_progress' => LabTestOrder::where('status', 'in_progress')->count(),
            'completed'   => LabTestOrder::where('status', 'completed')->count(),
            // NOT EXISTS instead of whereNotIn(...pluck()): at scale, pluck()
            // inlines every lab_test_result id as a bound parameter and blows
            // past Postgres's 65535-parameter-per-statement limit.
            'pending_results' => DB::table('lab_test_order as o')
                ->where('o.status', 'completed')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('lab_test_result as r')
                        ->whereColumn('r.test_order_id', 'o.test_order_id');
                })
                ->count(),
        ];

        return response()->json([
            'orders' => $this->paginated($orders),
            'results' => $this->paginated($results),
            'reports' => $this->paginated($reports),
            'stats' => $stats,
        ]);
    }

    public function showOrder(string $id): JsonResponse
    {
        $order = DB::table('lab_test_order as o')
            ->join('patient as p', 'p.patient_id', '=', 'o.patient_id')
            ->join('doctor as d', 'd.doctor_id', '=', 'o.doctor_id')
            ->join('staff as s', 's.staff_id', '=', 'd.staff_id')
            ->leftJoin('lab_technician as t', 't.technician_id', '=', 'o.technician_id')
            ->leftJoin('staff as ts', 'ts.staff_id', '=', 't.staff_id')
            ->where('o.test_order_id', $id)
            ->selectRaw("o.*, (p.first_name||' '||p.last_name) as patient_name, (s.first_name||' '||s.last_name) as doctor_name, (ts.first_name||' '||ts.last_name) as technician_name")
            ->first();

        if (! $order) {
            return response()->json(['message' => 'Lab order not found'], 404);
        }

        return response()->json($order);
    }

    public function storeOrder(StoreLabOrderRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['priority'], $data['notes']);

        $order = LabTestOrder::create($data);

        return response()->json($order->fresh(), 201);
    }

    public function updateOrderStatus(UpdateLabOrderStatusRequest $request, string $id): JsonResponse
    {
        $order = LabTestOrder::find($id);
        if (! $order) {
            return response()->json(['message' => 'Lab order not found'], 404);
        }

        $order->status = $request->validated('status');
        if ($tech = $request->input('resolved_technician_id')) {
            $order->technician_id = $order->technician_id ?: $tech;
        }
        $order->save();

        return response()->json($order->fresh());
    }

    public function showResult(string $id): JsonResponse
    {
        $result = DB::table('lab_test_result as r')
            ->join('lab_test_order as o', 'o.test_order_id', '=', 'r.test_order_id')
            ->join('patient as p', 'p.patient_id', '=', 'o.patient_id')
            ->where('r.test_result_id', $id)
            ->selectRaw("r.*, o.test_name, (p.first_name||' '||p.last_name) as patient_name, p.patient_id")
            ->first();

        if (! $result) {
            return response()->json(['message' => 'Lab result not found'], 404);
        }

        $order = DB::table('lab_test_order as o')
            ->join('patient as p', 'p.patient_id', '=', 'o.patient_id')
            ->join('doctor as d', 'd.doctor_id', '=', 'o.doctor_id')
            ->join('staff as s', 's.staff_id', '=', 'd.staff_id')
            ->leftJoin('lab_technician as t', 't.technician_id', '=', 'o.technician_id')
            ->leftJoin('staff as ts', 'ts.staff_id', '=', 't.staff_id')
            ->where('o.test_order_id', $result->test_order_id)
            ->selectRaw("o.*, (p.first_name||' '||p.last_name) as patient_name, (s.first_name||' '||s.last_name) as doctor_name, (ts.first_name||' '||ts.last_name) as technician_name")
            ->first();

        return response()->json([...(array) $result, 'order' => $order]);
    }

    public function updateResult(UpdateLabResultRequest $request, string $id): JsonResponse
    {
        $result = LabTestResult::find($id);
        if (! $result) {
            return response()->json(['message' => 'Lab result not found'], 404);
        }

        $result->update($request->validated());

        return response()->json($result->fresh());
    }

    public function enterResult(StoreLabResultRequest $request): JsonResponse
    {
        $data = $request->validated();

        // No unique constraint on test_order_id at the DB level, so without
        // this guard resubmitting the form (double-click, back-button repost)
        // creates a second result + a second lab report for the same order.
        if (LabTestResult::where('test_order_id', $data['test_order_id'])->exists()) {
            return response()->json(['message' => 'A result has already been entered for this order.'], 409);
        }

        $result = LabTestResult::create([
            'test_result_id' => 'LRS' . strtoupper(Str::random(8)),
            'test_order_id' => $data['test_order_id'],
            'result_value' => $data['result_value'],
            'result_status' => $data['result_status'],
            'remarks' => $data['remarks'] ?? null,
            'entered_by' => $request->input('entered_by'),
        ]);

        LabTestOrder::where('test_order_id', $data['test_order_id'])->update(['status' => 'completed']);

        // The report row itself is created synchronously (Postgres stays the
        // immediately-consistent source of truth for the Lab Reports tab).
        // The MongoDB snapshot and PDF generation/upload stay on the existing
        // async Redis-bus path — Database-final publishes that separately
        // using the lab_report_id returned here.
        $labReport = LabReport::create([
            'test_order_id' => $data['test_order_id'],
            'patient_id' => LabTestOrder::where('test_order_id', $data['test_order_id'])->value('patient_id'),
            'report_content' => $data['result_value'] . ($data['remarks'] ? " — {$data['remarks']}" : ''),
            'generated_by' => $request->input('generated_by'),
        ]);

        return response()->json([
            'test_result_id' => $result->test_result_id,
            'test_order_id' => $data['test_order_id'],
            'lab_report_id' => $labReport->lab_report_id,
        ], 201);
    }

    public function equipment(): JsonResponse
    {
        $rows = DB::table('laboratory_equipment as e')
            ->leftJoin('laboratory as l', 'l.laboratory_id', '=', 'e.laboratory_id')
            ->orderBy('e.equipment_name')
            ->selectRaw('e.*, l.laboratory_name')->get();

        return response()->json($rows);
    }

    /** Backs the lab_technician staff-form's laboratory dropdown (AdminController). */
    public function listAllLaboratories(): JsonResponse
    {
        return response()->json(Laboratory::orderBy('laboratory_name')->get());
    }

    private function paginated($paginator): array
    {
        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
            ],
        ];
    }
}
