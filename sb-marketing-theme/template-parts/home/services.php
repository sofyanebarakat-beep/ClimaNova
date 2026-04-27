<?php
/**
 * Homepage — Services section
 * Pulls from the 'climanova_service' CPT (up to 6 posts).
 * Falls back to hardcoded defaults if no posts exist yet.
 *
 * @package SBMarketingTheme
 */

$title    = sbmt_mod( 'sbmt_services_title',    'Trouvez le service adapté à vos besoins' );
$subtitle = sbmt_mod( 'sbmt_services_subtitle', 'Installation, dépannage ou mise aux normes — ClimaNova Energie vous met en relation avec les bons experts pour un résultat fiable et durable.' );
$btn_text = sbmt_mod( 'sbmt_services_btn_text', 'Tous nos services' );
$btn_url  = sbmt_mod( 'sbmt_services_btn_url',  home_url( '/services' ) );

// Query CPT
$services_query = new WP_Query( [
	'post_type'      => 'climanova_service',
	'posts_per_page' => 6,
	'post_status'    => 'publish',
	'orderby'        => 'menu_order',
	'order'          => 'ASC',
] );

// Fallback data if CPT has no posts yet
$fallback_services = [
	[
		'title' => 'Plomberie & Fuites',
		'price' => 'À partir de 90 €',
		'rating'=> '4.8',
		'count' => '57K+',
		'img'   => sbmt_asset_img( 'climanova-service-01.png' ),
		'url'   => home_url( '/services/plomberie-fuites' ),
	],
	[
		'title' => 'Rénovation énergétique',
		'price' => 'À partir de 800 €',
		'rating'=> '4.8',
		'count' => '14K+',
		'img'   => sbmt_asset_img( 'climanova-service-02.png' ),
		'url'   => home_url( '/services/renovation-energetique' ),
	],
	[
		'title' => 'Plomberie',
		'price' => 'À partir de 120 €',
		'rating'=> '4.8',
		'count' => '26K+',
		'img'   => sbmt_asset_img( 'climanova-service-03.png' ),
		'url'   => home_url( '/services/plomberie' ),
	],
	[
		'title' => 'Électricité',
		'price' => 'À partir de 150 €',
		'rating'=> '4.8',
		'count' => '38K+',
		'img'   => sbmt_asset_img( 'climanova-service-04.png' ),
		'url'   => home_url( '/services/electricite' ),
	],
	[
		'title' => 'Chauffage',
		'price' => 'À partir de 200 €',
		'rating'=> '4.8',
		'count' => '10K+',
		'img'   => sbmt_asset_img( 'climanova-service-05.png' ),
		'url'   => home_url( '/services/chauffage' ),
	],
	[
		'title' => 'Climatisation',
		'price' => 'À partir de 60 €',
		'rating'=> '4.8',
		'count' => '25K+',
		'img'   => sbmt_asset_img( 'climanova-service-06.png' ),
		'url'   => home_url( '/services/climatisation' ),
	],
];
?>

<section class="service-section" aria-label="<?php esc_attr_e( 'Nos services', 'sb-marketing-theme' ); ?>">
	<div class="container">

		<!-- Section header -->
		<div class="section-header-wrap">
			<div class="section-title-wrap">
				<div class="section-subtitle-wrap">
					<div class="section-subtitle-icon w-embed">
						<span class="material-icons-round" aria-hidden="true">home_repair_service</span>
					</div>
					<div class="section-subtitle text-sm"><?php esc_html_e( 'Nos services', 'sb-marketing-theme' ); ?></div>
				</div>
				<h2 class="heading-style-h2"><?php echo wp_kses_post( $title ); ?></h2>
			</div>
			<p class="section-description text-size-medium"><?php echo esc_html( $subtitle ); ?></p>
		</div>

		<!-- Services grid -->
		<div class="services-grid" role="list">

			<?php if ( $services_query->have_posts() ) : ?>

				<?php while ( $services_query->have_posts() ) : $services_query->the_post(); ?>
					<?php
					$price  = get_post_meta( get_the_ID(), '_service_price', true );
					$rating = get_post_meta( get_the_ID(), '_service_rating', true );
					$count  = get_post_meta( get_the_ID(), '_service_review_count', true );
					$img    = get_the_post_thumbnail_url( get_the_ID(), 'sbmt-service-card' );
					?>
					<article class="service-card" role="listitem">
						<a href="<?php the_permalink(); ?>" class="service-card-link" aria-label="<?php the_title_attribute(); ?>">
							<div class="service-image-block">
								<?php if ( $img ) : ?>
									<img src="<?php echo esc_url( $img ); ?>"
									     alt="<?php the_title_attribute(); ?>"
									     class="service-image"
									     loading="lazy"
									     width="400" height="520">
								<?php else : ?>
									<div class="service-image-placeholder" aria-hidden="true"></div>
								<?php endif; ?>
							</div>
							<div class="service-text-block">
								<h3 class="service-card-title"><?php the_title(); ?></h3>
								<?php if ( $price ) : ?>
									<div class="service-price text-sm"><?php echo esc_html( $price ); ?></div>
								<?php endif; ?>
								<?php if ( $rating ) : ?>
									<div class="service-rating text-sm">
										<span class="material-icons-round" aria-hidden="true" style="font-size:14px;color:#FFD700;">star</span>
										<?php echo esc_html( $rating ); ?>
										<?php if ( $count ) : ?>
											<span class="service-review-count">(<?php echo esc_html( $count ); ?>)</span>
										<?php endif; ?>
									</div>
								<?php endif; ?>
							</div>
						</a>
					</article>

				<?php endwhile; ?>
				<?php wp_reset_postdata(); ?>

			<?php else : ?>

				<!-- Fallback cards (no CPT posts yet) -->
				<?php foreach ( $fallback_services as $svc ) : ?>
					<article class="service-card" role="listitem">
						<a href="<?php echo esc_url( $svc['url'] ); ?>" class="service-card-link" aria-label="<?php echo esc_attr( $svc['title'] ); ?>">
							<div class="service-image-block">
								<img src="<?php echo esc_url( $svc['img'] ); ?>"
								     alt="<?php echo esc_attr( $svc['title'] ); ?>"
								     class="service-image"
								     loading="lazy"
								     width="400" height="520">
							</div>
							<div class="service-text-block">
								<h3 class="service-card-title"><?php echo esc_html( $svc['title'] ); ?></h3>
								<div class="service-price text-sm"><?php echo esc_html( $svc['price'] ); ?></div>
								<div class="service-rating text-sm">
									<span class="material-icons-round" aria-hidden="true" style="font-size:14px;color:#FFD700;">star</span>
									<?php echo esc_html( $svc['rating'] ); ?>
									<span class="service-review-count">(<?php echo esc_html( $svc['count'] ); ?>)</span>
								</div>
							</div>
						</a>
					</article>
				<?php endforeach; ?>

			<?php endif; ?>

		</div>
		<!-- /Services grid -->

		<!-- CTA button -->
		<div class="services-cta-wrap">
			<?php sbmt_button( $btn_text, $btn_url ); ?>
		</div>

	</div>
</section>
