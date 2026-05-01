<?php

namespace App\Models;

use App\Place;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceNews extends Model
{
    protected $table = 'place_news';

    protected $guarded = [];

    protected $casts = [
        'pubdate' => 'datetime',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'place_id');
    }

    public function newsArticle(): BelongsTo
    {
        return $this->belongsTo(NewsArticle::class, 'news_article_id');
    }
}
