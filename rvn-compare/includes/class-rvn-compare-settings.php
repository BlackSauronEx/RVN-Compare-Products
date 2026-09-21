<?php
/**
 * Единая точка чтения/записи настроек плагина.
 *
 * Все настройки хранятся в одном option-массиве rvn_compare_settings
 * (см. §8 живого ТЗ): один источник данных, дефолты + merge + санитизация
 * в одном месте. Это удобно и для будущего экспорта/миграций.
 *
 * @package RVN_Compare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Настройки плагина: дефолты, чтение, сохранение, санитизация.
 */
final class RVN_Compare_Settings {

	/**
	 * Единственный экземпляр.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Slug опции, в которой хранится весь массив настроек.
	 */
	const OPTION = 'rvn_compare_settings';

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
	 * Возвращает дефолтные настройки «из коробки».
	 *
	 * Текстовые дефолты (тексты кнопок и тостов) зависят от языка сайта:
	 * русская локаль получает русские тексты, все остальные — английские
	 * (см. §5 живого ТЗ).
	 *
	 * @return array
	 */
	public function defaults() {
		$is_ru       = 'ru' === substr( determine_locale(), 0, 2 );
		$defaults    = array(
			'compare_page_id'                  => '',
			'auto_insert_table'                => '1',
			'max_items_total'                  => 50,
			'max_items_per_context'            => 12,
			'breakpoint_tablet'                => 1024,
			'breakpoint_mobile'                => 768,
			'columns_desktop'                  => 5,
			'columns_tablet'                   => 3,
			'columns_mobile'                   => 2,
			'accent_color'                     => '#2563eb',
			'animation_speed'                  => 300,
			'toast_duration'                   => 3200,
			'archive_button_position'          => 'after_add_to_cart',
			'single_button_position'           => 'after_add_to_cart',
			'highlight_differences'            => '1',
			'hide_empty_rows'                  => '0',
			'show_only_differences_toggle'     => '1',
			'collapse_groups'                  => '1',
			'groups_default_state'             => 'expanded',
			'show_stock'                       => '1',
			'include_subcats'                  => '1',
			'custom_attributes'                => '1',
			'admin_capability'                 => 'manage_options',
			'button_text'                      => $is_ru ? 'Сравнить' : 'Compare',
			'button_added_text'                => $is_ru ? 'Уже в сравнении' : 'In compare list',
			'counter_button_text'              => $is_ru ? 'Сравнение' : 'Compare',
			'clear_text'                       => $is_ru ? 'Очистить всё' : 'Clear all',
			'toast_added_text'                 => $is_ru ? 'Товар добавлен в список сравнения' : 'Product added to compare list',
			'toast_removed_text'               => $is_ru ? 'Товар удалён из сравнения' : 'Product removed from compare list',
			'toast_cleared_text'               => $is_ru ? 'Список сравнения очищен' : 'Compare list cleared',
			'toast_limit_text'                 => $is_ru ? 'Достигнут максимум товаров в сравнении' : 'Compare list limit reached',
			'clear_confirm_text'               => $is_ru ? 'Очистить список сравнения?' : 'Clear the compare list?',
			'excluded_products'                => array(),
			'field_groups'                     => array(
				'basic' => $is_ru ? 'Основное' : 'General',
				'dimensions' => $is_ru ? 'Вес и размеры' : 'Weight and dimensions',
				'specs' => $is_ru ? 'Характеристики' : 'Specifications',
			),
			'fields'                           => array(),
			'category_groups'                  => array(),
			'acf_meta_fields'                  => array(),
			'design'                           => array(),
			'button_styles'                    => array(),
			'toast_styles'                     => array(),
			'uninstall'                        => array(
				'delete_settings' => 0,
				'delete_user_lists' => 0,
				'delete_page' => 0,
			),
		);

		return $defaults;
	}

	/**
	 * Возвращает сохранённые настройки, объединённые с дефолтами.
	 *
	 * Сохранённые значения всегда поверх дефолтов — так новые ключи,
	 * добавленные в новых версиях плагина, не теряют значения.
	 *
	 * @return array
	 */
	public function all() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, $this->defaults() );
	}

	/**
	 * Возвращает одно значение настройки по ключу.
	 *
	 * @param string $key      Ключ настройки.
	 * @param mixed  $fallback Значение, если настройка не найдена.
	 *
	 * @return mixed
	 */
	public function get( $key, $fallback = '' ) {
		$all = $this->all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Обновляет опцию настроек целиком.
	 *
	 * @param array $value Новый массив настроек.
	 *
	 * @return bool
	 */
	public function replace( $value ) {
		if ( ! is_array( $value ) ) {
			return false;
		}
		return update_option( self::OPTION, $value, false );
	}

	/**
	 * Сохраняет (с санитизацией) настройки из данных формы админки.
	 *
	 * Сохраняются только известные ключи; неизвестные игнорируются.
	 * Значения приводятся к типам из дефолтов, числа — через absint.
	 *
	 * @param array $raw «Сырые» данные формы ($_POST-массив настроек).
	 *
	 * @return array Сохранённый (санитизированный) массив.
	 */
	public function save_from_request( $raw ) {
		$current   = $this->all();
		$sanitized = array();

		/*
		 * Числовые ключи — неотрицательные целые.
		 */
		$int_keys = array(
			'max_items_total',
			'max_items_per_context',
			'breakpoint_tablet',
			'breakpoint_mobile',
			'columns_desktop',
			'columns_tablet',
			'columns_mobile',
			'animation_speed',
			'toast_duration',
		);

		foreach ( $int_keys as $key ) {
			$sanitized[ $key ] = isset( $raw[ $key ] ) ? absint( $raw[ $key ] ) : absint( $current[ $key ] );
		}

		/*
		 * Переключатели («галочки») — '1' если выставлены, иначе '0'.
		 */
		$bool_keys = array(
			'auto_insert_table',
			'highlight_differences',
			'hide_empty_rows',
			'show_only_differences_toggle',
			'collapse_groups',
			'show_stock',
			'include_subcats',
			'custom_attributes',
		);
		foreach ( $bool_keys as $key ) {
			$sanitized[ $key ] = isset( $raw[ $key ] ) && $raw[ $key ] ? '1' : '0';
		}

		/*
		 * Текстовые поля — sanitize_text_field.
		 */
		$text_keys = array(
			'button_text',
			'button_added_text',
			'counter_button_text',
			'clear_text',
			'clear_confirm_text',
			'toast_added_text',
			'toast_removed_text',
			'toast_cleared_text',
			'toast_limit_text',
		);
		foreach ( $text_keys as $key ) {
			$sanitized[ $key ] = isset( $raw[ $key ] ) ? sanitize_text_field( wp_unslash( $raw[ $key ] ) ) : $current[ $key ];
		}

		/*
		 * Допустимые значения из ограниченного набора (whitelist).
		 */
		$positions = array(
			'after_add_to_cart',
			'before_add_to_cart',
			'above_title',
			'below_title',
			'overlay_tl',
			'overlay_tr',
			'overlay_bl',
			'overlay_br',
			'disabled',
		);
		$sanitized['archive_button_position'] = in_array(
			isset( $raw['archive_button_position'] ) ? sanitize_key( $raw['archive_button_position'] ) : '',
			$positions,
			true
		) ? sanitize_key( $raw['archive_button_position'] ) : $current['archive_button_position'];

		$sanitized['single_button_position'] = in_array(
			isset( $raw['single_button_position'] ) ? sanitize_key( $raw['single_button_position'] ) : '',
			$positions,
			true
		) ? sanitize_key( $raw['single_button_position'] ) : $current['single_button_position'];

		$caps = array( 'manage_options', 'manage_woocommerce' );
		$sanitized['admin_capability'] = in_array(
			isset( $raw['admin_capability'] ) ? sanitize_key( $raw['admin_capability'] ) : '',
			$caps,
			true
		) ? sanitize_key( $raw['admin_capability'] ) : $current['admin_capability'];

		$states = array( 'expanded', 'collapsed' );
		$sanitized['groups_default_state'] = in_array(
			isset( $raw['groups_default_state'] ) ? sanitize_key( $raw['groups_default_state'] ) : '',
			$states,
			true
		) ? sanitize_key( $raw['groups_default_state'] ) : $current['groups_default_state'];

		/*
		 * Цвет акцента — sanitize_hex_color.
		 */
		$sanitized['accent_color'] = isset( $raw['accent_color'] ) && sanitize_hex_color( $raw['accent_color'] )
			? sanitize_hex_color( $raw['accent_color'] )
			: $current['accent_color'];

		/*
		 * ID страницы сравнения.
		 */
		$sanitized['compare_page_id'] = isset( $raw['compare_page_id'] ) ? absint( $raw['compare_page_id'] ) : absint( $current['compare_page_id'] );

		/*
		 * Сложные структуры на этом шаге не редактируются из формы —
		 * переносим текущие сохранённые значения как есть.
		 */
		$carry = array(
			'excluded_products',
			'field_groups',
			'fields',
			'category_groups',
			'acf_meta_fields',
			'design',
			'button_styles',
			'toast_styles',
			'uninstall',
		);
		foreach ( $carry as $key ) {
			$sanitized[ $key ] = isset( $current[ $key ] ) ? $current[ $key ] : array();
		}

		/*
		 * Финальный merge c дефолтами и запись.
		 */
		$merged = wp_parse_args( $sanitized, $this->defaults() );
		$this->replace( $merged );

		return $merged;
	}

	/**
	 * Возвращает числовые лимиты (глобальный и на контекст).
	 *
	 * @return array{total:int, context:int}
	 */
	public function limits() {
		return array(
			'total'   => (int) $this->get( 'max_items_total', 50 ),
			'context' => (int) $this->get( 'max_items_per_context', 12 ),
		);
	}
}
