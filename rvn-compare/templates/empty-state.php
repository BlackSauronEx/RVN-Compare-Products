<?php
/**
 * Шаблон пустого состояния (список сравнения пуст).
 *
 * Переопределяется из темы копированием в тему/rvn-compare/empty-state.php.
 *
 * @package RVN_Compare
 *
 * @var string $catalog_url Ссылка на каталог (или пустая строка).
 * @var string $class_attr  Дополнительный CSS-класс.
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $catalog_url ) ) {
	$catalog_url = '';
}
if ( ! isset( $class_attr ) ) {
	$class_attr = '';
}
?>
<div class="rvn-compare-empty <?php echo esc_attr( $class_attr ); ?>">
	<p class="rvn-compare-empty__title"><?php esc_html_e( 'Список сравнения пуст', 'rvn-compare' ); ?></p>
	<?php if ( $catalog_url ) : ?>
		<a class="rvn-compare-empty__link" href="<?php echo esc_url( $catalog_url ); ?>">
			<?php esc_html_e( 'Перейти в каталог', 'rvn-compare' ); ?>
		</a>
	<?php endif; ?>
</div>
