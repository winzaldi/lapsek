<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HealthCenter;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthCenterController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => HealthCenter::orderBy('name')->paginate(50)]);
    }

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'alpha_dash', 'unique:health_centers,code'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $center = HealthCenter::create($data);
        $audit->record($request, 'health_center.created', $center);

        return response()->json(['data' => $center], 201);
    }

    public function update(Request $request, HealthCenter $healthCenter, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:32', 'alpha_dash', 'unique:health_centers,code,'.$healthCenter->id],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $healthCenter->update($data);
        $audit->record($request, 'health_center.updated', $healthCenter, metadata: ['fields' => array_keys($data)]);

        return response()->json(['data' => $healthCenter->refresh()]);
    }
}
