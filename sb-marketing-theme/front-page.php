<?php
/**
 * Homepage template (used when a static front page is set in Settings → Reading).
 *
 * @package SBMarketingTheme
 */

get_header();
?>

<?php get_template_part( 'template-parts/home/hero' ); ?>

<?php get_template_part( 'template-parts/home/marquee' ); ?>

<?php get_template_part( 'template-parts/home/about' ); ?>

<?php get_template_part( 'template-parts/home/why-choose' ); ?>

<?php get_template_part( 'template-parts/home/services' ); ?>

<?php get_template_part( 'template-parts/home/process' ); ?>

<?php get_template_part( 'template-parts/home/projects' ); ?>

<?php get_template_part( 'template-parts/home/testimonials' ); ?>

<?php get_template_part( 'template-parts/home/cta' ); ?>

<?php get_template_part( 'template-parts/home/blog-preview' ); ?>

<?php get_footer(); ?>
