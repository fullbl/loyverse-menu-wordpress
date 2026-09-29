<?php
/**
 * Category taxonomy archive.
 *
 * @package MenuForLoyverse
 */

defined( 'ABSPATH' ) || exit;

get_header();

$settings = MFL_Settings::get_settings();
$queried_term = get_queried_object();
?>
<main class="lm-archive lm-archive--category">
	<header class="lm-archive__header">
		<p class="lm-archive__parent"><a href="<?php echo esc_url( get_post_type_archive_link( MFL_CPT::POST_TYPE ) ); ?>"><?php echo esc_html__( 'Menu', 'menu-for-loyverse' ); ?></a></p>
		<h1 class="lm-archive__title"><?php echo esc_html( $queried_term instanceof WP_Term ? $queried_term->name : '' ); ?></h1>
		<?php if ( $queried_term instanceof WP_Term && $queried_term->description ) : ?>
			<div class="lm-archive__description"><?php echo wp_kses_post( wpautop( $queried_term->description ) ); ?></div>
		<?php endif; ?>
	</header>
	<?php
	$slug = $queried_term instanceof WP_Term ? $queried_term->slug : '';
	echo do_shortcode(
		sprintf(
			'[loyverse_menu category="%s" layout="%s" columns="%d" show_images="%d" show_descriptions="%d" show_prices="%d" show_variants="%d"]',
			esc_attr( $slug ),
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
