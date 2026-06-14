<?php

namespace App\Models;

use App\Services\StaticMapUrlBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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

    /**
     * SCB county-nummer → svenskt län-namn. Speglar mappningen i
     * `App\Console\Commands\TrafikverketFetch::COUNTY_NAMES` — håll synkad.
     */
    public const COUNTY_NAMES = [
        1 => 'Stockholms län',
        3 => 'Uppsala län',
        4 => 'Södermanlands län',
        5 => 'Östergötlands län',
        6 => 'Jönköpings län',
        7 => 'Kronobergs län',
        8 => 'Kalmar län',
        9 => 'Gotlands län',
        10 => 'Blekinge län',
        12 => 'Skåne län',
        13 => 'Hallands län',
        14 => 'Västra Götalands län',
        17 => 'Värmlands län',
        18 => 'Örebro län',
        19 => 'Västmanlands län',
        20 => 'Dalarnas län',
        21 => 'Gävleborgs län',
        22 => 'Västernorrlands län',
        23 => 'Jämtlands län',
        24 => 'Västerbottens län',
        25 => 'Norrbottens län',
    ];

    /**
     * Reverse-lookup: län-namn → SCB county-nummer.
     */
    public static function getCountyNoForLanName(string $lanName): ?int
    {
        $reversed = array_flip(self::COUNTY_NAMES);
        return $reversed[$lanName] ?? null;
    }

    /**
     * SEO-slug för detaljsidan (todo #89): typ-väg-län-id, t.ex.
     * `trafikmeddelande-e4-vasterbottens-lan-36271`. Id:t sist gör lookup
     * snabb och robust (samma mönster som CrimeEvent-permalinks). Faller
     * tillbaka på location_descriptor när vägnummer saknas, och på bara id:t
     * om inget textunderlag finns.
     */
    public function getSlug(): string
    {
        $parts = array_filter([
            $this->message_type,
            $this->road_number ?: $this->location_descriptor,
            $this->administrative_area_level_1,
        ]);

        $base = trim(Str::slug(implode(' ', $parts)), '-');

        // Kapa långa location_descriptor-fallbacks så URL:en inte skenar.
        if (mb_strlen($base) > 60) {
            $base = rtrim(mb_substr($base, 0, 60), '-');
        }

        // Trimma hängande bindeord i slutet — capen ovan klipper ofta mitt i
        // en fras och lämnar kvar en preposition (t.ex. "...uppsala-lan-i").
        // Slug:en är redan ascii-iserad av Str::slug (å→a, ö→o), så orden är
        // gemena utan diakritiska tecken.
        $stopwords = ['i', 'pa', 'vid', 'och', 'av', 'till', 'for', 'med', 'mot', 'om', 'ur', 'samt'];
        $segments = explode('-', $base);
        while (count($segments) > 1 && in_array(end($segments), $stopwords, true)) {
            array_pop($segments);
        }
        $base = implode('-', $segments);

        return $base !== '' ? "{$base}-{$this->id}" : (string) $this->id;
    }

    /**
     * Kanonisk permalink till detaljsidan (todo #89).
     */
    public function getPermalink(bool $absolute = false): string
    {
        return route('trafik.show', ['slug' => $this->getSlug()], $absolute);
    }

    /**
     * Statisk kartbild (ingen JS) med röd punkt på platsen — för CWV/LCP
     * på detaljsidan (todo #89). Returnerar null om koordinater saknas.
     */
    public function getStaticMapUrl(int $width = 640, int $height = 360, int $scale = 1): ?string
    {
        if (!$this->lat || !$this->lng) {
            return null;
        }

        return app(StaticMapUrlBuilder::class)
            ->pointUrl((float) $this->lat, (float) $this->lng, $width, $height, $scale);
    }
}
