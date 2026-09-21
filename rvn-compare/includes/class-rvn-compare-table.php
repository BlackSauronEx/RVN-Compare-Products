<?php
/**
 * Построение таблицы сравнения.
 *
 * Пайплайн (R4-09, §4.4 живого ТЗ):
 *   entrypoint → context (категории/группы) → product → fields → template.
 *
 * Класс собирает структуру (вкладки, товары, группы, ряды), а шаблоны
 * (templates/compare-table.php) рисуют её. Логика различий и пустых
 * значений — здесь, вне шаблонов.
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
	 * @param string $group   Слаг единственной вкладки (необязательно).
	 * @param string $class   Дополнительный CSS-класс.
	 * @return string
	 */
	public function render( $ids_raw = '', $group = '', $class = '' ) {
		$ids = $this->resolve_ids( $ids_raw );

		if ( empty( $ids ) ) {
			// Пустое состояние: список пуст (части empty-state, §9 п.12).
			return $this->render_empty_state( $class );
		}

		// Если задана одна вкладка (шорткод group=) — рендерим только её состав.
		if ( '' !== trim( (string) $group ) ) {
			$ids  = $this->filter_by_group( $ids, $group );
			$tabs = empty( $ids ) ? array() : array( sanitize_key( $group ) => array(
				'label' => $this->group_label( $group ),
				'ids'   => $ids,
			) );
		} else {
			$tabs = RVN_Compare_Categories::instance()->build_tabs( $ids );
		}

		if ( empty( $tabs ) ) {
			return $this->render_empty_state( $class );
		}

		$data = array(
			'tabs'   => $tabs,
			'active' => array_keys( $tabs )[0],
			'class'  => $class,
		);

		return $this->render_template( $data );
	}

	/**
	 * Рендерит только одну вкладку (используется REST /table для AJAX).
	 *
	 * @param int[]  $ids     Список ID товаров вкладки.
	 * @param string $tab_key Ключ вкладки.
	 * @return string
	 */
	public function render_tab_html( $ids, $tab_key ) {
		return $this->render_table_parts( $ids, $tab_key );
	}

	/**
	 * Собирает структуру данных таблицы (для шаблонов и возможного REST).
	 *
	 * @param int[]  $ids     ID товаров (порядок = порядок колонок).
	 * @param string $tab_key Ключ активной вкладки.
	 * @return array
	 */
	public function build_structure( $ids, $tab_key = '' ) {
		$products = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( (int) $id );
			if ( $product instanceof WC_Product ) {
				$products[ (int) $id ] = $product;
			}
		}

		$rows = RVN_Compare_Fields::instance()->rows( array_keys( $products ) );

		// Группировка рядов по группам в порядке их первого появления.
		$groups = array();
		foreach ( $rows as $row ) {
			$gkey = $row['field']['group'];
			if ( ! isset( $groups[ $gkey ] ) ) {
				$groups[ $gkey ] = array();
			}
			$groups[ $gkey ][] = $row;
		}

		// Применяем «Только различия» / «скрывать пустые» на уровне структуры
		// (клиент может фильтровать повторно; сервер даёт базовый вид).
		$settings = RVN_Compare_Settings::instance();

		if ( '1' === (string) $settings->get( 'hide_empty_rows', '0' ) ) {
			foreach ( $groups as $gkey => $group_rows ) {
				$groups[ $gkey ] = array_values( array_filter( $group_rows, function ( $row ) {
					return ! empty( trim( implode( '', $row['values'] ) ) );
				} ) );
			}
		}
		$groups = array_filter( $groups );

		return array(
			'ids'      => array_keys( $products ),
			'products' => $products,
			'groups'   => $groups,
			'tab_key'  => $tab_key,
		);
	}

	/**
	 * Определяет массив ID для рендера (фикс-набор или актуальный список).
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
	 * Фильтрует ID товаров по ключу вкладки (для шорткода group=).
	 *
	 * @param int[]  $ids     ID товаров.
	 * @param string $group   Ключ вкладки (gN / catN / other).
	 * @return int[]
	 */
	private function filter_by_group( $ids, $group ) {
		$resolver = RVN_Compare_Categories::instance();
		$out      = array();

		foreach ( $ids as $id ) {
			if ( in_array( $group, $resolver->product_context_keys( $id ), true ) ) {
				$out[] = $id;
			}
		}

		return $out;
	}

	/**
	 * Название вкладки по её ключу.
	 *
	 * @param string $key Ключ вкладки.
	 * @return string
	 */
	private function group_label( $key ) {
		return RVN_Compare_Categories::instance()->context_label( $key );
	}

	/**
	 * Рендерит шаблон таблицы.
	 *
	 * @param array $data Данные таблицы.
	 * @return string
	 */
	private function render_template( $data ) {
		ob_start();

		$template = locate_template(
			array( 'rvn-compare/compare-table.php' )
		);

		if ( ! $template ) {
			$template = RVN_COMPARE_DIR . 'templates/compare-table.php';
		}

		include $template;

		return (string) ob_get_clean();
	}

	/**
	 * Рендерит внутренние части таблицы (для REST-подгрузки вкладок).
	 *
	 * @param int[]  $ids     ID товаров.
	 * @param string $tab_key Ключ вкладки.
	 * @return string
	 */
	private function render_table_parts( $ids, $tab_key ) {
		$structure = $this->build_structure( $ids, $tab_key );

		ob_start();
		$template = locate_template( array( 'rvn-compare/parts.php' ) );
		if ( ! $template ) {
			$template = RVN_COMPARE_DIR . 'templates/parts.php';
		}
		include $template;

		return (string) ob_get_clean();
	}

	/**
	 * Рендерит пустое состояние (список пуст).
	 *
	 * @param string $class Дополнительный CSS-класс.
	 * @return string
	 */
	private function render_empty_state( $class ) {
		$catalog = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';

		ob_start();

		$template = locate_template( array( 'rvn-compare/empty-state.php' ) );
		if ( ! $template ) {
			$template = RVN_COMPARE_DIR . 'templates/empty-state.php';
		}

		$catalog_url = $catalog;
		$class_attr  = $class;

		include $template;

		return (string) ob_get_clean();
	}
}
