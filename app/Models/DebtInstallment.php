<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DebtInstallment extends Model
{
    protected $fillable = [
        'debt_id', 'installment_number', 'due_date', 'amount_due',
        'amount_paid', 'status', 'paid_at',
    ];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function debt()
    {
        return $this->belongsTo(Debt::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
