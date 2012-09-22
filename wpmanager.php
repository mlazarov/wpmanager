<?php
/*
Plugin Name: WP Manager
Plugin Script: wpmanager.php
Plugin URI: http://marto.lazarov.org/plugins/wpmanager
Description: WP Manager extends basic functionalities of wordpress XMLPRC required for wpmanager.biz
Version: 1.0.2
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
