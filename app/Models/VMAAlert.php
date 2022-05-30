<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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


    /**
     * Hämta hela textbeskrivningen av en händelse.
     * 
     * @return string 
     */
    public function getDescription(): string
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

    /**
     * Hämtar description-texten som collection, där varje item är en rad.
     * 
     * @return Collection
     */
    public function getDescriptionLines(): Collection {
        $lines = Str::of($this->getDescription())
                    ->explode("\n")
                    ->reject(fn($line) => empty($line) || $line === "\r");
        return $lines;
    }
    
    public function nl2p($txt){
        return str_replace(["\r\n", "\n\r", "\n", "\r"], '</p><p>', '<p>' . $txt . '</p>');
    }

    /**
     * Hämtar texten till en händelse, minus första raden (som man får med getShortDescription())
     * 
     * @return string 
     */
    public function getText(): string {
        $lines = $this->getDescriptionLines()->slice(1);
        $text = $lines->join("\n");
        $text = $this->nl2p($text);
        return $text;
    }

    /**
     * Ger en kort text av händelsen, för att användas i listor osv.
     * Klipper getText()-texten efter n antal ord.
     * @return string 
     */
    public function getTeaser($numWords = 20): string {
        $text = $this->getText();
        $text = strip_tags($text);
        $text = Str::words($text, $numWords);
        return $text;
    }
}