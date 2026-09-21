<?php
/**
 * Ограничение частоты серверных мутаций списка сравнения.
 *
 * Простое скользящее окно для залогиненных пользователей: отметки времени
 * мутаций хранятся в user_meta; при превышении лимита за окно дальнейшие
 * мутации отклоняются с HTTP 429. Гостевые мутации выполняются чистым JS
 * в localStorage и на сервер не попадают, поэтому гость не ограничивается.
 *
 * @package RVN_Compare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Throttle мутаций (простое скользящее окно).
 */
final class RVN_Compare_Rate_Limit {

	/**
	 * Максимум разрешённых мутаций за окно.
	 */
	const MAX = 30;

	/**
	 * Размер окна в секундах.
	 */
	const WINDOW = 10;

	/**
	 * Мета-ключ для хранения отметок времени.
	 */
	const META_TS = 'rvn_compare_mut_ts';

	/**
	 * Регистрирует попытку мутации и отвечает, разрешена ли она.
	 *
	 * @return bool true — разрешено, false — превышен лимит (вернуть 429).
	 */
	public static function check() {
		// Гостевые мутации сервер не обрабатывает — не ограничиваем.
		if ( ! is_user_logged_in() ) {
			return true;
		}

		$user_id = get_current_user_id();
		$now     = time();

		$stamps = get_user_meta( $user_id, self::META_TS, true );
		if ( ! is_array( $stamps ) ) {
			$stamps = array();
		}

		// Оставляем только отметки внутри окна.
		$stamps = array_values( array_filter( $stamps, function ( $ts ) use ( $now ) {
			return ( $now - (int) $ts ) < self::WINDOW;
		} ) );

		$denied = count( $stamps ) >= self::MAX;

		// Регистрируем и эту попытку (в т.ч. отклонённую — усиливает блок).
		$stamps[] = $now;
		update_user_meta( $user_id, self::META_TS, $stamps );

		return ! $denied;
	}
}
