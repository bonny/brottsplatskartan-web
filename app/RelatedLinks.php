<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modell för relaterade länkar.
 */
class RelatedLinks extends Model
{
    use SoftDeletes;
}
