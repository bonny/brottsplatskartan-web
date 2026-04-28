{{--
    BRÅ:s anmälda brott per kommun (todo #38). Renderar bara om $bra finns.

    Kontroller måste passa:
        $bra            — objekt från BraStatistik::forKommun()
        $braLanGrannar  — Collection från BraStatistik::lanGrannar()
        $braRikssnitt   — int (per_100k, befolkningsviktat)

    Visuell separation från Polisens händelser är medveten — det här är
    årlig myndighetsstatistik, inte realtidshändelser.
--}}
@if(!empty($bra))
    @php
        $aktivKommunKod = $bra->kommun_kod;
        $procentMotRiket = $braRikssnitt
            ? (int) round((($bra->per_100k - $braRikssnitt) / $braRikssnitt) * 100)
            : null;
        $publiceringsAr = $bra->ar + 1;
        // SCB:s lan_namn saknar "län"-suffix ("Uppsala" inte "Uppsala län").
        // Använd `$lan` från parent-scope om det finns (rätt formaterat med possessiv:
        // "Stockholms län", "Västra Götalands län"), annars naive append.
        $lanLabel = $lan ?? ($bra->lan_namn . ' län');
    @endphp

    <section class="bra-statistik mt-8 p-4 border border-slate-200 rounded-lg bg-slate-50"
             aria-labelledby="bra-statistik-heading">
        <h2 id="bra-statistik-heading" class="text-2xl font-semibold mb-2">
            Anmälda brott i {{ $bra->kommun_namn }} kommun {{ $bra->ar }}
        </h2>

        <p class="text-base mb-4">
            <strong>{{ number_format($bra->antal, 0, ',', ' ') }} anmälda brott</strong>
            — {{ number_format($bra->per_100k, 0, ',', ' ') }} per 100 000 invånare.
            @if($procentMotRiket !== null)
                @if($procentMotRiket > 1)
                    Det är <strong>{{ $procentMotRiket }} % över rikssnittet</strong>
                    ({{ number_format($braRikssnitt, 0, ',', ' ') }} per 100 000).
                @elseif($procentMotRiket < -1)
                    Det är <strong>{{ abs($procentMotRiket) }} % under rikssnittet</strong>
                    ({{ number_format($braRikssnitt, 0, ',', ' ') }} per 100 000).
                @else
                    Det är i nivå med rikssnittet
                    ({{ number_format($braRikssnitt, 0, ',', ' ') }} per 100 000).
                @endif
            @endif
        </p>

        @if($braLanGrannar && $braLanGrannar->count() > 1)
            <h3 class="text-lg font-semibold mt-6 mb-2">
                Jämfört med övriga kommuner i {{ $lanLabel }}
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-300 text-left">
                            <th class="py-2 pr-4">Kommun</th>
                            <th class="py-2 pr-4 text-right">Anmälda brott</th>
                            <th class="py-2 pr-4 text-right">Per 100 000</th>
                            <th class="py-2 text-right">vs riket</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($braLanGrannar as $g)
                            @php
                                $diff = $braRikssnitt
                                    ? (int) round((($g->per_100k - $braRikssnitt) / $braRikssnitt) * 100)
                                    : null;
                                $isAktiv = $g->kommun_kod === $aktivKommunKod;
                            @endphp
                            <tr class="border-b border-slate-200 {{ $isAktiv ? 'font-semibold bg-white' : '' }}">
                                <td class="py-2 pr-4">
                                    {{ $g->kommun_namn }}{{ $isAktiv ? ' (denna sida)' : '' }}
                                </td>
                                <td class="py-2 pr-4 text-right">{{ number_format($g->antal, 0, ',', ' ') }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format($g->per_100k, 0, ',', ' ') }}</td>
                                <td class="py-2 text-right">
                                    @if($diff === null)
                                        —
                                    @elseif($diff > 0)
                                        +{{ $diff }} %
                                    @else
                                        {{ $diff }} %
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <p class="text-xs text-slate-600 mt-4">
            Källa:
            <a href="https://bra.se/statistik/kriminalstatistik/anmalda-brott.html"
               rel="external noopener"
               target="_blank">Brå</a>
            (Brottsförebyggande rådet) — officiell anmäld brottsstatistik, publicerad
            {{ \Carbon\Carbon::create($publiceringsAr, 3, 1)->locale('sv')->isoFormat('MMMM YYYY') }}.
            Anmälda brott är inte samma sak som faktiskt begångna brott — mörkertalet
            varierar mellan brottstyper. Listan ovan visar inte typ av brott, bara
            totaler.
        </p>
    </section>
@endif
