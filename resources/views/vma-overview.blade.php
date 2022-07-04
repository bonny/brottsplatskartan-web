@extends('layouts.web')

@section('title', 'VMA – Alarmet Viktigt Meddelande till Allmänheten')
@section('metaDescription', e('Se aktuella och tidigare VMA-alarm i vårt VMA-arkiv'))
@section('canonicalLink', route('vma-overview'))

@section('content')

    <div class="widget">
        <h1 class="widget__title">Lista på VMA – Viktigt Meddelande till Allmänheten</h1>

        <div class="callout">
            <p>Här listar vi de senaste VMA som sänts ut.</p>
            <p>VMA är en förkortning av Viktigt meddelande till allmänheten, och det är ett varningssystem som används vid
                olyckor, allvarliga händelser och störningar i viktiga samhällsfunktioner.</p>
            <p>Vi hämtar meddelandena från Sveriges Radio.</p>
        </div>

        @if ($currentAlerts->count())
            <h2>Aktuella meddelanden</h2>

            <ul class="list-none p-0">
                @foreach ($currentAlerts as $alert)
                    <li class="mb-6 pb-6 u-border-bottom">
                        <a href="{{ $alert->getPermalink() }}">
                            <h2 class="m-0 font-normal">
                                {{ $alert->getShortDescription() }}
                            </h2>
                        </a>

                        <p class="m-0 mt-2 text-sm"><time
                                datetime="{{ $alert->getIsoSentDateTime() }}">{{ $alert->getHumanSentDateTime() }}</time>
                        </p>

                        <p class="m-0 mt-2 excerpt">{!! $alert->getTeaser() !!}</p>
                    </li>
                @endforeach
            </ul>
        @endif

        <h2>Senaste meddelandena</h2>

        <ul class="list-none p-0">
            @foreach ($alerts as $alert)
                <li class="mb-6 pb-6 u-border-bottom">
                    <a href="{{ $alert->getPermalink() }}">
                        <h2 class="m-0 font-normal">
                            {{ $alert->getShortDescription() }}
                        </h2>
                    </a>

                    <p class="m-0 mt-2 text-sm"><time
                            datetime="{{ $alert->getIsoSentDateTime() }}">{{ $alert->getHumanSentDateTime() }}</time>
                    </p>

                    <p class="m-0 mt-2 excerpt">{!! $alert->getTeaser() !!}</p>
                </li>
            @endforeach
        </ul>

        <p>
            Vi hämtar listan med VMA genom att använda
            <a href="https://vmaapi.sr.se/api/v2/">"Sveriges Radio's API for Important Public Announcements"</a>.
        </p>

    </div>

@endsection

@section('sidebar')
    @include('parts.vma.sidebar', ['hideVMAAlerts' => true])
@endsection
