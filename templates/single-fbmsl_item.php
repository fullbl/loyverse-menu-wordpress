<?php
/**
 * Single menu item template.
 *
 * @package FullBLMenuSyncLoyverse
 */

defined( 'ABSPATH' ) || exit;

get_header();

$settings = FBMSL_Settings::get_settings();
?>
<main class="fbmsl-single">
	<?php
	while ( have_posts() ) :
		the_post();
		$item_post_id = get_the_ID();
		$price        = get_post_meta( $item_post_id, '_fbmsl_price', true );
		$variants     = FBMSL_Templates::get_variants( $item_post_id );
		$terms        = get_the_terms( $item_post_id, FBMSL_CPT::TAXONOMY );
		?>
		<article <?php post_class( 'fbmsl-single__article' ); ?>>
			<p class="fbmsl-single__breadcrumb">
				<a href="<?php echo esc_url( get_post_type_archive_link( FBMSL_CPT::POST_TYPE ) ); ?>"><?php echo esc_html__( 'Menu', 'fullbl-menu-sync-for-loyverse' ); ?></a>
				<?php if ( $terms && ! is_wp_error( $terms ) ) : ?>
					<span aria-hidden="true"> / </span>
					<a href="<?php echo esc_url( get_term_link( $terms[0] ) ); ?>"><?php echo esc_html( $terms[0]->name ); ?></a>
				<?php endif; ?>
			</p>

			<?php if ( $settings['show_images'] && has_post_thumbnail() ) : ?>
				<div class="fbmsl-single__image">
					<?php the_post_thumbnail( 'large' ); ?>
				</div>
			<?php endif; ?>

			<header class="fbmsl-single__header">
				<h1 class="fbmsl-single__title"><?php the_title(); ?></h1>
				<?php if ( $settings['show_prices'] && '' !== $price && null !== $price ) : ?>
					<p class="fbmsl-single__price"><?php echo esc_html( FBMSL_Templates::format_price( $price ) ); ?></p>
				<?php endif; ?>
			</header>

			<?php if ( $settings['show_descriptions'] && get_the_content() ) : ?>
				<div class="fbmsl-single__content">
					<?php the_content(); ?>
				</div>
			<?php endif; ?>

			<?php
			$meaningful = array_filter(
				$variants,
				static function ( $v ) {
					return ! empty( $v['option1_value'] ) || ! empty( $v['option2_value'] ) || ! empty( $v['option3_value'] );
				}
			);
			if ( $settings['show_variants'] && $meaningful ) :
				?>
				<ul class="fbmsl-single__variants">
					<?php foreach ( $meaningful as $variant ) : ?>
						<?php
						$label_parts = array_filter(
							array(
								$variant['option1_value'] ?? '',
								$variant['option2_value'] ?? '',
								$variant['option3_value'] ?? '',
							)
						);
						?>
						<li>
							<span><?php echo esc_html( implode( ' / ', $label_parts ) ); ?></span>
							<?php if ( $settings['show_prices'] && isset( $variant['price'] ) && null !== $variant['price'] ) : ?>
								<span><?php echo esc_html( FBMSL_Templates::format_price( $variant['price'] ) ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</article>
	<?php endwhile; ?>
</main>
<?php
get_footer();
