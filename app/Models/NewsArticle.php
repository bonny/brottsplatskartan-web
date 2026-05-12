<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsArticle extends Model
{
    protected $table = 'news_articles';

    protected $guarded = [];

    protected $casts = [
        'pubdate' => 'datetime',
        'fetched_at' => 'datetime',
    ];

    private const SOURCE_DISPLAY_NAMES = [
        'google-news-se' => 'Google News',
        'svt-texttv' => 'SVT Text TV',
        'svt' => 'SVT',
        'svt-inrikes' => 'SVT Inrikes',
        'aftonbladet' => 'Aftonbladet',
        'expressen' => 'Expressen',
        'expressen-gt' => 'GT',
        'expressen-kvp' => 'Kvällsposten',
        'dn' => 'Dagens Nyheter',
        'dn-sthlm' => 'DN Stockholm',
        'svd' => 'Svenska Dagbladet',
    ];

    /**
     * Snyggt visningsnamn för `source`-slug:en. Föll tillbaka till slug:en
     * själv om vi inte har en mappning (för svt-{lan}-feedsen genereras
     * `SVT <Lan>`).
     */
    public function getSourceDisplayName(): string
    {
        return self::sourceDisplayName((string) $this->source);
    }

    /**
     * Static variant för callers som har en source-slug men ingen modell-
     * instans (t.ex. blade-partials med DB::table-rader).
     */
    public static function sourceDisplayName(string $source): string
    {
        if (isset(self::SOURCE_DISPLAY_NAMES[$source])) {
            return self::SOURCE_DISPLAY_NAMES[$source];
        }
        if (str_starts_with($source, 'svt-')) {
            return 'SVT ' . ucfirst(substr($source, 4));
        }
        return $source;
    }
}
