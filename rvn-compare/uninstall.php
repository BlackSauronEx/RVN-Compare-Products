<?php
/**
 * Очистка данных плагина при удалении.
 *
 * WordPress вызывает этот файл только при полном удалении плагина
 * (из списка «Плагины»), при условии что файл находится в корне плагина.
 * Гейт ABSPATH / WP_UNINSTALL_PLUGIN не даёт вызвать файл напрямую.
 *
 * Что удаляется — решает администратор чекбоксами в настройках
 * (§6.1 п.7, R2-20 живого ТЗ): настройки / списки сравнения пользователей /
 * созданная страница. По умолчанию всё ВЫКЛЮЧЕНО.
 *
 * @package RVN_Compare
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Читаем настройки до их возможного удаления.
$settings = get_option( 'rvn_compare_settings', array() );

if ( ! is_array( $settings ) ) {
	$settings = array();
}

$uninstall = isset( $settings['uninstall'] ) && is_array( $settings['uninstall'] )
	? $settings['uninstall']
	: array();

/*
 * 1. Удалить настройки плагина.
 */
if ( ! empty( $uninstall['delete_settings'] ) ) {
	delete_option( 'rvn_compare_settings' );
}

/*
 * 2. Удалить списки сравнения пользователей (user_meta).
 */
if ( ! empty( $uninstall['delete_user_lists'] ) ) {
	/*
	 * Прямого «удалить произвольный meta у всех пользователей» в ядре нет,
	 * поэтому удаляем точечно через запрос по мета-ключу.
	 */
	global $wpdb;

	$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->usermeta, // phpcs:ignore WordPress.VIP.DirectDatabaseQuery
		array(
			'meta_key' => 'rvn_compare_list', // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);

	// Чистим объектный кеш пользователей после прямого запроса.
	wp_cache_flush();
}

/*
 * 3. Удалить созданную страницу сравнения.
 *    Только если она была помечена плагином (не трогаем чужие страницы).
 */
if ( ! empty( $uninstall['delete_page'] ) && ! empty( $settings['compare_page_id'] ) ) {
	$page_id = absint( $settings['compare_page_id'] );
	$created = get_post_meta( $page_id, '_rvn_compare_created', true );

	if ( $created && $page_id && 'trash' !== get_post_status( $page_id ) ) {
		wp_delete_post( $page_id, true );
	}
}
