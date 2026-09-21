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
	 *  - 'shortcode' — свой шорткод из настройки buy_shortcode (пусто = кнопка);
	 *  - 'hidden'    — ничего не выводим.
	 *
	 * @param WC_Product|null $product Товар.
	 * @param int             $id      ID товара.
	 * @param string          $mode    'buy' | 'shortcode' | 'hidden'.
	 * @return void
	 */
	function rvn_compare_buy_button( $product, $id, $mode ) {
		if ( 'hidden' === $mode ) {
			return;
		}

		if ( 'shortcode' === $mode ) {
			$custom = (string) RVN_Compare_Settings::instance()->get( 'buy_shortcode', '' );
			if ( $custom ) {
				// Чужой шорткод пользователя — выводим как есть.
				echo do_shortcode( wp_unslash( $custom ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- результат шорткода.
				return;
			}
			// Пустой шорткод — откатываемся к стандартной кнопке.
		}

		$url = function_exists( 'get_permalink' ) ? get_permalink( (int) $id ) : '';

		if ( $product instanceof WC_Product && $product->is_type( 'variable' ) ) {
			printf(
				'<a class="rvn-compare-buy" href="%s">%s</a>',
				esc_url( $url ),
				esc_html__( 'Выбрать вариант', 'rvn-compare' )
			);
			return;
		}

		printf(
			'<a class="rvn-compare-buy" href="%s">%s</a>',
			esc_url( $url ? add_query_arg( 'add-to-cart', (int) $id, $url ) : '' ),
			esc_html__( 'Купить', 'rvn-compare' )
		);
	}
}
