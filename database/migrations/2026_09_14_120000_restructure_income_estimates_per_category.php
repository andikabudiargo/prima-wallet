<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Estimasi pemasukan diubah jadi per-kategori (rincian sumber), sama
        // strukturnya dengan tabel budgets tapi untuk kategori income.
        Schema::dropIfExists('income_estimates');

        Schema::create('income_estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->decimal('amount', 15, 2);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['user_id', 'category_id', 'month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_estimates');

        Schema::create('income_estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->decimal('amount', 15, 2);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['user_id', 'month', 'year']);
        });
    }
};
