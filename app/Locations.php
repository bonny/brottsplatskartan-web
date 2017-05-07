<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Sofa\Eloquence\Eloquence;

class Locations extends Model
{

    use Eloquence;

    protected $fillable = [
        "prio",
        "name",
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = ['id', 'created_at', 'updated_at', 'crime_event_id'];
}
