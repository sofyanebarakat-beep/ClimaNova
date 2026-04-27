<?php
/**
 * Homepage — Témoignages section
 * Pulls from the 'climanova_testimonial' CPT.
 * Falls back to hardcoded defaults.
 *
 * @package SBMarketingTheme
 */

$title      = sbmt_mod( 'sbmt_testimonials_title',     'Ce que nos clients disent de ClimaNova Energie' );
$stat_val   = sbmt_mod( 'sbmt_testimonials_stat_val',  '4.9' );
$stat_label = sbmt_mod( 'sbmt_testimonials_stat_label','Avis clients vérifiés' );
$img_url    = get_theme_mod( 'sbmt_testimonials_image', '' ) ?: sbmt_asset_img( 'climanova-testimonial.png' );

$testimonials_query = new WP_Query( [
	'post_type'      => 'climanova_testimonial',
	'posts_per_page' => 4,
	'post_status'    => 'publish',
	'orderby'        => 'menu_order',
	'order'          => 'ASC',
] );

$fallback_testimonials = [
	[
		'content' => 'Travailler avec ClimaNova Energie a été une excellente expérience. Une équipe professionnelle et réactive, avec des résultats à la hauteur. Je recommande vivement — leur expertise et leur sérieux font toute la différence.',
		'author'  => 'Jean-Marc Dupont',
		'role'    => 'Propriétaire à Nice',
		'rating'  => 5,
		'avatar'  => sbmt_asset_img( 'climanova-avatar-04.png' ),
	],
	[
		'content' => 'ClimaNova Energie est une équipe remarquable. Professionnels, disponibles et efficaces — les résultats parlent d\'eux-mêmes. Je les recommande sans hésitation pour tous vos travaux.',
		'author'  => 'Isabelle Moreau',
		'role'    => 'Gestionnaire de patrimoine immobilier',
		'rating'  => 5,
		'avatar'  => '',
	],
	[
		'content' => 'Notre expérience avec ClimaNova Energie a été remarquable. Toute l\'équipe est réactive, professionnelle et garantit des résultats de qualité. Nous les recommandons chaleureusement.',
		'author'  => 'Thomas Laurent',
		'role'    => 'Chef de projet à Cannes',
		'rating'  => 5,
		'avatar'  => '',
	],
	[
		'content' => 'ClimaNova Energie a rendu le processus simple et agréable. L\'équipe est réactive, professionnelle et a livré un travail impeccable. Notre recommandation la plus forte.',
		'author'  => 'Marie Blanc',
		'role'    => 'Particulière à Antibes',
		'rating'  => 5,
		'avatar'  => '',
	],
];
?>

<section class="testimonial-section" aria-label="<?php esc_attr_e( 'Témoignages clients', 'sb-marketing-theme' ); ?>">
	<div class="container">
		<div class="testimonials-content-wrapper">

			<!-- Left: testimonials slider / grid -->
			<div class="testimonials-left-block">

				<div class="section-title-wrap">
					<div class="section-subtitle-wrap">
						<div class="section-subtitle-icon w-embed">
							<span class="material-icons-round" aria-hidden="true">format_quote</span>
						</div>
						<div class="section-subtitle text-sm"><?php esc_html_e( 'Témoignages', 'sb-marketing-theme' ); ?></div>
					</div>
					<h2 class="heading-style-h2"><?php echo wp_kses_post( $title ); ?></h2>
				</div>

				<div class="testimonials-grid" role="list">

					<?php if ( $testimonials_query->have_posts() ) : ?>

						<?php while ( $testimonials_query->have_posts() ) : $testimonials_query->the_post(); ?>
							<?php
							$author = get_post_meta( get_the_ID(), '_testimonial_author', true ) ?: get_the_title();
							$role   = get_post_meta( get_the_ID(), '_testimonial_role', true );
							$rating = (int) get_post_meta( get_the_ID(), '_testimonial_rating', true );
							$avatar = get_the_post_thumbnail_url( get_the_ID(), 'sbmt-testimonial-avatar' );
							?>
							<article class="testimonial-card" role="listitem">
								<div class="testimonial-quote-icon w-embed"><?php sbmt_icon_quote(); ?></div>
								<?php sbmt_star_rating( $rating ?: 5 ); ?>
								<blockquote class="testimonial-content">
									<?php echo wp_kses_post( get_the_content() ); ?>
								</blockquote>
								<footer class="testimonial-author-block">
									<?php if ( $avatar ) : ?>
										<img src="<?php echo esc_url( $avatar ); ?>"
										     alt="<?php echo esc_attr( $author ); ?>"
										     class="testimonial-avatar"
										     loading="lazy" width="48" height="48">
									<?php else : ?>
										<div class="testimonial-avatar-placeholder" aria-hidden="true">
											<span class="material-icons-round">person</span>
										</div>
									<?php endif; ?>
									<div class="testimonial-author-info">
										<cite class="testimonial-author-name"><?php echo esc_html( $author ); ?></cite>
										<?php if ( $role ) : ?>
											<p class="testimonial-author-role text-sm"><?php echo esc_html( $role ); ?></p>
										<?php endif; ?>
									</div>
								</footer>
							</article>
						<?php endwhile; ?>
						<?php wp_reset_postdata(); ?>

					<?php else : ?>

						<?php foreach ( $fallback_testimonials as $t ) : ?>
							<article class="testimonial-card" role="listitem">
								<div class="testimonial-quote-icon w-embed"><?php sbmt_icon_quote(); ?></div>
								<?php sbmt_star_rating( $t['rating'] ); ?>
								<blockquote class="testimonial-content">
									<?php echo esc_html( $t['content'] ); ?>
								</blockquote>
								<footer class="testimonial-author-block">
									<?php if ( $t['avatar'] ) : ?>
										<img src="<?php echo esc_url( $t['avatar'] ); ?>"
										     alt="<?php echo esc_attr( $t['author'] ); ?>"
										     class="testimonial-avatar"
										     loading="lazy" width="48" height="48">
									<?php else : ?>
										<div class="testimonial-avatar-placeholder" aria-hidden="true">
											<span class="material-icons-round">person</span>
										</div>
									<?php endif; ?>
									<div class="testimonial-author-info">
										<cite class="testimonial-author-name"><?php echo esc_html( $t['author'] ); ?></cite>
										<p class="testimonial-author-role text-sm"><?php echo esc_html( $t['role'] ); ?></p>
									</div>
								</footer>
							</article>
						<?php endforeach; ?>

					<?php endif; ?>

				</div>
				<!-- /Testimonials grid -->

			</div>
			<!-- /Left -->

			<!-- Right: image + stat -->
			<div class="testimonials-right-block">
				<img src="<?php echo esc_url( $img_url ); ?>"
				     alt="<?php esc_attr_e( 'Client satisfait ClimaNova Energie', 'sb-marketing-theme' ); ?>"
				     class="testimonial-right-image"
				     loading="lazy"
				     width="440" height="560">

				<!-- Floating stat badge -->
				<div class="testimonial-stat-badge" aria-label="<?php echo esc_attr( $stat_val . ' ' . $stat_label ); ?>">
					<div class="testimonial-stat-value heading-style-h2"><?php echo esc_html( $stat_val ); ?></div>
					<div class="testimonial-stat-label text-sm"><?php echo esc_html( $stat_label ); ?></div>
					<div class="testimonial-stat-stars" aria-hidden="true">
						<?php for ( $i = 0; $i < 5; $i++ ) : ?>
							<span class="material-icons-round" style="color:#FFD700;font-size:18px;">star</span>
						<?php endfor; ?>
					</div>
				</div>
			</div>

		</div>
	</div>
</section>
