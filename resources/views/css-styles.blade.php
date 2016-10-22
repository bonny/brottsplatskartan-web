:root {
    --color-grey-light: #ddd;
    --color-link: #0379C4;
    --default-margin: 1rem;
    --default-margin-half: calc(var(--default-margin)/2);
    --font-size-small: .9rem;
}

html, body {
    background: white;
    font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;
    /*font-family: sans-serif;*/
    font-weight: normal;
    font-size: 16px;
    line-height: 1.4;
}

body {
    padding-top: 100px;
    /*background: #fafafa;*/
    background: #f4f4f7;
    margin: 0;
}

h1, h2, h3, h4 {
    font-weight: normal;
    font-weight: 300;
}

h1, h2, h3, h4, ul, ol {
    margin-top: var(--default-margin);
    margin-bottom: var(--default-margin);
}

a {
    color: var(--color-link);
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

.container {
    margin: 0 auto;
    max-width: 1000px;
    padding: 0 10px;
}

.SiteHeader {
    background: #fff;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 5;
    padding: var(--default-margin);
    box-shadow: 2px 1px 1px rgba(0,0,0,0.15);
    font-size: .75rem;

    /* from http://uigradients.com/ */
    background: #fceabb; /* fallback for old browsers */
    background: -webkit-linear-gradient(to left, #fceabb , #f8b500); /* Chrome 10-25, Safari 5.1-6 */
    background: linear-gradient(to left, #fceabb , #f8b500); /* W3C, IE 10+/ Edge, Firefox 16+, Chrome 26+, Opera 12+, Safari 7+ */

}

.SiteHeader__inner {
    max-width: 980px;
    margin: 0 auto;
}

.SiteTitle {
    margin: 0;
    line-height: 1;
    text-transform: uppercase;
    font-size: 1.25rem;
}

.SiteTagline {
    margin-top: .5em;
    margin-bottom: 0;
    font-style:normal;
    line-height: 1;
}
.SiteTagline em {
    font-style: inherit;
}

.SiteTitle a {
    text-decoration: none;
    color: inherit;
}

/* Site navigation */
.SiteNav {
    display: block;
    margin-left: -1rem;
    margin-right: -1rem;
    margin-bottom: -1rem;
    background-color: rgba(255, 255, 255, .75);
}

.SiteNav__items {
    margin: 0;
    padding: 0;
    list-style-type: none;
    text-align: center;
}

.SiteNav__item {
    display: inline-block;
    text-align: center;
    width: 25%;
}

.SiteNav__item a {
    display: block;
    padding-top: .5rem;
    padding-bottom: .5rem;
    /*background: #FFF;*/
    color: inherit;
}

.SiteNav__item svg,
.SiteNav__item span {
    display: inline-block;
    vertical-align: middle;
}

.SiteNav__item span {
    display: inline-block;
}

.Events {
    /*column-count: 2;*/
    overflow: auto;
}

.Event {
    margin-top: 2rem;
    margin-bottom: 2rem;
    background: white;
    padding: var(--default-margin);
    box-shadow: 0 1px 2px rgba(0,0,0,.3);
}

.Event:nth-of-type(1) {
    margin-top: 0;
}

.Event__title {
    line-height: 1;
    margin-top: var(--default-margin);
    margin-bottom: .25rem;
    word-break: break-all;
}

.Event__date {
    /*margin-bottom: .75rem;*/
    margin-top: .75rem;
}

.Event__location {
    /*color: #999;*/
    /*margin-bottom: .25rem;*/
    margin-bottom: .75rem;
}

.Event__date,
.Event__location {
    /*font-size: .8rem;*/
}

.Event__meta {
    line-height: 1.3;
    margin-top: .75rem;
    margin-bottom: .75rem;
    padding-bottom: .75rem;
    border-bottom: 1px solid var(--color-grey-light);
    margin-left: -1rem;
    margin-right: -1rem;
    padding-left: 1rem;
    padding-right: 1rem;
    font-size: var(--font-size-small);
    color: #666;
}

.Event__metaDivider {
    color: var(--color-grey-light);
    margin-left: .25rem;
    margin-right: .25rem;
    /*-webkit-font-smoothing: none;*/
}

.Event__dateHuman {
    white-space: nowrap;
}

.Event__dateFormatted {
}

.Event--single .Event__title {
    font-size: 2.25rem;
}


.Event__teaser {
    font-weight: bold;
    margin-top: 0;
    margin-bottom: var(--default-margin-half);
}

.Event__teaser p {
    margin: 0;
}

.Event__content {
    overflow: hidden;
    position: relative;
    margin-bottom: 0;
}

.Event__content p {
    margin-top: var(--default-margin-half);
    margin-bottom: var(--default-margin-half);
}

.Event__content p:first-child {
    margin-top: 0;
}

.Event__content p:last-child {
    margin-bottom: 0;
}

.Event__contentLink {
    display: block;
    color: inherit;
}

.Event__contentLink:hover {
    text-decoration: none;
    /*color: var(--color-link);*/
}

/* emulate p tags using br */
.Event__content br {
    line-height: 2;
}

.Event__related {
    margin-top: var(--default-margin);
    border-top: 1px solid var(--color-grey-light);
    margin-left: -1rem;
    margin-right: -1rem;
    padding-left: 1rem;
    padding-top: 1rem;
}

.Event__share {
    line-height: 1;
    margin-top: var(--default-margin);
    border-top: 1px solid var(--color-grey-light);
    margin-left: -1rem;
    margin-right: -1rem;
    padding-left: 1rem;
    padding-top: 1rem;
}

amp-social-share {
    margin-right: .25rem;
}

/*
.Event__content {
    max-height: 5rem;
}
.Event__content:after {
    content: "";
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 2rem;
    background: linear-gradient( rgba(255, 255, 255, 0), white)
}
*/

.Event__map {
    margin-top: -1rem;
    margin-left: -1rem;
    margin-right: -1rem;
    line-height: 1;
    display: block;
}

.SearchForm {
    margin-bottom: var(--default-margin);
}

.SearchForm__s,
.SearchForm__submit {
    font-size: 1rem;
    padding: var(--default-margin-half);
    display: inline-block;
}

.SearchForm__s {
    width: calc(100% - 5rem);
}

.SearchForm__submit {
    width: 4rem;
}

.HeaderSearch {
    float: right;
    margin-top: -1rem;
    width: 30%;
    text-align: right;
    line-height: 1;
    margin-bottom: -1rem;
}

.HeaderSearch__s {
    width: 100%;
}

.HeaderSearch__submit {
    display: none;
}

.pagination {
    text-align: center;
    width: 100%;
    line-height: 1;
    padding: 0;
    margin-left: 0;
    margin-right: 0;
    margin-top: var(--default-margin);
    margin-bottom: var(--default-margin);
}

.pagination li {
    display: inline-block;
}

.pagination li > a,
.pagination li > span {
    display: block;
    padding: .25em;
}
.pagination li > span {
    font-weight: bold;
}
.pagination li > a:hover {
    background: #ccc;
}

.LanListing,
.PlatsListing {
    overflow: hidden;
}

.LanListing__lan,
.PlatsListing__plats {
    width: 50%;
    float: left;
    font-size: 1rem;
    padding-top: var(--default-margin-half);
    padding-bottom: var(--default-margin-half);
    margin: 0;
}

.SiteFooter {
    background: var(--color-grey-light);
    padding: .5rem;
    margin-top: var(--default-margin);
    margin-top: 4rem;
}

.Breadcrumbs {
    list-style: none;
    margin-bottom: var(--default-margin);
    margin-left: 0;
    margin-right: 0;
    margin-top: var(--default-margin);
    /*overflow: hidden;*/
    padding: 0;
    line-height: 1.2;
    font-size: var(--font-size-small);
}

.breadcrumbs {
    list-style: none;
    margin: 0;
    padding: 0;
    overflow: hidden;
}

.Breadcrumbs__intro,
.breadcrumbs,
.breadcrumbs li,
.breadcrumbs a,
.breadcrumbs .divider,
.Breadcrumbs__switchLan {
    display: inline-block;
    vertical-align: top;
    line-height: 1.2;
}

.breadcrumbs li {
    position: relative;
    margin-right: 1.5rem;
}

.breadcrumbs li:last-child {
    margin-right: .5rem;
}

.breadcrumbs a {
    display: block;
    position: relative;
}

.Breadcrumbs__intro {
    margin-right: var(--default-margin-half);
}

.breadcrumbs .divider {
    position: absolute;
    top: 0;
    right: -.9rem;
    color: #999;
}

.Breadcrumbs__switchLan {
    background: var(--color-link);
    color: #FFF;
    padding: .4rem .8rem;
    font-size: .75rem;
    margin-top: -.3rem;
    margin-left: .75rem;
    border-radius: 3px;
    vertical-align: middle;
}

.Breadcrumbs__switchLan:hover {
    text-decoration: none;
    background: var(--color-link);
}

/* ~ipad~ iphone 6 liggande and other medium to large screens */
@media only screen and (min-width: 667px) {

    .Events--overview .Event {
        float: left;
        /*break-inside: avoid;*/
        /*display: inline-block;*/
        width: 48.5%;
        margin-top: var(--default-margin);
        margin-bottom: var(--default-margin);
    }

    .Events--overview .Event:nth-of-type(1),
    .Events--overview .Event:nth-of-type(2) {
        margin-top: 0;
    }

    .Events--overview .Event:nth-child(odd) {
        clear: left;
    }

    .Events--overview .Event:nth-child(even) {
        /* margin to show box shadow */
        margin-right: 2px;
        float: right;
    }

}
