<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Debt extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'type', 'party_name', 'principal_amount', 'interest_type',
        'interest_rate', 'total_amount', 'paid_amount', 'tenor_months',
        'start_date', 'due_day', 'wallet_id', 'auto_debet', 'auto_wallet_id',
        'status', 'notes', 'evidence_path', 'created_by',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'start_date' => 'date',
        'auto_debet' => 'boolean',
        'tenor_months' => 'integer',
        'due_day' => 'integer',
    ];

    protected $appends = ['evidence_url', 'remaining_amount'];

    public function getEvidenceUrlAttribute()
    {
        return $this->evidence_path ? asset('storage/'.$this->evidence_path) : null;
    }

    public function getRemainingAmountAttribute()
    {
        return round($this->total_amount - $this->paid_amount, 2);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function autoWallet()
    {
        return $this->belongsTo(Wallet::class, 'auto_wallet_id');
    }

    public function installments()
    {
        return $this->hasMany(DebtInstallment::class)->orderBy('installment_number');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
