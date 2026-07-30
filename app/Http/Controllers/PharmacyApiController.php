<?php

namespace App\Http\Controllers;

use App\Http\Requests\DispenseRequest;
use App\Http\Requests\StoreMedicineBatchRequest;
use App\Http\Requests\StoreMedicineRequest;
use App\Http\Requests\UpdateMedicineBatchRequest;
use App\Http\Requests\UpdateMedicineRequest;
use App\Models\DispensingItem;
use App\Models\DispensingRecord;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Support\DropdownCache;
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

        // total/available/low_stock all scan `medicine` — one query with
        // FILTERs instead of 3 (see analysis.md §4.8).
        $medicineStats = DB::table('medicine')->selectRaw(
            "count(*) as total,
             count(*) filter (where status = 'available') as available,
             count(*) filter (where stock_quantity <= 20) as low_stock"
        )->first();

        $stats = [
            'total'   => (int) $medicineStats->total,
            'available' => (int) $medicineStats->available,
            'low_stock' => (int) $medicineStats->low_stock,
            // By actual expiry_date, not `status` — nothing in this codebase ever
            // auto-flips a batch's status to 'expired' once its date passes (it's
            // a manual dropdown in the edit form), so counting by status alone
            // silently undercounts real expired stock sitting on the shelf.
            'expired_batches' => MedicineBatch::where('expiry_date', '<', now()->toDateString())->count(),
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
        return response()->json(DropdownCache::remember('medicines', fn () => Medicine::orderBy('medicine_name')->get()));
    }

    public function showMedicine(string $id): JsonResponse
    {
        $medicine = Medicine::find($id);
        if (! $medicine) {
            return response()->json(['message' => 'Medicine not found'], 404);
        }

        return response()->json($medicine);
    }

    public function updateMedicine(UpdateMedicineRequest $request, string $id): JsonResponse
    {
        $medicine = Medicine::find($id);
        if (! $medicine) {
            return response()->json(['message' => 'Medicine not found'], 404);
        }
        $medicine->update($request->validated());

        return response()->json($medicine->fresh());
    }

    public function listBatches(): JsonResponse
    {
        $batches = DB::table('medicine_batch as b')
            ->join('medicine as m', 'm.medicine_id', '=', 'b.medicine_id')
            ->orderBy('b.expiry_date')
            ->selectRaw('b.*, m.medicine_name')->get();

        // Derived from $batches in PHP instead of a second identical join —
        // $batches already carries every column this filter needs
        // (expiry_date, status), so there's nothing the DB round trip adds.
        $expiryCutoff = now()->addDays(30)->toDateString();
        $expiring = $batches->filter(fn ($b) => $b->status === 'valid' && $b->expiry_date !== null && $b->expiry_date <= $expiryCutoff)->values();

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

        $medicines = DropdownCache::remember('medicines', fn () => Medicine::orderBy('medicine_name')->get());

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

                    // FEFO across as many valid, non-expired batches as it takes to
                    // cover the quantity — a single batch is the common case, but
                    // stock is often split (e.g. two batches of 25 when 30 are
                    // needed), and requiring one batch to hold the whole amount
                    // silently desynced batch quantities from stock_quantity: no
                    // batch matched, batch_id came back null, and stock_quantity
                    // still dropped while every batch's own quantity stayed put.
                    // status='valid' alone isn't enough to exclude expired stock —
                    // nothing in this codebase ever flips it automatically once
                    // expiry_date passes (it's a manual dropdown), so a batch can
                    // sit 'valid' long after it's actually expired. Filtering on
                    // the real date directly closes that gap regardless of
                    // whether status was ever updated.
                    $batches = DB::table('medicine_batch')
                        ->where('medicine_id', $med->medicine_id)->where('status', 'valid')
                        ->where('expiry_date', '>=', now()->toDateString())
                        ->where('quantity', '>', 0)
                        ->orderBy('expiry_date')
                        ->lockForUpdate()
                        ->get();

                    $remaining = $qty;
                    foreach ($batches as $batch) {
                        if ($remaining <= 0) {
                            break;
                        }
                        $take = min($remaining, $batch->quantity);
                        DispensingItem::create([
                            'dispensing_id' => $disp->dispensing_id,
                            'medicine_id' => $med->medicine_id,
                            'batch_id' => $batch->batch_id,
                            'quantity_dispensed' => $take,
                        ]);
                        DB::table('medicine_batch')->where('batch_id', $batch->batch_id)->decrement('quantity', $take);
                        $remaining -= $take;
                    }
                    // Whatever no tracked batch could cover (untracked/aggregate-only
                    // stock) is recorded batch-less, same fallback as before.
                    if ($remaining > 0) {
                        DispensingItem::create([
                            'dispensing_id' => $disp->dispensing_id,
                            'medicine_id' => $med->medicine_id,
                            'batch_id' => null,
                            'quantity_dispensed' => $remaining,
                        ]);
                    }
                    $med->decrement('stock_quantity', $qty);
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
