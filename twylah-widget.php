<?php
/**
 * Plugin Name: Twylah Widget
 * Plugin URI: http://wordpress.org/extend/plugins/twylah-widget/
 * Description: The best way to share your tweets directly from your website.
 * Version: 0.1a
 * Author: Xi-Lin Yeh
 * Author URI: http://www.twylah.com/
 * License: GPLv2 or later
 * Text Domain: twylah-widget
 */

/*
	Copyright 2012-current
	
	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	( at your option ) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

define( 'TWYLAH_WIDGET_VERSION', '0.1a' );

function TwylahWidgetEnqueAdminScripts() {
	wp_enqueue_script(
		'twylah-admin-script',
		plugins_url('/admin.js', __FILE__),
		array('jquery')
	);
}    
 
add_action('admin_enqueue_scripts', 'TwylahWidgetEnqueAdminScripts');

class Twylah_Widget extends WP_Widget {
	const Twylah_Widget_BaseId = "twylah_widget";
	const Twylah_Widget_UpdateInterval = 86400000; //Milliseconds in a day
	//Define possible layouts, "key" => "Name"
	private static $TwylahLayouts = array(
			"vertical" => "Vertical",
			"horizontal" => "Horizontal",
			"square" => "Square"
		);
	private static function TwylahGetUpdatedOptionName($username, $layout){
		return Twylah_Widget::Twylah_Widget_BaseId . "_" . $username ."_" . $layout . "_" . "lastUpdated";
	}
	private static function TwylahGetContentOptionName($username, $layout){
		return Twylah_Widget::Twylah_Widget_BaseId . "_" . $username ."_" . $layout . "_" . "content";
	}
	private static function TwylahGetData($url) {
		  $ch = curl_init();
		  $timeout = 5;
		  curl_setopt($ch, CURLOPT_URL, $url);
		  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		  $data = curl_exec($ch);
		  curl_close($ch);
		  return $data;
	}
	
	public static function TwylahValidateInstance($instance){
		//check to make sure everything submitted is ok
		if(!$instance['username']){
			return new WP_Error('twylah_missing_username', "Please enter a Twitter Username.");
		}
		if(!$instance['layout'] || !isset(Twylah_Widget::$TwylahLayouts[$instance['layout']])){
			return new WP_Error('twylah_invalid_layout', "Please select a valid Twylah Widget layout.");
		}
		
		
		//Pull in all views
		$refreshUpdate = Twylah_Widget::TwylahUpdateContent($instance['username'], $instance['caption'], "", true);
		if(is_wp_error($refreshUpdate)){
			return $refreshUpdate;
		}
		return true;
	}
	public static function TwylahUpdateContent($username, $widgetCaption = "", $layout = "", $force = false){
		
		$time = time();
		
		//If Layout specified update one layout
		if($layout && isset(Twylah_Widget::$TwylahLayouts[$layout])){
			$storedOptionName = Twylah_Widget::TwylahGetContentOptionName($username, $layout);
			$updatedOptionName = Twylah_Widget::TwylahGetUpdatedOptionName($username, $layout);
			$lastUpdated = intval(get_option($updatedOptionName, 0));
			//Update last time updated
			if(($lastUpdated + Twylah_Widget_UpdateInterval) < $time || $force){
				if(get_option($storedOptionName, null)) update_option($updatedOptionName, $time);
			

				$r = 'http://www.twylah.com/'.urlencode($username).'/widgets/trending_render?layout='.urlencode($layout).'&widget_caption='.urlencode($widgetCaption);
				try {
					$info = Twylah_Widget::TwylahGetData($r);
				} catch(Exception $e){
					
				}
				if($info) {
					//Attempt to use Json implementation
					$newJson = json_decode($info, true);
					//if not valid hack hack
					if(!$newJson || !isset($newJson["trending_widgets"])){
						preg_match('/"trending_widgets"\s*:\s*"([^"]*)"/', $info, $infoMatches);
						$newJson = json_decode("{ \"trending_widgets\":" . str_replace('\n', "", html_entity_decode($infoMatches[1]) . "}"), true);
					}
					
					if(isset($newJson["trending_widgets"])){
						update_option($updatedOptionName, $time);
						update_option($storedOptionName, $newJson["trending_widgets"]);
						return true;
					}
				} else {
					return new WP_Error('twylah_invalid_username', "Sorry, the Twitter username  \"". $username ."\" does not exist on Twylah. Check the username again or email us at <a href='mailto:info@twylah.com'>info@twylah.com</a> if you continue to have issues.");
				}
			}			
		//Otherwise Update all layouts
		} else {
			foreach( Twylah_Widget::$TwylahLayouts as $layoutType => $layoutName){
				$storedOptionName = Twylah_Widget::TwylahGetContentOptionName($username, $layoutType);
				$updatedOptionName = Twylah_Widget::TwylahGetUpdatedOptionName($username, $layoutType);
				
				$lastUpdated = intval(get_option($updatedOptionName, 0));
				//Update last time updated
				if(($lastUpdated + Twylah_Widget_UpdateInterval) < $time || $force){
					if(get_option($storedOptionName, null)) update_option($updatedOptionName, $time);
					
					$r = 'http://www.twylah.com/'.urlencode($username).'/widgets/trending_render?layout='.urlencode($layoutType).'&widget_caption='.urlencode($widgetCaption);
					try {
						$info = Twylah_Widget::TwylahGetData($r);
					} catch(Exception $e){
						
					}
					if($info) {
						//Attempt to use Json implementation
						$newJson = json_decode($info, true);
						//if not valid hack hack
						if(!$newJson || !isset($newJson["trending_widgets"])){
							preg_match('/"trending_widgets"\s*:\s*"([^"]*)"/', $info, $infoMatches);
							$newJson = json_decode("{ \"trending_widgets\":" . str_replace('\n', "", html_entity_decode($infoMatches[1]) . "}"), true);
						}
						if(isset($newJson["trending_widgets"])){
							update_option($updatedOptionName, $time);
							update_option($storedOptionName, $newJson["trending_widgets"]);
						}
					} else {
						return new WP_Error('twylah_invalid_username', "Sorry, the Twitter username  \"". $username ."\" does not exist on Twylah. Check the username again or email us at <a href='mailto:info@twylah.com'>info@twylah.com</a> if you continue to have issues.");
					}
				}
			}
			return true;
		}
		return false;
	}
	
	public function __construct() {
		parent::__construct(
	 		Twylah_Widget::Twylah_Widget_BaseId, // Base ID
			'Twylah Wordpress Widget', // Name
			array( 'description' => "Easily integrate the Twylah with Wordpress." ), // Args
			array( 'width' => 575)
		);
	}
	
	
	public function widget( $args, $instance ) {
		
		extract( $args );
		
		Twylah_Widget::TwylahUpdateContent($instance['username'], $instance['caption'], $instance['layout']);
		
		$title = apply_filters( 'widget_title', $instance['title'] );
		$caption = $instance['caption'];
		
		echo $before_widget;
		if (!empty($title))echo $before_title . $title . $after_title;
		echo get_option(Twylah_Widget::TwylahGetContentOptionName($instance['username'], $instance['layout']), "Unable to load Twylah widget.");
		
		?>
		<script type='text/javascript'>

		  var _gaq = _gaq || [];
		  _gaq.push(['_setAccount', 'UA-16514275-5']);
		  _gaq.push(['_setDomainName', 'none']);
		  _gaq.push(['_setAllowLinker', true]);
		  _gaq.push(['_trackPageview']);
		
		  (function() {
		    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
		    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
		    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
		  })();
		
		</script>
		<?php
			
		echo $after_widget;
	}
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$result = Twylah_Widget::TwylahValidateInstance($new_instance);
		
		if(is_wp_error($result)){
			$this->twylahSaveError = $result;
			$instance = $old_instance;
		} else {
			$instance['title'] = strip_tags( $new_instance['title'] );
			$instance['caption'] = strip_tags( $new_instance['caption'] );
			$instance['username'] = trim(strip_tags( $new_instance['username'] ));
			$instance['username'] = substr($instance['username'], 0, 1) === "@" ? substr($instance['username'],1) : $instance['username'];
			$instance['layout'] = $new_instance['layout'] ? $new_instance['layout'] : "vertical";
		}
		
		return $instance;
	}
	public function form( $instance ) {
		$title = isset( $instance[ 'title' ] ) ? $instance[ 'title' ] : "Twylah";
		$user = isset( $instance[ 'username' ] ) ? $instance[ 'username' ] : "";
		$caption = isset( $instance[ 'caption' ] ) ? $instance[ 'caption' ] : "";

		$layout = array(
					"square" => $instance["layout"] == "square" ? "checked" : "",
					"horizontal" => $instance["layout"] == "horizontal" ? "checked" : "",
					"vertical" => $instance["layout"] == "vertical" ? "checked" : ""
				  );
		?>
		
		<div class="twylah-widget-menu">
		
		<?php
		if(isset($this->twylahSaveError)) {
		?>
			<p style="color: #cc0000">
			<?php echo $this->twylahSaveError->get_error_message(); ?>
			</p>
		<?php } ?>

		<p>
			<label for="<?php echo $this->get_field_id( 'username' ); ?>"><?php _e( 'Twitter Username:' ); ?></label><br/>
			@<input class="widefat" id="<?php echo $this->get_field_id( 'username' ); ?>" name="<?php echo $this->get_field_name( 'username' ); ?>" type="text" value="<?php echo esc_attr( $user ); ?>" style="width:90%" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'caption' ); ?>"><?php _e( 'Widget Caption <em>(optional)</em>:' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'caption' ); ?>" name="<?php echo $this->get_field_name( 'caption' ); ?>" type="text" value="<?php echo esc_attr( $caption ); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'layout' ); ?>"><?php _e( 'Choose a Layout:' ); ?></label> <br/>
			<input type="radio" id="<?php echo $this->get_field_id( 'layout' ); ?>-vertical" name="<?php echo $this->get_field_name( 'layout' ); ?>" value="vertical" <?php echo $layout["vertical"]; ?> onchange="TwylahWidgetMenuPreviewUpdate(this)"/> Vertical
			&nbsp;
			<input type="radio" id="<?php echo $this->get_field_id( 'layout' ); ?>-horizontal" name="<?php echo $this->get_field_name( 'layout' ); ?>" value="horizontal" <?php echo $layout["horizontal"]; ?> onchange="TwylahWidgetMenuPreviewUpdate(this)"/> Horizontal
			&nbsp;
			<input type="radio" id="<?php echo $this->get_field_id( 'layout' ); ?>-square" name="<?php echo $this->get_field_name( 'layout' ); ?>" value="square" <?php echo $layout["square"]; ?> onchange="TwylahWidgetMenuPreviewUpdate(this)"/> Square
		</p>
		<?php if($user && get_option(Twylah_Widget::TwylahGetContentOptionName($user, $instance['layout']), "")) { ?>
		<div class="twylah-widget-menu-previews">
			<?php foreach( Twylah_Widget::$TwylahLayouts as $layoutType => $layoutName){ ?>
				<div id="<?php echo $this->get_field_id( 'layout' ); ?>-<?php echo $layoutType; ?>-preview" class="twylah-widget-preview" style="display:<?php if ($layout[$layoutType]) echo "block"; else echo "none"; ?>;">
					<?php echo get_option(Twylah_Widget::TwylahGetContentOptionName($user, $layoutType)); ?> 
				</div>
				<div style="clear:both"></div>
			<?php } ?>
		</div>
		<?php } ?>
		
		<br/>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title <em>(optional)</em>:' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		</div>
		<?php 
	}

}

add_action( 'widgets_init', create_function( '', 'register_widget( "twylah_widget" );' ) );