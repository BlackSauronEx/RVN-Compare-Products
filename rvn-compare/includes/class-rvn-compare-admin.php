<?php
/**
 * Админка плагина: меню RVN, экран настроек, вкладки, сбросы.
 *
 * Меню RVN создаётся один раз и переиспользуется другими плагинами
 * линейки (общий хелпер, см. §6 живого ТЗ). Подпункты: «Сравнение»
 * (настройки) и «Поддержка» (заглушка, без дублей).
 *
 * @package RVN_Compare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Регистрация меню и рендер настроек админки.
 */
final class RVN_Compare_Admin {

	/**
	 * Slug верхнего пункта меню линейки.
	 */
	const MENU_SLUG = 'rvn'; // @todo: уточнить финальный slug главного меню линейки.

	/**
	 * Slug экрана «Сравнение».
	 */
	const SCREEN_SLUG = 'rvn-compare';

	/**
	 * Единственный экземпляр.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Возвращает единственный экземпляр и вешает хуки админки.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			add_action( 'admin_menu', array( self::$instance, 'register_menu' ) );
			add_action( 'admin_post_rvn_compare_save', array( self::$instance, 'handle_save' ) );
		}
		return self::$instance;
	}

	/**
	 * Регистрирует (или находит существующий) пункт меню RVN и подпункты.
	 *
	 * @return void
	 */
	public function register_menu() {
		// Позиция 59 — «под WooCommerce»; при занятости WP выберет ближайшую.
		if ( empty( $GLOBALS['admin_page_hooks'][ self::MENU_SLUG ] ) ) {
			add_menu_page(
				__( 'RVN', 'rvn-compare' ),
				__( 'RVN', 'rvn-compare' ),
				'manage_options',
				self::MENU_SLUG,
				array( $this, 'render_support' ),
				'',
				59
			);
		}

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Сравнение', 'rvn-compare' ),
			__( 'Сравнение', 'rvn-compare' ),
			$this->settings_capability(),
			self::SCREEN_SLUG,
			array( $this, 'render_settings' )
		);

		// Подпункт «Поддержка» — общий для линейки, добавляем без дублей.
		if ( empty( $GLOBALS['submenu'][ self::MENU_SLUG ] ) || ! $this->submenu_has( 'rvn-support' ) ) {
			add_submenu_page(
				self::MENU_SLUG,
				__( 'Поддержка', 'rvn-compare' ),
				__( 'Поддержка', 'rvn-compare' ),
				'manage_options',
				'rvn-support',
				array( $this, 'render_support' )
			);
		}
	}

	/**
	 * Проверяет, есть ли уже подпункт с указанным slug в меню RVN.
	 *
	 * @param string $slug Slug подпункта.
	 * @return bool
	 */
	private function submenu_has( $slug ) {
		foreach ( (array) $GLOBALS['submenu'][ self::MENU_SLUG ] as $item ) {
			if ( isset( $item[2] ) && $slug === $item[2] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Возвращает capability для доступа к настройкам.
	 *
	 * Значение настраивается в админке + доступен фильтр (§6 живого ТЗ).
	 *
	 * @return string
	 */
	private function settings_capability() {
		$cap = RVN_Compare_Settings::instance()->get( 'admin_capability', 'manage_options' );
		return apply_filters( 'rvn_compare_settings_capability', $cap );
	}

	/**
	 * Рендерит экран «Поддержка» (заглушку линейки).
	 *
	 * @return void
	 */
	public function render_support() {
		echo '<div class="wrap rvn-compare-support">';
		echo '<h1>' . esc_html__( 'Поддержка', 'rvn-compare' ) . '</h1>';
		echo '<p>' . esc_html__( 'Будет реализовано в скором времени.', 'rvn-compare' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Рендерит экран настроек (вкладки + формы).
	 *
	 * @return void
	 */
	public function render_settings() {
		if ( ! current_user_can( $this->settings_capability() ) ) {
			wp_die( esc_html__( 'Недостаточно прав для доступа к этой странице.', 'rvn-compare' ) );
		}

		$settings = RVN_Compare_Settings::instance();
		$all      = $settings->all();
		$active   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';

		echo '<div class="wrap rvn-compare-admin">';
		echo '<h1>' . esc_html__( 'RVN Compare — настройки', 'rvn-compare' ) . '</h1>';

		// Всплывающие уведомления после сохранения/действий над страницей.
		if ( isset( $_GET['notice'] ) ) {
			$this->render_notice( sanitize_key( (string) $_GET['notice'] ) );
		}
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $this->admin_tabs() as $slug => $label ) {
			$url   = add_query_arg(
				array(
					'page' => self::SCREEN_SLUG,
					'tab'  => $slug,
				),
				admin_url( 'admin.php' )
			);
			$class = $active === $slug ? 'nav-tab nav-tab-active' : 'nav-tab';
			printf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( $url ),
				esc_attr( $class ),
				esc_html( $label )
			);
		}
		echo '</nav>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'rvn_compare_save', 'rvn_compare_nonce' );
		echo '<input type="hidden" name="action" value="rvn_compare_save" />';
		echo '<input type="hidden" name="tab" value="' . esc_attr( $active ) . '" />';

		$this->render_tab( $active, $all );

		submit_button( __( 'Сохранить настройки', 'rvn-compare' ) );
		echo '</form>';

		// Глобальный сброс всех настроек.
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rvn-compare-reset">';
		wp_nonce_field( 'rvn_compare_save', 'rvn_compare_nonce' );
		echo '<input type="hidden" name="action" value="rvn_compare_save" />';
		echo '<input type="hidden" name="rvn_compare_reset" value="1" />';
		submit_button( __( 'Сбросить все настройки', 'rvn-compare' ), 'secondary', 'submit', true, array( 'onclick' => 'return confirm(rvnCompareAdmin.confirmResetAll);' ) );
		echo '</form>';

		echo '</div>';
	}

	/**
	 * Возвращает список вкладок админки (slug => название).
	 *
	 * @return array
	 */
	private function admin_tabs() {
		return array(
			'general'    => __( 'Основное', 'rvn-compare' ),
			'fields'     => __( 'Таблица сравнения', 'rvn-compare' ),
			'design'     => __( 'Дизайн таблицы', 'rvn-compare' ),
			'elements'   => __( 'Дизайн элементов и кнопок', 'rvn-compare' ),
			'help'       => __( 'Справка', 'rvn-compare' ),
		);
	}

	/**
	 * Рендерит содержимое конкретной вкладки.
	 *
	 * @param string $tab Активная вкладка.
	 * @param array  $all Текущие настройки.
	 * @return void
	 */
	private function render_tab( $tab, $all ) {
		switch ( $tab ) {
			case 'fields':
				$this->render_fields_tab( $all );
				break;
			case 'design':
				$this->render_design_tab( $all );
				break;
			case 'elements':
				$this->render_elements_tab( $all );
				break;
			case 'help':
				$this->render_help_tab();
				break;
			default:
				$this->render_general_tab( $all );
		}
	}

	/**
	 * Вкладка «Основное».
	 *
	 * @param array $all Текущие настройки.
	 * @return void
	 */
	private function render_general_tab( $all ) {
		$this->render_page_section( $all );
		$this->render_exclusions_section();

		// ---- Поведение и ограничения ----
		echo '<h2 class="title">' . esc_html__( 'Поведение и ограничения', 'rvn-compare' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		$this->number_field( $all, 'max_items_total', __( 'Максимум товаров в сравнении', 'rvn-compare' ), 1, 100, __( 'Общий лимит списка сравнения (по умолчанию 50).', 'rvn-compare' ) );
		$this->number_field( $all, 'max_items_per_context', __( 'Лимит на категорию/группу', 'rvn-compare' ), 1, 50, __( 'Максимум товаров в одной категории или группе (по умолчанию 12). Не может превышать глобальный лимит.', 'rvn-compare' ) );
		$this->number_field( $all, 'breakpoint_tablet', __( 'Точка перехода (планшет), px', 'rvn-compare' ), 0, 10000, __( 'Ширина включения планшетного режима (по умолчанию 1024).', 'rvn-compare' ) );
		$this->number_field( $all, 'breakpoint_mobile', __( 'Точка перехода (телефон), px', 'rvn-compare' ), 0, 10000, __( 'Ширина включения мобильного режима (по умолчанию 768).', 'rvn-compare' ) );
		$this->number_field( $all, 'columns_desktop', __( 'Видимых товаров: десктоп', 'rvn-compare' ), 1, 10, __( 'Колонок на десктопе (по умолчанию 5).', 'rvn-compare' ) );
		$this->number_field( $all, 'columns_tablet', __( 'Видимых товаров: планшет', 'rvn-compare' ), 1, 10, __( 'Колонок на планшете (по умолчанию 3).', 'rvn-compare' ) );
		$this->number_field( $all, 'columns_mobile', __( 'Видимых товаров: телефон', 'rvn-compare' ), 1, 10, __( 'Колонок на телефоне (по умолчанию 2).', 'rvn-compare' ) );
		$this->color_field( $all, 'accent_color', __( 'Основной цветовой акцент', 'rvn-compare' ), __( 'Цвет кнопок «Купить», активных вкладок, бейджа счётчика и [?] (по умолчанию #2563eb).', 'rvn-compare' ) );
		$this->text_field( $all, 'floating_offset', __( 'Отступ плавающей панели', 'rvn-compare' ), __( 'Верхний отступ фиксированной панели при прокрутке (по умолчанию 0px; можно в своих единицах, напр. 80px).', 'rvn-compare' ) );
		echo '</tbody></table>';

		// ---- Фото и анимации ----
		echo '<h2 class="title">' . esc_html__( 'Фото и анимации', 'rvn-compare' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		$this->select_field( $all, 'photo_fit', __( 'Подгонка фото (object-fit)', 'rvn-compare' ), array(
			'contain' => __( 'Вписать целиком (contain)', 'rvn-compare' ),
			'cover'   => __( 'Заполнить (cover)', 'rvn-compare' ),
		), __( 'Как фото помещается в рамку шапки товара (R2-14).', 'rvn-compare' ) );
		$this->number_field( $all, 'animation_speed', __( 'Скорость анимации, мс', 'rvn-compare' ), 0, 10000, __( '0 — без анимации (по умолчанию 300).', 'rvn-compare' ) );
		$this->number_field( $all, 'toast_duration', __( 'Длительность уведомлений, мс', 'rvn-compare' ), 0, 60000, __( 'Через сколько скрывать всплывающие уведомления (по умолчанию 3200).', 'rvn-compare' ) );
		echo '</tbody></table>';

		// ---- Кнопка сравнения ----
		echo '<h2 class="title">' . esc_html__( 'Кнопка сравнения', 'rvn-compare' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		$this->select_field( $all, 'archive_button_position', __( 'Позиция кнопки на карточке товара', 'rvn-compare' ), $this->button_positions(), __( 'Дефолт — «После кнопки Купить».', 'rvn-compare' ) );
		$this->select_field( $all, 'single_button_position', __( 'Позиция кнопки на странице товара', 'rvn-compare' ), $this->button_positions(), __( 'Дефолт — «После кнопки Купить».', 'rvn-compare' ) );
		echo '</tbody></table>';

		// ---- Доступ и прочее ----
		echo '<h2 class="title">' . esc_html__( 'Доступ и прочее', 'rvn-compare' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		$this->select_field( $all, 'admin_capability', __( 'Доступ к настройкам', 'rvn-compare' ), array(
			'manage_options'     => __( 'Только администраторы', 'rvn-compare' ),
			'manage_woocommerce' => __( 'Администраторы и менеджеры магазина', 'rvn-compare' ),
		), __( 'Кому разрешено менять настройки плагина.', 'rvn-compare' ) );
		$this->select_field( $all, 'groups_default_state', __( 'Группы характеристик по умолчанию', 'rvn-compare' ), array(
			'expanded'  => __( 'Развёрнуты', 'rvn-compare' ),
			'collapsed' => __( 'Свёрнуты', 'rvn-compare' ),
		), __( 'Стартовое состояние групп в таблице.', 'rvn-compare' ) );
		$this->checkbox_field( $all, 'auto_insert_table', __( 'Авто-вставка таблицы', 'rvn-compare' ), __( 'Добавлять таблицу в конец страницы сравнения, если на ней нет шорткода.', 'rvn-compare' ) );
		$this->checkbox_field( $all, 'show_stock', __( 'Показывать остаток', 'rvn-compare' ), __( 'Показывать «В наличии (54)» в шапке товара.', 'rvn-compare' ) );
		echo '</tbody></table>';
	}

	/**
	 * Вкладка «Таблица сравнения» (заглушка первого шага).
	 *
	 * @param array $all Текущие настройки.
	 * @return void
	 */
	private function render_fields_tab( $all ) {
		$this->render_field_groups_section( $all );
		$this->render_fields_section( $all );
		$this->render_category_groups_section( $all );

		echo '<h2 class="title">' . esc_html__( 'Основное поведение', 'rvn-compare' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		$this->text_field( $all, 'clear_text', __( 'Текст кнопки «Очистить»', 'rvn-compare' ), __( 'Подпись кнопки удаления всех товаров из списка.', 'rvn-compare' ) );
		$this->checkbox_field( $all, 'highlight_differences', __( 'Подсветка различий', 'rvn-compare' ), __( 'Выделять цветом строки с различающимися значениями.', 'rvn-compare' ) );
		$this->checkbox_field( $all, 'hide_empty_rows', __( 'Пустые строки', 'rvn-compare' ), __( 'Скрывать строки, где у всех товаров значение пустое («—»).', 'rvn-compare' ) );
		$this->checkbox_field( $all, 'show_only_differences_toggle', __( 'Переключатель «Только различия»', 'rvn-compare' ), __( 'Показывать покупателям переключатель «Только различия» над таблицей.', 'rvn-compare' ) );
		$this->checkbox_field( $all, 'collapse_groups', __( 'Сворачивание групп', 'rvn-compare' ), __( 'Разрешить сворачивание групп характеристик на странице сравнения.', 'rvn-compare' ) );
		$this->checkbox_field( $all, 'include_subcats', __( 'Включать подкатегории', 'rvn-compare' ), __( 'Учитывать подкатегории при определении групп сравнения.', 'rvn-compare' ) );
		$this->checkbox_field( $all, 'custom_attributes', __( 'Кастомные атрибуты товаров', 'rvn-compare' ), __( 'Выводить неглобальные (кастомные) атрибуты товаров в таблице.', 'rvn-compare' ) );
		echo '</tbody></table>';
	}

	/**
	 * Секция «Группы характеристик» (переименование; drag&drop и добавление — позже).
	 *
	 * @param array $all Текущие настройки.
	 * @return void
	 */
	private function render_field_groups_section( $all ) {
		$fields       = RVN_Compare_Fields::instance();
		$labels       = $fields->group_labels();
		$allowed_keys = array( 'basic', 'dimensions', 'specs' );
		$groups       = (array) $this->setting_value( $all, 'field_groups', array() );

		echo '<h2 class="title">' . esc_html__( 'Группы характеристик', 'rvn-compare' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Заголовки-разделители в таблице. Переименуйте их или оставьте как есть.', 'rvn-compare' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $allowed_keys as $key ) {
			$value = isset( $groups[ $key ] ) ? $groups[ $key ] : ( isset( $labels[ $key ] ) ? $labels[ $key ] : $key );
			echo '<tr><th scope="row"><label for="fg_' . esc_attr( $key ) . '">#' . esc_html( ucfirst( $key ) ) . '</label></th><td>';
			echo '<input type="text" id="fg_' . esc_attr( $key ) . '" name="field_groups[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" class="regular-text" />';
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Секция «Поля таблицы» (переключатели полей: core/атрибуты/meta).
	 *
	 * Список полей строится на лету для товаров из списка; здесь показываем
	 * предопределённые источники и ручные ACF-meta.
	 *
	 * @param array $all Текущие настройки.
	 * @return void
	 */
	private function render_fields_section( $all ) {
		$fields = RVN_Compare_Fields::instance();

		echo '<h2 class="title">' . esc_html__( 'Поля таблицы', 'rvn-compare' ) . '</h2>';
		echo '<p class="description">'
			. esc_html__( 'Базовые поля управляются ниже; атрибуты WooCommerce подтягиваются автоматически. Полное описание выключено по умолчанию.', 'rvn-compare' )
			. '</p>';

		echo '<h3>' . esc_html__( 'ACF / Custom Meta', 'rvn-compare' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Ручной список meta-ключей: добавьте ключ, название и группу. Поддерживаются scalar-значения.', 'rvn-compare' ) . '</p>';

		$meta = (array) $this->setting_value( $all, 'acf_meta_fields', array() );
		echo '<table class="widefat striped rvn-compare-meta-table"><thead><tr>'
			. '<th>' . esc_html__( 'Meta key', 'rvn-compare' ) . '</th>'
			. '<th>' . esc_html__( 'Название', 'rvn-compare' ) . '</th>'
			. '<th>' . esc_html__( 'Группа', 'rvn-compare' ) . '</th>'
			. '<th>' . esc_html__( 'Тип', 'rvn-compare' ) . '</th>'
			. '<th>' . esc_html__( 'Вкл', 'rvn-compare' ) . '</th>'
			. '</tr></thead><tbody>';

		$types = array(
			'text'   => __( 'Текст', 'rvn-compare' ),
			'number' => __( 'Число', 'rvn-compare' ),
			'yesno'  => __( 'Да/Нет', 'rvn-compare' ),
			'select' => __( 'Метка select', 'rvn-compare' ),
		);

		for ( $i = 0; $i <= count( $meta ); $i++ ) {
			$row = isset( $meta[ $i ] ) ? $meta[ $i ] : array( 'meta_key' => '', 'label' => '', 'group' => 'specs', 'value_type' => 'text', 'enabled' => 1 );
			echo '<tr>';
			echo '<td><input type="text" name="acf_meta_fields[' . (int) $i . '][meta_key]" value="' . esc_attr( isset( $row['meta_key'] ) ? $row['meta_key'] : '' ) . '" class="regular-text" /></td>';
			echo '<td><input type="text" name="acf_meta_fields[' . (int) $i . '][label]" value="' . esc_attr( isset( $row['label'] ) ? $row['label'] : '' ) . '" class="regular-text" /></td>';
			echo '<td><select name="acf_meta_fields[' . (int) $i . '][group]">';
			$group_labels = $fields->group_labels();
			foreach ( $group_labels as $gk => $gl ) {
				printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $gk ), selected( isset( $row['group'] ) ? $row['group'] : 'specs', $gk, false ), esc_html( $gl ) );
			}
			echo '</select></td>';
			echo '<td><select name="acf_meta_fields[' . (int) $i . '][value_type]">';
			foreach ( $types as $tk => $tl ) {
				printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $tk ), selected( isset( $row['value_type'] ) ? $row['value_type'] : 'text', $tk, false ), esc_html( $tl ) );
			}
			echo '</select></td>';
			echo '<td><input type="checkbox" name="acf_meta_fields[' . (int) $i . '][enabled]" value="1" ' . checked( ! isset( $row['enabled'] ) || $row['enabled'], true, false ) . ' /></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Секция «Группы категорий для сравнения».
	 *
	 * @param array $all Текущие настройки.
	 * @return void
	 */
	private function render_category_groups_section( $all ) {
		echo '<h2 class="title">' . esc_html__( 'Группы категорий для сравнения', 'rvn-compare' ) . '</h2>';
		echo '<p class="description">'
			. esc_html__( 'Товары сравниваются внутри своих категорий. Объедините категории в группу, чтобы сравнивать их между собой; категория может входить только в одну группу.', 'rvn-compare' )
			. '</p>';

		$groups = RVN_Compare_Categories::instance()->groups();
		$taken  = RVN_Compare_Categories::instance()->taken_category_ids();

		if ( empty( $groups ) ) {
			echo '<p class="description">' . esc_html__( 'Пока групп нет.', 'rvn-compare' ) . '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Название', 'rvn-compare' ) . '</th><th>' . esc_html__( 'Категории', 'rvn-compare' ) . '</th></tr></thead><tbody>';
			foreach ( $groups as $group ) {
				echo '<tr><td><strong>' . esc_html( $group['name'] ) . '</strong></td><td>';
				$names = array();
				foreach ( $group['cats'] as $cat_id ) {
					$term = get_term( (int) $cat_id );
					$names[] = ( $term && ! is_wp_error( $term ) ) ? $term->name : ( '#' . (int) $cat_id );
				}
				echo esc_html( implode( ', ', $names ) );
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}

		echo '<h3>' . esc_html__( 'Добавить группу', 'rvn-compare' ) . '</h3>';
		echo '<p>';

		$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		echo '<select name="category_groups_new[]" multiple size="8" class="regular-text">';
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$disabled = in_array( (int) $term->term_id, $taken, true ) ? ' disabled="disabled"' : '';
				printf(
					'<option value="%1$d"%2$s%3$s>%4$s</option>',
					(int) $term->term_id,
					$disabled,
					'',
					esc_html( $term->name )
				);
			}
		}
		echo '</select>';
		echo '</p>';
		echo '<p><label for="category_groups_new_name">' . esc_html__( 'Название группы', 'rvn-compare' ) . '</label> ';
		echo '<input type="text" id="category_groups_new_name" name="category_groups_new_name" class="regular-text" placeholder="' . esc_attr__( 'Например: Смартфоны', 'rvn-compare' ) . '" /></p>';
		echo '<p class="description">' . esc_html__( 'Выберите одну или несколько категорий (Ctrl/Cmd — множественный выбор) и введите название.', 'rvn-compare' ) . '</p>';
	}

	/**
	 * Безопасно читает значение из массива настроек.
	 *
	 * @param array  $all     Настройки.
	 * @param string $key     Ключ.
	 * @param mixed  $default Дефолт.
	 * @return mixed
	 */
	private function setting_value( $all, $key, $default = '' ) {
		return isset( $all[ $key ] ) ? $all[ $key ] : $default;
	}

	private function render_design_tab( $all ) {
		$settings = RVN_Compare_Settings::instance();
		$design   = (array) $this->setting_value( $all, 'design', array() );
		$def      = (array) $settings->defaults()['design'];

		echo '<div class="rvn-compare-design">';
		echo '<div class="rvn-compare-design__form">';

		// ---- Цвета ----
		echo '<h2 class="title">' . esc_html__( 'Цвета', 'rvn-compare' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		$colors = array(
			'table_bg'      => __( 'Фон таблицы', 'rvn-compare' ),
			'header_bg'     => __( 'Фон шапки (фото/название)', 'rvn-compare' ),
			'label_bg'      => __( 'Фон строк-названий', 'rvn-compare' ),
			'group_bg'      => __( 'Фон заголовков групп', 'rvn-compare' ),
			'group_soft_bg' => __( 'Мягкий фон групп', 'rvn-compare' ),
			'text'          => __( 'Текст значений', 'rvn-compare' ),
			'value_text'    => __( 'Текст значений (таблица)', 'rvn-compare' ),
			'label_text'    => __( 'Названия полей', 'rvn-compare' ),
			'accent'        => __( 'Акцент ([?], вкладки, бейдж)', 'rvn-compare' ),
			'diff_bg'       => __( 'Подсветка различий', 'rvn-compare' ),
			'arrow_bg'      => __( 'Фон стрелок слайдера', 'rvn-compare' ),
			'floating_bg'   => __( 'Фон плавающей панели', 'rvn-compare' ),
			'border'        => __( 'Рамка таблицы', 'rvn-compare' ),
		);
		$this->design_color_row( 'colors', 'table_bg', $colors['table_bg'], $design, $def, __( 'Общий фон области таблицы.', 'rvn-compare' ) );
		$this->design_color_row( 'colors', 'header_bg', $colors['header_bg'], $design, $def, __( 'Фон колонок с фото и названием товара.', 'rvn-compare' ) );
		$this->design_color_row( 'colors', 'label_bg', $colors['label_bg'], $design, $def, __( 'Полоска с названием характеристики (слева).', 'rvn-compare' ) );
		$this->design_color_row( 'colors', 'group_bg', $colors['group_bg'], $design, $def, __( 'Полноширинные заголовки групп.', 'rvn-compare' ) );
		$this->design_color_row( 'colors', 'group_soft_bg', $colors['group_soft_bg'], $design, $def, __( 'Используется при включённом «мягком фоне группы».', 'rvn-compare' ) );
		$this->design_color_row( 'colors', 'text', $colors['text'], $design, $def, __( 'Базовый цвет текста.', 'rvn-compare' ) );
		$this->design_color_row( 'colors', 'value_text', $colors['value_text'], $design, $def, __( 'Цвет значений в ячейках.', 'rvn-compare' ) );
		$this->design_color_row( 'colors', 'label_text', $colors['label_text'], $design, $def, __( 'Заголовки всегда чёрные по умолчанию.', 'rvn-compare' ) );
		$this->design_color_row( 'colors', 'accent', $colors['accent'], $design, $def, __( 'Кнопки «Купить», активная вкладка, ссылки, [?] и бейдж.', 'rvn-compare' ) );
		$this->design_color_row( 'colors', 'diff_bg', $colors['diff_bg'], $design, $def, __( 'Подложка ячеек с различающимися значениями.', 'rvn-compare' ) );
		$this->design_color_row( 'colors', 'arrow_bg', $colors['arrow_bg'], $design, $def, __( 'Круглые стрелки прокрутки слайдера.', 'rvn-compare' ) );
		$this->design_color_row( 'colors', 'floating_bg', $colors['floating_bg'], $design, $def, __( 'Фиксированная панель при прокрутке.', 'rvn-compare' ) );
		$this->design_color_row( 'colors', 'border', $colors['border'], $design, $def, __( 'Рамки и разделители внутри таблицы.', 'rvn-compare' ) );
		echo '</tbody></table>';

		// ---- Типографика ----
		echo '<h2 class="title">' . esc_html__( 'Типографика', 'rvn-compare' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		$this->design_number_row( 'types', 'value_size', __( 'Размер значений, px', 'rvn-compare' ), $design, $def, __( 'Размер текста ячеек (по умолчанию 14).', 'rvn-compare' ) );
		$this->design_number_row( 'types', 'label_size', __( 'Размер названий, px', 'rvn-compare' ), $design, $def, __( 'Размер подписей характеристик (по умолчанию 13).', 'rvn-compare' ) );
		$this->design_number_row( 'types', 'group_size', __( 'Размер групп, px', 'rvn-compare' ), $design, $def, __( 'Размер заголовков групп (по умолчанию 14).', 'rvn-compare' ) );
		$this->design_number_row( 'types', 'label_weight', __( 'Насыщенность названий', 'rvn-compare' ), $design, $def, __( '400–900 (по умолчанию 600).', 'rvn-compare' ) );
		$this->design_number_row( 'types', 'value_weight', __( 'Насыщенность значений', 'rvn-compare' ), $design, $def, __( '400–900 (по умолчанию 400).', 'rvn-compare' ) );
		echo '</tbody></table>';

		// ---- Геометрия ----
		echo '<h2 class="title">' . esc_html__( 'Геометрия', 'rvn-compare' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		$this->design_number_row( 'geometry', 'radius', __( 'Скругление, px', 'rvn-compare' ), $design, $def, __( 'Радиус углов таблицы (по умолчанию 12).', 'rvn-compare' ) );
		$this->design_number_row( 'geometry', 'cell_padding', __( 'Паддинги ячеек, px', 'rvn-compare' ), $design, $def, __( 'Внутренний отступ ячеек (по умолчанию 8).', 'rvn-compare' ) );
		$this->design_number_row( 'geometry', 'photo_height', __( 'Высота фото, px', 'rvn-compare' ), $design, $def, __( 'Высота рамки фото в шапке (по умолчанию 120).', 'rvn-compare' ) );
		$this->design_select_row( 'geometry', 'photo_fit', __( 'Подгонка фото (object-fit)', 'rvn-compare' ), $design, $def, array(
			'contain' => __( 'Вписать целиком (contain)', 'rvn-compare' ),
			'cover'   => __( 'Заполнить (cover)', 'rvn-compare' ),
		), __( 'Как фото вписывается в рамку (R2-14).', 'rvn-compare' ) );
		echo '</tbody></table>';

		// ---- Кнопки «Купить» ----
		echo '<h2 class="title">' . esc_html__( 'Кнопка «Купить» в таблице', 'rvn-compare' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Что показывать в трёх местах таблицы: стандартную кнопку «Купить», свой шорткод или ничего.', 'rvn-compare' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';
		$this->design_select_row( 'behavior', 'buy_header', __( 'В шапке (под товаром)', 'rvn-compare' ), $design, $def, array(
			'buy'       => __( 'Кнопка «Купить»', 'rvn-compare' ),
			'shortcode' => __( 'Мой шорткод', 'rvn-compare' ),
			'hidden'    => __( 'Скрыть', 'rvn-compare' ),
		), __( 'Кнопка под названием/ценой товара (R4-03).', 'rvn-compare' ) );
		$this->design_select_row( 'behavior', 'buy_bottom', __( 'Внизу таблицы', 'rvn-compare' ), $design, $def, array(
			'buy'       => __( 'Кнопка «Купить»', 'rvn-compare' ),
			'shortcode' => __( 'Мой шорткод', 'rvn-compare' ),
			'hidden'    => __( 'Скрыть', 'rvn-compare' ),
		), __( 'Нижний ряд таблицы. Шорткод для шапки/низа выводится в самих местах с 0 аргументами.', 'rvn-compare' ) );
		$this->design_select_row( 'behavior', 'buy_floating', __( 'В плавающей панели', 'rvn-compare' ), $design, $def, array(
			'buy'       => __( 'Кнопка «Купить»', 'rvn-compare' ),
			'shortcode' => __( 'Мой шорткод', 'rvn-compare' ),
			'hidden'    => __( 'Скрыть', 'rvn-compare' ),
		), __( 'Компактная панель при прокрутке. Если скрыто — показывается фото.', 'rvn-compare' ) );
		echo '</tbody></table>';

		// Мягкий фон групп.
		$soft = $this->setting_value( $design, 'behavior', array() );
		$soft = is_array( $soft ) ? $soft : array();
		$checked = isset( $soft['soft_bg_enabled'] ) ? $soft['soft_bg_enabled'] : $def['behavior']['soft_bg_enabled'];
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Мягкий фон групп', 'rvn-compare' ) . '</th><td>';
		printf( '<label><input type="checkbox" name="design[behavior][soft_bg_enabled]" value="1" %s /> %s</label>', checked( '1', (string) $checked, false ), esc_html__( 'Включено', 'rvn-compare' ) );
		echo '<p class="description">' . esc_html__( 'Добавлять мягкую цветную подложку заголовкам групп.', 'rvn-compare' ) . '</p>';
		echo '</td></tr>';
		echo '</tbody></table>';

		echo '</div>'; // .rvn-compare-design__form

		// ---- Живой предпросмотр (sticky) ----
		echo '<div class="rvn-compare-design__preview">';
		$this->render_design_preview( $all );

		// Уже сохранённые значения дизайна — как исходные CSS-переменные
		// превью (JS обновит их мгновенно по мере ввода в форму). CSS собран
		// исключительно из санитизированных значений (hex/absint/esc_attr).
		echo '<style id="rvn-compare-design-css">' . $settings->design_css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Возвращает значение дизайн-настройки (секция/поле) с фолбэком на дефолт.
	 *
	 * @param array  $design  Текущий массив дизайна.
	 * @param string $section Секция.
	 * @param string $field   Поле.
	 * @param array  $def     Дефолты дизайна.
	 * @return mixed
	 */
	private function design_setting( $design, $section, $field, $def ) {
		if ( isset( $design[ $section ][ $field ] ) && '' !== (string) $design[ $section ][ $field ] ) {
			return $design[ $section ][ $field ];
		}
		return isset( $def[ $section ][ $field ] ) ? $def[ $section ][ $field ] : '';
	}

	/**
	 * Печатает строку выбора цвета (color picker WP) для вкладки «Дизайн таблицы».
	 *
	 * @param string $section Секция дизайна.
	 * @param string $field   Поле.
	 * @param string $label   Подпись.
	 * @param array  $design  Текущий дизайн.
	 * @param array  $def     Дефолты.
	 * @param string $help    Пояснение.
	 * @return void
	 */
	private function design_color_row( $section, $field, $label, $design, $def, $help = '' ) {
		$value = $this->design_setting( $design, $section, $field, $def );
		printf( '<tr><th scope="row"><label for="%1$s">%2$s</label></th><td>', esc_attr( 'd_' . $field ), esc_html( $label ) );
		printf(
			'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="rvn-compare-color" data-default-color="%4$s" />',
			esc_attr( 'd_' . $field ),
			esc_attr( 'design[' . $section . '][' . $field . ']' ),
			esc_attr( $value ),
			esc_attr( $value )
		);
		if ( $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Печатает числовую строку вкладки «Дизайн таблицы».
	 *
	 * @param string $section Секция.
	 * @param string $field   Поле.
	 * @param string $label   Подпись.
	 * @param array  $design  Текущий дизайн.
	 * @param array  $def     Дефолты.
	 * @param string $help    Пояснение.
	 * @return void
	 */
	private function design_number_row( $section, $field, $label, $design, $def, $help = '' ) {
		$value = $this->design_setting( $design, $section, $field, $def );
		printf( '<tr><th scope="row"><label for="%1$s">%2$s</label></th><td>', esc_attr( 'd_' . $field ), esc_html( $label ) );
		printf(
			'<input type="number" id="%1$s" name="%2$s" value="%3$s" min="0" max="9999" class="small-text" />',
			esc_attr( 'd_' . $field ),
			esc_attr( 'design[' . $section . '][' . $field . ']' ),
			esc_attr( $value )
		);
		if ( $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Печатает строку-селект вкладки «Дизайн таблицы».
	 *
	 * @param string $section Секция.
	 * @param string $field   Поле.
	 * @param string $label   Подпись.
	 * @param array  $design  Текущий дизайн.
	 * @param array  $def     Дефолты.
	 * @param array  $options Карта значение => подпись.
	 * @param string $help    Пояснение.
	 * @return void
	 */
	private function design_select_row( $section, $field, $label, $design, $def, $options, $help = '' ) {
		$value = $this->design_setting( $design, $section, $field, $def );
		printf( '<tr><th scope="row"><label for="%1$s">%2$s</label></th><td>', esc_attr( 'd_' . $field ), esc_html( $label ) );
		printf( '<select id="%1$s" name="%2$s">', esc_attr( 'd_' . $field ), esc_attr( 'design[' . $section . '][' . $field . ']' ) );
		foreach ( $options as $opt_key => $opt_label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $opt_key ), selected( $value, $opt_key, false ), esc_html( $opt_label ) );
		}
		echo '</select>';
		if ( $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Рендерит демо-таблицу живого предпросмотра вкладки «Дизайн таблицы».
	 *
	 * Статическая разметка двух фейковых товаров; JS (admin.js) по вводу
	 * мгновенно пересчитывает CSS-переменные из полей формы.
	 *
	 * @param array $all Текущие настройки.
	 * @return void
	 */
	private function render_design_preview( $all ) {
		echo '<h2 class="title">' . esc_html__( 'Живой предпросмотр', 'rvn-compare' ) . '</h2>';

		echo '<div class="rvn-compare-preview rvn-compare-table" data-rvn-compare-design-preview>' . "
";

		echo '<div class="rvn-compare-tabs"><button type="button" class="rvn-compare-tab is-active">' . esc_html__( 'Смартфоны', 'rvn-compare' ) . '</button><button type="button" class="rvn-compare-tab">' . esc_html__( 'Ноутбуки', 'rvn-compare' ) . '</button></div>';

		echo '<div class="rvn-compare-scroller"><div class="rvn-compare-clip"><div class="rvn-compare-track"><div class="rvn-compare-columns">' . "
";
		echo '<div class="rvn-compare-corner" aria-hidden="true"></div>';

		$demo = array(
			array( 'name' => __( 'Смартфон A', 'rvn-compare' ), 'price' => '23 990 ₽' ),
			array( 'name' => __( 'Смартфон B', 'rvn-compare' ), 'price' => '27 490 ₽' ),
		);
		foreach ( $demo as $d ) {
			echo '<div class="rvn-compare-col rvn-compare-col--header"><span class="rvn-compare-col__thumb"></span><span class="rvn-compare-col__title">' . esc_html( $d['name'] ) . '</span><span class="rvn-compare-col__price">' . esc_html( $d['price'] ) . '</span><a class="rvn-compare-buy" href="#">' . esc_html__( 'Купить', 'rvn-compare' ) . '</a></div>';
		}
		echo '</div>'; // /columns

		echo '<div class="rvn-compare-rows"><div class="rvn-compare-group">' . "
";
		echo '<button type="button" class="rvn-compare-group__head"><span class="rvn-compare-group__title">' . esc_html__( 'Основное', 'rvn-compare' ) . '</span><span class="rvn-compare-group__arrow" aria-hidden="true">▲</span></button>';
		echo '<div class="rvn-compare-group__body">' . "
";

		$rows = array(
			array( __( 'Экран', 'rvn-compare' ), '6.1"', '6.7"' ),
			array( __( 'Память', 'rvn-compare' ), '128 ГБ', '256 ГБ' ),
		);
		foreach ( $rows as $r ) {
			echo '<div class="rvn-compare-row' . ( $r[1] !== $r[2] ? ' has-diff' : '' ) . '"><div class="rvn-compare-row__label"><span>' . esc_html( $r[0] ) . '</span></div><div class="rvn-compare-row__values">';
			echo '<span class="rvn-compare-row__value">' . esc_html( $r[1] ) . '</span><span class="rvn-compare-row__value">' . esc_html( $r[2] ) . '</span>';
			echo '</div></div>';
		}

		echo '</div></div></div>'; // /body /group /rows
		echo '</div></div></div></div>'; // /track /clip /scroller
		echo '</div>'; // /preview
	}

	/**
	 * Вкладка «Дизайн элементов и кнопок» (заглушка первого шага).
	 *
	 * @param array $all Текущие настройки.
	 * @return void
	 */
	private function render_elements_tab( $all ) {
		echo '<p>' . esc_html__( 'Конструктор кнопок и тостов — в Этапе 2 (см. живое ТЗ §6.4). Базовые тексты кнопок настраиваются ниже.', 'rvn-compare' ) . '</p>';

		echo '<table class="form-table" role="presentation"><tbody>';
		$this->text_field( $all, 'button_text', __( 'Текст кнопки «Сравнить»', 'rvn-compare' ) );
		$this->text_field( $all, 'button_added_text', __( 'Текст кнопки «Уже в сравнении»', 'rvn-compare' ) );
		$this->text_field( $all, 'counter_button_text', __( 'Текст кнопки-счётчика', 'rvn-compare' ) );
		echo '</tbody></table>';
	}

	/**
	 * Вкладка «Справка».
	 *
	 * @return void
	 */
	private function render_help_tab() {
		echo '<p>' . esc_html__( 'Краткая справка по шорткодам плагина.', 'rvn-compare' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Шорткод', 'rvn-compare' ) . '</th><th>' . esc_html__( 'Назначение', 'rvn-compare' ) . '</th></tr></thead><tbody>';
		foreach ( RVN_Compare_Shortcodes::registry() as $tag => $info ) {
			printf(
				'<tr><td><code>%s</code></td><td>%s</td></tr>',
				esc_html( $tag ),
				esc_html( $info['description'] )
			);
		}
		echo '</tbody></table>';
	}

	/**
	 * Карта значений позиций кнопки (whitelist значений).
	 *
	 * @return array
	 */
	private function button_positions() {
		return array(
			'after_add_to_cart'  => __( 'После кнопки «Купить»', 'rvn-compare' ),
			'before_add_to_cart' => __( 'Перед кнопкой «Купить»', 'rvn-compare' ),
			'above_title'        => __( 'Над заголовком', 'rvn-compare' ),
			'below_title'        => __( 'Под заголовком', 'rvn-compare' ),
			'overlay_tl'         => __( 'На изображении: сверху слева', 'rvn-compare' ),
			'overlay_tr'         => __( 'На изображении: сверху справа', 'rvn-compare' ),
			'overlay_bl'         => __( 'На изображении: снизу слева', 'rvn-compare' ),
			'overlay_br'         => __( 'На изображении: снизу справа', 'rvn-compare' ),
			'disabled'           => __( 'Отключить (для использования шорткода)', 'rvn-compare' ),
		);
	}

	/**
	 * Обрабатывает сохранение настроек (admin-post).
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! current_user_can( $this->settings_capability() ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'rvn-compare' ) );
		}
		check_admin_referer( 'rvn_compare_save', 'rvn_compare_nonce' );

		$settings = RVN_Compare_Settings::instance();
		$tab      = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'general';

		/*
		 * Действия формы исключений товаров (отдельные формы на вкладке
		 * «Основное») — обрабатываем до сохранения настроек.
		 */
		if ( 'general' === $tab && isset( $_POST['rvn_compare_exclusion_action'] ) ) {
			$this->handle_exclusion_action();
			$msg = 'exclusion';
		} elseif ( 'fields' === $tab ) {
			// Вкладка «Таблица сравнения»: сложные структуры + скалярные поля.
			$this->handle_fields_save();
			$msg = 'saved';
		} elseif ( 'design' === $tab ) {
			// Вкладка «Дизайн таблицы»: плоские ключи design[секция][поле].
			$settings->save_from_request( wp_unslash( $_POST ) );
			$msg = 'saved';
		} elseif ( 'general' === $tab && isset( $_POST['rvn_compare_action'] ) ) {
			$action = sanitize_key( (string) $_POST['rvn_compare_action'] );

			if ( 'create_page' === $action ) {
				$this->create_compare_page();
				$msg = 'page_created';
			} elseif ( 'reset_page' === $action ) {
				$this->reset_compare_page();
				$msg = 'page_reset';
			} else {
				$msg = 'saved';
			}
		} elseif ( isset( $_POST['rvn_compare_reset'] ) && $_POST['rvn_compare_reset'] ) {
			// Полный сброс: пишем чистые дефолты.
			$settings->replace( $settings->defaults() );
			$msg = 'reset';
		} else {
			// $_POST передаём как есть — wp_unslash выполняется внутри.
			$settings->save_from_request( wp_unslash( $_POST ) );
			$msg = 'saved';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'   => self::SCREEN_SLUG,
					'tab'    => $tab,
					'notice' => $msg,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Обрабатывает сохранение вкладки «Таблица сравнения».
	 *
	 * Сначала применяет скалярные поля (через save_from_request), затем —
	 * сложные структуры (группы характеристик, ACF-meta) и создание группы
	 * категорий через save_compare_ui.
	 *
	 * @return void
	 */
	private function handle_fields_save() {
		$settings = RVN_Compare_Settings::instance();

		// Скалярные чекбоксы/текст этой вкладки.
		$settings->save_from_request( wp_unslash( $_POST ) );

		// Сложные структуры (пока только ЧТО прислано в POST).
		$complex = array();

		if ( isset( $_POST['field_groups'] ) && is_array( $_POST['field_groups'] ) ) {
			$complex['field_groups'] = $_POST['field_groups']; // wp_unslash применится внутри.
		}

		if ( isset( $_POST['acf_meta_fields'] ) && is_array( $_POST['acf_meta_fields'] ) ) {
			$complex['acf_meta_fields'] = $_POST['acf_meta_fields'];
		}

		/*
		 * Создание группы категорий: выбранные категории + название приходят
		 * из полей category_groups_new[] / category_groups_new_name.
		 * Уже существующие группы передаются как есть (категории без изменения).
		 */
		if ( isset( $_POST['category_groups_new'] ) && is_array( $_POST['category_groups_new'] ) ) {
			$new_cats = array_values( array_filter( array_map( 'absint', $_POST['category_groups_new'] ) ) );
			$new_name = isset( $_POST['category_groups_new_name'] ) ? sanitize_text_field( wp_unslash( $_POST['category_groups_new_name'] ) ) : '';
			if ( $new_name && ! empty( $new_cats ) ) {
				$current = RVN_Compare_Categories::instance()->groups();
				$current[] = array( 'name' => $new_name, 'cats' => $new_cats );
				$complex['category_groups'] = $current;
			}
		} elseif ( isset( $_POST['category_groups'] ) && is_array( $_POST['category_groups'] ) ) {
			$complex['category_groups'] = $_POST['category_groups'];
		}

		if ( ! empty( $complex ) ) {
			$settings->save_compare_ui( $complex );
		}
	}

	/**
	 * Создаёт страницу сравнения и назначает её в настройках.
	 *
	 * @return void
	 */
	private function create_compare_page() {
		$manager = RVN_Compare_Page_Manager::instance();

		if ( $manager->find_by_slug( RVN_Compare_Page_Manager::SLUG ) ) {
			// Страница уже есть (в т.ч. в корзине) — восстанавливаем/назначаем.
			$manager->ensure_page();
			return;
		}

		$manager->create_page();
	}

	/**
	 * Сбрасывает содержимое страницы сравнения (только шорткод).
	 *
	 * @return void
	 */
	private function reset_compare_page() {
		$manager = RVN_Compare_Page_Manager::instance();
		$manager->reset_page_content();
	}

	/**
	 * Рендерит секцию «Страница сравнения» на вкладке «Основное».
	 *
	 * @param array $all Текущие настройки.
	 * @return void
	 */
	private function render_page_section( $all ) {
		$manager = RVN_Compare_Page_Manager::instance();
		$page_id = (int) ( isset( $all['compare_page_id'] ) ? $all['compare_page_id'] : 0 );
		$exists  = $page_id && get_post( $page_id );

		echo '<h2 class="title">' . esc_html__( 'Страница сравнения', 'rvn-compare' ) . '</h2>';

		$pages = get_pages( array( 'post_status' => 'publish,private,draft', 'sort_column' => 'post_title' ) );

		echo '<table class="form-table" role="presentation"><tbody>';

		// Выбор текущей страницы сравнения.
		echo '<tr><th scope="row"><label for="compare_page_id">' . esc_html__( 'Страница сравнения', 'rvn-compare' ) . '</label></th><td>';
		echo '<select id="compare_page_id" name="compare_page_id">';
		echo '<option value="0">' . esc_html__( '— Не выбрана (использовать шорткод вручную) —', 'rvn-compare' ) . '</option>';
		foreach ( $pages as $page ) {
			printf(
				'<option value="%1$d" %2$s>%3$s</option>',
				(int) $page->ID,
				selected( $page_id, (int) $page->ID, false ),
				esc_html( get_the_title( $page ) )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Страница, на которую ведёт кнопка-счётчик и где живёт таблица сравнения.', 'rvn-compare' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';

		// Кнопки создания и сброса — отдельными формами (их действия зовут свои обработчики).
		echo '<p class="rvn-compare-page-actions">';
		if ( $exists ) {
			echo '<strong>' . esc_html__( 'Текущая страница:', 'rvn-compare' ) . '</strong> ';
			echo esc_html( get_the_title( $page_id ) );
			echo ' (<code>' . esc_html( get_post_field( 'post_name', $page_id ) ) . '</code>)';
			echo '</p>';

			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rvn-compare-inline-form">';
			wp_nonce_field( 'rvn_compare_save', 'rvn_compare_nonce' );
			echo '<input type="hidden" name="action" value="rvn_compare_save" />';
			echo '<input type="hidden" name="tab" value="general" />';
			echo '<input type="hidden" name="rvn_compare_action" value="reset_page" />';
			submit_button(
				__( 'Сбросить страницу (останется только шорткод)', 'rvn-compare' ),
				'secondary',
				'submit',
				true,
				array( 'onclick' => 'return confirm(rvnCompareAdmin.confirmResetPage);' )
			);
			echo '</form>';
		} else {
			echo '</p>';

			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rvn-compare-inline-form">';
			wp_nonce_field( 'rvn_compare_save', 'rvn_compare_nonce' );
			echo '<input type="hidden" name="action" value="rvn_compare_save" />';
			echo '<input type="hidden" name="tab" value="general" />';
			echo '<input type="hidden" name="rvn_compare_action" value="create_page" />';
			submit_button( __( 'Создать страницу', 'rvn-compare' ), 'primary', 'submit', true );
			echo '</form>';
		}

		if ( $exists ) {
			$view_url = get_permalink( $page_id );
			echo '<p>';
			echo '<a href="' . esc_url( $view_url ) . '" target="_blank">' . esc_html__( 'Посмотреть страницу', 'rvn-compare' ) . '</a>';
			echo '</p>';
		}
	}

	/**
	 * Выводит уведомление после сохранения/действия.
	 *
	 * @param string $type Тип уведомления (saved/reset/page_created/page_reset).
	 * @return void
	 */
	private function render_notice( $type ) {
		$messages = array(
			'saved'        => array( 'notice-success', __( 'Настройки сохранены.', 'rvn-compare' ) ),
			'reset'        => array( 'notice-success', __( 'Настройки сброшены к значениям по умолчанию.', 'rvn-compare' ) ),
			'page_created' => array( 'notice-success', __( 'Страница сравнения создана и назначена.', 'rvn-compare' ) ),
			'page_reset'   => array( 'notice-success', __( 'Содержимое страницы сравнения сброшено (остался только шорткод).', 'rvn-compare' ) ),
		);

		// Действия исключений приходят через transient с под-типом.
		if ( 'exclusion' === $type ) {
			$sub = get_transient( 'rvn_compare_notice' );
			delete_transient( 'rvn_compare_notice' );

			$exclusion_messages = array(
				'exclusion_saved'   => array( 'notice-success', __( 'Исключение товара обновлено.', 'rvn-compare' ) ),
				'exclusion_removed' => array( 'notice-success', __( 'Товар удалён из исключений.', 'rvn-compare' ) ),
				'exclusion_invalid' => array( 'notice-error', __( 'Не удалось добавить исключение: товар не найден или ID не указан.', 'rvn-compare' ) ),
			);

			if ( $sub && isset( $exclusion_messages[ $sub ] ) ) {
				printf(
					'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
					esc_attr( $exclusion_messages[ $sub ][0] ),
					esc_html( $exclusion_messages[ $sub ][1] )
				);
			}
			return;
		}

		if ( ! isset( $messages[ $type ] ) ) {
			return;
		}

		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $type ][0] ),
			esc_html( $messages[ $type ][1] )
		);
	}

	/**
	 * Рендерит секцию «Исключения товаров» на вкладке «Основное».
	 *
	 * Слева — добавление (поиск WooCommerce + ручной ID), справа — список
	 * уже исключённых товаров с флагами контекстов и удалением.
	 *
	 * @return void
	 */
	private function render_exclusions_section() {
		echo '<h2 class="title">' . esc_html__( 'Исключения товаров', 'rvn-compare' ) . '</h2>';
		echo '<p class="description">'
			. esc_html__( 'Товары, для которых Autobutton не выводится. Приоритет выше настроек позиции кнопки.', 'rvn-compare' )
			. '</p>';

		echo '<div class="rvn-compare-exclusions">';

		// ---- Добавление ----
		echo '<div class="rvn-compare-exclusions__add">';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'rvn_compare_save', 'rvn_compare_nonce' );
		echo '<input type="hidden" name="action" value="rvn_compare_save" />';
		echo '<input type="hidden" name="tab" value="general" />';
		echo '<input type="hidden" name="rvn_compare_exclusion_action" value="add" />';

		// Поле ввода ID товара (ручной ввод; можно несколько через запятую).
		echo '<p>';
		echo '<label for="rvn_compare_manual_id">' . esc_html__( 'ID товара', 'rvn-compare' ) . '</label> ';
		echo '<input type="text" id="rvn_compare_manual_id" name="rvn_compare_manual_id" class="regular-text" placeholder="' . esc_attr__( 'например 123 или 12, 34, 56', 'rvn-compare' ) . '" />';
		echo '<br /><span class="description">' . esc_html__( 'ID можно ввести вручную (несколько — через запятую). Автопоиск по названию появится позже.', 'rvn-compare' ) . '</span>';
		echo '</p>';

		// Флаги контекстов.
		echo '<p>';
		echo '<label><input type="checkbox" name="rvn_compare_exc_archive" value="1" checked="checked" /> '
			. esc_html__( 'Отключить на карточках товара', 'rvn-compare' ) . '</label><br />';
		echo '<label><input type="checkbox" name="rvn_compare_exc_single" value="1" checked="checked" /> '
			. esc_html__( 'Отключить на странице товара', 'rvn-compare' ) . '</label>';
		echo '</p>';

		submit_button( __( 'Добавить в исключения', 'rvn-compare' ), 'secondary', 'submit', true );
		echo '</form>';
		echo '</div>';

		// ---- Список исключённых ----
		echo '<div class="rvn-compare-exclusions__list">';
		$excluded = RVN_Compare_Settings::instance()->excluded_products();

		if ( empty( $excluded ) ) {
			echo '<p class="description">' . esc_html__( 'Исключений пока нет.', 'rvn-compare' ) . '</p>';
		} else {
			echo '<table class="widefat striped">';
			echo '<thead><tr><th>ID</th><th>' . esc_html__( 'Товар', 'rvn-compare' ) . '</th><th>' . esc_html__( 'Карточка', 'rvn-compare' ) . '</th><th>' . esc_html__( 'Страница', 'rvn-compare' ) . '</th><th></th></tr></thead>';
			echo '<tbody>';
			foreach ( $excluded as $id => $flags ) {
				$title = function_exists( 'wc_get_product' ) ? ( function_exists( 'get_the_title' ) ? get_the_title( (int) $id ) : '' ) : '';
				$long  = mb_strlen( (string) $title, 'UTF-8' ) > 40;
				echo '<tr>';
				echo '<td><code>' . esc_html( (string) $id ) . '</code></td>';
				echo '<td>' . esc_html( $long ? mb_substr( (string) $title, 0, 40, 'UTF-8' ) . '…' : $title ) . '</td>';
				echo '<td>';
				echo ! empty( $flags['archive'] ) ? '<span class="dashicons dashicons-yes"></span>' : '<span class="dashicons dashicons-minus"></span>';
				echo '</td><td>';
				echo ! empty( $flags['single'] ) ? '<span class="dashicons dashicons-yes"></span>' : '<span class="dashicons dashicons-minus"></span>';
				echo '</td><td>';
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rvn-compare-inline-del">';
				wp_nonce_field( 'rvn_compare_save', 'rvn_compare_nonce' );
				echo '<input type="hidden" name="action" value="rvn_compare_save" />';
				echo '<input type="hidden" name="tab" value="general" />';
				echo '<input type="hidden" name="rvn_compare_exclusion_action" value="remove" />';
				echo '<input type="hidden" name="rvn_compare_exclusion_id" value="' . (int) $id . '" />';
				echo '<button type="submit" class="button button-link button-link-delete" aria-label="' . esc_attr__( 'Удалить из исключений', 'rvn-compare' ) . '">✕</button>';
				echo '</form>';
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Обрабатывает действия формы исключений (add/remove).
	 *
	 * @return void
	 */
	private function handle_exclusion_action() {
		$action = sanitize_key( (string) $_POST['rvn_compare_exclusion_action'] );
		$settings = RVN_Compare_Settings::instance();

		if ( 'add' === $action ) {
			$raw_ids = isset( $_POST['rvn_compare_manual_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rvn_compare_manual_id'] ) ) : '';
			$ids     = $this->parse_product_ids( $raw_ids );

			if ( empty( $ids ) ) {
				set_transient( 'rvn_compare_notice', 'exclusion_invalid', 30 );
				return;
			}

			$archive = isset( $_POST['rvn_compare_exc_archive'] ) ? 1 : 0;
			$single  = isset( $_POST['rvn_compare_exc_single'] ) ? 1 : 0;

			$applied = 0;
			foreach ( $ids as $id ) {
				if ( 0 === $archive && 0 === $single ) {
					// Обе галочки сняты — удаляем исключение товара.
					$settings->remove_excluded( $id );
				} else {
					$settings->set_excluded( $id, $archive, $single );
				}
				$applied++;
			}

			set_transient( 'rvn_compare_notice', empty( $applied ) ? 'exclusion_invalid' : 'exclusion_saved', 30 );
		} elseif ( 'remove' === $action ) {
			$id = isset( $_POST['rvn_compare_exclusion_id'] ) ? absint( $_POST['rvn_compare_exclusion_id'] ) : 0;
			$settings->remove_excluded( $id );
			set_transient( 'rvn_compare_notice', 'exclusion_removed', 30 );
		}
	}

	/**
	 * Разбирает пользовательский ввод ID («12, 34» или «12 34») в массив целых.
	 *
	 * @param string $raw Строка ввода.
	 * @return int[]
	 */
	private function parse_product_ids( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return array();
		}

		$parts = preg_split( '/[,\s]+/', $raw );
		$ids   = array();
		foreach ( $parts as $part ) {
			$id = absint( $part );
			if ( $id && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	// ---- Хелперы полей формы (общие для всех вкладок). ----

	/**
	 * Печатает числовое поле настройки.
	 *
	 * @param array  $all   Текущие настройки.
	 * @param string $key   Ключ настройки.
	 * @param string $label Подпись.
	 * @param int    $min   Минимум.
	 * @param int    $max   Максимум.
	 * @param string $help  Поясняющий текст.
	 * @return void
	 */
	private function number_field( $all, $key, $label, $min, $max, $help = '' ) {
		$value = isset( $all[ $key ] ) ? $all[ $key ] : '';
		printf( '<tr><th scope="row"><label for="%1$s">%2$s</label></th><td>', esc_attr( $key ), esc_html( $label ) );
		printf(
			'<input type="number" id="%1$s" name="%1$s" value="%2$s" min="%3$d" max="%4$d" class="small-text" />',
			esc_attr( $key ),
			esc_attr( $value ),
			esc_attr( $min ),
			esc_attr( $max )
		);
		if ( $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Печатает текстовое поле настройки.
	 *
	 * @param array  $all   Текущие настройки.
	 * @param string $key   Ключ настройки.
	 * @param string $label Подпись.
	 * @param string $help  Поясняющий текст.
	 * @return void
	 */
	private function text_field( $all, $key, $label, $help = '' ) {
		$value = isset( $all[ $key ] ) ? $all[ $key ] : '';
		printf( '<tr><th scope="row"><label for="%1$s">%2$s</label></th><td>', esc_attr( $key ), esc_html( $label ) );
		printf(
			'<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text" />',
			esc_attr( $key ),
			esc_attr( $value )
		);
		if ( $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Печатает выбор из селекта.
	 *
	 * @param array  $all     Текущие настройки.
	 * @param string $key     Ключ настройки.
	 * @param string $label   Подпись.
	 * @param array  $options Карта значение => подпись.
	 * @param string $help    Поясняющий текст.
	 * @return void
	 */
	private function select_field( $all, $key, $label, $options, $help = '' ) {
		$value = isset( $all[ $key ] ) ? $all[ $key ] : '';
		printf( '<tr><th scope="row"><label for="%1$s">%2$s</label></th><td>', esc_attr( $key ), esc_html( $label ) );
		printf( '<select id="%1$s" name="%1$s">', esc_attr( $key ) );
		foreach ( $options as $opt_key => $opt_label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $opt_key ),
				selected( $value, $opt_key, false ),
				esc_html( $opt_label )
			);
		}
		echo '</select>';
		if ( $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Печатает чекбокс-переключатель (да/нет).
	 *
	 * @param array  $all   Текущие настройки.
	 * @param string $key   Ключ настройки.
	 * @param string $label Подпись.
	 * @param string $help  Поясняющий текст.
	 * @return void
	 */
	private function checkbox_field( $all, $key, $label, $help = '' ) {
		$value = isset( $all[ $key ] ) ? $all[ $key ] : '0';
		printf( '<tr><th scope="row">%s</th><td>', esc_html( $label ) );
		printf(
			'<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( $key ),
			checked( '1', (string) $value, false ),
			esc_html__( 'Включено', 'rvn-compare' )
		);
		if ( $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Печатает поле выбора цвета.
	 *
	 * @param array  $all   Текущие настройки.
	 * @param string $key   Ключ настройки.
	 * @param string $label Подпись.
	 * @param string $help  Поясняющий текст.
	 * @return void
	 */
	private function color_field( $all, $key, $label, $help = '' ) {
		$value = isset( $all[ $key ] ) ? $all[ $key ] : '#2563eb';
		printf( '<tr><th scope="row"><label for="%1$s">%2$s</label></th><td>', esc_attr( $key ), esc_html( $label ) );
		printf(
			'<input type="text" id="%1$s" name="%1$s" value="%2$s" class="rvn-compare-color" data-default-color="#2563eb" />',
			esc_attr( $key ),
			esc_attr( $value )
		);
		if ( $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}
}
