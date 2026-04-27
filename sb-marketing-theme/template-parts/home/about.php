<?php
/**
 * Homepage — À propos section
 *
 * @package SBMarketingTheme
 */

$title     = sbmt_mod( 'sbmt_about_title',    'Des interventions fiables, un service honnête, à chaque fois' );
$subtitle  = sbmt_mod( 'sbmt_about_subtitle', 'Nous intervenons sur Nice et toute la région PACA pour vos besoins en climatisation, électricité, plomberie et chauffage — rapidement, efficacement et au juste prix.' );
$bullet_1  = sbmt_mod( 'sbmt_about_bullet_1', 'Des matériaux de qualité pour des résultats durables.' );
$bullet_2  = sbmt_mod( 'sbmt_about_bullet_2', 'Des techniciens certifiés RGE, rigoureux et soigneux.' );
$bullet_3  = sbmt_mod( 'sbmt_about_bullet_3', 'Un service adapté aux besoins spécifiques de votre logement.' );
$stat_1_v  = sbmt_mod( 'sbmt_about_stat_1_value', '98,5 %' );
$stat_1_l  = sbmt_mod( 'sbmt_about_stat_1_label', 'Taux de satisfaction client' );
$stat_2_v  = sbmt_mod( 'sbmt_about_stat_2_value', '4.9/5' );
$stat_2_l  = sbmt_mod( 'sbmt_about_stat_2_label', 'Note moyenne clients' );
$btn_text  = sbmt_mod( 'sbmt_about_btn_text',  'En savoir plus' );
$btn_url   = sbmt_mod( 'sbmt_about_btn_url',   home_url( '/a-propos' ) );
$img_url   = get_theme_mod( 'sbmt_about_image', '' ) ?: sbmt_asset_img( 'climanova-kitchen-image.png' );
?>

<section class="about-section" aria-label="<?php esc_attr_e( 'À propos de ClimaNova Energie', 'sb-marketing-theme' ); ?>">
	<div class="container">
		<div class="about-content-wrapper">

			<!-- Left: image -->
			<div class="about-image-block">
				<img src="<?php echo esc_url( $img_url ); ?>"
				     alt="<?php esc_attr_e( 'Intervention ClimaNova Energie', 'sb-marketing-theme' ); ?>"
				     class="about-content-image-01"
				     loading="lazy"
				     width="560" height="480">
			</div>

			<!-- Right: text content -->
			<div class="about-text-block">

				<div class="section-title-wrap">
					<div class="section-subtitle-wrap">
						<div class="section-subtitle-icon w-embed">
							<span class="material-icons-round" aria-hidden="true">verified_user</span>
						</div>
						<div class="section-subtitle text-sm"><?php esc_html_e( 'À propos de nous', 'sb-marketing-theme' ); ?></div>
					</div>
					<h2 class="heading-style-h2"><?php echo wp_kses_post( $title ); ?></h2>
				</div>

				<p class="text-size-medium about-description"><?php echo esc_html( $subtitle ); ?></p>

				<ul class="about-bullets-list">
					<?php foreach ( [ $bullet_1, $bullet_2, $bullet_3 ] as $bullet ) : ?>
						<?php if ( $bullet ) : ?>
							<li class="about-bullet-item">
								<span class="material-icons-round about-bullet-icon" aria-hidden="true">check_circle</span>
								<span><?php echo esc_html( $bullet ); ?></span>
							</li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>

				<!-- Stats row -->
				<div class="about-stats-wrap">
					<div class="about-stat-item">
						<div class="about-stat-value heading-style-h3"><?php echo esc_html( $stat_1_v ); ?></div>
						<div class="about-stat-label text-sm"><?php echo esc_html( $stat_1_l ); ?></div>
					</div>
					<div class="about-stat-divider"></div>
					<div class="about-stat-item">
						<div class="about-stat-value heading-style-h3"><?php echo esc_html( $stat_2_v ); ?></div>
						<div class="about-stat-label text-sm"><?php echo esc_html( $stat_2_l ); ?></div>
					</div>
				</div>

				<?php sbmt_button( $btn_text, $btn_url ); ?>

			</div>
			<!-- /Right -->

		</div>
	</div>
</section>
