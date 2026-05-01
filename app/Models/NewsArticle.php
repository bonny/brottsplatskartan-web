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
}
