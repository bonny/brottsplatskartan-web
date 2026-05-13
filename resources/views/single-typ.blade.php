{{--

Template för ett län

--}}


@extends('layouts.web')

@section('title', "$type")
@section('metaDescription', "Brott och polishändelser av typen {$type}. Aktuella inrapporterade händelser från Polisen, sorterade efter datum.")
@section('canonicalLink', '/typ/' . ($canonicalSlug ?? mb_strtolower($type)))

@section('metaContent')
    @include('parts.collectionpage-jsonld', [
        'cpName' => "Brott och händelser av typen {$type}",
        'cpUrl' => url('/typ/' . ($canonicalSlug ?? mb_strtolower($type))),
        'cpAboutType' => 'Thing',
        'cpAboutName' => $type,
        'cpDescription' => "Brott och polishändelser av typen {$type}. Aktuella inrapporterade händelser från Polisen, sorterade efter datum.",
    ])
@endsection

@section('content')

    <div class="widget">
        <h1>Brott och händelser av typen {{ $type }}</h1>
        <p>Visar alla inrapporterade händelser och brott för brottstyp {{ $type }}.</p>

        @if ($events)
            <ul class="Events Events--overview">
                @foreach ($events as $event)
                    <x-crimeevent.list-item :event="$event" />
                @endforeach
            </ul>

            {{ $events->links() }}
        @endif
    </div>
@endsection
