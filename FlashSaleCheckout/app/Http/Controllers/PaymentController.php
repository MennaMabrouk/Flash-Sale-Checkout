<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Order;
use App\Models\Hold;
use App\Models\PaymentWebhook;

class PaymentController extends Controller
{
    public function pay(Request $request)
    {
        $request->validate([
            'idempotency_key' => 'required|string',
            'order_id' => 'required|exists:orders,id',
            'status' => 'required|in:success,failure',
        ]);

        $idempotencyKey = $request->idempotency_key;
        $orderId = $request->order_id;
        $paymentStatus = $request->status;

        try {
            $result = DB::transaction(function () use ($idempotencyKey, $orderId, $paymentStatus) {

                $existing = PaymentWebhook::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) 
                {
                    return $existing;
                }

                $order = Order::lockForUpdate()->with('hold')->findOrFail($orderId);

                if ($paymentStatus === 'success') 
                {
                    $order->status = Order::STATUS_PAID;
                    $order->hold->product->decrement('quantity', $order->hold->quantity);
                } 
                else 
                {
                    $order->status = Order::STATUS_CANCELLED;
                    $order->hold->delete();
                }
                $order->save();

                $webhook = PaymentWebhook::create([
                    'idempotency_key' => $idempotencyKey,
                    'order_id' => $order->id,
                    'status' => $paymentStatus,
                ]);

                Cache::forget("product_{$order->hold->product_id}_stock");

                return $webhook;
            });

            return response()->json([
                'message' => 'Webhook processed',
                'data' => $result
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}

