<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistItem extends Model
{
    protected $fillable = ['checklist_id', 'category_id', 'name', 'target_price', 'sort_order'];

    protected $casts = [
        'target_price' => 'decimal:2',
    ];

    public function checklist()
    {
        return $this->belongsTo(Checklist::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
