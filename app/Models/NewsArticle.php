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
     * Story-nyckel för dedup av nästan-dubbletter: `källa|normaliserad titel`.
     * Samma story återkommer ofta som flera rader — särskilt svt-texttv som
     * hämtas om vid varje sid-uppdatering (samma titel, ny content_hash → ny
     * rad). Både AI-matchningen (`MatchEventNews`) och visnings-widgeten
     * (`Helper::getLatestNewsForPlace`) dedupar på denna nyckel, så de aldrig
     * divergerar (todo #82).
     */
    public static function storyKey(?string $source, ?string $title): string
    {
        return (string) $source . '|' . self::normalizeTitle((string) $title);
    }

    /**
     * Normaliserar en artikeltitel för dedup: lowercase, kollapsad whitespace,
     * och borttaget trailing "…191"-mönster (svt-texttv-sidor får ofta ett
     * sidnummer efter en ellips som annars gör annars identiska titlar unika).
     */
    public static function normalizeTitle(string $title): string
    {
        $t = mb_strtolower(trim($title));
        $t = preg_replace('/\.{2,}[\d\s]*$/u', '', $t) ?? $t;

        return trim(preg_replace('/\s+/u', ' ', $t) ?? $t);
    }

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
