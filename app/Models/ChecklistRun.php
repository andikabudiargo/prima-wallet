<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistRun extends Model
{
    protected $fillable = ['checklist_id', 'status', 'started_at', 'completed_at'];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = ['target_total', 'actual_total'];

    public function checklist()
    {
        return $this->belongsTo(Checklist::class);
    }

    public function items()
    {
        return $this->hasMany(ChecklistRunItem::class)->orderBy('sort_order');
    }

    public function getTargetTotalAttribute()
    {
        return round($this->items->sum(fn (ChecklistRunItem $item) => (float) $item->target_price), 2);
    }

    public function getActualTotalAttribute()
    {
        return round(
            $this->items->where('is_checked', true)->sum(fn (ChecklistRunItem $item) => (float) $item->actual_price),
            2
        );
    }
}
