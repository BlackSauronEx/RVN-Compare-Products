<?php
/**
 * Авто-вставка кнопки сравнения на карточках и странице товара.
 *
 * Маппит настройку «позиция кнопки» в хуки WooCommerce:
 *  - архив/каталог (loop item);
 *  - страница товара (single product summary).
 *
 * Позиции (см. §6.1 живого ТЗ): после/перед «Купить», над/под заголовком,
 * 4× overlay поверх изображения (best effort), отключено.
 *
 * @package RVN_Compare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Вставка кнопок сравнения в стандартные точки WooCommerce.
 */
final class RVN_Compare_Buttons {

	/**
	 * Единственный экземпляр.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Возвращает единственный экземпляр и вешает регистрацию хуков.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			add_action( 'init', array( self::$instance, 'register_auto_insert' ) );
		}
		return self::$instance;
	}

	/**
	 * Регистрирует хуки вывода кнопок по настройкам позиций.
	 *
	 * @return void
	 */
	public function register_auto_insert() {
		$settings = RVN_Compare_Settings::instance();

		$archive = (string) $settings->get( 'archive_button_position', 'after_add_to_cart' );
		$single  = (string) $settings->get( 'single_button_position', 'after_add_to_cart' );

		foreach ( $this->positions_map( 'archive', $archive ) as $hook ) {
			add_action( $hook[0], array( $this, 'render_archive_button' ), $hook[1] );
		}
		foreach ( $this->positions_map( 'single', $single ) as $hook ) {
			add_action( $hook[0], array( $this, 'render_single_button' ), $hook[1] );
		}
	}

	/**
	 * Карта «позиция → [хук, приоритет]» для контекста.
	 *
	 * @param string $context  'archive' | 'single'.
	 * @param string $position Ключ позиции из настроек.
	 * @return array
	 */
	private function positions_map( $context, $position ) {
		$map = array(
			'archive' => array(
				'after_add_to_cart'  => array( array( 'woocommerce_after_shop_loop_item', 15 ) ),
				'before_add_to_cart' => array( array( 'woocommerce_after_shop_loop_item', 8 ) ),
				'above_title'        => array( array( 'woocommerce_before_shop_loop_item_title', 12 ) ),
				'below_title'        => array( array( 'woocommerce_shop_loop_item_title', 15 ) ),
				'overlay_tl'         => array( array( 'woocommerce_before_shop_loop_item_title', 12 ) ),
				'overlay_tr'         => array( array( 'woocommerce_before_shop_loop_item_title', 12 ) ),
				'overlay_bl'         => array( array( 'woocommerce_before_shop_loop_item_title', 12 ) ),
				'overlay_br'         => array( array( 'woocommerce_before_shop_loop_item_title', 12 ) ),
			),
			'single'  => array(
				'after_add_to_cart'  => array( array( 'woocommerce_after_add_to_cart_form', 10 ) ),
				'before_add_to_cart' => array( array( 'woocommerce_before_add_to_cart_form', 10 ) ),
				'above_title'        => array( array( 'woocommerce_single_product_summary', 3 ) ),
				'below_title'        => array( array( 'woocommerce_single_product_summary', 7 ) ),
				'overlay_tl'         => array( array( 'woocommerce_before_single_product_summary', 11 ) ),
				'overlay_tr'         => array( array( 'woocommerce_before_single_product_summary', 11 ) ),
				'overlay_bl'         => array( array( 'woocommerce_before_single_product_summary', 11 ) ),
				'overlay_br'         => array( array( 'woocommerce_before_single_product_summary', 11 ) ),
			),
		);

		return isset( $map[ $context ][ $position ] ) ? $map[ $context ][ $position ] : array();
	}

	/**
	 * Выводит кнопку в карточке товара (архив/каталог).
	 *
	 * @return void
	 */
	public function render_archive_button() {
		$product = $this->current_product();
		if ( ! $product || ! $this->is_allowed( $product ) ) {
			return;
		}

		$position = (string) RVN_Compare_Settings::instance()->get( 'archive_button_position', 'after_add_to_cart' );

		echo self::button_html( $this->canonical_id( $product ), $position, 'archive' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML собран через esc_*.
	}

	/**
	 * Выводит кнопку на странице товара.
	 *
	 * @return void
	 */
	public function render_single_button() {
		$product = $this->current_product();
		if ( ! $product || ! $this->is_allowed( $product ) ) {
			return;
		}

		$position = (string) RVN_Compare_Settings::instance()->get( 'single_button_position', 'after_add_to_cart' );

		echo self::button_html( $this->canonical_id( $product ), $position, 'single' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML собран через esc_*.
	}

	/**
	 * Собирает HTML-разметку кнопки сравнения.
	 *
	 * Единая фабрика для авто-вставки и шорткода: кнопка всегда имеет
	 * data-rvn-compare-add — по нему JS (compare.js) определяет состояние
	 * «Сравнить»/«Уже в сравнении» и обрабатывает клик (toggle).
	 *
	 * @param int    $id         Канонический ID товара.
	 * @param string $position   Ключ позиции (для CSS-класса и data-атрибута).
	 * @param string $context    'archive' | 'single' | 'shortcode'.
	 * @param string $extra_class Дополнительный CSS-класс.
	 * @return string
	 */
	public static function button_html( $id, $position = '', $context = '', $extra_class = '' ) {
		$settings = RVN_Compare_Settings::instance();

		$classes = array( 'rvn-compare-button' );
		if ( $position ) {
			$classes[] = 'rvn-compare-button--' . sanitize_html_class( $position );
		}
		if ( $context ) {
			$classes[] = 'rvn-compare-button--ctx-' . sanitize_html_class( $context );
		}
		if ( 0 === strpos( (string) $position, 'overlay' ) ) {
			$classes[] = 'rvn-compare-button--overlay';
		}
		if ( $extra_class ) {
			// Разрешаем несколько пользовательских классов через пробел.
			foreach ( preg_split( '/\s+/', trim( $extra_class ) ) as $cls ) {
				if ( $cls ) {
					$classes[] = sanitize_html_class( $cls );
				}
			}
		}
		$class = implode( ' ', array_unique( $classes ) );

		$position_attr = $position ? ' data-rvn-compare-position="' . esc_attr( $position ) . '"' : '';

		return sprintf(
			'<button type="button" class="%1$s" data-rvn-compare-add="%2$d" data-rvn-compare-state=""%3$s>' .
			'<span class="rvn-compare-button__label">%4$s</span></button>',
			esc_attr( $class ),
			(int) $id,
			$position_attr,
			esc_html( (string) $settings->get( 'button_text', __( 'Сравнить', 'rvn-compare' ) ) )
		);
	}

	/**
	 * Возвращает текущий товар WooCommerce из глобального контекста.
	 *
	 * @return WC_Product|null
	 */
	private function current_product() {
		global $product;

		if ( $product instanceof WC_Product ) {
			return $product;
		}

		if ( function_exists( 'wc_get_product' ) && function_exists( 'get_the_ID' ) ) {
			$fallback = wc_get_product( get_the_ID() );
			return $fallback instanceof WC_Product ? $fallback : null;
		}

		return null;
	}

	/**
	 * Канонический ID товара: вариация → родитель (R2-16).
	 *
	 * @param WC_Product $product Товар.
	 * @return int
	 */
	private function canonical_id( $product ) {
		if ( $product->is_type( 'variation' ) && $product->get_parent_id() ) {
			return (int) $product->get_parent_id();
		}
		return (int) $product->get_id();
	}

	/**
	 * Решает, показывать ли кнопку для товара.
	 *
	 * Скрываем для: не-публичных товаров и товаров из списка исключений.
	 * Дополнительно доступен фильтр rvn_compare_button_allowed.
	 *
	 * @param WC_Product $product Товар.
	 * @return bool
	 */
	private function is_allowed( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return false;
		}
		if ( 'publish' !== $product->get_status() ) {
			return false;
		}

		$id = $this->canonical_id( $product );
		if ( in_array( $id, $this->excluded_ids(), true ) ) {
			return false;
		}

		return (bool) apply_filters( 'rvn_compare_button_allowed', true, $product, $id );
	}

	/**
	 * Список ID исключённых товаров из настроек.
	 *
	 * @return int[]
	 */
	private function excluded_ids() {
		$excluded = (array) RVN_Compare_Settings::instance()->get( 'excluded_products', array() );

		return array_values( array_filter( array_map( 'absint', $excluded ) ) );
	}
}
