<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistRunItem extends Model
{
    protected $fillable = [
        'checklist_run_id', 'checklist_item_id', 'category_id', 'name', 'target_price',
        'actual_price', 'is_checked', 'checked_at', 'transaction_id', 'sort_order',
    ];

    protected $casts = [
        'target_price' => 'decimal:2',
        'actual_price' => 'decimal:2',
        'is_checked' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function run()
    {
        return $this->belongsTo(ChecklistRun::class, 'checklist_run_id');
    }

    public function templateItem()
    {
        return $this->belongsTo(ChecklistItem::class, 'checklist_item_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
