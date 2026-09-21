<?php
/**
 * Контракт хранения списка сравнения.
 *
 * Источник правды для списка:
 *  - залогиненные пользователи: user_meta 'rvn_compare_list';
 *  - гости:                      localStorage (на клиенте, см. assets/js/compare.js)
 *    — сервер работает с переданным клиентом массивом ID.
 *
 * Класс не только читает/сохраняет список, но и нормализует его:
 * отсекает несуществующие товары, ограничивает по лимитам и применяет
 * правила групп категорий (см. §8 живого ТЗ, R3-05).
 *
 * @package RVN_Compare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Хранилище списка сравнения.
 */
final class RVN_Compare_Storage {

	/**
	 * Ключ user_meta для списка залогиненного пользователя.
	 */
	const META_KEY = 'rvn_compare_list';

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
	 * Возвращает актуальный список ID товаров для текущего пользователя.
	 *
	 * Залогиненные: читаем user_meta и очищаем «умершие» товары.
	 * Гости: на сервере списка нет — возвращаем пустой массив.
	 *
	 * @return int[]
	 */
	public function get_items() {
		if ( ! is_user_logged_in() ) {
			return array();
		}

		$ids = get_user_meta( get_current_user_id(), self::META_KEY, true );
		if ( ! is_array( $ids ) ) {
			$ids = array();
		}

		return $this->normalize( $ids );
	}

	/**
	 * Сохраняет список товаров для текущего залогиненного пользователя.
	 *
	 * Для гостей ничего не делает — их список хранится в localStorage
	 * и на сервере не персистится.
	 *
	 * @param int[] $ids Массив ID товаров.
	 * @return void
	 */
	public function set_items( $ids ) {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$ids = $this->normalize( $ids );
		update_user_meta( get_current_user_id(), self::META_KEY, $ids );
	}

	/**
	 * Нормализует массив ID:
	 * - absint каждого элемента;
	 * - отбрасывает нули и несуществующие/нечитаемые товары;
	 * - убирает дубликаты, сохраняя порядок;
	 * - оставляет только залогиненного пользователя.
	 *
	 * @param int[] $ids Входной массив ID.
	 * @return int[]
	 */
	public function normalize( $ids ) {
		if ( ! is_array( $ids ) ) {
			return array();
		}

		$clean = array();
		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( ! $id || in_array( $id, $clean, true ) ) {
				continue;
			}
			// Санация «умерших» товаров: несуществующие пропускаются (self-healing).
			if ( ! wc_get_product( $id ) ) {
				continue;
			}
			$clean[] = $id;
		}

		return $clean;
	}

	/**
	 * Добавляет товар в список текущего пользователя с учётом лимитов.
	 *
	 * Если товар уже есть в списке — список не меняется (toggle повторного
	 * добавления обрабатывается клиентом до запроса).
	 *
	 * @param int $id ID товара.
	 * @return array{ok:bool, reason:string, items:int[]}
	 */
	public function add_item( $id ) {
		$id    = absint( $id );
		$items = $this->get_items();
		$limit = (int) RVN_Compare_Settings::instance()->get( 'max_items_total', 50 );

		if ( ! $id ) {
			return array( 'ok' => false, 'reason' => 'invalid', 'items' => $items );
		}
		if ( ! wc_get_product( $id ) ) {
			return array( 'ok' => false, 'reason' => 'missing', 'items' => $items );
		}
		if ( in_array( $id, $items, true ) ) {
			// Уже в списке — считаем успехом, состояние не меняется.
			return array( 'ok' => true, 'reason' => 'exists', 'items' => $items );
		}
		if ( count( $items ) >= $limit ) {
			return array( 'ok' => false, 'reason' => 'limit', 'items' => $items );
		}

		$items[] = $id;
		$this->set_items( $items );

		return array( 'ok' => true, 'reason' => 'added', 'items' => $this->get_items() );
	}

	/**
	 * Удаляет товар из списка текущего пользователя.
	 *
	 * @param int $id ID товара.
	 * @return int[] Актуальный список после удаления.
	 */
	public function remove_item( $id ) {
		$id    = absint( $id );
		$items = $this->get_items();

		$items = array_values( array_filter( $items, function ( $item ) use ( $id ) {
			return $item !== $id;
		} ) );

		$this->set_items( $items );

		return $this->get_items();
	}

	/**
	 * Полностью очищает список текущего пользователя.
	 *
	 * @return int[] Пустой массив.
	 */
	public function clear_items() {
		$this->set_items( array() );

		return array();
	}

	/**
	 * Сливает гостевой список со списком залогиненного пользователя.
	 *
	 * Порядок: сохранённый список пользователя + новые элементы гостя,
	 * затем дедупликация и обрезка «с конца» до глобального лимита.
	 * Используется при merge после логина (§8 живого ТЗ).
	 *
	 * @param int[] $guest_ids Гостевой список ID.
	 * @return int[] Итоговый (канонический) список.
	 */
	public function merge_guest( $guest_ids ) {
		if ( is_user_logged_in() ) {
			$items = $this->get_items();
			foreach ( (array) $guest_ids as $id ) {
				$id = absint( $id );
				if ( $id && ! in_array( $id, $items, true ) ) {
					$items[] = $id;
				}
			}
		} else {
			$items = array();
			foreach ( (array) $guest_ids as $id ) {
				$id = absint( $id );
				if ( $id && ! in_array( $id, $items, true ) ) {
					$items[] = $id;
				}
			}
		}

		$items = $this->normalize( $items );

		// Обрезка «с конца» до глобального лимита.
		$limit = (int) RVN_Compare_Settings::instance()->get( 'max_items_total', 50 );
		if ( count( $items ) > $limit ) {
			$items = array_slice( $items, 0, $limit );
		}

		$this->set_items( $items );

		return $this->get_items();
	}
}
