{{--

Template för bloggens startsida

--}}

@extends('layouts.web')

@section('title', 'Blogg: Brottsplatskartans blogg med uppdateringar om sajten med mera')
@section('canonicalLink', route('blog'))

@section('content')

    <div class="widget">

        <h1 class="widget__title">Brottsplatskartans blogg</h1>

        <p class="">
            Här på vår blogg kan du läsa om hur vi utvecklar sajten och
            få diverse länktips som handlar om polis och brandkår om brott
            andra typer av polisiära händelser.
        </p>

        @if (isset($blogItems) && $blogItems->count())
            <ol class="BlogItems">
                @foreach ($blogItems as $blog)
                    @include('parts.blog-item-overview', ['overview' => true])
                @endforeach
            </ol>
        @else
            <p>Hittade inga inlägg. <a href="{{ route('blog') }}">Gå till bloggens startsidan</a>.</p>
        @endif

    </div>

@endsection

@section('sidebar')
    @include('parts.widget-blog-entries')
    @include('parts.follow-us')
    @include('parts.lan-and-cities')
@endsection
