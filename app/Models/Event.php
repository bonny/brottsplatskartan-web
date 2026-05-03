<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Polymorf events-tabell (todo #50, Fas 1).
 * Trafikverket först, framtida källor (#51) återanvänder samma schema via `source`.
 */
class Event extends Model
{
    protected $table = 'events';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'severity_code' => 'integer',
        'suspended' => 'boolean',
        'last_seen_active_at' => 'datetime',
        'county_no' => 'integer',
        'lat' => 'float',
        'lng' => 'float',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'created_time' => 'datetime',
        'modified_time' => 'datetime',
        'imported_at' => 'datetime',
        'related_event_id' => 'integer',
        'payload' => 'array',
    ];

    /**
     * Aktiva trafikhändelser: `Suspended=true` filtreras vid import, så det
     * räcker att kolla end_time här.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('end_time')->orWhere('end_time', '>', now());
        });
    }

    public function scopeForSource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    public function scopeForCounty(Builder $query, int $countyNo): Builder
    {
        return $query->whereExists(function ($q) use ($countyNo) {
            $q->select('event_id')
                ->from('event_counties')
                ->whereColumn('event_counties.event_id', 'events.id')
                ->where('event_counties.county_no', $countyNo);
        });
    }
}
