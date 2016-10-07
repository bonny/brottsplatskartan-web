<!DOCTYPE html>
<html lang="sv" class="admin">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>B3 admin</title>

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/foundation/6.2.3/foundation.min.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/foundicons/3.0.0/foundation-icons.min.css" />

        <style>
            html {
                /*font-size: 95%;*/
            }
            body {
                margin-top: 20px;
                margin-bottom: 20px;
            }
            .pagination {
                text-align: center;
            }
            .pagination li {
            }
            .pagination .active span {
                font-weight: bold;
                background: #eee;
                color: #0a0a0a;
                padding: .1875rem .625rem;
                border-radius: 0;
            }
            .CrimeEventsTable th,
            .CrimeEventsTable td {
                vertical-align: top;
            }
            hr {
                max-width: none;
            }
            .CrimeEventCard {
                margin-top: 2em;
                margin-bottom: 1em;
                padding-top: 2em;
                padding-bottom: 1em;
                border-top: 1px solid #ccc;
            }
            :target {
                background: Thistle;
                padding-left: 1em;
                padding-right: 1em;
            }
        </style>

    </head>
    <body>

        <div class="row xexpanded">

            <div class="small-12 columns">
                <h2>
                    <a href="{{ route('adminDashboard') }}">Brottsplatskartan admin</a>
                </h2>

                <h4>updateFeedsFromPolisen resultat</h4>

                <ul>
                    <li>numItemsAdded: {{ $feedsUpdateResult["numItemsAdded"] }}</li>
                    <li>numItemsAlreadyAdded: {{ $feedsUpdateResult["numItemsAlreadyAdded"] }}</li>

                    @if ($feedsUpdateResult["itemsAdded"])
                    <li>
                        itemsAdded:
                        <ul>
                            @foreach ($feedsUpdateResult["itemsAdded"] as $item)
                                <li>{{ $item["title"] }}</li>
                            @endforeach
                        </ul>
                    </li>
                    @endif

                </ul>

                <h4>Händelser</h4>

                @if ($events)

                    @foreach ($events as $event)

                        <div class="CrimeEventCard" id="event-{{ $event->getKey() }}">

                            @if ($event["parsed_teaser"])

                                <div class="row">

                                    <div class="small-4 columns">
                                        <b>Date</b>
                                        <br>{{ $event["parsed_date"] }}
                                    </div>

                                    <div class="small-4 columns">
                                        <b>Title/type</b>
                                        <br>{{ $event["parsed_title"] }}
                                    </div>

                                    <div class="small-4 columns">
                                        <b>Main location:</b>
                                        <br>{{ $event["parsed_title_location"] }}</p>
                                    </div>

                                </div>

                                <p>
                                    <b>Teaser:</b><br>
                                    {!! nl2br($event["parsed_teaser"]) !!}
                                </p>

                                <p>
                                    <b>Remote content:</b><br>
                                    {!! nl2br($event["parsed_content"]) !!}
                                </p>

                            @else

                                <p>
                                    Event not parsed, original content is:<br><br>
                                    <b>Original title:</b> {{ $event["title"] }}<br>
                                    <b>Original description:</b><br>
                                    {{ $event["description"] }}<br>
                                    {{-- <br>DBID: {{ $event->getKey() }} --}}
                                </p>


                            @endif

                            <ul class="menu simple">
                                <li>
                                    <a href="{{ route('adminDashboard', [ "parseItem" => $event->getKey() ]) }}#event-{{ $event->getKey() }}">
                                        <i class="fi-marker"></i> <span>Parse</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ $event["permalink"] }}">
                                        <i class="fi-link"></i>
                                        <span>Visa källa</span>
                                    </a>
                                </li>
                            </ul>

                        </div>

                    @endforeach

                @endif

                {{ $events->links() }}

            </div>

        </div>


        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery-migrate/3.0.0/jquery-migrate.min.js"></script>
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/foundation/6.2.3/foundation.min.js"></script>

        <script>
          $(document).foundation();
        </script>

    </body>
</html>
