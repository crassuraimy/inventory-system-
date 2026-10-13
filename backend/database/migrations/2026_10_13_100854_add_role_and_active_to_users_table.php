<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 50)->default('administrator')->after('email');
            }
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('role');
            }
            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
        });

        Schema::table('units', function (Blueprint $table) {
            if (! Schema::hasColumn('units', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });

        Schema::table('items', function (Blueprint $table) {
            if (! Schema::hasColumn('items', 'target_stock_level')) {
                $table->decimal('target_stock_level', 14, 3)->nullable();
            }
            if (! Schema::hasColumn('items', 'preferred_supplier_id')) {
                $table->foreignId('preferred_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            }
            if (! Schema::hasColumn('items', 'unit_cost')) {
                $table->decimal('unit_cost', 14, 4)->nullable();
            }
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_movements', 'notes')) {
                $table->text('notes')->nullable();
            }
            if (! Schema::hasColumn('stock_movements', 'balance_after')) {
                $table->decimal('balance_after', 14, 3)->nullable();
            }
            if (! Schema::hasColumn('stock_movements', 'request_key')) {
                $table->string('request_key', 100)->nullable()->unique();
            }
        });

        Schema::table('purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('purchases', 'status')) {
            } // already
        });

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action', 100);
                $table->string('entity_type', 100)->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->text('reason')->nullable();
                $table->string('reference_number')->nullable();
                $table->ipAddress('ip_address')->nullable();
                $table->timestamps();
                $table->index(['entity_type', 'entity_id']);
            });
        }

        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('group', 50)->default('general');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('settings');
        Schema::table('stock_movements', function (Blueprint $table) {
            foreach (['notes', 'balance_after', 'request_key'] as $col) {
                if (Schema::hasColumn('stock_movements', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('items', function (Blueprint $table) {
            foreach (['target_stock_level', 'unit_cost'] as $col) {
                if (Schema::hasColumn('items', $col)) {
                    $table->dropColumn($col);
                }
            }
            if (Schema::hasColumn('items', 'preferred_supplier_id')) {
                $table->dropConstrainedForeignId('preferred_supplier_id');
            }
        });
        Schema::table('units', function (Blueprint $table) {
            if (Schema::hasColumn('units', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
        Schema::table('users', function (Blueprint $table) {
            foreach (['role', 'is_active', 'last_login_at'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
