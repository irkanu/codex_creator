<?php
/**
 * Setup codex_creator CPT
 *
 * @since 1.0.0
 * @package Codex Creator
 */

/**
 * Create our CPT
 *
 * @since 1.0.0
 * @package Codex Creator
 */
function cdxc_create_posttype() {

	$labels = array (
		'name'          => __('Codex', WP_CODEX_TEXTDOMAIN ),
		'singular_name' => __('Codex', WP_CODEX_TEXTDOMAIN ),
		'add_new'       => __('Add New', WP_CODEX_TEXTDOMAIN ),
		'add_new_item'  => __('Add New Page', WP_CODEX_TEXTDOMAIN ),
		'edit_item'     => __('Edit Page', WP_CODEX_TEXTDOMAIN ),
		'new_item'      => __('New Page', WP_CODEX_TEXTDOMAIN ),
		'view_item'     => __('View Page', WP_CODEX_TEXTDOMAIN ),
		'search_items'  => __('Search Pages', WP_CODEX_TEXTDOMAIN ),
		'not_found'     => __('No Page Found', WP_CODEX_TEXTDOMAIN ),
		'not_found_in_trash' => __('No Page Found In Trash', WP_CODEX_TEXTDOMAIN ) );

	$codex_defaults = array (
		'labels' => $labels,
		'can_export' => true,
		'capability_type' => 'post',
		'description' => 'Codex post type',
		'has_archive' => true,
		'hierarchical' => false,
		'map_meta_cap' => true,
		//'menu_icon' => $menu_icon,
		'public' => true,
		'query_var' => true,
		'rewrite' => array ('slug' => 'codex/%codex_project%', 'with_front' => false, 'hierarchical' => true),
		'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields', 'comments', 'revisions', /*'post-formats'*/ ),
		'taxonomies' => array('codex_category','codex_tags') );

	register_post_type( 'codex_creator',$codex_defaults);

	// Add new taxonomy, make it hierarchical (like categories)
	$labels = array(
		'name'              => _x( 'Codex Project', 'taxonomy general name' ),
		'singular_name'     => _x( 'Codex Project', 'taxonomy singular name' ),
		'search_items'      => __( 'Search Projects' ),
		'all_items'         => __( 'All Projects' ),
		'parent_item'       => __( 'Parent Folder' ),
		'parent_item_colon' => __( 'Parent Folder:' ),
		'edit_item'         => __( 'Edit Project' ),
		'update_item'       => __( 'Update Project' ),
		'add_new_item'      => __( 'Add New Project' ),
		'new_item_name'     => __( 'New Project Name' ),
		'menu_name'         => __( 'Codex Project' ),
	);

	$args = array(
		'hierarchical'      => true,
		'labels'            => $labels,
		'show_ui'           => true,
		'show_admin_column' => true,
		'query_var'         => true,
		'rewrite'           => array( 'slug' => 'project' ),
	);

	register_taxonomy( 'codex_project', 'codex_creator' , $args );

	// Add new taxonomy, NOT hierarchical (like tags)
	$labels = array(
		'name'                       => _x( 'Tags', 'taxonomy general name' ),
		'singular_name'              => _x( 'Tags', 'taxonomy singular name' ),
		'search_items'               => __( 'Search Tags' ),
		'popular_items'              => __( 'Popular Tags' ),
		'all_items'                  => __( 'All Tags' ),
		'parent_item'                => null,
		'parent_item_colon'          => null,
		'edit_item'                  => __( 'Edit Tag' ),
		'update_item'                => __( 'Update Tag' ),
		'add_new_item'               => __( 'Add New Tag' ),
		'new_item_name'              => __( 'New Tag Name' ),
		'separate_items_with_commas' => __( 'Separate tags with commas' ),
		'add_or_remove_items'        => __( 'Add or remove tags' ),
		'choose_from_most_used'      => __( 'Choose from the most used tags' ),
		'not_found'                  => __( 'No tags found.' ),
		'menu_name'                  => __( 'Tags' ),
	);

	$args = array(
		'hierarchical'          => false,
		'labels'                => $labels,
		'show_ui'               => true,
		'show_admin_column'     => true,
		'update_count_callback' => '_update_post_term_count',
		'query_var'             => true,
		'rewrite'               => array( 'slug' => 'codex_tag' ),
	);

	register_taxonomy( 'codex_tags', 'codex_creator', $args );
}

// Call the function to creat the CPT
add_action( 'init', 'cdxc_create_posttype' );


// insert the project folder in the permalink
add_filter('post_link', 'codex_creator_permalink', 1, 3);
add_filter('post_type_link', 'codex_creator_permalink', 1, 3);

function codex_creator_permalink($permalink, $post_id, $leavename) {
	//con %brand% catturo il rewrite del Custom Post Type
	if (strpos($permalink, '%codex_project%') === FALSE) return $permalink;
	// Get post
	$post = get_post($post_id);
	if (!$post) return $permalink;

	// Get taxonomy terms
	$terms = wp_get_object_terms($post->ID, 'codex_project');
	//print_r($terms);
    $taxonomy_slug = '';
	if (!is_wp_error($terms) && !empty($terms) && is_object($terms[0])) {

        foreach($terms as $term){
            if($term->parent=='0'){$taxonomy_slug = $term->slug;}
        }
	}

    if(!$taxonomy_slug){$taxonomy_slug = 'no-project';}

    //$taxonomy_slug = 'cat1/cat2';

	return str_replace('%codex_project%', $taxonomy_slug, $permalink);
}