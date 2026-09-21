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
			'add_product_button'               => '1',
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
			'excluded_categories'              => array(),
			'archive_show_mode'                => 'all',
			'archive_show_pages'               => array(),
			'archive_show_urls'                => array(),
			'single_show_mode'                 => 'all',
			'single_show_pages'                => array(),
			'single_show_urls'                 => array(),
			'field_groups'                     => array(
				'basic' => $is_ru ? 'Основное' : 'General',
				'dimensions' => $is_ru ? 'Вес и размеры' : 'Weight and dimensions',
				'specs' => $is_ru ? 'Характеристики' : 'Specifications',
			),
			'core_fields'                      => array(),
			'fields'                           => array(),
			'category_groups'                  => array(),
			'acf_meta_fields'                  => array(),
			'design'                           => $this->design_defaults(),
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
			'add_product_button',
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

			if ( 'uninstall' === $key ) {
				$raw_u = isset( $raw['uninstall'] ) && is_array( $raw['uninstall'] ) ? $raw['uninstall'] : array();
				$sanitized['uninstall'] = array(
					'delete_settings'   => isset( $raw_u['delete_settings'] ) ? 1 : 0,
					'delete_user_lists' => isset( $raw_u['delete_user_lists'] ) ? 1 : 0,
					'delete_page'       => isset( $raw_u['delete_page'] ) ? 1 : 0,
				);
				continue;
			}

			// Правила показа кнопки («Где показывать»).
			if ( null !== $this->sanitize_visibility( $key, $raw, $current, $sanitized ) ) {
				continue;
			}

			// Ячейки вкладки «Дизайн таблицы»: ключ [секция][поле] или [css_var].
			if ( null !== $this->sanitize_design( $key, $raw, $current, $sanitized ) ) {
				continue;
			}

			// Стили кнопок/тостов вкладки «Дизайн элементов и кнопок».
			if ( null !== $this->sanitize_element( $key, $raw, $current, $sanitized ) ) {
				continue;
			}
		}

		// Валидация адаптива: десктоп ≥ планшет ≥ телефон (колонки и брейкпоинты).
		$this->clamp_responsive( $sanitized );

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
				'add_product_button',
				'archive_show_mode',
				'archive_show_pages',
				'archive_show_urls',
				'single_show_mode',
				'single_show_pages',
				'single_show_urls',
				'uninstall',
			),
			'fields'   => array(
				'clear_text',
				'highlight_differences',
				'hide_empty_rows',
				'show_only_differences_toggle',
				'collapse_groups',
				'include_subcats',
				'custom_attributes',
			),
			'elements' => array_merge(
				array(
					'button_text',
					'button_added_text',
					'counter_button_text',
					'clear_confirm_text',
					'toast_added_text',
					'toast_removed_text',
					'toast_cleared_text',
					'toast_limit_text',
				),
				$this->element_style_keys()
			),
			'design'   => $this->design_keys(),
			'help'     => array(),
		);
	}


	/*
	 * ---- Правила показа кнопки («Где показывать», §6.1 п.2–3). ----
	 */

	/**
	 * Санитизирует ключи видимости кнопки (show_mode / show_pages / show_urls).
	 *
	 * @param string $key       Ключ настройки.
	 * @param array  $raw       Сырые данные формы.
	 * @param array  $current   Текущие настройки.
	 * @param array  $sanitized Санитизированный массив (по ссылке).
	 * @return bool|null true — обработано; null — не ключ видимости.
	 */
	private function sanitize_visibility( $key, $raw, $current, &$sanitized ) {
		if ( 0 === strpos( $key, 'archive_show_' ) ) {
			$field = substr( $key, strlen( 'archive_show_' ) );
			if ( 'mode' === $field ) {
				$v = isset( $raw[ $key ] ) ? sanitize_key( (string) $raw[ $key ] ) : '';
				$sanitized[ $key ] = in_array( $v, array( 'all', 'show', 'hide' ), true ) ? $v : $current[ $key ];
			} elseif ( 'pages' === $field ) {
				$pages = isset( $raw[ $key ] ) && is_array( $raw[ $key ] ) ? $raw[ $key ] : array();
				$sanitized[ $key ] = array_values( array_filter( array_map( 'absint', $pages ) ) );
			} else {
				// 'urls': textarea отдаёт строку с переносами, либо массив.
				$raw_urls = isset( $raw[ $key ] ) ? $raw[ $key ] : '';
				$urls = is_array( $raw_urls ) ? $raw_urls : preg_split( '/[\r\n]+/', (string) $raw_urls );
				$out  = array();
				foreach ( $urls as $u ) {
					$u = sanitize_text_field( wp_unslash( $u ) );
					$u = trim( $u );
					if ( '' !== $u && ! in_array( $u, $out, true ) ) {
						$out[] = $u;
					}
				}
				$sanitized[ $key ] = $out;
			}
			return true;
		}

		if ( 0 === strpos( $key, 'single_show_' ) ) {
			$field = substr( $key, strlen( 'single_show_' ) );
			if ( 'mode' === $field ) {
				$v = isset( $raw[ $key ] ) ? sanitize_key( (string) $raw[ $key ] ) : '';
				$sanitized[ $key ] = in_array( $v, array( 'all', 'show', 'hide' ), true ) ? $v : $current[ $key ];
			} elseif ( 'pages' === $field ) {
				$pages = isset( $raw[ $key ] ) && is_array( $raw[ $key ] ) ? $raw[ $key ] : array();
				$sanitized[ $key ] = array_values( array_filter( array_map( 'absint', $pages ) ) );
			} else {
				$raw_urls = isset( $raw[ $key ] ) ? $raw[ $key ] : '';
				$urls = is_array( $raw_urls ) ? $raw_urls : preg_split( '/[\r\n]+/', (string) $raw_urls );
				$out  = array();
				foreach ( $urls as $u ) {
					$u = sanitize_text_field( wp_unslash( $u ) );
					$u = trim( $u );
					if ( '' !== $u && ! in_array( $u, $out, true ) ) {
						$out[] = $u;
					}
				}
				$sanitized[ $key ] = $out;
			}
			return true;
		}

		return null;
	}

	/**
	 * Валидация: десктоп ≥ планшет ≥ телефон по колонкам и брейкпоинтам (§6.1 п.5).
	 *
	 * @param array $s Санитизированные настройки (по ссылке).
	 * @return void
	 */
	private function clamp_responsive( &$s ) {
		if ( (int) $s['columns_tablet'] > (int) $s['columns_desktop'] ) {
			$s['columns_tablet'] = (int) $s['columns_desktop'];
		}
		if ( (int) $s['columns_mobile'] > (int) $s['columns_tablet'] ) {
			$s['columns_mobile'] = (int) $s['columns_tablet'];
		}
		if ( (int) $s['breakpoint_mobile'] > (int) $s['breakpoint_tablet'] ) {
			$s['breakpoint_mobile'] = (int) $s['breakpoint_tablet'];
		}
	}

	/*
	 * ---- Дизайн таблицы (§6.3 живого ТЗ) ----
	 * Хранится в settings['design'] вложенной структурой [секция][поле];
	 * значения публикуются на фронт как CSS-переменные --rvn-compare-*.
	 */

	/**
	 * Дефолты дизайна таблицы (из §11 живого ТЗ).
	 *
	 * @return array
	 */
	private function design_defaults() {
		return array(
			'colors'   => array(
				'table_bg'      => '#ffffff',
				'header_bg'     => '#ffffff',
				'label_bg'      => '#f3f4f6',
				'group_bg'      => '#f3f4f6',
				'group_soft_bg' => '#f3f4f6',
				'text'          => '#1f2937',
				'value_text'    => '#1f2937',
				'label_text'    => '#000000',
				'accent'        => '#2563eb',
				'diff_bg'       => '#ffcfcc',
				'arrow_bg'      => '#ffffff',
				'floating_bg'   => '#ffffff',
				'border'        => '#e2e8f0',
			),
			'types'    => array(
				'value_size'   => 14,
				'label_size'   => 13,
				'group_size'   => 14,
				'label_weight' => 600,
				'value_weight' => 400,
			),
			'geometry' => array(
				'radius'       => 12,
				'cell_padding' => 8,
				'photo_height' => 120,
				'photo_fit'    => 'contain',
			),
			'behavior' => array(
				'soft_bg_enabled'      => '1',
				'buy_header'           => 'buy',
				'buy_bottom'           => 'buy',
				'buy_floating'         => 'buy',
				'buy_shortcodes'       => array(),
				'inherit_theme_styles' => '0',
			),
		);
	}

	/**
	 * Плоский список POST-ключей вкладки «Дизайн таблицы».
	 *
	 * Формат: design[секция][поле] — по нему же поднимаются значения из $_POST
	 * и проверяются в sanitize_design(). Вложенные массивы не используют,
	 * чтобы вписаться в общий таб-цикл save_from_request().
	 *
	 * @return string[]
	 */
	private function design_keys() {
		return array(
			'design[colors][table_bg]',
			'design[colors][header_bg]',
			'design[colors][label_bg]',
			'design[colors][group_bg]',
			'design[colors][group_soft_bg]',
			'design[colors][text]',
			'design[colors][value_text]',
			'design[colors][label_text]',
			'design[colors][accent]',
			'design[colors][diff_bg]',
			'design[colors][arrow_bg]',
			'design[colors][floating_bg]',
			'design[colors][border]',
			'design[types][value_size]',
			'design[types][label_size]',
			'design[types][group_size]',
			'design[types][label_weight]',
			'design[types][value_weight]',
			'design[geometry][radius]',
			'design[geometry][cell_padding]',
			'design[geometry][photo_height]',
			'design[geometry][photo_fit]',
			'design[behavior][soft_bg_enabled]',
			'design[behavior][buy_header]',
			'design[behavior][buy_bottom]',
			'design[behavior][buy_floating]',
			'design[behavior][buy_shortcodes]',
			'design[behavior][inherit_theme_styles]',
		);
	}

	/**
	 * Читает текущее значение дизайна (с фолбэком на дефолт).
	 *
	 * @param array  $design  Массив settings['design'].
	 * @param string $section Секция.
	 * @param string $field   Поле.
	 * @return mixed
	 */
	private function design_get( $design, $section, $field ) {
		if ( isset( $design[ $section ][ $field ] ) ) {
			return $design[ $section ][ $field ];
		}
		$def = $this->design_defaults();
		return isset( $def[ $section ][ $field ] ) ? $def[ $section ][ $field ] : '';
	}

	/**
	 * Санитизирует одно поле дизайна (вызывается из save_from_request()).
	 *
	 * @param string $key        Плоский ключ дизайна (или любой другой ключ — вернём null).
	 * @param array  $raw        Сырые данные формы.
	 * @param array  $current    Текущие настройки.
	 * @param array  $sanitized  Санитизированный массив (по ссылке).
	 * @return bool|null true — обработано; null — не дизайн-ключ.
	 */
	private function sanitize_design( $key, $raw, $current, &$sanitized ) {
		if ( 0 !== strpos( (string) $key, 'design[' ) ) {
			return null;
		}
		if ( ! preg_match( '/^design\[([a-z_]+)\]\[([a-z_]+)\]$/', (string) $key, $m ) ) {
			return null;
		}
		$section = $m[1];
		$field   = $m[2];
		$value   = isset( $raw['design'][ $section ][ $field ] ) ? $raw['design'][ $section ][ $field ] : null;
		$cur     = $this->design_get( $current['design'], $section, $field );

		if ( 'colors' === $section ) {
			$hex = ( null !== $value ) ? sanitize_hex_color( $value ) : '';
			$sanitized['design'][ $section ][ $field ] = $hex ? $hex : $cur;
			return true;
		}

		if ( 'types' === $section ) {
			$sanitized['design'][ $section ][ $field ] = ( null !== $value && '' !== (string) $value ) ? absint( $value ) : $cur;
			return true;
		}

		if ( 'geometry' === $section ) {
			if ( 'photo_fit' === $field ) {
				$v = ( null !== $value ) ? sanitize_key( (string) $value ) : '';
				$sanitized['design'][ $section ][ $field ] = in_array( $v, array( 'contain', 'cover' ), true ) ? $v : $cur;
			} else {
				$sanitized['design'][ $section ][ $field ] = ( null !== $value && '' !== (string) $value ) ? absint( $value ) : $cur;
			}
			return true;
		}

		if ( 'behavior' === $section ) {
			if ( 'soft_bg_enabled' === $field ) {
				// Снятый чекбокс в POST не приходит — явно пишем '0'.
				$sanitized['design'][ $section ][ $field ] = ( isset( $raw['design']['behavior'][ $field ] ) && $raw['design']['behavior'][ $field ] ) ? '1' : '0';
			} elseif ( 'inherit_theme_styles' === $field ) {
				$sanitized['design'][ $section ][ $field ] = ( isset( $raw['design']['behavior'][ $field ] ) && $raw['design']['behavior'][ $field ] ) ? '1' : '0';
			} elseif ( 'buy_shortcodes' === $field ) {
				// Textarea «свои шорткоды»: одна строка = один шорткод.
				$lines = array();
				$raw_v = isset( $raw['design']['behavior'][ $field ] ) ? $raw['design']['behavior'][ $field ] : '';
				if ( is_array( $raw_v ) ) {
					$raw_v = implode( "\n", $raw_v );
				}
				foreach ( preg_split( '/[\r\n]+/', (string) $raw_v ) as $line ) {
					$line = trim( (string) $line );
					if ( '' !== $line ) {
						$lines[] = wp_kses_post( wp_unslash( $line ) );
					}
				}
				$sanitized['design'][ $section ][ $field ] = array_slice( $lines, 0, 50 );
			} else {
				$v = ( null !== $value ) ? sanitize_key( (string) $value ) : '';
				$sanitized['design'][ $section ][ $field ] = in_array( $v, array( 'buy', 'shortcode', 'hidden' ), true ) ? $v : $cur;
			}
			return true;
		}

		return null;
	}

	/**
	 * Собирает плоский список CSS-переменных из настроек дизайна.
	 *
	 * @return array{name:string, value:string}[]
	 */
	public function design_css_vars() {
		$design = (array) $this->get( 'design', array() );
		$def    = $this->design_defaults(); // страховка при неполном массиве.
		$d      = wp_parse_args( $design, $def );

		$map = array(
			'--rvn-compare-table-bg'     => $this->design_get( $d, 'colors', 'table_bg' ),
			'--rvn-compare-header-bg'    => $this->design_get( $d, 'colors', 'header_bg' ),
			'--rvn-compare-label-bg'     => $this->design_get( $d, 'colors', 'label_bg' ),
			'--rvn-compare-group-bg'     => $this->design_get( $d, 'colors', 'group_bg' ),
			'--rvn-compare-group-soft'   => $this->design_get( $d, 'colors', 'group_soft_bg' ),
			'--rvn-compare-text'         => $this->design_get( $d, 'colors', 'text' ),
			'--rvn-compare-value-text'   => $this->design_get( $d, 'colors', 'value_text' ),
			'--rvn-compare-label-text'   => $this->design_get( $d, 'colors', 'label_text' ),
			'--rvn-compare-accent'       => $this->design_get( $d, 'colors', 'accent' ),
			'--rvn-compare-diff'         => $this->design_get( $d, 'colors', 'diff_bg' ),
			'--rvn-compare-arrow-bg'     => $this->design_get( $d, 'colors', 'arrow_bg' ),
			'--rvn-compare-floating-bg'  => $this->design_get( $d, 'colors', 'floating_bg' ),
			'--rvn-compare-border'       => $this->design_get( $d, 'colors', 'border' ),
			'--rvn-compare-font-size'    => absint( $this->design_get( $d, 'types', 'value_size' ) ) . 'px',
			'--rvn-compare-label-size'   => absint( $this->design_get( $d, 'types', 'label_size' ) ) . 'px',
			'--rvn-compare-group-size'   => absint( $this->design_get( $d, 'types', 'group_size' ) ) . 'px',
			'--rvn-compare-value-weight' => absint( $this->design_get( $d, 'types', 'value_weight' ) ),
			'--rvn-compare-label-weight' => absint( $this->design_get( $d, 'types', 'label_weight' ) ),
			'--rvn-compare-radius'       => absint( $this->design_get( $d, 'geometry', 'radius' ) ) . 'px',
			'--rvn-compare-cell-padding' => absint( $this->design_get( $d, 'geometry', 'cell_padding' ) ) . 'px',
			'--rvn-compare-photo-height' => absint( $this->design_get( $d, 'geometry', 'photo_height' ) ) . 'px',
		);

		return $map;
	}

	/**
	 * Генерирует инлайн-CSS настроек дизайна таблицы.
	 *
	 * Переменные выводятся область таблицы и плавающей панели — кнопки и
	 * прочие «корневые» стили при этом не задеваются (до RD-02).
	 *
	 * @return string
	 */
	public function design_css() {
		$vars = $this->design_css_vars();
		$decl = array();
		foreach ( $vars as $name => $value ) {
			$decl[] = $name . ':' . $value . ';';
		}

		$design = (array) $this->get( 'design', array() );
		$fit    = $this->design_get( $design, 'geometry', 'photo_fit' );

		$soft = '';
		if ( '1' === (string) $this->design_get( $design, 'behavior', 'soft_bg_enabled' ) ) {
			$soft = '.rvn-compare-table .rvn-compare-group__head{border-bottom:3px solid var(--rvn-compare-group-soft);}';
		}

		return '.rvn-compare-table,.rvn-compare-floating{' . implode( '', $decl ) . '}'
			. '.rvn-compare-table .rvn-compare-col__thumb img{object-fit:' . esc_attr( $fit ) . ';}'
			. $soft;
	}

	/*
	 * ---- Поля таблицы, группы характеристик, группы категорий (UI админки). ----
	 */

	/**
	 * Подбирает уникальный slug для новой группы характеристик.
	 *
	 * @param string $base   Базовый slug.
	 * @param array  $groups Существующие ключи => label.
	 * @return string
	 */
	private function unique_group_key( $base, $groups ) {
		$base = sanitize_key( $base );
		if ( ! isset( $groups[ $base ] ) ) {
			return $base;
		}
		$i = 2;
		do {
			$candidate = $base . '-' . $i;
			$i++;
		} while ( isset( $groups[ $candidate ] ) );
		return $candidate;
	}

	/**
	 * Применяет правки полей/групп/категорий из данных формы админки.
	 *
	 * Принимает структурированные массивы, санитизирует и пишет в опцию,
	 * не трогая прочие настройки. Используется вкладкой «Таблица сравнения».
	 *
	 * @param array $raw Данные формы.
	 * @return void
	 */
	public function save_compare_ui( $raw ) {
		$all = $this->all();

		if ( isset( $raw['field_groups'] ) && is_array( $raw['field_groups'] ) ) {
			$groups = array();
			foreach ( $raw['field_groups'] as $key => $label ) {
				$key   = sanitize_key( $key );
				$label = sanitize_text_field( wp_unslash( $label ) );
				if ( $label && ! isset( $groups[ $key ] ) ) {
					$groups[ $key ] = $label;
				}
			}

			// Новая группа (из field_groups_new): ключ генерируем уникальный.
			if ( isset( $raw['field_groups_new'] ) && '' !== trim( (string) $raw['field_groups_new'] ) ) {
				$label = sanitize_text_field( wp_unslash( $raw['field_groups_new'] ) );
				if ( $label ) {
					$key = $this->unique_group_key( 'group', $groups );
					$groups[ $key ] = $label;
				}
			}

			if ( ! empty( $groups ) ) {
				$all['field_groups'] = $groups;
			}
		}

		if ( isset( $raw['fields'] ) && is_array( $raw['fields'] ) ) {
			$fields = array();
			foreach ( $raw['fields'] as $index => $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				$fields[] = array(
					'key'     => isset( $field['key'] ) ? sanitize_key( $field['key'] ) : '',
					'source'  => isset( $field['source'] ) ? sanitize_text_field( wp_unslash( $field['source'] ) ) : '',
					'label'   => isset( $field['label'] ) ? sanitize_text_field( wp_unslash( $field['label'] ) ) : '',
					'group'   => isset( $field['group'] ) ? sanitize_key( $field['group'] ) : 'basic',
					'enabled' => isset( $field['enabled'] ) ? 1 : 0,
				);
			}
			$all['fields'] = $fields;
		}

		if ( isset( $raw['core_fields'] ) && is_array( $raw['core_fields'] ) ) {
			// Упорядоченная карта key => ['enabled','label','hint','group'].
			// Порядок вставки сохраняет порядок DOM-строк (drag&drop).
			$core = array();
			foreach ( $raw['core_fields'] as $key => $field ) {
				$key = sanitize_key( $key );
				if ( ! is_array( $field ) ) {
					$core[ $key ] = array( 'enabled' => 1, 'label' => '', 'hint' => '', 'group' => 'basic' );
					continue;
				}
				$core[ $key ] = array(
					'enabled' => isset( $field['enabled'] ) ? 1 : 0,
					'label'   => isset( $field['label'] ) ? sanitize_text_field( wp_unslash( $field['label'] ) ) : '',
					'hint'    => isset( $field['hint'] ) ? sanitize_text_field( wp_unslash( $field['hint'] ) ) : '',
					'group'   => isset( $field['group'] ) ? sanitize_key( (string) $field['group'] ) : 'basic',
				);
			}
			$all['core_fields'] = $core;
		}

		if ( isset( $raw['acf_meta_fields'] ) && is_array( $raw['acf_meta_fields'] ) ) {
			$meta = array();
			foreach ( $raw['acf_meta_fields'] as $index => $field ) {
				if ( ! is_array( $field ) || empty( $field['meta_key'] ) ) {
					continue;
				}
				$meta[] = array(
					'meta_key'   => sanitize_text_field( wp_unslash( $field['meta_key'] ) ),
					'label'      => isset( $field['label'] ) ? sanitize_text_field( wp_unslash( $field['label'] ) ) : '',
					'group'      => isset( $field['group'] ) ? sanitize_key( $field['group'] ) : 'specs',
					'value_type' => isset( $field['value_type'] ) ? sanitize_key( $field['value_type'] ) : 'text',
					'enabled'    => isset( $field['enabled'] ) ? 1 : 0,
				);
			}
			$all['acf_meta_fields'] = $meta;
		}

		if ( isset( $raw['category_groups'] ) && is_array( $raw['category_groups'] ) ) {
			$all['category_groups'] = RVN_Compare_Categories::instance()->sanitize_groups( $raw['category_groups'] );
		}

		// Нормализация: поля, ссылающиеся на удалённую группу, переносим
		// в группу «Характеристики», чтобы не плодить «осиротевшие» группы.
		$valid_groups = array_keys( (array) $all['field_groups'] );
		if ( ! empty( $valid_groups ) ) {
			foreach ( (array) $all['core_fields'] as $key => $field ) {
				if ( is_array( $field ) && isset( $field['group'] ) && ! in_array( $field['group'], $valid_groups, true ) ) {
					$all['core_fields'][ $key ]['group'] = 'specs';
				}
			}
			foreach ( (array) $all['acf_meta_fields'] as $key => $field ) {
				if ( is_array( $field ) && isset( $field['group'] ) && ! in_array( $field['group'], $valid_groups, true ) ) {
					$all['acf_meta_fields'][ $key ]['group'] = 'specs';
				}
			}
		}

		$this->replace( $all );
	}

	/*
	 * ---- Дизайн элементов и кнопок (§6.4, RD-02) ----
	 * Хранится в button_styles / toast_styles; форма использует плоские
	 * ключи вида es[<раздел>][<ключ>][normal][<поле>].
	 */

	/**
	 * Плоский список POST-ключей вкладки «Дизайн элементов и кнопок».
	 *
	 * @return string[]
	 */
	private function element_style_keys() {
		$keys   = array();
		$groups = array( 'compare', 'added', 'counter' );
		$toasts = array( 'added', 'removed', 'cleared', 'limit' );
		$normal = array( 'bg', 'color', 'border', 'border_width', 'radius', 'font_size', 'font_weight', 'padding' );
		$hover  = array( 'bg', 'color', 'border' );
		$toast_f = array( 'icon', 'icon_position', 'position_desktop', 'position_mobile', 'progress', 'bg', 'color', 'border', 'accent' );

		foreach ( $groups as $g ) {
			$keys[] = 'es[' . $g . '][mode]';
			$keys[] = 'es[' . $g . '][svg]';
			$keys[] = 'es[' . $g . '][badge_position]';
			$keys[] = 'es[' . $g . '][class]';
			foreach ( $normal as $f ) {
				$keys[] = 'es[' . $g . '][normal][' . $f . ']';
			}
			foreach ( $hover as $f ) {
				$keys[] = 'es[' . $g . '][hover][' . $f . ']';
			}
		}

		foreach ( $toasts as $t ) {
			foreach ( $toast_f as $f ) {
				$keys[] = 'es[' . $t . '][toast][' . $f . ']';
			}
		}

		return $keys;
	}

	/**
	 * Санитизирует одно поле элементов (вызывается из save_from_request()).
	 *
	 * @param string $key       Плоский ключ (es[...]) или любой другой ключ — вернём null.
	 * @param array  $raw       Сырые данные формы.
	 * @param array  $current   Текущие настройки.
	 * @param array  $sanitized Санитизированный массив (по ссылке).
	 * @return bool|null true — обработано; null — не элемент-ключ.
	 */
	private function sanitize_element( $key, $raw, $current, &$sanitized ) {
		if ( 0 !== strpos( (string) $key, 'es[' ) ) {
			return null;
		}
		if ( ! preg_match( '/^es\[([a-z_]+)\]\[(normal|hover|toast|mode|svg|badge_position|class)\]?(?:\[([a-z_]+)\])?$/', (string) $key, $m ) ) {
			return null;
		}

		$part    = $m[1];   // compare|added|counter|added|removed|cleared|limit
		$field   = $m[2];   // normal|hover|toast|mode|svg|badge_position|class
		$sub     = isset( $m[3] ) ? $m[3] : ''; // поле внутри normal/hover/toast

		$value = null;
		if ( 'toast' === $field ) {
			$value = isset( $raw['es'][ $part ]['toast'][ $sub ] ) ? $raw['es'][ $part ]['toast'][ $sub ] : null;
		} elseif ( in_array( $field, array( 'normal', 'hover' ), true ) ) {
			$value = isset( $raw['es'][ $part ][ $field ][ $sub ] ) ? $raw['es'][ $part ][ $field ][ $sub ] : null;
		} else {
			$value = isset( $raw['es'][ $part ][ $field ] ) ? $raw['es'][ $part ][ $field ] : null;
		}

		$design = RVN_Compare_Design::instance();
		$buttons = $design->buttons();
		$toasts  = $design->toasts();

		// Тосты: часть 'added' пересекается с кнопкой, поэтому ветку
		// выбираем по полю — наличие [toast][...] означает тост.
		if ( 'toast' === $field && in_array( $part, array_keys( $toasts ), true ) ) {
			$def = $design->toast_defaults();
			$cur_t = isset( $toasts[ $part ][ $sub ] ) && '' !== (string) $toasts[ $part ][ $sub ] ? $toasts[ $part ][ $sub ] : $def[ $part ][ $sub ];

			if ( in_array( $sub, array( 'bg', 'color', 'border', 'accent' ), true ) ) {
				$h = ( null !== $value ) ? sanitize_hex_color( $value ) : '';
				$sanitized['toast_styles'][ $part ][ $sub ] = $h ? $h : $cur_t;
			} elseif ( 'icon' === $sub ) {
				$sanitized['toast_styles'][ $part ][ 'icon' ] = $design->svg_sanitize( (string) $value );
			} elseif ( 'icon_position' === $sub ) {
				$v = ( null !== $value ) ? sanitize_key( (string) $value ) : '';
				$sanitized['toast_styles'][ $part ][ 'icon_position' ] = in_array( $v, array( 'left', 'right' ), true ) ? $v : $cur_t;
			} elseif ( in_array( $sub, array( 'position_desktop', 'position_mobile' ), true ) ) {
				$v = ( null !== $value ) ? sanitize_key( (string) $value ) : '';
				$sanitized['toast_styles'][ $part ][ $sub ] = in_array( $v, $design->toast_positions(), true ) ? $v : $cur_t;
			} elseif ( 'progress' === $sub ) {
				$sanitized['toast_styles'][ $part ][ 'progress' ] = ( isset( $raw['es'][ $part ]['toast']['progress'] ) && $raw['es'][ $part ]['toast']['progress'] ) ? '1' : '0';
			} else {
				$sanitized['toast_styles'][ $part ][ $sub ] = ( null !== $value ) ? sanitize_text_field( wp_unslash( $value ) ) : $cur_t;
			}

			return true;
		}

		if ( in_array( $part, array( 'compare', 'added', 'counter' ), true ) ) {
			$cur = isset( $buttons[ $part ] ) ? $buttons[ $part ] : array();

			if ( 'mode' === $field ) {
				$v = ( null !== $value ) ? sanitize_key( (string) $value ) : '';
				$sanitized['button_styles'][ $part ]['mode'] = in_array( $v, $design->button_modes(), true ) ? $v : ( isset( $cur['mode'] ) ? $cur['mode'] : 'icon_text' );
			} elseif ( 'svg' === $field ) {
				$sanitized['button_styles'][ $part ]['svg'] = $design->svg_sanitize( (string) $value );
			} elseif ( 'badge_position' === $field ) {
				$v = ( null !== $value ) ? sanitize_key( (string) $value ) : '';
				$sanitized['button_styles'][ $part ]['badge_position'] = in_array( $v, array( '', 'left', 'right', 'top' ), true ) ? $v : ( isset( $cur['badge_position'] ) ? $cur['badge_position'] : '' );
			} elseif ( 'class' === $field ) {
				$sanitized['button_styles'][ $part ]['class'] = sanitize_html_class( (string) $value );
			} else {
				// normal/hover: поля bg, color, border — hex; числа; padding.
				$defaults = $design->button_defaults();
				$def = isset( $defaults[ $part ][ $field ][ $sub ] ) ? $defaults[ $part ][ $field ][ $sub ] : '';
				$cur_v = isset( $cur[ $field ][ $sub ] ) && '' !== (string) $cur[ $field ][ $sub ] ? $cur[ $field ][ $sub ] : $def;

				if ( in_array( $sub, array( 'bg', 'color', 'border' ), true ) ) {
					$h = ( null !== $value ) ? sanitize_hex_color( $value ) : '';
					$sanitized['button_styles'][ $part ][ $field ][ $sub ] = $h ? $h : $cur_v;
				} elseif ( 'padding' === $sub ) {
					$sanitized['button_styles'][ $part ][ $field ][ $sub ] = ( null !== $value && '' !== (string) $value ) ? sanitize_text_field( wp_unslash( $value ) ) : $cur_v;
				} else {
					$sanitized['button_styles'][ $part ][ $field ][ $sub ] = ( null !== $value && '' !== (string) $value ) ? absint( $value ) : $cur_v;
				}
			}

			return true;
		}

		return null;
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
	 * Правила показа кнопки сравнения для контекста ('archive' | 'single').
	 *
	 * @param string $context Контекст.
	 * @return array{mode:string, pages:int[], urls:string[]}
	 */
	public function visibility( $context ) {
		$prefix = 'archive' === $context ? 'archive' : 'single';

		return array(
			'mode'  => (string) $this->get( $prefix . '_show_mode', 'all' ),
			'pages' => array_values( array_filter( array_map( 'absint', (array) $this->get( $prefix . '_show_pages', array() ) ) ) ),
			'urls'  => (array) $this->get( $prefix . '_show_urls', array() ),
		);
	}

	/**
	 * Список исключённых категорий (ID).
	 *
	 * @return int[]
	 */
	public function excluded_categories() {
		return array_values( array_filter( array_map( 'absint', (array) $this->get( 'excluded_categories', array() ) ) ) );
	}

	/**
	 * Устанавливает список исключённых категорий целиком.
	 *
	 * @param int[] $ids ID категорий.
	 * @return void
	 */
	public function set_excluded_categories( $ids ) {
		$all = $this->all();
		$all['excluded_categories'] = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
		$this->replace( $all );
	}

	/**
	 * Добавляет категорию в исключения.
	 *
	 * @param int $id ID категории.
	 * @return void
	 */
	public function add_excluded_category( $id ) {
		$ids   = $this->excluded_categories();
		$ids[] = absint( $id );
		$this->set_excluded_categories( array_unique( $ids ) );
	}

	/**
	 * Удаляет категорию из исключений.
	 *
	 * @param int $id ID категории.
	 * @return void
	 */
	public function remove_excluded_category( $id ) {
		$ids = $this->excluded_categories();
		$this->set_excluded_categories( array_values( array_diff( $ids, array( absint( $id ) ) ) ) );
	}

	/**
	 * Публичный доступ к флагу «Кнопка “Добавить товар”».
	 *
	 * @return bool
	 */
	public function add_product_button() {
		return '1' === (string) $this->get( 'add_product_button', '1' );
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
