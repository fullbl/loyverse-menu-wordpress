<?php
/**
 * Menu list partial (shortcode / archives).
 *
 * @package FullBLMenuSyncLoyverse
 *
 * @var WP_Query $query
 * @var string   $layout
 * @var int      $columns
 * @var bool     $show_images
 * @var bool     $show_descriptions
 * @var bool     $show_prices
 * @var bool     $show_variants
 * @var bool     $group_by_category
 */

defined( 'ABSPATH' ) || exit;

$layout            = $layout ?? 'grid';
$columns           = FBMSL_Settings::sanitize_columns( $columns ?? 2 );
$show_images       = $show_images ?? true;
$show_descriptions = $show_descriptions ?? true;
$show_prices       = $show_prices ?? true;
$show_variants     = $show_variants ?? true;
$group_by_category = $group_by_category ?? false;

if ( empty( $query ) || ! $query->have_posts() ) {
	echo '<p class="fbmsl-empty">' . esc_html__( 'No menu items found.', 'fullbl-menu-sync-for-loyverse' ) . '</p>';
	return;
}

$grouped = array();
if ( $group_by_category ) {
	while ( $query->have_posts() ) {
		$query->the_post();
		$terms = get_the_terms( get_the_ID(), FBMSL_CPT::TAXONOMY );
		$key   = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->term_id : 0;
		$label = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : __( 'Other', 'fullbl-menu-sync-for-loyverse' );
		if ( ! isset( $grouped[ $key ] ) ) {
			$grouped[ $key ] = array(
				'label' => $label,
				'posts' => array(),
			);
		}
		$grouped[ $key ]['posts'][] = get_post();
	}
	wp_reset_postdata();
} else {
	$grouped[0] = array(
		'label' => '',
		'posts' => $query->posts,
	);
}
?>
<div class="fbmsl-menu fbmsl-menu--<?php echo esc_attr( $layout ); ?>" style="--fbmsl-columns: <?php echo esc_attr( (string) $columns ); ?>">
	<?php foreach ( $grouped as $group ) : ?>
		<?php if ( $group['label'] ) : ?>
			<h2 class="fbmsl-menu__category"><?php echo esc_html( $group['label'] ); ?></h2>
		<?php endif; ?>
		<ul class="fbmsl-menu__list">
			<?php foreach ( $group['posts'] as $post_obj ) : ?>
				<?php
				setup_postdata( $post_obj );
				$item_post_id        = (int) $post_obj->ID;
				$price               = get_post_meta( $item_post_id, '_fbmsl_price', true );
				$variants            = FBMSL_Templates::get_variants( $item_post_id );
				$meaningful_variants = array_filter(
					$variants,
					static function ( $v ) {
						return ! empty( $v['option1_value'] ) || ! empty( $v['option2_value'] ) || ! empty( $v['option3_value'] );
					}
				);
				?>
				<li class="fbmsl-menu__item">
					<?php if ( $show_images && has_post_thumbnail( $item_post_id ) ) : ?>
						<a class="fbmsl-menu__image" href="<?php echo esc_url( get_permalink( $item_post_id ) ); ?>">
							<?php echo get_the_post_thumbnail( $item_post_id, 'medium' ); ?>
						</a>
					<?php endif; ?>
					<div class="fbmsl-menu__body">
						<div class="fbmsl-menu__header">
							<h3 class="fbmsl-menu__title">
								<a href="<?php echo esc_url( get_permalink( $item_post_id ) ); ?>"><?php echo esc_html( get_the_title( $item_post_id ) ); ?></a>
							</h3>
							<?php if ( $show_prices && '' !== $price && null !== $price ) : ?>
								<span class="fbmsl-menu__price"><?php echo esc_html( FBMSL_Templates::format_price( $price ) ); ?></span>
							<?php endif; ?>
						</div>
						<?php
						$content = (string) get_post_field( 'post_content', $item_post_id );
						if ( $show_descriptions && '' !== trim( wp_strip_all_tags( $content ) ) ) :
							?>
							<div class="fbmsl-menu__description">
								<?php echo esc_html( wp_trim_words( wp_strip_all_tags( $content ), 40 ) ); ?>
							</div>
						<?php endif; ?>
						<?php if ( $show_variants && $meaningful_variants ) : ?>
							<ul class="fbmsl-menu__variants">
								<?php foreach ( $meaningful_variants as $variant ) : ?>
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
										<span class="fbmsl-menu__variant-name"><?php echo esc_html( implode( ' / ', $label_parts ) ); ?></span>
										<?php if ( $show_prices && isset( $variant['price'] ) && null !== $variant['price'] && '' !== $variant['price'] ) : ?>
											<span class="fbmsl-menu__variant-price"><?php echo esc_html( FBMSL_Templates::format_price( $variant['price'] ) ); ?></span>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endforeach; ?>
</div>
<?php
wp_reset_postdata();
