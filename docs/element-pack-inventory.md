# Inventario estático de Element Pack

Fuente local: `reference/bdthemes-element-pack`. Se identificaron **263 IDs Elementor únicos** en 265 archivos `modules/*/widgets/*.php`.
La carpeta es material de referencia, no una dependencia de ejecución ni un asset publicable. Este inventario se generó sin ejecutar PHP ajeno.

Las clasificaciones, complejidades y motores son **hipótesis de triaje**, no una aprobación para migrar. `UIkit` aparece como dependencia indirecta del loader original; cada uso real se debe validar antes de reconstruir. La ausencia de un marcador estático no prueba ausencia de AJAX, REST, dependencias o riesgos.

## Versiones

Base: 9.9.1, inventariada cuando la referencia era la carpeta `reference/bdthemes-element-pack`, que ya no existe. Comparada con Element Pack Pro 10.0.1, leída de su ZIP sin extraerlo ni ejecutar nada (`scripts/inventory-element-pack.py --update-docs`). De los 263 IDs, 175 siguen en 10.0.1. 88 IDs faltan en 10.0.1 y se marcan `moved_to_addon` porque el changelog de la versión más reciente dice que los widgets obsoletos pasaron al plugin Element Pack Addon con los mismos nombres (declara 85; la diferencia sólo se resuelve con el ZIP del Addon). Los documentos con esos widgets siguen necesitando su adaptador, así que el inventario y `migration-map.json` los conservan. IDs nuevos en 10.0.1: —.

### Movidos al Addon (88)

`bdt-age-gate`, `bdt-air-pollution`, `bdt-bbpress-forum-form`, `bdt-bbpress-forum-index`, `bdt-bbpress-reply-form`, `bdt-bbpress-single-forum`, `bdt-bbpress-single-reply`, `bdt-bbpress-single-tag`, `bdt-bbpress-single-topic`, `bdt-bbpress-single-view`, `bdt-bbpress-stats`, `bdt-bbpress-topic-form`, `bdt-bbpress-topic-index`, `bdt-bbpress-topic-tags`, `bdt-buddypress-friends`, `bdt-buddypress-group`, `bdt-buddypress-member`, `bdt-call-out`, `bdt-carousel`, `bdt-charitable-campaigns`, `bdt-charitable-donation-form`, `bdt-charitable-donations`, `bdt-charitable-donors`, `bdt-charitable-login`, `bdt-charitable-profile`, `bdt-charitable-registration`, `bdt-charitable-stat`, `bdt-crypto-currency-card`, `bdt-crypto-currency-carousel`, `bdt-crypto-currency-chart`, `bdt-crypto-currency-chart-carousel`, `bdt-crypto-currency-grid`, `bdt-crypto-currency-list`, `bdt-crypto-currency-table`, `bdt-crypto-currency-ticker`, `bdt-dark-mode`, `bdt-download-monitor`, `bdt-dropbar`, `bdt-easy-digital-download-history`, `bdt-easy-digital-profile-editor`, `bdt-easy-digital-purchase-history`, `bdt-edd-cart`, `bdt-edd-category-carousel`, `bdt-edd-category-grid`, `bdt-edd-checkout`, `bdt-edd-login`, `bdt-edd-mini-cart`, `bdt-edd-product`, `bdt-edd-product-carousel`, `bdt-edd-product-review-carousel`, `bdt-edd-product-reviews`, `bdt-edd-register`, `bdt-edd-tabs`, `bdt-everest-forms`, `bdt-fancy-card`, `bdt-give-donation-history`, `bdt-give-donor-wall`, `bdt-give-form`, `bdt-give-form-grid`, `bdt-give-goal`, `bdt-give-login`, `bdt-give-profile-editor`, `bdt-give-receipt`, `bdt-give-register`, `bdt-give-totals`, `bdt-honeycombs`, `bdt-image-accordion`, `bdt-layer-slider`, `bdt-member`, `bdt-news-ticker`, `bdt-post-block`, `bdt-post-block-modern`, `bdt-post-card`, `bdt-post-comments`, `bdt-post-content`, `bdt-post-featured-image`, `bdt-post-gallery`, `bdt-post-grid`, `bdt-post-grid-tab`, `bdt-post-info`, `bdt-post-list`, `bdt-post-slider`, `bdt-post-title`, `bdt-single-post`, `bdt-slinky-vertical-menu`, `bdt-tags-cloud`, `bdt-thumb-gallery`, `bdt-timeline`

Del bloque prioritario: `bdt-call-out`, `bdt-fancy-card`, `bdt-post-grid`, `bdt-post-list`, `bdt-single-post`, `bdt-slinky-vertical-menu`, `bdt-tags-cloud`.

## Extensiones en 10.0.1

Módulos sin widgets y extensiones de `includes/` de 10.0.1. «Claves de control» cuenta las claves literales de sus controles (la lista está en el JSON); un «+» indica claves que se arman en tiempo de ejecución. Una extensión no tratada en `migration-map.json` hoy se descarta al convertir. La base 9.9.1 sólo registró las 10 extensiones prioritarias: que las demás figuren sólo en 10.0.1 no significa que sean nuevas.

| Extensión | Tipo | Presente en | Tratada en `migration-map.json` | Claves de control | Elementos |
| --- | --- | --- | --- | ---: | --- |
| Adblock Detector (`adblock-detector`) | extension | 10.0.1 | **no** | 1 | — |
| Animated Gradient Background (`animated-gradient-background`) | extension | 10.0.1 | **no** | 7 | container, section |
| Backdrop Filter (`backdrop-filter`) | extension | 9.9.1, 10.0.1 | sí | 14 | column, common, container, section |
| Background Expand (`background-expand`) | extension | 10.0.1 | **no** | 7+ | container, section |
| Background Image Parallax (`background-image-parallax`) | extension | 10.0.1 | **no** | 5+ | image, widget |
| Background Overlay (`background-overlay`) | extension | 10.0.1 | **no** | 13 | common |
| Background Parallax (`background-parallax`) | extension | 10.0.1 | **no** | 8 | container, section |
| Confetti Effects (`confetti-effects`) | extension | 10.0.1 | **no** | 21 | common, container, section, widget |
| Content Protector (`content-protector`) | extension | 10.0.1 | **no** | 1 | — |
| Cursor Effects (`cursor-effects`) | extension | 10.0.1 | **no** | 29 | common, container, section, widget |
| Custom Js (`custom-js`) | extension | 10.0.1 | **no** | 2 | * |
| Duplicator (`duplicator`) | extension | 9.9.1, 10.0.1 | sí | 0 | — |
| Elementor (`elementor`) | kit-tab | 10.0.1 | **no** | 31 | — |
| Equal Height (`equal-height`) | extension | 10.0.1 | **no** | 4 | container, section |
| Floating Effects (`floating-effects`) | extension | 9.9.1, 10.0.1 | sí | 33+ | column, common, section, widget |
| Global Badge (`global-badge`) | extension | 10.0.1 | **no** | 61 | variable |
| Grid Line (`grid-line`) | extension | 10.0.1 | **no** | 12+ | — |
| Hash Link (`hash-link`) | extension | 10.0.1 | **no** | 6 | — |
| Image Hover Effects (`image-hover-effects`) | extension | 10.0.1 | **no** | 5 | image, widget |
| Image Masking (`image-masking`) | extension | 10.0.1 | sí | 8 | image-box, image-carousel |
| Image Parallax (`image-parallax`) | extension | 10.0.1 | **no** | 14 | *, container, section |
| Notation (`notation`) | extension | 9.9.1, 10.0.1 | sí | 12 | common, widget |
| Parallax Effects (`parallax-effects`) | extension | 10.0.1 | **no** | 40 | column, common, section, widget |
| Particles (`particles`) | extension | 10.0.1 | **no** | 3 | container, section |
| Realistic Image Shadow (`realistic-image-shadow`) | extension | 9.9.1, 10.0.1 | sí | 8 | common, widget |
| Reveal Effects (`reveal-effects`) | extension | 10.0.1 | **no** | 5 | common, widget |
| Ripple Effects (`ripple-effects`) | extension | 10.0.1 | **no** | 9 | common, widget |
| Scroll Box (`scroll-box`) | extension | 10.0.1 | **no** | 11 | container, section, text-editor |
| Scroll Fill Effect (`scroll-fill-effect`) | extension | 10.0.1 | **no** | 20 | bdt-advanced-heading, heading, text-editor, widget |
| Section Sticky (`section-sticky`) | extension | 10.0.1 | **no** | 13 | common, container, section, widget |
| Shape Builder (`shape-builder`) | extension | 9.9.1, 10.0.1 | sí | 44 | common, container, section, widget |
| Shape Divider (`shape-divider`) | extension | 10.0.1 | sí | 0 | shape-divider |
| Sound Effects (`sound-effects`) | extension | 10.0.1 | **no** | 8 | common |
| SVG Support (`svg-support`) | extension | 9.9.1, 10.0.1 | sí | 0 | — |
| Text Gradient Background (`text-gradient-background`) | extension | 9.9.1, 10.0.1 | sí | 3 | common, widget |
| Threed Text (`threed-text`) | extension | 10.0.1 | **no** | 9+ | bdt-advanced-heading, heading, widget |
| Tile Scroll (`tile-scroll`) | extension | 10.0.1 | **no** | 15 | container, section |
| Tooltip (`tooltip`) | extension | 10.0.1 | **no** | 20 | common, widget |
| Transform Effects (`transform-effects`) | extension | 10.0.1 | **no** | 36 | column, common, section |
| Visibility Controls (`visibility-controls`) | extension | 9.9.1, 10.0.1 | sí | 9+ | common, container, section |
| Wrapper Link (`wrapper-link`) | extension | 9.9.1, 10.0.1 | sí | 0+ | column, common, container, section |

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

| Widget | ID legacy | Motor | Clase | Dependencia externa | CSS/JS | En 10.0.1 |
| --- | --- | --- | --- | --- | --- | --- |
| Accordion | `bdt-accordion` | navigation | A - CORE CANDIDATE | — | 1/1 | sí |
| ACF Accordion | `bdt-acf-accordion` | navigation | C - INTEGRATION | Advanced Custom Fields | 0/0 | sí |
| ACF Gallery | `bdt-acf-gallery` | media | C - INTEGRATION | Advanced Custom Fields | 0/0 | sí |
| ACF List | `bdt-acf-list` | content-and-layout | C - INTEGRATION | Advanced Custom Fields | 0/0 | sí |
| ACF Slider | `bdt-acf-slider` | carousel | C - INTEGRATION | Advanced Custom Fields | 0/0 | sí |
| ACF Tabs | `bdt-acf-tabs` | navigation | C - INTEGRATION | Advanced Custom Fields | 0/0 | sí |
| Advanced Button | `bdt-advanced-button` | content-and-layout | A - CORE CANDIDATE | — | 1/0 | sí |
| Advanced Calculator | `bdt-advanced-calculator` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Advanced Counter | `bdt-advanced-counter` | data-visualization | A - CORE CANDIDATE | — | 1/1 | sí |
| Advanced Divider | `bdt-advanced-divider` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Advanced Google Map | `bdt-advanced-gmap` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Advanced Heading | `bdt-advanced-heading` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Advanced Icon Box | `bdt-advanced-icon-box` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Advanced Image Gallery | `bdt-advanced-image-gallery` | media | A - CORE CANDIDATE | — | 1/1 | sí |
| Advanced Progress Bar | `bdt-advanced-progress-bar` | data-visualization | A - CORE CANDIDATE | — | 1/1 | sí |
| Age Gate | `bdt-age-gate` | content-and-layout | A - CORE CANDIDATE | — | 0/1 | Addon |
| Air Pollution | `bdt-air-pollution` | content-and-layout | A - CORE CANDIDATE | — | 0/0 | Addon |
| Animated Card | `bdt-animated-card` | content-and-layout | A - CORE CANDIDATE | — | 1/0 | sí |
| Animated Heading | `bdt-animated-heading` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Animated Link | `bdt-animated-link` | content-and-layout | A - CORE CANDIDATE | — | 1/0 | sí |
| Audio Player | `bdt-audio-player` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| BarCode | `bdt-barcode` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| bbPress Forum Form | `bdt-bbpress-forum-form` | forms | C - INTEGRATION | bbPress | 0/0 | Addon |
| bbPress Forum Index | `bdt-bbpress-forum-index` | content-and-layout | C - INTEGRATION | bbPress | 0/0 | Addon |
| bbPress Reply Form | `bdt-bbpress-reply-form` | forms | C - INTEGRATION | bbPress | 0/0 | Addon |
| bbPress Single Forum | `bdt-bbpress-single-forum` | content-and-layout | C - INTEGRATION | bbPress | 0/0 | Addon |
| bbPress Single Reply | `bdt-bbpress-single-reply` | content-and-layout | C - INTEGRATION | bbPress | 0/0 | Addon |
| bbPress Single Tag | `bdt-bbpress-single-tag` | content-and-layout | C - INTEGRATION | bbPress | 0/0 | Addon |
| bbPress Single Topic | `bdt-bbpress-single-topic` | content-and-layout | C - INTEGRATION | bbPress | 0/0 | Addon |
| bbPress Single View | `bdt-bbpress-single-view` | content-and-layout | C - INTEGRATION | bbPress | 0/0 | Addon |
| bbPress Stats | `bdt-bbpress-stats` | content-and-layout | C - INTEGRATION | bbPress | 0/0 | Addon |
| bbPress Topic Form | `bdt-bbpress-topic-form` | forms | C - INTEGRATION | bbPress | 0/0 | Addon |
| bbPress Topic Index | `bdt-bbpress-topic-index` | content-and-layout | C - INTEGRATION | bbPress | 0/0 | Addon |
| bbPress Topic Tags | `bdt-bbpress-topic-tags` | content-and-layout | C - INTEGRATION | bbPress | 0/0 | Addon |
| Brand Carousel | `bdt-brand-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 | sí |
| Brand Grid | `bdt-brand-grid` | query | A - CORE CANDIDATE | — | 1/0 | sí |
| Breadcrumbs | `bdt-breadcrumbs` | navigation | E - REVIEW FOR DUPLICATION | — | 1/0 | sí |
| BuddyPress Friends | `bdt-buddypress-friends` | content-and-layout | C - INTEGRATION | BuddyPress | 0/0 | Addon |
| BuddyPress Group | `bdt-buddypress-group` | content-and-layout | C - INTEGRATION | BuddyPress | 0/0 | Addon |
| BuddyPress Member | `bdt-buddypress-member` | content-and-layout | C - INTEGRATION | BuddyPress | 0/0 | Addon |
| Business Hours | `bdt-business-hours` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Calendly | `bdt-calendly` | content-and-layout | A - CORE CANDIDATE | — | 0/0 | sí |
| Call Out | `bdt-call-out` | content-and-layout | A - CORE CANDIDATE | — | 1/0 | Addon |
| Carousel | `bdt-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 | Addon |
| Changelog | `bdt-changelog` | content-and-layout | A - CORE CANDIDATE | — | 1/0 | sí |
| Charitable Campaigns | `bdt-charitable-campaigns` | query | C - INTEGRATION | Charitable | 1/0 | Addon |
| Charitable Donation Form | `bdt-charitable-donation-form` | forms | C - INTEGRATION | Charitable | 1/0 | Addon |
| Charitable Donations | `bdt-charitable-donations` | query | C - INTEGRATION | Charitable | 1/0 | Addon |
| Charitable Donors | `bdt-charitable-donors` | query | C - INTEGRATION | Charitable | 1/0 | Addon |
| Charitable Login | `bdt-charitable-login` | forms | C - INTEGRATION | Charitable | 1/0 | Addon |
| Charitable Profile | `bdt-charitable-profile` | query | C - INTEGRATION | Charitable | 1/0 | Addon |
| Charitable Registration | `bdt-charitable-registration` | query | C - INTEGRATION | Charitable | 1/0 | Addon |
| Charitable Stat | `bdt-charitable-stat` | query | C - INTEGRATION | Charitable | 1/0 | Addon |
| Chart | `bdt-chart` | data-visualization | A - CORE CANDIDATE | — | 0/1 | sí |
| Circle Info | `bdt-circle-info` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Circle Menu | `bdt-circle-menu` | navigation | A - CORE CANDIDATE | — | 1/1 | sí |
| Comment | `bdt-comment` | content-and-layout | A - CORE CANDIDATE | — | 0/1 | sí |
| Comparison List | `bdt-comparison-list` | content-and-layout | A - CORE CANDIDATE | — | 1/0 | sí |
| Simple Contact Form | `bdt-contact-form` | forms | A - CORE CANDIDATE | — | 1/1 | sí |
| Contact Form 7 | `bdt-contact-form-7` | forms | A - CORE CANDIDATE | — | 0/0 | sí |
| Content Switcher | `bdt-content-switcher` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Cookie Consent | `bdt-cookie-consent` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Countdown | `bdt-countdown` | content-and-layout | E - REVIEW FOR DUPLICATION | — | 1/1 | sí |
| Coupon Code | `bdt-coupon-code` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Creative Button | `bdt-creative-button` | content-and-layout | A - CORE CANDIDATE | — | 1/0 | sí |
| Crypto Currency Card | `bdt-crypto-currency-card` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | Addon |
| Crypto Currency Carousel | `bdt-crypto-currency-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 | Addon |
| Crypto Currency Chart | `bdt-crypto-currency-chart` | data-visualization | A - CORE CANDIDATE | — | 2/2 | Addon |
| Crypto Currency Chart Carousel | `bdt-crypto-currency-chart-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 | Addon |
| Crypto Currency Grid | `bdt-crypto-currency-grid` | query | A - CORE CANDIDATE | — | 1/1 | Addon |
| Crypto Currency List | `bdt-crypto-currency-list` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | Addon |
| Crypto Currency Table | `bdt-crypto-currency-table` | query | A - CORE CANDIDATE | — | 1/1 | Addon |
| Crypto Currency Ticker | `bdt-crypto-currency-ticker` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | Addon |
| Custom Carousel | `bdt-custom-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 | sí |
| Custom Gallery | `bdt-custom-gallery` | media | A - CORE CANDIDATE | — | 1/1 | sí |
| Dark Mode | `bdt-dark-mode` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | Addon |
| Device Slider | `bdt-device-slider` | carousel | B - ENGINE DERIVATIVE | — | 1/0 | sí |
| Document Viewer | `bdt-document-viewer` | content-and-layout | A - CORE CANDIDATE | — | 0/0 | sí |
| Download Monitor | `bdt-download-monitor` | content-and-layout | C - INTEGRATION | Download Monitor | 1/0 | Addon |
| Dropbar | `bdt-dropbar` | content-and-layout | A - CORE CANDIDATE | — | 0/0 | Addon |
| Dual Button | `bdt-dual-button` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Dynamic Carousel | `bdt-dynamic-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 | sí |
| Dynamic Grid | `bdt-dynamic-grid` | query | A - CORE CANDIDATE | — | 1/0 | sí |
| EDD Cart | `bdt-edd-cart` | commerce | C - INTEGRATION | Easy Digital Downloads | 1/0 | Addon |
| EDD Category Carousel | `bdt-edd-category-carousel` | carousel | C - INTEGRATION | Easy Digital Downloads | 1/1 | Addon |
| EDD Category Grid | `bdt-edd-category-grid` | commerce | C - INTEGRATION | Easy Digital Downloads | 1/0 | Addon |
| EDD Checkout | `bdt-edd-checkout` | forms | C - INTEGRATION | Easy Digital Downloads | 1/0 | Addon |
| EDD History | `bdt-easy-digital-download-history` | content-and-layout | C - INTEGRATION | Easy Digital Downloads | 0/0 | Addon |
| EDD Login | `bdt-edd-login` | forms | C - INTEGRATION | Easy Digital Downloads | 1/0 | Addon |
| EDD Mini Cart | `bdt-edd-mini-cart` | commerce | C - INTEGRATION | Easy Digital Downloads | 1/0 | Addon |
| EDD Product | `bdt-edd-product` | commerce | C - INTEGRATION | Easy Digital Downloads | 4/2 | Addon |
| EDD Product Carousel | `bdt-edd-product-carousel` | carousel | C - INTEGRATION | Easy Digital Downloads | 1/1 | Addon |
| EDD Product Review Carousel | `bdt-edd-product-review-carousel` | carousel | C - INTEGRATION | Easy Digital Downloads | 1/1 | Addon |
| EDD Product Reviews | `bdt-edd-product-reviews` | commerce | C - INTEGRATION | Easy Digital Downloads | 1/0 | Addon |
| EDD Profile Editor | `bdt-easy-digital-profile-editor` | content-and-layout | C - INTEGRATION | Easy Digital Downloads | 0/0 | Addon |
| EDD Purchase History | `bdt-easy-digital-purchase-history` | content-and-layout | C - INTEGRATION | Easy Digital Downloads | 0/0 | Addon |
| EDD Register | `bdt-edd-register` | forms | C - INTEGRATION | Easy Digital Downloads | 1/0 | Addon |
| EDD Tabs | `bdt-edd-tabs` | navigation | C - INTEGRATION | Easy Digital Downloads | 1/1 | Addon |
| Events Calendar Carousel | `bdt-event-carousel` | carousel | C - INTEGRATION | The Events Calendar | 1/1 | sí |
| Events Calendar Grid | `bdt-event-grid` | query | C - INTEGRATION | The Events Calendar | 1/0 | sí |
| Events Calendar List | `bdt-event-list` | content-and-layout | C - INTEGRATION | The Events Calendar | 1/0 | sí |
| Everest Forms | `bdt-everest-forms` | forms | A - CORE CANDIDATE | — | 1/0 | Addon |
| Facebook Feed | `bdt-facebook-feed` | content-and-layout | A - CORE CANDIDATE | — | 2/1 | sí |
| Facebook Feed Carousel | `bdt-facebook-feed-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 | sí |
| Fancy Card | `bdt-fancy-card` | content-and-layout | A - CORE CANDIDATE | — | 1/0 | Addon |
| Fancy Icons | `bdt-fancy-icons` | content-and-layout | A - CORE CANDIDATE | — | 1/0 | sí |
| Fancy List | `bdt-fancy-list` | content-and-layout | A - CORE CANDIDATE | — | 1/0 | sí |
| Fancy Slider | `bdt-fancy-slider` | carousel | B - ENGINE DERIVATIVE | — | 1/1 | sí |
| Fancy Tabs | `bdt-fancy-tabs` | navigation | A - CORE CANDIDATE | — | 1/1 | sí |
| FAQ | `bdt-faq` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Featured Box | `bdt-featured-box` | content-and-layout | A - CORE CANDIDATE | — | 1/0 | sí |
| Flip Box | `bdt-flip-box` | content-and-layout | E - REVIEW FOR DUPLICATION | — | 1/1 | sí |
| Floating Knowledgebase | `bdt-floating-knowledgebase` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Fluent Forms | `bdt-fluent-forms` | forms | A - CORE CANDIDATE | — | 1/0 | sí |
| FooEvents Calendar | `fooevents-calendar` | content-and-layout | C - INTEGRATION | FooEvents | 2/1 | sí |
| FooEvents Calendar Carousel | `bdt-fooevents-calendar-carousel` | carousel | C - INTEGRATION | FooEvents | 1/1 | sí |
| FooEvents Calendar Events | `bdt-fooevents-calendar-events` | content-and-layout | C - INTEGRATION | FooEvents | 1/0 | sí |
| Formidable Forms | `bdt-formidable-forms` | forms | A - CORE CANDIDATE | — | 1/0 | sí |
| Forminator Forms | `bdt-forminator-forms` | forms | A - CORE CANDIDATE | — | 1/0 | sí |
| Give Donation History | `bdt-give-donation-history` | content-and-layout | C - INTEGRATION | GiveWP | 1/0 | Addon |
| Give Donor Wall | `bdt-give-donor-wall` | content-and-layout | C - INTEGRATION | GiveWP | 1/0 | Addon |
| Give Form | `bdt-give-form` | forms | C - INTEGRATION | GiveWP | 2/0 | Addon |
| Give Form Grid | `bdt-give-form-grid` | forms | C - INTEGRATION | GiveWP | 1/0 | Addon |
| Give Goal | `bdt-give-goal` | content-and-layout | C - INTEGRATION | GiveWP | 1/0 | Addon |
| Give Login | `bdt-give-login` | forms | C - INTEGRATION | GiveWP | 1/0 | Addon |
| Give Profile Editor | `bdt-give-profile-editor` | content-and-layout | C - INTEGRATION | GiveWP | 1/0 | Addon |
| Give Receipt | `bdt-give-receipt` | content-and-layout | C - INTEGRATION | GiveWP | 1/0 | Addon |
| Give Register | `bdt-give-register` | forms | C - INTEGRATION | GiveWP | 1/0 | Addon |
| Give Totals | `bdt-give-totals` | content-and-layout | C - INTEGRATION | GiveWP | 0/0 | Addon |
| Google Reviews | `bdt-google-reviews` | content-and-layout | A - CORE CANDIDATE | — | 1/0 | sí |
| Gravity Forms | `bdt-gravity-form` | forms | A - CORE CANDIDATE | — | 1/0 | sí |
| Help Desk | `bdt-helpdesk` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Honeycombs | `bdt-honeycombs` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | Addon |
| Horizontal Scroller | `bdt-horizontal-scroller` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Hover Box | `bdt-hover-box` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Hover Video | `bdt-hover-video` | media | A - CORE CANDIDATE | — | 1/1 | sí |
| Icon Mobile Menu | `bdt-icon-mobile-menu` | navigation | A - CORE CANDIDATE | — | 1/1 | sí |
| Icon Nav | `bdt-iconnav` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Iframe | `bdt-iframe` | media | A - CORE CANDIDATE | — | 1/1 | sí |
| Image Accordion | `bdt-image-accordion` | navigation | A - CORE CANDIDATE | — | 1/1 | Addon |
| Image Compare | `bdt-image-compare` | media | A - CORE CANDIDATE | — | 1/1 | sí |
| Image Expand | `bdt-image-expand` | media | A - CORE CANDIDATE | — | 1/1 | sí |
| Image Magnifier | `bdt-image-magnifier` | media | A - CORE CANDIDATE | — | 0/1 | sí |
| Image Stack | `bdt-image-stack` | media | A - CORE CANDIDATE | — | 1/1 | sí |
| Instagram | `bdt-instagram` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Instagram Feed | `bdt-instagram-feed` | content-and-layout | A - CORE CANDIDATE | — | 0/0 | sí |
| Interactive Card | `bdt-interactive-card` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Interactive Tabs | `bdt-interactive-tabs` | navigation | A - CORE CANDIDATE | — | 1/1 | sí |
| Layer Slider | `bdt-layer-slider` | carousel | C - INTEGRATION | LayerSlider | 0/0 | Addon |
| LearnPress Carousel | `bdt-learnpress-carousel` | carousel | C - INTEGRATION | LearnPress | 1/1 | sí |
| LearnPress Grid | `bdt-learnpress-grid` | query | C - INTEGRATION | LearnPress | 1/0 | sí |
| Lightbox | `lightbox` | media | A - CORE CANDIDATE | — | 1/0 | sí |
| Logo Carousel | `bdt-logo-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 | sí |
| Logo Grid | `bdt-logo-grid` | query | A - CORE CANDIDATE | — | 1/1 | sí |
| Lottie Icon Box | `bdt-lottie-icon-box` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Lottie Image | `bdt-lottie-image` | media | A - CORE CANDIDATE | — | 0/1 | sí |
| Mailchimp | `bdt-mailchimp` | content-and-layout | C - INTEGRATION | Mailchimp | 1/1 | sí |
| Mailchimp for WP | `bdt-mailchimp-for-wp` | content-and-layout | C - INTEGRATION | Mailchimp | 0/0 | sí |
| Marker | `bdt-marker` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Marquee | `bdt-marquee` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Mega Menu | `bdt-mega-menu` | navigation | E - REVIEW FOR DUPLICATION | — | 1/1 | sí |
| Member | `bdt-member` | content-and-layout | A - CORE CANDIDATE | — | 1/0 | Addon |
| Modal | `bdt-modal` | interaction | A - CORE CANDIDATE | — | 0/1 | sí |
| Navbar | `bdt-navbar` | content-and-layout | A - CORE CANDIDATE | — | 1/0 | sí |
| News Ticker | `bdt-news-ticker` | query | A - CORE CANDIDATE | — | 1/1 | Addon |
| Ninja Forms | `bdt-ninja-form` | forms | A - CORE CANDIDATE | — | 0/0 | sí |
| Notification | `bdt-notification` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Offcanvas | `bdt-offcanvas` | interaction | A - CORE CANDIDATE | — | 1/1 | sí |
|  Open Street Map | `bdt-open-street-map` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Panel Slider | `bdt-panel-slider` | carousel | B - ENGINE DERIVATIVE | — | 1/1 | sí |
| Portfolio Carousel | `bdt-portfolio-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 | sí |
| Portfolio Gallery | `bdt-portfolio-gallery` | media | A - CORE CANDIDATE | — | 1/1 | sí |
| Portfolio List | `bdt-portfolio-list` | content-and-layout | A - CORE CANDIDATE | — | 1/0 | sí |
| Post Block | `bdt-post-block` | query | A - CORE CANDIDATE | — | 2/0 | Addon |
| Post Block Modern | `bdt-post-block-modern` | query | A - CORE CANDIDATE | — | 1/0 | Addon |
| Post Card | `bdt-post-card` | query | A - CORE CANDIDATE | — | 1/0 | Addon |
| Post Comments | `bdt-post-comments` | query | E - REVIEW FOR DUPLICATION | — | 0/0 | Addon |
| Post Content | `bdt-post-content` | query | E - REVIEW FOR DUPLICATION | — | 0/0 | Addon |
| Post Featured Image | `bdt-post-featured-image` | query | A - CORE CANDIDATE | — | 0/0 | Addon |
| Post Gallery | `bdt-post-gallery` | query | A - CORE CANDIDATE | — | 1/1 | Addon |
| Post Grid | `bdt-post-grid` | query | A - CORE CANDIDATE | — | 2/2 | Addon |
| Post Grid Tab | `bdt-post-grid-tab` | query | A - CORE CANDIDATE | — | 1/1 | Addon |
| Post Info | `bdt-post-info` | query | E - REVIEW FOR DUPLICATION | — | 1/0 | Addon |
| Post List | `bdt-post-list` | query | A - CORE CANDIDATE | — | 1/1 | Addon |
| Post Slider | `bdt-post-slider` | carousel | B - ENGINE DERIVATIVE | — | 1/0 | Addon |
| Post Title | `bdt-post-title` | query | E - REVIEW FOR DUPLICATION | — | 0/0 | Addon |
| Price List | `bdt-price-list` | content-and-layout | E - REVIEW FOR DUPLICATION | — | 1/0 | sí |
| Price Table | `bdt-price-table` | query | E - REVIEW FOR DUPLICATION | — | 1/1 | sí |
| Product Carousel | `bdt-product-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 | sí |
| Product Grid | `bdt-product-grid` | commerce | A - CORE CANDIDATE | — | 1/0 | sí |
| Profile Card | `bdt-profile-card` | content-and-layout | A - CORE CANDIDATE | — | 1/0 | sí |
| Progress Pie | `bdt-progress-pie` | data-visualization | A - CORE CANDIDATE | — | 1/1 | sí |
| Protected Content | `bdt-protected-content` | content-and-layout | A - CORE CANDIDATE | — | 0/0 | sí |
| QR Code | `bdt-qrcode` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| QuForm | `bdt-quform` | forms | A - CORE CANDIDATE | — | 0/0 | sí |
| Reading Progress | `bdt-reading-progress` | data-visualization | A - CORE CANDIDATE | — | 1/1 | sí |
| Reading Timer | `bdt-reading-timer` | content-and-layout | A - CORE CANDIDATE | — | 0/1 | sí |
| Remote Arrows | `bdt-remote-arrows` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Remote Fraction | `bdt-remote-fraction` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Remote Pagination | `bdt-remote-pagination` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Remote Thumbs | `bdt-remote-thumbs` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Review Card | `bdt-review-card` | content-and-layout | A - CORE CANDIDATE | — | 3/1 | sí |
| Review Card Carousel | `bdt-review-card-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 | sí |
| Review Card Grid | `bdt-review-card-grid` | query | A - CORE CANDIDATE | — | 1/0 | sí |
| Revolution Slider | `bdt-revolution-slider` | carousel | C - INTEGRATION | Slider Revolution | 0/0 | sí |
| Scroll Button | `bdt-scroll-button` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Scroll Image | `bdt-scroll-image` | media | A - CORE CANDIDATE | — | 1/0 | sí |
| Scroll Navigation | `bdt-scrollnav` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Search | `bdt-search` | content-and-layout | E - REVIEW FOR DUPLICATION | — | 1/1 | sí |
| Single Post | `bdt-single-post` | content-and-layout | A - CORE CANDIDATE | — | 1/0 | Addon |
| Slider | `bdt-slider` | carousel | B - ENGINE DERIVATIVE | — | 1/1 | sí |
| Slideshow | `bdt-slideshow` | carousel | B - ENGINE DERIVATIVE | — | 1/1 | sí |
| Slinky Vertical Menu | `bdt-slinky-vertical-menu` | navigation | A - CORE CANDIDATE | — | 1/1 | Addon |
| Social Proof | `bdt-social-proof` | content-and-layout | A - CORE CANDIDATE | — | 1/0 | sí |
| Social Share | `bdt-social-share` | content-and-layout | A - CORE CANDIDATE | — | 1/0 | sí |
| Source Code | `bdt-source-code` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Stacker | `bdt-stacker` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Static Carousel | `bdt-static-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 | sí |
| Static Grid Tab | `bdt-static-grid-tab` | query | A - CORE CANDIDATE | — | 1/1 | sí |
| Step Flow | `bdt-step-flow` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Sub Menu | `bdt-sub-menu` | navigation | A - CORE CANDIDATE | — | 1/0 | sí |
| SVG Blob | `bdt-svg-blob` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| SVG Image | `bdt-svg-image` | media | A - CORE CANDIDATE | — | 0/1 | sí |
| SVG Maps | `bdt-svg-maps` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Switcher | `bdt-switcher` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Table | `bdt-table` | query | A - CORE CANDIDATE | — | 2/2 | sí |
| Table of Content | `bdt-table-of-content` | query | A - CORE CANDIDATE | — | 1/1 | sí |
| TablePress | `bdt-tablepress` | query | A - CORE CANDIDATE | — | 0/0 | sí |
| Tabs | `bdt-tabs` | navigation | A - CORE CANDIDATE | — | 1/1 | sí |
| Tags Cloud | `bdt-tags-cloud` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | Addon |
| Testimonial Carousel | `bdt-testimonial-carousel` | carousel | E - REVIEW FOR DUPLICATION | — | 1/1 | sí |
| Testimonial Grid | `bdt-testimonial-grid` | query | A - CORE CANDIDATE | — | 1/0 | sí |
| Testimonial Slider | `bdt-testimonial-slider` | carousel | B - ENGINE DERIVATIVE | — | 1/1 | sí |
| The Newsletter | `bdt-the-newsletter` | content-and-layout | A - CORE CANDIDATE | — | 1/0 | sí |
| 360&#176; Product Viewer | `bdt-threesixty-product-viewer` | commerce | A - CORE CANDIDATE | — | 1/1 | sí |
| Thumb Gallery | `bdt-thumb-gallery` | media | A - CORE CANDIDATE | — | 1/0 | Addon |
| Time Zone | `bdt-time-zone` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Timeline | `bdt-timeline` | query | A - CORE CANDIDATE | — | 1/1 | Addon |
| Read More Toggle | `bdt-toggle` | interaction | A - CORE CANDIDATE | — | 1/1 | sí |
| Total Count | `bdt-total-count` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Trailer Box | `bdt-trailer-box` | content-and-layout | A - CORE CANDIDATE | — | 1/0 | sí |
| Tutor LMS Course Carousel | `bdt-tutor-lms-course-carousel` | carousel | C - INTEGRATION | Tutor LMS | 0/1 | sí |
| Tutor LMS Course Grid | `bdt-tutor-lms-course-grid` | query | C - INTEGRATION | Tutor LMS | 0/1 | sí |
| Twitter Carousel | `bdt-twitter-carousel` | carousel | B - ENGINE DERIVATIVE | — | 1/1 | sí |
| Twitter Grid | `bdt-twitter-grid` | query | A - CORE CANDIDATE | — | 1/0 | sí |
| Twitter Slider | `bdt-twitter-slider` | carousel | B - ENGINE DERIVATIVE | — | 1/1 | sí |
| User Login | `bdt-user-login` | forms | A - CORE CANDIDATE | — | 1/1 | sí |
| User Register | `bdt-user-register` | forms | A - CORE CANDIDATE | — | 1/1 | sí |
| Vertical Menu | `bdt-vertical-menu` | navigation | A - CORE CANDIDATE | — | 1/1 | sí |
| Video Gallery | `bdt-video-gallery` | media | A - CORE CANDIDATE | — | 1/1 | sí |
| Video Player | `bdt-video-player` | media | A - CORE CANDIDATE | — | 1/0 | sí |
| WC - Add To Cart | `bdt-wc-add-to-cart` | commerce | E - REVIEW FOR DUPLICATION | WooCommerce | 1/0 | sí |
| WC - Carousel | `bdt-wc-carousel` | carousel | C - INTEGRATION | WooCommerce | 1/0 | sí |
| WC - Categories | `bdt-wc-categories` | content-and-layout | E - REVIEW FOR DUPLICATION | WooCommerce | 1/0 | sí |
| WC - Elements | `bdt-wc-elements` | content-and-layout | E - REVIEW FOR DUPLICATION | WooCommerce | 1/0 | sí |
| WC - Mini Cart | `bdt-wc-mini-cart` | commerce | C - INTEGRATION | WooCommerce | 1/0 | sí |
| WC - Products | `bdt-wc-products` | commerce | E - REVIEW FOR DUPLICATION | WooCommerce | 1/1 | sí |
| WC - Slider | `bdt-wc-slider` | carousel | C - INTEGRATION | WooCommerce | 1/0 | sí |
| weForms | `bdt-we-form` | forms | C - INTEGRATION | weForms | 0/0 | sí |
| Weather | `bdt-weather` | content-and-layout | A - CORE CANDIDATE | — | 1/1 | sí |
| Webhook Form | `bdt-webhook-form` | forms | A - CORE CANDIDATE | — | 1/1 | sí |
| WP Forms | `bdt-wp-forms` | forms | A - CORE CANDIDATE | — | 0/0 | sí |
| wpDataTable | `bdt-wpdatatable` | query | A - CORE CANDIDATE | — | 0/0 | sí |

El JSON incluye clase original, rutas, controles, skins, handles declarados, señales de seguridad, complejidad, estado individual y, por versión, presencia (`present_in`) y traslado al Addon (`moved_to_addon`). Las dependencias transitivas y equivalencias visuales requieren comprobación funcional antes de marcar un widget como completo.
