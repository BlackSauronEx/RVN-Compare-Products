<?php
/**
 * Контексты сравнения: категории и группы категорий.
 *
 * Определяет, к каким «вкладкам» относится каждый товар (группа категорий,
 * отдельная категория или «Прочее»), и строит структуру вкладок таблицы.
 * Каждая категория может входить только в одну группу; товар из нескольких
 * категорий дублируется в нескольких вкладках (§6.2 п.3, R2-10/R2-24).
 *
 * @package RVN_Compare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Резолвер контекстов сравнения.
 */
final class RVN_Compare_Categories {

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
	 * Нормализованный список групп категорий из настроек.
	 *
	 * @return array[] Список ['name'=>string, 'cats'=>int[]].
	 */
	public function groups() {
		$raw = (array) RVN_Compare_Settings::instance()->get( 'category_groups', array() );
		$out = array();

		foreach ( $raw as $group ) {
			if ( isset( $group['name'], $group['cats'] ) ) {
				$out[] = array(
					'name' => (string) $group['name'],
					'cats' => array_values( array_filter( array_map( 'absint', (array) $group['cats'] ) ) ),
				);
			}
		}

		return $out;
	}

	/**
	 * Карта «ID категории → индекс группы».
	 *
	 * @return array
	 */
	public function group_cat_map() {
		$map = array();
		foreach ( $this->groups() as $index => $group ) {
			foreach ( $group['cats'] as $cat_id ) {
				$map[ $cat_id ] = $index;
			}
		}
		return $map;
	}

	/**
	 * Ключи вкладок, в которых участвует товар.
	 *
	 * Формат ключей: 'g{index}' — группа, 'cat{term_id}' — отдельная категория,
	 * 'other' — товар без категорий. Категории товара включают предков
	 * (get_category_ids), поэтому товар из подкатегории попадает и в группу,
	 * в которой состоит родительская рубрика (опция «включать подкатегории»
	 * применяется администратором на уровне состава группы).
	 *
	 * @param int $product_id ID товара.
	 * @return string[]
	 */
	public function product_context_keys( $product_id ) {
		$cat_map = $this->group_cat_map();
		$product = wc_get_product( (int) $product_id );

		$cats = $product ? array_map( 'absint', $product->get_category_ids() ) : array();
		if ( empty( $cats ) ) {
			return array( 'other' );
		}

		$keys = array();
		foreach ( $cats as $cat_id ) {
			$key = isset( $cat_map[ $cat_id ] ) ? 'g' . $cat_map[ $cat_id ] : 'cat' . $cat_id;
			if ( ! in_array( $key, $keys, true ) ) {
				$keys[] = $key;
			}
		}

		return empty( $keys ) ? array( 'other' ) : $keys;
	}

	/**
	 * Человекочитаемое название вкладки по её ключу.
	 *
	 * @param string $key Ключ вкладки.
	 * @return string
	 */
	public function context_label( $key ) {
		if ( 'other' === $key ) {
			return __( 'Прочее', 'rvn-compare' );
		}
		if ( 0 === strpos( $key, 'g' ) ) {
			$index  = (int) substr( $key, 1 );
			$groups = $this->groups();
			return isset( $groups[ $index ]['name'] ) ? $groups[ $index ]['name'] : $key;
		}
		if ( 0 === strpos( $key, 'cat' ) ) {
			$term_id = (int) substr( $key, 3 );
			$term    = get_term( $term_id );
			return ( $term && ! is_wp_error( $term ) ) ? (string) $term->name : (string) $term_id;
		}

		return $key;
	}

	/**
	 * Сохраняет список групп категорий целиком (CRUD-хелперы переиспользуют его).
	 *
	 * @param array $groups Нормализованный список групп.
	 * @return void
	 */
	public function save_groups( $groups ) {
		$settings = RVN_Compare_Settings::instance();
		$all      = $settings->all();
		$all['category_groups'] = $groups;
		$settings->replace( $all );
	}

	/**
	 * Санитизирует массив групп категорий из формы админки.
	 *
	 * Принимает как индексированный список ['name'=>..., 'cats'=>...],
	 * так и ассоциативную карту (index => группа, используется при
	 * drag&drop/переименовании). Пустые и невалидные отбрасываются.
	 *
	 * @param array $raw Сырые данные формы.
	 * @return array[]  Нормализованный список групп.
	 */
	public function sanitize_groups( $raw ) {
		$out = array();
		foreach ( (array) $raw as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			$name = isset( $group['name'] ) ? sanitize_text_field( wp_unslash( $group['name'] ) ) : '';
			$cats = isset( $group['cats'] ) ? $group['cats'] : array();
			$cats = array_values( array_filter( array_map( 'absint', (array) $cats ) ) );
			if ( '' === $name || empty( $cats ) ) {
				continue;
			}
			$out[] = array( 'name' => $name, 'cats' => $cats );
		}
		return $out;
	}

	/**
	 * Создаёт группу категорий из списка ID категорий.
	 *
	 * @param string $name Название группы.
	 * @param int[]  $cats ID категорий.
	 * @return bool Успех (false — название пустое или список пуст).
	 */
	public function create_group( $name, $cats ) {
		$name = trim( (string) $name );
		$cats = array_values( array_filter( array_map( 'absint', (array) $cats ) ) );

		if ( '' === $name || empty( $cats ) ) {
			return false;
		}

		$groups   = $this->groups();
		$groups[] = array( 'name' => $name, 'cats' => $cats );
		$this->save_groups( $groups );

		return true;
	}

	/**
	 * Удаляет группу категорий по индексу.
	 *
	 * @param int $index Индекс группы в сохранённом списке.
	 * @return void
	 */
	public function delete_group( $index ) {
		$groups = $this->groups();
		if ( isset( $groups[ $index ] ) ) {
			unset( $groups[ $index ] );
			$this->save_groups( array_values( $groups ) );
		}
	}

	/**
	 * Возвращает ID категорий, уже занятых какими-либо группами.
	 *
	 * @return int[]
	 */
	public function taken_category_ids() {
		$ids = array();
		foreach ( $this->group_cat_map() as $cat_id => $group_index ) {
			$ids[] = (int) $cat_id;
		}
		return $ids;
	}

	/**
	 * Строит структуру вкладок для набора товаров.
	 *
	 * Порядок: группы в порядке их создания, затем категории по алфавиту,
	 * вкладка «Прочее» — всегда последняя.
	 *
	 * @param int[] $ids Список ID товаров.
	 * @return array[] Ключ => ['label'=>string, 'ids'=>int[]].
	 */
	public function build_tabs( $ids ) {
		$tabs = array();

		// Группы в порядке создания.
		foreach ( array_keys( $this->groups() ) as $index ) {
			$key          = 'g' . $index;
			$tabs[ $key ] = array( 'label' => $this->context_label( $key ), 'ids' => array() );
		}

		$cat_tabs = array();
		$other    = array( 'label' => $this->context_label( 'other' ), 'ids' => array() );
		$has_other = false;

		foreach ( $ids as $id ) {
			foreach ( $this->product_context_keys( $id ) as $key ) {
				if ( 'other' === $key ) {
					$other['ids'][] = $id;
					$has_other      = true;
					continue;
				}
				if ( 0 === strpos( $key, 'g' ) ) {
					if ( ! isset( $tabs[ $key ] ) ) {
						$tabs[ $key ] = array( 'label' => $this->context_label( $key ), 'ids' => array() );
					}
					$tabs[ $key ]['ids'][] = $id;
				} else {
					if ( ! isset( $cat_tabs[ $key ] ) ) {
						$cat_tabs[ $key ] = array( 'label' => $this->context_label( $key ), 'ids' => array() );
					}
					$cat_tabs[ $key ]['ids'][] = $id;
				}
			}
		}

		// Категории — по алфавиту.
		uasort( $cat_tabs, function ( $a, $b ) {
			return strcasecmp( $a['label'], $b['label'] );
		} );

		$result = $tabs + $cat_tabs;
		if ( $has_other ) {
			$result['other'] = $other;
		}

		// Дедупликация ID внутри вкладки с сохранением порядка.
		foreach ( $result as $key => $tab ) {
			$result[ $key ]['ids'] = array_values( array_unique( array_map( 'absint', $tab['ids'] ) ) );
		}

		return $result;
	}
}
