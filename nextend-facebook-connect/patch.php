<?php 


function nextend_enqueue_scripts() {

	wp_register_script('nextend-scripts', plugins_url( '/js/scripts.js' , __FILE__ ), array('jquery'), '', true);

	wp_enqueue_script( 'nextend-scripts' );

}
add_action('wp_enqueue_scripts', 'nextend_enqueue_scripts');


function new_fb_login_patch() {

  if ( isset( $_REQUEST[ 'loginFacebook_v2' ] ) && $_REQUEST[ 'loginFacebook_v2' ] == '1' ) {
    new_fb_login_action_patch();
  }

}
add_action('login_init', 'new_fb_login_patch');

function nextend_login_message( $message ) {

	if ( isset( $_REQUEST[ 'loginFacebook_v2_no_email'] ) && $_REQUEST[ 'loginFacebook_v2_no_email' ] == '1' ) {
		return '<div id="login_error"><strong>ERROR: </strong>Can\'t get email address from Facebook.</div>'; 
	} else {
        return $message;
    }

}
add_filter( 'login_message', 'nextend_login_message' );


function new_fb_login_action_patch() {

	global $wp, $wpdb, $new_fb_settings;

	// Get data
	$fb_user_id = $_REQUEST[ 'fb_user_id' ];
	$access_token = $_REQUEST[ 'nextend_fb_access_token' ];
	$email = $_REQUEST[ 'email' ];
	$first_name = $_REQUEST[ 'first_name' ];
	$last_name = $_REQUEST[ 'last_name' ];
	$name = $_REQUEST[ 'name' ];

	$ID = $wpdb->get_var($wpdb->prepare('SELECT ID FROM ' . $wpdb->prefix . 'social_users WHERE type = "fb" AND identifier = "%s"', $fb_user_id));

	if (!get_user_by('id', $ID)) {
    	$wpdb->query($wpdb->prepare('DELETE FROM ' . $wpdb->prefix . 'social_users WHERE ID = "%s"', $ID));
        $ID = null;
    }

    if ( ! is_user_logged_in() ) {

    	if ( $ID == NULL ) { // Register

          	if ( ! isset( $email ) ) {

          		// Not provide email address with Facebook connect, can't process registration.
          		wp_safe_redirect( site_url( 'wp-login.php' ) . '?loginFacebook_v2_no_email=1' );

          	}

          	$ID = email_exists( $email );

          	if ( $ID == false ) { // Real register

	            require_once (ABSPATH . WPINC . '/registration.php');
    	       	$random_password = wp_generate_password($length = 12, $include_standard_special_chars = false);

        	    if ( ! isset( $new_fb_settings[ 'fb_user_prefix' ] ) ) $new_fb_settings[ 'fb_user_prefix' ] = 'facebook-';
            
	           	$username = strtolower( $first_name . $last_name );
    	        $sanitized_user_login = sanitize_user( $new_fb_settings['fb_user_prefix'] . $username );

        	    if ( ! validate_username( $sanitized_user_login ) ) {
            	  	$sanitized_user_login = sanitize_user('facebook' . $fb_user_id);
            	}

	            $defaul_user_name = $sanitized_user_login;
    	       	$i = 1;
        	    while ( username_exists( $sanitized_user_login ) ) {
            	  	$sanitized_user_login = $defaul_user_name . $i;
              		$i++;
	            }

    	        $ID = wp_create_user($sanitized_user_login, $random_password, $email);

        	    if (!is_wp_error($ID)) {
              		wp_new_user_notification($ID, $random_password);
            	  	$user_info = get_userdata($ID);
	              	wp_update_user(array(
    	            	'ID' => $ID,
        	        	'display_name' => $name,
            	    	'first_name' => $first_name,
                		'last_name' => $last_name
              		));

              		//update_user_meta( $ID, 'new_fb_default_password', $user_info->user_pass);
	              	//do_action('nextend_fb_user_registered', $ID, $user_profile, $facebook);
    	        } else {
        	      	return;
            	}
          	}

	        if ($ID) {
    	        $wpdb->insert($wpdb->prefix . 'social_users', array(
        	    	'ID' => $ID,
            	  	'type' => 'fb',
              		'identifier' => $fb_user_id
	            ) , array(
    	          	'%d',
        	      	'%s',
            	  	'%s'
            	));
	        }

    	    if (isset($new_fb_settings['fb_redirect_reg']) && $new_fb_settings['fb_redirect_reg'] != '' && $new_fb_settings['fb_redirect_reg'] != 'auto') {
        	    set_site_transient( nextend_uniqid().'_fb_r', $new_fb_settings['fb_redirect_reg'], 3600);
          	}

    	}

	    if ( $ID ) { // Login

    		$secure_cookie = is_ssl();
        	$secure_cookie = apply_filters('secure_signon_cookie', $secure_cookie, array());
          	global $auth_secure_cookie; // XXX ugly hack to pass this to wp_authenticate_cookie

	        $auth_secure_cookie = $secure_cookie;
    	    wp_set_auth_cookie($ID, true, $secure_cookie);
      	  	$user_info = get_userdata($ID);
       		update_user_meta($ID, 'fb_profile_picture', 'https://graph.facebook.com/' . $fb_user_id . '/picture?type=large');
	       	do_action('wp_login', $user_info->user_login, $user_info);
    	   	update_user_meta($ID, 'fb_user_access_token', $access_token);
	    	//do_action('nextend_fb_user_logged_in', $ID, $user_profile, $facebook);

    	}

    } else {

	    $current_user = wp_get_current_user();
    	if ($current_user->ID == $ID) {

	        // It was a simple login
          
    	} elseif ($ID === NULL) { // Let's connect the accout to the current user!

        	$wpdb->insert($wpdb->prefix . 'social_users', array(
	           	'ID' => $current_user->ID,
    	        'type' => 'fb',
        	    'identifier' => $fb_user_id
          	) , array(
	           	'%d',
    	       	'%s',
        	    '%s'
          	));

	        update_user_meta($current_user->ID, 'fb_user_access_token', $access_token );
    	    //do_action('nextend_fb_user_account_linked', $ID, $user_profile, $facebook);

        	$user_info = wp_get_current_user();
          	set_site_transient($user_info->ID.'_new_fb_admin_notice',__('Your Facebook profile is successfully linked with your account. Now you can sign in with Facebook easily.', 'nextend-facebook-connect'), 3600);

	    } else {

    	    $user_info = wp_get_current_user();
        	set_site_transient($user_info->ID.'_new_fb_admin_notice',__('This Facebook profile is already linked with other account. Linking process failed!', 'nextend-facebook-connect'), 3600);

        }

    }

    new_fb_redirect();
    exit;

}