<?php
/**
 * Homepage — Processus d'intervention section
 *
 * @package SBMarketingTheme
 */

$title    = sbmt_mod( 'sbmt_process_title',    'Notre processus d\'intervention' );
$subtitle = sbmt_mod( 'sbmt_process_subtitle', 'Notre méthode garantit une intervention rapide, bien réalisée et dans les délais — avec une communication claire à chaque étape.' );

$steps = [
	[
		'num'   => sbmt_mod( 'sbmt_process_step_1_num',   '01' ),
		'title' => sbmt_mod( 'sbmt_process_step_1_title', 'Planifiez votre intervention' ),
		'desc'  => sbmt_mod( 'sbmt_process_step_1_desc',  'Choisissez votre service et planifiez facilement en ligne ou par téléphone.' ),
		'img'   => get_theme_mod( 'sbmt_process_step_1_image', '' ) ?: sbmt_asset_img( 'process-step-01.svg' ),
	],
	[
		'num'   => sbmt_mod( 'sbmt_process_step_2_num',   '02' ),
		'title' => sbmt_mod( 'sbmt_process_step_2_title', 'Recevez votre devis' ),
		'desc'  => sbmt_mod( 'sbmt_process_step_2_desc',  'Nous évaluons les travaux et vous communiquons un devis clair et transparent.' ),
		'img'   => get_theme_mod( 'sbmt_process_step_2_image', '' ) ?: sbmt_asset_img( 'process-step-02.svg' ),
	],
	[
		'num'   => sbmt_mod( 'sbmt_process_step_3_num',   '03' ),
		'title' => sbmt_mod( 'sbmt_process_step_3_title', 'On intervient & on finalise' ),
		'desc'  => sbmt_mod( 'sbmt_process_step_3_desc',  'Nos techniciens arrivent à l\'heure, réalisent l\'intervention et repassent si nécessaire.' ),
		'img'   => get_theme_mod( 'sbmt_process_step_3_image', '' ) ?: sbmt_asset_img( 'process-step-03.svg' ),
	],
];
?>

<section class="process-section" aria-label="<?php esc_attr_e( 'Notre processus', 'sb-marketing-theme' ); ?>">
	<div class="container">

		<!-- Section header -->
		<div class="section-header-wrap section-header-centered">
			<div class="section-title-wrap">
				<div class="section-subtitle-wrap">
					<div class="section-subtitle-icon w-embed">
						<span class="material-icons-round" aria-hidden="true">route</span>
					</div>
					<div class="section-subtitle text-sm"><?php esc_html_e( 'Comment ça marche', 'sb-marketing-theme' ); ?></div>
				</div>
				<h2 class="heading-style-h2"><?php echo wp_kses_post( $title ); ?></h2>
			</div>
			<p class="section-description text-size-medium"><?php echo esc_html( $subtitle ); ?></p>
		</div>

		<!-- Steps -->
		<ol class="process-steps-wrapper" style="list-style:none;padding:0;margin:0;">
			<?php foreach ( $steps as $index => $step ) : ?>
				<li class="process-step-item">

					<!-- Image block -->
					<div class="process-step-image-block">
						<?php if ( $step['img'] ) : ?>
							<img src="<?php echo esc_url( $step['img'] ); ?>"
							     alt="<?php echo esc_attr( $step['title'] ); ?>"
							     class="process-step-image"
							     loading="lazy"
							     width="180" height="180">
						<?php else : ?>
							<div class="process-step-num-placeholder" aria-hidden="true">
								<span class="process-step-num heading-style-h2"><?php echo esc_html( $step['num'] ); ?></span>
							</div>
						<?php endif; ?>
					</div>

					<!-- Connector arrow (between steps) -->
					<?php if ( $index < count( $steps ) - 1 ) : ?>
						<div class="process-step-arrow" aria-hidden="true">
							<span class="material-icons-round">arrow_forward</span>
						</div>
					<?php endif; ?>

					<!-- Text -->
					<div class="process-step-text-block">
						<div class="process-step-number text-sm"><?php echo esc_html( $step['num'] ); ?></div>
						<h3 class="process-step-title"><?php echo esc_html( $step['title'] ); ?></h3>
						<p class="process-step-desc text-sm"><?php echo esc_html( $step['desc'] ); ?></p>
					</div>

				</li>
			<?php endforeach; ?>
		</ol>

	</div>
</section>
