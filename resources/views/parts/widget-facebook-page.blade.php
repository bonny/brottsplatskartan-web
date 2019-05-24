<?php
$title = "Följ oss på Facebook";
$url = 'https://www.facebook.com/Brottsplatskartan/';
?>

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


