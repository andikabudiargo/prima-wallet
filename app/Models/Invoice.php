<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'user_id', 'invoice_number', 'invoice_date', 'customer_name', 'items',
        'subtotal', 'total', 'notes',
        'business_name', 'business_tagline', 'business_address', 'business_phone', 'business_logo_path',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date:Y-m-d',
        'items' => 'array',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected $appends = ['business_logo_url', 'paid_amount', 'remaining_amount', 'status'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function getBusinessLogoUrlAttribute(): ?string
    {
        return $this->business_logo_path ? asset('storage/'.$this->business_logo_path) : null;
    }

    /**
     * Jumlah yang sudah masuk lewat transaksi pemasukan yang mereferensikan
     * invoice ini - dihitung langsung dari transaksi, bukan disimpan
     * terpisah, supaya selalu sinkron dengan riwayat kas yang sebenarnya.
     */
    public function getPaidAmountAttribute(): float
    {
        if ($this->relationLoaded('transactions')) {
            return round((float) $this->transactions->sum('amount'), 2);
        }

        return round((float) $this->transactions()->sum('amount'), 2);
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, round((float) $this->total - $this->paid_amount, 2));
    }

    /**
     * 'unpaid' | 'partial' | 'paid'
     */
    public function getStatusAttribute(): string
    {
        $paid = $this->paid_amount;

        if ($paid <= 0) {
            return 'unpaid';
        }

        if ($paid >= (float) $this->total) {
            return 'paid';
        }

        return 'partial';
    }
}
