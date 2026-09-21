<?php
/**
 * Шорткоды плагина.
 *
 * Все 5 шорткодов из §7 живого ТЗ. Шорткоды не содержат бизнес-логики —
 * только собирают аргументы и передают рендер дальше. Каждый из них
 * поддерживает аргумент class (свой CSS-класс).
 *
 * @package RVN_Compare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Регистрация шорткодов.
 */
final class RVN_Compare_Shortcodes {

	/**
	 * Единственный экземпляр.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Возвращает единственный экземпляр и регистрирует шорткоды.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			add_action( 'init', array( self::$instance, 'register' ) );
		}
		return self::$instance;
	}

	/**
	 * Реестр шорткодов: тег => описание (используется и в справке).
	 *
	 * @return array
	 */
	public static function registry() {
		return array(
			'rvn-compare-table'          => array(
				'description' => __( 'Таблица сравнения товаров.', 'rvn-compare' ),
				'args'        => 'class, group, ids',
				'example'     => '[rvn-compare-table class="my-table" group="g0" ids="12,34,56"]',
			),
			'rvn-compare-counter-button' => array(
				'description' => __( 'Кнопка «Сравнение» с живым счётчиком (для меню/шапки).', 'rvn-compare' ),
				'args'        => 'class, link (0/1), layout, badge (0/1), url',
				'example'     => '[rvn-compare-counter-button link="1" badge="1"]',
			),
			'rvn-compare-button'         => array(
				'description' => __( 'Кнопка «Сравнить» для конкретного товара.', 'rvn-compare' ),
				'args'        => 'id, class, layout',
				'example'     => '[rvn-compare-button id="123"]',
			),
			'rvn-compare-counter'        => array(
				'description' => __( 'Живой счётчик товаров в сравнении.', 'rvn-compare' ),
				'args'        => 'class, text, text_position (before/after), url',
				'example'     => '[rvn-compare-counter text="Товаров: " text_position="before"]',
			),
			'rvn-compare-clear'          => array(
				'description' => __( 'Кнопка «Очистить всё» с подтверждением.', 'rvn-compare' ),
				'args'        => 'class, text, confirm (0/1)',
				'example'     => '[rvn-compare-clear text="Очистить" confirm="1"]',
			),
		);
	}

	/**
	 * Регистрирует все шорткоды плагина.
	 *
	 * @return void
	 */
	public function register() {
		$map = array(
			'rvn-compare-table'          => array( $this, 'render_table' ),
			'rvn-compare-counter-button' => array( $this, 'render_counter_button' ),
			'rvn-compare-button'         => array( $this, 'render_button' ),
			'rvn-compare-counter'        => array( $this, 'render_counter' ),
			'rvn-compare-clear'          => array( $this, 'render_clear' ),
		);

		foreach ( $map as $tag => $callback ) {
			add_shortcode( $tag, $callback );
		}
	}

	/**
	 * [rvn-compare-table] — таблица сравнения.
	 *
	 * Аргументы: class, group (одна вкладка), ids (фиксированный набор).
	 *
	 * @param array|string $atts Атрибуты шорткода.
	 * @return string
	 */
	public function render_table( $atts ) {
		$atts = shortcode_atts(
			array(
				'class' => '',
				'group' => '',
				'ids'   => '',
			),
			(array) $atts,
			'rvn-compare-table'
		);

		return RVN_Compare_Table::instance()->render(
			$atts['ids'],
			$atts['group'],
			$atts['class']
		);
	}

	/**
	 * [rvn-compare-counter-button] — кнопка-счётчик для меню/шапки.
	 *
	 * Аргументы: class, link (0/1), layout, badge, url.
	 *
	 * @param array|string $atts Атрибуты шорткода.
	 * @return string
	 */
	public function render_counter_button( $atts ) {
		$atts = shortcode_atts(
			array(
				'class'  => '',
				'link'   => '1',
				'layout' => '',
				'badge'  => '1',
				'url'    => '',
			),
			(array) $atts,
			'rvn-compare-counter-button'
		);

		$settings = RVN_Compare_Settings::instance();
		$design   = RVN_Compare_Design::instance();
		$cfg      = $design->buttons();
		$counter  = isset( $cfg['counter'] ) ? $cfg['counter'] : array();
		$mode     = isset( $counter['mode'] ) ? $counter['mode'] : 'icon_text';
		$badge    = '1' === (string) $atts['badge'];

		$text   = (string) $settings->get( 'counter_button_text', __( 'Сравнение', 'rvn-compare' ) );
		$url    = $atts['url'] ? $atts['url'] : (string) get_permalink( (int) $settings->get( 'compare_page_id', '' ) );
		$linked = '0' !== (string) $atts['link'];

		$extra = trim( $atts['class'] );
		$class = trim( 'rvn-compare-counter-button rvn-compare-button--' . $mode . ' ' . $extra );
		if ( isset( $counter['class'] ) && $counter['class'] ) {
			$class .= ' ' . $counter['class'];
		}

		$inner = $badge ? $design->counter_inner_html( $text, '0' ) : $design->button_inner_html( 'counter', $text );

		if ( $linked && $url ) {
			return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . $inner . '</a>';
		}

		return '<span class="' . esc_attr( $class ) . '">' . $inner . '</span>';
	}

	/**
	 * [rvn-compare-button] — кнопка «Сравнить» товара.
	 *
	 * Аргументы: class, id (0 — авто: $product→get_the_ID()), layout.
	 *
	 * @param array|string $atts Атрибуты шорткода.
	 * @return string
	 */
	public function render_button( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'     => '0',
				'class'  => '',
				'layout' => '',
			),
			(array) $atts,
			'rvn-compare-button'
		);

		$id = absint( $atts['id'] );
		if ( ! $id ) {
			if ( function_exists( 'wc_get_product' ) && function_exists( 'get_the_ID' ) ) {
				$product = wc_get_product( get_the_ID() );
				$id      = $product ? $product->get_id() : 0;
			}
		}

		if ( ! $id ) {
			// Гард: без товара кнопку не рисуем (Шаг 7 живого ТЗ).
			return '';
		}

		$design = RVN_Compare_Design::instance();
		$cfg    = $design->buttons();
		$mode   = isset( $cfg['compare']['mode'] ) ? $cfg['compare']['mode'] : 'icon_text';
		$class  = trim( 'rvn-compare-button rvn-compare-button--' . $mode . ' ' . $atts['class'] );

		return sprintf(
			'<button type="button" class="%1$s" data-rvn-compare-add="%2$d" data-rvn-compare-state="">%3$s</button>',
			esc_attr( $class ),
			esc_attr( $id ),
			$design->button_inner_html( 'compare', (string) RVN_Compare_Settings::instance()->get( 'button_text', __( 'Сравнить', 'rvn-compare' ) ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG санитизирован, текст esc_html.
		);
	}

	/**
	 * [rvn-compare-counter] — живой счётчик товаров.
	 *
	 * Аргументы: class, text, text_position, url.
	 *
	 * @param array|string $atts Атрибуты шорткода.
	 * @return string
	 */
	public function render_counter( $atts ) {
		$atts = shortcode_atts(
			array(
				'class'         => '',
				'text'          => '',
				'text_position' => 'after',
				'url'           => '',
			),
			(array) $atts,
			'rvn-compare-counter'
		);

		$class   = trim( 'rvn-compare-counter ' . $atts['class'] );
		$count   = '<span class="rvn-compare-counter__value" data-rvn-compare-count>0</span>';
		$text    = $atts['text'] ? '<span class="rvn-compare-counter__text">' . esc_html( $atts['text'] ) . '</span>' : '';
		$inner   = ( 'before' === $atts['text_position'] ) ? $text . $count : $count . $text;
		$content = $inner;

		if ( $atts['url'] ) {
			$content = '<a href="' . esc_url( $atts['url'] ) . '">' . $inner . '</a>';
		}

		return '<span class="' . esc_attr( $class ) . '">' . $content . '</span>';
	}

	/**
	 * [rvn-compare-clear] — кнопка «Очистить всё».
	 *
	 * Аргументы: class, text, confirm (0/1).
	 *
	 * @param array|string $atts Атрибуты шорткода.
	 * @return string
	 */
	public function render_clear( $atts ) {
		$atts = shortcode_atts(
			array(
				'class'   => '',
				'text'    => '',
				'confirm' => '1',
			),
			(array) $atts,
			'rvn-compare-clear'
		);

		$settings = RVN_Compare_Settings::instance();
		$text     = $atts['text'] ? $atts['text'] : (string) $settings->get( 'clear_text', __( 'Очистить всё', 'rvn-compare' ) );
		$class    = trim( 'rvn-compare-clear ' . $atts['class'] );
		$need     = '1' === (string) $atts['confirm'];

		return sprintf(
			'<button type="button" class="%1$s" data-rvn-compare-clear%2$s>%3$s</button>',
			esc_attr( $class ),
			$need ? '' : ' data-rvn-compare-no-confirm="1"',
			esc_html( $text )
		);
	}
}
