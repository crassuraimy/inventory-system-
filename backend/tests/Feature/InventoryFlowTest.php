<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryFlowTest extends TestCase
{
    use RefreshDatabase;

    private function auth(): string
    {
        $user = User::factory()->create(['role' => 'administrator']);
        $token = $user->createToken('t')->plainTextToken;

        return $token;
    }

    public function test_auth_me_and_logout(): void
    {
        $token = $this->auth();
        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/auth/me')->assertOk()->assertJsonStructure(['id', 'email', 'role']);
        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/auth/logout')->assertOk();
    }

    public function test_item_crud_and_low_stock(): void
    {
        $token = $this->auth();
        $category = Category::create(['name' => 'Cat', 'is_active' => true]);
        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pcs', 'is_active' => true]);
        $res = $this->withHeader('Authorization', "Bearer $token")->postJson('/api/v1/items', [
            'item_code' => 'T1', 'name' => 'Test Item', 'item_type' => 'general',
            'category_id' => $category->id, 'unit_id' => $unit->id, 'minimum_stock' => 5,
        ]);
        $res->assertCreated();
        $itemId = $res->json('id');

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/reports/low-stock')->assertOk();
    }

    public function test_stock_in_out_adjustment_flow(): void
    {
        $token = $this->auth();
        $category = Category::create(['name' => 'Cat', 'is_active' => true]);
        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pcs', 'is_active' => true]);
        $item = $this->withHeader('Authorization', "Bearer $token")->postJson('/api/v1/items', [
            'item_code' => 'T2', 'name' => 'Flow Item', 'item_type' => 'general',
            'category_id' => $category->id, 'unit_id' => $unit->id, 'minimum_stock' => 5,
        ])->json();

        // opening
        $this->withHeader('Authorization', "Bearer $token")->postJson('/api/v1/stock/opening', [
            'item_id' => $item['id'], 'movement_date' => '2026-01-01', 'quantity' => 10,
        ])->assertCreated();

        // stock in
        $this->withHeader('Authorization', "Bearer $token")->postJson('/api/v1/stock-in', [
            'item_id' => $item['id'], 'movement_date' => '2026-01-02', 'quantity' => 5,
        ])->assertCreated();

        // stock out
        $this->withHeader('Authorization', "Bearer $token")->postJson('/api/v1/stock-out', [
            'item_id' => $item['id'], 'movement_date' => '2026-01-03', 'quantity' => 3,
        ])->assertCreated();

        // insufficient stock
        $this->withHeader('Authorization', "Bearer $token")->postJson('/api/v1/stock-out', [
            'item_id' => $item['id'], 'movement_date' => '2026-01-04', 'quantity' => 100,
        ])->assertStatus(422);

        // adjustment
        $this->withHeader('Authorization', "Bearer $token")->postJson('/api/v1/stock-adjustments', [
            'item_id' => $item['id'], 'mode' => 'set', 'quantity' => 20, 'reason' => 'count',
        ])->assertCreated();

        // monthly
        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/reports/monthly-inventory?month=2026-01&item_id='.$item['id'])
            ->assertOk();
    }

    public function test_category_unit_supplier_user_audit_settings(): void
    {
        $token = $this->auth();
        $this->withHeader('Authorization', "Bearer $token")->postJson('/api/v1/categories', ['name' => 'NewCat'])->assertCreated();
        $this->withHeader('Authorization', "Bearer $token")->postJson('/api/v1/units', ['name' => 'Gram', 'symbol' => 'g'])->assertCreated();
        $sup = $this->withHeader('Authorization', "Bearer $token")->postJson('/api/v1/suppliers', ['name' => 'Supp'])->assertCreated();
        $this->withHeader('Authorization', "Bearer $token")->getJson('/api/v1/suppliers/'.$sup->json('id').'/receipts')->assertOk();
        $this->withHeader('Authorization', "Bearer $token")->getJson('/api/v1/audit-logs')->assertOk();
        $this->withHeader('Authorization', "Bearer $token")->putJson('/api/v1/settings/company_name', ['value' => 'Acme'])->assertSuccessful();
    }

    public function test_reversal(): void
    {
        $token = $this->auth();
        $category = Category::create(['name' => 'Cat', 'is_active' => true]);
        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pcs', 'is_active' => true]);
        $item = $this->withHeader('Authorization', "Bearer $token")->postJson('/api/v1/items', [
            'item_code' => 'T3', 'name' => 'Rev Item', 'item_type' => 'general',
            'category_id' => $category->id, 'unit_id' => $unit->id, 'minimum_stock' => 0,
        ])->json();
        $opening = $this->withHeader('Authorization', "Bearer $token")->postJson('/api/v1/stock/opening', [
            'item_id' => $item['id'], 'movement_date' => '2026-01-01', 'quantity' => 10,
        ])->json();
        $this->withHeader('Authorization', "Bearer $token")->postJson('/api/v1/stock-adjustments/'.$opening['id'].'/reverse', [
            'reason' => 'wrong entry',
        ])->assertCreated();
    }
}
