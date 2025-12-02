<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Order;
use App\Models\Hold;
use App\Models\PaymentWebhook;
use App\Models\Product;

class PaymentController extends Controller
{
    /**
     * Process a payment webhook
     */
    public function pay(Request $request)
    {
        // Validate incoming request
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

                $existingWebhook = PaymentWebhook::where('idempotency_key', $idempotencyKey)->first();
                if ($existingWebhook) 
                {
                    return $existingWebhook;
                }

                $order = Order::with('hold')->lockForUpdate()->findOrFail($orderId);
                $hold = $order->hold;

                if (!$hold) 
                {
                    throw new \Exception("Order hold not found");
                }

                $product = Product::lockForUpdate()->findOrFail($hold->product_id);

                if ($paymentStatus === 'success') 
                {
                    $order->status = Order::STATUS_PAID;
                    $product->decrement('quantity', $hold->quantity);
                } 
                else 
                {
                    $order->status = Order::STATUS_CANCELLED;

                    $hold->delete();
                }

                $order->save();

                $webhook = PaymentWebhook::create([
                    'idempotency_key' => $idempotencyKey,
                    'order_id' => $order->id,
                    'status' => $paymentStatus,
                ]);

                Cache::forget("product_{$product->id}_stock");

                return $webhook;
            });

            return response()->json([
                'message' => 'Webhook processed successfully',
                'data' => $result
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
