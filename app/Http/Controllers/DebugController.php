<?php

namespace App\Http\Controllers;

/**
 * Rester av den gamla debug-controllern.
 *
 * `/debug/{what}` är borttagen (todo #100): den var publik utan auth och
 * `phpinfo`-grenen läckte hela PHP-miljön — inklusive APP_KEY, DB- och
 * Redis-lösenord — till vem som helst. `/debug/urls` echoade dessutom
 * `?url=` oescapat, alltså reflekterad XSS. Behövs något liknande igen:
 * lägg det bakom `auth` och `app()->environment('local')`.
 *
 * Kvar finns bara den publika sidan för sociala medier.
 */
class DebugController extends Controller
{
    public function socialaMedier() {
        $data = [
            'title' => 'Sociala medier',
            'canonicalLink' => route('socialaMedier')
        ];
        return view('sociala-medier', $data);
    }
}
