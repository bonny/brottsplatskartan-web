{{-- Template för helikopter-översikt --}}

@extends('layouts.web')
@section('title', '🚁 Helikopter just nu — polishelikopter & ambulanshelikopter över Sverige')
@section('metaDescription', e('Se polishelikoptrar och ambulanshelikoptrar över Sverige i realtid. Senaste polisnotiserna som nämner helikopter och live-karta över luftfarkoster.'))
@section('canonicalLink', '/helikopter')

@section('content')

    <div class="widget">
        <h1 class="widget__title">Helikopter</h1>

        @include('parts.collectionpage-jsonld', [
            'cpName' => 'Helikopter över Sverige just nu',
            'cpUrl' => url('/helikopter'),
            'cpAboutType' => 'Thing',
            'cpAboutName' => 'Polishelikopter',
            'cpDescription' => 'Polishelikoptrar och ambulanshelikoptrar över Sverige — senaste polisnotiser och live-karta över luftfarkoster.',
        ])

        <p>
            Senaste händelserna som nämner ordet <em>helikopter</em>.
        </p>

        <p>
            Har du hört en helikopter som cirklar ovanför ditt hus eller område
            eller som åkt förbi? Kanske kan du hitta anledningen till
            det bland polisens händelser. På den här sidan listar vi de polisnotiser
            som innehåller helikopter i någon form.
        </p>

        @foreach ($byCity as $city => $cityEvents)
            @if ($cityEvents->count() > 0)
                <div class="widget" style="margin-top: 1.5em;">
                    <h2 class="widget__title">Just nu i {{ $city }}</h2>
                    <ul class="widget__listItems">
                        @foreach ($cityEvents as $event)
                            <x-crimeevent.list-item :event="$event" detailed />
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach

        <div class="PlatsListing" style="margin-top: 1.5em;">
            <h2 class="widget__title">Alla helikopter-händelser från Polisen</h2>
            <ul class="widget__listItems">
                @foreach ($events as $event)
                    <x-crimeevent.list-item :event="$event" detailed />
                @endforeach
            </ul>

            {{ $events->links() }}
        </div>

        <p style="margin-top: 1.5em;">
            Den svenska polisen har nio helikoptrar och de finns
            i <a href="{{ route('city', ['city' => 'stockholm']) }}">Stockholm</a>,
            <a href="{{ route('city', ['city' => 'goteborg']) }}">Göteborg</a>,
            <a href="{{ route('city', ['city' => 'malmo']) }}">Malmö</a>,
            <a href="/plats/östersund">Östersund</a>
            och <a href="/plats/boden">Boden</a>.
        </p>

        <p>
            Helikopter används för räddningsverksamhet eller när grova brott har begåtts,
            t.ex. för att följa gärningsmännens flyktväg efter ett väpnat rån.
            De används också för att exempelvis undsätta nödställda fjällvandrare eller för att
            övervaka demonstrationer.
        </p>

        <div class="widget" style="margin-top: 2em;">
            <h2 class="widget__title">Luftfarkoster över Sverige just nu</h2>
            <p>
                Visar alla luftfarkoster (inklusive helikoptrar) som rapporterar
                position via ADS-B. Klicka på en ikon för att se typ, höjd och
                flygbana. Helikoptrar syns oftast som rundade ikoner.
            </p>
            <div style="width: 100%; aspect-ratio: 4 / 3; max-height: min(80vh, 600px); margin-top: 1em;">
                <iframe
                    src="https://globe.adsbexchange.com/?lat=62.5&lon=16.5&zoom=5"
                    width="800"
                    height="600"
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
            Hittar du inte rätt händelse här? Testa
            <a href="https://twitter.com/search?q=helikopter" rel="noopener">sök på Twitter efter helikopter</a>.
        </p>
    </div>

@endsection

@section('sidebar')
    @include('parts.lan-and-cities')
@endsection
