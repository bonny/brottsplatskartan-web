<!DOCTYPE html>
<html lang="sv">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>B3 admin</title>

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/foundation/6.2.3/foundation.min.css" />
        <style>
            html {
                font-size: 95%;
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
        </style>

    </head>
    <body>

        <div class="row expanded">

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
                    <table class="table-scroll">
                        <thead>
                            <tr>
                                <td>{{-- room for actions --}}</td>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Date parsed</th>
                                <th>Title parsed</th>
                                <th>Title location</th>
                                <th>Description</th>
                                <th>Permalink</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($events as $event)
                                <tr>
                                    <td>
                                        <a
                                            href={{ route('adminDashboard', [ "parseItem" => $event->getKey() ]) }}
                                            class="button small secondary">Parse</a>
                                    </td>
                                    <td>{{ $event->getKey() }}</td>
                                    <td>{{ $event["title"] }}</td>
                                    <td>{{ $event["parsed_date"] }}</td>
                                    <td>{{ $event["parsed_title"] }}</td>
                                    <td>{{ $event["parsed_title_location"] }}</td>
                                    <td>{{ $event["description"] }}</td>
                                    <td><a href="{{ $event["permalink"] }}">Link</a></td>
                                </tr>
                                <tr>
                                    <td colspan=2></td>
                                    <td colspan=2>{{ $event["parsed_teaser"] }}</td>
                                    <td colspan=4>{{ $event["parsed_content"] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
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
