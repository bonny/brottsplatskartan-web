<?php
$title = "Följ oss på Facebook";
$url = 'https://www.facebook.com/Brottsplatskartan/';
?>

@push('scripts')
    {{-- <script async custom-element="amp-facebook-page" src="https://cdn.ampproject.org/v0/amp-facebook-page-0.1.js"></script> --}}
@endpush

<section class="widget widget--blogentries">

    <h2 class="widget__title">{{$title}}</h2>

    <amp-facebook-page 
        width="500" 
        height="800"
        layout="responsive"
        data-hide-cover="false"
        data-small-header="true"
        data-tabs="timeline"
        data-href="{{$url}}"
    >
    </amp-facebook-page>

</section>


