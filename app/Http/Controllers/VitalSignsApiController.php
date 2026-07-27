<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVitalSignRequest;
use App\Models\VitalSign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VitalSignsApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vitals = DB::table('vital_signs as v')
            ->join('patient as p', 'p.patient_id', '=', 'v.patient_id')
            ->orderByDesc('v.recorded_at')
            ->selectRaw("v.*, (p.first_name||' '||p.last_name) as patient_name")
            ->paginate((int) $request->query('per_page', 20), ['*'], 'page', (int) $request->query('page', 1));

        return response()->json([
            'data' => $vitals->items(),
            'meta' => [
                'current_page' => $vitals->currentPage(),
                'last_page'    => $vitals->lastPage(),
                'total'        => $vitals->total(),
                'per_page'     => $vitals->perPage(),
            ],
        ]);
    }

    public function store(StoreVitalSignRequest $request): JsonResponse
    {
        $vital = VitalSign::create($request->validated() + [
            'recorded_by' => $request->input('recorded_by'),
        ]);

        return response()->json($vital, 201);
    }
}
