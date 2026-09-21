<?php
/**
 * Ядро / оркестратор плагина (singleton).
 *
 * Создаёт экземпляры всех сервисов и подключает их к хукам WordPress.
 * Каждый сервис — отдельный класс со своим файлом; ядро лишь собирает
 * их вместе, не содержа собственной бизнес-логики (см. §4.4 живого ТЗ).
 *
 * @package RVN_Compare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Главный контейнер плагина.
 */
final class RVN_Compare_Plugin {

	/**
	 * Единственный экземпляр плагина.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Slug текстового домена плагина.
	 */
	const TEXT_DOMAIN = 'rvn-compare';

	/**
	 * Возвращает (и при необходимости создаёт) единственный экземпляр.
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
	 * Инициализирует плагин: локализация, сервисы, хуки.
	 *
	 * @return void
	 */
	public function run() {
		// Файлы перевода подключаем сразу (мы уже внутри plugins_loaded).
		$this->load_textdomain();

		/*
		 * Сервисы в порядке, безопасном для регистрации их хуков.
		 * Каждый класс реализует статический instance() (singleton).
		 */
		$components = array(
			'RVN_Compare_Settings',
			'RVN_Compare_Page_Manager',
			'RVN_Compare_Storage',
			'RVN_Compare_Rest',
			'RVN_Compare_Assets',
			'RVN_Compare_Admin',
			'RVN_Compare_Design',
			'RVN_Compare_Shortcodes',
			'RVN_Compare_Buttons',
			'RVN_Compare_Categories',
			'RVN_Compare_Fields',
		);

		foreach ( $components as $class ) {
			$class::instance();
		}
	}

	/**
	 * Подключает файлы перевода плагина.
	 *
	 * Файлы переводов (rvn-compare-ru_RU.mo и т.д.) лежат в /languages.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			self::TEXT_DOMAIN,
			false,
			dirname( RVN_COMPARE_BASENAME ) . '/languages'
		);
	}
}
