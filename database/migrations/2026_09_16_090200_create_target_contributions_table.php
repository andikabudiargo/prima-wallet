<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('target_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('note')->nullable();
            $table->date('contribution_date');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('target_contributions');
    }
};
