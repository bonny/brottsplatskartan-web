<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class VMAAlert extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'vma_alerts';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'original_message' => 'array',
    ];

    public function getDescription()
    {
        return $this->original_message['info'][0]['description'];
    }

    /**
     * Get första raden av beskrivningen,
     * oftast en text i stil med:
     * "Viktigt meddelande till allmänheten i Uppsala i Uppsala kommun, Uppsala län."
     * 
     * @return string 
     */
    public function getShortDescription()
    {
        $firstDescriptionLine = Str::of($this->getDescription())->explode("\n")->first();
        return $firstDescriptionLine;
    }

    public function getPermalink()
    {
        $slug = "{$this->getHumanSentDate()}-" . Str::slug($this->getShortDescription()) . "-{$this->id}";

        $permalink = route(
            'vma-single',
            [
                'slug' => Str::slug($slug)
            ]
        );
        return $permalink;
    }

    public function getHumanSentDate(): string
    {
        $date = new Carbon($this->sent);
        $date = $date->isoFormat('D MMM YYYY');
        return $date;
    }

    public function getHumanSentDateTime(): string
    {
        $date = new Carbon($this->sent);
        $date = $date->isoFormat('D MMM YYYY LT');
        return $date;
    }

    public function getText(): string {
        $text = $this->getDescription();
        $text = nl2br($text);
        return $text;
    }

    public function getTeaser(): string {
        return $this->getText();
    }
}
