<?php

/*

	Plugin Name: Facebook Squirrel 0.2.1
	Plugin URI:
	Description: Caches facebook wall feeds in the database allowing for quick retrieval. Resizes both facebook and external source images.
	Version: 0.2.1
	Author: Maciej Gurban, C2 Advertising
	Author URI: http://c2.fi
	
*/

ini_set('max_execution_time', 60);

# Register plugin activation hook
register_activation_hook(__FILE__,'simple_prod_install');

# Register plugin deactivation hook
register_deactivation_hook( __FILE__, 'plugin_deactivation' );

# Plugin version
$simple_prod_version = "0.2.1";


$plugin_dir_path = dirname(__FILE__);
include $plugin_dir_path. '/options.php';



$config = array(
	'app_id'      => get_option('sqrl_app_id'),
	'secret'      => get_option('sqrl_app_secret')
);


# Initialize the plugin
global $wpdb;
$fbwall = new FBWall($config, $wpdb);
$wctest = new wctest( $fbwall );


# Default behavior, allowing to choose cached feed to be displayed
function show_feed( $atts ) {

	global $fbwall;

	ob_start();
	
	extract( 
		shortcode_atts( 
			array(
			  'source_page_id' => 'no page id supplied'
			), 
		$atts)
	);
	
	$fbwall->show_wall_feed( "{$source_page_id}" );
	
	$output_string = ob_get_contents();
	ob_end_clean();
	
	return $output_string;

}
add_shortcode( 'sqrl', 'show_feed' );



if ( ! wp_next_scheduled('prefix_hourly_event_hook') ) {
	wp_schedule_event( current_time ( 'timestamp' ), 'hourly', 'prefix_hourly_event_hook' );
}
add_action( 'prefix_hourly_event_hook', 'hourly_update_wall' );



function hourly_update_wall() {

	global $fbwall;
	$list = $fbwall->get_feeds_list();

	if( $list ) {
		foreach ( $list as $item ) {

			$feed_data = $fbwall->db_get_feed( $item['fb_page_id'] );

			$settings['fetch_limit']     = $feed_data->fetch_limit;
			$settings['image_width']     = $feed_data->image_width;
			$settings['ext_image_width'] = $feed_data->ext_image_width;
			
			$fbwall->save_feed( $item['fb_page_id'], $settings );

		}
	}
}
add_shortcode( 'sqrl_update', 'hourly_update_wall' );


// On Deactivation
function plugin_deactivation() {
	# remove function from the scheduled action hook.
	wp_clear_scheduled_hook( 'prefix_hourly_event_hook' );
}


// On Activation
function simple_prod_install () {

	global $wpdb;
	global $simple_prod_version;

	$table_name = $wpdb->prefix . "sqrl_fbwall_data";

	if( $wpdb->get_var("show tables like '$table_name'") != $table_name) {

	    $sql = "CREATE TABLE " . $table_name . " (
		  id mediumint(9) NOT NULL AUTO_INCREMENT,
		  fb_page_id VARCHAR(32) NOT NULL,
		  wall_feed text NOT NULL,
		  fetch_limit INT(2) NOT NULL,
		  image_width INT(4) NOT NULL,
		  ext_image_width INT(4) NOT NULL,
		  added TIMESTAMP,
		  UNIQUE KEY id (id)
		);";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		dbDelta($sql);

/*
		$fb_page_id  = "358130104199708";
		$wall_feed   = "-empty-";
		$fetch_limit = 3;
		$image_width = 320;
		$ext_image_width = 320;

		$insert = "INSERT INTO " . $table_name .
			" (fb_page_id, wall_feed, fetch_limit, image_width, ext_image_width) " .
			"VALUES ('" . $wpdb->escape($fb_page_id) . "','" . $wpdb->escape($wall_feed) . "', " .intval($fetch_limit) .", " .intval($image_width) .", " .intval($ext_image_width) .")";
*/
	// just in case, add wpdb->prepare
	//$results = $wpdb->query( $wpdb->prepare($insert) );

		add_option("simple_prod_version", $simple_prod_version);
		
   }
}



/*
$url = "site.com/?action=something";
$action = "myplugin-update";
$link = wp_nonce_url( $url, $action );
echo "<a href='$link'>click here</a>"; // whatever you're doing to echo the nonced link
*/


//add_action('sqrl/devlounge-plugin-series.php',  array(&$dl_pluginSeries, 'init'));

class FBWall {
  
	//public $fb_page_id;
	public $fb_wall_feed;
	
	private $app_secret;
	private $app_id;
	private $token;
	
	protected $wpdb;

    function __construct($c, $wpdb) {

		# objectify config (unnecessary, removal, requires changes)
		$c = json_decode(json_encode($c), FALSE);

		$this->app_id      = $c->app_id;
		$this->app_secret  = $c->secret;
		
		$this->wpdb 	   = $wpdb;

    }
	

	function get_feeds_table() {
		return $this->wpdb->prefix . 'sqrl_fbwall_data';
	}
	
	
	function get_access_token_expiration() {
		
	}

	# Not in use currently. Tokens don't expire unless app secret is changed (and possibly when password is changed -  TO BE CHECKED)
	function get_token_renewal_url() {

		return 'https://graph.facebook.com/oauth/access_token?'
			  .'&grant_type=fb_exchange_token'
			  .'&fb_exchange_token=' .$this->get_access_token()
			  .'&client_id=' .$this->app_id
			  .'&client_secret=' .$this->app_secret;
	}
	
	function get_token_url() {
	
		return 'https://graph.facebook.com/oauth/access_token?'
			  .'grant_type=client_credentials'
			  .'&client_id=' .$this->app_id
			  .'&client_secret=' .$this->app_secret;
	}
	
	function feed_exists($fb_page_id) {
		return $this->db_get_feed($fb_page_id);
	}
	
	/* model/controller
	 * action: update db records
	 * output:
	 * success: true
	 * fail: false
	 */
	function db_save_feed( $fb_page_id, $settings, $data ) {
		
		# If fetching posts from wall successfull
		if( sizeof($data['data']) > 0 ) {

			// Handles updating currently existing feed
			if( $this->feed_exists( $fb_page_id ) ) {
			
				$fields_to_update = array(
					'wall_feed' => json_encode($data)
				);
				
				if( $settings ) {
					$fields_to_update['fetch_limit']     = intval($settings['fetch_limit']);
					$fields_to_update['image_width']     = intval($settings['image_width']);
					$fields_to_update['ext_image_width'] = intval($settings['ext_image_width']);
				}
						
				$query = $this->wpdb->update(
					$this->get_feeds_table(),
					$fields_to_update, 
					array('fb_page_id' => $fb_page_id) // where statement
				);

			}

			// Handles adding a new feed
			else {
			
				$fields_to_insert = array(
					'fb_page_id'  => $fb_page_id,
					'wall_feed'   => json_encode($data)
				);
				
				if( $settings ) {
					$fields_to_insert['fetch_limit']     = intval($settings['fetch_limit']);
					$fields_to_insert['image_width']     = intval($settings['image_width']);
					$fields_to_insert['ext_image_width'] = intval($settings['ext_image_width']);
				}
				
				$query = $this->wpdb->insert(
					$this->get_feeds_table(),
					$fields_to_insert
				);
				
			}
			
			return $query;
		}
		
		return false;

	}
	

	function db_remove_feed( $fb_page_id ) {
	
		return $this->wpdb->query( 
			$this->wpdb->prepare('DELETE FROM '. $this->get_feeds_table() .' WHERE fb_page_id = %s',
				$fb_page_id
			)
		);
	}

	
	
	# controller
	# Request an authentication token
	function get_access_token($renew = false) {
	
		$token_url = ($renew == true) ? $this->get_token_renewal_url() : $this->get_token_url();
		$token = wp_remote_get( $token_url );

		if($token) {
			return $token['body'];
		}

		return false;
	}

	# Request page feed data
	/* 
	 * model
	 * output:
	 * success: array
	 * fail: false
	 */
	function fetch_wall_feed_data($fb_page_id, $fetch_limit) {
		
		$token = $this->get_access_token();

		if( $token ) {
			
			$feed_url = 'https://graph.facebook.com/' .$fb_page_id. '/feed?limit=' .$fetch_limit. '&' .$token;
			$feed 	  = wp_remote_get( $feed_url );
			$data     = json_decode( $feed['body'], true);

			return $data;
		}

		return false;
	}

	
	# Convert wall feed array in object data
	/* controller
	 * output: 
	 * success: object (array to object)
	 * fail: false
	 */
	function save_feed($fb_page_id, $settings) {
		
		$feed_data = $this->fetch_wall_feed_data($fb_page_id, $settings['fetch_limit']);

		if( $feed_data ) {
		
			$data = $feed_data;
			return $this->db_save_feed($fb_page_id, $settings, $data);
		}

		return false;
		
	}

	
	/* model
	 * output:
	 * success: string (json obj)
	 * fail: false
	 */
	function db_get_feed($fb_page_id) {
	
		$wpdb = $this->wpdb;
		
		$feed = $wpdb->get_row(
			$wpdb->prepare('
				SELECT id, fb_page_id, wall_feed, fetch_limit, image_width, ext_image_width
				FROM ' . $this->get_feeds_table() .'
				WHERE fb_page_id = %s',
				$fb_page_id
			)
		);
		
		if( $feed ) {
			return $feed;
		}

		return false;
		
	}
	
	// add wpdb prepare
	function db_get_feed_settings($fb_page_id) {
	
		$wpdb = $this->wpdb;
		
		$feed = $wpdb->get_row(
			$wpdb->prepare('
				SELECT fb_page_id, fetch_limit, image_width, ext_image_width
				FROM ' . $this->get_feeds_table() .'
				WHERE fb_page_id = %s',
				$fb_page_id
			)
		);
		
		if( $feed ) {
			return $feed;
		}

		return false;
		
	}
	


	function convertUrlQuery($query) { 
		$queryParts = explode('&', $query); 
		
		$params = array(); 
		foreach ($queryParts as $param) { 
			$item = explode('=', $param); 
			$params[$item[0]] = $item[1]; 
		} 
		
		return $params; 
	}


	 
	function get_big_fb_post_image( $url ) {
		if( strstr( $url, '_s.jpg') ) {
			return str_replace('_s.jpg', '_n.jpg', $url);
		}
	}
	 


	function show_wall_feed($fb_page_id) {
	
		$feed_data = $this->db_get_feed($fb_page_id);
		$json      = json_decode( $feed_data->wall_feed );
		
		if($json->data) {
			foreach($json->data as $post) {
			
				// Check which are necessary;
				$msg = "";
				$img = "";
				$img_url = "";
				$image = "";
		
				if( property_exists($post, 'picture') ) {
					$post_pic = $post->picture;
				}
				
				$nodisplay = false;
				

				# Display different template according to post type
				switch( $post->type ) {
					
					case 'link':
					case 'video':
					
						# Get the source URL of the image
						$img_url = parse_url( $post_pic );
						$img     = $this->convertUrlQuery( $img_url['query'] );
						$image   = wp_get_image_editor( urldecode($img['url']) );

						$width   = $feed_data->ext_image_width;
						
						if ( ! is_wp_error( $image) ) {

							$image->resize( $width, null, false );

							$filename = ABSPATH. 'wp-content/uploads/fb_out_' .$post->id;
							$saved    = $image->save( $filename );
							$post_pic = get_bloginfo('siteurl'). '/wp-content/uploads/' .$saved['file'];

						}
					
						if( strlen($post_pic) > 0 ) {
							$post_image = '<img src="' .$post_pic. '" class="post-link-image"/>';
						}
						
						if( strlen($post->name) > 0 ) {
							$post_name = $post->name;
						}
						else {
							$post_name = $post->link;
						}
						
						$msg .= '
						<a href="'. $post->link .'">'. $post_image .'</a>
						<div class="post-link-description">'
							.'<a href="'. $post->link .'" title="'. $post->caption .'" target="_blank">'
							.'<p class="post-name">'. $post_name .'</p>'
							.'</a>'
							.'<p class="post-link-description">'. $post->description. '</p>
						</div></a>
						'. PHP_EOL;
						
					break;
					
					default:
					case 'photo':
					case 'status':
						
						if( property_exists($post, 'picture') ) {
							$post_pic = $this->get_big_fb_post_image( $post->picture );
						}
						
						$image = wp_get_image_editor( $post_pic );
						
						$width =  $feed_data->image_width;

						if ( ! is_wp_error( $image) ) {

							$image->resize( $width, null, false );

							$filename = ABSPATH.'wp-content/uploads/fb_in_'.$post->id;
							$saved    = $image->save( $filename );
							$post_pic = get_bloginfo('siteurl').'/wp-content/uploads/'.$saved['file'];
						}
						
						if( property_exists($post, 'link') ) {
							$msg .= '<a href="' .$post->link. '" target="_blank" class="post-img-link"><img src="' .$post_pic. '" class="post-link-image"/></a>';
						}
						
					break;
					

					# Decide which post types to hide from display
					case 'checkin':
					case 'question':
					case 'page':
					
						$nodisplay = true;

					break;

				}
			
				if( $nodisplay == false ) {
			
					$template  = "";
					$story_id  = $post->id;
					$post_time = date("H:i d.m.Y", strtotime($post->created_time) );
					
					if( property_exists($post, 'link') ) {
						$post_link = $post->link;
					}

					$template .= PHP_EOL. '
					<div class="fb-post post-type-' .$post->type. '">
						<div class="fb-poster-profile">
							<a href="https://facebook.com/' .$post->from->id. '"  target="_blank">
								<img src="http://graph.facebook.com/' .$fb_page_id. '/picture" class="fb-poster-pic"/>
								<div class="fb-poster-name">
									' .$post->from->name. '
								</div>
								<div class="fb-poster-submitted">
									' .$post_time. '
								</div>
							</a>
						</div>
						<div class="fb-post-pic">
							' .$msg. '
						</div>
						<div class="fb-post-content">
						
							<div class="fb-post-message">
								' .$post->message .'
							</div>
							<div class="post-text-link-wrapper">
								<a href="' .$post_link. '" target="_blank" class="post-text-link">Read more</a>
							</div>
							
						</div>

					</div>';
				
				}
				
				echo $template;
				
			}
		}
		
	}
	


	function show_add_new_form() {
		
		?>

			<form method="post"><input type="hidden" name="sqrl_new" value="true"/>

			<div class="cgrid-row">
			
			<div class="col-sm-12 col-md-12 col-lg-24">
				<label for="fb_page_id">Facebook page ID</label><br/>
				<input type="text" name="fb_page_id" value=""/>
			</div>

			<div class="col-sm-12 col-md-12 col-lg-24">
				<label for="fetch_limit">Posts to fetch</label><br/>
				<input type="text" name="fetch_limit" value=""/>
			</div>

			<div class="col-sm-12 col-md-12 col-lg-24">
				<label for="image_width">Max. facebook-originating<br/>image width </label><br/>
				<input type="text" name="image_width" value=""/>
			</div>

			<div class="col-sm-12 col-md-12 col-lg-24">
				<label for="ext_image_width">Max. external<br/>image width</label><br/>
				<input type="text" name="ext_image_width" value=""/>
			</div>

			<div class="col-sm-12 col-md-12 col-lg-24">
				<p class="submit"><input type="submit" value="Create new" class="button button-primary"/></p>
			</div>

			<div class="clear"></div>

			</div>
			
			
			</form>

		<?php
	}
	

	/* model
	 * output:
	 * success: array
	 * fail: false
	 */
	function get_feeds_list() {

		$wpdb = $this->wpdb;
		
		$feeds = $wpdb->get_results( '
			SELECT id, fb_page_id, added, fetch_limit, image_width, ext_image_width
			FROM ' . $this->get_feeds_table().
			' ORDER BY fb_page_id ASC'
		);
		// TODO: add int index
		
		if( $feeds ) {
			$i = 0;
			foreach( $feeds as $feed) {
				$list[$i]['fb_page_id'] = $feed->fb_page_id;
				$list[$i]['added']      = $feed->added;
				$list[$i]['fetch_limit']     = $feed->fetch_limit;
				$list[$i]['image_width']     = $feed->image_width;
				$list[$i]['ext_image_width'] = $feed->ext_image_width;
				$i++;
			}
			return $list;
		}
		
		return false;
		
	}

	/* view
	 * call: model
	 * output:
	 * success: string (formatted)
	 * fail: false
	 */
	function show_feeds_list() {

		$list = $this->get_feeds_list();

		echo '<h3>Cached feeds:</h3>';
		//table
		// row 1: 6 cells
		// row 2: 3 cells
		if( $list ) {
				
			foreach ( $list as $item ) {
			
				$feed_json = @file_get_contents('http://graph.facebook.com/' . $item['fb_page_id'] );
				$feed_data = json_decode( $feed_json );
				
				$delete_button = '<input type="button" value="Remove" class="button button-secondary" onclick="jQuery(\'#delete_post_'.$item['fb_page_id'] .'\').click();"/>';
				$update_button = '<input type="button" value="Update" class="button button-primary update_post_submit" data-post-ref="'.$item['fb_page_id'].'"/>';


				$delete_form = '<form method="post" class="hide"><input type="hidden" name="sqrl_delete" value="'.$item['fb_page_id'] .'"/><input type="submit" value="Remove" id="delete_post_'.$item['fb_page_id'] .'"></form>';
				echo $delete_form;

				$update_form =  '<form class="hide" method="post"><input type="hidden" name="sqrl_update" value="'.$item['fb_page_id'] .'"/>'
								.'<input type="hidden" value="" name="fetch_limit" id="fetch_limit_'.$item['fb_page_id'] .'"/>'
								.'<input type="hidden" value="" name="image_width" id="image_width_'.$item['fb_page_id'] .'"/>'
								.'<input type="hidden" value="" name="ext_image_width" id="ext_image_width_'.$item['fb_page_id'] .'"/>'
								.'<input type="submit" id="update_post_'.$item['fb_page_id'] .'"/></form>';
				echo $update_form;

				// 24 cols

				echo '<div class="cgrid-row sqrl-styled-box feed-table">';

				echo '<div class="col-sm-6 col-md-4 col-lg-5">'
						.'<strong>Facebook page ID:</strong>'
						.$item['fb_page_id']
					.'</div>';

				echo '<div class="col-sm-14 col-md-10 col-lg-8">'
						.'<strong>Page name:</strong>'
						.$feed_data->name
					.'</div>';

				echo '<div class="col-sm-4 col-md-2 col-lg-3 text-center" id="sqrl-limit-box">'
						.'<strong>Limit:</strong>'
						.'<input type="text" value="'. $item['fetch_limit'] .'" name="fetch_limit" id="f_fetch_limit_'.$item['fb_page_id'].'" class="text-center"/>'
					.'</div>';

				echo '<div class="col-sm-6 col-md-4 col-lg-4 text-center sm-text-left" id="sqrl-image-width-box">'
						.'<strong>Facebook images:</strong>'
						.'<input type="text" value="'. $item['image_width'] .'" name="image_width" id="f_image_width_'.$item['fb_page_id'].'" class="text-center"/> px'
					.'</div>';

				echo '<div class="col-sm-14 col-md-4 col-lg-4 text-center sm-text-left" id="sqrl-link-image-width-box">'
						.'<strong>External images:</strong>'
						.'<input type="text" value="'. $item['ext_image_width'] .'" name="ext_image_width" id="f_ext_image_width_'.$item['fb_page_id'].'" class="text-center"/> px'
					.'</div>';

				echo '<div class="col-sm-4 col-md-4 col-lg-5 sm-text-center">'
						.'<strong>Data freshness:</strong> '
						.date("H:i d.m.Y", strtotime($item['added']))
					.'</div>';
				

				echo '<div class="col-sm-16 col-md-12 col-lg-11">'
						.'<strong>Shortcode:</strong>'
						.'[sqrl source_page_id=\''. $item['fb_page_id'] .'\']'
					.'</div>';

				echo '<div class="col-sm-4 col-md-4 col-lg-4 md-text-center text-center">'
						.$delete_button
					.'</div>';

				echo '<div class="col-sm-4 col-md-4 col-lg-4 md-text-center text-center">'
						.$update_button
					.'</div>';

				echo '<div class="clear"> </div>';

				echo '</div>';

				echo '</form>';



			}
			
			?>

				<script>
					jQuery('.update_post_submit').click(function(){
						var post_id = jQuery(this).attr('data-post-ref');

						jQuery('#fetch_limit_'+post_id).val( jQuery('#f_fetch_limit_'+post_id).val() );
						jQuery('#image_width_'+post_id).val( jQuery('#f_image_width_'+post_id).val() );
						jQuery('#ext_image_width_'+post_id).val( jQuery('#f_ext_image_width_'+post_id).val() );

						jQuery('#update_post_'+post_id).click();
					});
				</script>

			<?php
						

		}
		else {
			echo '<p>No feeds to display.</p>';
		}
		
	}
	
	
  }


/*
add_action('init', 'register_plugin_in_menu');

function register_plugin_in_menu() {

    $labels = array(

       'menu_name' => _x('FB Squirrel', 'squirrel'),

    );


}

//http://www.1stwebdesigner.com/css/wordpress-plugin-development-course-for-designers-custom-post-type/

*/



?>