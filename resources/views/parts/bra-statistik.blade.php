{{--
    BRÅ:s anmälda brott per kommun (todo #38). Renderar bara om $bra finns.

    Kontroller måste passa:
        $bra            — objekt från BraStatistik::forKommun()
        $braLanGrannar  — Collection från BraStatistik::lanGrannar()
        $braRikssnitt   — int (per_100k, befolkningsviktat)
--}}
@if(!empty($bra))
    @php
        $aktivKommunKod = $bra->kommun_kod;
        $procentMotRiket = $braRikssnitt
            ? (int) round((($bra->per_100k - $braRikssnitt) / $braRikssnitt) * 100)
            : null;
        $publiceringsAr = $bra->ar + 1;
        $lanLabel = \App\BraStatistik::lanLabel($bra->lan_kod);
    @endphp

    <section class="widget BraStatistik" aria-labelledby="bra-statistik-heading">
        <h2 id="bra-statistik-heading" class="widget__title">
            Anmälda brott i {{ $bra->kommun_namn }} kommun {{ $bra->ar }}
        </h2>

        <p class="BraStatistik__lead">
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
            <h3 class="BraStatistik__subheading">
                Jämfört med övriga kommuner i {{ $lanLabel }}
            </h3>
            <div class="BraStatistik__tableWrap">
                <table class="BraStatistik__table">
                    <thead>
                        <tr>
                            <th scope="col">Kommun</th>
                            <th scope="col" class="num">Anmälda brott</th>
                            <th scope="col" class="num">Per 100 000</th>
                            <th scope="col" class="num">vs riket</th>
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
                            <tr class="{{ $isAktiv ? 'is-active' : '' }}">
                                <td>
                                    {{ $g->kommun_namn }}@if ($isAktiv) <span class="BraStatistik__activeTag">denna sida</span>@endif
                                </td>
                                <td class="num">{{ number_format($g->antal, 0, ',', ' ') }}</td>
                                <td class="num">{{ number_format($g->per_100k, 0, ',', ' ') }}</td>
                                <td class="num">
                                    @if ($diff === null)
                                        —
                                    @elseif ($diff > 0)
                                        <span class="BraStatistik__diff BraStatistik__diff--up">+{{ $diff }}&nbsp;%</span>
                                    @elseif ($diff < 0)
                                        <span class="BraStatistik__diff BraStatistik__diff--down">{{ $diff }}&nbsp;%</span>
                                    @else
                                        0&nbsp;%
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <p class="BraStatistik__source">
            Källa:
            <a href="https://bra.se/statistik/kriminalstatistik/anmalda-brott.html"
               rel="external noopener"
               target="_blank">Brå</a>
            (Brottsförebyggande rådet) — officiell anmäld brottsstatistik, publicerad
            {{ \Carbon\Carbon::create($publiceringsAr, 3, 1)->locale('sv')->isoFormat('MMMM YYYY') }}.
            Anmälda brott är inte samma sak som faktiskt begångna brott — mörkertalet
            varierar mellan brottstyper. Listan ovan visar inte typ av brott, bara totaler.
        </p>
    </section>
@endif
