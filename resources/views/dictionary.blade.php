{{--

Template för ordlista/dictionary

--}}

@extends('layouts.web')

@section('title', 'Ordlista | Beskrivningar av orden som Polisen använder')
@section('canonicalLink', '/ordlista')

@section('content')

    <h1>Ordlista med brottsrelaterade ord</h1>

    <p>
        Brottsplatskartans ordlista.
        Här kan du läsa vad de begrepp och ord som Polisen använder
        betyder.
    </p>

    <p>
        Hittar du inte ordet du söker här så testa ordlistan hos
        <a href="https://www.brottsoffermyndigheten.se/utsatt-for-brott/ordlista">Brottsoffermyndigheten</a>, 
        eller
        <a href="https://www.aklagare.se/ordlista/">Åklararmyndigeten</a>.
        Även 
        <a href="https://snutkoll.se/dina-rattigheter/ordlista/">Snutkoll</a> och 
        <a href="https://www.flashback.org/t1356594">Flashbacks forum för aktuella brott och kriminalfall</a> har ordlistor med många ord.
    </p>

    <div class="DictionaryListing">

        @foreach ($words as $oneWord)

            <div class="DictionaryListing__word">

                <h2 class="DictionaryListing__title" id="{{str_slug($oneWord->word)}}">
                    <a href="{{ route('ordlistaOrd', ['word' => App\Helper::toAscii($oneWord->word)]) }}">
                        {{ $oneWord->word }}
                    </a>
                </h2>

                @if (empty($oneWord->description))
                    <p>Beskrivning saknas</p>
                @else
                    {!! Markdown::parse( $oneWord->getExcerpt() ) !!}
                @endif

            </div>

        @endforeach

    </div>

    <p>
        Förklaringarna kommer främst från <a href="https://wikipedia.com">Wikipedia.com</a> och <a href="https://polisen.se/Lagar-och-regler/Om-olika-brott/">polisen.se</a>.
        Saknas något ord eller är något av orden felaktigt beskrivna?
        Hör av dig till <a href="mailto:kontakt@brottsplatskartan.se">kontakt@brottsplatskartan.se</a>.
    </p>

@endsection

@section('sidebar')

    @include('parts.follow-us')
    @include('parts.lan-and-cities')

@endsection
