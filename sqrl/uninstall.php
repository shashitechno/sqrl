<?php

//if uninstall not called from WordPress exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) 
    exit();

$options_to_delete = array(
                        'sqrl_app_id',
                        'sqrl_app_secret',
                        'sqrl_fb_page_id',
                        'sqrl_img_link_video_width',
                        'sqrl_img_max_width',
                        'sqrl_limit',
                        'sqrl_link_video_max_width'
                    );

// For Single site
if ( !is_multisite() ) 
{
    foreach( $options_to_delete as $option ) {
        delete_option( $option );
    }
    
} 
// For Multisite
else 
{
    global $wpdb;
    $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
    $original_blog_id = get_current_blog_id();

    foreach ( $blog_ids as $blog_id ) 
    {
        switch_to_blog( $blog_id );
        foreach( $options_to_delete as $option ) {
            delete_option( $option );
        }
    }
    switch_to_blog( $original_blog_id );
    
}

global $wpdb;
$wpdb->query('DROP TABLE IF EXISTS '.$wpdb->prefix.'sqrl_fbwall_data');

?>
