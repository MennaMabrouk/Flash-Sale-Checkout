<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Hold;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class OrderController extends Controller
{
    public function order(Request $request)
{
    $request->validate([
        'hold_id' => 'required|exists:holds,id',
    ]);

    $holdId = $request->hold_id;

    try {
        $order = DB::transaction(function () use ($holdId) {

            $hold = Hold::with('order')->findOrFail($holdId);

            if ($hold->expires_at <= now()) 
            {
                throw new \Exception('Hold has expired');
            }

            if ($hold->order) 
            {
                throw new \Exception('Hold has already been used');
            }

            $product = Product::lockForUpdate()->findOrFail($hold->product_id);

            $activeHoldsQty = $product->holds()
                ->where('expires_at', '>', now())
                ->whereDoesntHave('order')
                ->sum('quantity');

            $availableStock = $product->quantity - $activeHoldsQty;

            if ($availableStock < 1) 
            {
                throw new \Exception('No available stock left for the product');
            }

            $order = Order::create([
                'hold_id' => $hold->id,
                'status' => Order::STATUS_PENDING,
            ]);

            return $order;
        });

        return response()->json([
            'order_id' => $order->id,
            'hold_id' => $order->hold_id,
            'status' => $order->status,
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'message' => $e->getMessage()
        ], 400);
    }
}
   
    
}
