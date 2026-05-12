{{-- Widget som visar aktuella och tidigare VMA-meddelanden --}}
<section class="widget" id="vma">
    <h2 class="widget__title">

        <svg xmlns="http://www.w3.org/2000/svg" class="align-text-bottom" height="18" width="18" viewBox="0 0 48 48">
            <path
                d="M2 42 24 4l22 38Zm5.2-3h33.6L24 10Zm17-2.85q.65 0 1.075-.425.425-.425.425-1.075 0-.65-.425-1.075-.425-.425-1.075-.425-.65 0-1.075.425Q22.7 34 22.7 34.65q0 .65.425 1.075.425.425 1.075.425Zm-1.5-5.55h3V19.4h-3Zm1.3-6.1Z" />
        </svg>

        <a href="{{ route('vma-overview') }}">VMA-meddelanden</a>
    </h2>
    
    <ul class="widget__listItems">
        @foreach ($shared_vma_current_alerts as $alert)
            <li class="widget__listItem"
                style="
                    border-left: 8px solid #bb0a22;
                    padding: 10px;
                    background-color: #f2dee1;">
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

    {{-- Arkiverade VMA viks ihop bakom CSS-only toggle på mobil
         (todo #71 Fas 3). Desktop ser hela listan. --}}
    @php($archivedVmas = $shared_archived_vma_alerts->slice(0, 3))
    @if ($archivedVmas->isNotEmpty())
        <div class="MobileCollapse MobileCollapse--vma">
            <input type="checkbox" id="mc-vma-archived" class="MobileCollapse__toggle">
            <label for="mc-vma-archived" class="MobileCollapse__summary">Visa tidigare VMA</label>
            <ul class="widget__listItems MobileCollapse__content">
                @foreach ($archivedVmas as $alert)
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
        </div>
    @endif

    <hr />

    <p>
        <a href="{{ route('vma-overview') }}">Se tidigare VMA-meddelanden</a>
        |
        <a href="{{ route('vma-textpage', ['slug' => 'om-vma']) }}">Vad är VMA?</a>
    </p>
</section>
