@if (empty($overview) && Auth::check())

    @if ($event->title_alt_1 || $event->description_alt_1)
        <details class="mt-6">
            <summary>Visa alternativ text</summary>

            <p>
                <strong>Alternativ titel:</strong>
                <br>
                {{ $event->title_alt_1 }}
            </p>

            <p>
                <strong>Alternativ text:</strong>
                <br>{!! $event->autop($event->description_alt_1) !!}
            </p>
        </details>
    @endif

    @php
        $vagueBucket = \App\CrimeEvent::isVagueTitle($event->parsed_title);
        $rewriteBucket = \App\CrimeEvent::shouldRewriteTitle($event);
        $bodyLen = mb_strlen(trim($event->parsed_content ?? ''));
        $titleAltLen = $event->title_alt_1 ? mb_strlen($event->title_alt_1) : 0;
        $descAltLen = $event->description_alt_1 ? mb_strlen($event->description_alt_1) : 0;
        $parsedTitleLen = mb_strlen($event->parsed_title ?? '');
    @endphp

    <details class="mt-6">
        <summary>AI-info</summary>

        <table class="text-sm">
            <tr>
                <td><strong>AI-titel:</strong></td>
                <td>
                    @if ($event->title_alt_1)
                        ✓ ja ({{ $titleAltLen }} tecken)
                    @else
                        – nej
                    @endif
                </td>
            </tr>
            <tr>
                <td><strong>AI-brödtext:</strong></td>
                <td>
                    @if ($event->description_alt_1)
                        ✓ ja ({{ $descAltLen }} tecken)
                    @else
                        – nej
                    @endif
                </td>
            </tr>
            <tr>
                <td><strong>Originaltitel:</strong></td>
                <td>"{{ $event->parsed_title }}" ({{ $parsedTitleLen }} tecken)</td>
            </tr>
            <tr>
                <td><strong>Vag-bucket:</strong></td>
                <td>
                    @if ($vagueBucket)
                        <code>{{ $vagueBucket }}</code>
                    @else
                        – OK (inte vag)
                    @endif
                </td>
            </tr>
            <tr>
                <td><strong>Brödtext-längd:</strong></td>
                <td>
                    {{ $bodyLen }} tecken
                    @if ($bodyLen < 100)
                        (för kort för AI-rewrite, kräver ≥100)
                    @endif
                </td>
            </tr>
            <tr>
                <td><strong>AI-rewrite-kandidat:</strong></td>
                <td>
                    @if ($event->title_alt_1)
                        – redan omskrivet
                    @elseif ($rewriteBucket)
                        ✓ ja (<code>{{ $rewriteBucket }}</code>) — auto-trigger plockar upp inom 15 min
                    @else
                        – nej
                    @endif
                </td>
            </tr>
        </table>
    </details>

    @php
        $open = $errors->any() || session('status');
    @endphp

    <div class="Event__admin" id="event-admin">
        <details @if ($open) open @endif>
            <summary class="cursor-pointer"
                onclick="setTimeout(() => { document.querySelector('input[name=url]').focus(); }, 0);">Admingrejjer
            </summary>

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif

            <form id="AdminFormAddMediaRef" method='post' class="AdminForm AdminForm--addMediaRef" target="_top">
                <fieldset>
                    <legend>Händelsen i media</legend>

                    <p class="AddMediaFormFields">
                        <label for="AdminFormAddMediaRef-url">
                            URL<br>
                            <input type="url" id="AdminFormAddMediaRef-url" name="url" placeholder="" autocomplete="off"
                                value="{{ old('url') }}">
                        </label>
                        <label for="AdminFormAddMediaRef-title">
                            Titel<br>
                            <input type="text" id="AdminFormAddMediaRef-title" name="title" placeholder="" autocomplete="off"
                                value="{{ old('title') }}">
                        </label>
                        <label for="AdminFormAddMediaRef-shortdesc">
                            Kort beskrivning<br>
                            <input type="text" id="AdminFormAddMediaRef-shortdesc" name="shortdesc" placeholder="" autocomplete="off"
                                value="{{ old('shortdesc') }}">
                        </label>
                    </p>

                    <p>
                        <input type="hidden" name="eventAction" value="addMediaReference">
                        {{ csrf_field() }}
                        <button type="submit">Spara media</button>
                    </p>

                    {{-- <div id="AdminFormAddMediaRef__successMessage" hidden>
                        <p>Ok! Tillagd!</p>
                    </div>

                    <div id="AdminFormAddMediaRef__errorMessage" hidden>
                        <p>Dang, något gick fel när media skulle sparas.</p>
                    </div> --}}

                </fieldset>

            </form>

            <form method='get' action='{{ url()->current() }}' target="_top">
                <fieldset>
                    <legend>Platser</legend>

                    <p>
                        <label for="AdminFormPlatser-locationAdd">
                            Lägg till plats<br>
                            <input type="text" id="AdminFormPlatser-locationAdd" name="locationAdd" placeholder="Hejsanhoppsangränd"
                                autocomplete="off">
                        </label>
                    </p>

                    <p>
                        <label for="AdminFormPlatser-locationIgnore">
                            Ignorera plats<br>
                            <input type="text" id="AdminFormPlatser-locationIgnore" name="locationIgnore" placeholder="Ipsumvägen" autocomplete="off">
                        </label>
                    </p>

                    <p>
                        <input type="hidden" name="debugActions[]" value="clearLocation">
                        <button>Rensa location-data &amp; hämta info &amp; plats igen</button>
                    </p>

                </fieldset>
            </form>

        </details>

    </div>
@endif
