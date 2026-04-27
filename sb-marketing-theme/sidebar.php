<?php
/**
 * Sidebar template.
 *
 * @package SBMarketingTheme
 */

if ( ! is_active_sidebar( 'sidebar-1' ) ) {
	return;
}
?>

<aside class="sidebar" role="complementary" aria-label="<?php esc_attr_e( 'Barre latérale', 'sb-marketing-theme' ); ?>">
	<?php dynamic_sidebar( 'sidebar-1' ); ?>
</aside>
