#!/usr/bin/env python3
"""Genera maps/bdt-*.php de los widgets de Element Pack que sólo imprimían el shortcode de otro plugin.

Cada entrada reproduce el `get_shortcode()` de la referencia: etiqueta, atributos (expresiones PHP
sobre `$s`, los ajustes ya completados con los defaults) y el ajuste obligatorio sin el que Element
Pack mostraba un aviso en lugar del shortcode. El destino es el widget Shortcode de Elementor; la
clase envolvente de Element Pack se reescribe a `.elementor-shortcode` para los estilos heredados.

Uso: python3 scripts/legacy-shortcode-maps.py [bdt-id …]   (sin argumentos, todos)
"""
import re
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
from legacy_controls import detail, ROOT  # noqa: E402

UI = ('HEADING', 'RAW_HTML', 'DIVIDER', 'NOTICE', 'ALERT', 'DEPRECATED_NOTICE', 'BUTTON', 'POPOVER_TOGGLE')
URL = "( is_array( $s['%s'] ?? null ) ? (string) ( $s['%s']['url'] ?? '' ) : '' )"


def u(key):
    return URL % (key, key)


def v(key, fallback="''"):
    return "( $s['%s'] ?? %s )" % (key, fallback)


def same(*keys):
    return ', '.join("'%s' => %s" % (k, v(k)) for k in keys)


SPEC = {
    'bdt-charitable-campaigns': ('campaigns', None, "array( 'orderby' => %s, 'order' => %s, 'number' => %s, 'button' => %s ) + ( in_array( 'all', (array) ( $s['campaigns'] ?? array() ), true ) ? array() : array( 'id' => implode( ',', (array) ( $s['campaigns'] ?? array() ) ) ) )" % (v('orderby'), v('order'), v('number'), v('button')), 'campaigns'),
    'bdt-charitable-donation-form': ('charitable_donation_form', None, "array( 'campaign_id' => %s )" % v('form_id'), 'form_id'),
    'bdt-charitable-donations': ('charitable_my_donations', None, 'array()', None),
    'bdt-charitable-donors': ('charitable_donors', None, 'array( %s )' % same('campaign', 'orderby', 'order', 'number', 'show_name', 'show_location', 'show_amount', 'show_avatar'), 'campaign'),
    'bdt-charitable-login': ('charitable_login', None, "array( 'logged_in_message' => '' !== (string) %s ? %s : 'You are already logged in!', 'registration_link_text' => '' !== (string) %s ? %s : 'Register', 'redirect' => %s )" % (v('logged_in_message'), v('logged_in_message'), v('registration_link_text'), v('registration_link_text'), u('redirect')), None),
    'bdt-charitable-profile': ('charitable_profile', None, 'array()', None),
    'bdt-charitable-registration': ('charitable_registration', None, "array( 'logged_in_message' => '' !== (string) %s ? %s : 'You are already logged in!', 'registration_link_text' => '' !== (string) %s ? %s : 'Signed up already? Login instead.', 'redirect' => %s )" % (v('logged_in_message'), v('logged_in_message'), v('registration_link_text'), v('registration_link_text'), u('redirect')), None),
    'bdt-charitable-stat': ('charitable_stat', None, "array( 'campaigns' => implode( ',', (array) ( $s['campaign'] ?? array() ) ), 'display' => %s, 'goal' => %s )" % (v('display'), v('goal')), 'campaign'),
    'bdt-contact-form-7': ('contact-form-7', None, "array( 'id' => %s )" % v('contact_form'), 'contact_form'),
    'bdt-everest-forms': ('everest_form', None, "array( 'id' => %s )" % v('everest_form'), 'everest_form'),
    'bdt-fluent-forms': ('fluentform', None, "array( 'id' => %s )" % v('fluent_form'), 'fluent_form'),
    'bdt-formidable-forms': ('formidable', None, "array( 'id' => %s )" % v('formidable_form'), 'formidable_form'),
    'bdt-forminator-forms': ('forminator_form', None, "array( 'id' => %s )" % v('forminator_form'), 'forminator_form'),
    'bdt-give-donation-history': ('donation_history', None, "array( %s, 'payment_method' => %s )" % (same('id', 'donor', 'date', 'amount', 'status').replace("'id' => ( $s['id'] ?? '' )", "'id' => ( $s['form_id'] ?? '' )"), v('method')), 'form_id'),
    'bdt-give-donor-wall': ('give_donor_wall', None, "array( %s, 'loadmore_text' => esc_html( (string) %s ), 'readmore_text' => esc_html( (string) %s ), 'show_comments' => 'yes' === %s, 'only_comments' => 'yes' === %s, 'comment_length' => %s )" % (same('form_id', 'donors_per_page', 'anonymous', 'show_avatar', 'show_name', 'show_total', 'show_time', 'order', 'orderby'), v('loadmore_text'), v('readmore_text'), v('show_comments'), v('only_comments'), v('comment_length')), "form_id|all_forms"),
    'bdt-give-form': ('give_form', None, "array( 'id' => %s, %s )" % (v('form_id'), same('display_style', 'continue_button_title')), 'form_id'),
    'bdt-give-form-grid': ('give_form_grid', None, "array( %s, 'display_style' => 'modal_reveal' )" % same('forms_per_page', 'orderby', 'order', 'show_title', 'show_goal', 'show_excerpt', 'excerpt_length', 'show_featured_image'), None),
    'bdt-give-goal': ('give_goal', None, "array( 'id' => %s, %s )" % (v('form_id'), same('show_text', 'show_bar')), 'form_id'),
    'bdt-give-login': ('give_login', None, "array( 'login_redirect' => %s )" % u('login_url'), None),
    'bdt-give-profile-editor': ('give_profile_editor', None, 'array()', None),
    'bdt-give-receipt': ('give_receipt', None, "array( 'error' => esc_html( (string) %s ), %s, 'payment_method' => %s, 'payment_id' => %s, 'payment_status' => %s, 'company_name' => %s, 'status_notice' => %s )" % (v('error'), same('price', 'donor', 'date'), v('method'), v('payment_id'), v('status'), v('company'), v('status_notice')), None),
    'bdt-give-register': ('give_register', None, "array( 'redirect' => %s )" % u('link'), None),
    'bdt-give-totals': ('give_totals', None, "array( 'ids' => %s, 'total_goal' => %s, 'message' => esc_html( (string) %s ), 'link' => esc_url( %s ), 'link_text' => esc_html( (string) %s ), 'progress_bar' => %s )" % (v('forms'), v('total_goal'), v('message'), u('link'), v('link_text'), v('show_progress')), 'forms'),
    'bdt-instagram-feed': ('instagram-feed', None, 'array()', None),
    'bdt-layer-slider': ('layerslider', None, "array( 'id' => %s, 'firstslide' => %s )" % (v('slider_name'), v('firstslide')), None),
    'bdt-mailchimp-for-wp': ('mc4wp_form', None, "array( 'id' => %s )" % v('mailchimp_id'), None),
    'bdt-ninja-form': ('ninja_form', None, "array( 'id' => %s )" % v('ninja_form'), 'ninja_form'),
    'bdt-quform': ('quform', None, "array( 'id' => %s )" % v('contact_form'), 'contact_form'),
    'bdt-revolution-slider': ('rev_slider', None, "array( 'alias' => %s )" % v('slider_name'), 'slider_name'),
    'bdt-tablepress': ('table', None, "array( 'id' => %s, 'responsive' => class_exists( 'TablePress_Responsive_Tables' ) ? %s : '' )" % (v('table_id'), v('table_responsive')), 'table_id'),
    'bdt-the-newsletter': ('newsletter_form', None, "array( 'type' => %s )" % v('the_news_letter_type'), None),
    'bdt-wc-categories': ('product_categories', None, "array( 'number' => %s, 'hide_empty' => 'yes' === %s ? 1 : 0, 'orderby' => %s, 'order' => %s ) + ( 'by_id' === %s ? array( 'ids' => implode( ',', (array) ( $s['categories'] ?? array() ) ) ) : ( 'by_parent' === %s ? array( 'parent' => %s ) : array() ) )" % (v('number'), v('hide_empty'), v('orderby'), v('order'), v('source'), v('source'), v('parent')), None),
    'bdt-wp-forms': ('wpforms', None, "array( 'id' => %s )" % v('contact_form'), 'contact_form'),
    'bdt-gravity-form': ('gravityform', None, "array( 'id' => (int) %s, 'title' => 'yes' === %s ? 'true' : 'false', 'description' => '' !== (string) %s ? 'true' : 'false', 'ajax' => '' !== (string) %s ? 'true' : 'false', 'tabindex' => '0' )" % (v('gravity_form'), v('title_hide'), v('description_hide'), v('form_ajax')), 'gravity_form'),
    'bdt-wpdatatable': ('wpdatatable', None, "array( 'id' => %s )" % v('table_id'), 'table_id'),
    # bbPress: Element Pack copiaba el código de sus shortcodes; se usan los del propio bbPress.
    'bdt-bbpress-forum-form': ('bbp-forum-form', 'raw', None, None),
    'bdt-bbpress-forum-index': ('bbp-forum-index', 'raw', None, None),
    'bdt-bbpress-reply-form': ('bbp-reply-form', 'raw', None, None),
    'bdt-bbpress-single-forum': ('bbp-single-forum', None, "array( 'id' => %s )" % v('bbpress_single_id'), 'bbpress_single_id'),
    'bdt-bbpress-single-reply': ('bbp-single-reply', None, "array( 'id' => %s )" % v('bbpress_reply_id'), 'bbpress_reply_id'),
    'bdt-bbpress-single-tag': ('bbp-single-tag', None, "array( 'id' => %s )" % v('bbpress_topic_tag_id'), 'bbpress_topic_tag_id'),
    'bdt-bbpress-single-topic': ('bbp-single-topic', None, "array( 'id' => %s )" % v('bbpress_topic_id'), 'bbpress_topic_id'),
    'bdt-bbpress-single-view': ('bbp-single-view', None, "array( 'id' => %s )" % v('bbpress_specific_view'), 'bbpress_specific_view'),
    'bdt-bbpress-stats': ('bbp-stats', 'raw', None, None),
    'bdt-bbpress-topic-form': ('bbp-topic-form', None, "( '' !== (string) %s ? array( 'forum_id' => %s ) : array() )" % (v('bbpress_forum_id'), v('bbpress_forum_id')), None),
    'bdt-bbpress-topic-index': ('bbp-topic-index', 'raw', None, None),
    'bdt-bbpress-topic-tags': ('bbp-topic-tags', 'raw', None, None),
    'bdt-easy-digital-download-history': ('download_history', 'raw', None, None),
    'bdt-easy-digital-profile-editor': ('edd_profile_editor', 'raw', None, None),
    'bdt-easy-digital-purchase-history': ('purchase_history', 'raw', None, None),
    'bdt-edd-login': ('edd_login', 'raw', None, None),
    'bdt-edd-register': ('edd_register', None, "array( 'redirect' => %s )" % u('form_register_redirect_url'), None),
}

# Atributos opcionales de weForms escritos como «clave|valor», con los mismos filtros de Element Pack.
WE_FORM = r"""		$attributes = array( 'id' => $s['we_form'] ?? '' );
		foreach ( explode( "\n", (string) ( $s['custom_attributes'] ?? '' ) ) as $line ) {
			$pair = explode( '|', $line, 2 );
			$key  = trim( $pair[0] );
			if ( '' === $key || ! preg_match( '/^[A-Za-z][A-Za-z0-9_:-]*$/', $key ) || 0 === stripos( $key, 'on' ) || in_array( strtolower( $key ), array( 'style', 'class' ), true ) ) {
				continue;
			}
			$attributes[ $key ] = trim( $pair[1] ?? '' );
		}
		$shortcode = '' !== (string) ( $s['we_form'] ?? '' ) ? Digitalisimo\Elements\Legacy\Translator::shortcode( 'weforms', $attributes ) : '';
"""

FOOEVENTS = r"""		$parts = array();
		$map   = array( 'fooevents_calendar_list' => 'defaultView', 'fooevents_calendar_include_cat' => 'include_cat', 'fooevents_calendar_num' => 'num', 'fooevents_calendar_post' => 'post', 'fooevents_calendar_id' => 'id', 'fooevents_calendar_time_format' => 'timeFormat' );
		if ( '' !== (string) ( $s['fooevents_calendar_list'] ?? '' ) ) {
			$parts[] = 'defaultView="' . esc_attr( $s['fooevents_calendar_list'] ) . '"';
		}
		// Element Pack compara con «0» sin convertir: un ajuste anulado (null) también imprime firstDay.
		if ( '0' !== ( array_key_exists( 'fooevents_calendar_startday', $s ) ? $s['fooevents_calendar_startday'] : '0' ) ) {
			$parts[] = 'firstDay="' . esc_attr( (string) $s['fooevents_calendar_startday'] ) . '"';
		}
		if ( '' !== (string) ( $s['fooevents_calendar_default_date'] ?? '' ) ) {
			$parts[] = 'defaultDate="' . esc_attr( gmdate( 'Y-m-d', strtotime( (string) $s['fooevents_calendar_default_date'] ) ) ) . '"';
		}
		foreach ( array_slice( $map, 1 ) as $key => $attribute ) {
			if ( '' !== (string) ( $s[ $key ] ?? '' ) ) {
				$parts[] = $attribute . '="' . esc_attr( $s[ $key ] ) . '"';
			}
		}
		if ( 'yes' !== ( $s['fooevents_calendar_weekends'] ?? '' ) ) {
			$parts[] = 'weekends="false"';
		}
		$shortcode = '[fooevents_calendar' . ( $parts ? ' ' . implode( ' ', $parts ) : '' ) . ']';
"""

EXTRA = {'bdt-we-form': WE_FORM, 'fooevents-calendar': FOOEVENTS}


_STYLES = None


def styles_module():
    global _STYLES
    if _STYLES is None:
        import importlib.util
        spec = importlib.util.spec_from_file_location('legacy_styles', HERE / 'legacy-styles.py')
        _STYLES = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(_STYLES)
    return _STYLES


def php_literal(value, indent):
    return styles_module().php(value, indent)


def generate(legacy_id):
    skeleton = subprocess.run(['python3', str(HERE / 'legacy-map-skeleton.py'), legacy_id, '--target=shortcode'], capture_output=True, text=True, check=True).stdout
    defaults = re.search(r"\t'defaults'  => (array\(.*?\n\t\)|array\(\)),\n", skeleton, re.S)
    inventory = __import__('json').loads((ROOT / 'docs/element-pack-inventory.json').read_text())
    source = ROOT / next(w['source_file'] for w in inventory['widgets'] if w['legacy_widget_id'] == legacy_id)
    src = source.read_text(errors='ignore')
    content, conditions = [], {}
    for c in detail(source):
        ctype = c.get('type', '').split('::')[-1].strip()
        if c['var'] == '$this' and c['kind'] != 'group' and not c.get('raw_selectors') and ctype not in UI and c['name'] not in content:
            content.append(c['name'])
            if c.get('raw_condition'):
                try:
                    conditions[c['name']] = styles_module().literal(c['raw_condition'])
                except Exception:  # noqa: BLE001
                    pass
    wrappers = sorted(set(re.findall(r"'class',\s*'(bdt-[a-z0-9-]+)'", src)) | set(re.findall(r'class="(bdt-[a-z0-9-]+)"', src)))
    wrappers = [w for w in wrappers if not w.startswith(('bdt-alert', 'bdt-margin', 'bdt-flex', 'bdt-grid', 'bdt-width', 'bdt-text-'))]
    classes = ''.join("\t\t'.%s' => '.elementor-shortcode',\n" % w for w in wrappers)
    if legacy_id in EXTRA:
        body = EXTRA[legacy_id]
    else:
        tag, mode, attributes, required = SPEC[legacy_id]
        if mode == 'raw':
            body = "\t\t$shortcode = '[%s]';\n" % tag
        else:
            check = ''
            if required:
                keys = required.split('|')
                if len(keys) == 2:
                    check = "( ! empty( $s['%s'] ) || 'yes' === ( $s['%s'] ?? '' ) ) ? " % tuple(keys)
                else:
                    check = "! empty( $s['%s'] ) ? " % keys[0]
            body = "\t\t$shortcode = %sDigitalisimo\\Elements\\Legacy\\Translator::shortcode( '%s', %s )%s;\n" % (check, tag, attributes, " : ''" if check else '')
    note = '' if legacy_id in EXTRA or SPEC[legacy_id][3] is None else "\t\t// Sin el ajuste obligatorio Element Pack mostraba un aviso; aquí no se muestra nada.\n"
    text = '\n'.join([
        '<?php',
        '/** Element Pack Pro 9.9.1 `%s` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */' % legacy_id,
        "defined( 'ABSPATH' ) || exit;",
        '',
        "$placeholder_url = class_exists( '\\Elementor\\Utils' ) ? \\Elementor\\Utils::get_placeholder_image_src() : '';",
        '',
        'return array(',
        "\t'target'    => 'shortcode',",
        "\t'classes'   => array(\n%s\t)," % classes if classes else "\t'classes'   => array(),",
        "\t'defaults'  => %s," % (defaults.group(1) if defaults else 'array()'),
        "\t// Ajustes que Elementor anula cuando su control no cumple la condición.\n\t'conditions' => %s," % php_literal(conditions, 1),
        "\t'filter'    => static function ( array $out ) {",
        '\t\t$s = $out;',
        note + body.rstrip('\n'),
        '\t\treturn Digitalisimo\\Elements\\Legacy\\Translator::as_shortcode( $out, $shortcode, %s );' % php_literal(content, 2),
        '\t},',
        ');',
        '',
    ])
    (ROOT / 'digitalisimo-elements/modules/digitalisimo-legacy/maps' / (legacy_id + '.php')).write_text(text, encoding='utf-8')
    return legacy_id


if __name__ == '__main__':
    ids = sys.argv[1:] or sorted(list(SPEC) + list(EXTRA))
    for legacy_id in ids:
        print(generate(legacy_id))
