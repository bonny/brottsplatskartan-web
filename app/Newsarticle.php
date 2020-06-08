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

    /**
     * Avgör om en nyhetskälla är inbäddningbar, t.ex. om det är möjligt att
     * visa hela innehållet via en iframe eller liknande.
     * 
     * @return bool True om det går att bädda in denna källa.
     */
    public function isEmbeddable() : bool {
        //  "url" => "https://twitter.com/goranlr/status/1265265411743940610"
        //  "url" => "https://www.facebook.com/mittivasastan/posts/2919400118108612"
        if (empty($this->url)) {
            return false;
        }

        $isTwitter = starts_with($this->url, 'https://twitter.com/');
        $isFb = starts_with($this->url, 'https://www.facebook.com/') || starts_with($this->url, 'https://facebook.com/');
        
        return $isTwitter || $isFb;
    }

    public function getEmbedMarkup() {
        $isTwitter = starts_with($this->url, 'https://twitter.com/');
        $isFb = starts_with($this->url, 'https://www.facebook.com/') || starts_with($this->url, 'https://facebook.com/');
        $embedCode = '';

        if ($isTwitter) {
            // Hämta id från tweet.
            $tweetId = preg_match('/\/status\/([\d]*)$/', $this->url, $matches);
            if (isset($matches[1])) {
                $embedCode = sprintf(
                    '
                        <amp-twitter 
                            width="375"
                            height="472"
                            layout="responsive"
                            data-tweetid="%1$d">
                        </amp-twitter>
                    ',
                    $matches[1]
                );
            }
        } elseif ($isFb) {
            $embedCode = sprintf(
                '
                    <amp-facebook 
                        width="552" 
                        height="310"
                        layout="responsive"
                        data-href="%1$s">
                    </amp-facebook>
                ',
                htmlspecialchars($this->url)
            );
        }

        return $embedCode;
    }

    public function getSourceName() {
        // Använd source om finns
        if (!empty($this->source)) {
            return $this->source;
        }

        // Försök reda ut källa via URL
        $url = $this->url;

        // Bail om ingen url.
        if (empty($url)) {
            return '';
        }

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
            case 'www.skd.se':
            case 'skd.se':
                $source = 'Skånska Dagbladet';
                break;
            case 'www.sydsvenskan.se':
            case 'sydsvenskan.se':
                $source = 'Sydsvenskan';
                break;
            default:
                $source = $urlHost;
        }

        return $source;
    }
}
