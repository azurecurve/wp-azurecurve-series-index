<?php
/*
Plugin Name: azurecurve Series Index
Plugin URI: http://wordpress.azurecurve.co.uk/plugins/series-index

Description: Displays Index of Series Posts using series-index Shortcode. This plugin is multi-site compatible, contains an inbuilt show/hide toggle and supports localisation.
Version: 1.1.0

Author: azurecurve
Author URI: http://wordpress.azurecurve.co.uk

Text Domain: azurecurve-series-index
Domain Path: /languages

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.

The full copy of the GNU General Public License is available here: http://www.gnu.org/licenses/gpl.txt

*/

register_activation_hook( __FILE__, 'azc_si_set_default_options' );

function azc_si_set_default_options($networkwide) {
	
	$new_options = array(
				'width' => '65%',
				'toggle_default' => 1,
				'space_before_title_separator' => 0,
				'title_separator' => ':',
				'space_after_title_separator' => 1,
				'container_before' => "<table class='azc_si' style='width: $width; ' >",
				'container_after' => '</table>',
				'enable_header' => 1,
				'enable_header_link' => 1,
				'header_before' => "<tr><th class='azc_si'>",
				'header_after' => '</th></tr>',
				'current_before' => '<tr><td>',
				'current_after' => '</td></tr>',
				'detail_before' => '<tr><td>',
				'detail_after' => '</td></tr>'
			);
	
	// set defaults for multi-site
	if (function_exists('is_multisite') && is_multisite()) {
		// check if it is a network activation - if so, run the activation function for each blog id
		if ($networkwide) {
			global $wpdb;

			$blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
			$original_blog_id = get_current_blog_id();

			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );

				if ( get_option( 'azc_si_options' ) === false ) {
					add_option( 'azc_si_options', $new_options );
				}
			}

			switch_to_blog( $original_blog_id );
		}else{
			if ( get_option( 'azc_si_options' ) === false ) {
				add_option( 'azc_si_options', $new_options );
			}
		}
	}
	//set defaults for single site
	else{
		if ( get_option( 'azc_si_options' ) === false ) {
			add_option( 'azc_si_options', $new_options );
		}
	}
}

add_shortcode( 'series-index', 'azc_display_series_index' );

add_action('wp_enqueue_scripts', 'azc_si_load_css');
add_action('wp_enqueue_scripts', 'azc_si_load_jquery');

function azc_si_load_css(){
	wp_enqueue_style( 'azurecurve-series-index', plugins_url( 'style.css', __FILE__ ), '', '1.0.0' );
}

function azc_si_load_jquery(){
	wp_enqueue_script( 'azurecurve-series-index', plugins_url('jquery.js', __FILE__), array('jquery'), '3.9.1');
}

function azc_display_series_index($atts, $content = null) {
	// Retrieve plugin configuration options from database
	$options = get_option( 'azc_si_options' );
	
	extract(shortcode_atts(array(
		'title' => "",
		'replace' => "",
		'width' => stripslashes($options['width']),
		'toggle' => stripslashes($options['toggle_default']),
		'heading' => ""
	), $atts));
	
	global $wpdb;
	$post_id = get_the_ID();
	if (strlen($title)==0){
		$series_title = get_post_meta($post_id, 'Series', true);
	}else{
		$series_title = $title;
	}
	$clean_series_title = addslashes($series_title);
	$clean_replace = addslashes($replace);
	$post = get_post($post_id); 
	$slug = $post->post_name;
	
	$title_separator = '';
	if ($options['space_before_title_separator'] == 1){
		$title_separator .= ' ';
	}
	$title_separator .= stripslashes($options['title_separator']);
	if ($options['space_after_title_separator'] == 1){
		$title_separator .= ' ';
	}
	
	$SQL = "SELECT p.post_name AS post_name, p.post_title AS post_title, YEAR(post_date) AS PostYear, DATE_FORMAT(post_date, '%m') AS PostMonth FROM `".$wpdb->prefix."posts` p INNER JOIN `".$wpdb->prefix."postmeta` pm ON pm.post_id = p.id AND pm.meta_key = 'SERIES' AND pm.meta_value = '".$clean_series_title."' INNER JOIN `".$wpdb->prefix."postmeta` pmsp ON pmsp.post_id = p.id AND pmsp.meta_value <> '0' AND (pmsp.meta_key = 'SERIES POSITION' or pmsp.meta_key = 'SERIES POS') WHERE p.post_status = 'publish' ORDER BY CONVERT(pmsp.meta_value, UNSIGNED INTEGER)";
	$myrows = $wpdb->get_results($SQL);
	//echo $SQL;
	
	$rows = '';
	foreach ($myrows as $myrow){
		if (strlen($replace) == 0 ){
			$post_title = str_replace( $clean_series_title.$title_separator, '', $myrow->post_title);
		}else{
			$post_title = str_replace( $replace, '', $myrow->post_title);
		}
		if ($myrow->post_name == $slug){
			$rows .= stripslashes($options['current_before']).$post_title.stripslashes($options['current_after']);
		}else{
			$rows .= stripslashes($options['detail_before'])."<a href='/".$myrow->PostYear."/".$myrow->PostMonth."/".$myrow->post_name."/' class='azc_si_index'>".$post_title."</a>".stripslashes($options['detail_after']);
		}
	}
	
	$header = '';
	if ($options['enable_header'] == 1){
		if ($options['enable_header_link'] == 1){
			$series_title_link = '<a href="/' . $wpdb->get_var($wpdb->prepare("SELECT p.post_name FROM `".$wpdb->prefix."posts` p INNER JOIN `".$wpdb->prefix."postmeta` pm ON pm.post_id = p.id AND pm.meta_key = 'SERIES' AND pm.meta_value = '%s' INNER JOIN `".$wpdb->prefix."postmeta`  pmsp ON pmsp.post_id = p.id AND pmsp.meta_value = '0' AND (pmsp.meta_key = 'SERIES POSITION' or pmsp.meta_key = 'SERIES POS') WHERE p.post_status = 'publish' ORDER BY CONVERT(pmsp.meta_value, UNSIGNED INTEGER)", $clean_series_title)) . '" class="azc_si_link">' . $series_title . '</a>';
		}else{
			$series_title_link = $series_title;
		}
		$header = stripslashes($options['header_before']).$series_title_link.stripslashes($options['header_after']);
	}
	$output = str_replace('$width', $width, stripslashes($options['container_before'])).$header.$rows.stripslashes($options['container_after']);
	if ($toggle == 1){
		if (strlen($heading) == 0){
			$heading = __(sprintf('Click to show/hide the %s Series Index', $series_title), 'azurecurve-series-index');
		}
		$output = "<h3 class='azc_si_toggle'><a href='#'>".$heading."</a></h3><div class='azc_si_toggle_container'>".$output."</div>";
	}
	return $output;
}

add_filter('plugin_action_links', 'azc_si_plugin_action_links', 10, 2);

function azc_si_plugin_action_links($links, $file) {
    static $this_plugin;

    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }

    if ($file == $this_plugin) {
        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=azurecurve-series-index">'.__('Settings', 'azurecurve-series-index').'</a>';
        array_unshift($links, $settings_link);
    }

    return $links;
}


add_action( 'admin_menu', 'azc_si_settings_menu' );

function azc_si_settings_menu() {
	add_options_page( 'azurecurve Series Index Settings',
	'azurecurve Series Index', 'manage_options',
	'azurecurve-series-index', 'azc_si_config_page' );
}

function azc_si_config_page() {
	if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'azurecurve-series-index'));
    }
	
	// Retrieve plugin configuration options from database
	$options = get_option( 'azc_si_options' );
	?>
	<div id="azc-si-general" class="wrap">
		<fieldset>
			<h2><?php _e('azurecurve Series Index Configuration', 'azurecurve-series-index'); ?></h2>
			<?php if( isset($_GET['settings-updated']) ) { ?>
				<div id="message" class="updated">
					<p><strong><?php _e('Settings have been saved.') ?></strong></p>
				</div>
			<?php } ?>
			<form method="post" action="admin-post.php">
				<input type="hidden" name="action" value="save_azc_si_options" />
				<input name="page_options" type="hidden" value="width,title_separator,container_before,container_after,enable_header,enable_header_link,header_before,header_after,detail_before,detail_after" />
				
				<!-- Adding security through hidden referrer field -->
				<?php wp_nonce_field( 'azc_si_nonce', 'azc_si_nonce' ); ?>
				<table class="form-table">
				<tr><td colspan=2>
					<?php _e('When creating a series of posts there are two custom fields required:', 'azurecurve-series-index'); ?>
					<ol>
					<li><?php '<strong><em>Series - </em></strong>'._e('This is the title of the series.', 'azurecurve-series-index'); ?></li>
					<li><?php '<strong><em>Series Position - </em></strong>'._e('This is the order the posts should be displayed in; any post which is part of the series, but should not be included in the displayed index should have a series index of <strong>0</strong>.', 'azurecurve-series-index'); ?></li>
					</ol>
					<?php _e(sprintf('Place the %1$s shortcode in the post where the index should be displayed. The series index to be displayed is automatically derived from the %2$s custom field. The shortcode can be used in any post or page if the title parameter is used (see below).', '<strong>[series-index]</strong>', '<strong>Series</strong>'), 'azurecurve-series-index'); ?>
					
					<?php _e('There are three optional parameters which can be used:', 'azurecurve-series-index'); ?>
					<ol>
					<li><?php echo '<strong><em>title - </em></strong>';
					_e('only required if the series index is not being displayed in a post (or a page) within the series; set it to the title of the required series.', 'azurecurve-series-index'); ?>
					<li><?php echo '<strong><em>replace - </em></strong>';
					_e('only required if the series title in the post name differs from the <strong>Series</strong> custom field.', 'azurecurve-series-index'); ?>
					<li><?php echo '<strong><em>width - </em></strong>';
					_e('only required if the series index should have a different width to the default specified in the settings.', 'azurecurve-series-index'); ?>
					<li><?php echo '<strong><em>toggle - </em></strong>';
					_e('enables the show/hide toggle.', 'azurecurve-series-index'); ?>
					<li><?php echo '<strong><em>heading - </em></strong>';
					_e('only required to override the default toggle heading of "Click to show/hide <series title> Series Index".', 'azurecurve-series-index'); ?>
					</ol>
				</td></tr>
				<tr><th scope="row"><label for="width"><?php _e('Index Width', 'azurecurve-series-index'); ?></label></th><td>
					<input type="text" name="width" value="<?php echo esc_html( stripslashes($options['width']) ); ?>" class="regular-text" />
					<p class="description"><?php _e('Specify the width of the index; e.g. 75% or 500px', 'azurecurve-series-index'); ?></p>
				</td></tr>
				<tr><th scope="row">Toggle Default</th><td>
					<fieldset><legend class="screen-reader-text"><span><?php _e('Toggle Default', 'azurecurve-series-index'); ?></span></legend>
					<label for="toggle_default"><input name="toggle_default" type="checkbox" id="toggle" value="1" <?php checked( '1', $options['toggle_default'] ); ?> /><?php _e('Show/Hide Toggle default enabled?', 'azurecurve-series-index'); ?></label>
					</fieldset>
				</td></tr>
				<tr><th scope="row"><label for="width"><?php _e('Title Separator', 'azurecurve-series-index'); ?></label></th><td>
					<fieldset><legend class="screen-reader-text"><span><?php _e('Space before title separator?', 'azurecurve-series-index'); ?></span></legend>
					<label for="space_before_title_separator"><input name="space_before_title_separator" type="checkbox" id="space_before_title_separator" value="1" <?php checked( '1', $options['space_before_title_separator'] ); ?> /><?php _e('Space before?', 'azurecurve-series-index'); ?></label>
					<input type="text" name="title_separator" value="<?php echo esc_html( stripslashes($options['title_separator']) ); ?>" class="small-text" /><legend class="screen-reader-text"><span><?php _e('Space after title separator?', 'azurecurve-series-index'); ?></span></legend>
					<label for="space_after_title_separator"><input name="space_after_title_separator" type="checkbox" id="space_after_title_separator" value="1" <?php checked( '1', $options['space_after_title_separator'] ); ?> /><?php _e('Space after?', 'azurecurve-series-index'); ?></label>
					</fieldset>
					<p class="description" style='width: 70%; margin-left: 0; '><?php _e('If your series title is included in the post title specify the separator and surrounding spaces. e.g. <strong>Installing Microsoft Dynamics GP 2013 R2: Introduction</strong> has a separator of <strong>:</strong> with a following space so the <strong>Space before?</strong> checkbox should be unmarked, <strong>:</strong> entered in the text box and the <strong>Space after?</strong> checkbox marked.', 'azurecurve-series-index'); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="width"><?php _e('Start/End Index', 'azurecurve-series-index'); ?></label></th><td>
					<input type="text" name="container_before" value="<?php echo esc_html( stripslashes($options['container_before']) ); ?>" class="regular-text" /> 
					/ <input type="text" name="container_after" value="<?php echo esc_html( stripslashes($options['container_after']) ); ?>" class="regular-text" />
					<p class="description" style='width: 70%; margin-left: 0; '><?php _e(sprintf('Enter %s to be swapped out for value specified in Width field otherwise 100%% will be used.', '$width'), 'azurecurve-series-index'); ?></p>
				</td></tr>
				<tr><th scope="row"><?php _e('Enable Header Row', 'azurecurve-series-index'); ?></th><td>
					<fieldset><legend class="screen-reader-text"><span><?php _e('Enable Header Row', 'azurecurve-series-index'); ?></span></legend>
					<label for="enable_header"><input name="enable_header" type="checkbox" id="enable_header" value="1" <?php checked( '1', $options['enable_header'] ); ?> /><?php _e('Display header row?', 'azurecurve-series-index'); ?></label>
					</fieldset>
				</td></tr>
				<tr><th scope="row"><?php _e('Enable Header Link', 'azurecurve-series-index'); ?></th><td>
					<fieldset><legend class="screen-reader-text"><span><?php _e('Enable Header Link', 'azurecurve-series-index'); ?></span></legend>
					<label for="enable_header_link"><input name="enable_header_link" type="checkbox" id="enable_header_link" value="1" <?php checked( '1', $options['enable_header_link'] ); ?> /><?php _e('Enable header link back to series index?', 'azurecurve-series-index'); ?></label>
					</fieldset>
				</td></tr>
				<tr><th scope="row"><label for="width"><?php _e('Start/End Header Row', 'azurecurve-series-index'); ?></label></th><td>
					<input type="text" name="header_before" value="<?php echo esc_html( stripslashes($options['header_before']) );	?>" class="regular-text" /> 
					/ <input type="text" name="header_after" value="<?php echo esc_html( stripslashes($options['header_after']) );	?>" class="regular-text" />
				</td></tr>
				<tr><th scope="row"><label for="width"><?php _e('Start/End Detail Row', 'azurecurve-series-index'); ?></label></th><td>
					<input type="text" name="detail_before" value="<?php echo esc_html( stripslashes($options['detail_before']) );	?>" class="regular-text" /> 
					/ <input type="text" name="detail_after" value="<?php echo esc_html( stripslashes($options['detail_after']) );	?>" class="regular-text" />
					<p class="description" style='width: 70%; margin-left: 0; '></p>
				</td></tr>
				<tr><th scope="row"><label for="width"><?php _e('Start/End Current Row', 'azurecurve-series-index'); ?></label></th><td>
					<input type="text" name="current_before" value="<?php echo esc_html( stripslashes($options['current_before']) );	?>" class="regular-text" /> 
					/ <input type="text" name="current_after" value="<?php echo esc_html( stripslashes($options['current_after']) );	?>" class="regular-text" />
					<p class="description" style='width: 70%; margin-left: 0; '><?php _e('The current post can be formatted differently to the other detail rows.', 'azurecurve-series-index'); ?></p>
				</td></tr>
				</table>
				<input type="submit" value="Submit" class="button-primary"/>
			</form>
		</fieldset>
	</div>
<?php }

// Add actions
add_action('plugins_loaded', 'azc_si_load_plugin_textdomain');

function azc_si_load_plugin_textdomain(){
	
	$loaded = load_plugin_textdomain( 'azurecurve-series-index', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	//if ($loaded){ echo 'true'; }else{ echo 'false'; }
}

add_action( 'admin_init', 'azc_si_admin_init' );

function azc_si_admin_init() {
	
	add_action( 'admin_post_save_azc_si_options', 'process_azc_si_options' );
	
}

function process_azc_si_options() {
		// Check that user has proper security level
	if ( !current_user_can( 'manage_options' ) ){ wp_die( __('You do not have sufficient permissions to perform this action.', 'azurecurve-series-index') ); }

	if ( ! empty( $_POST ) && check_admin_referer( 'azc_si_nonce', 'azc_si_nonce' ) ) {	
		// Retrieve original plugin options array
		$options = get_option( 'azc_si_options' );
		
		$option_name = 'width';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		$option_name = 'toggle_default';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = 1;
		}else{
			$options[$option_name] = 0;
		}
		$option_name = 'space_before_title_separator';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = 1;
		}else{
			$options[$option_name] = 0;
		}
		$option_name = 'title_separator';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		$option_name = 'space_after_title_separator';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = 1;
		}else{
			$options[$option_name] = 0;
		}
		$option_name = 'container_before';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		$option_name = 'container_after';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		$option_name = 'enable_header';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = 1;
		}else{
			$options[$option_name] = 0;
		}
		$option_name = 'enable_header_link';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = 1;
		}else{
			$options[$option_name] = 0;
		}
		$option_name = 'header_before';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		$option_name = 'header_after';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		$option_name = 'current_before';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		$option_name = 'current_after';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		$option_name = 'detail_before';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		$option_name = 'detail_after';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		// Store updated options array to database
		update_option( 'azc_si_options', $options );
		
		// Redirect the page to the configuration form that was processed
		wp_redirect( add_query_arg( 'page', 'azurecurve-series-index&settings-updated', admin_url( 'options-general.php' ) ) );
		exit;
	}
}

?>