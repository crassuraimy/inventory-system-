<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            // Laravel's enum() creates a CHECK constraint on PostgreSQL. Replace it
            // with a free-form status column that supports the full PO lifecycle.
            DB::statement('ALTER TABLE purchases DROP CONSTRAINT IF EXISTS purchases_status_check');
            DB::statement('ALTER TABLE purchases ALTER COLUMN status TYPE varchar(30) USING status::varchar');
            DB::statement("ALTER TABLE purchases ALTER COLUMN status SET DEFAULT 'draft'");
            DB::statement('ALTER TABLE purchases ALTER COLUMN status SET NOT NULL');

            return;
        }

        Schema::table('purchases', function (Blueprint $table) {
            $table->string('status', 30)->default('draft')->change();
        });
    }

    public function down(): void
    {
        // No destructive rollback; statuses remain free-form strings.
    }
};
