<?php

namespace App\Services;

use App\Models\Spk;
use App\Models\SpkMaterial;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SpkService
{
    /**
     * Mengambil daftar SPK dengan query filter komprehensif
     * (Mendukung filter Brand, Tipe, Status, Rentang Tanggal, & Pencarian)
     */
    public function list(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = Spk::with(['materials.material', 'materials.warehouse', 'brand', 'vendor', 'creator'])
            ->latest('id');

        // 1. Filter Tipe SPK (INTERNAL / EXTERNAL)
        if (!empty($filters['type']) && $filters['type'] !== 'Semua') {
            $query->where('type', strtoupper($filters['type']));
        }

        // 2. Filter Brand
        if (!empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        } elseif (!empty($filters['brand']) && $filters['brand'] !== 'Semua') {
            $brandKeyword = $filters['brand'];
            $query->whereHas('brand', function (Builder $q) use ($brandKeyword) {
                $q->where('name', 'LIKE', "%{$brandKeyword}%")
                  ->orWhere('slug', 'LIKE', "%{$brandKeyword}%");
            });
        }

        // 3. Filter Status SPK
        if (!empty($filters['status']) && $filters['status'] !== 'Semua') {
            $query->where('status', strtoupper($filters['status']));
        }

        // 4. Filter Product Type Label (ALUFOIL, HARDBOX, dsb)
        if (!empty($filters['product_type_label']) && $filters['product_type_label'] !== 'Semua') {
            $query->where('product_type_label', strtoupper($filters['product_type_label']));
        }

        // 5. Filter Delivery Type (DIAMBIL, DIKIRIM, EKSPEDISI)
        if (!empty($filters['delivery_type']) && $filters['delivery_type'] !== 'Semua') {
            $query->where('delivery_type', strtoupper($filters['delivery_type']));
        }

        // 6. Filter Rentang Tanggal Terbit SPK (Publish Date)
        if (!empty($filters['start_date'])) {
            $query->whereDate('publish_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('publish_date', '<=', $filters['end_date']);
        }

        // 7. Filter Pencarian Teks (No. SPK, No. Order, Folder, Job, Customer, Nama Bahan)
        $search = $filters['q'] ?? $filters['keyword'] ?? null;
        if (!empty($search)) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('spk_number', 'LIKE', "%{$search}%")
                  ->orWhere('order_number', 'LIKE', "%{$search}%")
                  ->orWhere('folder_name', 'LIKE', "%{$search}%")
                  ->orWhere('job_name', 'LIKE', "%{$search}%")
                  ->orWhere('customer_name', 'LIKE', "%{$search}%")
                  ->orWhereHas('materials', function (Builder $mq) use ($search) {
                      $mq->where('material_name', 'LIKE', "%{$search}%")
                         ->orWhere('target_product', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Paginasi atau fetch all
        $perPage = (int) ($filters['per_page'] ?? 15);
        if (isset($filters['all']) && $filters['all'] === true) {
            return $query->get();
        }

        return $query->paginate($perPage);
    }

    /**
     * Mengambil detail satu SPK lengkap dengan relasinya
     */
    public function find(int $id): Spk
    {
        return Spk::with([
            'materials.material',
            'materials.warehouse',
            'brand',
            'vendor',
            'bom',
            'layouter',
            'checker1',
            'checker2',
            'creator',
        ])->findOrFail($id);
    }

    /**
     * Membuat SPK baru beserta rincian multi-bahan baku dalam satu transaksi ACID
     */
    public function create(array $data, ?int $userId = null): Spk
    {
        return DB::transaction(function () use ($data, $userId) {
            $type = strtoupper($data['type'] ?? 'INTERNAL');
            $spkNumber = $data['spk_number'] ?? $this->generateSpkNumber($type);

            $spk = Spk::create([
                'spk_number'         => $spkNumber,
                'order_number'       => $data['order_number'] ?? null,
                'folder_name'        => $data['folder_name'] ?? null,
                'job_name'           => $data['job_name'] ?? null,
                'customer_name'      => $data['customer_name'] ?? null,
                'brand_id'           => $data['brand_id'] ?? null,
                'bom_id'             => $data['bom_id'] ?? null,
                'type'               => $type,
                'vendor_id'          => $type === 'EXTERNAL' ? ($data['vendor_id'] ?? null) : null,
                'product_type_label' => strtoupper($data['product_type_label'] ?? 'ALUFOIL'),
                'delivery_type'      => strtoupper($data['delivery_type'] ?? 'DIAMBIL'),
                'receipt_number'     => $data['receipt_number'] ?? null,
                'color_standard'     => $data['color_standard'] ?? 'Standart Martin / Indicator',
                'packing_type'       => $data['packing_type'] ?? 'Bungkus',
                'order_quantity'     => (int) ($data['order_quantity'] ?? 1000),
                'publish_date'       => $data['publish_date'] ?? now()->toDateString(),
                'deadline'           => $data['deadline'] ?? null,
                'status'             => strtoupper($data['status'] ?? 'DRAFT'),
                'finishing_details'  => $data['finishing_details'] ?? [],
                'cost_breakdown'     => $data['cost_breakdown'] ?? [],
                'notes'              => $data['notes'] ?? null,
                'layouter_id'        => $data['layouter_id'] ?? null,
                'checker1_id'        => $data['checker1_id'] ?? null,
                'checker2_id'        => $data['checker2_id'] ?? null,
                'created_by'         => $userId,
            ]);

            // Simpan Multi-Bahan Baku
            $materials = $data['materials'] ?? [];
            if (!empty($materials) && is_array($materials)) {
                foreach ($materials as $item) {
                    $this->createMaterialItem($spk->id, $item);
                }
            }

            return $this->find($spk->id);
        });
    }

    /**
     * Memperbarui data SPK dan sinkronisasi bahan baku
     */
    public function update(int $id, array $data): Spk
    {
        return DB::transaction(function () use ($id, $data) {
            $spk = Spk::findOrFail($id);

            // Update kolom header SPK
            $spk->update(array_filter([
                'order_number'       => $data['order_number'] ?? $spk->order_number,
                'folder_name'        => $data['folder_name'] ?? $spk->folder_name,
                'job_name'           => $data['job_name'] ?? $spk->job_name,
                'customer_name'      => $data['customer_name'] ?? $spk->customer_name,
                'brand_id'           => $data['brand_id'] ?? $spk->brand_id,
                'bom_id'             => $data['bom_id'] ?? $spk->bom_id,
                'type'               => isset($data['type']) ? strtoupper($data['type']) : $spk->type,
                'vendor_id'          => array_key_exists('vendor_id', $data) ? $data['vendor_id'] : $spk->vendor_id,
                'product_type_label' => isset($data['product_type_label']) ? strtoupper($data['product_type_label']) : $spk->product_type_label,
                'delivery_type'      => isset($data['delivery_type']) ? strtoupper($data['delivery_type']) : $spk->delivery_type,
                'receipt_number'     => $data['receipt_number'] ?? $spk->receipt_number,
                'color_standard'     => $data['color_standard'] ?? $spk->color_standard,
                'packing_type'       => $data['packing_type'] ?? $spk->packing_type,
                'order_quantity'     => isset($data['order_quantity']) ? (int) $data['order_quantity'] : $spk->order_quantity,
                'publish_date'       => $data['publish_date'] ?? $spk->publish_date,
                'deadline'           => $data['deadline'] ?? $spk->deadline,
                'status'             => isset($data['status']) ? strtoupper($data['status']) : $spk->status,
                'finishing_details'  => $data['finishing_details'] ?? $spk->finishing_details,
                'cost_breakdown'     => $data['cost_breakdown'] ?? $spk->cost_breakdown,
                'notes'              => $data['notes'] ?? $spk->notes,
                'layouter_id'        => $data['layouter_id'] ?? $spk->layouter_id,
                'checker1_id'        => $data['checker1_id'] ?? $spk->checker1_id,
                'checker2_id'        => $data['checker2_id'] ?? $spk->checker2_id,
            ], fn ($val) => $val !== null));

            // Sinkronisasi Multi-Bahan jika disertakan dalam request
            if (isset($data['materials']) && is_array($data['materials'])) {
                // Hapus item lama lalu tambahkan item baru yang telah disesuaikan
                $spk->materials()->delete();
                foreach ($data['materials'] as $item) {
                    $this->createMaterialItem($spk->id, $item);
                }
            }

            return $this->find($spk->id);
        });
    }

    /**
     * Memperbarui status SPK secara terisolasi (misal transisi ke TERBIT / IN_PRODUCTION / SELESAI)
     */
    public function updateStatus(int $id, string $status): Spk
    {
        $validStatuses = ['DRAFT', 'TERBIT', 'IN_PRODUCTION', 'SELESAI', 'CANCELLED'];
        $cleanStatus = strtoupper($status);

        if (!in_array($cleanStatus, $validStatuses, true)) {
            throw new InvalidArgumentException("Status SPK '{$status}' tidak valid.");
        }

        $spk = Spk::findOrFail($id);
        $spk->update(['status' => $cleanStatus]);

        return $this->find($spk->id);
    }

    /**
     * Menghapus dokumen SPK
     */
    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $spk = Spk::findOrFail($id);
            $spk->materials()->delete();
            return (bool) $spk->delete();
        });
    }

    /**
     * Helper membuat satu baris bahan baku SPK
     */
    protected function createMaterialItem(int $spkId, array $item): SpkMaterial
    {
        return SpkMaterial::create([
            'spk_id'                => $spkId,
            'material_id'           => $item['material_id'] ?? null,
            'source_type'           => strtoupper($item['source_type'] ?? 'BAHAN_SENDIRI'),
            'material_name'         => $item['material_name'] ?? 'Bahan Kertas Standar',
            'component_name'        => $item['component_name'] ?? null,
            'plano_sheets'          => (int) ($item['plano_sheets'] ?? 0),
            'cuts_per_plano'        => (int) ($item['cuts_per_plano'] ?? 1),
            'cut_sheets'            => (int) ($item['cut_sheets'] ?? 0),
            'cut_size'              => $item['cut_size'] ?? null,
            'file_size'             => $item['file_size'] ?? null,
            'target_product'        => $item['target_product'] ?? null,
            'cutting_layout_schema' => $item['cutting_layout_schema'] ?? null,
            'total_quantity'        => (float) ($item['total_quantity'] ?? $item['cut_sheets'] ?? 0),
            'unit'                  => $item['unit'] ?? 'Lembar',
            'source_warehouse_id'   => $item['source_warehouse_id'] ?? null,
        ]);
    }

    /**
     * Auto-generator nomor SPK: SPK-INT-YYYYMM-XXX atau SPK-EKS-YYYYMM-XXX
     */
    protected function generateSpkNumber(string $type): string
    {
        $prefix = ($type === 'EXTERNAL') ? 'SPK-EKS' : 'SPK-INT';
        $yearMonth = now()->format('Ym');
        $randomSeq = str_pad((string) (Spk::count() + 1), 3, '0', STR_PAD_LEFT);

        return "{$prefix}-{$yearMonth}-{$randomSeq}";
    }
}
