<?php
/*
Plugin Name: WP Manager
Plugin Script: wpmanager.php
Plugin URI: http://marto.lazarov.org/plugins/wpmanager
Description: WP Manager extends basic functionalities of wordpress XMLPRC required for <a href="https://wpmanager.biz/">wpmanager.biz</a>
Version: 1.0.4.1
Author: mlazarov
Author URI: http://marto.lazarov.org/
*/

function define_wpmanager_xmlrpc_class(){

	class WPManager_XMLRPC extends wp_xmlrpc_server {
		public function __construct() {
	        parent::__construct();

	        global $wp_version;

	        if (version_compare($wp_version, '3.4.0', '<=')) {
	        	// Unsupported wordpress version
	        	// TODO: add warning message
	        	return;
	        }

			$methods = array(
				'wpm.getPostsCount'		=>	'this:wpm_getPostsCount',
				'wpm.getCommentsCount'	=>	'this:wpm_getCommentsCount',
				'wpm.getPlugins'		=>	'this:wpm_getPlugins',
				'wpm.getThemes'			=>	'this:wpm_getThemes',
				'wpm.getSystemInfo'		=>	'this:wpm_getSystemInfo',
				'wpm.getTest'			=>	'this:wpm_getTest',
	        );

	        $this->methods = array_merge($this->methods, $methods);
	    }
		function wpm_getName(){
			 return __CLASS__;
		}

		function wpm_getPlugins($args){
			$this->escape( $args );
			if ( ! $user = $this->_checkLogin($args) )
				return $this->error;
			if ( ! current_user_can('update_plugins')){
				return(new IXR_Error(401, __('Sorry, you cannot manage this blog [1].')));
			}

			$data = array('all'=>get_plugins(),'active'=>get_option('active_plugins'));

			$data['updates'] = get_site_transient( 'update_core' );

			return $data;

		}

		function wpm_getThemes($args){
			$this->escape( $args );
			if ( ! $user = $this->_checkLogin($args) )
				return $this->error;
			if ( ! current_user_can('update_themes')){
				return(new IXR_Error(401, __('Sorry, you cannot manage this blog [2].')));
			}
			return wp_get_themes();
		}

		function wpm_getPostsCount($args){
			$this->escape( $args );
			if ( ! $user = $this->_checkLogin($args) )
				return $this->error;
			if ( ! current_user_can('edit_others_posts')){
				return(new IXR_Error(401, __('Sorry, you cannot manage this blog [3].')));
			}

			return wp_count_posts();
		}

		function wpm_getCommentsCount($args){
			$this->escape( $args );
			if ( ! $user = $this->_checkLogin($args) )
				return $this->error;
			if ( ! current_user_can('moderate_comments')){
				return(new IXR_Error(401, __('Sorry, you cannot manage this blog [4].')));
			}

			return wp_count_comments();
		}

		function wpm_getSystemInfo($args){
			$this->escape( $args );
			if ( ! $user = $this->_checkLogin($args) )
				return $this->error;
			if ( ! current_user_can('update_core')){
				return(new IXR_Error(401, __('Sorry, you cannot manage this blog [5].')));
			}

			$data = array();
			$data['loadavg'] = explode(' ',@file_get_contents('/proc/loadavg'));
			$data['meminfo'] = file_get_contents('/proc/meminfo');
			$data['diskinfo']['total'] = @disk_total_space(__DIR__);
			$data['diskinfo']['free'] = @disk_free_space(__DIR__);
			$data['cpus'] = `grep -c processor /proc/cpuinfo`;

			return $data;
		}
		function wpm_updateCore($args){
			$this->escape( $args );
			if ( ! $user = $this->_checkLogin($args) )
				return $this->error;
			if ( ! current_user_can('update_core')){
				return(new IXR_Error(401, __('Sorry, you cannot manage this blog [6].')));
			}
			$return = array('error'=>false);


			if(!class_exists('Core_Upgrader'))
				return(new IXR_Error(501, __('Sorry, core update not supported [3].')));

			$wpm_skin = new WPM_Core_Upgrader_Skin();
			$wpm_upgrader = new Core_Upgrader($wpm_skin);


			$updates = get_core_updates();
			$update = find_core_update($updates[0]->current, $updates[0]->locale);

			// Do the upgrade
			ob_start();
			$result = $wpm_upgrader->upgrade($update);
			$data = ob_get_contents();
			ob_clean();

			if($wpm_skin->error){
				$return['errorn'] = 501;
				$return['message'] = $skin->upgrader->strings[$wpm_skin->error];
				return $return;
			}
			if(!$result){
				$return['errorn'] = 501;
				$return['message'] = "Core Update Failder for unknow reason [1]";
				return $return;
			}

			if(is_wp_error($result)){
				$return['errorn'] = $result->get_error_code();
				$return['message'] = $result->get_error_message();
				return $return;

			}
			$return['data'] = $data;
			$return['message'] = "Upgrade complete. No errors detected";
			return $return;
		}

//		function wpm_getTest($args){
//			return get_plugin_updates();
//			//return get_declared_classes();
//			return get_included_files();
//		}

		function _checkLogin($args){
			$blog_id    = (int) $args[0];
			$username   = $args[1];
			$password   = $args[2];

			if ( ! $user = $this->login( $username, $password ) )
				return false;
			else
				return $user;

		}
	}

	if(!file_exists(ABSPATH.'wp-admin/includes/class-wp-upgrader.php'))
		return(new IXR_Error(501, __('Sorry, core update not supported [1].')));

	require_once(ABSPATH.'wp-admin/includes/class-wp-upgrader.php');

	if(!file_exists(ABSPATH . 'wp-admin/includes/admin.php'))
		return(new IXR_Error(501, __('Sorry, core update not supported [2].')));

	include_once(ABSPATH . 'wp-admin/includes/admin.php');

	class WPM_Core_Upgrader_Skin extends WP_Upgrader_Skin {
		var $feedback;
		var $error;

		function error($error) {
			$this->error = $error;
		}
		function feedback($feedback){
			$this->feedback = $feedback;
		}
		function before() {}
		function after() {}
		function header() {}
		function footer() {}
	}



	return 'WPManager_XMLRPC';

}

add_filter('wp_xmlrpc_server_class', 'define_wpmanager_xmlrpc_class', 'wpm_getName');


function wpmanager_blog_options($blog_info){

	$plugin_data = get_plugin_data(__FILE__);
	$plugin_version = $plugin_data['Version'];

	$blog_info['wpmanager'] = array();
	$blog_info['wpmanager']['version'] = $plugin_version;
    return $blog_info;
}

add_action('xmlrpc_blog_options', 'wpmanager_blog_options');

?>
