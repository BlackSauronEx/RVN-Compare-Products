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

		// ---- Где показывать кнопку ----
		echo '<h2 class="title">' . esc_html__( 'Где показывать кнопку', 'rvn-compare' ) . '</h2>';
		$this->render_visibility_rules( 'archive', __( 'На карточках товара', 'rvn-compare' ), $all );
		$this->render_visibility_rules( 'single', __( 'На странице товара', 'rvn-compare' ), $all );

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
		$this->checkbox_field( $all, 'add_product_button', __( 'Кнопка «Добавить товар»', 'rvn-compare' ), __( 'Показывать кнопку добавления товара с поиском в тулбаре таблицы сравнения (и в пустом состоянии).', 'rvn-compare' ) );
		echo '</tbody></table>';

		// ---- Удаление данных при удалении плагина ----
		$uninstall = isset( $all['uninstall'] ) && is_array( $all['uninstall'] ) ? $all['uninstall'] : array();
		echo '<h2 class="title">' . esc_html__( 'Удаление данных при удалении плагина', 'rvn-compare' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Применяется только при полном удалении плагина. По умолчанию всё выключено.', 'rvn-compare' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';
		$items = array(
			'delete_settings'   => __( 'Удалить настройки', 'rvn-compare' ),
			'delete_user_lists' => __( 'Удалить списки сравнения пользователей', 'rvn-compare' ),
			'delete_page'       => __( 'Удалить созданную страницу', 'rvn-compare' ),
		);
		foreach ( $items as $key => $label ) {
			$checked = ! empty( $uninstall[ $key ] );
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
			printf(
				'<label><input type="checkbox" name="uninstall[%1$s]" value="1" %2$s /> %3$s</label>',
				esc_attr( $key ),
				checked( $checked, true, false ),
				esc_html__( 'Включено', 'rvn-compare' )
			);
			echo '</td></tr>';
		}
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
	 * Секция «Группы характеристик» (переименование, добавление, удаление, drag&drop).
	 *
	 * @param array $all Текущие настройки.
	 * @return void
	 */
	private function render_field_groups_section( $all ) {
		$fields = RVN_Compare_Fields::instance();
		$groups = $fields->group_labels();

		echo '<h2 class="title">' . esc_html__( 'Группы характеристик', 'rvn-compare' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Заголовки-разделители в таблице. Перетаскивайте за ручку, переименовывайте, добавляйте и удаляйте. При удалении группы её поля останутся и попадут в группу «Характеристики».', 'rvn-compare' ) . '</p>';

		echo '<ul class="rvn-compare-sort rvn-compare-sort--groups" data-rvn-compare-sort="groups">';
		foreach ( $groups as $key => $label ) {
			echo '<li class="rvn-compare-sort__item">';
			echo '<span class="rvn-compare-sort__handle" aria-hidden="true">⠿</span>';
			echo '<input type="text" name="field_groups[' . esc_attr( $key ) . ']" value="' . esc_attr( $label ) . '" class="regular-text" />';
			echo '<button type="button" class="button-link rvn-compare-del-group" data-del-group="' . esc_attr( $key ) . '">' . esc_html__( 'Удалить', 'rvn-compare' ) . '</button>';
			echo '</li>';
		}
		echo '</ul>';

		echo '<p>';
		echo '<label for="field_groups_new">' . esc_html__( 'Новая группа', 'rvn-compare' ) . '</label> ';
		echo '<input type="text" id="field_groups_new" name="field_groups_new" class="regular-text" placeholder="' . esc_attr__( 'Название группы', 'rvn-compare' ) . '" />';
		echo '<button type="button" class="button" data-add-group>' . esc_html__( 'Добавить', 'rvn-compare' ) . '</button>';
		echo '</p>';
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
		$groups = $fields->group_labels();

		echo '<h2 class="title">' . esc_html__( 'Поля таблицы', 'rvn-compare' ) . '</h2>';
		echo '<p class="description">'
			. esc_html__( 'Базовые поля можно выключить, переименовать, снабдить подсказкой и перетащить. Атрибуты WooCommerce подтягиваются автоматически.', 'rvn-compare' )
			. '</p>';

		// ---- Базовые (core) поля ----
		$core   = $fields->core_fields_customized();
		$saved  = (array) $this->setting_value( $all, 'core_fields', array() );
		$saved  = empty( $saved ) ? array() : $saved;

		echo '<h3>' . esc_html__( 'Основные поля', 'rvn-compare' ) . '</h3>';
		echo '<div class="rvn-compare-fields-toolbar" data-rvn-fields-list>';
		echo '<input type="search" class="regular-text" data-rvn-fields-search placeholder="' . esc_attr__( 'Поиск по полям…', 'rvn-compare' ) . '" />';
		echo '<button type="button" class="button" data-rvn-fields-all>' . esc_html__( 'Включить все', 'rvn-compare' ) . '</button>';
		echo '<button type="button" class="button" data-rvn-fields-none>' . esc_html__( 'Выключить все', 'rvn-compare' ) . '</button>';
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Кнопки действуют на видимые (отфильтрованные) поля.', 'rvn-compare' ) . '</p>';
		echo '<ul class="rvn-compare-sort rvn-compare-sort--fields" data-rvn-compare-sort="core-fields">';
		foreach ( $core as $field ) {
			$key = $field['key'];
			$override = isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ? $saved[ $key ] : array();
			$enabled = array_key_exists( 'enabled', $override ) ? ! empty( $override['enabled'] ) : ! empty( $field['enabled'] );
			$label   = isset( $override['label'] ) && $override['label'] ? $override['label'] : ( isset( $field['label'] ) ? $field['label'] : $key );
			$hint    = isset( $override['hint'] ) ? $override['hint'] : ( isset( $field['hint'] ) ? $field['hint'] : '' );
			$group   = isset( $override['group'] ) && $override['group'] ? $override['group'] : ( isset( $field['group'] ) ? $field['group'] : 'basic' );

			echo '<li class="rvn-compare-sort__item" data-core-field="' . esc_attr( $key ) . '" data-field-label="' . esc_attr( $label . ' ' . $key ) . '">';
			echo '<span class="rvn-compare-sort__handle" aria-hidden="true">⠿</span>';
			echo '<label class="rvn-compare-sort__toggle"><input type="checkbox" name="core_fields[' . esc_attr( $key ) . '][enabled]" value="1" ' . checked( $enabled, true, false ) . ' /> ' . esc_html__( 'Вкл', 'rvn-compare' ) . '</label>';
			echo '<input type="text" name="core_fields[' . esc_attr( $key ) . '][label]" value="' . esc_attr( $label ) . '" class="regular-text" placeholder="' . esc_attr__( 'Название (пусто = дефолт)', 'rvn-compare' ) . '" />';
			echo '<input type="text" name="core_fields[' . esc_attr( $key ) . '][hint]" value="' . esc_attr( $hint ) . '" class="regular-text" placeholder="' . esc_attr__( 'Подсказка [?]', 'rvn-compare' ) . '" />';
			echo '<select name="core_fields[' . esc_attr( $key ) . '][group]">';
			foreach ( $groups as $gk => $gl ) {
				printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $gk ), selected( $group, $gk, false ), esc_html( $gl ) );
			}
			echo '</select>';
			echo '</li>';
		}
		echo '</ul>';

		// ---- ACF / Custom Meta ----
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
			foreach ( $groups as $gk => $gl ) {
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
			echo '<ul class="rvn-compare-catgroups">';
			foreach ( $groups as $index => $group ) {
				$names = array();
				foreach ( $group['cats'] as $cat_id ) {
					$term = get_term( (int) $cat_id );
					$names[] = ( $term && ! is_wp_error( $term ) ) ? $term->name : ( '#' . (int) $cat_id );
				}
				echo '<li class="rvn-compare-catgroups__item">';
				echo '<input type="text" name="category_groups[' . (int) $index . '][name]" value="' . esc_attr( $group['name'] ) . '" class="regular-text" />';
				foreach ( $group['cats'] as $cat_id ) {
					echo '<input type="hidden" name="category_groups[' . (int) $index . '][cats][]" value="' . (int) $cat_id . '" />';
				}
				echo '<span class="rvn-compare-catgroups__cats">' . esc_html( implode( ', ', $names ) ) . '</span>';
				echo '<button type="button" class="button-link rvn-compare-del-catgroup" data-del-catgroup="' . (int) $index . '">' . esc_html__( 'Удалить', 'rvn-compare' ) . '</button>';
				echo '</li>';
			}
			echo '</ul>';
		}

		echo '<h3>' . esc_html__( 'Добавить группу', 'rvn-compare' ) . '</h3>';
		echo '<p>';

		$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		echo '<select name="category_groups_new[]" multiple size="8" class="regular-text">';
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$disabled = in_array( (int) $term->term_id, $taken, true ) ? ' disabled="disabled"' : '';
				printf(
					'<option value="%1$d"%2$s>%3$s</option>',
					(int) $term->term_id,
					$disabled,
					esc_html( $term->name )
				);
			}
		}
		echo '</select>';
		echo '</p>';
		echo '<p><label for="category_groups_new_name">' . esc_html__( 'Название группы', 'rvn-compare' ) . '</label> ';
		echo '<input type="text" id="category_groups_new_name" name="category_groups_new_name" class="regular-text" placeholder="' . esc_attr__( 'Например: Смартфоны', 'rvn-compare' ) . '" /></p>';
		echo '<p class="description">' . esc_html__( 'Выберите одну или несколько категорий (Ctrl/Cmd — множественный выбор) и введите название. Категории уже занятые группами недоступны.', 'rvn-compare' ) . '</p>';
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
		echo '<p class="description">' . esc_html__( 'Что показывать в трёх местах таблицы: стандартную кнопку «Купить», свой шорткод (один или несколько) или ничего.', 'rvn-compare' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';
		$this->design_select_row( 'behavior', 'buy_header', __( 'В шапке (под товаром)', 'rvn-compare' ), $design, $def, array(
			'buy'       => __( 'Кнопка «Купить»', 'rvn-compare' ),
			'shortcode' => __( 'Мои шорткоды', 'rvn-compare' ),
			'hidden'    => __( 'Скрыть', 'rvn-compare' ),
		), __( 'Кнопка под названием/ценой товара (R4-03).', 'rvn-compare' ) );
		$this->design_select_row( 'behavior', 'buy_bottom', __( 'Внизу таблицы', 'rvn-compare' ), $design, $def, array(
			'buy'       => __( 'Кнопка «Купить»', 'rvn-compare' ),
			'shortcode' => __( 'Мои шорткоды', 'rvn-compare' ),
			'hidden'    => __( 'Скрыть', 'rvn-compare' ),
		), __( 'Нижний ряд таблицы. Шорткод для шапки/низа выводится в самих местах с 0 аргументами.', 'rvn-compare' ) );
		$this->design_select_row( 'behavior', 'buy_floating', __( 'В плавающей панели', 'rvn-compare' ), $design, $def, array(
			'buy'       => __( 'Кнопка «Купить»', 'rvn-compare' ),
			'shortcode' => __( 'Мои шорткоды', 'rvn-compare' ),
			'hidden'    => __( 'Скрыть', 'rvn-compare' ),
		), __( 'Компактная панель при прокрутке. Если скрыто — показывается фото.', 'rvn-compare' ) );
		$this->design_textarea_row( 'behavior', 'buy_shortcodes', __( 'Мои шорткоды', 'rvn-compare' ), $design, $def, 4, __( 'Одна строка = один шорткод. Выводятся все подряд (do_shortcode) в месте, где выбран режим «Мои шорткоды».', 'rvn-compare' ) );
		$this->design_checkbox_row( 'behavior', 'inherit_theme_styles', __( 'Наследовать стили темы', 'rvn-compare' ), $design, $def, __( 'Вместо фирменных стилей кнопке даются классы темы (button / button alt); оформление берётся из темы, как у WooCommerce.', 'rvn-compare' ) );
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
	 * Печатает строку-textarea вкладки «Дизайн таблицы» (одна строка = один элемент).
	 *
	 * @param string $section    Секция дизайна.
	 * @param string $field      Поле.
	 * @param string $label      Подпись.
	 * @param array  $design     Текущий дизайн.
	 * @param array  $def        Дефолты.
	 * @param int    $rows       Кол-во видимых строк.
	 * @param string $help       Пояснение.
	 * @return void
	 */
	private function design_textarea_row( $section, $field, $label, $design, $def, $rows = 4, $help = '' ) {
		$value = $this->design_setting( $design, $section, $field, $def );
		if ( is_array( $value ) ) {
			$value = implode( "\n", $value );
		}
		printf( '<tr><th scope="row"><label for="%1$s">%2$s</label></th><td>', esc_attr( 'd_' . $field ), esc_html( $label ) );
		printf(
			'<textarea id="%1$s" name="%2$s" rows="%3$d" class="large-text code" placeholder="%4$s">%5$s</textarea>',
			esc_attr( 'd_' . $field ),
			esc_attr( 'design[' . $section . '][' . $field . ']' ),
			(int) $rows,
			esc_attr__( 'Одна строка = один шорткод', 'rvn-compare' ),
			esc_textarea( $value )
		);
		if ( $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Печатает строку-чекбокс вкладки «Дизайн таблицы».
	 *
	 * @param string $section Секция дизайна.
	 * @param string $field   Поле.
	 * @param string $label   Подпись.
	 * @param array  $design  Текущий дизайн.
	 * @param array  $def     Дефолты.
	 * @param string $help    Пояснение.
	 * @return void
	 */
	private function design_checkbox_row( $section, $field, $label, $design, $def, $help = '' ) {
		$value = $this->design_setting( $design, $section, $field, $def );
		printf( '<tr><th scope="row">%s</th><td>', esc_html( $label ) );
		printf(
			'<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( 'design[' . $section . '][' . $field . ']' ),
			checked( '1', (string) $value, false ),
			esc_html__( 'Включено', 'rvn-compare' )
		);
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
			echo '<div class="rvn-compare-col rvn-compare-col--header"><span class="rvn-compare-col__thumb"></span><span class="rvn-compare-col__title">' . esc_html( $d['name'] ) . '</span><span class="rvn-compare-col__price">' . esc_html( $d['price'] ) . '</span><a class="rvn-compare-buy" href="#" data-rvn-buy-preview>' . esc_html__( 'Купить', 'rvn-compare' ) . '</a></div>';
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
		$design  = RVN_Compare_Design::instance();
		$buttons = $design->buttons();
		$toasts  = $design->toasts();

		// ----- Тексты кнопок и тостов (§6.4, R4-04) -----
		echo '<h2 class="title">' . esc_html__( 'Тексты', 'rvn-compare' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		$this->text_field( $all, 'button_text', __( 'Текст кнопки «Сравнить»', 'rvn-compare' ), __( 'Пустое значение подставит переведённый дефолт.', 'rvn-compare' ) );
		$this->text_field( $all, 'button_added_text', __( 'Текст кнопки «Уже в сравнении»', 'rvn-compare' ) );
		$this->text_field( $all, 'counter_button_text', __( 'Текст кнопки-счётчика', 'rvn-compare' ) );
		$this->text_field( $all, 'clear_confirm_text', __( 'Текст подтверждения очистки', 'rvn-compare' ) );
		$this->text_field( $all, 'toast_added_text', __( 'Тост «добавлен»', 'rvn-compare' ) );
		$this->text_field( $all, 'toast_removed_text', __( 'Тост «удалён»', 'rvn-compare' ) );
		$this->text_field( $all, 'toast_cleared_text', __( 'Тост «очищен»', 'rvn-compare' ) );
		$this->text_field( $all, 'toast_limit_text', __( 'Тост «достигнут лимит»', 'rvn-compare' ) );
		echo '</tbody></table>';

		// ----- Кнопки -----
		echo '<h2 class="title">' . esc_html__( 'Кнопки', 'rvn-compare' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Три кнопки плагина: настройте режим, иконку и стили (normal/hover).', 'rvn-compare' ) . '</p>';

		$button_defs = array(
			'compare' => array( __( 'Кнопка «Сравнить»', 'rvn-compare' ), 'button_text' ),
			'added'   => array( __( 'Кнопка «Уже в сравнении»', 'rvn-compare' ), 'button_added_text' ),
			'counter' => array( __( 'Кнопка-счётчик меню', 'rvn-compare' ), 'counter_button_text' ),
		);

		foreach ( $button_defs as $key => $meta ) {
			$cfg = isset( $buttons[ $key ] ) ? $buttons[ $key ] : array();
			$this->render_button_block( $key, $meta[0], $meta[1], $cfg, $all );
		}

		// ----- Тосты -----
		echo '<h2 class="title">' . esc_html__( 'Тосты', 'rvn-compare' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Четыре уведомления: позиция (десктоп и мобайл отдельно), значок и стили. Пустой текст + значок = не показывать.', 'rvn-compare' ) . '</p>';

		$toast_defs = array(
			'added'   => __( 'Тост «добавлен»', 'rvn-compare' ),
			'removed' => __( 'Тост «удалён»', 'rvn-compare' ),
			'cleared' => __( 'Тост «очищен»', 'rvn-compare' ),
			'limit'   => __( 'Тост «лимит»', 'rvn-compare' ),
		);

		foreach ( $toast_defs as $key => $label ) {
			$cfg = isset( $toasts[ $key ] ) ? $toasts[ $key ] : array();
			$this->render_toast_block( $key, $label, $cfg );
		}

		// ----- Превью на полосатой подложке -----
		echo '<div class="rvn-compare-elems" data-rvn-compare-elems-preview>';
		echo '<h2 class="title">' . esc_html__( 'Предпросмотр', 'rvn-compare' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Наведите курсор на кнопку, чтобы увидеть hover-состояние.', 'rvn-compare' ) . '</p>';
		echo '<div class="rvn-compare-elems__stage" data-rvn-compare-stage>' . "
";
		printf(
			'<button type="button" class="rvn-compare-button rvn-compare-button--icon_text" data-rvn-compare-add="0">%s<span class="rvn-compare-button__label">%s</span></button>',
			'',
			esc_html( (string) $this->setting_value( $all, 'button_text', __( 'Сравнить', 'rvn-compare' ) ) )
		);
		printf(
			'<a class="rvn-compare-counter-button"><span class="rvn-compare-counter-button__badge">2</span><span class="rvn-compare-counter-button__label">%s</span></a>',
			esc_html( (string) $this->setting_value( $all, 'counter_button_text', __( 'Сравнение', 'rvn-compare' ) ) )
		);
		echo '<div class="rvn-compare-toast rvn-compare-toast--demo is-visible" data-kind="added">' . esc_html__( 'Товар добавлен в список сравнения', 'rvn-compare' ) . '<span class="rvn-compare-toast__bar"></span></div>';
		echo '</div></div>';
		echo '<button type="button" class="button" data-rvn-compare-preview-refresh>'
			. esc_html__( 'Обновить предпросмотр', 'rvn-compare' )
			. '</button>';

		// Исходные стили превью (сохранённые) + JS-пересчёт по форме.
		echo '<style id="rvn-compare-elements-css">' . $design->elements_css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Печатает правила «Где показывать» для заданного контекста.
	 *
	 * @param string $context 'archive' | 'single'.
	 * @param string $label   Заголовок блока.
	 * @param array  $all     Все настройки.
	 * @return void
	 */
	private function render_visibility_rules( $context, $label, $all ) {
		$prefix = 'archive' === $context ? 'archive' : 'single';
		$mode   = (string) $this->setting_value( $all, $prefix . '_show_mode', 'all' );
		$pages  = (array) $this->setting_value( $all, $prefix . '_show_pages', array() );
		$urls   = (array) $this->setting_value( $all, $prefix . '_show_urls', array() );

		echo '<h3>' . esc_html( $label ) . '</h3>';
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="' . esc_attr( $prefix . '_show_mode' ) . '">' . esc_html__( 'Режим показа', 'rvn-compare' ) . '</label></th><td>';
		echo '<select id="' . esc_attr( $prefix . '_show_mode' ) . '" name="' . esc_attr( $prefix . '_show_mode' ) . '">';
		$modes = array(
			'all'  => __( 'Показывать везде', 'rvn-compare' ),
			'show' => __( 'Показывать ТОЛЬКО на выбранных', 'rvn-compare' ),
			'hide' => __( 'Скрывать на выбранных', 'rvn-compare' ),
		);
		foreach ( $modes as $mk => $ml ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $mk ), selected( $mode, $mk, false ), esc_html( $ml ) );
		}
		echo '</select>';
		echo '</td></tr>';

		// Страницы — мультиселект страниц.
		echo '<tr><th scope="row"><label>' . esc_html__( 'Страницы', 'rvn-compare' ) . '</label></th><td>';
		echo '<select name="' . esc_attr( $prefix . '_show_pages' ) . '[]" multiple="multiple" style="width:100%;min-height:90px;">';
		$all_pages = get_pages( array( 'post_status' => 'publish,private,draft' ) );
		foreach ( $all_pages as $p ) {
			printf(
				'<option value="%1$d" %2$s>%3$s</option>',
				(int) $p->ID,
				selected( in_array( (int) $p->ID, array_map( 'absint', $pages ), true ), true, false ),
				esc_html( get_the_title( $p ) )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Держите Ctrl/Cmd для выбора нескольких.', 'rvn-compare' ) . '</p>';
		echo '</td></tr>';

		// Произвольные URL (каждый с новой строки; * — wildcard).
		$urls_text = implode( "\n", array_map( 'esc_textarea', $urls ) );
		echo '<tr><th scope="row"><label for="' . esc_attr( $prefix . '_show_urls' ) . '">' . esc_html__( 'URL-адреса', 'rvn-compare' ) . '</label></th><td>';
		echo '<textarea id="' . esc_attr( $prefix . '_show_urls' ) . '" name="' . esc_attr( $prefix . '_show_urls' ) . '" rows="3" class="large-text code" placeholder="' . esc_attr__( 'например promos/, blog/offer-*, */sale', 'rvn-compare' ) . '">' . $urls_text . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Один шаблон в строку; * — любой набор символов. Сравнивается с путём (без домена).', 'rvn-compare' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';
	}

	/**
	 * Печатает карточку-конструктор одной кнопки.
	 *
	 * @param string $key   'compare' | 'added' | 'counter'.
	 * @param string $title Заголовок блока.
	 * @param string $text_key Ключ текста (button_*_text).
	 * @param array  $cfg   Конфигурация кнопки.
	 * @param array  $all   Все настройки.
	 * @return void
	 */
	private function render_button_block( $key, $title, $text_key, $cfg, $all ) {
		$v = function ( $path, $def = '' ) use ( $cfg ) {
			$cur = $cfg;
			foreach ( $path as $p ) {
				if ( ! is_array( $cur ) || ! isset( $cur[ $p ] ) ) {
					return $def;
				}
				$cur = $cur[ $p ];
			}
			return ( '' !== (string) $cur ) ? $cur : $def;
		};

		echo '<div class="rvn-compare-el-block" data-el-block="' . esc_attr( $key ) . '">';
		echo '<h3>' . esc_html( $title ) . '</h3>';
		echo '<table class="form-table" role="presentation"><tbody>';

		// Текст (уже вверху) + режим.
		echo '<tr><th scope="row"><label>' . esc_html__( 'Режим', 'rvn-compare' ) . '</label></th><td>';
		echo '<select name="' . esc_attr( 'es[' . $key . '][mode]' ) . '">';
		$modes = array(
			'icon_text' => __( 'Иконка + текст', 'rvn-compare' ),
			'text_icon' => __( 'Текст + иконка', 'rvn-compare' ),
			'icon'      => __( 'Только иконка', 'rvn-compare' ),
			'text'      => __( 'Только текст', 'rvn-compare' ),
			'bare'      => __( 'Ссылка (без фона)', 'rvn-compare' ),
			'hidden'    => __( 'Скрыть', 'rvn-compare' ),
		);
		foreach ( $modes as $mk => $ml ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $mk ), selected( $v( array( 'mode' ), 'icon_text' ), $mk, false ), esc_html( $ml ) );
		}
		echo '</select>';
		echo '</td></tr>';

		// Иконка (textarea).
		$svg = $v( array( 'svg' ), '' );
		echo '<tr><th scope="row"><label>' . esc_html__( 'Иконка (SVG / эмодзи)', 'rvn-compare' ) . '</label></th><td>';
		echo '<textarea name="' . esc_attr( 'es[' . $key . '][svg]' ) . '" rows="3" class="large-text code">' . esc_textarea( $svg ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'SVG очищается санитайзером (whitelist); короткий эмодзи тоже допустим. Пусто — без иконки.', 'rvn-compare' ) . '</p>';
		echo '</td></tr>';

		// Позиция бейджа (только счётчик).
		if ( 'counter' === $key ) {
			echo '<tr><th scope="row"><label>' . esc_html__( 'Позиция бейджа', 'rvn-compare' ) . '</label></th><td>';
			echo '<select name="' . esc_attr( 'es[' . $key . '][badge_position]' ) . '">';
			$bp = array(
				'right' => __( 'Справа', 'rvn-compare' ),
				'left'  => __( 'Слева', 'rvn-compare' ),
				'top'   => __( 'Сверху (угол)', 'rvn-compare' ),
			);
			foreach ( $bp as $bpk => $bpl ) {
				printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $bpk ), selected( $v( array( 'badge_position' ), 'right' ), $bpk, false ), esc_html( $bpl ) );
			}
			echo '</select></td></tr>';
		}

		// Свой класс.
		$cls = $v( array( 'class' ), '' );
		echo '<tr><th scope="row"><label>' . esc_html__( 'Свой CSS-класс', 'rvn-compare' ) . '</label></th><td>';
		echo '<input type="text" name="' . esc_attr( 'es[' . $key . '][class]' ) . '" value="' . esc_attr( $cls ) . '" class="regular-text" />';
		echo '</td></tr>';

		// Стили.
		$this->render_style_group( $key, 'normal', __( 'Обычное состояние', 'rvn-compare' ), $v, array( 'bg', 'color', 'border', 'border_width', 'radius', 'font_size', 'font_weight', 'padding' ) );
		$this->render_style_group( $key, 'hover', __( 'Hover', 'rvn-compare' ), $v, array( 'bg', 'color', 'border' ) );

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Печатает блок «Стили (normal|hover)» конструктора кнопки.
	 *
	 * @param string   $key    Ключ кнопки.
	 * @param string   $state  normal|hover.
	 * @param string   $title  Заголовок.
	 * @param callable $v      Читатель значения из конфига.
	 * @param array    $fields Поля.
	 * @return void
	 */
	private function render_style_group( $key, $state, $title, $v, $fields ) {
		echo '<tr><th scope="row">' . esc_html( $title ) . '</th><td><div class="rvn-compare-el-styles">';
		$color_fields = array( 'bg', 'color', 'border' );
		$labels = array(
			'bg'           => __( 'Фон', 'rvn-compare' ),
			'color'        => __( 'Текст', 'rvn-compare' ),
			'border'       => __( 'Рамка', 'rvn-compare' ),
			'border_width' => __( 'Толщина, px', 'rvn-compare' ),
			'radius'       => __( 'Радиус, px', 'rvn-compare' ),
			'font_size'    => __( 'Размер, px', 'rvn-compare' ),
			'font_weight'  => __( 'Насыщенность', 'rvn-compare' ),
			'padding'      => __( 'Паддинг', 'rvn-compare' ),
		);
		foreach ( $fields as $f ) {
			$val = $v( array( $state, $f ), '' );
			$name = 'es[' . $key . '][' . $state . '][' . $f . ']';
			echo '<label class="rvn-compare-el-style">' . esc_html( $labels[ $f ] ) . ' ';
			if ( in_array( $f, $color_fields, true ) ) {
				printf( '<input type="text" name="%1$s" value="%2$s" class="rvn-compare-color" data-default-color="%2$s" />', esc_attr( $name ), esc_attr( $val ) );
			} else {
				printf( '<input type="text" name="%1$s" value="%2$s" class="small-text" />', esc_attr( $name ), esc_attr( $val ) );
			}
			echo '</label>';
		}
		echo '</div></td></tr>';
	}

	/**
	 * Печатает карточку-конструктор тоста.
	 *
	 * @param string $key   added|removed|cleared|limit.
	 * @param string $title Заголовок.
	 * @param array  $cfg   Конфигурация тоста.
	 * @return void
	 */
	private function render_toast_block( $key, $title, $cfg ) {
		$design = RVN_Compare_Design::instance();

		$get = function ( $field ) use ( $cfg ) {
			return isset( $cfg[ $field ] ) ? $cfg[ $field ] : '';
		};

		echo '<div class="rvn-compare-el-block" data-el-block="toast-' . esc_attr( $key ) . '">';
		echo '<h3>' . esc_html( $title ) . '</h3>';
		echo '<table class="form-table" role="presentation"><tbody>';

		// Позиции.
		echo '<tr><th scope="row"><label>' . esc_html__( 'Позиция (десктоп)', 'rvn-compare' ) . '</label></th><td>';
		$this->position_select( 'es[' . $key . '][toast][position_desktop]', $get( 'position_desktop' ), $design->toast_positions() );
		echo '</td></tr>';

		echo '<tr><th scope="row"><label>' . esc_html__( 'Позиция (мобайл)', 'rvn-compare' ) . '</label></th><td>';
		$this->position_select( 'es[' . $key . '][toast][position_mobile]', $get( 'position_mobile' ), $design->toast_positions() );
		echo '</td></tr>';

		// Значок.
		$icon = $get( 'icon' );
		echo '<tr><th scope="row"><label>' . esc_html__( 'Значок (SVG / эмодзи)', 'rvn-compare' ) . '</label></th><td>';
		echo '<textarea name="' . esc_attr( 'es[' . $key . '][toast][icon]' ) . '" rows="2" class="large-text code">' . esc_textarea( $icon ) . '</textarea>';
		echo '</td></tr>';

		// Позиция значка.
		echo '<tr><th scope="row"><label>' . esc_html__( 'Позиция значка', 'rvn-compare' ) . '</label></th><td>';
		echo '<select name="' . esc_attr( 'es[' . $key . '][toast][icon_position]' ) . '">';
		foreach ( array( 'left' => __( 'Слева', 'rvn-compare' ), 'right' => __( 'Справа', 'rvn-compare' ) ) as $ik => $il ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $ik ), selected( $get( 'icon_position' ), $ik, false ), esc_html( $il ) );
		}
		echo '</select></td></tr>';

		// Прогресс.
		echo '<tr><th scope="row">' . esc_html__( 'Прогресс-бар', 'rvn-compare' ) . '</th><td>';
		printf( '<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>', esc_attr( 'es[' . $key . '][toast][progress]' ), checked( (string) $get( 'progress' ), '1', false ), esc_html__( 'Включено', 'rvn-compare' ) );
		echo '</td></tr>';

		// Цвета.
		foreach ( array( 'bg' => __( 'Фон', 'rvn-compare' ), 'color' => __( 'Текст', 'rvn-compare' ), 'border' => __( 'Рамка', 'rvn-compare' ), 'accent' => __( 'Акцент (полоса)', 'rvn-compare' ) ) as $fk => $fl ) {
			$val = $get( $fk );
			echo '<tr><th scope="row"><label>' . esc_html( $fl ) . '</label></th><td>';
			printf( '<input type="text" name="%1$s" value="%2$s" class="rvn-compare-color" data-default-color="%2$s" />', esc_attr( 'es[' . $key . '][toast][' . $fk . ']' ), esc_attr( $val ) );
			echo '</td></tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Селект позиции тоста.
	 *
	 * @param string   $name    Имя поля.
	 * @param string   $current Текущее.
	 * @param string[] $options Список позиций.
	 * @return void
	 */
	private function position_select( $name, $current, $options ) {
		$labels = array(
			'top-left'      => __( 'Верх слева', 'rvn-compare' ),
			'top-center'    => __( 'Верх центр', 'rvn-compare' ),
			'top-right'     => __( 'Верх справа', 'rvn-compare' ),
			'bottom-left'   => __( 'Низ слева', 'rvn-compare' ),
			'bottom-center' => __( 'Низ центр', 'rvn-compare' ),
			'bottom-right'  => __( 'Низ справа', 'rvn-compare' ),
		);
		echo '<select name="' . esc_attr( $name ) . '">';
		foreach ( $options as $o ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $o ), selected( $current, $o, false ), esc_html( isset( $labels[ $o ] ) ? $labels[ $o ] : $o ) );
		}
		echo '</select>';
	}

	/**
	 * Вкладка «Справка».
	 *
	 * @return void
	 */
	private function render_help_tab() {
		// ---- Шорткоды ----
		echo '<h2 class="title">' . esc_html__( 'Шорткоды', 'rvn-compare' ) . '</h2>';
		echo '<table class="widefat striped rvn-compare-shortcodes"><thead><tr>'
			. '<th>' . esc_html__( 'Шорткод', 'rvn-compare' ) . '</th>'
			. '<th>' . esc_html__( 'Назначение', 'rvn-compare' ) . '</th>'
			. '<th>' . esc_html__( 'Аргументы', 'rvn-compare' ) . '</th>'
			. '<th>' . esc_html__( 'Пример', 'rvn-compare' ) . '</th>'
			. '<th></th></tr></thead><tbody>';

		foreach ( RVN_Compare_Shortcodes::registry() as $tag => $info ) {
			$example = isset( $info['example'] ) ? $info['example'] : '[' . $tag . ']';
			echo '<tr>';
			echo '<td><code>' . esc_html( $tag ) . '</code></td>';
			echo '<td>' . esc_html( $info['description'] ) . '</td>';
			echo '<td><code>' . esc_html( isset( $info['args'] ) ? $info['args'] : '' ) . '</code></td>';
			echo '<td><code class="rvn-compare-copyable" data-rvn-compare-copy="' . esc_attr( $example ) . '">' . esc_html( $example ) . '</code></td>';
			echo '<td><button type="button" class="button button-small rvn-compare-copy-btn" data-rvn-compare-copy="' . esc_attr( $example ) . '">' . esc_html__( 'Копировать', 'rvn-compare' ) . '</button></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		// ---- Системный статус ----
		echo '<h2 class="title">' . esc_html__( 'Системный статус', 'rvn-compare' ) . '</h2>';
		echo '<table class="widefat striped rvn-compare-status"><tbody>';
		foreach ( $this->system_status_rows() as $row ) {
			echo '<tr><th>' . esc_html( $row[0] ) . '</th><td>' . $row[1] . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- values собраны через esc_*.
		}
		echo '</tbody></table>';

		// ---- FAQ ----
		echo '<h2 class="title">' . esc_html__( 'FAQ', 'rvn-compare' ) . '</h2>';
		foreach ( $this->help_faq() as $faq ) {
			echo '<div class="rvn-compare-faq">';
			echo '<h3>' . esc_html( $faq['q'] ) . '</h3>';
			echo '<p>' . esc_html( $faq['a'] ) . '</p>';
			echo '</div>';
		}

		// ---- Остальное ----
		echo '<h2 class="title">' . esc_html__( 'Автозагрузка страницы', 'rvn-compare' ) . '</h2>';
		echo '<p>' . esc_html__( 'Плагин пытается автоматически создать страницу со слагом rvn-compare при активации. Если она не появилась (или мешает другой плагин), создайте её вручную и вставьте шорткод:', 'rvn-compare' ) . '</p>';
		echo '<p><code class="rvn-compare-copyable" data-rvn-compare-copy="[rvn-compare-table]">[rvn-compare-table]</code> <button type="button" class="button button-small rvn-compare-copy-btn" data-rvn-compare-copy="[rvn-compare-table]">' . esc_html__( 'Копировать', 'rvn-compare' ) . '</button></p>';
	}

	/**
	 * Строки системного статуса для вкладки «Справка».
	 *
	 * @return array{string,string}[]
	 */
	private function system_status_rows() {
		global $wp_version;

		$wc_version = function_exists( 'WC' ) && defined( 'WOOCOMMERCE_VERSION' ) ? WOOCOMMERCE_VERSION : '—';

		$hpos = __( 'Недоступно', 'rvn-compare' );
		if ( class_exists( 'Automattic\\WooCommerce\\Utilities\\OrderUtil' ) && method_exists( 'Automattic\\WooCommerce\\Utilities\\OrderUtil', 'custom_orders_table_usage_is_enabled' ) ) {
			$hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? __( 'Включено', 'rvn-compare' ) : __( 'Выключено', 'rvn-compare' );
		}

		$cache = __( 'Не обнаружен', 'rvn-compare' );
		if ( class_exists( 'WP_Object_Cache' ) && wp_using_ext_object_cache() ) {
			$cache = __( 'Внешний объектный кеш', 'rvn-compare' );
		}

		$theme = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
		$theme_name = $theme ? $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) : '—';

		return array(
			array( __( 'WordPress', 'rvn-compare' ), esc_html( (string) $wp_version ) ),
			array( __( 'WooCommerce', 'rvn-compare' ), esc_html( (string) $wc_version ) ),
			array( __( 'PHP', 'rvn-compare' ), esc_html( PHP_VERSION ) ),
			array( __( 'RVN Compare', 'rvn-compare' ), esc_html( RVN_COMPARE_VERSION ) ),
			array( __( 'Тема', 'rvn-compare' ), esc_html( $theme_name ) ),
			array( __( 'HPOS (заказы в таблицах)', 'rvn-compare' ), esc_html( $hpos ) ),
			array( __( 'Объектный кеш', 'rvn-compare' ), esc_html( $cache ) ),
			array( __( 'Страница сравнения', 'rvn-compare' ), $this->status_page_cell() ),
		);
	}

	/**
	 * Ячейка статуса страницы сравнения.
	 *
	 * @return string
	 */
	private function status_page_cell() {
		$page_id = (int) RVN_Compare_Settings::instance()->get( 'compare_page_id', 0 );
		if ( $page_id && get_post( $page_id ) ) {
			$url = get_permalink( $page_id );
			return '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( get_the_title( $page_id ) ) . '</a> (ID ' . (int) $page_id . ')';
		}
		return esc_html__( '—', 'rvn-compare' );
	}

	/**
	 * Вопросы-ответы для справки.
	 *
	 * @return array[] ['q'=>string, 'a'=>string]
	 */
	private function help_faq() {
		return array(
			array(
				'q' => __( 'Кнопка сравнения не появляется на карточках. Почему?', 'rvn-compare' ),
				'a' => __( 'Проверьте настройку позиции кнопки на вкладке «Основное» и правила «Где показывать». Товар может быть в исключениях (или в исключённой категории).', 'rvn-compare' ),
			),
			array(
				'q' => __( 'Хочу изменить стиль кнопок и таблицы. Где это?', 'rvn-compare' ),
				'a' => __( 'Вкладки «Дизайн таблицы» (цвета, типографика, геометрия) и «Дизайн элементов и кнопок» (кнопки, тосты) с живым предпросмотром.', 'rvn-compare' ),
			),
			array(
				'q' => __( 'Как показать таблицу только для одной категории?', 'rvn-compare' ),
				'a' => __( 'Используйте шорткод [rvn-compare-table group="g0"] с ключом нужной группы категорий, либо ids="12,34" для фиксированного набора товаров.', 'rvn-compare' ),
			),
			array(
				'q' => __( 'Что будет при удалении плагина?', 'rvn-compare' ),
				'a' => __( 'По умолчанию ничего не удаляется. Что стереть (настройки, списки пользователей, страницу) выбирается чекбоксами в разделе «Удаление данных» на вкладке «Основное».', 'rvn-compare' ),
			),
		);
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
		} elseif ( 'elements' === $tab ) {
			// Вкладка «Дизайн элементов и кнопок»: тексты + es[...]-стили.
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
		$settings  = RVN_Compare_Settings::instance();
		$categories = RVN_Compare_Categories::instance();

		// Скалярные чекбоксы/текст этой вкладки.
		$settings->save_from_request( wp_unslash( $_POST ) );

		// Сложные структуры (пока только ЧТО прислано в POST).
		$complex = array();

		if ( isset( $_POST['field_groups'] ) && is_array( $_POST['field_groups'] ) ) {
			$complex['field_groups'] = $_POST['field_groups']; // wp_unslash применится внутри.
		}

		// Новая группа характеристик (field_groups_new — вне существующего списка).
		if ( isset( $_POST['field_groups_new'] ) && '' !== trim( (string) $_POST['field_groups_new'] ) ) {
			$complex['field_groups_new'] = sanitize_text_field( wp_unslash( $_POST['field_groups_new'] ) );
		}

		if ( isset( $_POST['core_fields'] ) && is_array( $_POST['core_fields'] ) ) {
			$complex['core_fields'] = $_POST['core_fields'];
		}

		if ( isset( $_POST['acf_meta_fields'] ) && is_array( $_POST['acf_meta_fields'] ) ) {
			$complex['acf_meta_fields'] = $_POST['acf_meta_fields'];
		}

		/*
		 * Группы категорий: rebuild полного списка — существующие группы
		 * (переименование/удаление) + новая группа из category_groups_new.
		 */
		$current     = $categories->groups();
		$groups_raw  = isset( $_POST['category_groups'] ) && is_array( $_POST['category_groups'] ) ? $_POST['category_groups'] : array();
		$deleted     = isset( $_POST['category_groups_delete'] ) && is_array( $_POST['category_groups_delete'] ) ? array_keys( $_POST['category_groups_delete'] ) : array();

		$kept = array();
		foreach ( $current as $index => $group ) {
			if ( in_array( (string) $index, $deleted, true ) || in_array( (int) $index, array_map( 'absint', $deleted ), true ) ) {
				continue;
			}
			$name = isset( $groups_raw[ $index ]['name'] ) ? sanitize_text_field( wp_unslash( $groups_raw[ $index ]['name'] ) ) : $group['name'];
			$cats = isset( $groups_raw[ $index ]['cats'] ) ? array_values( array_filter( array_map( 'absint', (array) $groups_raw[ $index ]['cats'] ) ) ) : $group['cats'];

			if ( '' !== $name && ! empty( $cats ) ) {
				$kept[] = array( 'name' => $name, 'cats' => $cats );
			}
		}

		$new_cats = isset( $_POST['category_groups_new'] ) && is_array( $_POST['category_groups_new'] ) ? array_values( array_filter( array_map( 'absint', $_POST['category_groups_new'] ) ) ) : array();
		$new_name = isset( $_POST['category_groups_new_name'] ) ? sanitize_text_field( wp_unslash( $_POST['category_groups_new_name'] ) ) : '';
		if ( $new_name && ! empty( $new_cats ) ) {
			$kept[] = array( 'name' => $new_name, 'cats' => $new_cats );
		}

		$complex['category_groups'] = $kept;

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

		// AJAX-поиск товара (wc-product-search) + ручной ID.
		echo '<p>';
		echo '<label for="rvn_compare_product_search">' . esc_html__( 'Поиск товара', 'rvn-compare' ) . '</label> ';
		echo '<select id="rvn_compare_product_search" name="rvn_compare_product_id[]" class="wc-product-search" style="width:100%%;" multiple="multiple" data-placeholder="' . esc_attr__( 'Начните вводить название или SKU…', 'rvn-compare' ) . '" data-action="woocommerce_json_search_products_and_variations"></select>';
		echo '</p>';

		echo '<p>';
		echo '<label for="rvn_compare_manual_id">' . esc_html__( 'Или введите ID вручную', 'rvn-compare' ) . '</label> ';
		echo '<input type="text" id="rvn_compare_manual_id" name="rvn_compare_manual_id" class="regular-text" placeholder="' . esc_attr__( 'например 123 или 12, 34, 56', 'rvn-compare' ) . '" />';
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

		// Исключить целую категорию.
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:12px;">';
		wp_nonce_field( 'rvn_compare_save', 'rvn_compare_nonce' );
		echo '<input type="hidden" name="action" value="rvn_compare_save" />';
		echo '<input type="hidden" name="tab" value="general" />';
		echo '<input type="hidden" name="rvn_compare_exclusion_action" value="add_category" />';
		echo '<p>';
		echo '<label for="rvn_compare_excl_category">' . esc_html__( 'Исключить категорию', 'rvn-compare' ) . '</label> ';
		echo '<select id="rvn_compare_excl_category" name="rvn_compare_exclusion_category" class="regular-text">';
		$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) {
			foreach ( $cats as $c ) {
				printf( '<option value="%1$d">%2$s</option>', (int) $c->term_id, esc_html( $c->name ) );
			}
		}
		echo '</select>';
		echo '</p>';
		submit_button( __( 'Исключить категорию', 'rvn-compare' ), 'secondary', 'submit', true );
		echo '</form>';
		echo '</div>';

		// ---- Список исключённых ----
		echo '<div class="rvn-compare-exclusions__list">';
		$settings = RVN_Compare_Settings::instance();
		$excluded = $settings->excluded_products();
		$excl_cats = $settings->excluded_categories();

		if ( ! empty( $excl_cats ) ) {
			echo '<h3>' . esc_html__( 'Исключённые категории', 'rvn-compare' ) . '</h3>';
			echo '<ul class="rvn-compare-excl-cats">';
			foreach ( $excl_cats as $cat_id ) {
				$term = get_term( (int) $cat_id );
				$name = ( $term && ! is_wp_error( $term ) ) ? $term->name : ( '#' . (int) $cat_id );
				echo '<li>' . esc_html( $name ) . ' ';
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rvn-compare-inline-del">';
				wp_nonce_field( 'rvn_compare_save', 'rvn_compare_nonce' );
				echo '<input type="hidden" name="action" value="rvn_compare_save" />';
				echo '<input type="hidden" name="tab" value="general" />';
				echo '<input type="hidden" name="rvn_compare_exclusion_action" value="remove_category" />';
				echo '<input type="hidden" name="rvn_compare_exclusion_category" value="' . (int) $cat_id . '" />';
				echo '<button type="submit" class="button button-link button-link-delete" aria-label="' . esc_attr__( 'Удалить из исключений', 'rvn-compare' ) . '">✕</button>';
				echo '</form></li>';
			}
			echo '</ul>';
		}

		if ( empty( $excluded ) && empty( $excl_cats ) ) {
			echo '<p class="description">' . esc_html__( 'Исключений пока нет.', 'rvn-compare' ) . '</p>';
		}

		if ( ! empty( $excluded ) || ! empty( $excl_cats ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rvn-compare-excl-clear" onsubmit="return confirm(rvnCompareAdmin.confirmClearExclusions);">';
			wp_nonce_field( 'rvn_compare_save', 'rvn_compare_nonce' );
			echo '<input type="hidden" name="action" value="rvn_compare_save" />';
			echo '<input type="hidden" name="tab" value="general" />';
			echo '<input type="hidden" name="rvn_compare_exclusion_action" value="clear" />';
			submit_button( __( 'Очистить список', 'rvn-compare' ), 'secondary', 'submit', true );
			echo '</form>';
		}

		if ( empty( $excluded ) ) {
			echo '';
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
			// ID могут прийти из AJAX-поиска (multiple select) или ручного ввода.
			$ids = array();
			if ( isset( $_POST['rvn_compare_product_id'] ) && is_array( $_POST['rvn_compare_product_id'] ) ) {
				foreach ( $_POST['rvn_compare_product_id'] as $id ) {
					$ids = array_merge( $ids, $this->parse_product_ids( sanitize_text_field( wp_unslash( $id ) ) ) );
				}
			}
			$raw_ids = isset( $_POST['rvn_compare_manual_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rvn_compare_manual_id'] ) ) : '';
			$ids     = array_merge( $ids, $this->parse_product_ids( $raw_ids ) );
			$ids     = array_values( array_unique( array_filter( $ids ) ) );

			if ( empty( $ids ) ) {
				set_transient( 'rvn_compare_notice', 'exclusion_invalid', 30 );
				return;
			}

			$archive = isset( $_POST['rvn_compare_exc_archive'] ) ? 1 : 0;
			$single  = isset( $_POST['rvn_compare_exc_single'] ) ? 1 : 0;

			$applied = 0;
			foreach ( $ids as $id ) {
				if ( function_exists( 'wc_get_product' ) && ! wc_get_product( (int) $id ) ) {
					continue;
				}
				if ( 0 === $archive && 0 === $single ) {
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
		} elseif ( 'add_category' === $action ) {
			$cat = isset( $_POST['rvn_compare_exclusion_category'] ) ? absint( $_POST['rvn_compare_exclusion_category'] ) : 0;
			if ( $cat ) {
				$settings->add_excluded_category( $cat );
				set_transient( 'rvn_compare_notice', 'exclusion_saved', 30 );
			} else {
				set_transient( 'rvn_compare_notice', 'exclusion_invalid', 30 );
			}
		} elseif ( 'remove_category' === $action ) {
			$cat = isset( $_POST['rvn_compare_exclusion_category'] ) ? absint( $_POST['rvn_compare_exclusion_category'] ) : 0;
			$settings->remove_excluded_category( $cat );
			set_transient( 'rvn_compare_notice', 'exclusion_removed', 30 );
		} elseif ( 'clear' === $action ) {
			$settings->set_excluded_categories( array() );
			// Очистить и товарный список: пишем пустую карту.
			$all = $settings->all();
			$all['excluded_products'] = array();
			$settings->replace( $all );
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
