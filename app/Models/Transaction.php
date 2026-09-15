<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'wallet_id', 'type', 'category_id', 'amount',
        'description', 'evidence_path', 'transaction_date', 'created_by',
        'debt_id', 'debt_installment_id', 'checklist_run_item_id', 'recurring_transaction_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    protected $appends = ['evidence_url'];

    public function getEvidenceUrlAttribute()
    {
        return $this->evidence_path ? asset('storage/' . $this->evidence_path) : null;
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function category()
{
    return $this->belongsTo(Category::class);
}

    public function debt()
    {
        return $this->belongsTo(Debt::class);
    }

    public function debtInstallment()
    {
        return $this->belongsTo(DebtInstallment::class);
    }

    public function checklistRunItem()
    {
        return $this->belongsTo(ChecklistRunItem::class);
    }

    public function recurringTransaction()
    {
        return $this->belongsTo(RecurringTransaction::class);
    }
}