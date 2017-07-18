<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Newsarticle extends Model
{

    protected $fillable = ['crime_event_id', 'title', 'shortdesc', 'url', 'source'];

    public function crimeevent()
    {
        return $this->belongsTo('App\CrimeEvent');
    }

    public function getSourceName() {
        // Använd source om finns
        if (!empty($this->source)) {
            return $this->source;
        }

        // Försök reda ut källa via URL
        $url = $this->url;
        $urlParsed = parse_url($url);
        $urlHost = $urlParsed['host'];

        $source = null;

        switch ($urlHost) {
            case 'texttv.nu':
                $source = 'SVT Text TV';
                break;
            case 'aftonbladet.se':
            case 'www.aftonbladet.se':
                $source = 'Aftonbladet';
                break;
            case 'expressen.se':
            case 'www.expressen.se':
                $source = 'Expressen';
                break;
            case 'dn.se':
            case 'www.dn.se':
                $source = 'DN';
                break;
            case 'nsd.se':
            case 'www.nsd.se':
                $source = 'NSD';
                break;
            case 'kristianstadsbladet.se':
            case 'www.kristianstadsbladet.se':
                $source = 'Kristianstadsbladet';
                break;
            case 'svt.se':
            case 'www.svt.se':
                $source = 'SVT';
                break;
            case 'gp.se':
            case 'www.gp.se':
                $source = 'Göteborgsposten';
                break;
            case 'unt.se':
            case 'www.unt.se':
                $source = 'UNT';
                break;
            default:
                $source = $urlHost;
        }

        return $source;
    }
}
