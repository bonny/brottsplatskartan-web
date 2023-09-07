{{--

Template för /inbrott

--}}


@extends('layouts.web')

@section('title', $title)

@section('canonicalLink', $canonicalLink)

@section('content')

    <div class="widget">
        <h2 class="widget__title">Sociala medier</h2>

        <amp-twitter
            width="375"
            height="472"
            layout="responsive"
            data-tweetid="1265973916792696832"
            >
        </amp-twitter>

        <amp-facebook-page 
            width="552"
            height="700"
            layout="responsive"
            data-tabs="timeline"
            data-small-header="true"
            data-hide-cover="true"
            data-locale="sv_SE"
            data-href="https://www.facebook.com/Krisinformation/">
        </amp-facebook-page>

        <amp-facebook-page 
            width="552"
            height="700"
            layout="responsive"
            data-tabs="timeline"
            data-small-header="true"
            data-hide-cover="true"
            data-locale="sv_SE"
            data-href="https://www.facebook.com/Folkhalsomyndigheten/">
        </amp-facebook-page>

        <amp-facebook-page 
            width="552"
            height="700"
            layout="responsive"
            data-tabs="timeline"
            data-small-header="true"
            data-hide-cover="true"
            data-locale="sv_SE"
            data-href="https://www.facebook.com/polisen/">
        </amp-facebook-page>
        <amp-facebook-page 
            width="552"
            height="700"
            layout="responsive"
            data-tabs="timeline"
            data-small-header="true"
            data-hide-cover="true"
            data-locale="sv_SE"
            data-href="https://www.facebook.com/trafikpolisen.stockholm/">
        </amp-facebook-page>
        <amp-facebook-page 
            width="552"
            height="700"
            layout="responsive"
            data-tabs="timeline"
            data-small-header="true"
            data-hide-cover="true"
            data-locale="sv_SE"
            data-href="https://www.facebook.com/polisen.sodermalm/">
        </amp-facebook-page>
        <amp-facebook-page 
            width="552"
            height="700"
            layout="responsive"
            data-tabs="timeline"
            data-small-header="true"
            data-hide-cover="true"
            data-locale="sv_SE"
            data-href="https://www.facebook.com/MSBse/">
        </amp-facebook-page>
    </div>

@endsection

@section('sidebar')
    @include('parts.widget-blog-entries')
    @include('parts.lan-and-cities')
@endsection
