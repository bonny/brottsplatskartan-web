{{--

Template för sverigekartan

--}}

@extends('layouts.web')

@section('title',
    'Sverigekartan – Brottsplatskartans karta med brott och händelser från hela Sverige utmarkerade på
    karta')
@section('canonicalLink', route('sverigekartan'))

@section('beforeMainContent')

    <div class="widget w-full overflow-visible">
        <h2 class="widget__title">
            <a href="{{ route('sverigekartan') }}">
                <svg class="align-text-bottom" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#0c3256"
                    width="18px" height="18px">
                    <path
                        d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z" />
                    <path d="M0 0h24v24H0z" fill="none" />
                </svg>
                Karta
            </a>

            <a href="{{ route('mostRead') }}" class="float-end">
                <svg class="align-text-bottom" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#0c3256"
                    width="18px" height="18px">
                    <path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z" />
                    <path d="M0 0h24v24H0z" fill="none" />
                </svg>
                Mest läst
            </a>
        </h2>

        <div class="block relative h-screen Sverigekartan__wrapper" style="margin-left: calc(-2 * var(--default-margin)); width: 100vw;">
            <iframe width="auto" height="300"
                sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox allow-top-navigation" layout="fill"
                frameborder="0" src="/sverigekartan-iframe/">
                <img loading="lazy" layout="fill" src="/img/share-img-blur.jpg" placeholder></img>
            </iframe>
        </div>
    </div>
@endsection
