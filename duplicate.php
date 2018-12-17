<?php
/*
Plugin Name: Duplicate
Plugin URI: http://страница_автора_плагина
Description: клонирует страницы
Version: Номер версии плагина, например: 1.0
Author: noname
Author URI: http://страница_автора_плагина
*/

function duplicate(){
	global $wpdb;
	if (! ( isset( $_GET['post']) || isset( $_POST['post'])  || ( isset($_REQUEST['action']) && 'duplicate' == $_REQUEST['action'] ) ) ) {
		wp_die('No post to duplicate has been supplied!');
	}


	if ( !isset( $_GET['duplicate_nonce'] ) || !wp_verify_nonce( $_GET['duplicate_nonce'], basename( __FILE__ ) ) )
		return;


	$post_id = (isset($_GET['post']) ? absint( $_GET['post'] ) : absint( $_POST['post'] ) );

	$post = get_post( $post_id );


	$current_user = wp_get_current_user();
	$new_post_author = $current_user->ID;


	if (isset( $post ) && $post != null) {


		$args = array(
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'post_author'    => $new_post_author,
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_name'      => $post->post_name,
			'post_parent'    => $post->post_parent,
			'post_password'  => $post->post_password,
			'post_status'    => 'draft',
			'post_title'     => $post->post_title,
			'post_type'      => $post->post_type,
			'to_ping'        => $post->to_ping,
			'menu_order'     => $post->menu_order
		);
		$optionsbefore = array(
			'post_title' => 0,
			'post_content' => 0,
			'post_excerpt' => 0,
			'post_password' => 0
		);
		$options = get_option('option_name');
		$options = array_merge($optionsbefore, $options);
		foreach( $options as $name => $val ){
			if( $val == 0){
				if( $name == 'post_title')
					$args["$name"] = 'untitled';
				else
				unset($args["$name"]);
			}
		}

		$new_post_id = wp_insert_post( $args );


		$taxonomies = get_object_taxonomies($post->post_type);
		foreach ($taxonomies as $taxonomy) {
			$post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
			wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
		}


		$post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id");
		if (count($post_meta_infos)!=0) {
			$sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";
			foreach ($post_meta_infos as $meta_info) {
				$meta_key = $meta_info->meta_key;
				if( $meta_key == '_wp_old_slug' ) continue;
				$meta_value = addslashes($meta_info->meta_value);
				$sql_query_sel[]= "SELECT $new_post_id, '$meta_key', '$meta_value'";
			}
			$sql_query.= implode(" UNION ALL ", $sql_query_sel);
			$wpdb->query($sql_query);
		}



		if ('page' == $post->post_type) {
			wp_redirect( admin_url('edit.php?post_type=page') );
			exit();
		}
	} else {
		wp_die('Post creation failed, could not find original post: ' . $post_id);
	}
}

add_action( 'post_action_duplicate', 'duplicate' );

add_filter( 'post_row_actions', 'duplicate_row_actions', 10, 2 );

add_filter( 'page_row_actions', 'duplicate_row_actions', 10, 2 );

function duplicate_row_actions( $actions, WP_Post $post ) {
    $actions['duplicate'] = '<a href="' . wp_nonce_url('http://localhost/wordpress/wp-admin/post.php?action=duplicate&post=' . $post->ID, basename(__FILE__) , 'duplicate_nonce') . '" title="Duplicate this item" rel="permalink">Duplicate</a>';
    return $actions;
}

add_action('admin_menu', 'add_duplicate_page');
function add_duplicate_page(){
	add_options_page( 'Duplicate', 'Duplicate', 'manage_options', 'duplicate_slug', 'primer_options_page_output' );
}

function primer_options_page_output(){
	?>
	<div class="wrap">
		<h2><?php echo get_admin_page_title() ?></h2>

		<form action="options.php" method="POST">
			<?php
				settings_fields( 'option_group' );
				do_settings_sections( 'duplicate_page' );
				submit_button();
			?>
		</form>
	</div>
	<?php
}

add_action('admin_init', 'plugin_settings');
function plugin_settings(){

	register_setting( 'option_group', 'option_name', 'sanitize_callback' );


	add_settings_section( 'section_id', 'What to copy', '', 'duplicate_page' );


	add_settings_field('primer_field2', 'Post/page elements to copy', 'fill_primer_field2', 'duplicate_page', 'section_id' );
}

function fill_primer_field2(){
	$val = get_option('option_name');
	?>
	<label><input type="checkbox" name="option_name[post_title]" value="1" <?php checked( 1, $val['post_title'] ) ?> /> Title</label></br>
	<label><input type="checkbox" name="option_name[post_content]" value="1" <?php checked( 1, $val['post_content'] ) ?> /> Content</label></br>
	<label><input type="checkbox" name="option_name[post_excerpt]" value="1" <?php checked( 1, $val['post_excerpt'] ) ?> /> Excerpt</label></br>
	<label><input type="checkbox" name="option_name[post_password]" value="1" <?php checked( 1, $val['post_password'] ) ?> /> Password</label>
	<?php
}

function sanitize_callback( $options ){

	foreach( $options as $name => & $val ){
			$val = intval( $val );
	}

	return $options;
}

function your_plugin_settings_link($links) {
  $settings_link = '<a href="options-general.php?page=duplicate_slug">Settings</a>';
  array_unshift($links, $settings_link);
  return $links;
}

$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'your_plugin_settings_link' );
?>