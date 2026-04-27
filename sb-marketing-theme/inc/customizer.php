<?php
/**
 * SB Marketing Theme — Customizer
 * Registers all Customizer panels, sections, settings, and controls.
 *
 * @package SBMarketingTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function sbmt_customize_register( WP_Customize_Manager $wp_customize ) {

	// ── Panel ────────────────────────────────────────────────────────────────
	$wp_customize->add_panel( 'sbmt_panel', [
		'title'       => __( 'ClimaNova — Paramètres', 'sb-marketing-theme' ),
		'description' => __( 'Personnalisez le contenu de votre site ClimaNova.', 'sb-marketing-theme' ),
		'priority'    => 30,
	] );

	// ════════════════════════════════════════════════════════════════════════
	// SECTION: Coordonnées & Contact
	// ════════════════════════════════════════════════════════════════════════
	$wp_customize->add_section( 'sbmt_contact', [
		'title'    => __( 'Coordonnées & Contact', 'sb-marketing-theme' ),
		'panel'    => 'sbmt_panel',
		'priority' => 10,
	] );

	$contact_fields = [
		'sbmt_phone'         => [ __( 'Téléphone', 'sb-marketing-theme' ),         '+33 4 93 00 00 00' ],
		'sbmt_email'         => [ __( 'E-mail', 'sb-marketing-theme' ),             'contact@climanova-energie.fr' ],
		'sbmt_address'       => [ __( 'Adresse', 'sb-marketing-theme' ),            '12 Rue de la Paix, 06000 Nice, France' ],
		'sbmt_certification' => [ __( 'Texte certification (top bar)', 'sb-marketing-theme' ), 'Certifié RGE – Expert énergie & confort depuis 2010' ],
		'sbmt_maps_url'      => [ __( 'Lien Google Maps', 'sb-marketing-theme' ),   'https://maps.google.com' ],
	];
	foreach ( $contact_fields as $id => [ $label, $default ] ) {
		$wp_customize->add_setting( $id, [ 'default' => $default, 'sanitize_callback' => 'sanitize_text_field', 'transport' => 'refresh' ] );
		$wp_customize->add_control( $id, [ 'label' => $label, 'section' => 'sbmt_contact', 'type' => 'text' ] );
	}

	// ════════════════════════════════════════════════════════════════════════
	// SECTION: Réseaux sociaux
	// ════════════════════════════════════════════════════════════════════════
	$wp_customize->add_section( 'sbmt_social', [
		'title'    => __( 'Réseaux sociaux', 'sb-marketing-theme' ),
		'panel'    => 'sbmt_panel',
		'priority' => 20,
	] );
	foreach ( [
		'sbmt_facebook_url'  => __( 'Facebook URL', 'sb-marketing-theme' ),
		'sbmt_instagram_url' => __( 'Instagram URL', 'sb-marketing-theme' ),
		'sbmt_linkedin_url'  => __( 'LinkedIn URL', 'sb-marketing-theme' ),
	] as $id => $label ) {
		$wp_customize->add_setting( $id, [ 'default' => '', 'sanitize_callback' => 'esc_url_raw', 'transport' => 'refresh' ] );
		$wp_customize->add_control( $id, [ 'label' => $label, 'section' => 'sbmt_social', 'type' => 'url' ] );
	}

	// ════════════════════════════════════════════════════════════════════════
	// SECTION: Section Hero
	// ════════════════════════════════════════════════════════════════════════
	$wp_customize->add_section( 'sbmt_hero', [
		'title'    => __( 'Section Hero', 'sb-marketing-theme' ),
		'panel'    => 'sbmt_panel',
		'priority' => 30,
	] );

	// Text settings
	foreach ( [
		'sbmt_hero_badge_text'   => [ __( 'Texte badge (petite pastille)', 'sb-marketing-theme' ), '5 000+ clients satisfaits',   'sanitize_text_field' ],
		'sbmt_hero_title'        => [ __( 'Titre principal', 'sb-marketing-theme' ),               'Votre partenaire Énergie & Confort à Nice et en région PACA', 'wp_kses_post' ],
		'sbmt_hero_subtitle'     => [ __( 'Sous-titre / description', 'sb-marketing-theme' ),      'Climatisation, électricité, plomberie et chauffage — nous assurons le bon fonctionnement de vos installations avec sérieux, réactivité et savoir-faire.', 'sanitize_textarea_field' ],
		'sbmt_hero_btn_text'     => [ __( 'Texte du bouton', 'sb-marketing-theme' ),               'Demander un devis',           'sanitize_text_field' ],
		'sbmt_hero_btn_url'      => [ __( 'Lien du bouton', 'sb-marketing-theme' ),                '/contact',                    'esc_url_raw' ],
	] as $id => [ $label, $default, $sanitize ] ) {
		$wp_customize->add_setting( $id, [ 'default' => $default, 'sanitize_callback' => $sanitize, 'transport' => 'postMessage' ] );
		$wp_customize->add_control( $id, [ 'label' => $label, 'section' => 'sbmt_hero', 'type' => str_contains( $default, "\n" ) || strlen( $default ) > 80 ? 'textarea' : 'text' ] );
	}

	// Image settings
	foreach ( [
		'sbmt_hero_image'        => __( 'Image héro (droite)', 'sb-marketing-theme' ),
		'sbmt_hero_client_img_1' => __( 'Badge client — image 1', 'sb-marketing-theme' ),
		'sbmt_hero_client_img_2' => __( 'Badge client — image 2', 'sb-marketing-theme' ),
	] as $id => $label ) {
		$wp_customize->add_setting( $id, [ 'default' => '', 'sanitize_callback' => 'esc_url_raw', 'transport' => 'refresh' ] );
		$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, $id, [ 'label' => $label, 'section' => 'sbmt_hero' ] ) );
	}

	// ════════════════════════════════════════════════════════════════════════
	// SECTION: Section À propos
	// ════════════════════════════════════════════════════════════════════════
	$wp_customize->add_section( 'sbmt_about', [
		'title'    => __( 'Section À propos', 'sb-marketing-theme' ),
		'panel'    => 'sbmt_panel',
		'priority' => 40,
	] );
	foreach ( [
		'sbmt_about_title'    => [ __( 'Titre', 'sb-marketing-theme' ),        'Des interventions fiables, un service honnête, à chaque fois',   'wp_kses_post' ],
		'sbmt_about_subtitle' => [ __( 'Sous-titre', 'sb-marketing-theme' ),   'Nous intervenons sur Nice et toute la région PACA pour vos besoins en climatisation, électricité, plomberie et chauffage — rapidement, efficacement et au juste prix.', 'sanitize_textarea_field' ],
		'sbmt_about_bullet_1' => [ __( 'Point fort 1', 'sb-marketing-theme' ), 'Des matériaux de qualité pour des résultats durables.',           'sanitize_text_field' ],
		'sbmt_about_bullet_2' => [ __( 'Point fort 2', 'sb-marketing-theme' ), 'Des techniciens certifiés RGE, rigoureux et soigneux.',           'sanitize_text_field' ],
		'sbmt_about_bullet_3' => [ __( 'Point fort 3', 'sb-marketing-theme' ), 'Un service adapté aux besoins spécifiques de votre logement.',   'sanitize_text_field' ],
		'sbmt_about_stat_1_value' => [ __( 'Stat 1 — valeur', 'sb-marketing-theme' ), '98,5 %', 'sanitize_text_field' ],
		'sbmt_about_stat_1_label' => [ __( 'Stat 1 — libellé', 'sb-marketing-theme' ), 'Taux de satisfaction client', 'sanitize_text_field' ],
		'sbmt_about_stat_2_value' => [ __( 'Stat 2 — valeur', 'sb-marketing-theme' ), '4.9/5', 'sanitize_text_field' ],
		'sbmt_about_stat_2_label' => [ __( 'Stat 2 — libellé', 'sb-marketing-theme' ), 'Note moyenne clients', 'sanitize_text_field' ],
		'sbmt_about_btn_text'     => [ __( 'Texte du bouton', 'sb-marketing-theme' ), 'En savoir plus', 'sanitize_text_field' ],
		'sbmt_about_btn_url'      => [ __( 'Lien du bouton', 'sb-marketing-theme' ), '/a-propos', 'esc_url_raw' ],
	] as $id => [ $label, $default, $sanitize ] ) {
		$wp_customize->add_setting( $id, [ 'default' => $default, 'sanitize_callback' => $sanitize, 'transport' => 'postMessage' ] );
		$wp_customize->add_control( $id, [ 'label' => $label, 'section' => 'sbmt_about', 'type' => strlen( $default ) > 80 ? 'textarea' : 'text' ] );
	}
	$wp_customize->add_setting( 'sbmt_about_image', [ 'default' => '', 'sanitize_callback' => 'esc_url_raw', 'transport' => 'refresh' ] );
	$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'sbmt_about_image', [ 'label' => __( 'Image principale', 'sb-marketing-theme' ), 'section' => 'sbmt_about' ] ) );

	// ════════════════════════════════════════════════════════════════════════
	// SECTION: Section Pourquoi nous choisir
	// ════════════════════════════════════════════════════════════════════════
	$wp_customize->add_section( 'sbmt_why_choose', [
		'title'    => __( 'Section « Pourquoi nous choisir »', 'sb-marketing-theme' ),
		'panel'    => 'sbmt_panel',
		'priority' => 45,
	] );
	foreach ( [
		'sbmt_why_title'    => [ __( 'Titre', 'sb-marketing-theme' ),       'Pourquoi les Niçois font confiance à ClimaNova Energie', 'wp_kses_post' ],
		'sbmt_why_subtitle' => [ __( 'Description', 'sb-marketing-theme' ), 'Chez ClimaNova Energie, nous simplifions la gestion de vos équipements. Climatisation, électricité, plomberie ou chauffage — une seule équipe pour tout gérer, rapidement et sans stress.', 'sanitize_textarea_field' ],
		'sbmt_why_btn_text' => [ __( 'Texte du bouton', 'sb-marketing-theme' ), 'En savoir plus', 'sanitize_text_field' ],
		'sbmt_why_btn_url'  => [ __( 'Lien du bouton', 'sb-marketing-theme' ), '/a-propos', 'esc_url_raw' ],
		// Feature 1
		'sbmt_why_feat_1_title' => [ __( 'Avantage 1 — titre', 'sb-marketing-theme' ), 'Techniciens qualifiés', 'sanitize_text_field' ],
		'sbmt_why_feat_1_desc'  => [ __( 'Avantage 1 — description', 'sb-marketing-theme' ), 'Nos techniciens certifiés interviennent avec précision et professionnalisme.', 'sanitize_textarea_field' ],
		// Feature 2
		'sbmt_why_feat_2_title' => [ __( 'Avantage 2 — titre', 'sb-marketing-theme' ), 'Intervention rapide et fiable', 'sanitize_text_field' ],
		'sbmt_why_feat_2_desc'  => [ __( 'Avantage 2 — description', 'sb-marketing-theme' ), 'Nous respectons les délais d\'intervention et communiquons clairement à chaque étape.', 'sanitize_textarea_field' ],
		// Feature 3
		'sbmt_why_feat_3_title' => [ __( 'Avantage 3 — titre', 'sb-marketing-theme' ), 'Qualité et sécurité garanties', 'sanitize_text_field' ],
		'sbmt_why_feat_3_desc'  => [ __( 'Avantage 3 — description', 'sb-marketing-theme' ), 'Chaque intervention respecte les normes de sécurité et de qualité en vigueur.', 'sanitize_textarea_field' ],
		// Feature 4
		'sbmt_why_feat_4_title' => [ __( 'Avantage 4 — titre', 'sb-marketing-theme' ), 'Tarifs transparents, sans surprise', 'sanitize_text_field' ],
		'sbmt_why_feat_4_desc'  => [ __( 'Avantage 4 — description', 'sb-marketing-theme' ), 'Nous établissons un devis détaillé avant toute intervention, sans frais cachés.', 'sanitize_textarea_field' ],
	] as $id => [ $label, $default, $sanitize ] ) {
		$wp_customize->add_setting( $id, [ 'default' => $default, 'sanitize_callback' => $sanitize, 'transport' => 'postMessage' ] );
		$wp_customize->add_control( $id, [ 'label' => $label, 'section' => 'sbmt_why_choose', 'type' => strlen( $default ) > 80 ? 'textarea' : 'text' ] );
	}
	$wp_customize->add_setting( 'sbmt_why_image', [ 'default' => '', 'sanitize_callback' => 'esc_url_raw', 'transport' => 'refresh' ] );
	$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'sbmt_why_image', [ 'label' => __( 'Image (colonne droite)', 'sb-marketing-theme' ), 'section' => 'sbmt_why_choose' ] ) );

	// ════════════════════════════════════════════════════════════════════════
	// SECTION: Section Services
	// ════════════════════════════════════════════════════════════════════════
	$wp_customize->add_section( 'sbmt_services', [
		'title'    => __( 'Section Services', 'sb-marketing-theme' ),
		'panel'    => 'sbmt_panel',
		'priority' => 50,
	] );
	foreach ( [
		'sbmt_services_title'    => [ __( 'Titre', 'sb-marketing-theme' ),    'Trouvez le service adapté à vos besoins',                              'wp_kses_post' ],
		'sbmt_services_subtitle' => [ __( 'Sous-titre', 'sb-marketing-theme' ), 'Installation, dépannage ou mise aux normes — ClimaNova Energie vous met en relation avec les bons experts pour un résultat fiable et durable.', 'sanitize_textarea_field' ],
		'sbmt_services_btn_text' => [ __( 'Texte du bouton', 'sb-marketing-theme' ), 'Tous nos services', 'sanitize_text_field' ],
		'sbmt_services_btn_url'  => [ __( 'Lien du bouton', 'sb-marketing-theme' ), '/services', 'esc_url_raw' ],
	] as $id => [ $label, $default, $sanitize ] ) {
		$wp_customize->add_setting( $id, [ 'default' => $default, 'sanitize_callback' => $sanitize, 'transport' => 'postMessage' ] );
		$wp_customize->add_control( $id, [ 'label' => $label, 'section' => 'sbmt_services', 'type' => strlen( $default ) > 80 ? 'textarea' : 'text' ] );
	}

	// ════════════════════════════════════════════════════════════════════════
	// SECTION: Section Processus
	// ════════════════════════════════════════════════════════════════════════
	$wp_customize->add_section( 'sbmt_process', [
		'title'    => __( 'Section Processus', 'sb-marketing-theme' ),
		'panel'    => 'sbmt_panel',
		'priority' => 60,
	] );
	foreach ( [
		'sbmt_process_title'    => [ __( 'Titre', 'sb-marketing-theme' ),    'Notre processus d\'intervention',                                      'wp_kses_post' ],
		'sbmt_process_subtitle' => [ __( 'Sous-titre', 'sb-marketing-theme' ), 'Notre méthode garantit une intervention rapide, bien réalisée et dans les délais — avec une communication claire à chaque étape.', 'sanitize_textarea_field' ],
		'sbmt_process_step_1_num'  => [ __( 'Étape 1 — numéro', 'sb-marketing-theme' ), '01', 'sanitize_text_field' ],
		'sbmt_process_step_1_title'=> [ __( 'Étape 1 — titre', 'sb-marketing-theme' ),  'Planifiez votre intervention', 'sanitize_text_field' ],
		'sbmt_process_step_1_desc' => [ __( 'Étape 1 — description', 'sb-marketing-theme' ), 'Choisissez votre service et planifiez facilement en ligne ou par téléphone.', 'sanitize_textarea_field' ],
		'sbmt_process_step_2_num'  => [ __( 'Étape 2 — numéro', 'sb-marketing-theme' ), '02', 'sanitize_text_field' ],
		'sbmt_process_step_2_title'=> [ __( 'Étape 2 — titre', 'sb-marketing-theme' ),  'Recevez votre devis', 'sanitize_text_field' ],
		'sbmt_process_step_2_desc' => [ __( 'Étape 2 — description', 'sb-marketing-theme' ), 'Nous évaluons les travaux et vous communiquons un devis clair et transparent.', 'sanitize_textarea_field' ],
		'sbmt_process_step_3_num'  => [ __( 'Étape 3 — numéro', 'sb-marketing-theme' ), '03', 'sanitize_text_field' ],
		'sbmt_process_step_3_title'=> [ __( 'Étape 3 — titre', 'sb-marketing-theme' ),  'On intervient & on finalise', 'sanitize_text_field' ],
		'sbmt_process_step_3_desc' => [ __( 'Étape 3 — description', 'sb-marketing-theme' ), 'Nos techniciens arrivent à l\'heure, réalisent l\'intervention et repassent si nécessaire.', 'sanitize_textarea_field' ],
	] as $id => [ $label, $default, $sanitize ] ) {
		$wp_customize->add_setting( $id, [ 'default' => $default, 'sanitize_callback' => $sanitize, 'transport' => 'postMessage' ] );
		$wp_customize->add_control( $id, [ 'label' => $label, 'section' => 'sbmt_process', 'type' => strlen( $default ) > 80 ? 'textarea' : 'text' ] );
	}
	foreach ( [
		'sbmt_process_step_1_image' => __( 'Étape 1 — image', 'sb-marketing-theme' ),
		'sbmt_process_step_2_image' => __( 'Étape 2 — image', 'sb-marketing-theme' ),
		'sbmt_process_step_3_image' => __( 'Étape 3 — image', 'sb-marketing-theme' ),
	] as $id => $label ) {
		$wp_customize->add_setting( $id, [ 'default' => '', 'sanitize_callback' => 'esc_url_raw', 'transport' => 'refresh' ] );
		$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, $id, [ 'label' => $label, 'section' => 'sbmt_process' ] ) );
	}

	// ════════════════════════════════════════════════════════════════════════
	// SECTION: Section Réalisations
	// ════════════════════════════════════════════════════════════════════════
	$wp_customize->add_section( 'sbmt_projects', [
		'title'    => __( 'Section Réalisations', 'sb-marketing-theme' ),
		'panel'    => 'sbmt_panel',
		'priority' => 70,
	] );
	foreach ( [
		'sbmt_projects_title'    => [ __( 'Titre', 'sb-marketing-theme' ),    'Nos récentes réalisations',             'wp_kses_post' ],
		'sbmt_projects_subtitle' => [ __( 'Sous-titre', 'sb-marketing-theme' ), 'Découvrez quelques-unes de nos interventions réalisées à Nice et en région PACA — avec soin, expertise et engagement pour un confort durable.', 'sanitize_textarea_field' ],
		'sbmt_projects_btn_text' => [ __( 'Texte du bouton', 'sb-marketing-theme' ), 'Voir toutes les réalisations', 'sanitize_text_field' ],
		'sbmt_projects_btn_url'  => [ __( 'Lien du bouton', 'sb-marketing-theme' ), '/realisations', 'esc_url_raw' ],
	] as $id => [ $label, $default, $sanitize ] ) {
		$wp_customize->add_setting( $id, [ 'default' => $default, 'sanitize_callback' => $sanitize, 'transport' => 'postMessage' ] );
		$wp_customize->add_control( $id, [ 'label' => $label, 'section' => 'sbmt_projects', 'type' => strlen( $default ) > 80 ? 'textarea' : 'text' ] );
	}

	// ════════════════════════════════════════════════════════════════════════
	// SECTION: Section Témoignages
	// ════════════════════════════════════════════════════════════════════════
	$wp_customize->add_section( 'sbmt_testimonials', [
		'title'    => __( 'Section Témoignages', 'sb-marketing-theme' ),
		'panel'    => 'sbmt_panel',
		'priority' => 75,
	] );
	foreach ( [
		'sbmt_testimonials_title'    => [ __( 'Titre', 'sb-marketing-theme' ),    'Ce que nos clients disent de ClimaNova Energie', 'wp_kses_post' ],
		'sbmt_testimonials_stat_val' => [ __( 'Valeur statistique (ex: 4.9)', 'sb-marketing-theme' ), '4.9', 'sanitize_text_field' ],
		'sbmt_testimonials_stat_label'=> [ __( 'Libellé statistique', 'sb-marketing-theme' ), 'Avis clients vérifiés', 'sanitize_text_field' ],
	] as $id => [ $label, $default, $sanitize ] ) {
		$wp_customize->add_setting( $id, [ 'default' => $default, 'sanitize_callback' => $sanitize, 'transport' => 'postMessage' ] );
		$wp_customize->add_control( $id, [ 'label' => $label, 'section' => 'sbmt_testimonials', 'type' => 'text' ] );
	}
	$wp_customize->add_setting( 'sbmt_testimonials_image', [ 'default' => '', 'sanitize_callback' => 'esc_url_raw', 'transport' => 'refresh' ] );
	$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'sbmt_testimonials_image', [ 'label' => __( 'Image décorative (droite)', 'sb-marketing-theme' ), 'section' => 'sbmt_testimonials' ] ) );

	// ════════════════════════════════════════════════════════════════════════
	// SECTION: Section CTA
	// ════════════════════════════════════════════════════════════════════════
	$wp_customize->add_section( 'sbmt_cta', [
		'title'    => __( 'Section CTA (appel à l\'action)', 'sb-marketing-theme' ),
		'panel'    => 'sbmt_panel',
		'priority' => 80,
	] );
	foreach ( [
		'sbmt_cta_title'    => [ __( 'Titre', 'sb-marketing-theme' ),    'Votre logement mérite le meilleur — faites confiance à ClimaNova Energie', 'wp_kses_post' ],
		'sbmt_cta_subtitle' => [ __( 'Sous-titre', 'sb-marketing-theme' ), 'Climatisation, électricité, plomberie et chauffage — une seule équipe pour tous vos besoins.', 'sanitize_textarea_field' ],
		'sbmt_cta_btn_text' => [ __( 'Texte du bouton', 'sb-marketing-theme' ), 'Demander un devis gratuit', 'sanitize_text_field' ],
		'sbmt_cta_btn_url'  => [ __( 'Lien du bouton', 'sb-marketing-theme' ), '/contact', 'esc_url_raw' ],
	] as $id => [ $label, $default, $sanitize ] ) {
		$wp_customize->add_setting( $id, [ 'default' => $default, 'sanitize_callback' => $sanitize, 'transport' => 'postMessage' ] );
		$wp_customize->add_control( $id, [ 'label' => $label, 'section' => 'sbmt_cta', 'type' => strlen( $default ) > 80 ? 'textarea' : 'text' ] );
	}
	$wp_customize->add_setting( 'sbmt_cta_image', [ 'default' => '', 'sanitize_callback' => 'esc_url_raw', 'transport' => 'refresh' ] );
	$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'sbmt_cta_image', [ 'label' => __( 'Image CTA', 'sb-marketing-theme' ), 'section' => 'sbmt_cta' ] ) );

	// ════════════════════════════════════════════════════════════════════════
	// SECTION: Section Blog
	// ════════════════════════════════════════════════════════════════════════
	$wp_customize->add_section( 'sbmt_blog_section', [
		'title'    => __( 'Section Blog (accueil)', 'sb-marketing-theme' ),
		'panel'    => 'sbmt_panel',
		'priority' => 85,
	] );
	foreach ( [
		'sbmt_blog_title'    => [ __( 'Titre', 'sb-marketing-theme' ),    'Conseils et guides pratiques', 'wp_kses_post' ],
		'sbmt_blog_btn_text' => [ __( 'Texte du bouton', 'sb-marketing-theme' ), 'Voir tous les articles', 'sanitize_text_field' ],
		'sbmt_blog_btn_url'  => [ __( 'Lien du bouton', 'sb-marketing-theme' ), '/blog', 'esc_url_raw' ],
		'sbmt_blog_posts_count' => [ __( 'Nombre d\'articles à afficher', 'sb-marketing-theme' ), '4', 'absint' ],
	] as $id => [ $label, $default, $sanitize ] ) {
		$wp_customize->add_setting( $id, [ 'default' => $default, 'sanitize_callback' => $sanitize, 'transport' => 'refresh' ] );
		$wp_customize->add_control( $id, [ 'label' => $label, 'section' => 'sbmt_blog_section', 'type' => 'text' ] );
	}

	// ════════════════════════════════════════════════════════════════════════
	// SECTION: Marquee / Bandeau défilant
	// ════════════════════════════════════════════════════════════════════════
	$wp_customize->add_section( 'sbmt_marquee', [
		'title'    => __( 'Bandeau défilant (marquee)', 'sb-marketing-theme' ),
		'panel'    => 'sbmt_panel',
		'priority' => 90,
	] );
	for ( $i = 1; $i <= 6; $i++ ) {
		$wp_customize->add_setting( "sbmt_marquee_image_{$i}", [ 'default' => '', 'sanitize_callback' => 'esc_url_raw', 'transport' => 'refresh' ] );
		$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, "sbmt_marquee_image_{$i}", [
			/* translators: %d: image number */
			'label'   => sprintf( __( 'Image marquee %d', 'sb-marketing-theme' ), $i ),
			'section' => 'sbmt_marquee',
		] ) );
	}

	// ════════════════════════════════════════════════════════════════════════
	// SECTION: Footer
	// ════════════════════════════════════════════════════════════════════════
	$wp_customize->add_section( 'sbmt_footer', [
		'title'    => __( 'Footer', 'sb-marketing-theme' ),
		'panel'    => 'sbmt_panel',
		'priority' => 100,
	] );
	foreach ( [
		'sbmt_footer_description'       => [ __( 'Description (colonne logo)', 'sb-marketing-theme' ), 'ClimaNova Energie assure l\'installation et l\'entretien de vos équipements à Nice et en région PACA — climatisation, électricité, plomberie et chauffage.', 'sanitize_textarea_field' ],
		'sbmt_footer_newsletter_title'  => [ __( 'Titre newsletter', 'sb-marketing-theme' ),           'Recevez nos conseils et offres exclusives !', 'sanitize_text_field' ],
		'sbmt_footer_copyright'         => [ __( 'Texte copyright', 'sb-marketing-theme' ),            '© Copyright ' . date( 'Y' ) . ', Tous droits réservés — ClimaNova Energie', 'sanitize_text_field' ],
		'sbmt_footer_made_by'           => [ __( 'Mention agence', 'sb-marketing-theme' ),             'Made by SB Marketing', 'sanitize_text_field' ],
		'sbmt_footer_made_by_url'       => [ __( 'Lien agence', 'sb-marketing-theme' ),                'https://sbmarketing.fr', 'esc_url_raw' ],
		'sbmt_footer_privacy_url'       => [ __( 'Lien Politique de confidentialité', 'sb-marketing-theme' ), '/politique-de-confidentialite', 'esc_url_raw' ],
		'sbmt_footer_terms_url'         => [ __( 'Lien Conditions générales', 'sb-marketing-theme' ),  '/conditions-generales', 'esc_url_raw' ],
	] as $id => [ $label, $default, $sanitize ] ) {
		$wp_customize->add_setting( $id, [ 'default' => $default, 'sanitize_callback' => $sanitize, 'transport' => 'refresh' ] );
		$wp_customize->add_control( $id, [ 'label' => $label, 'section' => 'sbmt_footer', 'type' => strlen( $default ) > 80 ? 'textarea' : 'text' ] );
	}
	$wp_customize->add_setting( 'sbmt_footer_logo', [ 'default' => '', 'sanitize_callback' => 'esc_url_raw', 'transport' => 'refresh' ] );
	$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'sbmt_footer_logo', [ 'label' => __( 'Logo footer (optionnel — utilise le logo principal si vide)', 'sb-marketing-theme' ), 'section' => 'sbmt_footer' ] ) );
}
add_action( 'customize_register', 'sbmt_customize_register' );

// ── Customizer Partial Refresh (postMessage bindings) ─────────────────────────
function sbmt_customize_preview_js() {
	wp_enqueue_script(
		'sbmt-customizer-preview',
		SBMT_URI . '/assets/js/customizer-preview.js',
		[ 'customize-preview', 'jquery' ],
		SBMT_VERSION,
		true
	);
}
add_action( 'customize_preview_init', 'sbmt_customize_preview_js' );
