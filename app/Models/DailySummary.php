<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySummary extends Model
{
    protected $fillable = [
        'summary_date',
        'area',
        'summary',
        'events_data',
        'events_count'
    ];

    protected $casts = [
        'summary_date' => 'date',
        'events_data' => 'array'
    ];
}
