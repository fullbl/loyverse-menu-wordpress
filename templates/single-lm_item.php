<?php
/**
 * Single menu item template.
 *
 * @package LoyverseMenu
 */

defined( 'ABSPATH' ) || exit;

get_header();

$settings = LM_Settings::get_settings();
?>
<main class="lm-single">
	<?php
	while ( have_posts() ) :
		the_post();
		$post_id  = get_the_ID();
		$price    = get_post_meta( $post_id, '_lm_price', true );
		$variants = LM_Templates::get_variants( $post_id );
		$terms    = get_the_terms( $post_id, LM_CPT::TAXONOMY );
		?>
		<article <?php post_class( 'lm-single__article' ); ?>>
			<p class="lm-single__breadcrumb">
				<a href="<?php echo esc_url( get_post_type_archive_link( LM_CPT::POST_TYPE ) ); ?>"><?php echo esc_html__( 'Menu', 'loyverse-menu' ); ?></a>
				<?php if ( $terms && ! is_wp_error( $terms ) ) : ?>
					<span aria-hidden="true"> / </span>
					<a href="<?php echo esc_url( get_term_link( $terms[0] ) ); ?>"><?php echo esc_html( $terms[0]->name ); ?></a>
				<?php endif; ?>
			</p>

			<?php if ( $settings['show_images'] && has_post_thumbnail() ) : ?>
				<div class="lm-single__image">
					<?php the_post_thumbnail( 'large' ); ?>
				</div>
			<?php endif; ?>

			<header class="lm-single__header">
				<h1 class="lm-single__title"><?php the_title(); ?></h1>
				<?php if ( $settings['show_prices'] && '' !== $price && null !== $price ) : ?>
					<p class="lm-single__price"><?php echo esc_html( LM_Templates::format_price( $price ) ); ?></p>
				<?php endif; ?>
			</header>

			<?php if ( $settings['show_descriptions'] && get_the_content() ) : ?>
				<div class="lm-single__content">
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
				<ul class="lm-single__variants">
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
								<span><?php echo esc_html( LM_Templates::format_price( $variant['price'] ) ); ?></span>
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
