<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
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
}
