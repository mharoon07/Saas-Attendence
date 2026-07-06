<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Addition extends Model
{
    use HasFactory, LogsActivity;
    protected $guarded = [];
    protected $casts = [
        'custom_items' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'text']);
    }

    public function getSum()
    {
        $customItemsSum = 0;
        if (is_array($this->custom_items)) {
            foreach ($this->custom_items as $item) {
                $customItemsSum += $item['amount'] ?? 0;
            }
        }

        return $customItemsSum + $this->overtime;
    }
    public function payroll(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }
}
