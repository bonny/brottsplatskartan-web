<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Blog extends Model
{
    protected $table = 'blog';

    public function getCreatedAtFormatted($format = '%d %B %Y')
    {
        return Carbon::parse($this->created_at)->formatLocalized($format);
    }

    public function getCreatedAtAsW3cString()
    {
        return Carbon::parse($this->created_at)->toW3cString();
    }

    public function getExcerpt($length = 50)
    {
        $str = $this->content;
        $str = \Markdown::parse($str);
        $str = strip_tags($str);
        $str = Str::words($str, $length);

        return $str;
    }

    public function getPermalink()
    {
        return route(
            'blogItem',
            [
                'year' => date('Y', $this->created_at->timestamp),
                'slug' => $this->slug
            ]
        );
    }
}
