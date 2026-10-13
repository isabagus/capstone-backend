<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * MaterialCollection
 *
 * Wraps a paginated list of MaterialResource items and appends summary
 * metadata used by the frontend dashboard widgets (total SKU, total valuation).
 */
class MaterialCollection extends ResourceCollection
{
    /** @var string */
    public $collects = MaterialResource::class;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }

    /**
     * Additional metadata appended outside the "data" envelope.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'total_sku'      => $this->collection->count(),
                'item_types'     => $this->collection->groupBy('itemType')->map->count(),
                'below_rop_count' => $this->collection->filter(
                    fn ($r) => $r['totalStock'] <= $r['rop']
                )->count(),
            ],
        ];
    }
}
