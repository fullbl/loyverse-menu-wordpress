<?php
/**
 * Archive template for menu items.
 *
 * @package LoyverseMenu
 */

defined( 'ABSPATH' ) || exit;

get_header();

$settings = LM_Settings::get_settings();
?>
<main class="lm-archive">
	<header class="lm-archive__header">
		<h1 class="lm-archive__title"><?php echo esc_html__( 'Menu', 'loyverse-menu' ); ?></h1>
		<?php
		$terms = get_terms(
			array(
				'taxonomy'   => LM_CPT::TAXONOMY,
				'hide_empty' => true,
			)
		);
		if ( ! is_wp_error( $terms ) && $terms ) :
			?>
			<nav class="lm-archive__nav" aria-label="<?php echo esc_attr__( 'Menu categories', 'loyverse-menu' ); ?>">
				<ul>
					<?php foreach ( $terms as $term ) : ?>
						<li><a href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>
	</header>
	<?php
	echo do_shortcode(
		sprintf(
			'[loyverse_menu layout="%s" columns="%d" show_images="%d" show_descriptions="%d" show_prices="%d" show_variants="%d"]',
			esc_attr( $settings['layout'] ),
			(int) $settings['columns'],
			(int) $settings['show_images'],
			(int) $settings['show_descriptions'],
			(int) $settings['show_prices'],
			(int) $settings['show_variants']
		)
	);
	?>
</main>
<?php
get_footer();
