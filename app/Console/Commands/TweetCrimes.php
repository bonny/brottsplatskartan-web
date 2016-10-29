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
                                ["tweeted", "=", false]
                            ])
                            ->get();

        $this->line("Found {$events->count()} events");

        foreach ($events as $event) {

            $this->info("\n{$event->title}");

            $url = $event->getPermalink(true);

            // description:
            // Trafikolycka 4 fordon Norrmalmsgatan.
            // parsed_content:
            // På Norrmalmsgatan skedde en trafikolycka där 4 fordon var inblandade. Inga personer skadades. Ärendet kommer att utredas vidare.
            // parsed_title: Trafikolycka

            $hashTags = "#polisen #brott";
            $hashTagsLength = strlen($hashTags);

            // calculate how long teaser we can have
            // the url counts as 22 chars
            $content_length_before_teaser = 22 + mb_strlen($event->parsed_title) + mb_strlen($event->getLocationString());
            // - n because new lines + getMetaDesc adds "..." (which we change to "…")
            $teaser_can_be_in_length = 140 - $content_length_before_teaser - $hashTagsLength - 7;


            $tweetMessage = sprintf(
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

            $tweetMessage = trim($tweetMessage);
            $tweetMessage = str_replace("...", "…", $tweetMessage);

            if (ENV("APP_ENV") == "local") {
                $tweetMessage = str_replace('https://brottsplatskartan.dev','https://brottsplatskartan.se', $tweetMessage);
            }

            //$this->line("teaser_can_be_in_length: $teaser_can_be_in_length");
            $this->line("Tweet will be for ID {$event->getKey()}:\n{$tweetMessage}");

            // comment out this to just test...

            // Do the tweet!
            $tweetResult = \Twitter::postTweet([
                'status' => $tweetMessage,
                'format' => 'json'
            ]);

            #exit;

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
