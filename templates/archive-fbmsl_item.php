<?php
/**
 * Archive template for menu items.
 *
 * @package FullBLMenuSyncLoyverse
 */

defined( 'ABSPATH' ) || exit;

get_header();

$settings = FBMSL_Settings::get_settings();
?>
<main class="fbmsl-archive">
	<header class="fbmsl-archive__header">
		<h1 class="fbmsl-archive__title"><?php echo esc_html__( 'Menu', 'fullbl-menu-sync-for-loyverse' ); ?></h1>
		<?php
		$terms = get_terms(
			array(
				'taxonomy'   => FBMSL_CPT::TAXONOMY,
				'hide_empty' => true,
			)
		);
		if ( ! is_wp_error( $terms ) && $terms ) :
			?>
			<nav class="fbmsl-archive__nav" aria-label="<?php echo esc_attr__( 'Menu categories', 'fullbl-menu-sync-for-loyverse' ); ?>">
				<ul>
					<?php foreach ( $terms as $menu_term ) : ?>
						<li><a href="<?php echo esc_url( get_term_link( $menu_term ) ); ?>"><?php echo esc_html( $menu_term->name ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>
	</header>
	<?php
	echo wp_kses_post(
		do_shortcode(
			sprintf(
				'[fbmsl_menu layout="%s" columns="%d" show_images="%d" show_descriptions="%d" show_prices="%d" show_variants="%d"]',
				esc_attr( $settings['layout'] ),
				(int) $settings['columns'],
				(int) $settings['show_images'],
				(int) $settings['show_descriptions'],
				(int) $settings['show_prices'],
				(int) $settings['show_variants']
			)
		)
	);
	?>
</main>
<?php
get_footer();
