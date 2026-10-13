<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UnitConversion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitConversionController extends Controller
{
    /**
     * Display a listing of unit conversions.
     * GET /api/v1/unit-conversions
     */
    public function index(): JsonResponse
    {
        $conversions = UnitConversion::with(['fromUnit', 'toUnit'])
            ->orderBy('id', 'asc')
            ->get();

        $data = $conversions->map(function ($c) {
            return [
                'id'           => (string) $c->id,
                'fromUnitId'   => (string) $c->from_unit_id,
                'fromUnitName' => $c->fromUnit?->name,
                'toUnitId'     => (string) $c->to_unit_id,
                'toUnitName'   => $c->toUnit?->name,
                'factor'       => (float) $c->factor,
                'note'         => $c->note,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar aturan konversi berhasil diambil',
            'data'    => $data,
        ]);
    }

    /**
     * Store a newly created unit conversion.
     * POST /api/v1/unit-conversions
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->has('fromUnitId') && !$request->has('from_unit_id')) {
            $request->merge(['from_unit_id' => $request->input('fromUnitId')]);
        }
        if ($request->has('toUnitId') && !$request->has('to_unit_id')) {
            $request->merge(['to_unit_id' => $request->input('toUnitId')]);
        }

        $validated = $request->validate([
            'from_unit_id' => ['required', 'exists:units,id'],
            'to_unit_id'   => ['required', 'exists:units,id', 'different:from_unit_id'],
            'factor'       => ['required', 'numeric', 'min:0.0001'],
            'note'         => ['nullable', 'string', 'max:255'],
        ]);

        $exists = UnitConversion::where('from_unit_id', $validated['from_unit_id'])
            ->where('to_unit_id', $validated['to_unit_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Aturan konversi untuk pasangan satuan ini sudah ada',
            ], 422);
        }

        $conversion = UnitConversion::create([
            'from_unit_id' => $validated['from_unit_id'],
            'to_unit_id'   => $validated['to_unit_id'],
            'factor'       => $validated['factor'],
            'note'         => $validated['note'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Aturan konversi baru berhasil ditambahkan',
            'data'    => [
                'id'         => (string) $conversion->id,
                'fromUnitId' => (string) $conversion->from_unit_id,
                'toUnitId'   => (string) $conversion->to_unit_id,
                'factor'     => (float) $conversion->factor,
                'note'       => $conversion->note,
            ],
        ], 201);
    }

    /**
     * Update the specified unit conversion.
     * PUT/PATCH /api/v1/unit-conversions/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $conversion = UnitConversion::find($id);

        if (!$conversion) {
            return response()->json([
                'success' => false,
                'message' => "Aturan konversi dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        $validated = $request->validate([
            'factor' => ['sometimes', 'required', 'numeric', 'min:0.0001'],
            'note'   => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if (isset($validated['factor'])) {
            $conversion->factor = $validated['factor'];
        }
        if (array_key_exists('note', $validated)) {
            $conversion->note = $validated['note'];
        }

        $conversion->save();

        return response()->json([
            'success' => true,
            'message' => 'Aturan konversi berhasil diperbarui',
            'data'    => [
                'id'         => (string) $conversion->id,
                'fromUnitId' => (string) $conversion->from_unit_id,
                'toUnitId'   => (string) $conversion->to_unit_id,
                'factor'     => (float) $conversion->factor,
                'note'       => $conversion->note,
            ],
        ]);
    }

    /**
     * Remove the specified unit conversion.
     * DELETE /api/v1/unit-conversions/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $conversion = UnitConversion::find($id);

        if (!$conversion) {
            return response()->json([
                'success' => false,
                'message' => "Aturan konversi dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        $conversion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Aturan konversi berhasil dihapus',
        ]);
    }
}
