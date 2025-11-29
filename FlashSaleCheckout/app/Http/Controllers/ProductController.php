<?php

namespace App\Http\Controllers;

use App\Models\Product; 
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;


class ProductController extends Controller
{
        public function show($id)
    {

      $product = Product::findOrFail($id);

        $availableStock = Cache::remember("product_{$id}_stock", 5, function () use ($product) {
            $stock = $product->quantity - $product->holds()
                ->where('expires_at', '>', now())
                ->sum('quantity');

            Log::info("Calculated stock for product {$product->id}: {$stock}");

            return $stock;
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