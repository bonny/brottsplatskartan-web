<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * En visning av ett brott. Används för att t.ex. skapa
 * en topplista över de mest "populära" brotten.
 */
class CrimeView extends Model
{
    /**
     * Get the crime record associated with this view.
     */
    // public function crimeEvent()
    // {
    //     return $this->hasOne('App\CrimeEvent');
    // }

    /**
     * Get the crime event that this is a view for.
     */
    public function crimeEvent()
    {
        return $this->belongsTo('App\CrimeEvent');
    }
}
