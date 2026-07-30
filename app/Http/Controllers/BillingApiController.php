<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBillItemRequest;
use App\Http\Requests\StoreBillRequest;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'all');
        $patientId = $request->query('patient_id');

        // paid_amount as a correlated scalar subquery instead of a
        // LEFT JOIN + 9-column GROUP BY (analysis.md §4.10/scalability-plan
        // §5.1) — same pattern already used for item_count right below it.
        // Mathematically identical result (SUM of that bill's payments,
        // 0 if none), but the main paginated query no longer has to join
        // and group every row against `payment` just to compute one sum;
        // Postgres can index-scan the subquery per row instead
        // (payment.bill_id is indexed — see 2026_07_30_000002).
        $bills = DB::table('bill as b')
            ->join('patient as p', 'p.patient_id', '=', 'b.patient_id')
            ->when($status !== 'all', fn ($q) => $q->where('b.status', $status))
            ->when($patientId, fn ($q) => $q->where('b.patient_id', $patientId))
            ->orderByDesc('b.bill_date')
            ->selectRaw("b.*, (p.first_name||' '||p.last_name) as patient_name,
                         (SELECT COALESCE(SUM(amount_paid), 0) FROM payment WHERE payment.bill_id = b.bill_id) as paid_amount,
                         (SELECT COUNT(*) FROM bill_item WHERE bill_item.bill_id = b.bill_id) as item_count")
            ->paginate((int) $request->query('bills_per_page', 20), ['*'], 'bills_page', (int) $request->query('bills_page', 1));

        $payments = DB::table('payment as pm')
            ->join('bill as b', 'b.bill_id', '=', 'pm.bill_id')
            ->join('patient as p', 'p.patient_id', '=', 'b.patient_id')
            ->orderByDesc('pm.payment_date')
            ->selectRaw("pm.*, (p.first_name||' '||p.last_name) as patient_name")
            ->paginate((int) $request->query('payments_per_page', 20), ['*'], 'payments_page', (int) $request->query('payments_page', 1));

        $pendingAmount = (float) DB::table('bill as b')
            ->leftJoin(DB::raw('(SELECT bill_id, SUM(amount_paid) as paid FROM payment GROUP BY bill_id) pm'), 'pm.bill_id', '=', 'b.bill_id')
            ->whereIn('b.status', ['unpaid', 'partially_paid'])
            ->selectRaw('COALESCE(SUM(b.total_amount - COALESCE(pm.paid, 0)), 0) as pending')
            ->value('pending');

        // total_amount/unpaid/partially_paid/paid all scan `bill` — one
        // query with FILTERs instead of 4 (see analysis.md §4.8).
        // total_revenue scans the separate `payment` table, so it stays its
        // own query; pending_amount above is already a single query.
        $billStats = DB::table('bill')->selectRaw(
            "coalesce(sum(total_amount), 0) as total_amount,
             count(*) filter (where status = 'unpaid') as unpaid,
             count(*) filter (where status = 'partially_paid') as partially_paid,
             count(*) filter (where status = 'paid') as paid"
        )->first();

        $stats = [
            'total_amount'    => (float) $billStats->total_amount,
            'total_revenue'   => (float) Payment::sum('amount_paid'),
            'pending_amount'  => $pendingAmount,
            'unpaid'          => (int) $billStats->unpaid,
            'partially_paid'  => (int) $billStats->partially_paid,
            'paid'            => (int) $billStats->paid,
        ];

        return response()->json([
            'bills' => $this->paginated($bills),
            'payments' => $this->paginated($payments),
            'stats' => $stats,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $bill = Bill::with(['items', 'payments', 'patient'])->find($id);
        if (! $bill) {
            return response()->json(['message' => 'Bill not found'], 404);
        }

        return response()->json($this->present($bill));
    }

    public function store(StoreBillRequest $request): JsonResponse
    {
        $data = $request->validated();

        $bill = DB::transaction(function () use ($data, $request) {
            $bill = Bill::create([
                'patient_id' => $data['patient_id'],
                'appointment_id' => $data['appointment_id'] ?? null,
                'generated_by' => $request->input('generated_by'),
            ]);

            foreach ($data['items'] ?? [] as $item) {
                BillItem::create([
                    'bill_item_id' => 'BI' . strtoupper(Str::random(8)),
                    'bill_id' => $bill->bill_id,
                    'item_type' => $item['item_type'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                ]);
            }

            if (! empty($data['items'])) {
                $bill->recomputeTotal();
                $bill->recomputeStatus();
            }

            return $bill;
        });

        return response()->json($this->present($bill->fresh(['items', 'payments', 'patient'])), 201);
    }

    public function addItem(StoreBillItemRequest $request, string $id): JsonResponse
    {
        $bill = Bill::find($id);
        if (! $bill) {
            return response()->json(['message' => 'Bill not found'], 404);
        }

        $data = $request->validated();
        BillItem::create([
            'bill_item_id' => 'BI' . strtoupper(Str::random(8)),
            'bill_id' => $bill->bill_id,
            'item_type' => $data['item_type'],
            'description' => $data['description'] ?? null,
            'quantity' => $data['quantity'],
            'unit_price' => $data['unit_price'],
        ]);
        $bill->recomputeTotal();
        $bill->recomputeStatus();

        return response()->json($this->present($bill->fresh(['items', 'payments', 'patient'])), 201);
    }

    public function pay(Request $request, string $id): JsonResponse
    {
        $bill = Bill::find($id);
        if (! $bill) {
            return response()->json(['message' => 'Bill not found'], 404);
        }
        if ($bill->status === 'paid') {
            return response()->json(['message' => 'This bill is already fully paid.'], 409);
        }

        $balance = round((float) $bill->total_amount - $bill->paidAmount(), 2);
        $data = $request->validate([
            'amount_paid' => ['required', 'numeric', 'min:0.01', 'max:' . max($balance, 0.01)],
            'payment_method' => 'required|in:cash,card,online',
            'transaction_reference' => 'nullable|string|max:100',
        ]);

        Payment::create([
            'payment_id' => 'PAY' . strtoupper(Str::random(8)),
            'bill_id' => $bill->bill_id,
            'received_by' => $request->input('received_by'),
            'payment_method' => $data['payment_method'],
            'amount_paid' => $data['amount_paid'],
            'transaction_reference' => $data['transaction_reference'] ?? null,
        ]);

        $bill->recomputeStatus();

        return response()->json($this->present($bill->fresh(['items', 'payments', 'patient'])));
    }

    private function present(Bill $bill): array
    {
        $paid = $bill->paidAmount();

        return [
            ...$bill->only(['bill_id', 'patient_id', 'appointment_id', 'generated_by', 'bill_date', 'total_amount', 'status']),
            'patient' => $bill->patient?->only(['patient_id', 'first_name', 'last_name', 'date_of_birth']),
            'items' => $bill->items->map(fn (BillItem $i) => $i->only([
                'bill_item_id', 'bill_id', 'item_type', 'description', 'quantity', 'unit_price', 'subtotal',
            ]))->values(),
            'payments' => $bill->payments->map(fn (Payment $p) => $p->only([
                'payment_id', 'bill_id', 'received_by', 'payment_method', 'amount_paid', 'transaction_reference', 'payment_date',
            ]))->values(),
            'paid_amount' => $paid,
            'balance' => round((float) $bill->total_amount - $paid, 2),
        ];
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
