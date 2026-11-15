<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SpkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Throwable;

class SpkController extends Controller
{
    public function __construct(
        protected SpkService $spkService
    ) {}

    /**
     * GET /api/v1/spk
     * Mengambil daftar dokumen SPK (mendukung filter Brand, Tanggal, & Pencarian)
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'type',
            'brand_id',
            'brand',
            'status',
            'product_type_label',
            'delivery_type',
            'start_date',
            'end_date',
            'q',
            'keyword',
            'per_page',
            'all',
        ]);

        $spks = $this->spkService->list($filters);

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar dokumen SPK berhasil dimuat.',
            'data'    => $spks,
        ]);
    }

    /**
     * POST /api/v1/spk
     * Menerbitkan SPK Digital baru (Internal / Eksternal) beserta multi-bahan baku
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'spk_number'         => 'nullable|string|max:64|unique:spks,spk_number',
            'order_number'       => 'nullable|string|max:64',
            'folder_name'        => 'nullable|string|max:150',
            'job_name'           => 'nullable|string|max:200',
            'customer_name'      => 'nullable|string|max:150',
            'brand_id'           => 'nullable|exists:brands,id',
            'bom_id'             => 'nullable|exists:bill_of_materials,id',
            'type'               => ['nullable', 'string', Rule::in(['INTERNAL', 'EXTERNAL'])],
            'vendor_id'          => 'nullable|exists:vendors,id',
            'product_type_label' => ['nullable', 'string', Rule::in(['ALUFOIL', 'HARDBOX', 'SOFTBOX', 'CORRUGATED', 'LABEL_STICKER', 'OTHERS'])],
            'delivery_type'      => ['nullable', 'string', Rule::in(['DIAMBIL', 'DIKIRIM', 'EKSPEDISI'])],
            'receipt_number'     => 'nullable|string|max:100',
            'color_standard'     => 'nullable|string|max:150',
            'packing_type'       => 'nullable|string|max:100',
            'order_quantity'     => 'nullable|integer|min:1',
            'publish_date'       => 'nullable|date',
            'deadline'           => 'nullable|date',
            'status'             => ['nullable', 'string', Rule::in(['DRAFT', 'TERBIT', 'IN_PRODUCTION', 'SELESAI', 'CANCELLED'])],
            'finishing_details'  => 'nullable|array',
            'cost_breakdown'     => 'nullable|array',
            'notes'              => 'nullable|string',
            'layouter_id'        => 'nullable|exists:users,id',
            'checker1_id'        => 'nullable|exists:users,id',
            'checker2_id'        => 'nullable|exists:users,id',

            // Validasi Multi-Bahan Baku
            'materials'                          => 'nullable|array',
            'materials.*.material_id'            => 'nullable|exists:materials,id',
            'materials.*.source_type'            => ['nullable', 'string', Rule::in(['BAHAN_SENDIRI', 'BAHAN_KONSUMEN', 'GUDANG'])],
            'materials.*.material_name'          => 'required_with:materials|string|max:150',
            'materials.*.component_name'         => 'nullable|string|max:100',
            'materials.*.plano_sheets'           => 'nullable|integer|min:0',
            'materials.*.cuts_per_plano'         => 'nullable|integer|min:1',
            'materials.*.cut_sheets'             => 'nullable|integer|min:0',
            'materials.*.cut_size'               => 'nullable|string|max:50',
            'materials.*.file_size'              => 'nullable|string|max:50',
            'materials.*.target_product'         => 'nullable|string|max:200',
            'materials.*.cutting_layout_schema'  => 'nullable|array',
            'materials.*.total_quantity'         => 'nullable|numeric|min:0',
            'materials.*.unit'                   => 'nullable|string|max:30',
            'materials.*.source_warehouse_id'    => 'nullable|exists:warehouses,id',
        ]);

        try {
            $userId = $request->user()?->id;
            $spk = $this->spkService->create($validated, $userId);

            return response()->json([
                'status'  => 'success',
                'message' => 'Dokumen SPK Digital berhasil diterbitkan.',
                'data'    => $spk,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menerbitkan SPK: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/spk/{id}
     * Menampilkan detail lengkap satu SPK beserta bahan dan checker
     */
    public function show(int $id): JsonResponse
    {
        try {
            $spk = $this->spkService->find($id);

            return response()->json([
                'status'  => 'success',
                'message' => 'Detail dokumen SPK berhasil dimuat.',
                'data'    => $spk,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Dokumen SPK tidak ditemukan.',
            ], 404);
        }
    }

    /**
     * PUT /api/v1/spk/{id}
     * Memperbarui dokumen SPK dan rincian multi-bahan
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'order_number'       => 'nullable|string|max:64',
            'folder_name'        => 'nullable|string|max:150',
            'job_name'           => 'nullable|string|max:200',
            'customer_name'      => 'nullable|string|max:150',
            'brand_id'           => 'nullable|exists:brands,id',
            'bom_id'             => 'nullable|exists:bill_of_materials,id',
            'type'               => ['nullable', 'string', Rule::in(['INTERNAL', 'EXTERNAL'])],
            'vendor_id'          => 'nullable|exists:vendors,id',
            'product_type_label' => ['nullable', 'string', Rule::in(['ALUFOIL', 'HARDBOX', 'SOFTBOX', 'CORRUGATED', 'LABEL_STICKER', 'OTHERS'])],
            'delivery_type'      => ['nullable', 'string', Rule::in(['DIAMBIL', 'DIKIRIM', 'EKSPEDISI'])],
            'receipt_number'     => 'nullable|string|max:100',
            'color_standard'     => 'nullable|string|max:150',
            'packing_type'       => 'nullable|string|max:100',
            'order_quantity'     => 'nullable|integer|min:1',
            'publish_date'       => 'nullable|date',
            'deadline'           => 'nullable|date',
            'status'             => ['nullable', 'string', Rule::in(['DRAFT', 'TERBIT', 'IN_PRODUCTION', 'SELESAI', 'CANCELLED'])],
            'finishing_details'  => 'nullable|array',
            'cost_breakdown'     => 'nullable|array',
            'notes'              => 'nullable|string',
            'layouter_id'        => 'nullable|exists:users,id',
            'checker1_id'        => 'nullable|exists:users,id',
            'checker2_id'        => 'nullable|exists:users,id',

            'materials'                          => 'nullable|array',
            'materials.*.material_id'            => 'nullable|exists:materials,id',
            'materials.*.source_type'            => ['nullable', 'string', Rule::in(['BAHAN_SENDIRI', 'BAHAN_KONSUMEN', 'GUDANG'])],
            'materials.*.material_name'          => 'required_with:materials|string|max:150',
            'materials.*.component_name'         => 'nullable|string|max:100',
            'materials.*.plano_sheets'           => 'nullable|integer|min:0',
            'materials.*.cuts_per_plano'         => 'nullable|integer|min:1',
            'materials.*.cut_sheets'             => 'nullable|integer|min:0',
            'materials.*.cut_size'               => 'nullable|string|max:50',
            'materials.*.file_size'              => 'nullable|string|max:50',
            'materials.*.target_product'         => 'nullable|string|max:200',
            'materials.*.cutting_layout_schema'  => 'nullable|array',
            'materials.*.total_quantity'         => 'nullable|numeric|min:0',
            'materials.*.unit'                   => 'nullable|string|max:30',
            'materials.*.source_warehouse_id'    => 'nullable|exists:warehouses,id',
        ]);

        try {
            $spk = $this->spkService->update($id, $validated);

            return response()->json([
                'status'  => 'success',
                'message' => 'Dokumen SPK berhasil diperbarui.',
                'data'    => $spk,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal memperbarui SPK: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PATCH /api/v1/spk/{id}/status
     * Memperbarui status alur dokumen SPK (DRAFT -> TERBIT -> IN_PRODUCTION, dst)
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['DRAFT', 'TERBIT', 'IN_PRODUCTION', 'SELESAI', 'CANCELLED'])],
        ]);

        try {
            $spk = $this->spkService->updateStatus($id, $validated['status']);

            return response()->json([
                'status'  => 'success',
                'message' => "Status SPK berhasil diperbarui menjadi {$validated['status']}.",
                'data'    => $spk,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal memperbarui status SPK.',
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/spk/{id}
     * Menghapus dokumen SPK beserta rincian bahannya
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->spkService->delete($id);

            return response()->json([
                'status'  => 'success',
                'message' => 'Dokumen SPK berhasil dihapus.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menghapus dokumen SPK.',
            ], 500);
        }
    }
}
