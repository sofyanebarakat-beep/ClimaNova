<?php
/**
 * 404 — Page not found template.
 *
 * @package SBMarketingTheme
 */

get_header();
?>

<div class="error-404-wrap">

	<div aria-hidden="true" class="error-404-number">404</div>

	<h1><?php esc_html_e( 'Page introuvable', 'sb-marketing-theme' ); ?></h1>
	<p>
		<?php esc_html_e( 'Oups ! La page que vous cherchez n\'existe pas ou a été déplacée.', 'sb-marketing-theme' ); ?>
	</p>

	<!-- Search form -->
	<div class="error-404-search" style="max-width:420px;width:100%;margin-bottom:32px;">
		<?php get_search_form(); ?>
	</div>

	<!-- Back home button -->
	<?php sbmt_button( __( 'Retour à l\'accueil', 'sb-marketing-theme' ), home_url( '/' ) ); ?>

</div>

<?php get_footer(); ?>
