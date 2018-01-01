<li>
    <article class="BlogItem">
        <h2><a href="{{ $blog->getPermalink() }}">{{ $blog->title }}</a></h2>

        <p class="Event__meta">
            Postat <time datetime='{{ $blog->getCreatedAtAsW3cString() }}'>{{ $blog->getCreatedAtFormatted() }}</time>
        </p>

        {{ $blog->getExcerpt() }}
    </article>
</li>
