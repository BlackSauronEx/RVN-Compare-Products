<?php
/**
 * Внутренние части таблицы: шапка товаров и ряды характеристик.
 *
 * Структура под слайдер (R2-02, §9 п.6 живого ТЗ):
 *  - общий горизонтальный скролл-контейнер (.clip): шапка и ряды значений
 *    прокручиваются синхронно (единый scrollLeft), колонки фиксированной
 *    ширины «контейнер / N видимых»;
 *  - подписи характеристик (и уголок шапки) закреплены слева (sticky) и не
 *    уезжают при прокрутке; группы — полноширинные заголовки-разделители.
 *
 * Используется compare-table.php (основной рендер) и REST /table
 * (подгрузка вкладок по AJAX). Получает уже готовую $structure.
 *
 * @package RVN_Compare
 *
 * @var array $structure {
 *   @type int[]        $ids      Порядок колонок.
 *   @type WC_Product[] $products Карта id => товар.
 *   @type array        $groups   Ряды сгруппированы: group_key => rows[].
 * }
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $structure ) ) {
	return;
}

$ids      = $structure['ids'];
$products = $structure['products'];
$groups   = $structure['groups'];

$settings = RVN_Compare_Settings::instance();

$settings_design = (array) $settings->get( 'design', array() );
$def_design      = (array) $settings->defaults()['design'];
$buy_header      = isset( $settings_design['behavior']['buy_header'] ) ? $settings_design['behavior']['buy_header'] : $def_design['behavior']['buy_header'];
$buy_bottom      = isset( $settings_design['behavior']['buy_bottom'] ) ? $settings_design['behavior']['buy_bottom'] : $def_design['behavior']['buy_bottom'];
$buy_floating    = isset( $settings_design['behavior']['buy_floating'] ) ? $settings_design['behavior']['buy_floating'] : $def_design['behavior']['buy_floating'];
$inherit_theme   = isset( $settings_design['behavior']['inherit_theme_styles'] ) ? $settings_design['behavior']['inherit_theme_styles'] : $def_design['behavior']['inherit_theme_styles'];
$inherit_theme   = ( '1' === (string) $inherit_theme );
$photo_fit       = isset( $settings_design['geometry']['photo_fit'] ) ? $settings_design['geometry']['photo_fit'] : $def_design['geometry']['photo_fit'];

$show_stock    = '1' === (string) $settings->get( 'show_stock', '1' );
$can_collapse  = '1' === (string) $settings->get( 'collapse_groups', '1' );
$default_state = (string) $settings->get( 'groups_default_state', 'expanded' );

$columns = array(
	'desktop' => (int) $settings->get( 'columns_desktop', 5 ),
	'tablet'  => (int) $settings->get( 'columns_tablet', 3 ),
	'mobile'  => (int) $settings->get( 'columns_mobile', 2 ),
);

$count = count( $ids );
?>
<div class="rvn-compare-scroller"
	data-rvn-compare-scroller
	data-columns-desktop="<?php echo esc_attr( $columns['desktop'] ); ?>"
	data-columns-tablet="<?php echo esc_attr( $columns['tablet'] ); ?>"
	data-columns-mobile="<?php echo esc_attr( $columns['mobile'] ); ?>"
	data-total="<?php echo esc_attr( $count ); ?>">

	<button type="button" class="rvn-compare-arrow rvn-compare-arrow--prev" data-rvn-compare-arrow="prev" aria-label="<?php esc_attr_e( 'Предыдущие товары', 'rvn-compare' ); ?>">‹</button>
	<button type="button" class="rvn-compare-arrow rvn-compare-arrow--next" data-rvn-compare-arrow="next" aria-label="<?php esc_attr_e( 'Следующие товары', 'rvn-compare' ); ?>">›</button>

	<div class="rvn-compare-clip" data-rvn-compare-clip>
		<div class="rvn-compare-track" data-rvn-compare-track>

			<!-- Шапка: уголок-распорка (sticky) + колонки товаров (скроллятся) -->
			<div class="rvn-compare-columns" data-rvn-compare-header>
				<div class="rvn-compare-corner" aria-hidden="true"></div>
				<?php foreach ( $ids as $id ) :
					$product = isset( $products[ $id ] ) ? $products[ $id ] : null;
					if ( ! $product ) { continue; }

					$image = $product->get_image_id() ? wp_get_attachment_image( $product->get_image_id(), 'woocommerce_thumbnail', false, array( 'class' => 'rvn-compare-col__photo' ) ) : wc_placeholder_img( 'woocommerce_thumbnail', array( 'class' => 'rvn-compare-col__photo' ) );
					$url   = get_permalink( $id );
					$price = $product->get_price_html();
					?>
					<div class="rvn-compare-col rvn-compare-col--header" data-rvn-compare-col="<?php echo (int) $id; ?>">
						<button type="button" class="rvn-compare-col__remove" data-rvn-compare-remove="<?php echo (int) $id; ?>" aria-label="<?php esc_attr_e( 'Удалить товар', 'rvn-compare' ); ?>">✕</button>

						<a class="rvn-compare-col__thumb" href="<?php echo esc_url( $url ); ?>">
							<?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — wp_get_attachment_image(). ?>
						</a>

						<a class="rvn-compare-col__title" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>

						<span class="rvn-compare-col__price"><?php echo $price; // phpcs:ignore WordPress.Security.EscapeOutput -- get_price_html(). ?></span>

						<div class="rvn-compare-col__buy rvn-compare-buy-slot" data-buy-slot="header">
							<?php rvn_compare_buy_button( $product, $id, $buy_header, $inherit_theme ); ?>

							<?php if ( $show_stock ) : ?>
								<span class="rvn-compare-col__stock">
									<?php
									$availability = $product->get_availability();
									echo isset( $availability['availability'] ) && $availability['availability']
										? esc_html( $availability['availability'] )
										: '';
									?>
								</span>
							<?php endif; ?>
						</div>

						<div class="rvn-compare-buy-slot" data-buy-slot="bottom"><?php rvn_compare_buy_button( $product, $id, $buy_bottom, $inherit_theme ); ?></div>
						<div class="rvn-compare-buy-slot rvn-compare-buy-slot--floating-only" data-buy-slot="floating"><?php rvn_compare_buy_button( $product, $id, $buy_floating, $inherit_theme ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Ряды характеристик, сгруппированы -->
			<div class="rvn-compare-rows" data-rvn-compare-rows>
				<?php foreach ( $groups as $group_key => $rows ) :
					$labels = RVN_Compare_Fields::instance()->group_labels();
					$group_label = isset( $labels[ $group_key ] ) ? $labels[ $group_key ] : ucfirst( str_replace( array( '-', '_' ), ' ', $group_key ) );
					?>
					<div class="rvn-compare-group<?php echo ( $can_collapse && 'collapsed' === $default_state ) ? ' is-collapsed' : ''; ?>" data-rvn-compare-group="<?php echo esc_attr( $group_key ); ?>">
						<?php if ( $can_collapse ) : ?>
						<button type="button" class="rvn-compare-group__head" data-rvn-compare-group-toggle>
							<span class="rvn-compare-group__title"><?php echo esc_html( $group_label ); ?></span>
							<span class="rvn-compare-group__arrow" aria-hidden="true">▲</span>
						</button>
						<?php else : ?>
						<div class="rvn-compare-group__head rvn-compare-group__head--static"><span class="rvn-compare-group__title"><?php echo esc_html( $group_label ); ?></span></div>
						<?php endif; ?>

						<div class="rvn-compare-group__body">
							<?php foreach ( $rows as $row ) :
								$field = $row['field'];
								?>
								<div class="rvn-compare-row<?php echo $row['diff'] ? ' has-diff' : ''; ?>" data-rvn-compare-row>
							<div class="rvn-compare-row__label">
								<span><?php echo esc_html( $field['label'] ); ?></span>
								<?php if ( ! empty( $field['hint'] ) ) : ?>
									<button type="button" class="rvn-compare-tip" data-rvn-compare-tip="<?php echo esc_attr( $field['hint'] ); ?>" aria-label="<?php esc_attr_e( 'Подсказка', 'rvn-compare' ); ?>">?</button>
								<?php endif; ?>
							</div>
									<div class="rvn-compare-row__values" data-rvn-compare-row-values>
										<?php foreach ( $row['values'] as $value ) : ?>
											<span class="rvn-compare-row__value"><?php echo esc_html( $value ); ?></span>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

		</div><!-- /track -->
	</div><!-- /clip -->
</div><!-- /scroller -->
