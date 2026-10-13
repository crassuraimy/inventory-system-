<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('quantity_on_hand', 14, 3)->default(0);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('stock_balances'); }
};
