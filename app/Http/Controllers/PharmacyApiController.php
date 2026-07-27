<?php

namespace App\Http\Controllers;

use App\Http\Requests\DispenseRequest;
use App\Http\Requests\StoreMedicineBatchRequest;
use App\Http\Requests\StoreMedicineRequest;
use App\Http\Requests\UpdateMedicineBatchRequest;
use App\Models\DispensingItem;
use App\Models\DispensingRecord;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PharmacyApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $medicines = Medicine::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . $q . '%';
                $query->where('medicine_id', 'ilike', $like)
                    ->orWhere('medicine_name', 'ilike', $like)
                    ->orWhere('manufacturer', 'ilike', $like);
            })
            ->orderBy('medicine_name')
            ->paginate((int) $request->query('inventory_per_page', 20), ['*'], 'inventory_page', (int) $request->query('inventory_page', 1));

        $batches = DB::table('medicine_batch as b')
            ->join('medicine as m', 'm.medicine_id', '=', 'b.medicine_id')
            ->orderBy('b.expiry_date')
            ->selectRaw('b.*, m.medicine_name')
            ->paginate((int) $request->query('batches_per_page', 20), ['*'], 'batches_page', (int) $request->query('batches_page', 1));

        $prescriptions = DB::table('prescription as pr')
            ->join('patient as p', 'p.patient_id', '=', 'pr.patient_id')
            ->join('doctor as d', 'd.doctor_id', '=', 'pr.doctor_id')
            ->join('staff as s', 's.staff_id', '=', 'd.staff_id')
            ->orderByDesc('pr.prescription_date')
            ->selectRaw("pr.*, (p.first_name||' '||p.last_name) as patient_name, (s.first_name||' '||s.last_name) as doctor_name")
            ->paginate((int) $request->query('prescriptions_per_page', 20), ['*'], 'prescriptions_page', (int) $request->query('prescriptions_page', 1));

        $dispensingRecords = DB::table('dispensing_record as dr')
            ->join('patient as p', 'p.patient_id', '=', 'dr.patient_id')
            ->leftJoin('pharmacist as ph', 'ph.pharmacist_id', '=', 'dr.pharmacist_id')
            ->leftJoin('staff as s', 's.staff_id', '=', 'ph.staff_id')
            ->orderByDesc('dr.dispensing_date')
            ->selectRaw("dr.*, (p.first_name||' '||p.last_name) as patient_name, (s.first_name||' '||s.last_name) as pharmacist_name")
            ->paginate((int) $request->query('dispensing_per_page', 20), ['*'], 'dispensing_page', (int) $request->query('dispensing_page', 1));

        $stats = [
            'total'   => Medicine::count(),
            'available' => Medicine::where('status', 'available')->count(),
            'low_stock' => Medicine::where('stock_quantity', '<=', 20)->count(),
            'expired_batches' => MedicineBatch::where('status', 'expired')->count(),
        ];

        return response()->json([
            'medicines' => $this->paginated($medicines),
            'batches' => $this->paginated($batches),
            'prescriptions' => $this->paginated($prescriptions),
            'dispensing_records' => $this->paginated($dispensingRecords),
            'stats' => $stats,
        ]);
    }

    public function storeMedicine(StoreMedicineRequest $request): JsonResponse
    {
        $medicine = Medicine::create($request->validated());

        return response()->json($medicine->fresh(), 201);
    }

    /** Unpaginated list, for dropdowns (batch form, dispensing form). */
    public function listAllMedicines(): JsonResponse
    {
        return response()->json(Medicine::orderBy('medicine_name')->get());
    }

    public function listBatches(): JsonResponse
    {
        $batches = DB::table('medicine_batch as b')
            ->join('medicine as m', 'm.medicine_id', '=', 'b.medicine_id')
            ->orderBy('b.expiry_date')
            ->selectRaw('b.*, m.medicine_name')->get();

        $expiring = DB::table('medicine_batch as b')
            ->join('medicine as m', 'm.medicine_id', '=', 'b.medicine_id')
            ->whereRaw("b.expiry_date <= (CURRENT_DATE + interval '30 day')")
            ->where('b.status', 'valid')
            ->orderBy('b.expiry_date')
            ->selectRaw('b.*, m.medicine_name')->get();

        return response()->json(['batches' => $batches, 'expiring' => $expiring]);
    }

    public function storeBatch(StoreMedicineBatchRequest $request): JsonResponse
    {
        $batch = MedicineBatch::create($request->validated());

        return response()->json($batch->fresh(), 201);
    }

    public function showBatch(string $id): JsonResponse
    {
        $batch = MedicineBatch::find($id);
        if (! $batch) {
            return response()->json(['message' => 'Batch not found'], 404);
        }
        $medicine = Medicine::find($batch->medicine_id);

        return response()->json(['batch' => $batch, 'medicine' => $medicine]);
    }

    public function updateBatch(UpdateMedicineBatchRequest $request, string $id): JsonResponse
    {
        $batch = MedicineBatch::find($id);
        if (! $batch) {
            return response()->json(['message' => 'Batch not found'], 404);
        }
        $batch->update($request->validated());

        return response()->json($batch->fresh());
    }

    public function dispensingPage(Request $request): JsonResponse
    {
        $records = DB::table('dispensing_record as dr')
            ->join('patient as p', 'p.patient_id', '=', 'dr.patient_id')
            ->orderByDesc('dr.dispensing_date')
            ->selectRaw("dr.*, (p.first_name||' '||p.last_name) as patient_name")
            ->paginate((int) $request->query('per_page', 20), ['*'], 'page', (int) $request->query('page', 1));

        $prescriptions = DB::table('prescription as pr')
            ->join('patient as p', 'p.patient_id', '=', 'pr.patient_id')
            ->orderByDesc('pr.prescription_date')
            ->selectRaw("pr.prescription_id, pr.patient_id, (p.first_name||' '||p.last_name) as patient_name")
            ->limit(100)->get();

        $medicines = Medicine::orderBy('medicine_name')->get();

        return response()->json([
            'records' => $this->paginated($records),
            'prescriptions' => $prescriptions,
            'medicines' => $medicines,
        ]);
    }

    public function showPrescription(string $id): JsonResponse
    {
        $prescription = DB::table('prescription as pr')
            ->join('patient as p', 'p.patient_id', '=', 'pr.patient_id')
            ->join('doctor as d', 'd.doctor_id', '=', 'pr.doctor_id')
            ->join('staff as s', 's.staff_id', '=', 'd.staff_id')
            ->where('pr.prescription_id', $id)
            ->selectRaw("pr.*, (p.first_name||' '||p.last_name) as patient_name, (s.first_name||' '||s.last_name) as doctor_name")
            ->first();

        if (! $prescription) {
            return response()->json(['message' => 'Prescription not found'], 404);
        }

        $items = DB::table('prescription_item as pi')
            ->join('medicine as m', 'm.medicine_id', '=', 'pi.medicine_id')
            ->where('pi.prescription_id', $id)
            ->selectRaw('pi.*, m.medicine_name')->get();

        return response()->json(['prescription' => $prescription, 'items' => $items]);
    }

    public function dispenseFormData(string $id): JsonResponse
    {
        $prescription = DB::table('prescription as pr')
            ->join('patient as p', 'p.patient_id', '=', 'pr.patient_id')
            ->where('pr.prescription_id', $id)
            ->selectRaw("pr.*, (p.first_name||' '||p.last_name) as patient_name")
            ->first();

        if (! $prescription) {
            return response()->json(['message' => 'Prescription not found'], 404);
        }

        $items = DB::table('prescription_item as pi')
            ->join('medicine as m', 'm.medicine_id', '=', 'pi.medicine_id')
            ->where('pi.prescription_id', $id)
            ->selectRaw('pi.*, m.medicine_name')->get();

        return response()->json(['prescription' => $prescription, 'items' => $items]);
    }

    public function showDispensing(string $id): JsonResponse
    {
        $record = DB::table('dispensing_record as dr')
            ->join('patient as p', 'p.patient_id', '=', 'dr.patient_id')
            ->leftJoin('pharmacist as ph', 'ph.pharmacist_id', '=', 'dr.pharmacist_id')
            ->leftJoin('staff as s', 's.staff_id', '=', 'ph.staff_id')
            ->where('dr.dispensing_id', $id)
            ->selectRaw("dr.*, (p.first_name||' '||p.last_name) as patient_name, (s.first_name||' '||s.last_name) as pharmacist_name")
            ->first();

        if (! $record) {
            return response()->json(['message' => 'Dispensing record not found'], 404);
        }

        $items = DB::table('dispensing_item as di')
            ->join('medicine as m', 'm.medicine_id', '=', 'di.medicine_id')
            ->leftJoin('medicine_batch as b', 'b.batch_id', '=', 'di.batch_id')
            ->where('di.dispensing_id', $id)
            ->selectRaw('di.*, m.medicine_name, b.batch_number')->get();

        return response()->json(['record' => $record, 'items' => $items]);
    }

    public function dispense(DispenseRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $dispensingId = DB::transaction(function () use ($data, $request) {
                // Without this, resubmitting the dispense form (double-click,
                // back-button repost) re-dispenses the same prescription: a
                // second DispensingRecord + a second stock decrement for
                // medicine the patient already received.
                if (DispensingRecord::where('prescription_id', $data['prescription_id'])->exists()) {
                    throw new \RuntimeException('This prescription has already been dispensed.');
                }

                $items = DB::table('prescription_item')->where('prescription_id', $data['prescription_id'])->get();
                if ($items->isEmpty()) {
                    throw new \RuntimeException('This prescription has no items to dispense.');
                }

                $disp = DispensingRecord::create([
                    'prescription_id' => $data['prescription_id'],
                    'patient_id' => $data['patient_id'],
                    'pharmacist_id' => $request->input('pharmacist_id'),
                ]);

                foreach ($items as $item) {
                    $med = Medicine::lockForUpdate()->findOrFail($item->medicine_id);
                    $qty = (int) ($item->quantity ?? 0);
                    if ($qty < 1) {
                        continue;
                    }
                    if ($med->stock_quantity < $qty) {
                        throw new \RuntimeException("Insufficient stock for {$med->medicine_name}.");
                    }

                    // FEFO: earliest-expiry valid batch with enough quantity.
                    $batch = DB::table('medicine_batch')
                        ->where('medicine_id', $med->medicine_id)->where('status', 'valid')
                        ->where('quantity', '>=', $qty)
                        ->orderBy('expiry_date')->first();

                    DispensingItem::create([
                        'dispensing_id' => $disp->dispensing_id,
                        'medicine_id' => $med->medicine_id,
                        'batch_id' => $batch?->batch_id,
                        'quantity_dispensed' => $qty,
                    ]);
                    $med->decrement('stock_quantity', $qty);
                    if ($batch) {
                        DB::table('medicine_batch')->where('batch_id', $batch->batch_id)->decrement('quantity', $qty);
                    }
                }

                return $disp->dispensing_id;
            });

            return response()->json(['dispensing_id' => $dispensingId], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
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
