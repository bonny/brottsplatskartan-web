<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Prunable;

/**
 * En visning av ett brott. Används för att t.ex. skapa
 * en topplista över de mest "populära" brotten.
 */
class CrimeView extends Model
{
    use Prunable;

    /**
     * Get the crime event that this is a view for.
     */
    public function crimeEvent(): BelongsTo
    {
        return $this->belongsTo('App\CrimeEvent');
    }

    /**
     * Get the prunable model query.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function prunable()
    {
        return static::where('created_at', '<=', now()->subMonths(12));
    }
}
