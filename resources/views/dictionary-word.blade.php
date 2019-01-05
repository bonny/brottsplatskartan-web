{{--

Template för ordlista/dictionary och ett enskild ord

--}}


@extends('layouts.web')

@section('title', $word->word . ' | Ordlista')
@section('metaDescription', e("Ordlista"))
@section('canonicalLink', "/ordlista/" . mb_strtolower($word->word))

@section('content')
    <div class="widget">

        <h1 class="widget__title">{{ $word->word }}</h1>

        {!! Markdown::parse($word->description) !!}

        {{-- Om inbrott så visa lista med typ 3 inbrott och sen länka till sidan om inbrott --}}
        {{-- @if (str_contains(mb_strtolower($word->word), ['inbrott']) || str_contains(mb_strtolower($word->description), ['inbrott']))
            <h3>Inbrott</h3>
            <p><a href="{{route('inbrott')}}">Inbrott</a></p>
        @endif --}}

        <h2 class="u-margin-top-double">Fler ord och förklaringar</h2>

        <ul class="DictionaryAllWordsListing__items">
            @foreach ($allWords as $word)
                <li class="DictionaryAllWordsListing__item">
                    <a class="DictionaryAllWordsListing__link" href="{{ route('ordlistaOrd', ['word' => $word]) }}">

                        @if ($loop->last)
                            {{ $word }}
                        @else
                            {{ $word }},
                        @endif

                    </a>

                </li>
            @endforeach
        </ul>

    </div>

@endsection

@section('sidebar')

    @include('parts.follow-us')
    @include('parts.lan-and-cities')

@endsection
