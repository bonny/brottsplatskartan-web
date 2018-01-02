<article class="BlogItem">
    <h1>{{ $blog->title }}</h1>

    <p class="Event__meta">
        Postat <time datetime='{{ $blog->getCreatedAtAsW3cString() }}'>{{ $blog->getCreatedAtFormatted() }}</time>
    </p>

    {!! $blog->getContentFormatted() !!}
</article>
