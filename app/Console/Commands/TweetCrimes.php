<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\CrimeEvent;

class TweetCrimes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tweets:post';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Posts tweets with latest crimes';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        // Default command line time limit i 0 = forever.
        // We limit this because something on the server is hanging, perhaps this..
        set_time_limit(45);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $this->info('Ok, let\'s go');

        //print_r( \Twitter::getHomeTimeline(['count' => 20, 'format' => 'json']) );

        // hämta händelser från den senaste nn timmarna eller dagarna typ
        // så vi får med ev missade om något skulle vara nere, typ polisen ligga efter
        // med rapportering eller twitter nere eller whatever
        $daysBack = 2;
        $date = strTotime("today -{$daysBack} days");
        $this->line("Fetching all events since {$daysBack} days back (since " .  date("Y-m-d H:i", $date) . ")");

        $events = CrimeEvent::select("*")
                            ->orderBy("created_at", "desc")
                            ->where([
                                ["created_at", ">=", $date],
                                ["tweeted", "=", false],
                                ["geocoded", "=", true]
                            ])
                            ->get();

        $this->line("Found {$events->count()} events");

        foreach ($events as $event) {
            $this->info("\n{$event->title} (" . $event->getKey() . ")");

            $url = $event->getPermalink(true);

            // description:
            // Trafikolycka 4 fordon Norrmalmsgatan.
            // parsed_content:
            // På Norrmalmsgatan skedde en trafikolycka där 4 fordon var inblandade. 
            // Inga personer skadades. Ärendet kommer att utredas vidare.
            // parsed_title: Trafikolycka

            #$hashTags = "#polisen #brott";
            $hashTags = "";
            $hashTagsLength = mb_strlen($hashTags);

            $maxTweetLength = 140;
            // the url counts as 24 chars + 1 for the space before it
            $urlLength = 24;

            // minus 1 "just to be sure"
            $statusAllowedLength = $maxTweetLength - $hashTagsLength - $urlLength - 1;

            // Tweet text can be hashtags length - link length
            $statusBeforeShortened = trim($event->getLocationString()) . ": " . trim($event->getMetaDescription(1000));

            $statusAfterShortened = mb_substr($statusBeforeShortened, 0, $statusAllowedLength);
            $statusAfterShortened = trim($statusAfterShortened);

            echo "\n\nparsed_title:\n" . $event->parsed_title;
            echo "\n\nlocation_string:\n" . $event->getLocationString();
            echo "\n\nmeta desc:\n" . $event->getMetaDescription(1000);
            echo "\n\nstatusAfterShortened:\n" . $statusAfterShortened;
            echo "\n\n";
            #exit;

            // calculate how long teaser we can have
            #$content_length_before_teaser = 22 + mb_strlen($event->parsed_title) + mb_strlen($event->getLocationString());
            // - n because new lines + getMetaDesc adds "..." (which we change to "…")
            #$teaser_can_be_in_length = 140 - $content_length_before_teaser - $hashTagsLength - 7;

            #if ($teaser_can_be_in_length <= 0) {
            #    $teaser_can_be_in_length = 100;
            #}

            /*$tweetMessage = sprintf(
                '
%2$s, %3$s
%4$s %5$s
%1$s
                ',
                $url, // 1
                $event->parsed_title, // 2
                $event->getLocationString(), // 3
                $event->getMetaDescription($teaser_can_be_in_length), // 4
                $hashTags // 5
            );
            */

            $tweetMessage = $statusAfterShortened . " $url";

            $tweetMessage = trim($tweetMessage);
            $tweetMessage = str_replace("...", "…", $tweetMessage);

            // My logic above is apparently completely wrong so just cut it to 140 chars here..
            // $tweetMessage = mb_substr($tweetMessage, 0, 140);

            if (ENV("APP_ENV") == "local") {
                $tweetMessage = str_replace(
                    'https://brottsplatskartan.dev',
                    'https://brottsplatskartan.se',
                    $tweetMessage
                );
            }

            //$this->line("teaser_can_be_in_length: $teaser_can_be_in_length");
            $this->line("Tweet will be for ID {$event->getKey()}:\n{$tweetMessage}");

            /*
            The number of digits after the decimal separator passed to lat (up
            to 8) is tracked so that when the lat is returned in a status object
            it will have the same number of digits after the decimal separator.

            Use a decimal point as the separator (and not a decimal comma) for
            the latitude and the longitude - usage of a decimal comma will cause
            the geo-tagged portion of the status update to be dropped.
            */
            $lat = $event->location_lat;
            $long = $event->location_lng;

            // Do the tweet
            if (ENV("APP_ENV") == "local") {
                // no tweet on local
                $this->line("No actual tweet because on local");
            } else {
                // use config for global account first
                \Twitter::reconfig([
                    'consumer_key' => getenv('TWITTER_CONSUMER_KEY'),
                    'consumer_secret' => getenv('TWITTER_CONSUMER_SECRET'),
                    'token' => getenv('TWITTER_ACCESS_TOKEN'),
                    'secret' => getenv('TWITTER_ACCESS_TOKEN_SECRET'),
                ]);

                // Do the tweet!
                $tweetResult = \Twitter::postTweet([
                    'status' => $tweetMessage,
                    'format' => 'json',
                    "lat" => $lat,
                    "long" => $long
                ]);
            }

            #exit;

            /*

            tweet to Stockholm twitter account

            twitter::reconfig to switch

            administrative_area_level_1 = Stockholms län
            eller locations innehåller Stockholms län
            */

            $isStockholm = false;

            if (strpos($event->administrative_area_level_1, "Stockholm") !== false) {
                $isStockholm = true;
            }

            if ($isStockholm) {
                // $this->line("administrative_area_level_1: " . $event->administrative_area_level_1);

                if (ENV("APP_ENV") == "local") {
                    // no tweet on local
                    $this->line("No actual tweet to stockholm because on local");
                } else {
                    // switch account to stockholm
                    \Twitter::reconfig([
                        'consumer_key' => getenv('STOCKHOLMBROTT_TWITTER_CONSUMER_KEY'),
                        'consumer_secret' => getenv('STOCKHOLMBROTT_TWITTER_CONSUMER_SECRET'),
                        'token' => getenv('STOCKHOLMBROTT_TWITTER_ACCESS_TOKEN'),
                        'secret' => getenv('STOCKHOLMBROTT_TWITTER_ACCESS_TOKEN_SECRET'),
                    ]);

                    $tweetResult = \Twitter::postTweet([
                        'status' => $tweetMessage,
                        'format' => 'json',
                        "lat" => $lat,
                        "long" => $long
                    ]);
                }
            }

            // after tweetet
            $event->tweeted = true;
            $event->save();
        }

        /*
        $tweetResult = \Twitter::postTweet([
            'status' => $tweetMessage,
            'format' => 'json'
        ]);

        dd($tweetResult);
        */

        // return Twitter::postTweet(['status' => 'Laravel is beautiful', 'format' => 'json']);
    }
}
