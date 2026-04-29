{{--
    MCF räddningsinsatser för en specifik månad (todo #39 + #25). Renderar
    bara om $mcfManad är fylld. Sätter Polisens månadsdata i kontext.

    Kontroller måste passa:
        $mcfManad           — Collection från MCFStatistik::forKommunManad()
                              (redan exkluderar typ 14 + 15)
        $mcfManadKommunNamn — string
        $monthYearTitle     — t.ex. "Augusti 2024"
--}}
@if(!empty($mcfManad) && $mcfManad->count() > 0)
    @php
        $totalt = $mcfManad->sum('antal');
    @endphp

    <section class="widget MCFStatistik MCFStatistik--manad" aria-labelledby="mcf-manad-heading">
        <h2 id="mcf-manad-heading" class="widget__title">
            Räddningstjänstens insatser i {{ $mcfManadKommunNamn }} under {{ mb_strtolower($monthYearTitle) }}
        </h2>

        <p class="MCFStatistik__lead">
            <strong>{{ \App\Helper::number($totalt) }} insatser</strong>
            registrerade av räddningstjänsten denna månad.
        </p>

        <div class="MCFStatistik__tableWrap">
            <table class="DataTable">
                <thead>
                    <tr>
                        <th scope="col">Typ av insats</th>
                        <th scope="col" class="num">Antal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($mcfManad as $rad)
                        <tr>
                            <td>{{ $rad->handelsetyp_namn }}</td>
                            <td class="num">{{ \App\Helper::number($rad->antal) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="MCFStatistik__source">
            Källa:
            <a href="https://statistik.mcf.se/"
               rel="external noopener"
               target="_blank">MCF</a>
            (Myndigheten för civilt försvar). Officiella insatssiffror per månad —
            visar bredd i räddningstjänstens arbete utöver det som rapporteras
            via Polisens händelseflöde ovan. Automatlarm utan brand och händelser
            utan risk är exkluderade.
        </p>
    </section>
@endif
