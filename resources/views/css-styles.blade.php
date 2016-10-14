
:root {
    --color-grey-light: #ddd;
}

html, body {
    background: white;
    font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;
    font-size: 16px;
    line-height: 1.4;
}

body {
    padding-top: 80px;
    /*background: #fafafa;*/
    background: #f4f4f7;
}

h1, h2, h3, h4 {
    font-weight: normal;
    font-weight: 300;
}

h1, h2, h3, h4, p, ul, ol {
    margin-top: .25rem;
    margin-bottom: .25rem;
}

a {
    color: #0379C4;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

.container {
    box-sizing: border-box;
    margin: 0 auto;
    max-width: 1000px;
    padding: 0 20px;
}

.SiteHeader {
    background: #fff;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 5;
    padding: 2em;
    box-shadow: 2px 1px 1px rgba(0,0,0,0.15);
    font-size: .75rem;
}

.SiteTitle {
    margin: 0;
    line-height: 1;
    text-transform: uppercase;
}

.SiteTagline {
    margin-top: .5em;
    margin-bottom: 0;
}

.SiteTitle a {
    text-decoration: none;
    color: inherit;
}

.Event {
    margin-top: 2rem;
    margin-bottom: 2rem;
    background: white;
    padding: 1rem;
    box-shadow: 0 1px 2px rgba(0,0,0,.3);
}

.Event__title {
    line-height: 1;
    margin-top: 1rem;
    margin-bottom: .25rem;
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
    line-height: 1;
    margin-top: .75rem;
    margin-bottom: .75rem;
    padding-bottom: .75rem;
    /*border-bottom: 1px solid var(--color-grey-light);*/
}

.Event__metaDivider {
    color: #aaa;
    margin-left: .25rem;
    margin-right: .25rem;
    -webkit-font-smoothing: none;
}

.Event__dateHuman {
}

.Event__dateFormatted {
}

.Event--single .Event__title {

}


.Event__teaser {
    font-weight: bold;
    /*font-size: 1.25rem;*/
    /*color: #616161;*/
}

.Event__mapImage {
}

.pagination {
    text-align: center;
    width: 100%;
    line-height: 1;
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
