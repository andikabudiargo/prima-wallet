<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->unique();
            $table->date('invoice_date');
            $table->string('customer_name');
            // [{name, qty, price}, ...] - satu invoice bisa banyak baris barang,
            // disimpan sebagai JSON supaya tidak perlu tabel terpisah.
            $table->json('items');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('total', 15, 2);
            $table->string('notes')->nullable();
            // Snapshot profil usaha SAAT invoice dibuat, supaya invoice lama
            // tidak ikut berubah kalau profil usaha diedit belakangan.
            $table->string('business_name')->nullable();
            $table->string('business_tagline')->nullable();
            $table->string('business_address')->nullable();
            $table->string('business_phone')->nullable();
            $table->string('business_logo_path')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['user_id', 'invoice_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
