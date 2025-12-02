<?php

namespace Tests\Feature;

use App\Models\Hold;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;


class OrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_parallel_holds_no_oversell()
    {
        $product = Product::factory()->create(['quantity' => 1]);

        $hold1 = Hold::factory()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'expires_at' => now()->addMinutes(2),
        ]);

        $hold2 = Hold::factory()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'expires_at' => now()->addMinutes(2),
        ]);

        $response1 = $this->postJson('/api/orders', ['hold_id' => $hold1->id]);
        $response1->assertStatus(201);

        $response2 = $this->postJson('/api/orders', ['hold_id' => $hold2->id]);
        $response2->assertStatus(400)
                ->assertJson(['message' => 'No available stock left for the product']);
    }

    public function test_hold_expiry_releases_stock()
    {
        $product = Product::factory()->create(['quantity' => 1]);
        $hold = Hold::factory()->create([
            'product_id' => $product->id,
            'expires_at' => now()->subMinutes(1),
        ]);

        $response = $this->postJson('/api/orders', ['hold_id' => $hold->id]);

        $response->assertStatus(400)
                 ->assertJson(['message' => 'Hold has expired']);
    }

    public function test_webhook_idempotency()
    {
        $order = Order::factory()->create(['status' => Order::STATUS_PENDING]);

        $payload = [
            'idempotency_key' => 'unique-key-123',
            'order_id' => $order->id,
            'status' => 'success',
        ];

        $this->postJson('/api/payments/webhook', $payload)->assertStatus(200);
        $this->postJson('/api/payments/webhook', $payload)->assertStatus(200);

        $order->refresh();
        $this->assertEquals(Order::STATUS_PAID, $order->status);

        $this->assertDatabaseCount('payment_webhooks', 1);
    }

    public function test_webhook_before_order_creation()
    {
        $payload = [
            'idempotency_key' => 'preorder-key-123',
            'order_id' => 9999,
            'status' => 'success',
        ];

        $response = $this->postJson('/api/payments/webhook', $payload);
        $response->assertStatus(422); 

        $order = Order::factory()->create(['id' => 9999, 'status' => Order::STATUS_PENDING]);
        $this->postJson('/api/payments/webhook', $payload)->assertStatus(200);

        $order->refresh();
        $this->assertEquals(Order::STATUS_PAID, $order->status);
    }
}
