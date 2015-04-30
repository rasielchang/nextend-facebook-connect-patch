
// Need Facebook Javascript sdk loadded
function nextend_fb_login( redirect ) {

	FB.login(function(response){
    	var accessToken = response.authResponse.accessToken;
    	FB.api('/me', function(response) {
    		window.location = '/wp-login.php?fb_user_id=' + response.id + '&nextend_fb_access_token=' + accessToken + '&loginFacebook_v2=1&email=' + response.email + '&first_name=' + response.first_name + '&last_name=' + response.last_name + '&name=' + response.name + '&redirect=' + redirect;
    	});

    }, {scope: 'public_profile,email'});
}