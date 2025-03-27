@if ($paginator->hasPages())

    <nav class="pagination">
        <div>
            <p class="">
                Visar
                @if ($paginator->firstItem())
                    <span class="">{{ $paginator->firstItem() }}</span>
                    till
                    <span class="">{{ $paginator->lastItem() }}</span>
                @else
                    {{ $paginator->count() }}
                @endif
                av
                <span class="">{{ $paginator->total() }}</span>
                resultat
            </p>
        </div>

        <ul class="pagination">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <li class="disabled"><span>&laquo;</span></li>
            @else
                <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev">&laquo; Föregående</a></li>
            @endif

            {{-- Pagination Elements --}}
            @foreach ($elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <li class="disabled"><span>{{ $element }}</span></li>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="active"><span>{{ $page }}</span></li>
                        @else
                            <li><a href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <li><a href="{{ $paginator->nextPageUrl() }}" rel="next">Nästa &raquo;</a></li>
            @else
                <li class="disabled"><span>&raquo;</span></li>
            @endif
        </ul>

    </nav>
@endif
