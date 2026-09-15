<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->foreignId('to_wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('note')->nullable();
            $table->date('transfer_date');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'transfer_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
