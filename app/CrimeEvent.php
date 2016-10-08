<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CrimeEvent extends Model
{

    protected $fillable = [
        'title',
        'description',
        'permalink',
        'pubdate',
        'pubdate_iso8601',
        'md5',
        'parsed_date',
        'parsed_title',
        'parsed_title_location',
        'parsed_content_location',
        'parsed_content',
        'parsed_lng',
        'parsed_lat',
        'parsed_teaser',
        'parsed_content',
    ];

    /**
     * Get the comments for the blog post.
     */
    public function locations()
    {
        return $this->hasMany('App\Locations');
    }

}
