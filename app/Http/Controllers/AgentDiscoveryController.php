<?php

namespace App\Http\Controllers;

/**
 * Agent-discovery-endpoints under /.well-known/* som hjälper AI-agenter
 * och scannrar (t.ex. isitagentready.com) att hitta vårt publika API.
 */
class AgentDiscoveryController extends Controller
{
    /**
     * RFC 9727 — Linkset-format för API-katalog. Listar våra publika
     * API-endpoints så agenter kan upptäcka dem programmatiskt.
     *
     * Spec: https://datatracker.ietf.org/doc/html/rfc9727
     */
    public function apiCatalog()
    {
        $base = config('app.url', 'https://brottsplatskartan.se');

        $docs = [
            ['href' => 'https://github.com/bonny/brottsplatskartan-web/blob/main/docs/API.md', 'type' => 'text/markdown'],
        ];

        $endpoints = [
            '/api/events',
            '/api/event/{id}',
            '/api/eventsMap',
            '/api/eventsNearby',
            '/api/areas',
        ];

        $linkset = [
            'linkset' => array_map(fn (string $path) => [
                'anchor' => $base . $path,
                'service-desc' => $docs,
            ], $endpoints),
        ];

        return response()->json($linkset, 200, [
            'Content-Type' => 'application/linkset+json; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
