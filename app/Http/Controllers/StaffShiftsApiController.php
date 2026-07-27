<?php

namespace App\Http\Controllers;

use App\Models\StaffShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffShiftsApiController extends Controller
{
    /** Backs the nurse-assignment shift dropdown on the patient detail modal. */
    public function index(): JsonResponse
    {
        return response()->json(StaffShift::orderByDesc('shift_date')->limit(50)->get());
    }

    /** Backs the flat, paginated "Schedule Management" list page (PageController::schedule()). */
    public function schedulePage(Request $request): JsonResponse
    {
        $shifts = DB::table('staff_shift as sh')
            ->join('staff as s', 's.staff_id', '=', 'sh.staff_id')
            ->orderByDesc('sh.shift_date')
            ->selectRaw("sh.*, (s.first_name||' '||s.last_name) as staff_name")
            ->paginate((int) $request->query('per_page', 20), ['*'], 'page', (int) $request->query('page', 1));

        return response()->json([
            'data' => $shifts->items(),
            'meta' => [
                'current_page' => $shifts->currentPage(),
                'last_page'    => $shifts->lastPage(),
                'total'        => $shifts->total(),
                'per_page'     => $shifts->perPage(),
            ],
        ]);
    }
}
