<?php
/**
 * Шаблон таблицы сравнения.
 *
 * Переопределяется из темы копированием в тему/rvn-compare/compare-table.php.
 * Структура данных готовится классом RVN_Compare_Table (см. §4.4 живого ТЗ):
 * здесь только рисуем, без бизнес-логики.
 *
 * @package RVN_Compare
 *
 * @var array $data {
 *   @type array  $tabs   Вкладки: key => ['label'=>string, 'ids'=>int[]].
 *   @type string $active Ключ активной вкладки.
 *   @type string $class  Дополнительный CSS-класс.
 * }
 */

defined( 'ABSPATH' ) || exit;

$tabs   = isset( $data['tabs'] ) ? $data['tabs'] : array();
$active = isset( $data['active'] ) ? $data['active'] : '';

$active_ids = isset( $tabs[ $active ]['ids'] ) ? $tabs[ $active ]['ids'] : array();
$structure  = RVN_Compare_Table::instance()->build_structure( $active_ids, $active );
?>
<div class="rvn-compare-table <?php echo esc_attr( $data['class'] ); ?>" data-rvn-compare-table data-active="<?php echo esc_attr( $active ); ?>">

	<?php if ( count( $tabs ) > 1 ) : ?>
		<div class="rvn-compare-tabs" data-rvn-compare-tabs>
			<?php foreach ( $tabs as $key => $tab ) : ?>
				<button type="button"
					class="rvn-compare-tab<?php echo $key === $active ? ' is-active' : ''; ?>"
					data-rvn-compare-tab="<?php echo esc_attr( $key ); ?>">
					<?php echo esc_html( $tab['label'] ); ?>
					<span class="rvn-compare-tab__count">(<?php echo esc_html( (string) count( $tab['ids'] ) ); ?>)</span>
				</button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="rvn-compare-toolbar" data-rvn-compare-toolbar>
		<label class="rvn-compare-diff-toggle">
			<input type="checkbox" data-rvn-compare-only-diff value="1" />
			<span><?php esc_html_e( 'Только различия', 'rvn-compare' ); ?></span>
		</label>
		<button type="button" class="rvn-compare-clear" data-rvn-compare-clear>
			<?php echo esc_html( (string) RVN_Compare_Settings::instance()->get( 'clear_text', __( 'Очистить всё', 'rvn-compare' ) ) ); ?>
		</button>
	</div>

	<div class="rvn-compare-table__viewport" data-rvn-compare-viewport>
		<?php
		// Внутренние части (шапка, ряды, группы) — для основной загрузки
		// и подмены по AJAX при переключении вкладок.
		include RVN_COMPARE_DIR . 'templates/parts.php';
		?>
	</div>

</div>
