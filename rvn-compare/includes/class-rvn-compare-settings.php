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
			'floating_offset'                  => '0px',
			'photo_fit'                        => 'contain',
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
		$sanitized = $current;

		$tab = isset( $raw['tab'] ) ? sanitize_key( (string) $raw['tab'] ) : 'general';
		$keys_by_tab = $this->keys_by_tab();

		/*
		 * Неизвестная вкладка (или вкладка без полей) — ничего не меняем.
		 */
		if ( ! isset( $keys_by_tab[ $tab ] ) || empty( $keys_by_tab[ $tab ] ) ) {
			return $current;
		}

		/*
		 * Спецификации санитизации по типу поля.
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

		$bool_keys = array(
			'auto_insert_table',
			'show_stock',
			'highlight_differences',
			'hide_empty_rows',
			'show_only_differences_toggle',
			'collapse_groups',
			'include_subcats',
			'custom_attributes',
		);

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

		$caps   = array( 'manage_options', 'manage_woocommerce' );
		$states = array( 'expanded', 'collapsed' );

		/*
		 * Обрабатываем только ключи активной вкладки; остальные настройки
		 * (других вкладок) сохраняются без изменений — это исключает сброс
		 * их значений при сохранении одной вкладки.
		 */
		foreach ( $keys_by_tab[ $tab ] as $key ) {
			if ( in_array( $key, $int_keys, true ) ) {
				$sanitized[ $key ] = isset( $raw[ $key ] ) ? absint( $raw[ $key ] ) : absint( $current[ $key ] );
				continue;
			}

			if ( in_array( $key, $bool_keys, true ) ) {
				// Снятая галочка не приходит в POST — явно пишем '0'.
				$sanitized[ $key ] = ( isset( $raw[ $key ] ) && $raw[ $key ] ) ? '1' : '0';
				continue;
			}

			if ( in_array( $key, $text_keys, true ) ) {
				$sanitized[ $key ] = isset( $raw[ $key ] ) ? sanitize_text_field( wp_unslash( $raw[ $key ] ) ) : $current[ $key ];
				continue;
			}

			if ( 'archive_button_position' === $key || 'single_button_position' === $key ) {
				$value = isset( $raw[ $key ] ) ? sanitize_key( $raw[ $key ] ) : '';
				$sanitized[ $key ] = in_array( $value, $positions, true ) ? $value : $current[ $key ];
				continue;
			}

			if ( 'admin_capability' === $key ) {
				$value = isset( $raw[ $key ] ) ? sanitize_key( $raw[ $key ] ) : '';
				$sanitized[ $key ] = in_array( $value, $caps, true ) ? $value : $current[ $key ];
				continue;
			}

			if ( 'groups_default_state' === $key ) {
				$value = isset( $raw[ $key ] ) ? sanitize_key( $raw[ $key ] ) : '';
				$sanitized[ $key ] = in_array( $value, $states, true ) ? $value : $current[ $key ];
				continue;
			}

			if ( 'accent_color' === $key ) {
				$sanitized[ $key ] = ( isset( $raw[ $key ] ) && sanitize_hex_color( $raw[ $key ] ) )
					? sanitize_hex_color( $raw[ $key ] )
					: $current[ $key ];
				continue;
			}

			if ( 'floating_offset' === $key ) {
				$sanitized[ $key ] = isset( $raw[ $key ] )
					? sanitize_text_field( wp_unslash( $raw[ $key ] ) )
					: $current[ $key ];
				continue;
			}

			if ( 'photo_fit' === $key ) {
				$value = isset( $raw[ $key ] ) ? sanitize_key( $raw[ $key ] ) : '';
				$sanitized[ $key ] = in_array( $value, array( 'contain', 'cover' ), true ) ? $value : $current[ $key ];
				continue;
			}

			if ( 'compare_page_id' === $key ) {
				$sanitized[ $key ] = isset( $raw[ $key ] ) ? absint( $raw[ $key ] ) : absint( $current[ $key ] );
				continue;
			}
		}

		/*
		 * Пишем полный массив: изменились только поля активной вкладки.
		 */
		$this->replace( $sanitized );

		return $sanitized;
	}

	/**
	 * Карта «вкладка админки → сохраняемые ею ключи настроек».
	 *
	 * Настройки, не перечисленные в активной вкладке, при сохранении
	 * не трогаются — это защищает данные соседних вкладок от сброса.
	 * При добавлении нового поля настройки его ключ нужно внести сюда.
	 *
	 * @return array
	 */
	private function keys_by_tab() {
		return array(
			'general'  => array(
				'compare_page_id',
				'auto_insert_table',
				'floating_offset',
				'photo_fit',
				'max_items_total',
				'max_items_per_context',
				'breakpoint_tablet',
				'breakpoint_mobile',
				'columns_desktop',
				'columns_tablet',
				'columns_mobile',
				'animation_speed',
				'toast_duration',
				'accent_color',
				'archive_button_position',
				'single_button_position',
				'admin_capability',
				'groups_default_state',
				'show_stock',
			),
			'fields'   => array(
				'highlight_differences',
				'hide_empty_rows',
				'show_only_differences_toggle',
				'collapse_groups',
				'include_subcats',
				'custom_attributes',
			),
			'elements' => array(
				'button_text',
				'button_added_text',
				'counter_button_text',
				'clear_text',
				'clear_confirm_text',
				'toast_added_text',
				'toast_removed_text',
				'toast_cleared_text',
				'toast_limit_text',
			),
			'design'   => array(),
			'help'     => array(),
		);
	}


	/*
	 * ---- Исключения товаров (структурированный список). ----
	 */

	/**
	 * Возвращает карту исключённых товаров: id => ['archive'=>0/1,'single'=>0/1].
	 *
	 * Толерантен к legacy-формату (плоский массив ID — считаем оба контекста).
	 *
	 * @return array
	 */
	public function excluded_products() {
		$raw = $this->get( 'excluded_products', array() );
		$map = array();

		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return $map;
		}

		foreach ( $raw as $key => $value ) {
			if ( is_array( $value ) ) {
				$map[ absint( $key ) ] = array(
					'archive' => ( ! empty( $value['archive'] ) ) ? 1 : 0,
					'single'  => ( ! empty( $value['single'] ) ) ? 1 : 0,
				);
			} else {
				// Legacy: плоский список ID.
				$map[ absint( $value ) ] = array( 'archive' => 1, 'single' => 1 );
			}
		}

		return $map;
	}

	/**
	 * Добавляет/обновляет исключение товара.
	 *
	 * @param int  $id      ID товара.
	 * @param bool $archive Исключить на карточках.
	 * @param bool $single  Исключить на странице товара.
	 * @return void
	 */
	public function set_excluded( $id, $archive, $single ) {
		$map = $this->excluded_products();
		$map[ absint( $id ) ] = array(
			'archive' => ( $archive ? 1 : 0 ),
			'single'  => ( $single ? 1 : 0 ),
		);
		$this->persist_excluded( $map );
	}

	/**
	 * Удаляет исключение товара.
	 *
	 * @param int $id ID товара.
	 * @return void
	 */
	public function remove_excluded( $id ) {
		$map = $this->excluded_products();
		unset( $map[ absint( $id ) ] );
		$this->persist_excluded( $map );
	}

	/**
	 * Сохраняет карту исключений в опцию настроек.
	 *
	 * @param array $map Карта исключений.
	 * @return void
	 */
	private function persist_excluded( $map ) {
		$all = $this->all();
		$all['excluded_products'] = $map;
		$this->replace( $all );
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
