<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Brand::all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:10', 'unique:brands,code'],
            'slug' => ['required', 'string', 'max:100', 'unique:brands,slug'],
        ]);

        return response()->json(Brand::create($validated), 201);
    }

    public function show(Brand $brand): JsonResponse
    {
        return response()->json($brand->load('materials'));
    }

    public function update(Request $request, Brand $brand): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'code' => ['sometimes', 'string', 'max:10', "unique:brands,code,{$brand->id}"],
            'slug' => ['sometimes', 'string', 'max:100', "unique:brands,slug,{$brand->id}"],
        ]);

        $brand->update($validated);

        return response()->json($brand);
    }
}
