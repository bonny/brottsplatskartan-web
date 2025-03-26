@once
    <style>
        .TextTVBox {
            background: #111;
            color: #eee;
            padding: var(--default-margin);
            margin: var(--default-margin) 0;
            border-radius: var(--border-radius-normal);
        }

        .TextTVBox a {
            color: inherit;
        }

        .TextTVBox-title {
            margin-top: 0;
        }

        .TextTVBox-newslisting {
            display: flex;
            flex-direction: column;
            gap: var(--default-margin);
            list-style: none;
            padding: 0;
            font-family: 'monaco', 'lucida', 'courier', monospace;
        }

        .TextTVBox-newslisting-text {
            display: flex;
            gap: var(--default-margin);
            color: rgb(252, 254, 1);
            text-wrap: pretty;
        }

        .TextTVBox-pages {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            justify-content: space-evenly;
            border-top: 1px solid var(--color-gray-1);
            border-bottom: 1px solid var(--color-gray-1);
            margin-block-start: 1rem;

            a {
                display: block;
                padding: 1rem;
            }
        }
    </style>
@endonce

<div class="TextTVBox">
    <h2 class="TextTVBox-title">Nyheter från Text TV</h2>

    <p>Presenteras i samarbete med <a href="https://texttv.nu/">TextTV.nu</a>.</p>

    <ul class="TextTVBox-pages">
        <li><a href="https://texttv.nu/100">Nyheter</a></li>
        <li><a href="https://texttv.nu/300">Sport</a></li>
        <li><a href="https://texttv.nu/377">Målservice</a></li>
        <li><a href="https://texttv.nu/400">Väder</a></li>
        <li><a href="https://texttv.nu/700">Innehåll</a></li>
    </ul>


    @if (isset($latestNews))
        <h3>Senaste nytt</h3>

        <ul class="TextTVBox-newslisting">
            @foreach ($latestNews as $news)
                <li>
                    <a href="{{ $news['permalink'] }}?utm_source=brottsplatskartan&utm_medium=newslist">
                        <div class="TextTVBox-newslisting-text">
                            <div>{{ $news['date_added_time'] }}</div>
                            <div>{{ $news['title'] }}</div>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    @if (isset($mostRead))
        <h3>Mest läst</h3>

        <ul class="TextTVBox-newslisting">
            @foreach ($mostRead as $news)
                <li>
                    <a href="{{ $news['permalink'] }}?utm_source=brottsplatskartan&utm_medium=newslist">
                        <div class="TextTVBox-newslisting-text">
                            <div>{{ $news['date_added_time'] }}</div>
                            <div>{{ $news['title'] }}</div>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
