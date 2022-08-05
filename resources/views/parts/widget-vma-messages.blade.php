{{-- Widget som visar aktuella och tidigare VMA-meddelanden --}}
<section class="widget">
    <h2 class="widget__title">
        <a href="{{ route('vma-overview') }}">VMA-meddelanden</a>
    </h2>

    <ul class="widget__listItems">
        @foreach ($shared_vma_current_alerts as $alert)
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

        @foreach ($shared_vma_alerts->slice(0, 3) as $alert)
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

    <hr />

    <p>
        <a href="{{ route('vma-overview') }}">Se tidigare VMA-meddelanden</a>
        |
        <a href="{{ route('vma-textpage', ['slug' => 'om-vma']) }}">Vad är VMA?</a>
    </p>
</section>
