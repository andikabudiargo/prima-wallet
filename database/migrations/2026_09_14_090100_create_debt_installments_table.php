<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->date('due_date');
            $table->decimal('amount_due', 15, 2);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->enum('status', ['upcoming', 'paid', 'overdue'])->default('upcoming');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['debt_id', 'installment_number']);
            $table->index(['debt_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_installments');
    }
};
