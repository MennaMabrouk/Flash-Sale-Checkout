<?php

namespace App\Http\Controllers;

use App\Models\Product; 
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function show($id)
    {

        $product = Product::with(['holds' => function ($query) {
            $query->where('expires_at', '>', now());
        }])->findOrFail($id);

        $cacheKey = "product_{$id}_stock";
        $ttlSeconds = 5;

        $availableStock = Cache::remember($cacheKey, $ttlSeconds, function () use ($product) {
            return $product->quantity - $product->holds->sum('quantity');
        });

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'quantity' => $product->quantity,
            'available_stock' => $availableStock
        ]);
    }
}
