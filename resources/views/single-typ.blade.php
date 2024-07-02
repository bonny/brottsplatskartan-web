{{--

Template för ett län

--}}


@extends('layouts.web')

@section('title', "$type")
@section('canonicalLink', '/typ/' . mb_strtolower($type))

@section('content')

    <div class="widget">
        <h1>Brott och händelser av typen {{ $type }}</h1>
        <p>Visar alla inrapporterade händelser och brott för brottstyp {{ $type }}.</p>

        @if ($events)
            <ul class="Events Events--overview">
                @foreach ($events as $event)
                    @include('parts.crimeevent-small', ['overview' => true])
                @endforeach
            </ul>

            {{ $events->links() }}
        @endif
    </div>
@endsection
