<?php
/**
 * Построение таблицы сравнения.
 *
 * Пайплайн рендера (R4-09, §4.4 живого ТЗ):
 *   entrypoint → context (категории/группы) → product → fields → table.
 * Класс возвращает структуру данных, а шаблон (templates/) только рисует её.
 *
 * В этом шаге реализован каркас (обёртка + пустое состояние + счётчики);
 * полный резолвинг полей/групп/атрибутов добавляется в следующих шагах.
 *
 * @package RVN_Compare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Сборка структуры таблицы сравнения.
 */
final class RVN_Compare_Table {

	/**
	 * Единственный экземпляр.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Запрещает прямое создание (singleton).
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
	 * Рендерит таблицу сравнения.
	 *
	 * @param string $ids_raw Строка ID через запятую (для фикс-режима шорткода).
	 * @param string $group   Слаг единственной группы (необязательно).
	 * @param string $class   Дополнительный CSS-класс.
	 * @return string
	 */
	public function render( $ids_raw = '', $group = '', $class = '' ) {
		$ids = $this->resolve_ids( $ids_raw );

		if ( empty( $ids ) ) {
			// Пустое состояние: таблицу не рендерим (части empty-state, §9 п.12).
			return $this->render_empty_state( $class );
		}

		// Каркас контейнера таблицы; наполнение — в следующих шагах.
		$out  = '<div class="rvn-compare-table ' . esc_attr( $class ) . '" data-rvn-compare-table>';
		$out .= '<div class="rvn-compare-table__toolbar" data-rvn-compare-toolbar></div>';
		$out .= '<div class="rvn-compare-table__body" data-rvn-compare-body></div>';
		$out .= '</div>';

		return $out;
	}

	/**
	 * Определяет массив ID для рендера.
	 *
	 * Если передан фиксированный набор ids — используем его (посадочные),
	 * иначе берём актуальный список пользователя/гостя.
	 *
	 * @param string $ids_raw Строка ID через запятую.
	 * @return int[]
	 */
	private function resolve_ids( $ids_raw ) {
		if ( '' !== trim( (string) $ids_raw ) ) {
			$ids = array_map( 'absint', array_filter( (array) explode( ',', $ids_raw ) ) );

			return array_values( array_filter( $ids, function ( $id ) {
				return $id && wc_get_product( $id );
			} ) );
		}

		return RVN_Compare_Storage::instance()->get_items();
	}

	/**
	 * Рендерит пустое состояние (список пуст).
	 *
	 * @param string $class Дополнительный CSS-класс.
	 * @return string
	 */
	private function render_empty_state( $class ) {
		$catalog = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';

		$html  = '<div class="rvn-compare-empty ' . esc_attr( $class ) . '">';
		$html .= '<p class="rvn-compare-empty__title">' . esc_html__( 'Список сравнения пуст', 'rvn-compare' ) . '</p>';
		if ( $catalog ) {
			$html .= '<a class="rvn-compare-empty__link" href="' . esc_url( $catalog ) . '">'
				. esc_html__( 'Перейти в каталог', 'rvn-compare' )
				. '</a>';
		}
		$html .= '</div>';

		return $html;
	}
}
