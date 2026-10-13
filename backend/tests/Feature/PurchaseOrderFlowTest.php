<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $manager;

    private Supplier $supplier;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'administrator']);
        $this->manager = User::factory()->create(['role' => 'inventory_manager']);
        $this->supplier = Supplier::create(['name' => 'Test Supplier', 'is_active' => true]);
        $category = Category::create(['name' => 'Cat', 'is_active' => true]);
        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pcs', 'is_active' => true]);
        $this->item = Item::create([
            'item_code' => 'PO-1', 'name' => 'PO Item', 'item_type' => 'general',
            'category_id' => $category->id, 'unit_id' => $unit->id, 'minimum_stock' => 0, 'is_active' => true,
        ]);
        StockBalance::create(['item_id' => $this->item->id, 'quantity_on_hand' => 0]);
    }

    private function token(User $user): string
    {
        return $user->createToken('t')->plainTextToken;
    }

    private function header(User $user): array
    {
        return ['Authorization' => 'Bearer '.$this->token($user)];
    }

    public function test_purchase_order_lifecycle_and_partial_receipt(): void
    {
        $admin = $this->header($this->admin);

        $created = $this->withHeaders($admin)->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => '2026-02-01',
            'items' => [['item_id' => $this->item->id, 'quantity' => 10]],
        ])->assertCreated()->json();

        $this->assertSame('draft', $created['status']);
        $purchaseId = $created['id'];
        $lineId = $created['items'][0]['id'];

        // Stock is unchanged while draft/pending/approved.
        $this->assertSame(0.0, (float) StockBalance::first()->quantity_on_hand);

        $this->withHeaders($admin)->postJson("/api/v1/purchases/{$purchaseId}/submit")->assertOk()->assertJsonPath('status', 'pending_approval');
        $this->withHeaders($admin)->postJson("/api/v1/purchases/{$purchaseId}/approve")->assertOk()->assertJsonPath('status', 'approved');
        $this->withHeaders($admin)->postJson("/api/v1/purchases/{$purchaseId}/order")->assertOk()->assertJsonPath('status', 'ordered');
        $this->assertSame(0.0, (float) StockBalance::first()->quantity_on_hand);

        // Partial receipt grants 4 units.
        $this->withHeaders($admin)->postJson("/api/v1/purchases/{$purchaseId}/receive", [
            'receipt_date' => '2026-02-05',
            'items' => [['purchase_item_id' => $lineId, 'quantity' => 4]],
        ])->assertCreated();

        $this->assertSame(4.0, (float) StockBalance::first()->quantity_on_hand);
        $this->withHeaders($admin)->getJson("/api/v1/purchases/{$purchaseId}")->assertJsonPath('status', 'partially_received');

        // Over-receipt is rejected.
        $this->withHeaders($admin)->postJson("/api/v1/purchases/{$purchaseId}/receive", [
            'items' => [['purchase_item_id' => $lineId, 'quantity' => 10]],
        ])->assertStatus(422);

        // Remaining receipt completes the order.
        $this->withHeaders($admin)->postJson("/api/v1/purchases/{$purchaseId}/receive", [
            'items' => [['purchase_item_id' => $lineId, 'quantity' => 6]],
        ])->assertCreated();

        $this->assertSame(10.0, (float) StockBalance::first()->quantity_on_hand);
        $this->withHeaders($admin)->getJson("/api/v1/purchases/{$purchaseId}")->assertJsonPath('status', 'fully_received');
    }

    public function test_receipts_are_blocked_before_approval_and_on_cancelled_orders(): void
    {
        $admin = $this->header($this->admin);
        $created = $this->withHeaders($admin)->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => '2026-02-01',
            'items' => [['item_id' => $this->item->id, 'quantity' => 5]],
        ])->json();
        $purchaseId = $created['id'];
        $lineId = $created['items'][0]['id'];

        // Cannot receive a draft.
        $this->withHeaders($admin)->postJson("/api/v1/purchases/{$purchaseId}/receive", [
            'items' => [['purchase_item_id' => $lineId, 'quantity' => 1]],
        ])->assertStatus(422);

        // Cancel the draft.
        $this->withHeaders($admin)->postJson("/api/v1/purchases/{$purchaseId}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');

        $this->withHeaders($admin)->postJson("/api/v1/purchases/{$purchaseId}/receive", [
            'items' => [['purchase_item_id' => $lineId, 'quantity' => 1]],
        ])->assertStatus(422);
    }

    public function test_approval_requires_authorised_role(): void
    {
        $admin = $this->header($this->admin);
        $created = $this->withHeaders($admin)->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => '2026-02-01',
            'items' => [['item_id' => $this->item->id, 'quantity' => 5]],
        ])->json();

        $this->app['auth']->forgetGuards();

        $this->withHeaders($this->header($this->manager))
            ->postJson("/api/v1/purchases/{$created['id']}/approve")
            ->assertStatus(403);
    }

    public function test_receipt_reversal_restores_stock_and_is_not_repeatable(): void
    {
        $admin = $this->header($this->admin);
        $created = $this->withHeaders($admin)->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => '2026-02-01',
            'items' => [['item_id' => $this->item->id, 'quantity' => 5]],
        ])->json();
        $purchaseId = $created['id'];
        $lineId = $created['items'][0]['id'];

        $this->withHeaders($admin)->postJson("/api/v1/purchases/{$purchaseId}/submit");
        $this->withHeaders($admin)->postJson("/api/v1/purchases/{$purchaseId}/approve");
        $receipt = $this->withHeaders($admin)->postJson("/api/v1/purchases/{$purchaseId}/receive", [
            'items' => [['purchase_item_id' => $lineId, 'quantity' => 5]],
        ])->assertCreated()->json();

        $this->assertSame(5.0, (float) StockBalance::first()->quantity_on_hand);

        $this->withHeaders($admin)->postJson("/api/v1/purchases/{$purchaseId}/receipts/{$receipt['id']}/reverse", [
            'reason' => 'Damaged goods returned',
        ])->assertCreated();

        $this->assertSame(0.0, (float) StockBalance::first()->quantity_on_hand);

        // A receipt can only be reversed once.
        $this->withHeaders($admin)->postJson("/api/v1/purchases/{$purchaseId}/receipts/{$receipt['id']}/reverse", [
            'reason' => 'Duplicate reversal attempt',
        ])->assertStatus(422);
    }

    public function test_exports_return_csv_and_excel(): void
    {
        $admin = $this->header($this->admin);

        $csv = $this->withHeaders($admin)->get('/api/v1/exports/items?format=csv');
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('content-type'));

        $xlsx = $this->withHeaders($admin)->get('/api/v1/exports/stock-balances?format=xlsx');
        $xlsx->assertOk();
        $this->assertStringContainsString('spreadsheet', strtolower((string) $xlsx->headers->get('content-type')));
    }

    public function test_unknown_export_dataset_returns_not_found(): void
    {
        $this->withHeaders($this->header($this->admin))
            ->get('/api/v1/exports/nope')
            ->assertNotFound();
    }
}
