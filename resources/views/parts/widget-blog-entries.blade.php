{{--
modul i sidebar med länkar till alla län
--}}

<?php
$latestBlogItems = Cache::remember('widgetLatestBlogItems', 1 * 60, function() {
    return \App\Blog::orderBy("created_at", "desc")->paginate(3);
});
?>

<section class="widget widget--blogentries" id="blogg">

    <h2 class="widget__title">Senaste inläggen från vår blogg</h2>

    {{-- På mobil visas första inlägget, resten viks ihop bakom toggle
         (todo #71 Fas 3). Desktop ser hela listan. --}}
    @php
        $blogItemsCollection = collect($latestBlogItems->items());
        $blogVisible = $blogItemsCollection->take(1);
        $blogHidden = $blogItemsCollection->slice(1);
    @endphp

    <ul class="widget__listItems">
        @foreach ($blogVisible as $blogItem)
            <li class="widget__listItem">
                <p class="widget__listItem__preTitle">
                    <time datetime='{{ $blogItem->getCreatedAtAsW3cString() }}'>{{ $blogItem->getCreatedAtFormatted() }}</time>
                </p>
                <h3 class="widget__listItem__title"><a href="{{ $blogItem->getPermalink() }}">{{ $blogItem->title }}</a></h3>
            </li>
        @endforeach
    </ul>

    @if ($blogHidden->isNotEmpty())
        <div class="MobileCollapse MobileCollapse--sidebar">
            <input type="checkbox" id="mc-blog-more" class="MobileCollapse__toggle">
            <label for="mc-blog-more" class="MobileCollapse__summary">Visa {{ $blogHidden->count() }} fler blogginlägg</label>
            <ul class="widget__listItems MobileCollapse__content">
                @foreach ($blogHidden as $blogItem)
                    <li class="widget__listItem">
                        <p class="widget__listItem__preTitle">
                            <time datetime='{{ $blogItem->getCreatedAtAsW3cString() }}'>{{ $blogItem->getCreatedAtFormatted() }}</time>
                        </p>
                        <h3 class="widget__listItem__title"><a href="{{ $blogItem->getPermalink() }}">{{ $blogItem->title }}</a></h3>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <p>
        <a href="{{route('blog')}}">» Se alla blogginlägg</a>
    </p>

</section>
