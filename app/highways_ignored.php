<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class highways_ignored extends Model
{
    protected $table = 'highways_ignored';

    protected $fillable = [
        'name'
    ];
}
