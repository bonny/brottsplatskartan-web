<?php

namespace App\Models;

use App\CrimeEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Haiku-validerad koppling event ↔ artikel (todo #63 fas 1).
 */
class CrimeEventNews extends Model
{
    protected $table = 'crime_event_news';

    protected $guarded = [];

    protected $casts = [
        'matched_at' => 'datetime',
    ];

    public function crimeEvent(): BelongsTo
    {
        return $this->belongsTo(CrimeEvent::class, 'crime_event_id');
    }

    public function newsArticle(): BelongsTo
    {
        return $this->belongsTo(NewsArticle::class, 'news_article_id');
    }
}
