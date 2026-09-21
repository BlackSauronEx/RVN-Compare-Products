<?php
/**
 * Проверки совместимости и «гейт» запуска плагина.
 *
 * Отвечает за то, чтобы не выполнять логику плагина до того, как
 * окружение (в первую очередь WooCommerce) признано подходящим.
 * При невыполненных требованиях администратор видит понятное
 * уведомление, а сайт продолжает работать без фатальных ошибок.
 *
 * @package RVN_Compare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Проверяет окружение и решает, запускать ли основную логику плагина.
 */
final class RVN_Compare_Compatibility {

	/**
	 * Точка входа: если WooCommerce доступен — загружаем ядро,
	 * иначе показываем уведомление и не инициализируем логику.
	 *
	 * @return void
	 */
	public static function maybe_load() {
		if ( ! self::is_wc_active() ) {
			add_action( 'admin_notices', array( __CLASS__, 'wc_missing_notice' ) );
			return;
		}

		self::declare_hpos_compatibility();

		RVN_Compare_Registry::instance()->plugin()->run();
	}

	/**
	 * Проверяет, что WooCommerce установлен и загружен.
	 *
	 * @return bool
	 */
	public static function is_wc_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Декларирует совместимость с High-Performance Order Storage (HPOS).
	 *
	 * Плагин не хранит данных заказов, поэтому HPOS не влияет на его работу;
	 * декларация нужна, чтобы WooCommerce корректно отображал статус
	 * совместимости в разделе «Настройки > Дополнительно > Функции».
	 * Гард class_exists() защищает от вызова до загрузки WooCommerce.
	 *
	 * @return void
	 */
	public static function declare_hpos_compatibility() {
		if ( class_exists( 'Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				RVN_COMPARE_FILE,
				true
			);
		}
	}

	/**
	 * Уведомление администратору при неактивном WooCommerce.
	 *
	 * @return void
	 */
	public static function wc_missing_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		esc_html_e(
			'RVN Compare products for woocommerce требует установленный и активированный WooCommerce. Плагин не будет работать, пока WooCommerce не включён.',
			'rvn-compare'
		);
		echo '</p></div>';
	}
}
