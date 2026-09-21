<?php
/**
 * REST-эндпоинты плагина.
 *
 * Все мутации списка сравнения проходят через эти маршруты (для
 * залогиненных пользователей). Гости мутируют список локально в JS,
 * но серверные маршруты безопасны и для них (nonce + проверки).
 *
 * Маршруты (namespace rvn-compare/v1):
 *   GET  /count               — текущее количество товаров в списке.
 *   POST /toggle              — добавить/убрать товар (по action).
 *   POST /remove              — удалить товар по ID.
 *   POST /clear               — очистить список целиком.
 *   POST /merge               — слить гостевой список после логина.
 *   GET  /table               — HTML активной вкладки таблицы (AJAX).
 *   GET  /search              — поиск товаров для кнопки «Добавить товар» (R3-01).
 *
 * @package RVN_Compare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Регистрация REST-маршрутов и их обработчики.
 */
final class RVN_Compare_Rest {

	/**
	 * Пространство имён маршрутов без версии, напр. 'rvn-compare/v1'.
	 */
	const NS = 'rvn-compare/v1';

	/**
	 * Единственный экземпляр.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Возвращает единственный экземпляр и вешает регистрацию маршрутов.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			add_action( 'rest_api_init', array( self::$instance, 'register_routes' ) );
		}
		return self::$instance;
	}

	/**
	 * Регистрирует все маршруты плагина.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/count',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_count' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NS,
			'/toggle',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_toggle' ),
				'permission_callback' => array( $this, 'mutate_permission' ),
				'args'                => array(
					'product_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $v ) {
							return $v > 0;
						},
					),
					'action'     => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/remove',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_remove' ),
				'permission_callback' => array( $this, 'mutate_permission' ),
				'args'                => array(
					'product_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $v ) {
							return $v > 0;
						},
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/clear',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_clear' ),
				'permission_callback' => array( $this, 'mutate_permission' ),
			)
		);

		register_rest_route(
			self::NS,
			'/merge',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_merge' ),
				'permission_callback' => array( $this, 'mutate_permission' ),
				'args'                => array(
					'items' => array(
						'required'          => true,
						'sanitize_callback' => array( $this, 'sanitize_ids' ),
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/table',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_table' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'ids'  => array(
						'sanitize_callback' => array( $this, 'sanitize_ids' ),
					),
					'tab'  => array(
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_search' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'q'  => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Nonce-проверка для мутаций.
	 *
	 * nonce передаётся в заголовке X-WP-Nonce (стандарт REST) или в поле
	 * nonce тела запроса. Отказ инициализирует стандартный подсчёт
	 * rate limit, поэтому гостям мы разрешаем читающие маршруты, но
	 * не пускаем без nonce в мутации.
	 *
	 * @return bool
	 */
	public function permission() {
		return is_user_logged_in() && wp_verify_nonce( self::nonce(), 'wp_rest' )
			? true
			: current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Порядок обработки POST-мутации: throttle + валидация.
	 *
	 * @param WP_REST_Request $request Запрос.
	 * @return true|WP_Error
	 */
	public function mutate_permission( $request ) {
		$base = $this->permission();
		if ( true !== $base ) {
			return $base;
		}

		// Для гостей всегда разрешено (check() вернёт true).
		if ( ! RVN_Compare_Rate_Limit::check() ) {
			return new WP_Error(
				'rvn_compare_rate_limited',
				__( 'Слишком много запросов. Попробуйте через секунду.', 'rvn-compare' ),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * Возвращает текущий nonce для REST-запросов.
	 *
	 * @return string
	 */
	public static function nonce() {
		$nonce = '';
		if ( function_exists( 'wp_get_nonce' ) ) {
			$nonce = wp_create_nonce( 'wp_rest' );
		}

		return $nonce;
	}

	/**
	 * Приводит строку/массив ID к массиву неотрицательных целых.
	 *
	 * @param mixed $value Входное значение.
	 * @return int[]
	 */
	public function sanitize_ids( $value ) {
		if ( is_string( $value ) ) {
			$value = array_map( 'absint', array_filter( (array) explode( ',', $value ) ) );
		} elseif ( ! is_array( $value ) ) {
			$value = array();
		}

		$out = array();
		foreach ( $value as $id ) {
			$id = absint( $id );
			if ( $id ) {
				$out[] = $id;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * GET /count — количество товаров в списке.
	 *
	 * @param WP_REST_Request $request Запрос.
	 * @return WP_REST_Response
	 */
	public function handle_count( $request ) {
		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'count' => count( RVN_Compare_Storage::instance()->get_items() ),
				),
			)
		);
	}

	/**
	 * POST /toggle — добавляет или удаляет товар по action.
	 *
	 * @param WP_REST_Request $request Запрос.
	 * @return WP_REST_Response
	 */
	public function handle_toggle( $request ) {
		$storage = RVN_Compare_Storage::instance();
		$id      = (int) $request->get_param( 'product_id' );
		$action  = $request->get_param( 'action' );

		if ( 'remove' === $action ) {
			$items = $storage->remove_item( $id );
			return $this->items_response( 'removed', $items );
		}

		$result = $storage->add_item( $id );
		$status = $result['ok'] ? 200 : 400;

		return new WP_REST_Response(
			array(
				'success' => $result['ok'],
				'data'    => array(
					'reason' => $result['reason'],
					'count'  => count( $result['items'] ),
					'items'  => $result['items'],
				),
			),
			$status
		);
	}

	/**
	 * POST /remove — удаляет товар по ID.
	 *
	 * @param WP_REST_Request $request Запрос.
	 * @return WP_REST_Response
	 */
	public function handle_remove( $request ) {
		$id    = (int) $request->get_param( 'product_id' );
		$items = RVN_Compare_Storage::instance()->remove_item( $id );

		return $this->items_response( 'removed', $items );
	}

	/**
	 * POST /clear — очищает список целиком.
	 *
	 * @param WP_REST_Request $request Запрос.
	 * @return WP_REST_Response
	 */
	public function handle_clear( $request ) {
		$items = RVN_Compare_Storage::instance()->clear_items();

		return $this->items_response( 'cleared', $items );
	}

	/**
	 * POST /merge — сливает гостевой список со списком пользователя.
	 *
	 * @param WP_REST_Request $request Запрос.
	 * @return WP_REST_Response
	 */
	public function handle_merge( $request ) {
		$guest = array_map( 'absint', array_map( function ( $v ) {
			return (int) $v;
		}, $this->sanitize_ids( $request->get_param( 'items' ) ) ) );

		$items = RVN_Compare_Storage::instance()->merge_guest( $guest );

		return $this->items_response( 'merged', $items );
	}

	/**
	 * GET /table — HTML активной вкладки таблицы (для AJAX-подгрузки).
	 *
	 * Пока возвращает структуру-заглушку: полноценный рендер вкладок —
	 * в одном из следующих шагов (class-rvn-compare-table.php).
	 *
	 * @param WP_REST_Request $request Запрос.
	 * @return WP_REST_Response
	 */
	public function handle_table( $request ) {
		$ids = $this->sanitize_ids( $request->get_param( 'ids' ) );
		$tab = sanitize_key( (string) $request->get_param( 'tab' ) );

		/*
		 * Гард: без WooCommerce об этом маршруте мы не оказываемся (его
		 * регистрация происходит только после maybe_load), но на случай
		 * прямого вызова возвращаем понятный 503.
		 */
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'message' => 'WooCommerce is required' ),
				),
				503
			);
		}

		// Рендер активной вкладки (или первой доступной).
		$resolver = RVN_Compare_Categories::instance();
		if ( ! $tab && ! empty( $ids ) ) {
			$all_tabs = $resolver->build_tabs( $ids );
			if ( ! empty( $all_tabs ) ) {
				$tab = array_keys( $all_tabs )[0];
			}
		}

		$tab_ids = array_values( array_filter( $ids, function ( $product_id ) use ( $resolver, $tab ) {
			return in_array( $tab, $resolver->product_context_keys( $product_id ), true );
		} ) );

		$html = RVN_Compare_Table::instance()->render_tab_html( $tab_ids, $tab );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'html' => $html,
					'tab'  => $tab,
					'ids'  => $tab_ids,
				),
			)
		);
	}

	/**
	 * GET /search — подсказки товаров для кнопки «Добавить товар» (R3-01).
	 *
	 * Клиентский поиск по названию/SKU публичных товаров. Вызывается и для
	 * гостей (без nonce), поэтому лимитируется скользящим окном, а данные
	 * отдают только публичные и доступные товары.
	 *
	 * @param WP_REST_Request $request Запрос.
	 * @return WP_REST_Response
	 */
	public function handle_search( $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'message' => 'WooCommerce is required' ),
				),
				503
			);
		}

		// Мягкий лимит для анонимного поиска (тот же механизм, что у мутаций).
		if ( ! RVN_Compare_Rate_Limit::check() ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'message' => 'rate_limited' ),
				),
				429
			);
		}

		$q = (string) $request->get_param( 'q' );
		$q = trim( $q );
		if ( '' === $q ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'data'    => array( 'results' => array(), 'count' => 0 ),
				)
			);
		}

		$ids = wc_get_products(
			array(
				'limit'  => 15,
				'status' => 'publish',
				's'      => $q,
				'return' => 'ids',
			)
		);

		$limit       = (int) RVN_Compare_Settings::instance()->get( 'max_items_total', 50 );
		$button      = RVN_Compare_Buttons::instance();
		$results     = array();
		$current_ids = array_values( RVN_Compare_Storage::instance()->get_items() );

		foreach ( (array) $ids as $id ) {
			$product = wc_get_product( (int) $id );
			if ( ! $product instanceof WC_Product ) {
				continue;
			}
			if ( 'publish' !== $product->get_status() ) {
				continue;
			}
			// Канонический вид: вариации не добавляем отдельной строкой (R2-16).
			if ( $product->is_type( 'variation' ) ) {
				continue;
			}
			// Исключённые товары/категории (там нет кнопки) не предлагаем.
			if ( $button->is_excluded( (int) $id, '' ) ) {
				continue;
			}

			$results[] = array(
				'id'     => (int) $id,
				'title'  => $product->get_name(),
				'sku'    => (string) $product->get_sku(),
				'price'  => wp_strip_all_tags( (string) $product->get_price_html() ),
				'inList' => in_array( (int) $id, $current_ids, true ),
			);

			if ( count( $results ) >= 10 ) {
				break;
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'results' => $results,
					'count'   => count( $results ),
					'limit'   => $limit,
				),
			)
		);
	}

	/**
	 * Формирует единообразный JSON-ответ мутаций.
	 *
	 * @param string $reason Причина/итог операции.
	 * @param int[]  $items  Актуальный список ID после операции.
	 * @return WP_REST_Response
	 */
	private function items_response( $reason, $items ) {
		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'reason' => $reason,
					'count'  => count( $items ),
					'items'  => $items,
				),
			)
		);
	}
}
