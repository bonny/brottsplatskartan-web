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

            $tweetMessage = sprintf(
                '
%2$s, %3$s
%1$s
                ',
                $url, // 1
                $event->parsed_title, // 2
                $event->getLocationString() // 3
            );

            $tweetMessage = trim($tweetMessage);

            $this->line("Tweet will be:\n{$tweetMessage}");

            // Do the tweet!
            $tweetResult = \Twitter::postTweet([
                'status' => $tweetMessage,
                'format' => 'json'
            ]);

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
