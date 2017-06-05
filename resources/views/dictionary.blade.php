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
                    {!! Markdown::parse($oneWord->description) !!}
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
