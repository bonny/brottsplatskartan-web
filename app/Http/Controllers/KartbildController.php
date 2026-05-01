<?php

namespace App\Http\Controllers;

use App\CrimeEvent;
use App\Services\StaticMapUrlBuilder;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Kort URL för statiska kartbilder — 301 redirect till tileservern.
 *
 * Bygger long-URL:n via StaticMapUrlBuilder och svarar med 301 +
 * immutable Cache-Control. Browsern cachar både redirect:en och
 * destinationen permanent på URL:n. Spatie Response Cache (befintlig
 * Redis-stack) cachar 301-svaret → andra hit kostar inte PHP/SQL alls.
 *
 * Spec-format (se todo #55, Alt B):
 *   /k/v1/circle-{id}-{w}x{h}[@2x].jpg       — full cirkel-densitet (3 lager × 48 punkter)
 *   /k/v1/circle-low-{id}-{w}x{h}[@2x].jpg   — kapad densitet (1 lager × 24) — för thumbs
 *   /k/v1/near-{id}-{w}x{h}[@2x].jpg         — close-up bbox
 *   /k/v1/far-{id}-{w}x{h}[@2x].jpg          — översikt zoom 5
 *
 * `v1` är versions-prefix: vid stilbyte (cirkel-färg, opacity etc.) bumpa
 * till v2 så browser-cache invalideras på en gång — `immutable` annars
 * sitter kvar i ~1 år.
 */
class KartbildController extends Controller
{
    private const SPEC_PATTERN = '/^(?<mode>circle-low|circle|near|far)-(?<id>\d+)-(?<w>\d+)x(?<h>\d+)(?<retina>@2x)?$/';

    public function show(string $spec, StaticMapUrlBuilder $builder): SymfonyResponse
    {
        if (!preg_match(self::SPEC_PATTERN, $spec, $m)) {
            abort(400, 'invalid spec');
        }

        $width = (int) $m['w'];
        $height = (int) $m['h'];

        // Storleksgräns — skydd mot DOS via /k/v1/circle-X-99999x99999.jpg.
        if ($width < 16 || $width > 2000 || $height < 16 || $height > 2000) {
            abort(400, 'invalid size');
        }

        $scale = !empty($m['retina']) ? 2 : 1;

        $event = CrimeEvent::find((int) $m['id']);
        if (!$event) {
            abort(404);
        }

        $longUrl = match ($m['mode']) {
            'circle'     => $builder->circleUrl($event, $width, $height, $scale, 'high'),
            'circle-low' => $builder->circleUrl($event, $width, $height, $scale, 'low'),
            'near'       => $builder->closeUpUrl($event, $width, $height, $scale),
            'far'        => $builder->farUrl($event, $width, $height, $scale),
        };

        if ($longUrl === '') {
            abort(404, 'event saknar koordinater');
        }

        // Bygger response direkt istället för redirect()-helpern: den
        // försöker binda session till svaret, vilket kraschar när vi har
        // skippat StartSession-middleware för routen.
        return response('', 301, [
            'Location'      => $longUrl,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Content-Type'  => 'text/plain',
        ]);
    }
}
