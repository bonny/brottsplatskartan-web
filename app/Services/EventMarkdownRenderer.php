<?php

namespace App\Services;

use App\CrimeEvent;

/**
 * Renderar ett CrimeEvent som ren markdown för AI-agent-konsumtion.
 *
 * Tanken: samma URL + ".md" (eller Accept: text/markdown) returnerar
 * en version utan HTML-chrome (nav, ads, sidebar), bara själva
 * händelsen + metadata. Mindre payload = lägre tokenkostnad för
 * LLM:er som ChatGPT Search, Perplexity, Claude WebFetch m.fl.
 *
 * Vercels mätning (feb 2026): ~99% payload-reduktion HTML → markdown.
 */
class EventMarkdownRenderer
{
    public function render(CrimeEvent $event): string
    {
        $permalink = $event->getPermalink(true);
        $headline = $event->getHeadline();
        $type = $event->parsed_title;
        $date = $event->getParsedDateFormattedForHumans();
        $dateISO = $event->getParsedDateISO8601();
        $location = trim((string) $event->getLocationString()) ?: 'Okänd plats';
        $lan = $event->administrative_area_level_1;
        $body = $this->htmlToMarkdown($event->getParsedContent());

        $md = "# {$headline}\n\n";
        $md .= "> {$type} — {$location}" . ($lan && $lan !== $location ? ", {$lan}" : '') . "\n\n";
        $md .= "- **Typ:** {$type}\n";
        $md .= "- **Datum:** {$date} (`{$dateISO}`)\n";
        $md .= "- **Plats:** {$location}\n";
        if ($lan) {
            $md .= "- **Län:** {$lan}\n";
        }
        if ($event->location_lat && $event->location_lng) {
            $md .= sprintf("- **Koordinater:** %.5f, %.5f\n", $event->location_lat, $event->location_lng);
        }
        $md .= "\n## Händelsetext\n\n";
        $md .= $body . "\n\n";

        if ($event->newsarticles->count()) {
            $md .= "## Relaterade nyhetsartiklar\n\n";
            /** @var \App\Newsarticle $article */
            foreach ($event->newsarticles as $article) {
                $title = $article->title ?? $article->url;
                $md .= "- [{$title}]({$article->url})\n";
            }
            $md .= "\n";
        }

        $md .= "---\n\n";
        $md .= "Hämtad från [Brottsplatskartan]({$permalink}). ";
        $md .= "Originalhändelsen publicerades av Polisen. ";
        $md .= "Data hämtas från Polisens RSS-flöden.\n";

        return $md;
    }

    /**
     * Konvertera event-bodyns HTML till rimlig markdown. Bodyn är
     * normalt ett par paragrafer, inga tabeller eller listor.
     */
    private function htmlToMarkdown(string $html): string
    {
        // Konvertera <br> och <p> till radbrytningar
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $text = preg_replace('/<\/p>\s*<p[^>]*>/i', "\n\n", (string) $text);
        $text = preg_replace('/<\/?p[^>]*>/i', '', (string) $text);

        // Behåll länkar som [text](url)
        $text = preg_replace_callback(
            '/<a[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is',
            fn ($m) => '[' . trim(strip_tags($m[2])) . '](' . $m[1] . ')',
            (string) $text
        );

        // Ta bort övriga taggar
        $text = strip_tags((string) $text);

        // Normalisera whitespace
        $text = preg_replace("/\n{3,}/", "\n\n", (string) $text);
        $text = trim((string) $text);

        return $text;
    }
}
