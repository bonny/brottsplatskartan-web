@if(!empty($mcf))
    @php
        $publiceringsAr = $mcf->ar + 1;
        $typerVisa = $mcf->per_typ
            ->reject(fn ($r) => in_array($r->handelsetyp_id, \App\MCFStatistik::TYPER_EXKLUDERA_DEFAULT))
            ->sortByDesc('antal')
            ->take(8);
    @endphp

    <section class="widget MCFStatistik" id="raddningstjansten" aria-labelledby="mcf-statistik-heading">
        <h2 id="mcf-statistik-heading" class="widget__title">
            Räddningstjänstens insatser i {{ $mcf->kommun_namn }} kommun {{ $mcf->ar }}
        </h2>

        <p class="MCFStatistik__lead">
            <strong>{{ \App\Helper::number($mcf->olyckor) }} olyckor</strong>
            registrerade av räddningstjänsten.
            @if($mcf->automatlarm > 0)
                Därutöver {{ \App\Helper::number($mcf->automatlarm) }} automatlarm
                utan brand (falsklarm).
            @endif
        </p>

        @if($typerVisa->count() > 0)
            <div class="MCFStatistik__tableWrap">
                <table class="DataTable">
                    <thead>
                        <tr>
                            <th scope="col">Typ av insats</th>
                            <th scope="col" class="num">Antal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($typerVisa as $rad)
                            <tr>
                                <td>{{ $rad->handelsetyp_namn }}</td>
                                <td class="num">{{ \App\Helper::number($rad->antal) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <p class="MCFStatistik__source">
            Källa:
            <a href="https://statistik.mcf.se/"
               rel="external noopener"
               target="_blank">MCF</a>
            (Myndigheten för civilt försvar, tidigare MSB) — räddningstjänstens
            insatser, publicerad
            {{ \Carbon\Carbon::create($publiceringsAr, 3, 1)->locale('sv')->isoFormat('MMMM YYYY') }}.
            Detta är räddningstjänstens registrerade insatser, inte alla händelser
            i kommunen — t.ex. ingår inte småbränder utan utryckning. Polisens
            händelseflöde ovan är ett annat urval.
        </p>
    </section>
@endif
