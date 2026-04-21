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
            eller se om aktuellt helikopter finns med på som t.ex.
            <a href="https://www.flightradar24.com/">flightradar24.com</a>
            eller
            <a href="https://planefinder.net/">planefinder.net</a>.
        </p>

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
