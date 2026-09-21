<?php
/**
 * Plugin Name: RVN Compare products for woocommerce
 * Description: Сравнение товаров в стиле DNS / Ситилинк / Regard: список сравнения и отдельная страница с удобной таблицей.
 * Version: 0.1.0
 * Author: Revolen
 * Text Domain: rvn-compare
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * WC requires at least: 8.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package RVN_Compare
 */

defined( 'ABSPATH' ) || exit;

/*
 * Константы плагина.
 * RVN_COMPARE_VERSION — версия плагина (используется для cache-busting ассетов).
 * RVN_COMPARE_FILE    — абсолютный путь к bootstrap-файлу.
 * RVN_COMPARE_DIR     — абсолютный путь к папке плагина (без завершающего слэша).
 * RVN_COMPARE_URL     — публичный URL папки плагина (без завершающего слэша).
 * RVN_COMPARE_BASENAME — базовое имя плагина (rvn-compare/rvn-compare.php) для хук'-ов.
 */
define( 'RVN_COMPARE_VERSION', '0.1.0' );
define( 'RVN_COMPARE_FILE', __FILE__ );
define( 'RVN_COMPARE_DIR', plugin_dir_path( __FILE__ ) );
define( 'RVN_COMPARE_URL', plugin_dir_url( __FILE__ ) );
define( 'RVN_COMPARE_BASENAME', plugin_basename( __FILE__ ) );

/*
 * Реестр автоматической загрузки классов.
 *
 * Маппит человекочитаемое имя класса в относительный путь к файлу внутри /includes.
 * Формат ключа: короткое имя после префикса RVN_Compare_, например
 * 'Plugin' => 'class-rvn-compare-plugin.php'.
 *
 * Каждому классу плагина соответствует один файл — это делает структуру
 * предсказуемой и соответствует требованиям каталога WordPress.org.
 */
final class RVN_Compare_Registry {

	/** Единственный экземпляр (singleton). */
	private static $instance = null;

	/** Объект главного класса плагина. */
	public $plugin = null;

	/**
	 * Возвращает (и при необходимости создаёт) единственный экземпляр реестра.
	 *
	 * @return RVN_Compare_Registry
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Возвращает главный объект плагина, создавая его при первом обращении.
	 *
	 * @return RVN_Compare_Plugin
	 */
	public function plugin() {
		if ( null === $this->plugin ) {
			$this->plugin = new RVN_Compare_Plugin();
		}
		return $this->plugin;
	}

	/**
	 * Автозагрузчик классов плагина.
	 *
	 * Правило именования: класс RVN_Compare_Plugin лежит в
	 * includes/class-rvn-compare-plugin.php, RVN_Compare_Settings — в
	 * includes/class-rvn-compare-settings.php и т.д. Имя класса приводится
	 * к нижнему регистру, CamelCase разбивается дефисами.
	 *
	 * @param string $class Полное имя класса.
	 * @return void
	 */
	public static function autoload( $class ) {
		if ( 0 !== strpos( $class, 'RVN_Compare_' ) ) {
			return;
		}
		$short = strtolower( substr( $class, strlen( 'RVN_Compare_' ) ) );
		$short = preg_replace( '/_+/', '-', $short );
		$path  = RVN_COMPARE_DIR . 'includes/class-rvn-compare-' . $short . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}

	/**
	 * Инициализирует плагин: подключает ядро и запускает его.
	 *
	 * Проверка совместимости (WooCommerce активен) выполняется внутри
	 * RVN_Compare_Compatibility::maybe_load(), чтобы при невыполненных
	 * требованиях мы не инициализировали логику и не роняли сайт.
	 *
	 * @return void
	 */
	public static function boot() {
		require_once RVN_COMPARE_DIR . 'includes/class-rvn-compare-plugin.php';
		require_once RVN_COMPARE_DIR . 'includes/class-rvn-compare-compatibility.php';

		spl_autoload_register( array( __CLASS__, 'autoload' ) );

		RVN_Compare_Compatibility::maybe_load();
	}
}

add_action(
	'plugins_loaded',
	array( 'RVN_Compare_Registry', 'boot' ),
	20
);
