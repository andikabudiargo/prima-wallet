<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TargetContribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'target_id', 'amount', 'note', 'contribution_date', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'contribution_date' => 'date:Y-m-d',
    ];

    public function target()
    {
        return $this->belongsTo(Target::class);
    }
}
