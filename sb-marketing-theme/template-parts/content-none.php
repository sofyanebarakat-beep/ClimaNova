<?php
/**
 * No content found template part.
 *
 * @package SBMarketingTheme
 */
?>

<div class="content-none-wrap" style="text-align:center;padding:80px 20px;">
	<span class="material-icons-round" aria-hidden="true" style="font-size:64px;color:#cbd5e1;display:block;margin-bottom:16px;">search_off</span>
	<h2><?php esc_html_e( 'Aucun contenu trouvé', 'sb-marketing-theme' ); ?></h2>
	<p><?php esc_html_e( 'Il semble que nous ne puissions pas trouver ce que vous cherchez. Une recherche peut vous aider.', 'sb-marketing-theme' ); ?></p>
	<div style="max-width:400px;margin:24px auto 0;">
		<?php get_search_form(); ?>
	</div>
</div>
