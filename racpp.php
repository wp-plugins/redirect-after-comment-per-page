<?php
/*
  Plugin Name: Redirect After Comment Per Page
  Plugin URI: http://www.anyideas.net/redirect-after-comment-per-page-wordpress-plugin/
  Description: Redirects commenters to a page-defined URL after clicking submit.
  Author: Jean-Philippe Policieux
  Version: 0.9.4
  Author URI: http://www.anyideas.net
 */

/*  Jean-Philippe Policieux (Email : policieuxjp@gmail.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

#if (!load_plugin_textdomain('redirect-after-comment-per-page', '', 'wp-content/languages/'))
load_plugin_textdomain('redirect-after-comment-per-page', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/');

/* Installation and Removal */
register_activation_hook(__FILE__, 'racpp_redirect_install');
register_deactivation_hook(__FILE__, 'racpp_redirect_remove');

function racpp_redirect_install() {
    add_option("racpp_redirect_settings", array("enabled" => ''), '', 'yes');
}

function racpp_redirect_remove() {
    delete_option('racpp_redirect_settings');
}

/******************************************/
/* Meta box for post defined redirect url */
add_action( 'add_meta_boxes', 'racpp_meta_box_add' );  

/* define meta box */
function racpp_meta_box_add()  
{  
    add_meta_box( 'racpp-meta-box-id', 'Redirect after comment per page', 'racpp_meta_box', 'page', 'normal', 'high' );  
    add_meta_box( 'racpp-meta-box-id', 'Redirect after comment per page', 'racpp_meta_box', 'post', 'normal', 'high' );  
}  

/* Content of meta box */
function racpp_meta_box()  
{  
    global $post;
    $values = get_post_custom( $post->ID );  
    $url = isset( $values['racpp_meta_box_url'] ) ? esc_url( $values['racpp_meta_box_url'][0] ) : ""; 
    // We'll use this nonce field later on when saving.  
    wp_nonce_field( 'racpp_meta_box_nonce', 'meta_box_nonce' ); 

    _e('Please specify the url to which a user will be redirected after commenting (with http:// ).', 'redirect-after-comment-per-page');  
    ?>  
    <br /><label for="racpp_meta_box_text"><?php _e('URL', 'redirect-after-comment-per-page')?> :</label> 
    <input type="text" name="racpp_meta_box_url" id="racpp_meta_box_url" size="100" value="<?php echo $url; ?>"/>  
    <br />
    <?php 
    echo "<em>";
    _e('Make sure the URL is working first !', 'redirect-after-comment-per-page');
    echo "</em>";
}  

/* Save the content of meta box */
add_action( 'save_post', 'racpp_meta_box_save' );

function racpp_meta_box_save( $post_id )
{
	// Bail if we're doing an auto save
	if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	// if our nonce isn't there, or we can't verify it, bail
	if( !isset( $_POST['meta_box_nonce'] ) || !wp_verify_nonce( $_POST['meta_box_nonce'], 'racpp_meta_box_nonce' ) ) return;
	// if our current user can't edit this post, bail
	if( !current_user_can( 'edit_post' ) ) return;
	// Make sure your data is set before trying to save it
	if( isset( $_POST['racpp_meta_box_url'] ) )
		update_post_meta( $post_id, 'racpp_meta_box_url', esc_url_raw( $_POST['racpp_meta_box_url'] ) );
}

/*****************/
/* Redirect Code */
$settings = get_option('racpp_redirect_settings');
if ($settings['enabled'] != '') {
    add_action('comment_post_redirect', 'racpp_do_comment_redirect');
    add_action('comment_form', 'racpp_insert_referer');
}

function racpp_do_comment_redirect() {
    global $post;
    $values = get_post_custom( $post->ID );
    $url = (isset( $values['racpp_meta_box_url'] ) and $values['racpp_meta_box_url'][0] != "") ? esc_url( $values['racpp_meta_box_url'][0] ) : urldecode($_REQUEST["racpp_redirect_referrer"]);
    
    return $url;
}


function racpp_insert_referer() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
            || $_SERVER['SERVER_PORT'] == 443) {

        $racpp_protocol = 'https://';
    } else {
        $racpp_proto = 'http://';
    }
    echo '<input type="hidden" name="racpp_redirect_referrer" value="' .  $racpp_proto . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"] . '" />';
}

/************************/
/* Administration panel */
add_action('admin_menu', 'racpp_admin_menu');

function racpp_admin_menu() {
    add_options_page('Redirect after comment per page Options', 'Redirect after Comment', 'manage_options', 'Redirect_after_Comment', 'racpp_redirect_after_comment_admin_page');
}

function racpp_redirect_after_comment_admin_page() {
    $settings = get_option('racpp_redirect_settings');

    $result_notice = "";

    if (isset($_REQUEST['action'])) {
        // Defaults
        $update_array = array("enabled" => '');
        $do_update = TRUE;
        // Enable Plugin
        if (isset($_REQUEST['racpp_redirect_enabled'])) {
            $update_array['enabled'] = ' checked="checked" ';
            $settings['enabled'] = ' checked="checked" ';
        } else {
            $settings['enabled'] = '';
        }

        // Update Settings
        if ($do_update) {
            update_option('racpp_redirect_settings', $update_array);
            $notice_success = __("Settings successfully updated.", 'racpp-redirect-after-comment');
            $result_notice = '<div style="height: 20px; width: 500px; background-color: #FFFBCC; padding: 10px; font-weight: bold;" >' . $notice_success . '</div>';
        }
    }
    ?>
    <div class="wrap">
        <h2>Redirect After Comment Per Page</h2>
    <?php echo $result_notice; ?>
        <form method="post" action="" id="racpp-settings">
            <br />
            <input type="checkbox" name="racpp_redirect_enabled" value="checked" <?php echo $settings['enabled']; ?> /> <strong><?php _e("Enable Plugin ?", 'redirect-after-comment-per-page'); ?></strong><br /><br />


            <input type="hidden" name="action" value="update" />
            <input type="hidden" name="page_options" value="racpp_redirect_settings" />
            <p>
                <input type="submit" value="<?php _e('Save Changes', 'redirect-after-comment-per-page') ?>" />
            </p>
        </form>
            <h2><?php _e("Additional Usage Notes", 'redirect-after-comment-per-page'); ?></h2>
	    <p><?php _e("Instructions", 'redirect-after-comment-per-page'); ?> : <a href="http://www.anyideas.net/redirect-after-comment-per-page-wordpress-plugin/">http://www.anyideas.net/redirect-after-comment-per-page-wordpress-plugin/</a></p>
	    <p><?php _e("Instructions (Français)", 'redirect-after-comment-per-page'); ?> : <a href="http://www.anyideas.net/redirect-after-comment-per-page-wordpress-plugin-francais/">http://www.anyideas.net/redirect-after-comment-per-page-wordpress-plugin-francais/</a></p>
    </div>
    <?php
}
?>
