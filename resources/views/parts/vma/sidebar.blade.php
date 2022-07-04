<nav class="widget">
    <h2>VMA Navigation</h2>
    <ul>
        <li><a href="{{ route('vma-overview') }}">Aktuella och tidigare VMA</a></li>
        <li><a href="{{ route('vma-textpage', ['slug' => 'om-vma']) }}">Vad är VMA?</a></li>
        <li><a href="{{ route('vma-textpage', ['slug' => 'vanliga-fragor-och-svar-om-vma']) }}">Vanliga frågor &
                svar</a>
        </li>
    </ul>
</nav>

@unless(isset($hideVMAAlerts) && $hideVMAAlerts === true)
    <section class="widget">
        <h2>VMA-Meddelanden</h2>

        <ul class="widget__listItems">
            @foreach ($shared_vma_alerts as $alert)
                <li class="widget__listItem">
                    <p class="widget__listItem__preTitle">
                        <time datetime="{{ $alert->getIsoSentDateTime() }}">{{ $alert->getHumanSentDate() }}</time>
                    </p>
                    <p class="widget__listItem__title">
                        <a href="{{ $alert->getPermalink() }}">
                            {{ $alert->getShortDescription() }}
                        </a>
                    </p>
                </li>
            @endforeach
        </ul>
    </section>
@endunless


<section class="widget widget--follow">
    <h2 class="widget__title">VMA är ett varningssystem</h2>
    <p>VMA är en förkortning av <em>"Viktigt Meddelande till Allmänheten"</em></p>
    <p>Det är ett varningssystem som används vid olyckor, allvarliga händelser och störningar i viktiga
        samhällsfunktioner.</p>
    <p><a href="{{ route('vma-textpage', ['slug' => 'om-vma']) }}">Om VMA</a>
        |
        <a href="{{ route('vma-textpage', ['slug' => 'vanliga-fragor-och-svar-om-vma']) }} ">Vanliga frågor.</a>
    </p>
</section>
{{-- @include('parts.lan-and-cities')
@include('parts.follow-us') --}}
