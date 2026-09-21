<?php
/**
 * Управление ассетами плагина (стили и скрипты, фронтенд и админка).
 *
 * Подключает файлы только там, где они реально нужны. На фронтенде
 * используется ванильный JS (без jQuery) — см. §12 живого ТЗ.
 *
 * @package RVN_Compare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Регистрация и постановка в очередь ассетов.
 */
final class RVN_Compare_Assets {

	/**
	 * Единственный экземпляр.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Возвращает единственный экземпляр и вешает хуки ассетов.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			add_action( 'wp_enqueue_scripts', array( self::$instance, 'register_frontend' ) );
			add_action( 'admin_enqueue_scripts', array( self::$instance, 'register_admin' ) );
		}
		return self::$instance;
	}

	/**
	 * Регистрирует фронтенд-скрипты и стили + печатает инлайн-сниппет
	 * до отрисовки страницы (для корректной работы под page-cache).
	 *
	 * @return void
	 */
	public function register_frontend() {
		wp_register_style(
			'rvn-compare',
			RVN_COMPARE_URL . 'assets/css/frontend.css',
			array(),
			RVN_COMPARE_VERSION
		);

		wp_register_script(
			'rvn-compare',
			RVN_COMPARE_URL . 'assets/js/compare.js',
			array(),
			RVN_COMPARE_VERSION,
			true
		);

		// CSS загружаем всегда: кнопки сравнения могут встретиться на любой странице.
		wp_enqueue_style( 'rvn-compare' );

		// Лёгкий JS нужен везде (кнопки, счётчики).
		wp_enqueue_script( 'rvn-compare' );

		// Данные для JS по схеме §10 живого ТЗ (неймспейс window.rvnCompare).
		wp_localize_script(
			'rvn-compare',
			'rvnCompare',
			$this->frontend_data()
		);

		// Снапшот списка для быстрой коррекции до paint (page-cache safe).
		wp_add_inline_script(
			'rvn-compare',
			'window.rvnCompareState = ' . wp_json_encode( $this->frontend_state() ) . ';',
			'before'
		);

		// Инлайн-стили дизайна таблицы (CSS-переменные + object-fit), §6.3.
		wp_add_inline_style(
			'rvn-compare',
			RVN_Compare_Settings::instance()->design_css()
		);

		// Инлайн-стили элементов (кнопки/тосты), §6.4.
		wp_add_inline_style(
			'rvn-compare',
			RVN_Compare_Design::instance()->elements_css()
		);
	}

	/**
	 * Регистрирует скрипты и стили админки (только на экранах плагина).
	 *
	 * @param string $hook_suffix Суффикс хука текущего экрана админки.
	 * @return void
	 */
	public function register_admin( $hook_suffix ) {
		// Показываем админ-ассеты только на странице настроек плагина.
		if ( false === strpos( $hook_suffix, 'rvn-compare' ) && false === strpos( $hook_suffix, 'rvn_compare' ) ) {
			return;
		}

		wp_register_style(
			'rvn-compare-admin',
			RVN_COMPARE_URL . 'assets/css/admin.css',
			array(),
			RVN_COMPARE_VERSION
		);
		wp_enqueue_style( 'rvn-compare-admin' );

		wp_register_script(
			'rvn-compare-admin',
			RVN_COMPARE_URL . 'assets/js/admin.js',
			array(),
			RVN_COMPARE_VERSION,
			true
		);
		wp_enqueue_script( 'rvn-compare-admin' );

		wp_localize_script(
			'rvn-compare-admin',
			'rvnCompareAdmin',
			array(
				'confirmResetTab'  => __( 'Сбросить все значения на этой вкладке?', 'rvn-compare' ),
				'confirmResetAll'  => __( 'Сбросить все настройки плагина к значениям по умолчанию?', 'rvn-compare' ),
				'confirmResetPage' => __( 'Заменить содержимое страницы сравнения только шорткодом таблицы? Существующее содержимое будет удалено.', 'rvn-compare' ),
			)
		);
	}

	/**
	 * Возвращает данные, передаваемые во фронтенд-JS.
	 *
	 * @return array
	 */
	private function frontend_data() {
		$settings = RVN_Compare_Settings::instance();

		return array(
			'pageUrl'      => function_exists( 'get_permalink' ) ? (string) get_permalink( (int) $settings->get( 'compare_page_id', '' ) ) : '',
			'restUrl'      => esc_url_raw( rest_url( RVN_Compare_Rest::NS . '/' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'loggedIn'     => is_user_logged_in(),
			'limits'       => $settings->limits(),
			'count'        => count( RVN_Compare_Storage::instance()->get_items() ),
			'animationMs'  => (int) $settings->get( 'animation_speed', 300 ),
			'toastMs'      => (int) $settings->get( 'toast_duration', 3200 ),
			'toasts'       => RVN_Compare_Design::instance()->elements_data(),
			'accent'       => (string) $settings->get( 'accent_color', '#2563eb' ),
			'breakpoints'  => array(
				'tablet' => (int) $settings->get( 'breakpoint_tablet', 1024 ),
				'mobile' => (int) $settings->get( 'breakpoint_mobile', 768 ),
			),
			'columns'      => array(
				'desktop' => (int) $settings->get( 'columns_desktop', 5 ),
				'tablet'  => (int) $settings->get( 'columns_tablet', 3 ),
				'mobile'  => (int) $settings->get( 'columns_mobile', 2 ),
			),
			'floatingOffset' => (string) $settings->get( 'floating_offset', '0px' ),
			'photoFit'       => (string) $settings->get( 'photo_fit', 'contain' ),
			'archivePosition' => (string) $settings->get( 'archive_button_position', 'after_add_to_cart' ),
			'singlePosition'  => (string) $settings->get( 'single_button_position', 'after_add_to_cart' ),
			'excluded'        => RVN_Compare_Settings::instance()->excluded_products(),
			'i18n'    => array(
				'added'        => (string) $settings->get( 'toast_added_text' ),
				'removed'      => (string) $settings->get( 'toast_removed_text' ),
				'cleared'      => (string) $settings->get( 'toast_cleared_text' ),
				'limit'        => (string) $settings->get( 'toast_limit_text' ),
				'buttonLabel'  => (string) $settings->get( 'button_text' ),
				'addedLabel'   => (string) $settings->get( 'button_added_text' ),
				'clearConfirm' => (string) $settings->get( 'clear_confirm_text', __( 'Очистить список сравнения?', 'rvn-compare' ) ),
			),
		);
	}

	/**
	 * Возвращает стартовое состояние фронтенда (список ID).
	 *
	 * Для залогиненных — из user_meta, для гостей — пустой массив
	 * (их список лежит в localStorage и подхватывается на клиенте).
	 *
	 * @return array
	 */
	private function frontend_state() {
		return array(
			'items' => RVN_Compare_Storage::instance()->get_items(),
		);
	}
}
