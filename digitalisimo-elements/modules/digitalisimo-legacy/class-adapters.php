<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-translator.php';

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
}
if ( class_exists( '\\Digitalisimo\\Elements\\Offcanvas_Widget' ) ) {
	final class Bdt_Offcanvas extends \Digitalisimo\Elements\Offcanvas_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-offcanvas'; }
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
