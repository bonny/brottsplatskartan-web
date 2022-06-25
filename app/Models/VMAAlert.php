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
        return $this->original_message['info'][0]['description'] ?? '';
    }

    /**
     * Ger första raden av beskrivningen,
     * oftast en text i stil med:
     * "Viktigt meddelande till allmänheten i Uppsala i Uppsala kommun, Uppsala län."
     * 
     * @return string 
     */
    public function getShortDescription()
    {
        $line = $this->getDescriptionLines()->slice(0, 1)->first();
        return $line;
    }

    /**
     * Ger första raden av beskrivningen,
     * oftast en text i stil med:
     * "Viktigt meddelande till allmänheten i Uppsala i Uppsala kommun, Uppsala län."
     * 
     * @return string 
     */
    public function getDescriptionSecondLine()
    {
        $line = $this->getDescriptionLines()->slice(1, 1)->first();
        return $line;
    }

    public function getSlug() {
        return Str::slug($this->getHumanSentDate()) . "-" . Str::slug($this->getShortDescription()) . "-{$this->id}";
    }

    public function getPermalink()
    {
        $permalink = route(
            'vma-single',
            [
                'slug' => $this->getSlug()
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

    public function getIsoSentDateTime(): string {
        $date = new Carbon($this->sent);
        return $date->toISOString();
    }

    /**
     * Hämtar description-texten som collection, där varje item är en rad.
     * 
     * @return Collection
     */
    public function getDescriptionLines(): Collection {
        $lines = Str::of($this->getDescription())
                    ->explode("\n")
                    ->reject(fn($line) => empty($line) || $line === "\r")
                    ->values();
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
        $text = $lines->join(" \n");
        $text = $this->nl2p($text);
        return $text;
    }

    /**
     * Ger en kort text av händelsen, för att användas i listor osv.
     * Klipper getText()-texten efter n antal ord.
     * @return string 
     */
    public function getTeaser($numWords = 30): string {
        $text = $this->getText();
        $text = strip_tags($text);
        $text = Str::words($text, $numWords);
        return $text;
    }

    public function getOriginalMessageAsPrettyJson(): string {
        return json_encode($this->original_message, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
