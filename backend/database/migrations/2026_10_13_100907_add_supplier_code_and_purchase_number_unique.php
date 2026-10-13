<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (! Schema::hasColumn('suppliers', 'supplier_code')) {
                $table->string('supplier_code', 50)->nullable()->unique()->after('id');
            }
        });

        // Backfill supplier codes.
        $suppliers = DB::table('suppliers')->whereNull('supplier_code')->get();
        foreach ($suppliers as $supplier) {
            DB::table('suppliers')->where('id', $supplier->id)->update([
                'supplier_code' => 'SUP-'.str_pad((string) $supplier->id, 4, '0', STR_PAD_LEFT),
            ]);
        }

        // Unique numbers on purchase/issue headers where missing.
        Schema::table('purchases', function (Blueprint $table) {
            if (! DB::connection()->getSchemaBuilder()->hasColumn('purchases', 'purchase_number')) {
                // noop
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (Schema::hasColumn('suppliers', 'supplier_code')) {
                $table->dropUnique(['supplier_code']);
                $table->dropColumn('supplier_code');
            }
        });
    }
};
