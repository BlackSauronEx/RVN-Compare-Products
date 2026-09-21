<?php
/**
 * Внутренние части таблицы: шапка товаров и ряды характеристик.
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

$settings   = RVN_Compare_Settings::instance();
$show_stock = '1' === (string) $settings->get( 'show_stock', '1' );
$photo_fit  = (string) $settings->get( 'photo_fit', 'contain' );

/*
 * Колонки шапки товаров: фото / название (3 строки) / цена + Купить / крестик.
 */
?>
<div class="rvn-compare-columns" data-rvn-compare-columns>
	<?php foreach ( $ids as $id ) :
		$product = isset( $products[ $id ] ) ? $products[ $id ] : null;
		if ( ! $product ) { continue; }

		$image = $product->get_image_id() ? wp_get_attachment_image( $product->get_image_id(), 'woocommerce_thumbnail', false, array( 'class' => 'rvn-compare-col__photo' ) ) : wc_placeholder_img( 'woocommerce_thumbnail', array( 'class' => 'rvn-compare-col__photo' ) );
		$url   = get_permalink( $id );
		$price = $product->get_price_html();
		?>
		<div class="rvn-compare-col rvn-compare-col--header" data-rvn-compare-col="<?php echo (int) $id; ?>">
			<button type="button" class="rvn-compare-col__remove" data-rvn-compare-remove="<?php echo (int) $id; ?>" aria-label="<?php esc_attr_e( 'Удалить товар', 'rvn-compare' ); ?>">✕</button>

			<a class="rvn-compare-col__thumb" href="<?php echo esc_url( $url ); ?>" style="object-fit: <?php echo esc_attr( $photo_fit ); ?>">
				<?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — wp_get_attachment_image(). ?>
			</a>

			<a class="rvn-compare-col__title" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>

			<span class="rvn-compare-col__price"><?php echo $price; // phpcs:ignore WordPress.Security.EscapeOutput -- get_price_html() с эскейпингом Woo. ?></span>

			<div class="rvn-compare-col__buy">
				<?php
				// Кнопка «Купить»/«Выбрать вариант» (для вариативных — ведём на карточку).
				$is_variable = $product->is_type( 'variable' );
				if ( $is_variable ) :
					?>
					<a class="rvn-compare-buy" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Выбрать вариант', 'rvn-compare' ); ?></a>
				<?php else : ?>
					<a class="rvn-compare-buy" href="<?php echo esc_url( $url ); ?>?add-to-cart=<?php echo (int) $id; ?>"><?php esc_html_e( 'Купить', 'rvn-compare' ); ?></a>
				<?php endif; ?>

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
		</div>
	<?php endforeach; ?>
</div>

<?php
/*
 * Ряды характеристик, сгруппированные по группам.
 */
?>
<div class="rvn-compare-rows" data-rvn-compare-rows>
	<?php foreach ( $groups as $group_key => $rows ) :
		$labels = RVN_Compare_Fields::instance()->group_labels();
		$group_label = isset( $labels[ $group_key ] ) ? $labels[ $group_key ] : ucfirst( str_replace( array( '-', '_' ), ' ', $group_key ) );
		?>
		<div class="rvn-compare-group" data-rvn-compare-group="<?php echo esc_attr( $group_key ); ?>">
			<button type="button" class="rvn-compare-group__head" data-rvn-compare-group-toggle>
				<span class="rvn-compare-group__title"><?php echo esc_html( $group_label ); ?></span>
				<span class="rvn-compare-group__arrow" aria-hidden="true">▲</span>
			</button>

			<div class="rvn-compare-group__body">
				<?php foreach ( $rows as $row ) :
					$field = $row['field'];
					?>
					<div class="rvn-compare-row<?php echo $row['diff'] ? ' has-diff' : ''; ?>" data-rvn-compare-row>
						<div class="rvn-compare-row__label">
							<span><?php echo esc_html( $field['label'] ); ?></span>
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
