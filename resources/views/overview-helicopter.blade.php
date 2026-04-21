{{-- Template för helikopter-översikt --}}

@extends('layouts.web')
@section('title', '🚁 Helikopter - polishelikopter eller ambulanshelikopter nära dig?')
@section('metaDescription', e('Se senaste händelserna från Polisen som nämner helikopter'))
@section('canonicalLink', '/helikopter')

@section('content')

    <div class="widget">
        <h1 class="widget__title">Helikopter</h1>

        <p>
            Senaste händelserna som nämner ordet <em>helikopter</em>.
        </p>

        <p>
            Har du hört en helikopter som cirklar ovanför ditt hus eller område
            eller som åkt förbi? Kanske kan du hitta anledningen till
            det bland polisens händelser. På den här sidan listar vi de polisnotiser
            som innehåller helikopter i någon form.
        </p>

        <p>
            Hittar du inte rätt händelse här så testa
            <a href="https://twitter.com/search?q=helikopter">sök på Twitter efter helikopter</a>,
            eller se aktuella luftfarkoster på kartan nedan (data från
            <a href="https://www.adsbexchange.com/">ADS-B Exchange</a>).
        </p>

        <div class="widget" style="margin-top: 2em;">
            <h2 class="widget__title">Luftfarkoster över Sverige just nu</h2>
            <p>
                Visar alla luftfarkoster (inklusive helikoptrar) som rapporterar
                position via ADS-B. Klicka på en ikon för att se typ, höjd och
                flygbana. Helikoptrar syns oftast som rundade ikoner.
            </p>
            <div style="width: 100%; height: min(80vh, 600px); margin-top: 1em;">
                <iframe
                    src="https://globe.adsbexchange.com/?lat=62.5&lon=16.5&zoom=5"
                    style="border: 0; width: 100%; height: 100%;"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    title="Luftfarkoster över Sverige, live från ADS-B Exchange"
                    allow="geolocation"
                ></iframe>
            </div>
            <p class="text-sm u-color-gray-1 u-margin-top-half">
                Kartan visar inte alla militära helikoptrar då de kan ha stängt
                av sin ADS-B-transponder.
                <a href="https://globe.adsbexchange.com/?lat=62.5&lon=16.5&zoom=5" target="_blank" rel="noopener">
                    Öppna kartan i nytt fönster &rarr;
                </a>
            </p>
        </div>

        <p>
            Den svenska polisen har nio helikoptrar och de finns
            i <a href="/plats/stockholm">Stockholm</a>, <a href="/plats/göteborg">Göteborg</a>, <a
                href="/plats/malmö">Malmö</a>, <a href="/plats/östersund">Östersund</a> och <a href="/plats/boden">Boden</a>.
        </p>

        <p>
            Helikopter används för räddningsverksamhet eller när grova brott har begåtts,
            t.ex. för att följa gärningsmännens flyktväg efter ett väpnat rån.
            De används också för att exempelvis undsätta nödställda fjällvandrare eller för att
            övervaka demonstrationer.
        </p>

        <div class="PlatsListing">

            <ul class="widget__listItems">
                @foreach ($events as $event)
                    @include('parts.crimeevent', [
                        'event' => $event,
                        'overview' => true,
                        'highlight' => ['polishelikopter', 'ambulanshelikopter', 'helikopter'],
                    ])
                @endforeach
            </ul>

        </div>

    </div>

@endsection

@section('sidebar')
    @include('parts.lan-and-cities')
@endsection
