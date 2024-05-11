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
    </style>
@endonce

<div class="TextTVBox">
    <h2 class="TextTVBox-title">Nyheter från Text TV</h2>

    <p>Presenteras i samarbete med <a href="https://texttv.nu/">TextTV.nu</a>.</p>

    <h3>Senaste nytt</h3>

    <ul class="TextTVBox-newslisting">
        @foreach ($latestNews as $news)
            <li>
                <a href="{{ $news['permalink'] }}">
                    <div class="TextTVBox-newslisting-text">
                        <div>{{ $news['date_added_time'] }}</div>
                        <div>{{ $news['title'] }}</div>
                    </div>
                    {{-- <p>{{ $news['page_content'] }}</p> --}}
                </a>
            </li>
        @endforeach
    </ul>

    <h3>Mest läst</h3>

    <ul class="TextTVBox-newslisting">
        @foreach ($mostRead as $news)
            <li>
                <a href="{{ $news['permalink'] }}">
                    <div class="TextTVBox-newslisting-text">
                        <div>{{ $news['date_added_time'] }}</div>
                        <div>{{ $news['title'] }}</div>
                    </div>
                    {{-- <p>{{ $news['page_content'] }}</p> --}}
                </a>
            </li>
        @endforeach
    </ul>

</div>
