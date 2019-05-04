{{--
modul i sidebar med länkar till alla län
--}}

<?php
$latestBlogItems = Cache::remember('widgetLatestBlogItems', 1 * 60, function() {
    return \App\Blog::orderBy("created_at", "desc")->paginate(3);
});
?>

<section class="widget widget--blogentries">

    <h2 class="widget__title">Senaste inläggen från vår blogg</h2>

    <ul class="widget__listItems">
        @foreach ($latestBlogItems as $blogItem)
            <li class="widget__listItem">
                <p class="widget__listItem__preTitle">
                    <time datetime='{{ $blogItem->getCreatedAtAsW3cString() }}'>{{ $blogItem->getCreatedAtFormatted() }}</time>
                </p>
                <h3 class="widget__listItem__title"><a href="{{ $blogItem->getPermalink() }}">{{ $blogItem->title }}</a></h3>
            </li>
        @endforeach
    </ul>

    <p>
        <a href="{{route('blog')}}">» Se alla blogginlägg</a>
    </p>

</section>
