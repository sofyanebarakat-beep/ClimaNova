<?php
/**
 * Homepage — Pourquoi nous choisir section
 *
 * @package SBMarketingTheme
 */

$title    = sbmt_mod( 'sbmt_why_title',    'Pourquoi les Niçois font confiance à ClimaNova Energie' );
$subtitle = sbmt_mod( 'sbmt_why_subtitle', 'Chez ClimaNova Energie, nous simplifions la gestion de vos équipements. Climatisation, électricité, plomberie ou chauffage — une seule équipe pour tout gérer, rapidement et sans stress.' );
$btn_text = sbmt_mod( 'sbmt_why_btn_text', 'En savoir plus' );
$btn_url  = sbmt_mod( 'sbmt_why_btn_url',  home_url( '/a-propos' ) );
$img_url  = get_theme_mod( 'sbmt_why_image', '' ) ?: sbmt_asset_img( 'climanova-why-choose.png' );

$features = [
	[
		'icon'  => 'engineering',
		'title' => sbmt_mod( 'sbmt_why_feat_1_title', 'Techniciens qualifiés' ),
		'desc'  => sbmt_mod( 'sbmt_why_feat_1_desc',  'Nos techniciens certifiés interviennent avec précision et professionnalisme.' ),
	],
	[
		'icon'  => 'schedule',
		'title' => sbmt_mod( 'sbmt_why_feat_2_title', 'Intervention rapide et fiable' ),
		'desc'  => sbmt_mod( 'sbmt_why_feat_2_desc',  'Nous respectons les délais d\'intervention et communiquons clairement à chaque étape.' ),
	],
	[
		'icon'  => 'verified',
		'title' => sbmt_mod( 'sbmt_why_feat_3_title', 'Qualité et sécurité garanties' ),
		'desc'  => sbmt_mod( 'sbmt_why_feat_3_desc',  'Chaque intervention respecte les normes de sécurité et de qualité en vigueur.' ),
	],
	[
		'icon'  => 'receipt_long',
		'title' => sbmt_mod( 'sbmt_why_feat_4_title', 'Tarifs transparents, sans surprise' ),
		'desc'  => sbmt_mod( 'sbmt_why_feat_4_desc',  'Nous établissons un devis détaillé avant toute intervention, sans frais cachés.' ),
	],
];
?>

<section class="why-choose-section" aria-label="<?php esc_attr_e( 'Pourquoi nous choisir', 'sb-marketing-theme' ); ?>">
	<div class="container">
		<div class="why-choose-content-wrapper">

			<!-- Left: text + features -->
			<div class="why-choose-text-block">

				<div class="section-title-wrap">
					<div class="section-subtitle-wrap">
						<div class="section-subtitle-icon w-embed">
							<span class="material-icons-round" aria-hidden="true">thumb_up</span>
						</div>
						<div class="section-subtitle text-sm"><?php esc_html_e( 'Pourquoi nous choisir', 'sb-marketing-theme' ); ?></div>
					</div>
					<h2 class="heading-style-h2"><?php echo wp_kses_post( $title ); ?></h2>
				</div>

				<p class="text-size-medium why-choose-description"><?php echo esc_html( $subtitle ); ?></p>

				<div class="why-choose-features-grid">
					<?php foreach ( $features as $feature ) : ?>
						<div class="why-choose-feature-card">
							<div class="feature-icon-wrap">
								<span class="material-icons-round feature-icon" aria-hidden="true"><?php echo esc_html( $feature['icon'] ); ?></span>
							</div>
							<div class="feature-text-block">
								<h3 class="feature-title text-size-medium"><?php echo esc_html( $feature['title'] ); ?></h3>
								<p class="feature-desc text-sm"><?php echo esc_html( $feature['desc'] ); ?></p>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<?php sbmt_button( $btn_text, $btn_url ); ?>

			</div>
			<!-- /Left -->

			<!-- Right: image -->
			<div class="why-choose-image-block">
				<img src="<?php echo esc_url( $img_url ); ?>"
				     alt="<?php esc_attr_e( 'Technicien ClimaNova Energie en intervention', 'sb-marketing-theme' ); ?>"
				     class="why-choose-image"
				     loading="lazy"
				     width="560" height="600">
			</div>

		</div>
	</div>
</section>
