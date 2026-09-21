<?php
/**
 * Дизайн элементов и кнопок (§6.4 живого ТЗ, RD-02).
 *
 * Единая точка для конструктора кнопок и тостов:
 *  - дефолты (из §11 + утверждённые SVG/тексты R2-05, R4-02, R4-04);
 *  - SVG-санитайзер (whitelist через DOMDocument, см. §12);
 *  - чтение слитой конфигурации (сохранённое + дефолты);
 *  - генерация инлайн-CSS для фронтенда;
 *  - данные для wp_localize_script (тосты).
 *
 * Настройки хранятся в rvn_compare_settings['button_styles'] и
 * rvn_compare_settings['toast_styles']; тексты кнопок/тостов — в плоских
 * ключах button_text/button_added_text/counter_button_text/toast_*_text
 * (исторически из Шагов 1–8), поэтому они сюда не дублируются.
 *
 * @package RVN_Compare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Конструктор кнопок/тостов.
 */
final class RVN_Compare_Design {

	/**
	 * Единственный экземпляр.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Запрещает прямое создание.
	 */
	private function __construct() {}

	/**
	 * Возвращает единственный экземпляр.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Режимы кнопок (6): что показывать внутри кнопки.
	 *
	 * @return string[]
	 */
	public function button_modes() {
		return array( 'icon_text', 'text_icon', 'icon', 'text', 'bare', 'hidden' );
	}

	/**
	 * Дефолтные стили кнопок (из §11 живого ТЗ).
	 *
	 * @return array
	 */
	public function button_defaults() {
		$n = array(
			'bg'           => '#ffffff',
			'color'        => '#1f2937',
			'border'       => '#e2e8f0',
			'border_width' => 1,
			'radius'       => 10,
			'font_size'    => 14,
			'font_weight'  => 500,
			'padding'      => '8px 14px',
		);
		$h = array(
			'bg'     => '#f8fafc',
			'color'  => '#2563eb',
			'border' => '#cbd5e1',
		);

		return array(
			'compare' => array(
				'mode'           => 'icon_text',
				'svg'            => $this->icon_compare(),
				'badge_position' => '',
				'class'          => '',
				'normal'         => $n,
				'hover'          => $h,
			),
			'added'   => array(
				'mode'           => 'icon_text',
				'svg'            => $this->icon_added(),
				'badge_position' => '',
				'class'          => '',
				'normal'         => array_merge( $n, array( 'color' => '#2563eb', 'border' => '#2563eb' ) ),
				'hover'          => array_merge( $h, array( 'bg' => '#eff6ff' ) ),
			),
			'counter' => array(
				'mode'           => 'icon_text',
				'svg'            => $this->icon_counter(),
				'badge_position' => 'right',
				'class'          => '',
				'normal'         => $n,
				'hover'          => $h,
			),
		);
	}

	/**
	 * Дефолтные настройки тостов (§6.4 + §11).
	 *
	 * @return array
	 */
	public function toast_defaults() {
		$base = array(
			'icon'             => '',
			'icon_position'    => 'left',
			'position_desktop' => 'bottom-center',
			'position_mobile'  => 'bottom-center',
			'progress'         => '1',
			'bg'               => '#ffffff',
			'color'            => '#1f2937',
			'border'           => '#e2e8f0',
			'accent'           => '#2563eb',
		);

		return array(
			'added'   => $base,
			'removed' => $base,
			'cleared' => $base,
			'limit'   => $base,
		);
	}

	/**
	 * Позиции тостов (десктоп и мобайл).
	 *
	 * @return string[]
	 */
	public function toast_positions() {
		return array( 'top-left', 'top-center', 'top-right', 'bottom-left', 'bottom-center', 'bottom-right' );
	}

	/**
	 * Иконка счётчика-меню (утверждена R2-05 / R4-02).
	 *
	 * @return string
	 */
	public function icon_counter() {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="18" rx="1.5"></rect><rect x="14" y="3" width="7" height="18" rx="1.5"></rect></svg>';
	}

	/**
	 * Иконка кнопки «Сравнить» (утверждена R2-05 / R4-02).
	 *
	 * @return string
	 */
	public function icon_compare() {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>';
	}

	/**
	 * Иконка кнопки «Уже в сравнении» (утверждена R2-05 / R4-02).
	 *
	 * @return string
	 */
	public function icon_added() {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
	}

	/**
	 * Санитизирует SVG (whitelist тегов/атрибутов) или пропускает короткий
	 * текстовый значок (эмодзи). Всё прочее → пустая строка.
	 *
	 * @param string $svg Сырой SVG (или эмодзи).
	 * @return string
	 */
	public function svg_sanitize( $svg ) {
		$svg = trim( (string) $svg );
		if ( '' === $svg ) {
			return '';
		}

		if ( 0 !== strpos( $svg, '<svg' ) ) {
			// Не SVG: разрешаем только короткий «значок» (эмодзи) без разметки.
			$len = function_exists( 'mb_strlen' ) ? mb_strlen( $svg, 'UTF-8' ) : strlen( $svg );
			if ( $len <= 32 && ! preg_match( '/[<>"\'\\\\]/', $svg ) ) {
				return wp_kses_post( $svg );
			}
			return '';
		}

		if ( ! class_exists( 'DOMDocument' ) ) {
			return '';
		}

		$allowed_tags = array(
			'g'         => array(),
			'defs'      => array(),
			'title'     => array(),
			'svg'       => array( 'xmlns', 'width', 'height', 'viewbox', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin' ),
			'rect'      => array( 'x', 'y', 'width', 'height', 'rx', 'ry', 'fill', 'stroke', 'stroke-width' ),
			'line'      => array( 'x1', 'y1', 'x2', 'y2', 'stroke', 'stroke-width', 'stroke-linecap' ),
			'polyline'  => array( 'points', 'fill', 'stroke', 'stroke-width', 'stroke-linejoin' ),
			'polygon'   => array( 'points', 'fill', 'stroke', 'stroke-width', 'stroke-linejoin' ),
			'path'      => array( 'd', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin' ),
			'circle'    => array( 'cx', 'cy', 'r', 'fill', 'stroke', 'stroke-width' ),
			'ellipse'   => array( 'cx', 'cy', 'rx', 'ry', 'fill', 'stroke', 'stroke-width' ),
		);

		$prev = libxml_use_internal_errors( true );
		$doc  = new DOMDocument( '1.0', 'UTF-8' );
		$ok   = $doc->loadXML( $svg );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		if ( ! $ok || ! $doc->documentElement || 'svg' !== strtolower( $doc->documentElement->nodeName ) ) {
			return '';
		}

		$elements = $doc->getElementsByTagName( '*' );
		for ( $i = $elements->length - 1; $i >= 0; $i-- ) {
			$el = $elements->item( $i );
			if ( ! $el instanceof DOMElement ) {
				continue;
			}
			$tag = strtolower( $el->nodeName );

			// Убираем запрещённые теги (script, foreignObject, ...) целиком.
			if ( ! isset( $allowed_tags[ $tag ] ) ) {
				$el->parentNode->removeChild( $el );
				continue;
			}

			// Убираем лишние атрибуты.
			$allowed_attrs = $allowed_tags[ $tag ];
			$attrs         = $el->attributes;
			for ( $j = $attrs->length - 1; $j >= 0; $j-- ) {
				$attr = $attrs->item( $j );
				if ( ! in_array( strtolower( $attr->nodeName ), $allowed_attrs, true ) ) {
					$el->removeAttribute( $attr->nodeName );
				}
			}
		}

		return (string) $doc->saveXML( $doc->documentElement );
	}

	/**
	 * Слитая конфигурация кнопок (сохранённое поверх дефолтов).
	 *
	 * @return array
	 */
	public function buttons() {
		$saved = (array) RVN_Compare_Settings::instance()->get( 'button_styles', array() );
		$def   = $this->button_defaults();

		$out = array();
		foreach ( $def as $key => $defaults ) {
			$s = isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ? $saved[ $key ] : array();
			$merged = array(
				'mode'           => isset( $s['mode'] ) && in_array( $s['mode'], $this->button_modes(), true ) ? $s['mode'] : $defaults['mode'],
				'svg'            => isset( $s['svg'] ) ? $this->svg_sanitize( $s['svg'] ) : $defaults['svg'],
				'badge_position' => isset( $s['badge_position'] ) && in_array( $s['badge_position'], array( '', 'left', 'right', 'top' ), true ) ? $s['badge_position'] : $defaults['badge_position'],
				'class'          => isset( $s['class'] ) ? sanitize_html_class( $s['class'] ) : $defaults['class'],
				'normal'         => isset( $s['normal'] ) && is_array( $s['normal'] ) ? wp_parse_args( $s['normal'], $defaults['normal'] ) : $defaults['normal'],
				'hover'          => isset( $s['hover'] ) && is_array( $s['hover'] ) ? wp_parse_args( $s['hover'], $defaults['hover'] ) : $defaults['hover'],
			);
			$out[ $key ] = $merged;
		}

		return $out;
	}

	/**
	 * Слитая конфигурация тостов.
	 *
	 * @return array
	 */
	public function toasts() {
		$saved = (array) RVN_Compare_Settings::instance()->get( 'toast_styles', array() );
		$def   = $this->toast_defaults();

		$out = array();
		foreach ( $def as $key => $defaults ) {
			$s = isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ? $saved[ $key ] : array();
			$out[ $key ] = wp_parse_args( $s, $defaults );
		}

		return $out;
	}

	/**
	 * Собирает внутренности кнопки (иконка + текст) по режиму.
	 *
	 * @param string $key   compare | added | counter.
	 * @param string $label Текст кнопки.
	 * @return string
	 */
	public function button_inner_html( $key, $label ) {
		$buttons = $this->buttons();
		$cfg     = isset( $buttons[ $key ] ) ? $buttons[ $key ] : array();
		$mode    = isset( $cfg['mode'] ) ? $cfg['mode'] : 'icon_text';

		$icon = '';
		if ( in_array( $mode, array( 'icon_text', 'text_icon', 'icon' ), true ) && ! empty( $cfg['svg'] ) ) {
			$icon = '<span class="rvn-compare-button__icon">' . $cfg['svg'] . '</span>'; // SVG санитизирован.
		}

		$text = '';
		if ( in_array( $mode, array( 'icon_text', 'text_icon', 'text' ), true ) ) {
			$text = '<span class="rvn-compare-button__label">' . esc_html( $label ) . '</span>';
		}

		if ( 'text_icon' === $mode ) {
			return $text . $icon;
		}
		return $icon . $text;
	}

	/**
	 * Собирает внутренности кнопки-счётчика (иконка + текст + бейдж).
	 *
	 * @param string $label Текст.
	 * @param string $count Число (строка) для бейджа.
	 * @return string
	 */
	public function counter_inner_html( $label, $count = '0' ) {
		$buttons = $this->buttons();
		$cfg     = isset( $buttons['counter'] ) ? $buttons['counter'] : array();
		$mode    = isset( $cfg['mode'] ) ? $cfg['mode'] : 'icon_text';
		$badge   = isset( $cfg['badge_position'] ) ? $cfg['badge_position'] : 'right';

		$icon = '';
		if ( in_array( $mode, array( 'icon_text', 'text_icon', 'icon' ), true ) && ! empty( $cfg['svg'] ) ) {
			$icon = '<span class="rvn-compare-button__icon">' . $cfg['svg'] . '</span>';
		}

		$text = '';
		if ( in_array( $mode, array( 'icon_text', 'text_icon', 'text' ), true ) ) {
			$text = '<span class="rvn-compare-counter-button__label">' . esc_html( $label ) . '</span>';
		}

		$badge_html = '<span class="rvn-compare-counter-button__badge rvn-compare-counter-button__badge--' . esc_attr( $badge ) . '" data-rvn-compare-count>' . esc_html( $count ) . '</span>';

		if ( 'left' === $badge ) {
			$inner = $badge_html . ( 'text_icon' === $mode ? $text . $icon : $icon . $text );
		} else {
			$inner = ( 'text_icon' === $mode ? $text . $icon : $icon . $text ) . $badge_html;
		}

		return $inner;
	}

	/**
	 * Генерирует CSS-блок для кнопки.
	 *
	 * @param string $selector Селектор.
	 * @param array  $cfg      Конфигурация.
	 * @param bool   $hover    Ховер-ли это правила.
	 * @return string
	 */
	private function button_css_block( $selector, $cfg, $hover ) {
		$n = $hover ? $cfg['hover'] : $cfg['normal'];

		$rules  = 'background:' . $n['bg'] . ';';
		$rules .= 'color:' . $n['color'] . ';';
		$rules .= 'border-color:' . $n['border'] . ';';
		if ( ! $hover ) {
			$rules .= 'border-width:' . absint( $cfg['normal']['border_width'] ) . 'px;';
			$rules .= 'border-style:solid;';
			$rules .= 'border-radius:' . absint( $cfg['normal']['radius'] ) . 'px;';
			$rules .= 'font-size:' . absint( $cfg['normal']['font_size'] ) . 'px;';
			$rules .= 'font-weight:' . absint( $cfg['normal']['font_weight'] ) . ';';
			$rules .= 'padding:' . $this->safe_padding( $cfg['normal']['padding'] ) . ';';
		}

		return $selector . '{' . $rules . '}';
	}

	/**
	 * Оставляет padding только вида "Npx" или "Npx Mpx".
	 *
	 * @param string $padding Значение.
	 * @return string
	 */
	private function safe_padding( $padding ) {
		$padding = trim( (string) $padding );
		if ( preg_match( '/^\d+px( \d+px)?$/', $padding ) ) {
			return $padding;
		}
		return '8px 14px';
	}

	/**
	 * Полный инлайн-CSS элементов (кнопки + тосты) для фронтенда.
	 *
	 * @return string
	 */
	public function elements_css() {
		$b = $this->buttons();

		$css  = $this->button_css_block( '.rvn-compare-button', $b['compare'], false );
		$css .= $this->button_css_block( '.rvn-compare-button:hover', $b['compare'], true );
		$css .= $this->button_css_block( '.rvn-compare-button.is-added', $b['added'], false );
		$css .= $this->button_css_block( '.rvn-compare-button.is-added:hover', $b['added'], true );
		$css .= $this->button_css_block( '.rvn-compare-counter-button', $b['counter'], false );
		$css .= $this->button_css_block( '.rvn-compare-counter-button:hover', $b['counter'], true );

		// Режим «bare» — без фона и рамки (ссылка).
		$css .= '.rvn-compare-button.rvn-compare-button--bare,.rvn-compare-counter-button.rvn-compare-button--bare{background:transparent;border-color:transparent;box-shadow:none;}';

		foreach ( $this->toasts() as $kind => $t ) {
			$sel = '.rvn-compare-toast[data-kind="' . $kind . '"]';
			$css .= $sel . '{background:' . $t['bg'] . ';color:' . $t['color'] . ';border-color:' . $t['border'] . ';border-left-color:' . $t['accent'] . ';}';
		}

		return $css;
	}

	/**
	 * Данные тостов для фронтенд-JS (позиции, прогресс, иконка).
	 *
	 * @return array
	 */
	public function elements_data() {
		$toasts = array();
		foreach ( $this->toasts() as $kind => $t ) {
			$toasts[ $kind ] = array(
				'positionDesktop' => $t['position_desktop'],
				'positionMobile'  => $t['position_mobile'],
				'progress'        => ( '1' === (string) $t['progress'] ),
				'icon'            => $t['icon'] ? $this->svg_sanitize( $t['icon'] ) : '',
				'iconPosition'    => $t['icon_position'],
			);
		}

		return $toasts;
	}
}
