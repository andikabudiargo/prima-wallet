<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->enum('category', ['cash', 'bank', 'e_wallet', 'credit_card', 'investment', 'other'])->default('cash');
            $table->string('name');
            $table->string('reference')->nullable();
            $table->decimal('initial_balance', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};