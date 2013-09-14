<?php

class wctest{

	// Imported FBWall class
	private $FB;
	
    public function __construct($fbwall){
        if(is_admin()){
			$this->FB = $fbwall;
			add_action('admin_menu', array($this, 'add_plugin_page'));
			add_action('admin_init', array($this, 'page_init'));
			add_action('admin_enqueue_scripts', array($this, 'my_enqueue'));
		}
    }
	
	
    public function add_plugin_page(){
        // This page will be under Wordpress "Settings"
		add_options_page('Facebook Squirrel', 'Facebook Squirrel', 'manage_options', 'Facebook_Squirrel', array($this, 'create_admin_page'));
    }

    public function create_admin_page() {

    ?>
	
    <div class="sqrl-sections cgrid-container">

	<div class="cgrid-row">

		<!-- column 1 -->
		<div class="col-sm-24 col-md-24 col-lg-18">

			<div class="sqrl-section-settings">
			    <?php screen_icon(); ?>
			    <h2>Facebook Squirrel Settings</h2>	

			    <form method="post" action="options.php">
				<?php
					// This prints out all hidden setting fields
					settings_fields('test_option_group');	
					do_settings_sections('Facebook_Squirrel');

					submit_button(); 
				?>
			    </form>
			</div>

			<div class="sqrl-section-feed-listing">
			<?php
					
					# "Save settings" clicked
					if( $_REQUEST['action'] == 'update' ) {
					
						$settings = array(
							'fetch_limit'     => get_option('sqrl_limit'),
							'image_width'     => get_option('sqrl_img_max_width'),
							'ext_image_width' => get_option('sqrl_link_video_max_width')
						);
						$this->FB->save_feed( $fbid, $settings );
						
					}
					

					# Delete a feed
					$fbid = $_REQUEST['sqrl_delete'];
					if( !empty( $fbid ) ) {

						$this->FB->db_remove_feed( $fbid );
						wp_redirect( $_SERVER['HTTP_REFERER'] );
						
					}
					
					
					# Update a feed
					$fbid = $_REQUEST['sqrl_update'];
					if( !empty( $fbid ) ) {


						$this->FB->db_remove_feed( $fbid );
						
						$settings = array(
							'fetch_limit'     => $_REQUEST['fetch_limit'],
							'image_width'     => $_REQUEST['image_width'],
							'ext_image_width' => $_REQUEST['ext_image_width']
						);
						$this->FB->save_feed( $fbid, $settings );
						wp_redirect( $_SERVER['HTTP_REFERER'] );

					}


					# Add new feed
					if( $_REQUEST['sqrl_new'] == 'true' ) {

						$settings = array(
							'fetch_limit'     => $_REQUEST['fetch_limit'],
							'image_width'     => $_REQUEST['image_width'],
							'ext_image_width' => $_REQUEST['ext_image_width']
						);
						$this->FB->save_feed( $_REQUEST['fb_page_id'], $settings );
						wp_redirect( $_SERVER['HTTP_REFERER'] );
					
					}


					$this->FB->show_feeds_list();
			?>
			</div>

		</div>
		<!-- end column 1 -->
			


		<!-- column 2 -->
		<div class="col-sm-24 col-md-24 col-lg-6">
		<div class="cgrid-row">

			<div class="col-sm-12 col-md-12 col-lg-24">
				<div class="sqrl-section-add-new sqrl-styled-box">
					<h3>Add feed</h3>
					<?php

						$this->FB->show_add_new_form();

					?>
				</div>
			</div>

			<div class="col-sm-12 col-md-12 col-lg-24">
				<div class="sqrl-section-add-new sqrl-styled-box text-center">
					<h4>Facebook Squirrel:</h4>
					<p><strong>Maciej Gurban</strong><br/>
					maciej@c2.fi</p>
					<p class="logo-container"><a href="http://c2.fi" target="_blank"><img src="<?php echo plugin_dir_url( __FILE__ ); ?>images/c2-logo.png"/></a></p>
					<p>mainoksia.kiitos@c2.fi<br/>+358 20 759 8465</p>
					<p class="text-justify">C2 is an advertising agency from Vaasa, Finland. We do digital marketing and advertising campaigns, as well as traditional print stuff.</p>
					<p class="text-justify">We also specialize in creating custom web solutions for our customers, using high quality latest technologies.</p>
				</div>
			</div>

			<div class="clear"></div>

		</div>
		</div>
		<!-- end column 2 -->

		<div class="clear"></div>

	</div>

	</div>

<?php

    } // end create-admin-page


	function my_enqueue() {

        wp_register_style( 'sqrl_styles', plugin_dir_url( __FILE__ ) . 'style.css' );
        wp_enqueue_style( 'sqrl_styles' );

	}


    public function page_init(){	

		register_setting('test_option_group', 'array_key', array($this, 'check_field'));


		add_settings_section('main_app_settings', 'Plugin settings', array($this, 'print_section_info'), 'Facebook_Squirrel');		
		add_settings_field('sqrl_app_id', 'Facebook APP ID', array($this, 'create_text_field'), 'Facebook_Squirrel', 'main_app_settings', array('field' => 'app_id'));	
		add_settings_field('sqrl_app_secret', 'Application secret', array($this, 'create_text_field'), 'Facebook_Squirrel', 'main_app_settings', array('field' => 'app_secret'));	
		
	}
	
	
	
    public function check_field($input) {
		foreach($input as $key => $value) {
			( $input[$key] === false ) ? add_option($key, $val) : update_option($key, $value);
		}
	}
	
    public function print_section_info(){
		print "In order to be able to fetch wall posts, you need to access Facebook API. To acess it, create a new <a href=\"https://developers.facebook.com/apps\" target=\"_blank\">Facebook application</a>. Once you're done, copy its details here.";
    }    
	public function single_feed_options_info(){
		print "To change each feed's options, copy its Facebook page ID into the <em>Facebook page ID</em> field, update fields accordingly, and press <strong>Save changes</strong>.";
    }
	
    public function create_text_field(array $args){
		$field_name = $args['field'];
        echo '<input type="text" id="' .$field_name. '" class="regular-text" name="array_key[sqrl_' .$field_name. ']" value="'. get_option( 'sqrl_' .$field_name ) .'" />';
    }
}



?>