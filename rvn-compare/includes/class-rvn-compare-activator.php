<?php
/**
 * Активатор плагина.
 *
 * Выполняется один раз при активации: записывает дефолтные настройки
 * (если их ещё нет) и создаёт страницу сравнения со слагом rvn-compare.
 *
 * Не полагается на автозагрузчик/plugins_loaded: файл сам подключает
 * нужные классы, т.к. активация может выполняться в момент, когда
 * основная инициализация ещё не отработала.
 *
 * @package RVN_Compare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Логика активации плагина.
 */
final class RVN_Compare_Activator {

	/**
	 * Действия при активации плагина.
	 *
	 * @return void
	 */
	public static function activate() {
		require_once RVN_COMPARE_DIR . 'includes/class-rvn-compare-settings.php';
		require_once RVN_COMPARE_DIR . 'includes/class-rvn-compare-page-manager.php';

		$settings = RVN_Compare_Settings::instance();

		// Дефолтные настройки — только если опции ещё не было.
		if ( false === get_option( RVN_Compare_Settings::OPTION, false ) ) {
			$settings->replace( $settings->defaults() );
		}

		// Страница сравнения: найдём существующую или создадим новую.
		RVN_Compare_Page_Manager::instance()->ensure_page();
	}
}
