<?php
/**
 * SB Marketing Theme — Custom Post Types & Meta Boxes
 * Registers: Services, Réalisations, Témoignages
 *
 * @package SBMarketingTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── 1. Services ──────────────────────────────────────────────────────────────
function sbmt_register_cpt_services() {
	$labels = [
		'name'               => __( 'Services', 'sb-marketing-theme' ),
		'singular_name'      => __( 'Service', 'sb-marketing-theme' ),
		'add_new'            => __( 'Ajouter un service', 'sb-marketing-theme' ),
		'add_new_item'       => __( 'Ajouter un nouveau service', 'sb-marketing-theme' ),
		'edit_item'          => __( 'Modifier le service', 'sb-marketing-theme' ),
		'new_item'           => __( 'Nouveau service', 'sb-marketing-theme' ),
		'view_item'          => __( 'Voir le service', 'sb-marketing-theme' ),
		'search_items'       => __( 'Rechercher des services', 'sb-marketing-theme' ),
		'not_found'          => __( 'Aucun service trouvé.', 'sb-marketing-theme' ),
		'not_found_in_trash' => __( 'Aucun service dans la corbeille.', 'sb-marketing-theme' ),
		'menu_name'          => __( 'Services', 'sb-marketing-theme' ),
	];
	register_post_type( 'climanova_service', [
		'labels'             => $labels,
		'public'             => true,
		'show_in_rest'       => true,
		'has_archive'        => true,
		'rewrite'            => [ 'slug' => 'services' ],
		'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ],
		'menu_icon'          => 'dashicons-hammer',
		'menu_position'      => 5,
		'show_in_nav_menus'  => true,
	] );
}
add_action( 'init', 'sbmt_register_cpt_services' );

// ── 2. Réalisations (Projects) ───────────────────────────────────────────────
function sbmt_register_cpt_projects() {
	$labels = [
		'name'               => __( 'Réalisations', 'sb-marketing-theme' ),
		'singular_name'      => __( 'Réalisation', 'sb-marketing-theme' ),
		'add_new'            => __( 'Ajouter une réalisation', 'sb-marketing-theme' ),
		'add_new_item'       => __( 'Ajouter une nouvelle réalisation', 'sb-marketing-theme' ),
		'edit_item'          => __( 'Modifier la réalisation', 'sb-marketing-theme' ),
		'new_item'           => __( 'Nouvelle réalisation', 'sb-marketing-theme' ),
		'view_item'          => __( 'Voir la réalisation', 'sb-marketing-theme' ),
		'search_items'       => __( 'Rechercher des réalisations', 'sb-marketing-theme' ),
		'not_found'          => __( 'Aucune réalisation trouvée.', 'sb-marketing-theme' ),
		'not_found_in_trash' => __( 'Aucune réalisation dans la corbeille.', 'sb-marketing-theme' ),
		'menu_name'          => __( 'Réalisations', 'sb-marketing-theme' ),
	];
	register_post_type( 'climanova_project', [
		'labels'            => $labels,
		'public'            => true,
		'show_in_rest'      => true,
		'has_archive'       => true,
		'rewrite'           => [ 'slug' => 'realisations' ],
		'supports'          => [ 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ],
		'menu_icon'         => 'dashicons-portfolio',
		'menu_position'     => 6,
		'show_in_nav_menus' => true,
	] );

	// Project category taxonomy
	register_taxonomy( 'project_category', 'climanova_project', [
		'labels'            => [
			'name'          => __( 'Catégories de réalisations', 'sb-marketing-theme' ),
			'singular_name' => __( 'Catégorie', 'sb-marketing-theme' ),
			'add_new_item'  => __( 'Ajouter une catégorie', 'sb-marketing-theme' ),
		],
		'public'            => true,
		'show_in_rest'      => true,
		'hierarchical'      => true,
		'rewrite'           => [ 'slug' => 'realisations/categorie' ],
		'show_admin_column' => true,
	] );
}
add_action( 'init', 'sbmt_register_cpt_projects' );

// ── 3. Témoignages (Testimonials) ────────────────────────────────────────────
function sbmt_register_cpt_testimonials() {
	$labels = [
		'name'               => __( 'Témoignages', 'sb-marketing-theme' ),
		'singular_name'      => __( 'Témoignage', 'sb-marketing-theme' ),
		'add_new'            => __( 'Ajouter un témoignage', 'sb-marketing-theme' ),
		'add_new_item'       => __( 'Ajouter un nouveau témoignage', 'sb-marketing-theme' ),
		'edit_item'          => __( 'Modifier le témoignage', 'sb-marketing-theme' ),
		'new_item'           => __( 'Nouveau témoignage', 'sb-marketing-theme' ),
		'search_items'       => __( 'Rechercher des témoignages', 'sb-marketing-theme' ),
		'not_found'          => __( 'Aucun témoignage trouvé.', 'sb-marketing-theme' ),
		'not_found_in_trash' => __( 'Aucun témoignage dans la corbeille.', 'sb-marketing-theme' ),
		'menu_name'          => __( 'Témoignages', 'sb-marketing-theme' ),
	];
	register_post_type( 'climanova_testimonial', [
		'labels'            => $labels,
		'public'            => false,
		'show_ui'           => true,
		'show_in_rest'      => true,
		'supports'          => [ 'title', 'editor', 'thumbnail' ],
		'menu_icon'         => 'dashicons-format-quote',
		'menu_position'     => 7,
	] );
}
add_action( 'init', 'sbmt_register_cpt_testimonials' );

// ══════════════════════════════════════════════════════════════════════════════
// META BOXES
// ══════════════════════════════════════════════════════════════════════════════
function sbmt_register_meta_boxes() {
	// Service meta box
	add_meta_box(
		'sbmt_service_meta',
		__( 'Détails du service', 'sb-marketing-theme' ),
		'sbmt_service_meta_cb',
		'climanova_service',
		'side',
		'high'
	);

	// Project meta box
	add_meta_box(
		'sbmt_project_meta',
		__( 'Détails de la réalisation', 'sb-marketing-theme' ),
		'sbmt_project_meta_cb',
		'climanova_project',
		'side',
		'high'
	);

	// Testimonial meta box
	add_meta_box(
		'sbmt_testimonial_meta',
		__( 'Informations du témoignage', 'sb-marketing-theme' ),
		'sbmt_testimonial_meta_cb',
		'climanova_testimonial',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'sbmt_register_meta_boxes' );

// ── Service Meta Callback ────────────────────────────────────────────────────
function sbmt_service_meta_cb( WP_Post $post ) {
	wp_nonce_field( 'sbmt_service_save', 'sbmt_service_nonce' );
	$price        = get_post_meta( $post->ID, '_service_price', true );
	$rating       = get_post_meta( $post->ID, '_service_rating', true );
	$review_count = get_post_meta( $post->ID, '_service_review_count', true );
	$icon         = get_post_meta( $post->ID, '_service_icon', true );
	?>
	<table class="form-table" style="margin:0">
		<tr>
			<th style="padding:8px 0"><label for="service_price"><?php esc_html_e( 'Prix (ex : À partir de 90 €)', 'sb-marketing-theme' ); ?></label></th>
			<td><input type="text" id="service_price" name="service_price" value="<?php echo esc_attr( $price ); ?>" class="widefat"></td>
		</tr>
		<tr>
			<th style="padding:8px 0"><label for="service_rating"><?php esc_html_e( 'Note (ex : 4.8)', 'sb-marketing-theme' ); ?></label></th>
			<td><input type="text" id="service_rating" name="service_rating" value="<?php echo esc_attr( $rating ); ?>" class="widefat"></td>
		</tr>
		<tr>
			<th style="padding:8px 0"><label for="service_review_count"><?php esc_html_e( 'Nb d\'avis (ex : 57K+)', 'sb-marketing-theme' ); ?></label></th>
			<td><input type="text" id="service_review_count" name="service_review_count" value="<?php echo esc_attr( $review_count ); ?>" class="widefat"></td>
		</tr>
		<tr>
			<th style="padding:8px 0"><label for="service_icon"><?php esc_html_e( 'Icône Material (ex : ac_unit)', 'sb-marketing-theme' ); ?></label></th>
			<td><input type="text" id="service_icon" name="service_icon" value="<?php echo esc_attr( $icon ); ?>" class="widefat"></td>
		</tr>
	</table>
	<?php
}

// ── Project Meta Callback ────────────────────────────────────────────────────
function sbmt_project_meta_cb( WP_Post $post ) {
	wp_nonce_field( 'sbmt_project_save', 'sbmt_project_nonce' );
	$location = get_post_meta( $post->ID, '_project_location', true );
	$service  = get_post_meta( $post->ID, '_project_service', true );
	?>
	<table class="form-table" style="margin:0">
		<tr>
			<th style="padding:8px 0"><label for="project_location"><?php esc_html_e( 'Lieu (ex : Nice)', 'sb-marketing-theme' ); ?></label></th>
			<td><input type="text" id="project_location" name="project_location" value="<?php echo esc_attr( $location ); ?>" class="widefat"></td>
		</tr>
		<tr>
			<th style="padding:8px 0"><label for="project_service"><?php esc_html_e( 'Type de service', 'sb-marketing-theme' ); ?></label></th>
			<td><input type="text" id="project_service" name="project_service" value="<?php echo esc_attr( $service ); ?>" class="widefat"></td>
		</tr>
	</table>
	<?php
}

// ── Testimonial Meta Callback ────────────────────────────────────────────────
function sbmt_testimonial_meta_cb( WP_Post $post ) {
	wp_nonce_field( 'sbmt_testimonial_save', 'sbmt_testimonial_nonce' );
	$author_name  = get_post_meta( $post->ID, '_testimonial_author', true );
	$author_role  = get_post_meta( $post->ID, '_testimonial_role', true );
	$rating       = get_post_meta( $post->ID, '_testimonial_rating', true );
	?>
	<table class="form-table">
		<tr>
			<th><label for="testimonial_author"><?php esc_html_e( 'Nom du client', 'sb-marketing-theme' ); ?></label></th>
			<td><input type="text" id="testimonial_author" name="testimonial_author" value="<?php echo esc_attr( $author_name ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th><label for="testimonial_role"><?php esc_html_e( 'Rôle / description (ex : Propriétaire à Nice)', 'sb-marketing-theme' ); ?></label></th>
			<td><input type="text" id="testimonial_role" name="testimonial_role" value="<?php echo esc_attr( $author_role ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th><label for="testimonial_rating"><?php esc_html_e( 'Note (1 à 5)', 'sb-marketing-theme' ); ?></label></th>
			<td>
				<select id="testimonial_rating" name="testimonial_rating">
					<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
						<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $rating, $i ); ?>>
							<?php echo esc_html( $i ); ?>
						</option>
					<?php endfor; ?>
				</select>
			</td>
		</tr>
	</table>
	<p class="description"><?php esc_html_e( 'Le contenu du témoignage est saisi dans l\'éditeur principal ci-dessus.', 'sb-marketing-theme' ); ?></p>
	<?php
}

// ── Save Meta Boxes ──────────────────────────────────────────────────────────
function sbmt_save_meta_boxes( int $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Service
	if ( isset( $_POST['sbmt_service_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sbmt_service_nonce'] ) ), 'sbmt_service_save' ) ) {
		foreach ( [ 'service_price', 'service_rating', 'service_review_count', 'service_icon' ] as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, "_{$field}", sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}
	}

	// Project
	if ( isset( $_POST['sbmt_project_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sbmt_project_nonce'] ) ), 'sbmt_project_save' ) ) {
		foreach ( [ 'project_location', 'project_service' ] as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, "_{$field}", sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}
	}

	// Testimonial
	if ( isset( $_POST['sbmt_testimonial_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sbmt_testimonial_nonce'] ) ), 'sbmt_testimonial_save' ) ) {
		foreach ( [ 'testimonial_author', 'testimonial_role' ] as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, "_{$field}", sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}
		if ( isset( $_POST['testimonial_rating'] ) ) {
			update_post_meta( $post_id, '_testimonial_rating', absint( $_POST['testimonial_rating'] ) );
		}
	}
}
add_action( 'save_post', 'sbmt_save_meta_boxes' );

// ── Flush rewrite rules on activation ────────────────────────────────────────
function sbmt_flush_rewrite_rules() {
	sbmt_register_cpt_services();
	sbmt_register_cpt_projects();
	sbmt_register_cpt_testimonials();
	flush_rewrite_rules();
}
register_activation_hook( SBMT_DIR . '/functions.php', 'sbmt_flush_rewrite_rules' );
