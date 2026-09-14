<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('target_price', 15, 2);
            $table->decimal('actual_price', 15, 2)->nullable();
            $table->boolean('is_checked')->default(false);
            $table->timestamp('checked_at')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['checklist_run_id', 'is_checked']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_run_items');
    }
};
