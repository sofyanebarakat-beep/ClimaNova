<?php
/**
 * Homepage — Réalisations section
 * Pulls latest 6 projects from the 'climanova_project' CPT.
 * Falls back to theme asset images if no posts exist.
 *
 * @package SBMarketingTheme
 */

$title    = sbmt_mod( 'sbmt_projects_title',    'Nos récentes réalisations' );
$subtitle = sbmt_mod( 'sbmt_projects_subtitle', 'Découvrez quelques-unes de nos interventions réalisées à Nice et en région PACA — avec soin, expertise et engagement pour un confort durable.' );
$btn_text = sbmt_mod( 'sbmt_projects_btn_text', 'Voir toutes les réalisations' );
$btn_url  = sbmt_mod( 'sbmt_projects_btn_url',  home_url( '/realisations' ) );

$projects_query = new WP_Query( [
	'post_type'      => 'climanova_project',
	'posts_per_page' => 6,
	'post_status'    => 'publish',
	'orderby'        => 'date',
	'order'          => 'DESC',
] );

$fallback_imgs = [
	sbmt_asset_img( 'climanova-project-00.png' ),
	sbmt_asset_img( 'climanova-project-01.png' ),
	sbmt_asset_img( 'climanova-project-02.png' ),
	sbmt_asset_img( 'climanova-project-03.png' ),
	sbmt_asset_img( 'climanova-project-04.png' ),
	sbmt_asset_img( 'climanova-project-05.png' ),
];
?>

<section class="project-section" aria-label="<?php esc_attr_e( 'Nos réalisations', 'sb-marketing-theme' ); ?>">
	<div class="container">

		<!-- Section header -->
		<div class="section-header-wrap">
			<div class="section-title-wrap">
				<div class="section-subtitle-wrap">
					<div class="section-subtitle-icon w-embed">
						<span class="material-icons-round" aria-hidden="true">photo_library</span>
					</div>
					<div class="section-subtitle text-sm"><?php esc_html_e( 'Nos réalisations', 'sb-marketing-theme' ); ?></div>
				</div>
				<h2 class="heading-style-h2"><?php echo wp_kses_post( $title ); ?></h2>
			</div>
			<div class="section-header-right">
				<p class="section-description text-size-medium"><?php echo esc_html( $subtitle ); ?></p>
				<?php sbmt_button( $btn_text, $btn_url ); ?>
			</div>
		</div>

		<!-- Projects grid -->
		<div class="projects-grid" role="list">

			<?php if ( $projects_query->have_posts() ) : ?>

				<?php $project_index = 0; ?>
				<?php while ( $projects_query->have_posts() ) : $projects_query->the_post(); ?>
					<?php
					$thumb    = get_the_post_thumbnail_url( get_the_ID(), 'sbmt-project-thumb' );
					$location = get_post_meta( get_the_ID(), '_project_location', true );
					$service  = get_post_meta( get_the_ID(), '_project_service', true );
					// First card is wider (spans 2 columns on desktop)
					$card_class = ( 0 === $project_index ) ? 'project-card project-card--wide' : 'project-card';
					?>
					<article class="<?php echo esc_attr( $card_class ); ?>" role="listitem">
						<a href="<?php the_permalink(); ?>" class="project-card-link" aria-label="<?php the_title_attribute(); ?>">
							<?php if ( $thumb ) : ?>
								<img src="<?php echo esc_url( $thumb ); ?>"
								     alt="<?php the_title_attribute(); ?>"
								     class="project-card-image"
								     loading="lazy"
								     width="600" height="440">
							<?php endif; ?>
							<div class="project-card-overlay">
								<h3 class="project-card-title"><?php the_title(); ?></h3>
								<?php if ( $location || $service ) : ?>
									<div class="project-card-meta text-sm">
										<?php if ( $service ) : ?>
											<span><?php echo esc_html( $service ); ?></span>
										<?php endif; ?>
										<?php if ( $location ) : ?>
											<span>
												<span class="material-icons-round" aria-hidden="true" style="font-size:12px;">place</span>
												<?php echo esc_html( $location ); ?>
											</span>
										<?php endif; ?>
									</div>
								<?php endif; ?>
							</div>
						</a>
					</article>
					<?php $project_index++; ?>

				<?php endwhile; ?>
				<?php wp_reset_postdata(); ?>

			<?php else : ?>

				<!-- Fallback: theme asset images -->
				<?php foreach ( $fallback_imgs as $i => $img ) : ?>
					<article class="project-card<?php echo 0 === $i ? ' project-card--wide' : ''; ?>" role="listitem">
						<div class="project-card-link">
							<img src="<?php echo esc_url( $img ); ?>"
							     alt="<?php printf( esc_attr__( 'Réalisation ClimaNova %d', 'sb-marketing-theme' ), $i + 1 ); ?>"
							     class="project-card-image"
							     loading="lazy"
							     width="600" height="440">
						</div>
					</article>
				<?php endforeach; ?>

			<?php endif; ?>

		</div>
		<!-- /Projects grid -->

	</div>
</section>
