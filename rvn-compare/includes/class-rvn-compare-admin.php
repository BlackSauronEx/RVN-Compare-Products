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

		echo '<h2 class="title">' . esc_html__( 'Поведение и ограничения', 'rvn-compare' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		$this->number_field( $all, 'max_items_total', __( 'Максимум товаров в сравнении', 'rvn-compare' ), 1, 100, __( 'Общий лимит списка сравнения (по умолчанию 50).', 'rvn-compare' ) );
		$this->number_field( $all, 'max_items_per_context', __( 'Лимит на категорию/группу', 'rvn-compare' ), 1, 50, __( 'Максимум товаров в одной категории или группе (по умолчанию 12). Не может превышать глобальный лимит.', 'rvn-compare' ) );
		$this->number_field( $all, 'breakpoint_tablet', __( 'Точка перехода (планшет), px', 'rvn-compare' ), 0, 10000, __( 'Ширина, с которой включается планшетный режим (по умолчанию 1024).', 'rvn-compare' ) );
		$this->number_field( $all, 'breakpoint_mobile', __( 'Точка перехода (телефон), px', 'rvn-compare' ), 0, 10000, __( 'Ширина, с которой включается мобильный режим (по умолчанию 768).', 'rvn-compare' ) );
		$this->number_field( $all, 'columns_desktop', __( 'Видимых товаров: десктоп', 'rvn-compare' ), 1, 10, __( 'Колонок на десктопе (по умолчанию 5).', 'rvn-compare' ) );
		$this->number_field( $all, 'columns_tablet', __( 'Видимых товаров: планшет', 'rvn-compare' ), 1, 10, __( 'Колонок на планшете (по умолчанию 3).', 'rvn-compare' ) );
		$this->number_field( $all, 'columns_mobile', __( 'Видимых товаров: телефон', 'rvn-compare' ), 1, 10, __( 'Колонок на телефоне (по умолчанию 2).', 'rvn-compare' ) );
		$this->number_field( $all, 'animation_speed', __( 'Скорость анимации, мс', 'rvn-compare' ), 0, 10000, __( '0 — без анимации (по умолчанию 300).', 'rvn-compare' ) );
		$this->number_field( $all, 'toast_duration', __( 'Длительность уведомлений, мс', 'rvn-compare' ), 0, 60000, __( 'Через сколько скрывать всплывающие уведомления (по умолчанию 3200).', 'rvn-compare' ) );

		$this->color_field( $all, 'accent_color', __( 'Основной цветовой акцент', 'rvn-compare' ), __( 'Цвет кнопок «Купить», активных вкладок, бейджа счётчика и [?] (по умолчанию #2563eb).', 'rvn-compare' ) );

		$this->select_field( $all, 'archive_button_position', __( 'Позиция кнопки на карточке товара', 'rvn-compare' ), $this->button_positions(), __( 'Дефолт — «После кнопки Купить».', 'rvn-compare' ) );
		$this->select_field( $all, 'single_button_position', __( 'Позиция кнопки на странице товара', 'rvn-compare' ), $this->button_positions(), __( 'Дефолт — «После кнопки Купить».', 'rvn-compare' ) );

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
		echo '<p>' . esc_html__( 'Группы характеристик, поля таблицы и группы категорий появятся в одном из следующих шагов.', 'rvn-compare' ) . '</p>';

		$this->checkbox_field( $all, 'highlight_differences', __( 'Подсветка различий', 'rvn-compare' ), __( 'Выделять цветом строки с различающимися значениями.', 'rvn-compare' ) );
		$this->checkbox_field( $all, 'hide_empty_rows', __( 'Пустые строки', 'rvn-compare' ), __( 'Скрывать строки, где у всех товаров значение пустое («—»).', 'rvn-compare' ) );
		$this->checkbox_field( $all, 'show_only_differences_toggle', __( 'Переключатель «Только различия»', 'rvn-compare' ), __( 'Показывать покупателям переключатель «Только различия» над таблицей.', 'rvn-compare' ) );
		$this->checkbox_field( $all, 'collapse_groups', __( 'Сворачивание групп', 'rvn-compare' ), __( 'Разрешить сворачивание групп характеристик на странице сравнения.', 'rvn-compare' ) );
		$this->checkbox_field( $all, 'include_subcats', __( 'Включать подкатегории', 'rvn-compare' ), __( 'Учитывать подкатегории при определении групп сравнения.', 'rvn-compare' ) );
		$this->checkbox_field( $all, 'custom_attributes', __( 'Кастомные атрибуты товаров', 'rvn-compare' ), __( 'Выводить неглобальные (кастомные) атрибуты товаров в таблице.', 'rvn-compare' ) );
	}

	/**
	 * Вкладка «Дизайн таблицы» (заглушка первого шага).
	 *
	 * @param array $all Текущие настройки.
	 * @return void
	 */
	private function render_design_tab( $all ) {
		echo '<p>' . esc_html__( 'Полный конструктор дизайна таблицы — в Этапе 2 (см. живое ТЗ §6.3). Сейчас доступен только акцент.', 'rvn-compare' ) . '</p>';
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
		$this->text_field( $all, 'clear_text', __( 'Текст кнопки «Очистить всё»', 'rvn-compare' ) );
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
		 * Действия над страницей сравнения (отдельные формы на вкладке
		 * «Основное») — обрабатываем до сохранения настроек.
		 */
		if ( 'general' === $tab && isset( $_POST['rvn_compare_action'] ) ) {
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

		if ( ! isset( $messages[ $type ] ) ) {
			return;
		}

		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $type ][0] ),
			esc_html( $messages[ $type ][1] )
		);
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
