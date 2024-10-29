<?php
function b2_xml_ajax(){
	$action = $_POST["b2xml_action"];
	global $b2xml_entry;
	switch($action){
		case "0x001": /* Save Settings */
			foreach($b2xml_entry->engine->options as $k => $v) {
				if($k == "excludes") {
					$IDss = array();
					$IDs = explode(",", $_POST[$k]);
					for($x = 0; $x < count($IDs); $x++) {
						$ID = intval(trim($IDs[$x]));
						if($ID > 0) 
							$IDss[] = $ID;
					}
					$b2xml_entry->engine->options[$k] = $IDss;
				} else if($k == "exclude_cats") {
					if(isset($_POST[$k]) && $_POST[$k] != ''){
						$b2xml_entry->engine->options[$k] = explode(",", $_POST[$k]);
					}
				} else if($k == "yahoo_key"){
					$b2xml_entry->engine->options[$k] = (string)$_POST[$k];
				} else if($k == "priority"){
					$b2xml_entry->engine->options[$k] = (string)$_POST[$k];
				} else if(substr($k, 0, 2) == "pr"){
					$b2xml_entry->engine->options[$k]= (float)$_POST[$k];
				} else if($k == "home"){
					$b2xml_entry->engine->options[$k] = (string)$_POST[$k];
				} else if($k == "posts"){
					$b2xml_entry->engine->options[$k] = (string)$_POST[$k];
				} else if($k == "pages"){
					$b2xml_entry->engine->options[$k] = (string)$_POST[$k];
				} else if($k == "cats"){
					$b2xml_entry->engine->options[$k] = (string)$_POST[$k];
				} else if($k == "arch"){
					$b2xml_entry->engine->options[$k] = (string)$_POST[$k];
				} else if($k == "arch_old"){
					$b2xml_entry->engine->options[$k] = (string)$_POST[$k];
				} else if($k == "auth"){
					$b2xml_entry->engine->options[$k] = (string)$_POST[$k];
				} else if($k == "tags"){
					$b2xml_entry->engine->options[$k] = (string)$_POST[$k];
				}else {
					if($k != 'xml' && $k != 'zip' && $k != 'file_name' && $k != 'manual_enabled' 
					&& $k != 'auto_delay' && $k != 'manual_key' && $k != '' && $k != 'memory'
					&& $k != 'time' && $k != 'max_posts' && $k != 'style_template' 
					&& $k != 'location_mode'&& $k != 'filname_manual'&& $k != 'file_url_manual' 
					&& $k != 'install_date'){
						$b2xml_entry->engine->options[$k] = (bool)$_POST[$k];
					}
				}
			}
			$b2xml_entry->engine->s_options();
			echo 'Settings updated scuccessfully';
			exit;
		break;
		case "0x002": /* Reset Settings */
			$b2xml_entry->engine->setup_options();
			$b2xml_entry->engine->s_options();
			echo 'Settings restored successfully';
			exit;
		break;
		case "0x003": /* Rebuilt Sitemap */
			if(function_exists('wp_clear_scheduled_hook')) 
				wp_clear_scheduled_hook('b2xml_cron_job');
			$b2xml_entry->engine->generate_xml_sitemap();
			echo B2_XML_PL_URL;
			exit;
		break;
	}
}
add_action('wp_ajax_b2_xml_ajax', 'b2_xml_ajax');