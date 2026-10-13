<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_receipts', 'request_key')) {
                $table->string('request_key', 100)->nullable()->unique()->after('receipt_number');
            }
            if (! Schema::hasColumn('purchase_receipts', 'reversed_at')) {
                $table->timestamp('reversed_at')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('purchase_receipts', 'reversed_by')) {
                $table->foreignId('reversed_by')->nullable()->after('reversed_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('purchase_receipts', 'reversal_reason')) {
                $table->text('reversal_reason')->nullable()->after('reversed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $table) {
            foreach (['request_key', 'reversed_at', 'reversal_reason'] as $column) {
                if (Schema::hasColumn('purchase_receipts', $column)) {
                    $table->dropColumn($column);
                }
            }
            if (Schema::hasColumn('purchase_receipts', 'reversed_by')) {
                $table->dropConstrainedForeignId('reversed_by');
            }
        });
    }
};
