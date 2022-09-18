{{-- Template för 404 --}}

@extends('layouts.web')

@section('title', 'Kunde inte hitta sidan (felkod 404)')

@section('content')

    <h1>Oups, vi kunde inte hitta den här sidan</h1>

    <p>Vi kunde tyvärr inte hitta någon sida på den här adressen.</p>

    <p>För att fortsätta så kan du välja <a href="/">gå till startsidan</a> för att se
        de <a href="/">senaste brotten i hela Sverige</a>.</p>

    <p>Nedan hittar du också en lista på de händelser som är senast rapporterade och även de
        som är mest lästa just nu.</p>



    @if ($events->count())
        <h2 class="u-margin-top-double">Senast rapporterat</h2>

        <ul class="widget__listItems">
            @foreach ($events as $event)
                @include('parts.crimeevent-mapless')
            @endforeach
        </ul>

        <p><a href="{{ route('handelser') }}">› Visa fler nya händelser</a></p>
    @endif

    @if ($most_read_events->count())
        <h2 class="u-margin-top-double">Mest läst</h2>

        <ul class="widget__listItems">
            @foreach ($most_read_events as $oneMostViewed)
                @include('parts.crimeevent-mapless', ['event' => $oneMostViewed->crimeEvent,])
            @endforeach
        </ul>

        <p><a href="{{ route('mostRead') }}">› Visa fler händelser som många läser nu</a></p>
    @endif


    @if (!empty($lan))

        <h2 class="u-margin-top-double">Händelser i län</h2>

        <div class="LanListing">

            <p>Du kan också fortsätta genom att välja ett län nedan för att se senaste brotten i det länet.</p>

            @foreach ($lan as $oneLan)
                <h2 class="LanListing__lan">
                    <a href="{{ route('lanSingle', ['lan' => $oneLan->administrative_area_level_1]) }}">
                        {{ $oneLan->administrative_area_level_1 }}
                    </a>
                </h2>
            @endforeach

        </div>

    @endif

@endsection
