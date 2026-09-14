<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wallet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'code', 'category', 'name', 'reference',
        'initial_balance', 'balance', 'currency', 'icon', 'color',
        'is_active', 'is_primary', 'sort_order', 'created_by',
    ];

    protected $casts = [
        'initial_balance' => 'decimal:2',
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
        'is_primary' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}