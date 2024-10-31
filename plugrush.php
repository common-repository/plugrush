<?php
/*
Plugin Name: PlugRush
Plugin URI: http://wordpress.org/extend/plugins/plugrush/
Description: A plugin to use PlugRush on your wordpress enabled site. Easily add PlugRush widgets to your sidebar or theme, enable popunders or mobile redirects.
Version: 1.30
Author: PlugRush.com
Author URI: //www.plugrush.com
License: GPL2
*/

if(isset($_REQUEST['pr_api'])){
	$settings = get_option('plugrush-settings');
	$plugrush = new Plugrush();
	$plugrush->init($settings);
	if(!empty($_REQUEST["action"])&&$_REQUEST["action"]=="update"){
		$plugrush->update();
	}elseif(isset($_REQUEST["action"])&&$_REQUEST["action"]=="status"){
		$plugrush->status();
	}
	exit();
}

add_action('wp_head','plugrush_head');
add_theme_support('post-thumbnails'); 

if(is_admin()){
	add_action('admin_menu','plugrush_menu');
	add_action('admin_init','plugrush_settings');
	register_activation_hook('plugrush/plugrush.php','plugrush_activate');
	$plugin = plugin_basename(__FILE__);
	add_filter("plugin_action_links_$plugin",'plugrush_settings_link');
	add_action('post_submitbox_misc_actions','plugrush_publish_box');
	add_filter('manage_edit-post_columns','plugrush_post_header_columns',10,1);
	add_action('manage_posts_custom_column','plugrush_post_data_row',10,2);
	add_action('save_post','plugrush_post',10,2);
	add_action('add_meta_boxes','plugrush_add_box');
	add_action('wp_dashboard_setup','plugrush_dashboard_widgets'); 
}

function plugrush_earnings(){
	$plugrush = get_option('plugrush-settings');
	$earnings = plugrush_request(array('action'=>'earnings','start'=>date('Y-m-d',strtotime('-14 days')),'user'=>$plugrush['user'],'api_key'=>$plugrush['api_key']));
	if(!empty($earnings['data'])){ $earnings['data'] = array_reverse($earnings['data']); ?>
    <div id="chartcontainer" style="height: 300px"></div>​
    <script type="text/javascript" src="//static.plugrush.com/js/highcharts/js/highcharts.js"></script>
    <script type="text/javascript">	
		var earnings;jQuery(document).ready(function(){var options={chart:{renderTo:'chartcontainer',backgroundColor:'rgba(255,255,255,0.1)',},exporting:{enabled:false},title:{text:false},subtitle:{text:false},credits:{enabled:false},legend:{enabled:false},xAxis:{labels:{}},yAxis:[{title:{text:'USD'},labels:{formatter:function(){return(this.value<10000?this.value:(this.value/1000)+'K')},style:{color:'#4572A7'}},min:0}],series:[{name:'Earnings',color:'#4572A7',type:'column'}]}
		s0 = [];s1 = [];<?php $data = array_reverse($earnings['data']); foreach($data as $row){ ?>s0.push(<?= $row['amount']; ?>);s1.push('<?= date('M jS',strtotime($row['date'])); ?>');<?php } ?>options.series[0].data = s0;options.xAxis.categories=s1;earnings=new Highcharts.Chart(options);});
    </script>
    <?php }else{
		echo "Nothing to show here yet";
	} ?>
    <div style="text-align:right"><a href="//www.plugrush.com/publishers/earnings">Full stats at plugrush.com</a></div>
    <?php
}

function plugrush_dashboard_widgets(){
	wp_add_dashboard_widget('plugrush_earnings','PlugRush earnings','plugrush_earnings');	
}

function plugrush_add_box(){
    add_meta_box('plugrush_box',__( 'PlugRush','plugrush_textdomain'),'plugrush_box','post','side','high');
}

function plugrush_error($key,$error){
	$errors = array();
	$errors['descr'] = 'Description is too short. Use the Excerpt field to make a description with at least 10 characters';
	$errors['thumbnail'] = 'No valid thumbnail was submitted. Please set a featured image for your post';
	if(isset($errors[$key])){
		return $errors[$key];
	}
	return $error;
}

function plugrush_box($post){
	$plugrush = get_option('plugrush-settings');
	$categories = plugrush_request(array('action'=>'category/list','user'=>$plugrush['user'],'api_key'=>$plugrush['api_key']));
	$categories_gallery = plugrush_request(array('action'=>'category/list','user'=>$plugrush['user'],'api_key'=>$plugrush['api_key'],'type'=>'gallery'));
    wp_nonce_field(plugin_basename( __FILE__ ),'plugrush_noncename');
	$plugrush_post = get_post_meta($post->ID,'_plugrush',TRUE);
    if (isset($plugrush_post['categories'])) {
        $plugrush_post['categories'] = explode(',',$plugrush_post['categories']);
    } else {
        $plugrush_post['categories'] = array();
    }
	?>
		<div style="padding-top:5px;">
        <?php if(!empty($plugrush_post['message'])){ ?>
        	<div class="<?=$plugrush_post['posted']==1?'updated':'error';?>"><p>
            <strong>PlugRush:</strong> <?=$plugrush_post['message'];?>
            <?php if(!empty($plugrush_post['errors'])){ ?>
            <ol>
            	<?php foreach($plugrush_post['errors'] as $key => $error){ ?>
                	<li><?=plugrush_error($key,$error);?></li>
                <?php } ?>
            </ol>
            <?php } ?>
            </p></div>
        <?php } ?>
		</div>
        <div id="plugrush-plug-type" style="padding:5px 0 0 0;">
        	<label>Post type</label>
            <select id="pr_type_select" name="_plugrush_type">
            	<option<?= !empty($plugrush_post['type'])&&$plugrush_post['type']==1?' selected="selected"':'';?> value="1">Regular Plug</option>
            	<option<?= !empty($plugrush_post['type'])&&$plugrush_post['type']==2?' selected="selected"':'';?> value="2">Gallery Plug (2:3 thumb)</option>
            </select>
        </div>
        <div class="categorydiv" id="pr_cats">
            <?php if(!empty($categories['data'])){ ?>
                <ul class="category-tabs" id="plugrush-category-tabs">
                    <li class="tabs"><a tabindex="3" href="#plugrush-category-all">Categories</a></li>
                </ul>                
                <div class="tabs-panel" id="pr_categories">
                	<?php $selected = get_post_meta($post->ID,'_plugrush_category',TRUE); print_r($selected); ?>
                    <ul class="list:category categorychecklist form-no-clear" id="plugrushcategorychecklist">
                        <?php foreach($categories['data'] as $key => $val){ ?>
                            <li id="plugrush_category-<?=$key;?>">
                                <label>
                                    <input id="pr-category-<?=$key;?>" type="checkbox" name="_plugrush_category[]"<?= is_array($plugrush_post['categories'])&&in_array($key,$plugrush_post['categories'])?' checked="checked"':'';?> value="<?=$key;?>" />
                                    <?=$val;?>
                                    </label>
                                </label>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
                <div class="tabs-panel" id="pr_categories_gallery" style="display:none;">
                    <ul class="list:category_gallery categorychecklist form-no-clear" id="plugrushcategorygallerychecklist">
                        <?php foreach($categories_gallery['data'] as $key => $val){ ?>
                            <li id="plugrush_category_gallery-<?=$key;?>">
                                <label>
                                    <input id="prg-category-<?=$key;?>" type="checkbox" name="_plugrush_category_gallery[]"<?= is_array($plugrush_post['categories'])&&in_array($key,$plugrush_post['categories'])?' checked="checked"':'';?> value="<?=$key;?>" />
                                    <?=$val;?>
                                    </label>
                                </label>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
                <script type="text/javascript">
					jQuery('#pr_type_select').change(function(){
						if(jQuery(this).val()==1){
							jQuery('#pr_categories').fadeIn(200);	
							jQuery('#pr_categories_gallery').hide();	
						}else{
							jQuery('#pr_categories_gallery').fadeIn(200);	
							jQuery('#pr_categories').hide();	
						}
					});
				</script>
            <?php }else{ ?>
                Error: Check your PlugRush settings
            <?php } ?>
        </div>
	<?php
}

// Add settings link on plugin page
function plugrush_settings_link($links){
	$settings_link = '<a href="admin.php?page=plugrush/plugrush.php">Settings</a>';
	array_unshift($links,$settings_link);
	return $links;
}

function plugrush_head(){
	$plugrush = get_option('plugrush-settings');
	echo htmlspecialchars_decode($plugrush['integration_library']);
}

function plugrush_activate(){
	add_action('admin_notices','my_admin_notice');
}

function my_admin_notice(){
    echo '<div class="updated"><p>PlugRush has been activated. Go to the PlugRush settings page in the admin menu to configure your settings.</p></div>';
}

function plugrush_menu(){
	add_menu_page('PlugRush Settings','PlugRush','administrator',__FILE__,'plugrush_settings_page',plugins_url('/logo16.png' ,__FILE__ ));
}

function plugrush_settings(){
	register_setting('plugrush-settings','plugrush-settings','plugrush_save');
	add_settings_field('user','Plugrush Email','plugrush_field','plugrush_settings_page');
	add_settings_field('api_key','Plugrush API Key','plugrush_field','plugrush_settings_page');
	add_settings_field('popunder_status','Popunder Status','plugrush_field','plugrush_settings_page');
	add_settings_field('popunder_alturl','Popunder Alternative URL','plugrush_field','plugrush_settings_page');
	add_settings_field('mobile_status','Mobile Status','plugrush_field','plugrush_settings_page');
	add_settings_field('mobile_alturl','Mobile Alternative URL','plugrush_field','plugrush_settings_page');
	add_settings_field('autopost','Autopost to Plugrush','plugrush_field','plugrush_settings_page');
	add_settings_field('integration_library','Integration Library','plugrush_field','plugrush_settings_page');
}

function plugrush_field(){
}

function plugrush_post($post_id,$post){
	require_once(ABSPATH.'wp-admin/includes/media.php');
	require_once(ABSPATH.'wp-includes/post.php');
	if(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE){
		return;
	}
	$plugrush = get_option('plugrush-settings');
	/* Verify the nonce before proceeding. */
	#if ( !isset( $_POST['smashing_post_class_nonce'] ) || !wp_verify_nonce( $_POST['smashing_post_class_nonce'],basename( __FILE__ ) ) )
	#	return $post_id;
	/* Get the post type object. */
	$real_post_id = wp_is_post_revision($post_id);
	if(!empty($real_post_id)){
		$post_id = $real_post_id;
	}
	$thumb = wp_get_attachment_image_src(get_post_thumbnail_id($post_id),'full');
	if(!empty($_POST['_plugrush_post'])){
		$post_type = get_post_type_object($post->post_type);
		$plug = array();
		$plug['title'] = $_POST['post_title'];
		$plug['description'] = !empty($_POST['excerpt'])?$_POST['excerpt']:$_POST['post_title'];
		$plug['type'] = $_POST['_plugrush_type'];
		$plug['link'] = get_permalink($post_id);
		$plug['thumbnail'] = $thumb[0];
		if($plug['type']==1){
			if(is_array($_POST['_plugrush_category'])){
				$plug['categories'] = implode(',',$_POST['_plugrush_category']);
			}
		}else{
			if(is_array($_POST['_plugrush_category_gallery'])){
				$plug['categories'] = implode(',',$_POST['_plugrush_category_gallery']);
			}
		}
		$params = array('action'=>'post/save','user'=>$plugrush['user'],'api_key'=>$plugrush['api_key']);
		$params = array_merge($params,$plug);
		/*$path = '?';
		foreach($params as $k => $v){
			$path .= $k.'='.urlencode($v).'&';
		}
		$path = rtrim($path,'&');*/
		$status = plugrush_request($params);
		if($status['status']=='200'){
			$plug['posted'] = 1;	
			$plug['message'] = $status['message'];
			$plug['errors'] = FALSE;
		}else{
			$plug['posted'] = 0;
			$plug['message'] = $status['message'];
			$plug['errors'] = isset($status['errors'])?$status['errors']:FALSE;
		}
		$plug['autopost'] = '1';
		update_post_meta($post_id,'_plugrush',$plug);
	}else{
		$plug = get_post_meta($post_id,'_plugrush',TRUE);
		$plug['message'] = FALSE;
		$plug['errors'] = FALSE;
        if (!array_key_exists('autopost', $plug)) {
            $plug['autopost'] = (string)$plugrush['autopost'];
        } else {
		    $plug['autopost'] = '0';
        }
		update_post_meta($post_id,'_plugrush',$plug);
	}
}

function plugrush_messages($messages){
  	global $post,$post_ID;
  	print_r($messages);
	die();
	return $messages;
}

function plugrush_save($params){
	if(!empty($params['user']) && !empty($params['api_key'])){
		$status = plugrush_request(array('action'=>'verify','user'=>$params['user'],'api_key'=>$params['api_key']));
	}
	if($status['status']=='200'){
		$params['action'] = 'adzone/update';
		$response = plugrush_request($params);
		if($response['status']=='200'){
			if(isset($response['data']['integration_library'])){
				$params['integration_library'] = $response['data']['integration_library'];
				wp_cache_flush();
			}
		}else{
			add_settings_error('plugrush-settings','plugrush','Failed to update settings: '.$response['message']);
			return (array)get_option('plugrush-settings');	
		}
		return $params;
	}else{
		return array('user'=>$params['user'],'api_key'=>$params['api_key']);	
	}
}

function plugrush_request($params){
	$params['format'] = 'json';
	$params['domain'] = plugrush_domain_from_url(get_option('siteurl'));
	if(function_exists('curl_init')){
		$curl = curl_init();
		curl_setopt($curl,CURLOPT_HEADER,TRUE);
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,TRUE);
		curl_setopt($curl,CURLOPT_POST,TRUE);
		curl_setopt($curl,CURLOPT_URL,'http://www.plugrush.com/api/');
		curl_setopt($curl,CURLOPT_POSTFIELDS,$params);
		$result = $headers = curl_exec($curl);
		$result = explode("\r\n\r\n",$result,3);
		$result = (array)json_decode($result[2],TRUE);
		if(preg_match('/x-plugrush-status: ([0-9]{3})/i',$headers,$match)){
			$result['status'] = $match[1];
		}
		return $result;
	}else{
		return array('status'=>'400','message'=>'CURL is required for this plugin to work');	
	}
}

function plugrush_domain_from_url($url,$returnTopDomain=FALSE){
	preg_match('_^(?:([^:/?#]+):)?(?://([^/?#]*))?'.'([^?#]*)(?:\?([^#]*))?(?:#(.*))?$_',$url,$uri_parts);
	$domain = $uri_parts[2];
	$exp = explode('.',$domain);
	if($exp[0]=='www'){
		$domain = substr($domain,4);
	}
	if($returnTopDomain){
		$exp = explode('.',$domain);
		$nr = count($exp);
		switch($nr){
			case 4:
				$domain = $exp[1].'.'.$exp[2].'.'.$exp[3];
			break;
			case 3:
				if(strlen($exp[1])>3) $domain = $exp[1].'.'.$exp[2];
				else $domain = $exp[0].'.'.$exp[1].'.'.$exp[2];
			break;
			default:
				$domain = $domain;
			break;
		}
	}
	return strtolower($domain);
}

function plugrush_settings_page(){
	$plugrush = get_option('plugrush-settings');
	if(!empty($plugrush['user'])&&!empty($plugrush['api_key'])){
		$status = plugrush_request(array('action'=>'verify','user'=>$plugrush['user'],'api_key'=>$plugrush['api_key']));
	}else{
		$status = array('status'=>'400','message'=>'Please specify a user and an API key to continue');	
	}
	if($status['status']=='200'){
		$data = plugrush_request(array('action'=>'adzone/update','user'=>$plugrush['user'],'api_key'=>$plugrush['api_key'],'domain'=>plugrush_domain_from_url(get_option('siteurl'))));
		if(!empty($data['data'])){
			$plugrush = array_merge($plugrush,$data['data']);
		}else{
			$status = array('status'=>'401','message'=>'API key is correct but domain is not in your plugrush account.');
		}
	}
?>
<div class="wrap">
    <div class="icon32" style="background-image:url('<?=plugins_url('/logo32.png' ,__FILE__ );?>');background-repeat:no-repeat;">
      <br>
    </div>

    <h2>PlugRush Settings</h2>
    <?php 
	if ( $_REQUEST['settings-updated'] ){
		$errors = '';
		foreach(get_settings_errors() as $message){
			if($message['type']=='error'){
				$errors .= '<div class="error fade">'.$message['message'].'</div>';
			}
		}
		if($errors){
			echo $errors;
		} else{
			echo '<div id="message" class="updated fade"><p><strong>settings saved.</strong></p></div>';
		}
	}
    ?>
	<hr>
	<br>
	<h3 class="title">Credentials</h3>
    <form action="options.php" method="post">
    	<?php settings_fields( 'plugrush-settings' ); ?>
    	<?php do_settings_fields( 'plugrush-settings','' ); ?>
    	<input type="hidden" name="plugrush-settings[integration_library]" value="<?=htmlspecialchars($plugrush['integration_library']);?>" />
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row"><label for="plugrush-user">&raquo; PlugRush Email</label></th>
					<td><input type="text" placeholder="your@email.com" class="regular-text" value="<?=$plugrush['user'];?>" id="plugrush-user" name="plugrush-settings[user]" style="width:320px;">
					<p class="description">The email you used when signing up to <a target="_blank" href="//www.plugrush.com/">www.plugrush.com</a></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="plugrush-api_key">&raquo; PlugRush API Key</label></th>
					<td>
					<input type="text" placeholder="your_api_key" class="regular-text" value="<?=$plugrush['api_key'];?>"
					id="plugrush-api_key" name="plugrush-settings[api_key]" style="width:320px;">
					<p class="description">Find this key by logging into plugrush and go to <a target="_blank" href="//www.plugrush.com/account/api">API Settings</a></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="plugrush-api_status">&raquo; API Status</label></th>
					<td>
					<p class="description" style="color:#<?=$status['status']=='200'?'090':'C30';?>"><?=$status['message'];?></p>
					</td>
				</tr>
			</tbody>
		</table>
      	<?php if($status['status']=='200'){ ?>
	  	<hr>
	  	<br>
      	<h3 class="title">Popunders</h3>
      	<p>Enabling popunders will let you make money by having paid advertising campaigns open in a new tab/window.</p>
        <table class="form-table">
            <tbody>
                <tr valign="top">
                    <th scope="row">Status</th>
                    <td>
                        <fieldset>
                        	<legend class="screen-reader-text"><span>&raquo; Status</span></legend>
                        	<label><input type="radio"<?= $plugrush['popunder_status']==1?' checked="checked"':'';?> value="1" name="plugrush-settings[popunder_status]"> Enabled</label><br>
                        	<label><input type="radio"<?= $plugrush['popunder_status']==0?' checked="checked"':'';?> value="0" name="plugrush-settings[popunder_status]"> Disabled</label>
                            <br>
                        </fieldset>
                    </td>
                </tr>
                <tr valign="top">
                	<th scope="row"><label for="plugrush-popunder_alturl">&raquo; Alternative URL</label></th>
                	<td>
                    	<input type="text" placeholder="http://www.alternativeurl.com/" class="regular-text" value="<?=$plugrush['popunder_alturl'];?>" id="plugrush-popunder_alturl" style="width:320px;" name="plugrush-settings[popunder_alturl]">
                  		<p class="description">A URL you want to pop when there are no available campaigns in PlugRush</p>
                	</td>
              	</tr>
            </tbody>
        </table>
	  	<hr>
	  	<br>
      	<h3 class="title">Mobile Redirects</h3>
      	<p>These will redirect users who are visiting your site on a mobile device to paid advertising campaigns,and make you money. A good option if your site is not mobile compatible.</p>
        <table class="form-table">
            <tbody>
                <tr valign="top">
                    <th scope="row">Status</th>
                    <td>
                        <fieldset>
                        	<legend class="screen-reader-text"><span>&raquo; Status</span></legend>
                        	<label><input type="radio"<?= $plugrush['mobile_status']==1?' checked="checked"':'';?> value="1" name="plugrush-settings[mobile_status]"> Enabled</label><br>
                        	<label><input type="radio"<?= $plugrush['mobile_status']==0?' checked="checked"':'';?> value="0" name="plugrush-settings[mobile_status]"> Disabled</label>
                            <br>
                        </fieldset>
                    </td>
                </tr>
                <tr valign="top">
                	<th scope="row"><label for="plugrush-mobile_alturl">&raquo; Alternative URL</label></th>
                	<td>
                    	<input type="text" placeholder="http://www.alternativeurl.com/" class="regular-text" value="<?=$plugrush['mobile_alturl'];?>" id="plugrush-mobile_alturl" style="width:320px;" name="plugrush-settings[mobile_alturl]">
                  		<p class="description">A URL you want to redirect to when there are no available campaigns in PlugRush. Do not use your home page,since that can cause an endless loop</p>
                	</td>
              	</tr>
            </tbody>
        </table>
	    <hr>
	    <br>
        <h3 class="title">Plugs</h3>
   		<p>When you create posts in wordpress, you can have them automatically posted to your plugs in PlugRush.</p>
       	<table class="form-table">
            <tbody>
                <tr valign="top">
                    <th scope="row">&raquo; Autopost to PlugRush</th>
                    <td>
						<fieldset>
                        	<legend class="screen-reader-text"><span>&raquo; Autopost to PlugRush</span></legend>
							<label><input type="radio" name="plugrush-settings[autopost]" value="1"<?= $plugrush['autopost']==1?' checked="checked"':'';?>> Enabled</label><br>
							<label><input type="radio" name="plugrush-settings[autopost]" value="0"<?= $plugrush['autopost']==0?' checked="checked"':'';?>> Disabled</label>
						</fieldset>
                    <p class="description">Selecting 'Enabled' will pre-check the "Post to PlugRush" option when you create or edit a post</p>
                    </td>
                </tr>
            </tbody>
        </table>
		<hr>
		<br>
      	<h3 class="title">Widgets</h3>
  		<p>Go to your <a href="widgets.php">widgets settings</a> page and place the widgets you've created in PlugRush into your theme.</p>
	    <hr>
	    <br>
        <?php }elseif($status['status']=='401'){ ?>
        	<p><?= plugrush_domain_from_url(get_option('siteurl'));?> is not in your PlugRush account. Please add it to your account by going to <a href="//www.plugrush.com/websites/add">PlugRush.com</a></p>
        	<hr>
        	<br>
        <?php } ?>
        <h3 class="title">Update Settings</h3>
   		<p>If you've enabled caching, you may have to clear it in order for these settings to take effect. You may also have to wait for cache on PlugRush to update.</p>
      	<p class="submit"><input type="submit" value="Save Changes" class="button-primary" id="submit" name="submit"></p>
    </form>
  </div>
<?php
}

class Plugrush_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct(){
		parent::__construct(
	 		'plugrush_widget',// Base ID
			'Plugrush Widget',// Name
			array( 'description' => __( 'Lets you display a widget from PlugRush in your sidebar or in other locations in your theme','text_domain' ),) // Args
		);
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget($args,$instance){
		extract($args);
		$title = apply_filters('widget_title',$instance['title']);
		echo $before_widget;
		if(!empty($title)){
			echo $before_title.$title.$after_title;
		}
		echo htmlspecialchars_decode($instance['widget_code']);
		echo $after_widget;
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance,$old_instance ) {
		$instance = array();
		$instance['title'] = $new_instance['title'];
		$plugrush = get_option('plugrush-settings');
		if(!empty($plugrush['user']) && !empty($plugrush['api_key'])){
			$status = plugrush_request(array('action'=>'verify','user'=>$plugrush['user'],'api_key'=>$plugrush['api_key']));
		}
		if($status['status'] =='200'){
			if(!empty($new_instance['widget_id'])){
				$params = array('action'=>'adzone/widget','user'=>$plugrush['user'],'api_key'=>$plugrush['api_key'],'id'=>$new_instance['widget_id']);
				$w = (array) plugrush_request($params);
				if(!empty($w)){
					$instance['widget_id'] = $w['data']['id'];	
					$instance['widget_code'] = $w['data']['code'];	
				}
			}
		}
		return $instance;
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		$plugrush = get_option('plugrush-settings');
		if(!empty($plugrush['user']) && !empty($plugrush['api_key'])){
			$status = plugrush_request(array('action'=>'verify','user'=>$plugrush['user'],'api_key'=>$plugrush['api_key']));
		}
		if($status['status']=='200'){
			$widgets = plugrush_request(array('action'=>'adzone/widgets','user'=>$plugrush['user'],'api_key'=>$plugrush['api_key']));
		}
		?>
        <?php if(!empty($widgets['data'])){ ?>
            <p>
                <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
                <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
            </p>
            <p>
                <label for="<?php echo $this->get_field_id( 'widget_id' ); ?>"><?php _e( 'PlugRush Widget:' ); ?></label>
                <select name="<?php echo $this->get_field_name( 'widget_id' ); ?>" style="width:218px;">
                    <option>Please select a widget</option>
                    <?php foreach($widgets['data'] as $row){ ?>
                        <option value="<?=$row['id'];?>"<?=$instance['widget_id']==$row['id']?' selected="selected"':'';?>><?=$row['title'];?> (<?=$row['width'];?>x<?=$row['height'];?>)</option>
                    <?php } ?>
                </select>
            </p>
        <?php }else{ ?>
        	<p>No PlugRush Widgets found. Go to <a href="//www.plugrush.com/publishers/adzones" target="_blank">PlugRush.com</a> and create some first.
        <?php } ?>
		<?php 
	}
}

add_action('widgets_init',create_function('','register_widget( "plugrush_widget" );'));

class PlugRush{
	var $domain;
	var $settings;

	function init($settings){
		$this->settings = $settings;
		$this->domain = plugrush_domain_from_url(get_option('siteurl'));
	}

	function status(){
		$status = array("status"=>1,"message"=>"Script has been installed successfully");
		if(isset($_GET["format"])&&$_GET["format"]=="json"){
			header("Content-type: application/json");
			echo json_encode($status);
		}else{
			echo $status["message"];
		}
		exit();
	}

	function update(){
		$status = array("status"=>0,"message"=>"Script has not been installed");
		if(empty($_POST["secret"])||empty($_POST["script"])){
			$status = array("status"=>-1,"message"=>"Could not update script");
		}elseif($_POST["secret"]!=$this->settings['api_key']){
			$status = array("status"=>-1,"message"=>"api_key does not match");
		}elseif(empty($_POST["script"])){
			$status = array("status"=>-1,"message"=>"An error occured");
		}
		if($status["status"]==0){
			$this->settings['adblock_code'] = htmlspecialchars($_POST['script']);
			if(update_option('plugrush-settings',$this->settings)){
				$status = array("status"=>1,"message"=>"Script updated successfully to your server");
			}else{
				$status = array("status"=>-1,"message"=>"Could not update. js.php is not writable");
			}
		}
		if(isset($_GET["format"])&&$_GET["format"]=="json"){
			header("Content-type: application/json");
			echo json_encode($status);
		}else{
			echo $status["message"];
		}
		exit();
	}
}

function plugrush_publish_box(){
	global $post;
	// only display for authorized users
	if(!current_user_can('publish_posts')){
		return;
	}
	// don't display for pages
	if($post->post_type=='page'){
		return;
	}
	$plugrush = get_option('plugrush-settings');
	$plugrush_post = get_post_meta($post->ID,'_plugrush',TRUE);
?>
<div class="misc-pub-section plugrush">
	<input type="hidden" name="_plugrush_post" value="0" />
    <span id="plugrush" style="background-image: url('<?=plugins_url('/logo16.png' ,'plugrush/plugrush.php' );?>'); background-repeat:no-repeat; padding-left:20px;">
    <?php
    $checked = $plugrush['autopost']==1?' checked="checked"':'';
    if(isset($plugrush_post['autopost'])&&$plugrush_post['autopost']=='0'){
        $checked = '';	
    }
    ?>
    Post to PlugRush: <b><input type="checkbox" name="_plugrush_post"<?=$checked;?> value="1" /></b></span>
</div>
<?php
}

function plugrush_post_header_columns($columns){
    if(!isset($columns['_plugrush_posted'])){
        $columns['_plugrush_posted'] = '<img src="'.plugins_url('/logo16.png','plugrush/plugrush.php').'" title="Posted to PlugRush" alt="PlugRush" />';
    }
    return $columns;
}

function plugrush_post_data_row($column_name,$post_id){
    switch($column_name){
        case '_plugrush_posted':       
            $plugrush_post = get_post_meta($post_id,'_plugrush',TRUE);
            if(isset($plugrush_post['posted'])&&$plugrush_post['posted']=='1'){
				echo '<img src="'.plugins_url('/tick16.png','plugrush/plugrush.php').'" title="Posted to PlugRush" alt="Yes" />';
			}else{
				echo '<img src="'.plugins_url('/delete16.png','plugrush/plugrush.php').'" title="Not yet posted to PlugRush" alt="No" />';
			}
        break;
        default:
        break;
    }
}
?>