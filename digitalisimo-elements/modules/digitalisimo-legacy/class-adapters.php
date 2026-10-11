<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-translator.php';
require_once __DIR__ . '/class-acf.php';

/**
 * Registra un ID `bdt-*` sobre el widget propio equivalente. Al construir cada elemento traduce sus
 * ajustes, de modo que el render, el CSS que genera Elementor y el editor trabajan ya con los
 * nombres propios. No aparece en el panel: el contenido nuevo usa el widget `digitalisimo-*`.
 */
trait Legacy_Adapter {
	public function __construct( $data = array(), $args = null ) {
		if ( is_array( $data ) && ! empty( $data['id'] ) ) {
			$data['settings'] = Translator::translate( static::LEGACY_ID, is_array( $data['settings'] ?? null ) ? $data['settings'] : array() );
		}
		if ( method_exists( (string) get_parent_class( $this ), '__construct' ) ) {
			parent::__construct( $data, $args );
		}
	}

	public function get_name(): string {
		return static::LEGACY_ID;
	}

	public function get_title(): string {
		return parent::get_title() . ' (Element Pack)';
	}

	public function show_in_panel(): bool {
		return false;
	}

	/**
	 * En la página el elemento se presenta como el widget de destino: su hoja usa
	 * `.elementor-widget-{nombre}` y sus scripts se enganchan por `data-widget_type`.
	 */
	protected function add_render_attributes() {
		parent::add_render_attributes();
		$target = Translator::target( static::LEGACY_ID );
		if ( '' === $target || $target === $this->get_name() ) {
			return;
		}
		$skin = (string) ( $this->get_settings( '_skin' ) ?: 'default' );
		$this->add_render_attribute( '_wrapper', 'class', 'elementor-widget-' . $target );
		$this->add_render_attribute( '_wrapper', 'data-widget_type', $target . '.' . $skin, true );
	}

	/**
	 * Añade los controles de estilo de Element Pack que el widget propio no tiene, con sus mismos
	 * nombres y plantillas CSS: Elementor vuelve a generar los colores, tipografías y espacios que
	 * el usuario ya había elegido, ahora sobre el marcado propio.
	 */
	protected function register_controls(): void {
		parent::register_controls();
		if ( method_exists( $this, 'register_adapter_controls' ) ) {
			$this->register_adapter_controls();
		}
		$styles = Translator::styles( static::LEGACY_ID );
		if ( ! $styles ) {
			return;
		}
		$existing = array_keys( (array) $this->get_controls() );
		$this->start_controls_section( 'digitalisimo_legacy_style', array(
			'label' => 'Estilo de Element Pack',
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		foreach ( $styles as $control ) {
			$name = (string) ( $control['name'] ?? '' );
			if ( '' === $name || ( ! isset( $control['group'] ) && in_array( $name, $existing, true ) ) ) {
				continue;
			}
			$label = ucfirst( str_replace( '_', ' ', $name ) );
			if ( isset( $control['group'] ) ) {
				// Un grupo sólo choca con controles que comparten su prefijo, no con uno de igual nombre.
				foreach ( $existing as $key ) {
					if ( 0 === strpos( $key, $name . '_' ) ) {
						continue 2;
					}
				}
				$args = array( 'name' => $name, 'label' => $label, 'selector' => $control['selector'] );
				foreach ( array( 'types', 'exclude' ) as $key ) {
					if ( isset( $control[ $key ] ) ) {
						$args[ $key ] = $control[ $key ];
					}
				}
				$this->add_group_control( $control['group'], $args );
				continue;
			}
			$args = array( 'label' => $label, 'type' => $control['type'], 'selectors' => $control['selectors'] );
			foreach ( array( 'default', 'desktop_default', 'tablet_default', 'mobile_default', 'selectors_dictionary', 'condition', 'size_units', 'options' ) as $key ) {
				if ( isset( $control[ $key ] ) ) {
					$args[ $key ] = $control[ $key ];
				}
			}
			if ( ! empty( $control['responsive'] ) ) {
				$this->add_responsive_control( $name, $args );
			} else {
				$this->add_control( $name, $args );
			}
		}
		$this->end_controls_section();
	}
}

final class Adapters {
	/** ID heredado => clase adaptadora; la clase base debe estar cargada por el registro. */
	const CLASSES = array(
		'bdt-accordion' => Bdt_Accordion::class,
		'bdt-advanced-button' => Bdt_AdvancedButton::class,
		'bdt-advanced-divider' => Bdt_AdvancedDivider::class,
		'bdt-advanced-heading' => Bdt_AdvancedHeading::class,
		'bdt-advanced-icon-box' => Bdt_AdvancedIconBox::class,
		'bdt-animated-heading' => Bdt_AnimatedHeading::class,
		'bdt-brand-grid' => Bdt_BrandGrid::class,
		'bdt-brand-carousel' => Bdt_BrandCarousel::class,
		'bdt-breadcrumbs' => Bdt_Breadcrumbs::class,
		'bdt-dual-button' => Bdt_DualButton::class,
		'bdt-call-out' => Bdt_CallOut::class,
		'bdt-comparison-list' => Bdt_ComparisonList::class,
		'bdt-content-switcher' => Bdt_ContentSwitcher::class,
		'bdt-custom-gallery' => Bdt_CustomGallery::class,
		'bdt-creative-button' => Bdt_CreativeButton::class,
		'bdt-device-slider' => Bdt_DeviceSlider::class,
		'bdt-fancy-card' => Bdt_FancyCard::class,
		'bdt-fancy-list' => Bdt_FancyList::class,
		'bdt-fancy-icons' => Bdt_FancyIcons::class,
		'bdt-fancy-slider' => Bdt_FancySlider::class,
		'bdt-fancy-tabs' => Bdt_FancyTabs::class,
		'bdt-featured-box' => Bdt_FeaturedBox::class,
		'bdt-google-reviews' => Bdt_GoogleReviews::class,
		'bdt-icon-mobile-menu' => Bdt_IconMobileMenu::class,
		'bdt-iconnav' => Bdt_Iconnav::class,
		'bdt-logo-grid' => Bdt_LogoGrid::class,
		'bdt-notification' => Bdt_Notification::class,
		'bdt-product-grid' => Bdt_ProductGrid::class,
		'bdt-qrcode' => Bdt_Qrcode::class,
		'bdt-table' => Bdt_Table::class,
		'bdt-tags-cloud' => Bdt_TagsCloud::class,
		'bdt-total-count' => Bdt_TotalCount::class,
		'bdt-user-register' => Bdt_UserRegister::class,
		'bdt-video-player' => Bdt_VideoPlayer::class,
		'bdt-switcher' => Bdt_Switcher::class,
		'bdt-tabs' => Bdt_Tabs::class,
		'bdt-lottie-image' => Bdt_LottieImage::class,
		'bdt-price-list' => Bdt_PriceList::class,
		'bdt-price-table' => Bdt_PriceTable::class,
		'bdt-search' => Bdt_Search::class,
		'bdt-social-share' => Bdt_SocialShare::class,
		'bdt-table-of-content' => Bdt_TableOfContent::class,
		'bdt-user-login' => Bdt_UserLogin::class,
		'bdt-profile-card' => Bdt_ProfileCard::class,
		'bdt-navbar' => Bdt_Navbar::class,
		'bdt-vertical-menu' => Bdt_VerticalMenu::class,
		'bdt-slinky-vertical-menu' => Bdt_SlinkyVerticalMenu::class,
		'bdt-sub-menu' => Bdt_SubMenu::class,
		'bdt-offcanvas' => Bdt_Offcanvas::class,
		'bdt-review-card' => Bdt_ReviewCard::class,
		'bdt-review-card-carousel' => Bdt_ReviewCardCarousel::class,
		'bdt-review-card-grid' => Bdt_ReviewCardGrid::class,
		'bdt-scrollnav' => Bdt_Scrollnav::class,
		'bdt-testimonial-carousel' => Bdt_TestimonialCarousel::class,
		'bdt-testimonial-grid' => Bdt_TestimonialGrid::class,
		'bdt-testimonial-slider' => Bdt_TestimonialSlider::class,
		'bdt-custom-carousel' => Bdt_CustomCarousel::class,
		'bdt-video-gallery' => Bdt_VideoGallery::class,
		'bdt-static-carousel' => Bdt_StaticCarousel::class,
		'bdt-product-carousel' => Bdt_ProductCarousel::class,
		'bdt-panel-slider' => Bdt_PanelSlider::class,
		'bdt-slideshow' => Bdt_Slideshow::class,
		'bdt-thumb-gallery' => Bdt_ThumbGallery::class,
		'bdt-advanced-image-gallery' => Bdt_AdvancedImageGallery::class,
		'bdt-image-stack' => Bdt_ImageStack::class,
		'bdt-chart' => Bdt_Chart::class,
		'bdt-open-street-map' => Bdt_OpenStreetMap::class,
		'bdt-marquee' => Bdt_Marquee::class,
		'bdt-news-ticker' => Bdt_NewsTicker::class,
		'bdt-timeline' => Bdt_Timeline::class,
		'bdt-audio-player' => Bdt_AudioPlayer::class,
		'bdt-image-compare' => Bdt_ImageCompare::class,
		'bdt-business-hours' => Bdt_BusinessHours::class,
		'bdt-advanced-progress-bar' => Bdt_AdvancedProgressBar::class,
		'bdt-progress-pie' => Bdt_ProgressPie::class,
		'bdt-reading-progress' => Bdt_ReadingProgress::class,
		'bdt-reading-timer' => Bdt_ReadingTimer::class,
		'bdt-hover-box' => Bdt_HoverBox::class,
		'bdt-image-expand' => Bdt_ImageExpand::class,
		'bdt-interactive-tabs' => Bdt_InteractiveTabs::class,
		'bdt-static-grid-tab' => Bdt_StaticGridTab::class,
		'bdt-edd-tabs' => Bdt_EddTabs::class,
		'bdt-image-accordion' => Bdt_ImageAccordion::class,
		'bdt-toggle' => Bdt_Toggle::class,
		'bdt-animated-card' => Bdt_AnimatedCard::class,
		'bdt-interactive-card' => Bdt_InteractiveCard::class,
		'bdt-member' => Bdt_Member::class,
		'bdt-trailer-box' => Bdt_TrailerBox::class,
		'bdt-image-magnifier' => Bdt_ImageMagnifier::class,
		'bdt-scroll-image' => Bdt_ScrollImage::class,
		'bdt-svg-image' => Bdt_SvgImage::class,
		'bdt-source-code' => Bdt_SourceCode::class,
		'bdt-lottie-icon-box' => Bdt_LottieIconBox::class,
		'bdt-step-flow' => Bdt_StepFlow::class,
		'bdt-hover-video' => Bdt_HoverVideo::class,
		'bdt-modal' => Bdt_Modal::class,
		'bdt-dropbar' => Bdt_Dropbar::class,
		'lightbox' => Legacy_Lightbox::class,
		'bdt-carousel' => Bdt_Carousel::class,
		'bdt-portfolio-carousel' => Bdt_PortfolioCarousel::class,
		'bdt-portfolio-gallery' => Bdt_PortfolioGallery::class,
		'bdt-portfolio-list' => Bdt_PortfolioList::class,
		'bdt-post-block' => Bdt_PostBlock::class,
		'bdt-post-block-modern' => Bdt_PostBlockModern::class,
		'bdt-post-card' => Bdt_PostCard::class,
		'bdt-post-gallery' => Bdt_PostGallery::class,
		'bdt-post-grid-tab' => Bdt_PostGridTab::class,
		'bdt-post-slider' => Bdt_PostSlider::class,
		'bdt-download-monitor' => Bdt_DownloadMonitor::class,
		'bdt-edd-cart' => Bdt_EddCart::class,
		'bdt-edd-checkout' => Bdt_EddCheckout::class,
		'bdt-wc-elements' => Bdt_WcElements::class,
		'bdt-calendly' => Bdt_Calendly::class,
		'bdt-iframe' => Bdt_Iframe::class,
		'bdt-countdown' => Bdt_Countdown::class,
		'bdt-flip-box' => Bdt_FlipBox::class,
		'bdt-advanced-counter' => Bdt_AdvancedCounter::class,
		'bdt-post-title' => Bdt_PostTitle::class,
		'bdt-post-featured-image' => Bdt_PostFeaturedImage::class,
		'bdt-post-content' => Bdt_PostContent::class,
		'bdt-post-info' => Bdt_PostInfo::class,
		'bdt-post-comments' => Bdt_PostComments::class,
		'bdt-document-viewer' => Bdt_DocumentViewer::class,
		'bdt-scroll-button' => Bdt_ScrollButton::class,
		'bdt-logo-carousel' => Bdt_LogoCarousel::class,
		'bdt-charitable-campaigns' => Bdt_CharitableCampaigns::class,
		'bdt-charitable-donation-form' => Bdt_CharitableDonationForm::class,
		'bdt-charitable-donations' => Bdt_CharitableDonations::class,
		'bdt-charitable-donors' => Bdt_CharitableDonors::class,
		'bdt-charitable-login' => Bdt_CharitableLogin::class,
		'bdt-charitable-profile' => Bdt_CharitableProfile::class,
		'bdt-charitable-registration' => Bdt_CharitableRegistration::class,
		'bdt-charitable-stat' => Bdt_CharitableStat::class,
		'bdt-contact-form-7' => Bdt_ContactForm7::class,
		'bdt-easy-digital-download-history' => Bdt_EasyDigitalDownloadHistory::class,
		'bdt-easy-digital-profile-editor' => Bdt_EasyDigitalProfileEditor::class,
		'bdt-easy-digital-purchase-history' => Bdt_EasyDigitalPurchaseHistory::class,
		'bdt-edd-login' => Bdt_EddLogin::class,
		'bdt-edd-register' => Bdt_EddRegister::class,
		'bdt-everest-forms' => Bdt_EverestForms::class,
		'bdt-fluent-forms' => Bdt_FluentForms::class,
		'bdt-formidable-forms' => Bdt_FormidableForms::class,
		'bdt-forminator-forms' => Bdt_ForminatorForms::class,
		'bdt-give-donation-history' => Bdt_GiveDonationHistory::class,
		'bdt-give-donor-wall' => Bdt_GiveDonorWall::class,
		'bdt-give-form' => Bdt_GiveForm::class,
		'bdt-give-form-grid' => Bdt_GiveFormGrid::class,
		'bdt-give-goal' => Bdt_GiveGoal::class,
		'bdt-give-login' => Bdt_GiveLogin::class,
		'bdt-give-profile-editor' => Bdt_GiveProfileEditor::class,
		'bdt-give-receipt' => Bdt_GiveReceipt::class,
		'bdt-give-register' => Bdt_GiveRegister::class,
		'bdt-give-totals' => Bdt_GiveTotals::class,
		'bdt-gravity-form' => Bdt_GravityForm::class,
		'bdt-instagram-feed' => Bdt_InstagramFeed::class,
		'bdt-layer-slider' => Bdt_LayerSlider::class,
		'bdt-mailchimp-for-wp' => Bdt_MailchimpForWp::class,
		'bdt-ninja-form' => Bdt_NinjaForm::class,
		'bdt-quform' => Bdt_Quform::class,
		'bdt-revolution-slider' => Bdt_RevolutionSlider::class,
		'bdt-tablepress' => Bdt_Tablepress::class,
		'bdt-the-newsletter' => Bdt_TheNewsletter::class,
		'bdt-wc-categories' => Bdt_WcCategories::class,
		'bdt-we-form' => Bdt_WeForm::class,
		'bdt-wp-forms' => Bdt_WpForms::class,
		'bdt-wpdatatable' => Bdt_Wpdatatable::class,
		'fooevents-calendar' => Legacy_FooeventsCalendar::class,
		'bdt-bbpress-forum-form' => Bdt_BbpressForumForm::class,
		'bdt-bbpress-forum-index' => Bdt_BbpressForumIndex::class,
		'bdt-bbpress-reply-form' => Bdt_BbpressReplyForm::class,
		'bdt-bbpress-single-forum' => Bdt_BbpressSingleForum::class,
		'bdt-bbpress-single-reply' => Bdt_BbpressSingleReply::class,
		'bdt-bbpress-single-tag' => Bdt_BbpressSingleTag::class,
		'bdt-bbpress-single-topic' => Bdt_BbpressSingleTopic::class,
		'bdt-bbpress-single-view' => Bdt_BbpressSingleView::class,
		'bdt-bbpress-stats' => Bdt_BbpressStats::class,
		'bdt-bbpress-topic-form' => Bdt_BbpressTopicForm::class,
		'bdt-bbpress-topic-index' => Bdt_BbpressTopicIndex::class,
		'bdt-bbpress-topic-tags' => Bdt_BbpressTopicTags::class,
		'bdt-slider' => Bdt_Slider::class,
		'bdt-post-grid' => Bdt_PostGrid::class,
		'bdt-post-list' => Bdt_PostList::class,
		'bdt-single-post' => Bdt_SinglePost::class,
		'bdt-wc-products' => Bdt_WcProducts::class,
		'bdt-wc-carousel' => Bdt_WcCarousel::class,
		'bdt-wc-slider' => Bdt_WcSlider::class,
		'bdt-wc-add-to-cart' => Bdt_WcAddToCart::class,
		'bdt-wc-mini-cart' => Bdt_WcMiniCart::class,
		'bdt-dynamic-grid' => Bdt_DynamicGrid::class,
		'bdt-dynamic-carousel' => Bdt_DynamicCarousel::class,
		'bdt-advanced-gmap' => Bdt_AdvancedGmap::class,
		'bdt-acf-accordion' => Bdt_AcfAccordion::class,
		'bdt-acf-tabs' => Bdt_AcfTabs::class,
		'bdt-acf-list' => Bdt_AcfList::class,
		'bdt-acf-slider' => Bdt_AcfSlider::class,
		'bdt-acf-gallery' => Bdt_AcfGallery::class,
	);

	public static function element_pack_active() {
		return Translator::element_pack_active();
	}

	public static function register( $manager ) {
		if ( self::element_pack_active() ) {
			return;
		}
		foreach ( self::CLASSES as $id => $class ) {
			if ( ! Translator::map( $id ) || ! class_exists( $class ) ) {
				continue;
			}
			// Un widget de PRO Elements puede estar desactivado en este sitio: sin destino no hay adaptador.
			if ( method_exists( $manager, 'get_widget_types' ) && ! $manager->get_widget_types( Translator::target( $id ) ) ) {
				continue;
			}
			if ( method_exists( $manager, 'get_widget_types' ) && $manager->get_widget_types( $id ) ) {
				continue;
			}
			$manager->register( new $class() );
		}
	}
}

if ( class_exists( '\\Digitalisimo\\Elements\\Accordion_Widget' ) ) {
	final class Bdt_Accordion extends \Digitalisimo\Elements\Accordion_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-accordion'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Advanced_Button_Widget' ) ) {
	final class Bdt_AdvancedButton extends \Digitalisimo\Elements\Advanced_Button_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-advanced-button'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Advanced_Divider_Widget' ) ) {
	final class Bdt_AdvancedDivider extends \Digitalisimo\Elements\Advanced_Divider_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-advanced-divider'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Advanced_Heading_Widget' ) ) {
	final class Bdt_AdvancedHeading extends \Digitalisimo\Elements\Advanced_Heading_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-advanced-heading'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Advanced_Icon_Box_Widget' ) ) {
	final class Bdt_AdvancedIconBox extends \Digitalisimo\Elements\Advanced_Icon_Box_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-advanced-icon-box'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Animated_Heading_Widget' ) ) {
	final class Bdt_AnimatedHeading extends \Digitalisimo\Elements\Animated_Heading_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-animated-heading'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Brand_Grid_Widget' ) ) {
	final class Bdt_BrandGrid extends \Digitalisimo\Elements\Brand_Grid_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-brand-grid'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Brand_Carousel_Widget' ) ) {
	final class Bdt_BrandCarousel extends \Digitalisimo\Elements\Brand_Carousel_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-brand-carousel'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Breadcrumbs_Widget' ) ) {
	final class Bdt_Breadcrumbs extends \Digitalisimo\Elements\Breadcrumbs_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-breadcrumbs'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Dual_Button_Widget' ) ) {
	final class Bdt_DualButton extends \Digitalisimo\Elements\Dual_Button_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-dual-button'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Call_Out_Widget' ) ) {
	final class Bdt_CallOut extends \Digitalisimo\Elements\Call_Out_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-call-out'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Comparison_List_Widget' ) ) {
	final class Bdt_ComparisonList extends \Digitalisimo\Elements\Comparison_List_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-comparison-list'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Content_Switcher_Widget' ) ) {
	final class Bdt_ContentSwitcher extends \Digitalisimo\Elements\Content_Switcher_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-content-switcher'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Custom_Gallery_Widget' ) ) {
	final class Bdt_CustomGallery extends \Digitalisimo\Elements\Custom_Gallery_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-custom-gallery'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Creative_Button_Widget' ) ) {
	final class Bdt_CreativeButton extends \Digitalisimo\Elements\Creative_Button_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-creative-button'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Device_Slider_Widget' ) ) {
	final class Bdt_DeviceSlider extends \Digitalisimo\Elements\Device_Slider_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-device-slider'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Fancy_Card_Widget' ) ) {
	final class Bdt_FancyCard extends \Digitalisimo\Elements\Fancy_Card_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-fancy-card'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Fancy_List_Widget' ) ) {
	final class Bdt_FancyList extends \Digitalisimo\Elements\Fancy_List_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-fancy-list'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Fancy_Icons_Widget' ) ) {
	final class Bdt_FancyIcons extends \Digitalisimo\Elements\Fancy_Icons_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-fancy-icons'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Fancy_Slider_Widget' ) ) {
	final class Bdt_FancySlider extends \Digitalisimo\Elements\Fancy_Slider_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-fancy-slider'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Fancy_Tabs_Widget' ) ) {
	final class Bdt_FancyTabs extends \Digitalisimo\Elements\Fancy_Tabs_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-fancy-tabs'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Featured_Box_Widget' ) ) {
	final class Bdt_FeaturedBox extends \Digitalisimo\Elements\Featured_Box_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-featured-box'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Google_Reviews_Widget' ) ) {
	final class Bdt_GoogleReviews extends \Digitalisimo\Elements\Google_Reviews_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-google-reviews'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Icon_Mobile_Menu_Widget' ) ) {
	final class Bdt_IconMobileMenu extends \Digitalisimo\Elements\Icon_Mobile_Menu_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-icon-mobile-menu'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Icon_Nav_Widget' ) ) {
	final class Bdt_Iconnav extends \Digitalisimo\Elements\Icon_Nav_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-iconnav'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Logo_Grid_Widget' ) ) {
	final class Bdt_LogoGrid extends \Digitalisimo\Elements\Logo_Grid_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-logo-grid'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Notification_Widget' ) ) {
	final class Bdt_Notification extends \Digitalisimo\Elements\Notification_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-notification'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Product_Grid_Widget' ) ) {
	final class Bdt_ProductGrid extends \Digitalisimo\Elements\Product_Grid_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-product-grid'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\QR_Code_Widget' ) ) {
	final class Bdt_Qrcode extends \Digitalisimo\Elements\QR_Code_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-qrcode'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Table_Widget' ) ) {
	final class Bdt_Table extends \Digitalisimo\Elements\Table_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-table'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Tags_Cloud_Widget' ) ) {
	final class Bdt_TagsCloud extends \Digitalisimo\Elements\Tags_Cloud_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-tags-cloud'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Total_Count_Widget' ) ) {
	final class Bdt_TotalCount extends \Digitalisimo\Elements\Total_Count_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-total-count'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\User_Register_Widget' ) ) {
	final class Bdt_UserRegister extends \Digitalisimo\Elements\User_Register_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-user-register'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Video_Player_Widget' ) ) {
	final class Bdt_VideoPlayer extends \Digitalisimo\Elements\Video_Player_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-video-player'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Content_Switcher_Widget' ) ) {
	final class Bdt_Switcher extends \Digitalisimo\Elements\Content_Switcher_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-switcher'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Content_Switcher_Widget' ) ) {
	final class Bdt_Tabs extends \Digitalisimo\Elements\Content_Switcher_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-tabs'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\Lottie\\Widgets\\Lottie' ) ) {
	final class Bdt_LottieImage extends \ElementorPro\Modules\Lottie\Widgets\Lottie { use Legacy_Adapter; const LEGACY_ID = 'bdt-lottie-image'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\Pricing\\Widgets\\Price_List' ) ) {
	final class Bdt_PriceList extends \ElementorPro\Modules\Pricing\Widgets\Price_List { use Legacy_Adapter; const LEGACY_ID = 'bdt-price-list'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\Pricing\\Widgets\\Price_Table' ) ) {
	final class Bdt_PriceTable extends \ElementorPro\Modules\Pricing\Widgets\Price_Table { use Legacy_Adapter; const LEGACY_ID = 'bdt-price-table'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\ThemeElements\\Widgets\\Search_Form' ) ) {
	final class Bdt_Search extends \ElementorPro\Modules\ThemeElements\Widgets\Search_Form { use Legacy_Adapter; const LEGACY_ID = 'bdt-search'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\ShareButtons\\Widgets\\Share_Buttons' ) ) {
	final class Bdt_SocialShare extends \ElementorPro\Modules\ShareButtons\Widgets\Share_Buttons { use Legacy_Adapter; const LEGACY_ID = 'bdt-social-share'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\TableOfContents\\Widgets\\Table_Of_Contents' ) ) {
	final class Bdt_TableOfContent extends \ElementorPro\Modules\TableOfContents\Widgets\Table_Of_Contents { use Legacy_Adapter; const LEGACY_ID = 'bdt-table-of-content'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\Forms\\Widgets\\Login' ) ) {
	final class Bdt_UserLogin extends \ElementorPro\Modules\Forms\Widgets\Login { use Legacy_Adapter; const LEGACY_ID = 'bdt-user-login'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\ThemeElements\\Widgets\\Author_Box' ) ) {
	final class Bdt_ProfileCard extends \ElementorPro\Modules\ThemeElements\Widgets\Author_Box { use Legacy_Adapter; const LEGACY_ID = 'bdt-profile-card'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\NavMenu\\Widgets\\Nav_Menu' ) ) {
	final class Bdt_Navbar extends \ElementorPro\Modules\NavMenu\Widgets\Nav_Menu { use Legacy_Adapter; const LEGACY_ID = 'bdt-navbar'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Vertical_Menu_Widget' ) ) {
	final class Bdt_VerticalMenu extends \Digitalisimo\Elements\Vertical_Menu_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-vertical-menu'; }
	final class Bdt_SlinkyVerticalMenu extends \Digitalisimo\Elements\Vertical_Menu_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-slinky-vertical-menu'; }
	final class Bdt_SubMenu extends \Digitalisimo\Elements\Vertical_Menu_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-sub-menu'; }
	final class Bdt_Scrollnav extends \Digitalisimo\Elements\Vertical_Menu_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-scrollnav'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\Slides\\Widgets\\Slides' ) ) {
	final class Bdt_Slider extends \ElementorPro\Modules\Slides\Widgets\Slides {
		use Legacy_Adapter;
		const LEGACY_ID = 'bdt-slider';

		private static $depth = 0;

		/** Las diapositivas de Element Pack que eran una plantilla de Elementor la muestran como contenido. */
		protected function render() {
			$slides = $this->get_settings( 'slides' );
			if ( is_array( $slides ) && self::$depth < 2 && class_exists( '\\Elementor\\Plugin' ) ) {
				++self::$depth;
				foreach ( $slides as $index => $slide ) {
					$id = absint( $slide['digitalisimo_template_id'] ?? 0 );
					if ( $id && 'publish' === get_post_status( $id ) ) {
						$slides[ $index ]['description'] = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $id, true );
					}
				}
				--self::$depth;
				$this->set_settings( 'slides', $slides );
				if ( method_exists( $this, 'reset_render_state' ) ) {
					$this->reset_render_state();
				}
			}
			parent::render();
		}
	}
}
if ( class_exists( '\\ElementorPro\\Modules\\Posts\\Widgets\\Posts' ) && class_exists( '\\ElementorPro\\Modules\\Posts\\Skins\\Skin_Classic' ) ) {
	require_once __DIR__ . '/class-posts-skin.php';

	/** Base de los adaptadores de entradas: sólo la piel propia, con controles registrados a su nombre. */
	abstract class Legacy_Posts extends \ElementorPro\Modules\Posts\Widgets\Posts {
		protected function register_skins() {
			$this->add_skin( new Posts_Skin( $this ) );
		}

		protected function register_controls() {
			parent::register_controls();
			Posts_Skin::register_term_controls( $this );
		}

		/** La consulta se nombra como en `posts` (`posts_*`), no con el ID heredado. */
		public function get_query_name() {
			return 'posts';
		}

		protected function register_query_section_controls() {
			$this->start_controls_section( 'section_query', array( 'label' => esc_html__( 'Query', 'elementor-pro' ), 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
			$this->add_group_control( \ElementorPro\Modules\QueryControl\Controls\Group_Control_Related::get_type(), array( 'name' => 'posts', 'presets' => array( 'full' ), 'exclude' => array( 'posts_per_page' ) ) );
			$this->end_controls_section();
		}
	}
	final class Bdt_PostGrid extends Legacy_Posts { use Legacy_Adapter; const LEGACY_ID = 'bdt-post-grid'; }
	final class Bdt_PostList extends Legacy_Posts { use Legacy_Adapter; const LEGACY_ID = 'bdt-post-list'; }
	final class Bdt_SinglePost extends Legacy_Posts { use Legacy_Adapter; const LEGACY_ID = 'bdt-single-post'; }
	final class Bdt_TestimonialCarousel extends Legacy_Posts { use Legacy_Adapter; const LEGACY_ID = 'bdt-testimonial-carousel'; }
	final class Bdt_TestimonialGrid extends Legacy_Posts { use Legacy_Adapter; const LEGACY_ID = 'bdt-testimonial-grid'; }
	final class Bdt_TestimonialSlider extends Legacy_Posts { use Legacy_Adapter; const LEGACY_ID = 'bdt-testimonial-slider'; }
	final class Bdt_ThumbGallery extends Legacy_Posts { use Legacy_Adapter; const LEGACY_ID = 'bdt-thumb-gallery'; }
	final class Bdt_Carousel extends Legacy_Posts { use Legacy_Adapter; const LEGACY_ID = 'bdt-carousel'; }
	final class Bdt_PortfolioCarousel extends Legacy_Posts { use Legacy_Adapter; const LEGACY_ID = 'bdt-portfolio-carousel'; }
	final class Bdt_PortfolioGallery extends Legacy_Posts { use Legacy_Adapter; const LEGACY_ID = 'bdt-portfolio-gallery'; }
	final class Bdt_PortfolioList extends Legacy_Posts { use Legacy_Adapter; const LEGACY_ID = 'bdt-portfolio-list'; }
	final class Bdt_PostBlock extends Legacy_Posts { use Legacy_Adapter; const LEGACY_ID = 'bdt-post-block'; }
	final class Bdt_PostBlockModern extends Legacy_Posts { use Legacy_Adapter; const LEGACY_ID = 'bdt-post-block-modern'; }
	final class Bdt_PostCard extends Legacy_Posts { use Legacy_Adapter; const LEGACY_ID = 'bdt-post-card'; }
	final class Bdt_PostGallery extends Legacy_Posts { use Legacy_Adapter; const LEGACY_ID = 'bdt-post-gallery'; }
	final class Bdt_PostGridTab extends Legacy_Posts { use Legacy_Adapter; const LEGACY_ID = 'bdt-post-grid-tab'; }
	final class Bdt_PostSlider extends Legacy_Posts { use Legacy_Adapter; const LEGACY_ID = 'bdt-post-slider'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Offcanvas_Widget' ) ) {
	final class Bdt_Offcanvas extends \Digitalisimo\Elements\Offcanvas_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-offcanvas'; }
	final class Bdt_Modal extends \Digitalisimo\Elements\Offcanvas_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-modal'; }
	final class Bdt_Dropbar extends \Digitalisimo\Elements\Offcanvas_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-dropbar'; }
	final class Legacy_Lightbox extends \Digitalisimo\Elements\Offcanvas_Widget { use Legacy_Adapter; const LEGACY_ID = 'lightbox'; }
}
// Widgets que sólo imprimían el shortcode de otro plugin: el widget Shortcode de Elementor lo muestra igual.
if ( class_exists( '\\Elementor\\Widget_Shortcode' ) ) {
	final class Bdt_CharitableCampaigns extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-charitable-campaigns'; }
	final class Bdt_CharitableDonationForm extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-charitable-donation-form'; }
	final class Bdt_CharitableDonations extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-charitable-donations'; }
	final class Bdt_CharitableDonors extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-charitable-donors'; }
	final class Bdt_CharitableLogin extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-charitable-login'; }
	final class Bdt_CharitableProfile extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-charitable-profile'; }
	final class Bdt_CharitableRegistration extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-charitable-registration'; }
	final class Bdt_CharitableStat extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-charitable-stat'; }
	final class Bdt_ContactForm7 extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-contact-form-7'; }
	final class Bdt_EasyDigitalDownloadHistory extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-easy-digital-download-history'; }
	final class Bdt_EasyDigitalProfileEditor extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-easy-digital-profile-editor'; }
	final class Bdt_EasyDigitalPurchaseHistory extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-easy-digital-purchase-history'; }
	final class Bdt_EddLogin extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-edd-login'; }
	final class Bdt_EddRegister extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-edd-register'; }
	final class Bdt_EverestForms extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-everest-forms'; }
	final class Bdt_FluentForms extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-fluent-forms'; }
	final class Bdt_FormidableForms extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-formidable-forms'; }
	final class Bdt_ForminatorForms extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-forminator-forms'; }
	final class Bdt_GiveDonationHistory extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-give-donation-history'; }
	final class Bdt_GiveDonorWall extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-give-donor-wall'; }
	final class Bdt_GiveForm extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-give-form'; }
	final class Bdt_GiveFormGrid extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-give-form-grid'; }
	final class Bdt_GiveGoal extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-give-goal'; }
	final class Bdt_GiveLogin extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-give-login'; }
	final class Bdt_GiveProfileEditor extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-give-profile-editor'; }
	final class Bdt_GiveReceipt extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-give-receipt'; }
	final class Bdt_GiveRegister extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-give-register'; }
	final class Bdt_GiveTotals extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-give-totals'; }
	final class Bdt_GravityForm extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-gravity-form'; }
	final class Bdt_InstagramFeed extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-instagram-feed'; }
	final class Bdt_LayerSlider extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-layer-slider'; }
	final class Bdt_MailchimpForWp extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-mailchimp-for-wp'; }
	final class Bdt_NinjaForm extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-ninja-form'; }
	final class Bdt_Quform extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-quform'; }
	final class Bdt_RevolutionSlider extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-revolution-slider'; }
	final class Bdt_Tablepress extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-tablepress'; }
	final class Bdt_TheNewsletter extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-the-newsletter'; }
	final class Bdt_WcCategories extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-wc-categories'; }
	final class Bdt_WeForm extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-we-form'; }
	final class Bdt_WpForms extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-wp-forms'; }
	final class Bdt_Wpdatatable extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-wpdatatable'; }
	final class Legacy_FooeventsCalendar extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'fooevents-calendar'; }
	final class Bdt_DownloadMonitor extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-download-monitor'; }
	final class Bdt_EddCart extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-edd-cart'; }
	final class Bdt_EddCheckout extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-edd-checkout'; }
	final class Bdt_WcElements extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-wc-elements'; }
	final class Bdt_BbpressForumForm extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-bbpress-forum-form'; }
	final class Bdt_BbpressForumIndex extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-bbpress-forum-index'; }
	final class Bdt_BbpressReplyForm extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-bbpress-reply-form'; }
	final class Bdt_BbpressSingleForum extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-bbpress-single-forum'; }
	final class Bdt_BbpressSingleReply extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-bbpress-single-reply'; }
	final class Bdt_BbpressSingleTag extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-bbpress-single-tag'; }
	final class Bdt_BbpressSingleTopic extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-bbpress-single-topic'; }
	final class Bdt_BbpressSingleView extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-bbpress-single-view'; }
	final class Bdt_BbpressStats extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-bbpress-stats'; }
	final class Bdt_BbpressTopicForm extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-bbpress-topic-form'; }
	final class Bdt_BbpressTopicIndex extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-bbpress-topic-index'; }
	final class Bdt_BbpressTopicTags extends \Elementor\Widget_Shortcode { use Legacy_Adapter; const LEGACY_ID = 'bdt-bbpress-topic-tags'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Document_Viewer_Widget' ) ) {
	final class Bdt_DocumentViewer extends \Digitalisimo\Elements\Document_Viewer_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-document-viewer'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Scroll_Button_Widget' ) ) {
	final class Bdt_ScrollButton extends \Digitalisimo\Elements\Scroll_Button_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-scroll-button'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Logo_Carousel_Widget' ) ) {
	final class Bdt_LogoCarousel extends \Digitalisimo\Elements\Logo_Carousel_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-logo-carousel'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\Countdown\\Widgets\\Countdown' ) ) {
	final class Bdt_Countdown extends \ElementorPro\Modules\Countdown\Widgets\Countdown { use Legacy_Adapter; const LEGACY_ID = 'bdt-countdown'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\FlipBox\\Widgets\\Flip_Box' ) ) {
	final class Bdt_FlipBox extends \ElementorPro\Modules\FlipBox\Widgets\Flip_Box { use Legacy_Adapter; const LEGACY_ID = 'bdt-flip-box'; }
}
if ( class_exists( '\\Elementor\\Widget_Counter' ) ) {
	final class Bdt_AdvancedCounter extends \Elementor\Widget_Counter { use Legacy_Adapter; const LEGACY_ID = 'bdt-advanced-counter'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Widgets\\Post_Title' ) ) {
	final class Bdt_PostTitle extends \ElementorPro\Modules\ThemeBuilder\Widgets\Post_Title { use Legacy_Adapter; const LEGACY_ID = 'bdt-post-title'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Widgets\\Post_Featured_Image' ) ) {
	final class Bdt_PostFeaturedImage extends \ElementorPro\Modules\ThemeBuilder\Widgets\Post_Featured_Image { use Legacy_Adapter; const LEGACY_ID = 'bdt-post-featured-image'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Widgets\\Post_Excerpt' ) ) {
	final class Bdt_PostContent extends \ElementorPro\Modules\ThemeBuilder\Widgets\Post_Excerpt { use Legacy_Adapter; const LEGACY_ID = 'bdt-post-content'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\ThemeElements\\Widgets\\Post_Info' ) ) {
	final class Bdt_PostInfo extends \ElementorPro\Modules\ThemeElements\Widgets\Post_Info { use Legacy_Adapter; const LEGACY_ID = 'bdt-post-info'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\ThemeElements\\Widgets\\Post_Comments' ) ) {
	final class Bdt_PostComments extends \ElementorPro\Modules\ThemeElements\Widgets\Post_Comments { use Legacy_Adapter; const LEGACY_ID = 'bdt-post-comments'; }
}
// Widgets que imprimían un iframe o un marcado de terceros: el widget HTML de Elementor lo muestra igual.
if ( class_exists( '\\Elementor\\Widget_Html' ) ) {
	final class Bdt_Calendly extends \Elementor\Widget_Html { use Legacy_Adapter; const LEGACY_ID = 'bdt-calendly'; }
	final class Bdt_Iframe extends \Elementor\Widget_Html { use Legacy_Adapter; const LEGACY_ID = 'bdt-iframe'; }
}
if ( class_exists( '\\Elementor\\Widget_Image' ) ) {
	final class Bdt_ImageMagnifier extends \Elementor\Widget_Image { use Legacy_Adapter; const LEGACY_ID = 'bdt-image-magnifier'; }
}
if ( class_exists( '\\Elementor\\Widget_Image' ) ) {
	final class Bdt_ScrollImage extends \Elementor\Widget_Image { use Legacy_Adapter; const LEGACY_ID = 'bdt-scroll-image'; }
}
if ( class_exists( '\\Elementor\\Widget_Image' ) ) {
	final class Bdt_SvgImage extends \Elementor\Widget_Image { use Legacy_Adapter; const LEGACY_ID = 'bdt-svg-image'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\CodeHighlight\\Widgets\\Code_Highlight' ) ) {
	final class Bdt_SourceCode extends \ElementorPro\Modules\CodeHighlight\Widgets\Code_Highlight { use Legacy_Adapter; const LEGACY_ID = 'bdt-source-code'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\Lottie\\Widgets\\Lottie' ) ) {
	final class Bdt_LottieIconBox extends \ElementorPro\Modules\Lottie\Widgets\Lottie { use Legacy_Adapter; const LEGACY_ID = 'bdt-lottie-icon-box'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Advanced_Icon_Box_Widget' ) ) {
	final class Bdt_StepFlow extends \Digitalisimo\Elements\Advanced_Icon_Box_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-step-flow'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Video_Player_Widget' ) ) {
	final class Bdt_HoverVideo extends \Digitalisimo\Elements\Video_Player_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-hover-video'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Fancy_Card_Widget' ) ) {
	final class Bdt_AnimatedCard extends \Digitalisimo\Elements\Fancy_Card_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-animated-card'; }
	final class Bdt_InteractiveCard extends \Digitalisimo\Elements\Fancy_Card_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-interactive-card'; }
	final class Bdt_Member extends \Digitalisimo\Elements\Fancy_Card_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-member'; }
	final class Bdt_TrailerBox extends \Digitalisimo\Elements\Fancy_Card_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-trailer-box'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Fancy_Tabs_Widget' ) ) {
	final class Bdt_HoverBox extends \Digitalisimo\Elements\Fancy_Tabs_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-hover-box'; }
	final class Bdt_ImageExpand extends \Digitalisimo\Elements\Fancy_Tabs_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-image-expand'; }
	final class Bdt_InteractiveTabs extends \Digitalisimo\Elements\Fancy_Tabs_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-interactive-tabs'; }
	final class Bdt_StaticGridTab extends \Digitalisimo\Elements\Fancy_Tabs_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-static-grid-tab'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Accordion_Widget' ) ) {
	final class Bdt_EddTabs extends \Digitalisimo\Elements\Accordion_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-edd-tabs'; }
	final class Bdt_ImageAccordion extends \Digitalisimo\Elements\Accordion_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-image-accordion'; }
	final class Bdt_Toggle extends \Digitalisimo\Elements\Accordion_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-toggle'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Progress_Bars_Widget' ) ) {
	final class Bdt_AdvancedProgressBar extends \Digitalisimo\Elements\Progress_Bars_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-advanced-progress-bar'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Progress_Bars_Widget' ) ) {
	final class Bdt_ProgressPie extends \Digitalisimo\Elements\Progress_Bars_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-progress-pie'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Reading_Progress_Widget' ) ) {
	final class Bdt_ReadingProgress extends \Digitalisimo\Elements\Reading_Progress_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-reading-progress'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Reading_Time_Widget' ) ) {
	final class Bdt_ReadingTimer extends \Digitalisimo\Elements\Reading_Time_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-reading-timer'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Audio_Player_Widget' ) ) {
	final class Bdt_AudioPlayer extends \Digitalisimo\Elements\Audio_Player_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-audio-player'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Image_Compare_Widget' ) ) {
	final class Bdt_ImageCompare extends \Digitalisimo\Elements\Image_Compare_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-image-compare'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Business_Hours_Widget' ) ) {
	final class Bdt_BusinessHours extends \Digitalisimo\Elements\Business_Hours_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-business-hours'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Marquee_Widget' ) ) {
	final class Bdt_Marquee extends \Digitalisimo\Elements\Marquee_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-marquee'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Marquee_Widget' ) ) {
	final class Bdt_NewsTicker extends \Digitalisimo\Elements\Marquee_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-news-ticker'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Timeline_Widget' ) ) {
	final class Bdt_Timeline extends \Digitalisimo\Elements\Timeline_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-timeline'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Chart_Widget' ) ) {
	final class Bdt_Chart extends \Digitalisimo\Elements\Chart_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-chart'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Map_Widget' ) ) {
	final class Bdt_OpenStreetMap extends \Digitalisimo\Elements\Map_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-open-street-map'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\Carousel\\Widgets\\Media_Carousel' ) ) {
	final class Bdt_CustomCarousel extends \ElementorPro\Modules\Carousel\Widgets\Media_Carousel { use Legacy_Adapter; const LEGACY_ID = 'bdt-custom-carousel'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\Carousel\\Widgets\\Media_Carousel' ) ) {
	final class Bdt_VideoGallery extends \ElementorPro\Modules\Carousel\Widgets\Media_Carousel { use Legacy_Adapter; const LEGACY_ID = 'bdt-video-gallery'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Fancy_Slider_Widget' ) ) {
	final class Bdt_StaticCarousel extends \Digitalisimo\Elements\Fancy_Slider_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-static-carousel'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Fancy_Slider_Widget' ) ) {
	final class Bdt_PanelSlider extends \Digitalisimo\Elements\Fancy_Slider_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-panel-slider'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Fancy_Slider_Widget' ) ) {
	final class Bdt_Slideshow extends \Digitalisimo\Elements\Fancy_Slider_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-slideshow'; }
	final class Bdt_ProductCarousel extends \Digitalisimo\Elements\Fancy_Slider_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-product-carousel'; }
}
if ( class_exists( '\\Elementor\\Widget_Image_Gallery' ) ) {
	final class Bdt_AdvancedImageGallery extends \Elementor\Widget_Image_Gallery { use Legacy_Adapter; const LEGACY_ID = 'bdt-advanced-image-gallery'; }
}
if ( class_exists( '\\Elementor\\Widget_Image_Gallery' ) ) {
	final class Bdt_ImageStack extends \Elementor\Widget_Image_Gallery { use Legacy_Adapter; const LEGACY_ID = 'bdt-image-stack'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\Carousel\\Widgets\\Reviews' ) ) {
	final class Bdt_ReviewCard extends \ElementorPro\Modules\Carousel\Widgets\Reviews { use Legacy_Adapter; const LEGACY_ID = 'bdt-review-card'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\Carousel\\Widgets\\Reviews' ) ) {
	final class Bdt_ReviewCardCarousel extends \ElementorPro\Modules\Carousel\Widgets\Reviews { use Legacy_Adapter; const LEGACY_ID = 'bdt-review-card-carousel'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\Carousel\\Widgets\\Reviews' ) ) {
	final class Bdt_ReviewCardGrid extends \ElementorPro\Modules\Carousel\Widgets\Reviews { use Legacy_Adapter; const LEGACY_ID = 'bdt-review-card-grid'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\Woocommerce\\Widgets\\Products' ) ) {
	/**
	 * Base de las rejillas de productos de Element Pack: `woocommerce-products` con el número exacto de
	 * productos y las partes que el widget heredado mostraba (imagen, título, extracto, categoría,
	 * valoración, precio, botón), aplicadas con los hooks del bucle de WooCommerce sólo durante su render.
	 */
	abstract class Legacy_Products extends \ElementorPro\Modules\Woocommerce\Widgets\Products {
		const PARTS = array(
			'image'  => array( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 ),
			'title'  => array( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 ),
			'rating' => array( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 ),
			'price'  => array( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 ),
			'cart'   => array( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 ),
			'badge'  => array( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 ),
		);

		protected function register_controls() {
			parent::register_controls();
			$hidden = array( 'type' => \Elementor\Controls_Manager::HIDDEN );
			$this->add_control( 'posts_per_page', $hidden + array( 'default' => '' ) );
			$this->add_control( 'digitalisimo_wc_parts', $hidden + array( 'default' => 'image,title,rating,price,cart,badge' ) );
			$this->add_control( 'digitalisimo_wc_excerpt_length', $hidden + array( 'default' => 10 ) );
			$this->add_control( 'digitalisimo_wc_title_tag', $hidden + array( 'default' => 'h2' ) );
			$this->add_control( 'digitalisimo_wc_readmore_text', $hidden + array( 'default' => 'Read More' ) );
		}

		protected function render() {
			$s       = $this->get_settings_for_display();
			$parts   = array_filter( explode( ',', (string) ( $s['digitalisimo_wc_parts'] ?? '' ) ) );
			$tag     = in_array( $s['digitalisimo_wc_title_tag'] ?? 'h2', array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), true ) ? $s['digitalisimo_wc_title_tag'] : 'h2';
			$removed = array();
			foreach ( self::PARTS as $part => $hook ) {
				$custom_title = 'title' === $part && 'h2' !== $tag;
				if ( ( ! in_array( $part, $parts, true ) || $custom_title ) && false !== has_action( $hook[0], $hook[1] ) ) {
					remove_action( $hook[0], $hook[1], $hook[2] );
					$removed[] = $hook;
				}
			}
			$added = array();
			if ( in_array( 'title', $parts, true ) && 'h2' !== $tag ) {
				$added[] = array( 'woocommerce_shop_loop_item_title', static function () use ( $tag ) {
					echo '<' . $tag . ' class="' . esc_attr( apply_filters( 'woocommerce_product_loop_title_classes', 'woocommerce-loop-product__title' ) ) . '">' . esc_html( get_the_title() ) . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- etiqueta de una lista cerrada.
				}, 10 );
			}
			if ( in_array( 'category', $parts, true ) ) {
				$added[] = array( 'woocommerce_after_shop_loop_item_title', static function () {
					$list = wc_get_product_category_list( get_the_ID(), ', ' );
					echo $list ? '<div class="digi-legacy-product__categories">' . wp_kses_post( $list ) . '</div>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post.
				}, 4 );
			}
			// Como Element Pack: el contenido recortado, o en «summary» el extracto manual si existe.
			$summary = in_array( 'summary', $parts, true );
			if ( $summary || in_array( 'excerpt', $parts, true ) ) {
				$words   = max( 1, (int) ( $s['digitalisimo_wc_excerpt_length'] ?? 10 ) );
				$added[] = array( 'woocommerce_after_shop_loop_item_title', static function () use ( $words, $summary ) {
					$text = $summary && has_excerpt() ? wp_strip_all_tags( (string) get_the_excerpt() ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) get_the_content() ) ), $words, $summary ? '…' : '' );
					echo '' !== trim( $text ) ? '<div class="digi-legacy-product__excerpt">' . esc_html( trim( $text ) ) . '</div>' : '';
				}, 6 );
			}
			if ( in_array( 'readmore', $parts, true ) ) {
				$label   = (string) ( $s['digitalisimo_wc_readmore_text'] ?? '' );
				$added[] = array( 'woocommerce_after_shop_loop_item', static function () use ( $label ) {
					echo '<a class="button digi-legacy-product__readmore" href="' . esc_url( get_permalink() ) . '">' . esc_html( '' !== $label ? $label : __( 'Read more', 'woocommerce' ) ) . '</a>';
				}, 11 );
			}
			foreach ( $added as $hook ) {
				add_action( $hook[0], $hook[1], $hook[2] );
			}
			try {
				parent::render();
			} finally {
				foreach ( $added as $hook ) {
					remove_action( $hook[0], $hook[1], $hook[2] );
				}
				foreach ( $removed as $hook ) {
					add_action( $hook[0], $hook[1], $hook[2] );
				}
			}
		}
	}
	final class Bdt_WcProducts extends Legacy_Products { use Legacy_Adapter; const LEGACY_ID = 'bdt-wc-products'; }
	final class Bdt_WcCarousel extends Legacy_Products { use Legacy_Adapter; const LEGACY_ID = 'bdt-wc-carousel'; }
	final class Bdt_WcSlider extends Legacy_Products { use Legacy_Adapter; const LEGACY_ID = 'bdt-wc-slider'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\Woocommerce\\Widgets\\Add_To_Cart' ) ) {
	final class Bdt_WcAddToCart extends \ElementorPro\Modules\Woocommerce\Widgets\Add_To_Cart { use Legacy_Adapter; const LEGACY_ID = 'bdt-wc-add-to-cart'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\Woocommerce\\Widgets\\Menu_Cart' ) ) {
	final class Bdt_WcMiniCart extends \ElementorPro\Modules\Woocommerce\Widgets\Menu_Cart { use Legacy_Adapter; const LEGACY_ID = 'bdt-wc-mini-cart'; }
}
if ( class_exists( '\\ElementorPro\\Modules\\LoopBuilder\\Skins\\Skin_Loop_Post' ) ) {
	/**
	 * Piel «post» de los adaptadores dinámicos: Element Pack admitía cualquier plantilla de Elementor
	 * (no sólo «Loop Item»), así que cada entrada imprime la plantilla elegida como lo hacía él, dentro
	 * del elemento de bucle (y de la diapositiva en el carrusel) que esperan los estilos y scripts del Loop.
	 */
	class Loop_Template_Skin extends \ElementorPro\Modules\LoopBuilder\Skins\Skin_Loop_Post {
		protected function render_post() {
			$template = (int) $this->parent->get_settings_for_display( 'template_id' );
			if ( ! $template || ! class_exists( '\\Elementor\\Plugin' ) ) {
				return;
			}
			$slide = $this->parent instanceof \ElementorPro\Modules\LoopBuilder\Widgets\Loop_Carousel;
			$class = 'e-loop-item e-loop-item-' . get_the_ID() . ' ' . implode( ' ', get_post_class() ) . ( $slide ? ' swiper-slide' : '' );
			echo '<div class="' . esc_attr( $class ) . '"' . ( $slide ? ' role="group" aria-roledescription="slide"' : '' ) . '>';
			echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $template, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- contenido de Elementor.
			echo '</div>';
		}
	}
	if ( class_exists( '\\ElementorPro\\Modules\\LoopBuilder\\Widgets\\Loop_Grid' ) ) {
		final class Bdt_DynamicGrid extends \ElementorPro\Modules\LoopBuilder\Widgets\Loop_Grid {
			use Legacy_Adapter;
			const LEGACY_ID = 'bdt-dynamic-grid';
			protected function register_skins() {
				$this->add_skin( new Loop_Template_Skin( $this ) );
			}
		}
	}
	if ( class_exists( '\\ElementorPro\\Modules\\LoopBuilder\\Widgets\\Loop_Carousel' ) ) {
		final class Bdt_DynamicCarousel extends \ElementorPro\Modules\LoopBuilder\Widgets\Loop_Carousel {
			use Legacy_Adapter;
			const LEGACY_ID = 'bdt-dynamic-carousel';
			protected function register_skins() {
				$this->add_skin( new Loop_Template_Skin( $this ) );
			}
		}
	}
}
if ( class_exists( '\\Elementor\\Widget_Google_Maps' ) ) {
	final class Bdt_AdvancedGmap extends \Elementor\Widget_Google_Maps { use Legacy_Adapter; const LEGACY_ID = 'bdt-advanced-gmap'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Accordion_Widget' ) ) {
	final class Bdt_AcfAccordion extends \Digitalisimo\Elements\Accordion_Widget {
		use Legacy_Adapter, Legacy_Acf;
		const LEGACY_ID  = 'bdt-acf-accordion';
		const ACF_FIELDS = array( 'digitalisimo_acf_field' => 'Campo repetidor', 'digitalisimo_acf_title' => 'Subcampo del título', 'digitalisimo_acf_content' => 'Subcampo del contenido' );
		protected function acf_settings( array $s ) {
			$tabs = array();
			foreach ( Acf_Source::rows( $s['digitalisimo_acf_field'] ?? '' ) as $i => $row ) {
				$title   = Acf_Source::text( $row, $s['digitalisimo_acf_title'] ?? '' );
				$content = Acf_Source::text( $row, $s['digitalisimo_acf_content'] ?? '' );
				if ( '' !== $title || '' !== $content ) {
					$tabs[] = array( '_id' => self::acf_item_id( $i ), 'tab_title' => $title, 'source' => 'custom', 'tab_content' => $content );
				}
			}
			return array( 'tabs' => $tabs );
		}
	}
}
if ( class_exists( '\\Digitalisimo\\Elements\\Fancy_Tabs_Widget' ) ) {
	final class Bdt_AcfTabs extends \Digitalisimo\Elements\Fancy_Tabs_Widget {
		use Legacy_Adapter, Legacy_Acf;
		const LEGACY_ID  = 'bdt-acf-tabs';
		const ACF_FIELDS = array( 'digitalisimo_acf_field' => 'Campo repetidor', 'digitalisimo_acf_title' => 'Subcampo del título', 'digitalisimo_acf_sub_title' => 'Subcampo del subtítulo', 'digitalisimo_acf_content' => 'Subcampo del contenido' );
		protected function acf_settings( array $s ) {
			$tabs = array();
			foreach ( Acf_Source::rows( $s['digitalisimo_acf_field'] ?? '' ) as $i => $row ) {
				$title = Acf_Source::text( $row, $s['digitalisimo_acf_title'] ?? '' );
				if ( '' !== $title ) {
					$tabs[] = array( '_id' => self::acf_item_id( $i ), 'icon_type' => 'none', 'tab_title' => $title, 'tab_sub_title' => Acf_Source::text( $row, $s['digitalisimo_acf_sub_title'] ?? '' ), 'tab_content' => Acf_Source::text( $row, $s['digitalisimo_acf_content'] ?? '' ) );
				}
			}
			return array( 'tabs' => $tabs );
		}
	}
}
if ( class_exists( '\\Elementor\\Widget_Icon_List' ) ) {
	final class Bdt_AcfList extends \Elementor\Widget_Icon_List {
		use Legacy_Adapter, Legacy_Acf;
		const LEGACY_ID  = 'bdt-acf-list';
		const ACF_FIELDS = array( 'digitalisimo_acf_field' => 'Campo repetidor', 'digitalisimo_acf_title' => 'Subcampo del título', 'digitalisimo_acf_text' => 'Subcampo del texto', 'digitalisimo_acf_link' => 'Subcampo del enlace' );
		protected function acf_settings( array $s ) {
			$items = array();
			foreach ( Acf_Source::rows( $s['digitalisimo_acf_field'] ?? '' ) as $i => $row ) {
				// El título en negrita y el texto a continuación, como los dos elementos de Element Pack.
				$title = trim( wp_strip_all_tags( Acf_Source::text( $row, $s['digitalisimo_acf_title'] ?? '' ) ) );
				$body  = trim( wp_strip_all_tags( Acf_Source::text( $row, $s['digitalisimo_acf_text'] ?? '' ) ) );
				$text  = trim( ( '' !== $title ? '<strong>' . esc_html( $title ) . '</strong> ' : '' ) . esc_html( $body ) );
				if ( '' !== $text ) {
					$link    = '' !== (string) ( $s['digitalisimo_acf_link'] ?? '' ) ? Acf_Source::link( $row[ $s['digitalisimo_acf_link'] ] ?? '' ) : array( 'url' => '' );
					$items[] = array( '_id' => self::acf_item_id( $i ), 'text' => $text, 'selected_icon' => is_array( $s['list_icon'] ?? null ) ? $s['list_icon'] : array( 'value' => '', 'library' => '' ), 'link' => $link );
				}
			}
			return array( 'icon_list' => $items );
		}
	}
}
if ( class_exists( '\\Digitalisimo\\Elements\\Fancy_Slider_Widget' ) ) {
	final class Bdt_AcfSlider extends \Digitalisimo\Elements\Fancy_Slider_Widget {
		use Legacy_Adapter, Legacy_Acf;
		const LEGACY_ID  = 'bdt-acf-slider';
		const ACF_FIELDS = array( 'digitalisimo_acf_field' => 'Campo repetidor', 'digitalisimo_acf_title' => 'Subcampo del título', 'digitalisimo_acf_image' => 'Subcampo de la imagen', 'digitalisimo_acf_content' => 'Subcampo del contenido', 'digitalisimo_acf_link' => 'Subcampo del enlace' );
		protected function acf_settings( array $s ) {
			$slides = array();
			foreach ( Acf_Source::rows( $s['digitalisimo_acf_field'] ?? '' ) as $i => $row ) {
				$link     = '' !== (string) ( $s['digitalisimo_acf_link'] ?? '' ) ? Acf_Source::link( $row[ $s['digitalisimo_acf_link'] ] ?? '' ) : array( 'url' => '' );
				$slides[] = array(
					'_id'          => self::acf_item_id( $i ),
					'title'        => trim( wp_strip_all_tags( Acf_Source::text( $row, $s['digitalisimo_acf_title'] ?? '' ) ) ),
					'description'  => Acf_Source::text( $row, $s['digitalisimo_acf_content'] ?? '' ),
					'slide_image'  => '' !== (string) ( $s['digitalisimo_acf_image'] ?? '' ) ? Acf_Source::image( $row[ $s['digitalisimo_acf_image'] ] ?? '' ) : array( 'url' => '' ),
					'slide_button' => (string) ( $s['button_text'] ?? '' ),
					'button_link'  => $link,
				);
			}
			return array( 'slides' => $slides );
		}
	}
}
if ( class_exists( '\\Elementor\\Widget_Image_Gallery' ) ) {
	final class Bdt_AcfGallery extends \Elementor\Widget_Image_Gallery {
		use Legacy_Adapter, Legacy_Acf;
		const LEGACY_ID  = 'bdt-acf-gallery';
		const ACF_FIELDS = array( 'digitalisimo_acf_field' => 'Campo galería' );
		protected function acf_settings( array $s ) {
			$images = array();
			foreach ( (array) Acf_Source::value( $s['digitalisimo_acf_field'] ?? '' ) as $value ) {
				$image = Acf_Source::image( $value );
				if ( $image['id'] || '' !== $image['url'] ) {
					$images[] = array( 'id' => $image['id'], 'url' => $image['url'] );
				}
			}
			return array( 'wp_gallery' => $images );
		}
	}
}
