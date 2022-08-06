@foreach ($shared_vma_current_alerts as $alert)
    <p style="flex-grow: 1; margin: 0;">
        <a href="{{ $alert->getPermalink() }}"
            style="
                display: block;
                border-left: 8px solid #bb0a22;
                padding: 10px;
                background-color: #f2dee1;
            ">
            <strong>{{ $alert->getShortDescription() }}</strong>
        </a>
    </p>
@endforeach
