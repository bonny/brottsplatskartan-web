<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Blog extends Model
{
    protected $table = 'blog';

    public function getCreatedAtFormatted($format = '%d %B %Y')
    {
        return Carbon::parse($this->created_at)->formatLocalized($format);
    }

    public function getCreatedAtAsW3cString()
    {
        return Carbon::parse($this->created_at)->toW3cString();
    }

    public function getExcerpt($length = 50)
    {
        $str = $this->content;
        $str = \Markdown::parse($str);

        // Behöver köra tweet-embedningen, även om vi inte ska visa tweets,
        // annars riskerar vi att det står "AMPTWEET: [...]" i utdraget.
        $str = $this->embedTweets($str);
        $str = $this->embedFacebook($str);

        $str = strip_tags($str);
        $str = Str::words($str, $length);

        return $str;
    }

    /**
     * Hämta permalink för ett blogginlägg.
     *
     * @return string URL till blogginlägg.
     */
    public function getPermalink()
    {
        return route(
            'blogItem',
            [
                'year' => date('Y', $this->created_at->timestamp),
                'slug' => $this->slug
            ]
        );
    }

    /**
     * Hämta innehållet för ett blogginlägg HTML-formaterat,
     * dvs. Markdown har parse'ats och Twitter och Facebook-strängar har
     * konverterats till embeds.
     *
     * @return string HTML.
     */
    public function getContentFormatted()
    {
        $str = $this->content;
        $str = \Markdown::parse($str);
        $str = $this->embedTweets($str);
        $str = $this->embedFacebook($str);
        return $str;
    }

    /**
     * Convert lines like
     * AMPTWEET: https://twitter.com/eskapism/status/944609719179796480
     *
     * To
     *
     * <amp-twitter
     *    width="375"
     *    height="472"
     *    layout="responsive"
     *    data-tweetid="944609719179796480">
     * </amp-twitter>
     *
     * @param string $str
     * @return string
     */
    public static function embedTweets($str)
    {
        $lines = preg_split('/\R/', $str);

        foreach ($lines as $key => $val) {
            #if (starts_with($val, 'https://twitter.com/')) {
            if (starts_with($val, '<p>AMPTWEET:')) {
                preg_match('!/status/(\d+)!', $val, $matches);
                if (sizeof($matches) === 2) {
                    $tweetId = $matches[1];
                    $lines[$key] = sprintf(
                        '<amp-twitter
                             width="375"
                             height="472"
                             layout="responsive"
                             data-tweetid="%1$s">
                            </amp-twitter>
                        ',
                        $tweetId
                    );
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Like embedTweets() but for facebook
     *
     * @param string $str
     * @return string
     */
    public static function embedFacebook($str)
    {
        $lines = preg_split('/\R/', $str);

        foreach ($lines as $key => $val) {
            // AMPFB: https://www.facebook.com/Brottsplatskartan/posts/1107232239332768
            $strToFind = '<p>AMPFB:';
            if (starts_with($val, $strToFind)) {
                #$str = $val;
                #$str = trim(str_replace($strToFind, '', $str));
                #echo $str;exit;
                $a = new \SimpleXMLElement($val);
                $href = $a->a['href'];

                if ($href) {
                    $lines[$key] = sprintf(
                        '
                        <amp-facebook width="552" height="310"
                            layout="responsive"
                            data-href="%1$s">
                        </amp-facebook>
                        ',
                        $href
                    );
                }
            }
        }

        return implode("\n", $lines);
    }
}
