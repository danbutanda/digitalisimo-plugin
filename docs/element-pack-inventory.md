# Inventario estático de Element Pack

Fuente local: `digitalisimo-elements/bdthemes-element-pack`. Se identificaron **263 IDs Elementor únicos** en 265 archivos `modules/*/widgets/*.php`.
La carpeta es material de referencia, no una dependencia de ejecución ni un asset publicable. Este inventario se generó sin ejecutar PHP ajeno.

Las clasificaciones, complejidades y motores son **hipótesis de triaje**, no una aprobación para migrar. `UIkit` aparece como dependencia indirecta del loader original; cada uso real se debe validar antes de reconstruir. La ausencia de un marcador estático no prueba ausencia de AJAX, REST, dependencias o riesgos.

## Familias provisionales

| Motor | Widgets |
| --- | ---: |
| carousel | 34 |
| commerce | 10 |
| content-and-layout | 115 |
| data-visualization | 6 |
| forms | 26 |
| interaction | 3 |
| media | 17 |
| navigation | 15 |
| query | 37 |

## Clasificación provisional

| Clase | Widgets |
| --- | ---: |
| A - CORE CANDIDATE | 154 |
| B - ENGINE DERIVATIVE | 21 |
| C - INTEGRATION | 72 |
| E - REVIEW FOR DUPLICATION | 16 |

## Matriz de widgets

| Widget | ID legacy | Motor | Clase | Dependencia externa | CSS/JS |
| --- | --- | --- | --- | --- | --- |
| Accordion | `bdt-accordion` | navigation | A - CORE CANDIDATE | — | 1/1 |
| ACF Accordion | `bdt-acf-accordion` | navigation | C - INTEGRATION | Advanced Custom Fields | 0/0 |
| ACF Gallery | `bdt-acf-gallery` | media | C - INTEGRATION | Advanced Custom Fields | 0/0 |
| ACF List | `bdt-acf-list` | content-and-layout | C - INTEGRATION | Advanced Custom Fields | 0/0 |
| ACF Slider | `bdt-acf-slider` | carousel | C - INTEGRATION | Advanced Custom Fields | 0/0 |
| ACF Tabs | `bdt-acf-tabs` | navigation | C - INTEGRATION | Advanced Custom Fields | 0/0 |
| Advanced Button | `bdt-advanced-button` | content-and-layout | A - CORE CANDIDATE | — | 1/0 |
| Advanced Calculator | `bdt-advanced-calculator` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Advanced Counter | `bdt-advanced-counter` | data-visualization | A - CORE CANDIDATE | — | 1/1 |
| Advanced Divider | `bdt-advanced-divider` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Advanced Google Map | `bdt-advanced-gmap` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Advanced Heading | `bdt-advanced-heading` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Advanced Icon Box | `bdt-advanced-icon-box` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Advanced Image Gallery | `bdt-advanced-image-gallery` | media | A - CORE CANDIDATE | — | 1/1 |
| Advanced Progress Bar | `bdt-advanced-progress-bar` | data-visualization | A - CORE CANDIDATE | — | 1/1 |
| Age Gate | `bdt-age-gate` | content-and-layout | A - CORE CANDIDATE | — | 0/1 |
| Air Pollution | `bdt-air-pollution` | content-and-layout | A - CORE CANDIDATE | — | 0/0 |
| Animated Card | `bdt-animated-card` | content-and-layout | A - CORE CANDIDATE | — | 1/0 |
| Animated Heading | `bdt-animated-heading` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Animated Link | `bdt-animated-link` | content-and-layout | A - CORE CANDIDATE | — | 1/0 |
| Audio Player | `bdt-audio-player` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| BarCode | `bdt-barcode` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| bbPress Forum Form | `bdt-bbpress-forum-form` | forms | C - INTEGRATION | bbPress | 0/0 |
| bbPress Forum Index | `bdt-bbpress-forum-index` | content-and-layout | C - INTEGRATION | bbPress | 0/0 |
| bbPress Reply Form | `bdt-bbpress-reply-form` | forms | C - INTEGRATION | bbPress | 0/0 |
| bbPress Single Forum | `bdt-bbpress-single-forum` | content-and-layout | C - INTEGRATION | bbPress | 0/0 |
| bbPress Single Reply | `bdt-bbpress-single-reply` | content-and-layout | C - INTEGRATION | bbPress | 0/0 |
| bbPress Single Tag | `bdt-bbpress-single-tag` | content-and-layout | C - INTEGRATION | bbPress | 0/0 |
| bbPress Single Topic | `bdt-bbpress-single-topic` | content-and-layout | C - INTEGRATION | bbPress | 0/0 |
| bbPress Single View | `bdt-bbpress-single-view` | content-and-layout | C - INTEGRATION | bbPress | 0/0 |
| bbPress Stats | `bdt-bbpress-stats` | content-and-layout | C - INTEGRATION | bbPress | 0/0 |
| bbPress Topic Form | `bdt-bbpress-topic-form` | forms | C - INTEGRATION | bbPress | 0/0 |
| bbPress Topic Index | `bdt-bbpress-topic-index` | content-and-layout | C - INTEGRATION | bbPress | 0/0 |
| bbPress Topic Tags | `bdt-bbpress-topic-tags` | content-and-layout | C - INTEGRATION | bbPress | 0/0 |
| Brand Carousel | `bdt-brand-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 |
| Brand Grid | `bdt-brand-grid` | query | A - CORE CANDIDATE | — | 1/0 |
| Breadcrumbs | `bdt-breadcrumbs` | navigation | E - REVIEW FOR DUPLICATION | — | 1/0 |
| BuddyPress Friends | `bdt-buddypress-friends` | content-and-layout | C - INTEGRATION | BuddyPress | 0/0 |
| BuddyPress Group | `bdt-buddypress-group` | content-and-layout | C - INTEGRATION | BuddyPress | 0/0 |
| BuddyPress Member | `bdt-buddypress-member` | content-and-layout | C - INTEGRATION | BuddyPress | 0/0 |
| Business Hours | `bdt-business-hours` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Calendly | `bdt-calendly` | content-and-layout | A - CORE CANDIDATE | — | 0/0 |
| Call Out | `bdt-call-out` | content-and-layout | A - CORE CANDIDATE | — | 1/0 |
| Carousel | `bdt-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 |
| Changelog | `bdt-changelog` | content-and-layout | A - CORE CANDIDATE | — | 1/0 |
| Charitable Campaigns | `bdt-charitable-campaigns` | query | C - INTEGRATION | Charitable | 1/0 |
| Charitable Donation Form | `bdt-charitable-donation-form` | forms | C - INTEGRATION | Charitable | 1/0 |
| Charitable Donations | `bdt-charitable-donations` | query | C - INTEGRATION | Charitable | 1/0 |
| Charitable Donors | `bdt-charitable-donors` | query | C - INTEGRATION | Charitable | 1/0 |
| Charitable Login | `bdt-charitable-login` | forms | C - INTEGRATION | Charitable | 1/0 |
| Charitable Profile | `bdt-charitable-profile` | query | C - INTEGRATION | Charitable | 1/0 |
| Charitable Registration | `bdt-charitable-registration` | query | C - INTEGRATION | Charitable | 1/0 |
| Charitable Stat | `bdt-charitable-stat` | query | C - INTEGRATION | Charitable | 1/0 |
| Chart | `bdt-chart` | data-visualization | A - CORE CANDIDATE | — | 0/1 |
| Circle Info | `bdt-circle-info` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Circle Menu | `bdt-circle-menu` | navigation | A - CORE CANDIDATE | — | 1/1 |
| Comment | `bdt-comment` | content-and-layout | A - CORE CANDIDATE | — | 0/1 |
| Comparison List | `bdt-comparison-list` | content-and-layout | A - CORE CANDIDATE | — | 1/0 |
| Simple Contact Form | `bdt-contact-form` | forms | A - CORE CANDIDATE | — | 1/1 |
| Contact Form 7 | `bdt-contact-form-7` | forms | A - CORE CANDIDATE | — | 0/0 |
| Content Switcher | `bdt-content-switcher` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Cookie Consent | `bdt-cookie-consent` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Countdown | `bdt-countdown` | content-and-layout | E - REVIEW FOR DUPLICATION | — | 1/1 |
| Coupon Code | `bdt-coupon-code` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Creative Button | `bdt-creative-button` | content-and-layout | A - CORE CANDIDATE | — | 1/0 |
| Crypto Currency Card | `bdt-crypto-currency-card` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Crypto Currency Carousel | `bdt-crypto-currency-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 |
| Crypto Currency Chart | `bdt-crypto-currency-chart` | data-visualization | A - CORE CANDIDATE | — | 2/2 |
| Crypto Currency Chart Carousel | `bdt-crypto-currency-chart-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 |
| Crypto Currency Grid | `bdt-crypto-currency-grid` | query | A - CORE CANDIDATE | — | 1/1 |
| Crypto Currency List | `bdt-crypto-currency-list` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Crypto Currency Table | `bdt-crypto-currency-table` | query | A - CORE CANDIDATE | — | 1/1 |
| Crypto Currency Ticker | `bdt-crypto-currency-ticker` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Custom Carousel | `bdt-custom-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 |
| Custom Gallery | `bdt-custom-gallery` | media | A - CORE CANDIDATE | — | 1/1 |
| Dark Mode | `bdt-dark-mode` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Device Slider | `bdt-device-slider` | carousel | B - ENGINE DERIVATIVE | — | 1/0 |
| Document Viewer | `bdt-document-viewer` | content-and-layout | A - CORE CANDIDATE | — | 0/0 |
| Download Monitor | `bdt-download-monitor` | content-and-layout | C - INTEGRATION | Download Monitor | 1/0 |
| Dropbar | `bdt-dropbar` | content-and-layout | A - CORE CANDIDATE | — | 0/0 |
| Dual Button | `bdt-dual-button` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Dynamic Carousel | `bdt-dynamic-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 |
| Dynamic Grid | `bdt-dynamic-grid` | query | A - CORE CANDIDATE | — | 1/0 |
| EDD Cart | `bdt-edd-cart` | commerce | C - INTEGRATION | Easy Digital Downloads | 1/0 |
| EDD Category Carousel | `bdt-edd-category-carousel` | carousel | C - INTEGRATION | Easy Digital Downloads | 1/1 |
| EDD Category Grid | `bdt-edd-category-grid` | commerce | C - INTEGRATION | Easy Digital Downloads | 1/0 |
| EDD Checkout | `bdt-edd-checkout` | forms | C - INTEGRATION | Easy Digital Downloads | 1/0 |
| EDD History | `bdt-easy-digital-download-history` | content-and-layout | C - INTEGRATION | Easy Digital Downloads | 0/0 |
| EDD Login | `bdt-edd-login` | forms | C - INTEGRATION | Easy Digital Downloads | 1/0 |
| EDD Mini Cart | `bdt-edd-mini-cart` | commerce | C - INTEGRATION | Easy Digital Downloads | 1/0 |
| EDD Product | `bdt-edd-product` | commerce | C - INTEGRATION | Easy Digital Downloads | 4/2 |
| EDD Product Carousel | `bdt-edd-product-carousel` | carousel | C - INTEGRATION | Easy Digital Downloads | 1/1 |
| EDD Product Review Carousel | `bdt-edd-product-review-carousel` | carousel | C - INTEGRATION | Easy Digital Downloads | 1/1 |
| EDD Product Reviews | `bdt-edd-product-reviews` | commerce | C - INTEGRATION | Easy Digital Downloads | 1/0 |
| EDD Profile Editor | `bdt-easy-digital-profile-editor` | content-and-layout | C - INTEGRATION | Easy Digital Downloads | 0/0 |
| EDD Purchase History | `bdt-easy-digital-purchase-history` | content-and-layout | C - INTEGRATION | Easy Digital Downloads | 0/0 |
| EDD Register | `bdt-edd-register` | forms | C - INTEGRATION | Easy Digital Downloads | 1/0 |
| EDD Tabs | `bdt-edd-tabs` | navigation | C - INTEGRATION | Easy Digital Downloads | 1/1 |
| Events Calendar Carousel | `bdt-event-carousel` | carousel | C - INTEGRATION | The Events Calendar | 1/1 |
| Events Calendar Grid | `bdt-event-grid` | query | C - INTEGRATION | The Events Calendar | 1/0 |
| Events Calendar List | `bdt-event-list` | content-and-layout | C - INTEGRATION | The Events Calendar | 1/0 |
| Everest Forms | `bdt-everest-forms` | forms | A - CORE CANDIDATE | — | 1/0 |
| Facebook Feed | `bdt-facebook-feed` | content-and-layout | A - CORE CANDIDATE | — | 2/1 |
| Facebook Feed Carousel | `bdt-facebook-feed-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 |
| Fancy Card | `bdt-fancy-card` | content-and-layout | A - CORE CANDIDATE | — | 1/0 |
| Fancy Icons | `bdt-fancy-icons` | content-and-layout | A - CORE CANDIDATE | — | 1/0 |
| Fancy List | `bdt-fancy-list` | content-and-layout | A - CORE CANDIDATE | — | 1/0 |
| Fancy Slider | `bdt-fancy-slider` | carousel | B - ENGINE DERIVATIVE | — | 1/1 |
| Fancy Tabs | `bdt-fancy-tabs` | navigation | A - CORE CANDIDATE | — | 1/1 |
| FAQ | `bdt-faq` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Featured Box | `bdt-featured-box` | content-and-layout | A - CORE CANDIDATE | — | 1/0 |
| Flip Box | `bdt-flip-box` | content-and-layout | E - REVIEW FOR DUPLICATION | — | 1/1 |
| Floating Knowledgebase | `bdt-floating-knowledgebase` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Fluent Forms | `bdt-fluent-forms` | forms | A - CORE CANDIDATE | — | 1/0 |
| FooEvents Calendar | `fooevents-calendar` | content-and-layout | C - INTEGRATION | FooEvents | 2/1 |
| FooEvents Calendar Carousel | `bdt-fooevents-calendar-carousel` | carousel | C - INTEGRATION | FooEvents | 1/1 |
| FooEvents Calendar Events | `bdt-fooevents-calendar-events` | content-and-layout | C - INTEGRATION | FooEvents | 1/0 |
| Formidable Forms | `bdt-formidable-forms` | forms | A - CORE CANDIDATE | — | 1/0 |
| Forminator Forms | `bdt-forminator-forms` | forms | A - CORE CANDIDATE | — | 1/0 |
| Give Donation History | `bdt-give-donation-history` | content-and-layout | C - INTEGRATION | GiveWP | 1/0 |
| Give Donor Wall | `bdt-give-donor-wall` | content-and-layout | C - INTEGRATION | GiveWP | 1/0 |
| Give Form | `bdt-give-form` | forms | C - INTEGRATION | GiveWP | 2/0 |
| Give Form Grid | `bdt-give-form-grid` | forms | C - INTEGRATION | GiveWP | 1/0 |
| Give Goal | `bdt-give-goal` | content-and-layout | C - INTEGRATION | GiveWP | 1/0 |
| Give Login | `bdt-give-login` | forms | C - INTEGRATION | GiveWP | 1/0 |
| Give Profile Editor | `bdt-give-profile-editor` | content-and-layout | C - INTEGRATION | GiveWP | 1/0 |
| Give Receipt | `bdt-give-receipt` | content-and-layout | C - INTEGRATION | GiveWP | 1/0 |
| Give Register | `bdt-give-register` | forms | C - INTEGRATION | GiveWP | 1/0 |
| Give Totals | `bdt-give-totals` | content-and-layout | C - INTEGRATION | GiveWP | 0/0 |
| Google Reviews | `bdt-google-reviews` | content-and-layout | A - CORE CANDIDATE | — | 1/0 |
| Gravity Forms | `bdt-gravity-form` | forms | A - CORE CANDIDATE | — | 1/0 |
| Help Desk | `bdt-helpdesk` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Honeycombs | `bdt-honeycombs` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Horizontal Scroller | `bdt-horizontal-scroller` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Hover Box | `bdt-hover-box` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Hover Video | `bdt-hover-video` | media | A - CORE CANDIDATE | — | 1/1 |
| Icon Mobile Menu | `bdt-icon-mobile-menu` | navigation | A - CORE CANDIDATE | — | 1/1 |
| Icon Nav | `bdt-iconnav` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Iframe | `bdt-iframe` | media | A - CORE CANDIDATE | — | 1/1 |
| Image Accordion | `bdt-image-accordion` | navigation | A - CORE CANDIDATE | — | 1/1 |
| Image Compare | `bdt-image-compare` | media | A - CORE CANDIDATE | — | 1/1 |
| Image Expand | `bdt-image-expand` | media | A - CORE CANDIDATE | — | 1/1 |
| Image Magnifier | `bdt-image-magnifier` | media | A - CORE CANDIDATE | — | 0/1 |
| Image Stack | `bdt-image-stack` | media | A - CORE CANDIDATE | — | 1/1 |
| Instagram | `bdt-instagram` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Instagram Feed | `bdt-instagram-feed` | content-and-layout | A - CORE CANDIDATE | — | 0/0 |
| Interactive Card | `bdt-interactive-card` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Interactive Tabs | `bdt-interactive-tabs` | navigation | A - CORE CANDIDATE | — | 1/1 |
| Layer Slider | `bdt-layer-slider` | carousel | C - INTEGRATION | LayerSlider | 0/0 |
| LearnPress Carousel | `bdt-learnpress-carousel` | carousel | C - INTEGRATION | LearnPress | 1/1 |
| LearnPress Grid | `bdt-learnpress-grid` | query | C - INTEGRATION | LearnPress | 1/0 |
| Lightbox | `lightbox` | media | A - CORE CANDIDATE | — | 1/0 |
| Logo Carousel | `bdt-logo-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 |
| Logo Grid | `bdt-logo-grid` | query | A - CORE CANDIDATE | — | 1/1 |
| Lottie Icon Box | `bdt-lottie-icon-box` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Lottie Image | `bdt-lottie-image` | media | A - CORE CANDIDATE | — | 0/1 |
| Mailchimp | `bdt-mailchimp` | content-and-layout | C - INTEGRATION | Mailchimp | 1/1 |
| Mailchimp for WP | `bdt-mailchimp-for-wp` | content-and-layout | C - INTEGRATION | Mailchimp | 0/0 |
| Marker | `bdt-marker` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Marquee | `bdt-marquee` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Mega Menu | `bdt-mega-menu` | navigation | E - REVIEW FOR DUPLICATION | — | 1/1 |
| Member | `bdt-member` | content-and-layout | A - CORE CANDIDATE | — | 1/0 |
| Modal | `bdt-modal` | interaction | A - CORE CANDIDATE | — | 0/1 |
| Navbar | `bdt-navbar` | content-and-layout | A - CORE CANDIDATE | — | 1/0 |
| News Ticker | `bdt-news-ticker` | query | A - CORE CANDIDATE | — | 1/1 |
| Ninja Forms | `bdt-ninja-form` | forms | A - CORE CANDIDATE | — | 0/0 |
| Notification | `bdt-notification` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Offcanvas | `bdt-offcanvas` | interaction | A - CORE CANDIDATE | — | 1/1 |
|  Open Street Map | `bdt-open-street-map` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Panel Slider | `bdt-panel-slider` | carousel | B - ENGINE DERIVATIVE | — | 1/1 |
| Portfolio Carousel | `bdt-portfolio-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 |
| Portfolio Gallery | `bdt-portfolio-gallery` | media | A - CORE CANDIDATE | — | 1/1 |
| Portfolio List | `bdt-portfolio-list` | content-and-layout | A - CORE CANDIDATE | — | 1/0 |
| Post Block | `bdt-post-block` | query | A - CORE CANDIDATE | — | 2/0 |
| Post Block Modern | `bdt-post-block-modern` | query | A - CORE CANDIDATE | — | 1/0 |
| Post Card | `bdt-post-card` | query | A - CORE CANDIDATE | — | 1/0 |
| Post Comments | `bdt-post-comments` | query | E - REVIEW FOR DUPLICATION | — | 0/0 |
| Post Content | `bdt-post-content` | query | E - REVIEW FOR DUPLICATION | — | 0/0 |
| Post Featured Image | `bdt-post-featured-image` | query | A - CORE CANDIDATE | — | 0/0 |
| Post Gallery | `bdt-post-gallery` | query | A - CORE CANDIDATE | — | 1/1 |
| Post Grid | `bdt-post-grid` | query | A - CORE CANDIDATE | — | 2/2 |
| Post Grid Tab | `bdt-post-grid-tab` | query | A - CORE CANDIDATE | — | 1/1 |
| Post Info | `bdt-post-info` | query | E - REVIEW FOR DUPLICATION | — | 1/0 |
| Post List | `bdt-post-list` | query | A - CORE CANDIDATE | — | 1/1 |
| Post Slider | `bdt-post-slider` | carousel | B - ENGINE DERIVATIVE | — | 1/0 |
| Post Title | `bdt-post-title` | query | E - REVIEW FOR DUPLICATION | — | 0/0 |
| Price List | `bdt-price-list` | content-and-layout | E - REVIEW FOR DUPLICATION | — | 1/0 |
| Price Table | `bdt-price-table` | query | E - REVIEW FOR DUPLICATION | — | 1/1 |
| Product Carousel | `bdt-product-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 |
| Product Grid | `bdt-product-grid` | commerce | A - CORE CANDIDATE | — | 1/0 |
| Profile Card | `bdt-profile-card` | content-and-layout | A - CORE CANDIDATE | — | 1/0 |
| Progress Pie | `bdt-progress-pie` | data-visualization | A - CORE CANDIDATE | — | 1/1 |
| Protected Content | `bdt-protected-content` | content-and-layout | A - CORE CANDIDATE | — | 0/0 |
| QR Code | `bdt-qrcode` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| QuForm | `bdt-quform` | forms | A - CORE CANDIDATE | — | 0/0 |
| Reading Progress | `bdt-reading-progress` | data-visualization | A - CORE CANDIDATE | — | 1/1 |
| Reading Timer | `bdt-reading-timer` | content-and-layout | A - CORE CANDIDATE | — | 0/1 |
| Remote Arrows | `bdt-remote-arrows` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Remote Fraction | `bdt-remote-fraction` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Remote Pagination | `bdt-remote-pagination` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Remote Thumbs | `bdt-remote-thumbs` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Review Card | `bdt-review-card` | content-and-layout | A - CORE CANDIDATE | — | 3/1 |
| Review Card Carousel | `bdt-review-card-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 |
| Review Card Grid | `bdt-review-card-grid` | query | A - CORE CANDIDATE | — | 1/0 |
| Revolution Slider | `bdt-revolution-slider` | carousel | C - INTEGRATION | Slider Revolution | 0/0 |
| Scroll Button | `bdt-scroll-button` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Scroll Image | `bdt-scroll-image` | media | A - CORE CANDIDATE | — | 1/0 |
| Scroll Navigation | `bdt-scrollnav` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Search | `bdt-search` | content-and-layout | E - REVIEW FOR DUPLICATION | — | 1/1 |
| Single Post | `bdt-single-post` | content-and-layout | A - CORE CANDIDATE | — | 1/0 |
| Slider | `bdt-slider` | carousel | B - ENGINE DERIVATIVE | — | 1/1 |
| Slideshow | `bdt-slideshow` | carousel | B - ENGINE DERIVATIVE | — | 1/1 |
| Slinky Vertical Menu | `bdt-slinky-vertical-menu` | navigation | A - CORE CANDIDATE | — | 1/1 |
| Social Proof | `bdt-social-proof` | content-and-layout | A - CORE CANDIDATE | — | 1/0 |
| Social Share | `bdt-social-share` | content-and-layout | A - CORE CANDIDATE | — | 1/0 |
| Source Code | `bdt-source-code` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Stacker | `bdt-stacker` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Static Carousel | `bdt-static-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 |
| Static Grid Tab | `bdt-static-grid-tab` | query | A - CORE CANDIDATE | — | 1/1 |
| Step Flow | `bdt-step-flow` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Sub Menu | `bdt-sub-menu` | navigation | A - CORE CANDIDATE | — | 1/0 |
| SVG Blob | `bdt-svg-blob` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| SVG Image | `bdt-svg-image` | media | A - CORE CANDIDATE | — | 0/1 |
| SVG Maps | `bdt-svg-maps` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Switcher | `bdt-switcher` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Table | `bdt-table` | query | A - CORE CANDIDATE | — | 2/2 |
| Table of Content | `bdt-table-of-content` | query | A - CORE CANDIDATE | — | 1/1 |
| TablePress | `bdt-tablepress` | query | A - CORE CANDIDATE | — | 0/0 |
| Tabs | `bdt-tabs` | navigation | A - CORE CANDIDATE | — | 1/1 |
| Tags Cloud | `bdt-tags-cloud` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Testimonial Carousel | `bdt-testimonial-carousel` | carousel | E - REVIEW FOR DUPLICATION | — | 1/1 |
| Testimonial Grid | `bdt-testimonial-grid` | query | A - CORE CANDIDATE | — | 1/0 |
| Testimonial Slider | `bdt-testimonial-slider` | carousel | B - ENGINE DERIVATIVE | — | 1/1 |
| The Newsletter | `bdt-the-newsletter` | content-and-layout | A - CORE CANDIDATE | — | 1/0 |
| 360&#176; Product Viewer | `bdt-threesixty-product-viewer` | commerce | A - CORE CANDIDATE | — | 1/1 |
| Thumb Gallery | `bdt-thumb-gallery` | media | A - CORE CANDIDATE | — | 1/0 |
| Time Zone | `bdt-time-zone` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Timeline | `bdt-timeline` | query | A - CORE CANDIDATE | — | 1/1 |
| Read More Toggle | `bdt-toggle` | interaction | A - CORE CANDIDATE | — | 1/1 |
| Total Count | `bdt-total-count` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Trailer Box | `bdt-trailer-box` | content-and-layout | A - CORE CANDIDATE | — | 1/0 |
| Tutor LMS Course Carousel | `bdt-tutor-lms-course-carousel` | carousel | C - INTEGRATION | Tutor LMS | 0/1 |
| Tutor LMS Course Grid | `bdt-tutor-lms-course-grid` | query | C - INTEGRATION | Tutor LMS | 0/1 |
| Twitter Carousel | `bdt-twitter-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 |
| Twitter Grid | `bdt-twitter-grid` | query | A - CORE CANDIDATE | — | 1/0 |
| Twitter Slider | `bdt-twitter-slider` | carousel | B - ENGINE DERIVATIVE | — | 1/1 |
| User Login | `bdt-user-login` | forms | A - CORE CANDIDATE | — | 1/1 |
| User Register | `bdt-user-register` | forms | A - CORE CANDIDATE | — | 1/1 |
| Vertical Menu | `bdt-vertical-menu` | navigation | A - CORE CANDIDATE | — | 1/1 |
| Video Gallery | `bdt-video-gallery` | media | A - CORE CANDIDATE | — | 1/1 |
| Video Player | `bdt-video-player` | media | A - CORE CANDIDATE | — | 1/0 |
| WC - Add To Cart | `bdt-wc-add-to-cart` | commerce | E - REVIEW FOR DUPLICATION | WooCommerce | 1/0 |
| WC - Carousel | `bdt-wc-carousel` | carousel | C - INTEGRATION | WooCommerce | 1/0 |
| WC - Categories | `bdt-wc-categories` | content-and-layout | E - REVIEW FOR DUPLICATION | WooCommerce | 1/0 |
| WC - Elements | `bdt-wc-elements` | content-and-layout | E - REVIEW FOR DUPLICATION | WooCommerce | 1/0 |
| WC - Mini Cart | `bdt-wc-mini-cart` | commerce | C - INTEGRATION | WooCommerce | 1/0 |
| WC - Products | `bdt-wc-products` | commerce | E - REVIEW FOR DUPLICATION | WooCommerce | 1/1 |
| WC - Slider | `bdt-wc-slider` | carousel | C - INTEGRATION | WooCommerce | 1/0 |
| weForms | `bdt-we-form` | forms | C - INTEGRATION | weForms | 0/0 |
| Weather | `bdt-weather` | content-and-layout | A - CORE CANDIDATE | — | 1/1 |
| Webhook Form | `bdt-webhook-form` | forms | A - CORE CANDIDATE | — | 1/1 |
| WP Forms | `bdt-wp-forms` | forms | A - CORE CANDIDATE | — | 0/0 |
| wpDataTable | `bdt-wpdatatable` | query | A - CORE CANDIDATE | — | 0/0 |

El JSON incluye clase original, rutas, controles, skins, handles declarados, señales de seguridad, complejidad y estado individual. Las dependencias transitivas y equivalencias visuales requieren comprobación funcional antes de marcar un widget como completo.
