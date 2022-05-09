{{--

Template för sverigekartan

--}}

@extends('layouts.web')

@section('title', 'Sverigekartan – Brottsplatskartans karta med brott och händelser från hela Sverige utmarkerade på karta')
@section('canonicalLink', route('sverigekartan'))

@section('beforeMainContent')

    <div class="block relative h-screen Sverigekartan__wrapper">
        <iframe
            width="auto"
            height="300"
            sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox allow-top-navigation"
            layout="fill"
            frameborder="0"
            src="/sverigekartan-iframe/"
        >
            <img loading="lazy" layout="fill" src="/img/share-img-blur.jpg" placeholder></img>
        </iframe>
    </div>

@endsection
