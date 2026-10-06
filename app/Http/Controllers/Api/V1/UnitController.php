<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    /**
     * List all units of measurement.
     */
    public function index(): JsonResponse
    {
        return response()->json(Unit::all());
    }

    /**
     * Create a new unit of measurement.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:20', 'unique:units,code'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(Unit::create($validated), 201);
    }

    /**
     * Show a single unit details.
     */
    public function show(Unit $unit): JsonResponse
    {
        return response()->json($unit->load('materials'));
    }

    /**
     * Update unit details.
     */
    public function update(Request $request, Unit $unit): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:50'],
            'code' => ['sometimes', 'string', 'max:20', "unique:units,code,{$unit->id}"],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $unit->update($validated);

        return response()->json($unit);
    }

    /**
     * Delete a unit of measurement.
     */
    public function destroy(Unit $unit): JsonResponse
    {
        if ($unit->materials()->exists()) {
            return response()->json([
                'message' => 'Cannot delete unit that is currently used by materials.',
            ], 422);
        }

        $unit->delete();

        return response()->json(['message' => 'Unit deleted successfully.']);
    }
}
