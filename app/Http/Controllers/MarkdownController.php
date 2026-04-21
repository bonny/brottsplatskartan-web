<?php

namespace App\Http\Controllers;

use App\CrimeEvent;
use App\Services\EventMarkdownRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Serverar markdown-varianter av publika sidor för LLM/agent-konsumtion.
 *
 * Routes:
 *   /{lan}/{eventName}.md    → event-markdown (enskild händelse)
 *
 * Content-Type: text/markdown; charset=utf-8
 * Innehåller Link: <canonical-url>; rel="canonical" så Google inte
 * indexerar .md-varianten som duplicate content.
 */
class MarkdownController extends Controller
{
    public function __construct(private EventMarkdownRenderer $renderer)
    {
    }

    public function event(string $lan, string $eventName, Request $request)
    {
        // Routen fångar ".../rattfylleri-2331.md" — strippa suffixet.
        $eventName = preg_replace('/\.md$/', '', $eventName);

        // Event-ID är sista siffergruppen i slugen.
        preg_match('!(\d+)$!', $eventName, $matches);
        if (empty($matches[1])) {
            abort(404);
        }

        if (is_numeric($lan)) {
            abort(404);
        }

        $eventID = $matches[1];
        $cacheKey = "markdown-event:{$eventID}";

        $md = Cache::remember($cacheKey, HOUR_IN_SECONDS, function () use ($eventID) {
            $event = CrimeEvent::with(['locations', 'newsarticles'])->find($eventID);
            if (! $event) {
                return null;
            }
            return [
                'content' => $this->renderer->render($event),
                'canonical' => $event->getPermalink(true),
            ];
        });

        if (! $md) {
            abort(404);
        }

        return response($md['content'], 200)
            ->header('Content-Type', 'text/markdown; charset=utf-8')
            ->header('Link', '<' . $md['canonical'] . '>; rel="canonical"')
            ->header('X-Robots-Tag', 'noindex');
    }
}
