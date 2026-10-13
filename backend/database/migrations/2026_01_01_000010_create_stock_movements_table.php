<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->date('movement_date');
            $table->enum('movement_type', [
                'opening', 'purchase', 'issue', 'adjustment_in', 'adjustment_out'
            ]);
            $table->decimal('quantity_in', 14, 3)->default(0);
            $table->decimal('quantity_out', 14, 3)->default(0);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['item_id', 'movement_date', 'id']);
            $table->index(['reference_type', 'reference_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('stock_movements'); }
};
