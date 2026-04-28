<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlySummary extends Model
{
    protected $fillable = [
        'area',
        'year',
        'month',
        'summary',
        'events_data',
        'events_count',
        'prev_month_count',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'events_count' => 'integer',
        'prev_month_count' => 'integer',
        'events_data' => 'array',
    ];
}
