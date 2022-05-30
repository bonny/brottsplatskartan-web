@extends('layouts.web')

@section('title', 'VMA – Viktigt Meddelande till Allmänheten')
@section('metaDescription', e('Se aktuella och tidigare VMA'))
@section('canonicalLink', route('vma-overview'))

@section('content')

    <div class="widget">
        <h1 class="widget__title">VMA – Viktigt Meddelande till Allmänheten</h1>

        <p>Vi hämtar listan med VMA genom att använda <a href="https://vmaapi.sr.se/api/v2/">"Sveriges Radio's API for
                Important Public Announcements"</a>.</p>

        <h2>Arkiv</h2>
        <ul class="list-none m-0 p-0">
            @foreach ($alerts as $alert)
                <li class="mb-6 pb-6 u-border-bottom">
                    <a href="{{ $alert->getPermalink() }}">
                        <h2 class="m-0">
                            {{ $alert->getShortDescription() }}
                        </h2>
                    </a>

                    <p class="m-0 mt-2 text-sm"><time
                            datetime="{{ $alert->getIsoSentDateTime() }}">{{ $alert->getHumanSentDateTime() }}</time>
                    </p>

                    <p class="m-0 mt-2 text-sm excerpt">{!! $alert->getTeaser() !!}</p>
                </li>
            @endforeach
        </ul>

    </div>

@endsection

@section('sidebar')
    @include('parts.vma.sidebar')
@endsection
