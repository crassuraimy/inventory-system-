<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    private function token(string $role = 'administrator'): string
    {
        return User::factory()->create(['role' => $role])->createToken('t')->plainTextToken;
    }

    private function itemPayload(string $name = 'Export Item'): array
    {
        $category = Category::create(['name' => 'Cat '.$name, 'is_active' => true]);
        $unit = Unit::create(['name' => 'Piece '.$name, 'symbol' => 'p'.substr(md5($name), 0, 4), 'is_active' => true]);

        return [
            'item_code' => 'EXP-'.substr(md5($name), 0, 6),
            'name' => $name,
            'item_type' => 'general',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'minimum_stock' => 5,
        ];
    }

    public function test_csv_export_returns_utf8_bom_and_csv_content_type(): void
    {
        $token = $this->token();
        $this->withHeader('Authorization', "Bearer $token")->postJson('/api/v1/items', $this->itemPayload());

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/exports/items?format=csv');

        $response->assertOk();
        $this->assertSame('text/csv; charset=UTF-8', $response->baseResponse->headers->get('Content-Type'));

        $csv = $response->streamedContent();
        $this->assertTrue(str_starts_with($csv, "\xEF\xBB\xBF"), 'CSV should start with a UTF-8 BOM.');
        $this->assertStringContainsString('Item Code', $csv);
    }

    public function test_spreadsheet_formula_injection_is_neutralised(): void
    {
        $token = $this->token();
        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/items', $this->itemPayload('=SUM(A1:A2)'));

        $csv = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/exports/items?format=csv')
            ->streamedContent();

        $this->assertStringContainsString("'=SUM(", $csv);

        $cells = [];
        foreach (array_filter(explode("\n", trim($csv))) as $line) {
            $cells = array_merge($cells, str_getcsv($line));
        }
        foreach ($cells as $cell) {
            $this->assertNotContains(
                substr((string) $cell, 0, 1),
                ['=', '+', '@', "\t", "\r"],
                "Export cell should not begin with a spreadsheet formula character: {$cell}"
            );
        }
    }

    public function test_excel_export_streams_a_downloadable_workbook(): void
    {
        $token = $this->token();
        $this->withHeader('Authorization', "Bearer $token")->postJson('/api/v1/items', $this->itemPayload());

        $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/exports/items?format=xlsx')
            ->assertOk();
    }

    public function test_unsupported_export_format_is_rejected(): void
    {
        $token = $this->token();
        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/exports/items?format=pdf')
            ->assertStatus(422);
    }

    public function test_unknown_export_dataset_returns_not_found(): void
    {
        $token = $this->token();
        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/exports/does-not-exist')
            ->assertNotFound();
    }

    public function test_exports_require_authentication(): void
    {
        $this->getJson('/api/v1/exports/items')->assertUnauthorized();
    }

    public function test_authenticated_non_admin_can_still_export(): void
    {
        $token = $this->token('storekeeper');
        $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/exports/stock-balances?format=csv')
            ->assertOk();
    }

    public function test_meta_returns_company_details(): void
    {
        Setting::create(['key' => 'company_name', 'value' => 'Acme Supplies']);
        $token = $this->token();

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/meta')
            ->assertOk()
            ->assertJson(['company_name' => 'Acme Supplies']);
    }
}
