<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['hutang', 'piutang']);
            $table->string('party_name');
            $table->decimal('principal_amount', 15, 2);
            $table->enum('interest_type', ['none', 'flat', 'declining'])->default('none');
            $table->decimal('interest_rate', 5, 2)->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->unsignedSmallInteger('tenor_months');
            $table->date('start_date');
            $table->unsignedTinyInteger('due_day');
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('auto_debet')->default(false);
            $table->foreignId('auto_wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->enum('status', ['active', 'paid_off', 'cancelled'])->default('active');
            $table->string('notes')->nullable();
            $table->string('evidence_path')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debts');
    }
};
