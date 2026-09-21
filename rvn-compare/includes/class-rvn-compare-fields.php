<?php
/**
 * Реестр полей сравнения.
 *
 * Объединяет три источника строк таблицы:
 *  1) core-поля (цена, артикул, рейтинг, наличие, описания, вес, размеры);
 *  2) атрибуты WooCommerce (глобальные + кастомные при включённом тумблере);
 *  3) ACF / custom meta (ручной список ключей из настроек).
 *
 * Порядок полей — как в §6.2 живого ТЗ (Цена → … → Полное описание).
 * Все поля описываются единой структурой, поэтому таблица не зависит от
 * происхождения поля (R4-09).
 *
 * @package RVN_Compare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Сборка полей и значений для таблицы.
 */
final class RVN_Compare_Fields {

	/**
	 * Ключи групп по умолчанию.
	 */
	const GROUP_BASIC      = 'basic';
	const GROUP_DIMENSIONS = 'dimensions';
	const GROUP_SPECS      = 'specs';

	/**
	 * Единственный экземпляр.
	 *
	 * @var self|null
	 */
	private static $instance = null;

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
	 * Карта названий групп (key => label) из настроек + дефолты.
	 *
	 * @return array
	 */
	public function group_labels() {
		$labels = (array) RVN_Compare_Settings::instance()->get( 'field_groups', array() );

		return array(
			self::GROUP_BASIC      => isset( $labels['basic'] ) && $labels['basic'] ? $labels['basic'] : __( 'Основное', 'rvn-compare' ),
			self::GROUP_DIMENSIONS => isset( $labels['dimensions'] ) && $labels['dimensions'] ? $labels['dimensions'] : __( 'Вес и размеры', 'rvn-compare' ),
			self::GROUP_SPECS      => isset( $labels['specs'] ) && $labels['specs'] ? $labels['specs'] : __( 'Характеристики', 'rvn-compare' ),
		);
	}

	/**
	 * Core-поля в порядке §6.2 (без полного описания — оно вставляется последним).
	 *
	 * @return array[]
	 */
	private function core_fields() {
		return array(
			array( 'key' => 'price',             'label' => __( 'Цена', 'rvn-compare' ),              'group' => self::GROUP_BASIC,      'enabled' => true,  'source' => 'core:price' ),
			array( 'key' => 'sku',               'label' => __( 'Артикул', 'rvn-compare' ),            'group' => self::GROUP_BASIC,      'enabled' => true,  'source' => 'core:sku' ),
			array( 'key' => 'rating',            'label' => __( 'Рейтинг', 'rvn-compare' ),            'group' => self::GROUP_BASIC,      'enabled' => true,  'source' => 'core:rating' ),
			array( 'key' => 'stock',             'label' => __( 'Наличие', 'rvn-compare' ),            'group' => self::GROUP_BASIC,      'enabled' => true,  'source' => 'core:stock' ),
			array( 'key' => 'short_description', 'label' => __( 'Краткое описание', 'rvn-compare' ),   'group' => self::GROUP_BASIC,      'enabled' => true,  'source' => 'core:short_description' ),
			array( 'key' => 'weight',            'label' => __( 'Вес', 'rvn-compare' ),                'group' => self::GROUP_DIMENSIONS, 'enabled' => true,  'source' => 'core:weight' ),
			array( 'key' => 'dimensions',        'label' => __( 'Размеры', 'rvn-compare' ),            'group' => self::GROUP_DIMENSIONS, 'enabled' => true,  'source' => 'core:dimensions' ),
			array( 'key' => 'full_description',  'label' => __( 'Полное описание', 'rvn-compare' ),    'group' => self::GROUP_SPECS,      'enabled' => false, 'source' => 'core:full_description' ),
		);
	}

	/**
	 * Атрибуты товаров (глобальные + кастомные), объединённые по набору товаров.
	 *
	 * @param WC_Product[] $products Карта id => товар.
	 * @return array[]
	 */
	private function attribute_fields( $products ) {
		$settings      = RVN_Compare_Settings::instance();
		$custom_attrs  = '1' === (string) $settings->get( 'custom_attributes', '1' );
		$collected     = array();

		foreach ( $products as $product ) {
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			foreach ( $product->get_attributes() as $attribute ) {
				if ( ! $attribute instanceof WC_Product_Attribute ) {
					continue;
				}

				$name = $attribute->get_name();
				$is_tax = $attribute->is_taxonomy();

				// Глобальные атрибуты всегда; кастомные — по тумблеру.
				if ( ! $is_tax && ! $custom_attrs ) {
					continue;
				}

				$slug = $is_tax ? $name : ( 'cattr:' . $name );
				if ( isset( $collected[ $slug ] ) ) {
					continue;
				}

				$label = $is_tax
					? wc_attribute_label( $name, $product )
					: $name;

				$collected[ $slug ] = array(
					'key'     => 'attr:' . $slug,
					'label'   => $label ? $label : $name,
					'group'   => self::GROUP_SPECS,
					'enabled' => true,
					'source'  => $is_tax ? 'attribute:' . $name : 'cattribute:' . $name,
				);
			}
		}

		// Стабильный порядок — по названию.
		uasort( $collected, function ( $a, $b ) {
			return strcasecmp( $a['label'], $b['label'] );
		} );

		return array_values( $collected );
	}

	/**
	 * Ручные ACF / custom meta поля из настроек.
	 *
	 * @return array[]
	 */
	private function meta_fields() {
		$rows = (array) RVN_Compare_Settings::instance()->get( 'acf_meta_fields', array() );
		$out  = array();

		foreach ( $rows as $row ) {
			if ( empty( $row['meta_key'] ) ) {
				continue;
			}
			$out[] = array(
				'key'     => 'meta:' . $row['meta_key'],
				'label'   => isset( $row['label'] ) && $row['label'] ? $row['label'] : $row['meta_key'],
				'group'   => isset( $row['group'] ) ? sanitize_key( $row['group'] ) : self::GROUP_SPECS,
				'enabled' => ! isset( $row['enabled'] ) || ! empty( $row['enabled'] ),
				'source'  => 'meta:' . $row['meta_key'],
				'value_type' => isset( $row['value_type'] ) ? sanitize_key( $row['value_type'] ) : 'text',
			);
		}

		return $out;
	}

	/**
	 * Собирает итоговый упорядоченный список полей для набора товаров.
	 *
	 * @param int[] $ids ID товаров.
	 * @return array[]
	 */
	public function collect( $ids ) {
		$products = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( (int) $id );
			if ( $product instanceof WC_Product ) {
				$products[ (int) $id ] = $product;
			}
		}

		// Core-поля; полное описание — отдельно (вставляется последним).
		$core = $this->core_fields();
		$full = null;
		foreach ( $core as $index => $field ) {
			if ( 'full_description' === $field['key'] ) {
				$full = $field;
				unset( $core[ $index ] );
			}
		}
		$core = array_values( $core );

		$attrs = $this->attribute_fields( $products );
		$meta  = $this->meta_fields();

		$fields = array_merge( $core, $attrs, $meta );

		if ( $full ) {
			$fields[] = $full;
		}

		return $fields;
	}

	/**
	 * Строит строки таблицы: для каждого поля — значения по товарам + факт различия.
	 *
	 * @param int[] $ids Список ID товаров (в порядке колонок).
	 * @return array[] Строки ['field'=>..., 'values'=>..., 'diff'=>bool].
	 */
	public function rows( $ids ) {
		$fields   = $this->collect( $ids );
		$rows     = array();

		foreach ( $fields as $field ) {
			if ( empty( $field['enabled'] ) ) {
				continue;
			}

			$values = array();
			foreach ( $ids as $id ) {
				$product = wc_get_product( (int) $id );
				$values[] = $product instanceof WC_Product
					? $this->resolve_value( $field, $product )
					: '—';
			}

			$rows[] = array(
				'field'  => $field,
				'values' => $values,
				'diff'   => count( array_unique( $values ) ) > 1,
			);
		}

		return $rows;
	}

	/**
	 * Возвращает значение поля для товара (унифицированно для всех источников).
	 *
	 * @param array      $field   Описание поля.
	 * @param WC_Product $product Товар.
	 * @return string
	 */
	private function resolve_value( $field, $product ) {
		$source = isset( $field['source'] ) ? (string) $field['source'] : '';
		$dash   = '—';

		if ( 0 === strpos( $source, 'core:' ) ) {
			$type = substr( $source, 5 );

			switch ( $type ) {
				case 'price':
					$price = $product->get_price();
					if ( '' === $price || null === $price ) {
						return $dash;
					}
					return function_exists( 'wc_price' ) ? wc_price( $product->get_price() ) : (string) $price;

				case 'sku':
					$sku = $product->get_sku();
					return $sku ? $sku : $dash;

				case 'rating':
					$avg = (float) $product->get_average_rating();
					if ( $avg <= 0 ) {
						return $dash;
					}
					$count = (int) $product->get_rating_count();
					/* translators: 1: rating value, 2: reviews count */
					return sprintf( _n( '%1$s (из 5) · %2$s отзыв', '%1$s (из 5) · %2$s отзывов', $count, 'rvn-compare' ), number_format_i18n( $avg, 1 ), number_format_i18n( $count ) );

				case 'stock':
					$availability = $product->get_availability();
					return isset( $availability['availability'] ) && $availability['availability']
						? $availability['availability']
						: $dash;

				case 'short_description':
					return $this->clip_words( $product->get_short_description(), 40, true );

				case 'full_description':
					$desc = strip_shortcodes( $product->get_description() );
					return $this->clip_words( $desc, 60, true );

				case 'weight':
					$weight = $product->get_weight();
					if ( '' === $weight || null === $weight ) {
						return $dash;
					}
					$unit = get_option( 'woocommerce_weight_unit', 'kg' );
					return number_format_i18n( (float) $weight, 2 ) . ' ' . esc_html( $unit );

				case 'dimensions':
					return function_exists( 'wc_format_dimensions' )
						? ( wc_format_dimensions( $product->get_dimensions( false ) ) ?: $dash )
						: $dash;
			}

			return $dash;
		}

		if ( 0 === strpos( $source, 'attribute:' ) ) {
			$value = $product->get_attribute( substr( $source, 10 ) );
			return $value ? $value : $dash;
		}

		if ( 0 === strpos( $source, 'cattribute:' ) ) {
			$value = $product->get_attribute( substr( $source, 11 ) );
			return $value ? $value : $dash;
		}

		if ( 0 === strpos( $source, 'meta:' ) ) {
			$meta_key = substr( $source, 5 );
			$value    = $this->get_meta_value( $product->get_id(), $meta_key );

			if ( is_array( $value ) || is_object( $value ) ) {
				return $dash;
			}

			$type = isset( $field['value_type'] ) ? $field['value_type'] : 'text';
			if ( 'yesno' === $type ) {
				return $value ? __( 'Да', 'rvn-compare' ) : __( 'Нет', 'rvn-compare' );
			}
			if ( 'number' === $type && is_numeric( $value ) ) {
				return number_format_i18n( (float) $value );
			}

			return (string) $value ?: $dash;
		}

		return $dash;
	}

	/**
	 * Читает meta-значение (с поддержкой ACF get_field, когда плагин активен).
	 *
	 * @param int    $id       ID товара.
	 * @param string $meta_key Ключ meta.
	 * @return mixed
	 */
	private function get_meta_value( $id, $meta_key ) {
		if ( function_exists( 'get_field' ) && ( is_string( $meta_key ) ) ) {
			$acf = get_field( $meta_key, (int) $id, false );
			if ( null !== $acf && false !== $acf && '' !== $acf ) {
				return $acf;
			}
		}

		return get_post_meta( (int) $id, $meta_key, true );
	}

	/**
	 * Обрезает текст до N слов, убирая теги, с многоточием при нехватке.
	 *
	 * @param string $text     Исходный текст.
	 * @param int    $words    Лимит слов.
	 * @param bool   $strip    Убирать теги.
	 * @return string
	 */
	private function clip_words( $text, $words, $strip = true ) {
		if ( $strip ) {
			$text = wp_strip_all_tags( (string) $text );
		}
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return '—';
		}

		$parts = preg_split( '/\s+/', $text );
		if ( count( $parts ) <= $words ) {
			return $text;
		}

		return implode( ' ', array_slice( $parts, 0, $words ) ) . '…';
	}
}
