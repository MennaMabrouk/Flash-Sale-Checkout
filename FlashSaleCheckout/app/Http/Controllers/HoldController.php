<?php

namespace App\Http\Controllers;

use App\Models\Hold;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HoldController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1',
        ]);

        $productId = $request->product_id;
        $qty = $request->qty;

        try {
            $hold = DB::transaction(function () use ($productId, $qty) {

                $product = Product::lockForUpdate()->find($productId);
                $activeHoldsQty = $product->holds()
                    ->where('expires_at', '>', now())
                    ->sum('quantity');

                $available = $product->quantity - $activeHoldsQty;

                if ($qty > $available) {
                    throw new \Exception('Not enough stock available');
                }

                $hold = Hold::create([
                    'product_id' => $productId,
                    'quantity' => $qty,
                    'expires_at' => now()->addMinutes(2), 
                ]);

                // Clear cache for the product
                Cache::forget("product_{$productId}_stock");

                return $hold;
            });

            return response()->json([
                'hold_id' => $hold->id,
                'expires_at' => $hold->expires_at,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
