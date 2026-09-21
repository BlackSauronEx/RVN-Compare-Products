<?php
/**
 * Менеджер страницы сравнения.
 *
 * Отвечает за:
 *  - поиск/создание страницы со слагом `rvn-compare`;
 *  - назначение её как текущей страницы сравнения в настройках;
 *  - метку «Страница сравнения» в списке страниц админки;
 *  - авто-вставку таблицы в конец страницы, если шорткод отсутствует
 *    и опция «Авто-вставка таблицы» включена (§6.1 живого ТЗ).
 *
 * @package RVN_Compare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Управление страницей сравнения.
 */
final class RVN_Compare_Page_Manager {

	/**
	 * Слаг страницы сравнения.
	 */
	const SLUG = 'rvn-compare';

	/**
	 * Meta-ключ, которым помечаем созданную плагином страницу
	 * (используется в uninstall.php для безопасного удаления).
	 */
	const CREATED_META = '_rvn_compare_created';

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
	 * Возвращает единственный экземпляр и вешает хуки.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			add_action( 'init', array( self::$instance, 'register_hooks' ) );
		}
		return self::$instance;
	}

	/**
	 * Регистрирует хуки фронтенда и админки.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'display_post_states', array( $this, 'post_states' ), 10, 2 );
		add_filter( 'the_content', array( $this, 'maybe_auto_insert_table' ), 5 );
	}

	/**
	 * Находит ID страницы по слагу (включая корзину).
	 *
	 * @param string $slug Слаг страницы.
	 * @return int ID страницы или 0.
	 */
	public function find_by_slug( $slug ) {
		$post = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $post ) {
			return (int) $post->ID;
		}

		$found = get_posts( array(
			'name'           => sanitize_title( $slug ),
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'trash', 'future' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );

		return $found ? (int) $found[0] : 0;
	}

	/**
	 * Создаёт страницу сравнения со шорткодом таблицы.
	 *
	 * @return int ID созданной страницы или 0 при ошибке.
	 */
	public function create_page() {
		$is_ru = 'ru' === substr( determine_locale(), 0, 2 );
		$title = $is_ru ? 'Сравнение товаров' : 'Compare products';

		$id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_name'    => self::SLUG,
			'post_title'   => $title,
			'post_content' => '[rvn-compare-table]',
		), true );

		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}

		update_post_meta( $id, self::CREATED_META, '1' );
		$this->set_page_id( (int) $id );

		return (int) $id;
	}

	/**
	 * Гарантирует наличие страницы сравнения и её назначение в настройках.
	 *
	 * Если страница уже существует — не создаёт дубликат, при необходимости
	 * восстанавливает её из корзины и помечает меткой плагина.
	 *
	 * @return int ID страницы сравнения или 0 при ошибке.
	 */
	public function ensure_page() {
		$existing = $this->find_by_slug( self::SLUG );

		if ( $existing ) {
			$page = get_post( $existing );

			// Страница в корзине — восстанавливаем.
			if ( $page && 'trash' === $page->post_status ) {
				wp_untrash_post( $existing );
			}

			update_post_meta( $existing, self::CREATED_META, '1' );
			$this->set_page_id( $existing );

			return (int) $existing;
		}

		return $this->create_page();
	}

	/**
	 * Сбрасывает содержимое страницы сравнения (остаётся только шорткод).
	 *
	 * @param int $id ID страницы (0 — взять из настроек).
	 * @return bool Успех операции.
	 */
	public function reset_page_content( $id = 0 ) {
		$id = $id ? (int) $id : (int) RVN_Compare_Settings::instance()->get( 'compare_page_id', 0 );
		if ( ! $id ) {
			return false;
		}

		$result = wp_update_post( array(
			'ID'           => $id,
			'post_content' => '[rvn-compare-table]',
		) );

		return $result && ! is_wp_error( $result );
	}

	/**
	 * Сохраняет ID страницы сравнения в настройках.
	 *
	 * @param int $id ID страницы.
	 * @return void
	 */
	private function set_page_id( $id ) {
		$settings = RVN_Compare_Settings::instance();
		$all      = $settings->all();
		$all['compare_page_id'] = (int) $id;
		$settings->replace( $all );
	}

	/**
	 * Добавляет метку «Страница сравнения» в списке страниц админки.
	 *
	 * @param string[]  $states Текущие метки.
	 * @param WP_Post   $post   Текущий пост в списке.
	 * @return string[]
	 */
	public function post_states( $states, $post ) {
		if ( 'page' !== $post->post_type ) {
			return $states;
		}

		$page_id = (int) RVN_Compare_Settings::instance()->get( 'compare_page_id', 0 );
		if ( $page_id && (int) $post->ID === $page_id ) {
			$states['rvn_compare'] = __( 'Страница сравнения', 'rvn-compare' );
		}

		return $states;
	}

	/**
	 * Авто-вставка таблицы в конец страницы сравнения.
	 *
	 * Срабатывает, только если: включена опция, это страница сравнения
	 * и в контенте ещё нет шорткода [rvn-compare-table]. Приоритет 5 —
	 * чтобы шорткод был обработан стандартным do_shortcode (11).
	 *
	 * @param string $content Контент страницы.
	 * @return string
	 */
	public function maybe_auto_insert_table( $content ) {
		if ( ! is_page() || is_admin() ) {
			return $content;
		}

		$settings = RVN_Compare_Settings::instance();
		if ( '1' !== (string) $settings->get( 'auto_insert_table', '1' ) ) {
			return $content;
		}
		if ( (int) get_the_ID() !== (int) $settings->get( 'compare_page_id', 0 ) ) {
			return $content;
		}
		if ( has_shortcode( $content, 'rvn-compare-table' ) ) {
			return $content;
		}

		return $content . "\n\n" . '[rvn-compare-table]';
	}
}
