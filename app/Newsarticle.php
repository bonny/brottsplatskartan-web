<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Newsarticle extends Model
{
    public function crimeevent()
    {
        return $this->belongsTo('App\CrimeEvent');
    }
}
