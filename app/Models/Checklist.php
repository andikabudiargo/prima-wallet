<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Checklist extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'name', 'wallet_id', 'created_by'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function items()
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('sort_order');
    }

    public function runs()
    {
        return $this->hasMany(ChecklistRun::class)->orderBy('created_at', 'desc');
    }

    public function activeRun()
    {
        return $this->hasOne(ChecklistRun::class)->where('status', 'active')->latestOfMany();
    }
}
