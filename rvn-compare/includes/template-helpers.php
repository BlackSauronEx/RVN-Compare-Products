<?php
/**
 * Глобальные функции-хелперы шаблонов таблицы.
 *
 * Выносятся в отдельный файл, чтобы шаблоны (templates/*.php) не содержали
 * бизнес-логики, а только вызывали эти маленькие функции. Подключается
 * один раз в bootstrap — до любых рендеров таблицы.
 *
 * @package RVN_Compare
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'rvn_compare_buy_button' ) ) {
	/**
	 * Печатает кнопку действия товара в заданном слоте таблицы.
	 *
	 * Режимы (R4-03, §6.3 живого ТЗ):
	 *  - 'buy'       — стандартная кнопка «Купить» / «Выбрать вариант»;
	 *  - 'shortcode' — свои шорткоды из настройки buy_shortcodes (пусто = кнопка);
	 *  - 'hidden'    — ничего не выводим.
	 *
	 * @param WC_Product|null $product   Товар.
	 * @param int             $id        ID товара.
	 * @param string          $mode      'buy' | 'shortcode' | 'hidden'.
	 * @param bool            $inherit   «Наследовать стили темы»: кнопке даётся
	 *                                   класс .rvn-compare-buy--theme вместо фирменных стилей.
	 * @return void
	 */
	function rvn_compare_buy_button( $product, $id, $mode, $inherit = false ) {
		if ( 'hidden' === $mode ) {
			return;
		}

		if ( 'shortcode' === $mode ) {
			$design     = (array) RVN_Compare_Settings::instance()->get( 'design', array() );
			$shortcodes = isset( $design['behavior']['buy_shortcodes'] ) ? (array) $design['behavior']['buy_shortcodes'] : array();
			if ( ! empty( $shortcodes ) ) {
				// Чужие шорткоды пользователя — выводим как есть.
				foreach ( $shortcodes as $shortcode ) {
					echo do_shortcode( wp_unslash( (string) $shortcode ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- результат шорткода.
				}
				return;
			}
			// Пустой список — откатываемся к стандартной кнопке.
		}

		$url = function_exists( 'get_permalink' ) ? get_permalink( (int) $id ) : '';

		$class = 'rvn-compare-buy';
		if ( $inherit ) {
			// Стили берутся из темы (WooCommerce-кнопка); фирменные стили не применяются.
			$class .= ' rvn-compare-buy--theme button alt wp-element-button';
		}

		if ( $product instanceof WC_Product && $product->is_type( 'variable' ) ) {
			printf(
				'<a class="%s" href="%s">%s</a>',
				esc_attr( $class ),
				esc_url( $url ),
				esc_html__( 'Выбрать вариант', 'rvn-compare' )
			);
			return;
		}

		printf(
			'<a class="%s" href="%s">%s</a>',
			esc_attr( $class ),
			esc_url( $url ? add_query_arg( 'add-to-cart', (int) $id, $url ) : '' ),
			esc_html__( 'Купить', 'rvn-compare' )
		);
	}
}

if ( ! function_exists( 'rvn_compare_add_product_button' ) ) {
	/**
	 * Печатает кнопку «Добавить товар» (открывает модал с поиском, R3-01).
	 *
	 * Считаем, что у пользователя есть «умение» менять список (сам список
	 * мутируется тем же механизмом, что и toggle), поэтому кнопка печатается
	 * всем. Работу модала берёт на себя compare.js (data-rvn-compare-add-open).
	 *
	 * @param string       $label      Текст кнопки (по умолчанию из i18n-перевода).
	 * @param string|array $extra_class Дополнительные CSS-классы.
	 * @return void
	 */
	function rvn_compare_add_product_button( $label = '', $extra_class = '' ) {
		$classes = array( 'rvn-compare-add-open' );
		if ( $extra_class ) {
			$classes[] = is_array( $extra_class ) ? implode( ' ', array_filter( $extra_class ) ) : (string) $extra_class;
		}
		echo sprintf(
			'<button type="button" class="%1$s" data-rvn-compare-add-open>%2$s</button>',
			esc_attr( implode( ' ', array_filter( $classes ) ) ),
			esc_html( $label ? $label : __( 'Добавить товар', 'rvn-compare' ) )
		);
	}
}
