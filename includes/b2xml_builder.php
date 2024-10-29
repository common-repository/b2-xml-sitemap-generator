<?php

class B2XML_Builder{
	var $options = array();
	var $pages = array();
	var $names = array();
	var $priorities = array();
	var $initiated = false;
	var $last_error = null;
	var $last_postID = 0;
	var $is_active = false;
	var $is_scheduled = false;
	var $fhnd = null;
	var $zhnd = null;
	
	function get_xslt(){
		return B2_XML_URL . 'template/sitemap.xsl';
	}
	
	function setup_options(){
		$this->options = array();
		$this->options['priority'] = 'B2XML_PRIO_BCC';
		$this->options['file_name'] = 'sitemap.xml';
		$this->options['xml'] = true;
		$this->options['zip'] = true;
		$this->options['ping'] = true;
		$this->options['ping_ask'] = true;
		$this->options['ping_bing'] = true;
		$this->options['manual_enabled'] = false;
		$this->options['auto_enabled'] = true;
		$this->options['auto_delay'] = true;
		$this->options['manual_key'] = md5(microtime());
		$this->options['memory'] = '';
		$this->options['time'] = -1;
		$this->options['max_posts'] = -1;
		$this->options['style_template'] = true;
		$this->options['robots'] = true;
		$this->options['excludes'] = array();
		$this->options['exclude_cats'] = array();
		$this->options['location_mode'] = 'auto';
		$this->options['filname_manual'] = '';
		$this->options['file_url_manual'] = '';

		$this->options['in_home'] = true;
		$this->options['in_posts'] = true;
		$this->options['in_posts_sub'] = false;
		$this->options['in_pages'] = true;
		$this->options['in_cats'] = false;
		$this->options['in_arch'] = false;
		$this->options['in_auth'] = false;
		$this->options['in_tag'] = false;
		$this->options['in_tax'] = array();
		$this->options['in_last_mode'] = true;

		$this->options['home'] = 'daily';
		$this->options['posts'] = 'monthly';
		$this->options['pages'] = 'weekly';
		$this->options['cats'] = 'weekly';
		$this->options['auth'] = 'weekly';
		$this->options['arch'] = 'daily';
		$this->options['arch_old'] = 'yearly';
		$this->options['tags'] = 'weekly';

		$this->options['pr_home'] = 1.0;
		$this->options['pr_posts'] = 0.6;
		$this->options['pr_posts_min'] = 0.2;
		$this->options['pr_pages'] = 0.6;
		$this->options['pr_cats'] = 0.3;
		$this->options['pr_arch'] = 0.3;
		$this->options['pr_auth'] = 0.3;
		$this->options['pr_tags'] = 0.3;
		
		$this->options['install_date'] = time();
	}
	function load_options() {
		
		$this->setup_options();
		$def_options = get_option("b2xml_options");
		if($def_options && is_array($def_options)) {
			foreach($def_options AS $k=>$v) {
				$this->options[$k] = $v;
			}
		} else {
			update_option("b2xml_options", $this->options);
		}
	}
	function init(){
		if(!$this->initiated) {
			$this->names = array(
				"always" => "Always",
				"hourly" => "Hourly",
				"daily" => "Daily",
				"weekly" => "Weekly",
				"monthly" => "Monthly",
				"yearly" => "Yearly",
				"never" => "Never"
			);
			$this->load_options();
			$this->load_pages();
			add_filter("b2xml_add_priority", array(&$this, 'add_def_prio'));
			$r = apply_filters("b2xml_add_priority", $this->priorities);
			if($r != null) 
				$this->priorities = $r;
			$this->validate_priority();
			$this->initiated = true;
		}
	}
	function &get_instance() {
		if(isset($GLOBALS["b2xml_instance"])) {
			return $GLOBALS["b2xml_instance"];
		} else{ 
			return null;
		}
	}
	function is_active(){
		$inst = &B2XML_Builder::get_instance();
		return ($inst != null && $inst->is_active);
	}
	function is_zip_enabled(){
		return ($this->g_option("zip") === true && function_exists("gzwrite"));
	}
	function is_tax_supported() {
		return (function_exists("get_taxonomy") && function_exists("get_terms"));
	}
	function get_custom_tax() {
		$taxes = get_object_taxonomies('post');
		return array_diff($taxes, array("category", "post_tag"));
	}
	function enable_xml() {
		if(!isset($GLOBALS["b2xml_instance"])) {
			$GLOBALS["b2xml_instance"] = new B2XML_Builder();
		}
	}
	function check_auto_build($postID, $external = false){
		$this->init();
		if((($this->g_option("auto_enabled") === true && 
			$this->last_postID != $postID) || $external) && 
			(!defined('WP_IMPORTING') || WP_IMPORTING != true)) {
			if($this->g_option("auto_delay") == true) {
				if(!$this->is_scheduled) {
					wp_clear_scheduled_hook('b2xml_cron_job');
					wp_schedule_single_event(time() + 15,'b2xml_cron_job');
					$this->is_scheduled = true;
				}
			} else {
				if(!$this->last_postID && 
					(!isset($_GET["delete"]) || count((array) $_GET['delete']) <= 0)) {
					$this->generate_xml_sitemap();
				}
			}
			$this->last_postID = $postID;
		}
	}
	function create_now() {
		$this->check_auto_build(null, true);	
	}
	function check_manual_build() {
		if(!empty($_GET["b2xml_com"]) && !empty($_GET["b2xml_key"])) {
			$this->init();
			if($this->g_option("manual_enabled")===true && 
				$_GET["b2xml_com"] == "build" && 
				$_GET["b2xml_key"] == $this->g_option("manual_key")) {
				$this->generate_xml_sitemap();
				echo "DONE";
				exit;
			}
		}
	}
	function get_parent($class_name) {
		$parent = get_parent_class($class_name);
		$parents = array();
		if (!empty($parent)) {
			$parents = $this->get_parent($parent);
			$parents[] = strtolower($parent);
		}
		return $parents;
	}
	function is_sub_class($class_name, $parent_name) {
		$class_name = strtolower($class_name);
		$parent_name = strtolower($parent_name);
		if(empty($class_name) || empty($parent_name) || 
			!class_exists($class_name) || 
			!class_exists($parent_name)) 
			return false;
		$parents = $this->get_parent($class_name);
		return in_array($parent_name, $parents);
	}
	function validate_priority(){
		$valids = array();
		for($i=0; $i < count($this->priorities); $i++) {
			if(class_exists($this->priorities[$i])) {
				if($this->is_sub_class($this->priorities[$i], "B2XML_PRIO_B")) {
					array_push($valids,$this->priorities[$i]);
				}
			}
		}
		$this->priorities = $valids;
		if(!$this->g_option("priority")) {
			if(!in_array($this->g_option("priority"), $this->priorities, true)) {
				$this->s_option("priority","");
			}
		}
	}
	function add_def_prio($prios) {
		array_push($prios, "B2XML_PRIO_BCC");
		array_push($prios, "B2XML_PRIO_BAC");
		return $prios;
	}
	function load_pages(){
		global $wpdb;
		$req_update = false;
		$pg = $wpdb->get_var("SELECT option_value FROM $wpdb->options WHERE option_name = 'b2xml_pages'");
		if(!empty($pg) && strpos($pg,"b2xml_page") !== false) {
			$pg = str_replace("O:7:\"b2xml_page\"","O:26:\"b2xml_sitemap_page\"",$pg);
			$req_update=true;
		}
		if(!empty($pg)) {
			$sp = unserialize($pg);
			$this->pages = $sp;
		} else {
			$this->pages=array();
		}
		if($req_update) 
			$this->save_pages();
	}
	function save_pages(){
		$ov = get_option("b2xml_pages");
		if($ov == $this->pages) {
			return true;
		} else {
			delete_option("b2xml_pages");
			add_option("b2xml_pages", $this->pages, null, "no");
			return true;
		}
	}
	function get_xml_url($auto = false){
		if(!$auto && $this->g_option("location_mode") == "manual") {
			return $this->g_option("file_url_manual");
		} else {
			return B2_XML_SITEMAP_URL . $this->g_option("file_name");
		}
	}
	function get_zip_url($auto = false) {
		return $this->get_xml_url($auto) . ".gz";
	}
	function get_xml_path($auto = false) {
		if(!$auto && $this->g_option("location_mode") == "manual") {
			return $this->g_option("filname_manual");
		} else {
			return B2_XML_SITEMAP_DIR . $this->g_option("file_name");
		}
	}
	function get_zip_path($auto = false) {
		return $this->get_xml_path($auto) . ".gz";
	}
	function g_option($key) {
		if(array_key_exists($key, $this->options)) {
			return $this->options[$key];
		} else 
			return null;
	}
	function s_option($key, $value) {
		$this->options[$key] = $value;
	}
	function s_options() {
		$ov = get_option("b2xml_options");
		if($ov == $this->options) {
			return true;
		} else 
			return update_option("b2xml_options",$this->options);
	}
	function get_comments() {
		global $wpdb;
		$comments = array();
		$coms=$wpdb->get_results("SELECT `comment_post_ID` as `post_id`, COUNT(comment_ID) as `comment_count` FROM `" . $wpdb->comments . "` WHERE `comment_approved`='1' GROUP BY `comment_post_ID`");
		if($coms) {
			foreach($coms as $comment) {
				$comments[$comment->post_id] = $comment->comment_count;
			}
		}
		return $comments;
	}
	function get_com_count($comments) {
		$count = 0;
		foreach($comments as $k => $v) {
			$count += $v;
		}
		return $count;
	}
	function add_url($url, $last_mode = 0, $freq = "monthly", $prio = 0.5) {
		if($this->g_option('in_last_mode') === false) 
			$last_mode = 0;
		$page = new B2XML_SMP($url, $prio, $freq, $last_mode);
		$this->add_node($page);
	}
	function add_node(&$page) {
		if(empty($page)) 
			return;
		$e = $page->rend();
		if($this->ziphnd && $this->is_zip_enabled()) {
			gzwrite($this->ziphnd, $e);
		}
		if($this->fhnd && $this->g_option("xml")) {
			fwrite($this->fhnd, $e);
		}
	}
	function is_fwr($file_name) {
		if(!is_writable($file_name)) {
			if(!@chmod($file_name, 0666)) {
				$p2f = dirname($file_name);
				if(!is_writable($p2f)) {
					if(!@chmod($p2f, 0666)) {
						return false;
					}
				}
			}
		}
		return true;
	}
	function d_robo() {
		$this->init();
		if($this->g_option('robots') === true) {
			$sitemap_url = $this->get_xml_url();
			if($this->is_zip_enabled()) {
				$sitemap_url = $this->get_zip_url();
			}
			echo  "\nSitemap: " . $sitemap_url . "\n";
		}
	}
	function show_ping() {
		$service = !empty($_GET["b2xml_p_serv"]) ? $_GET["b2xml_p_serv"] : null;
		$status_data = &B2XML_Status::load_status();
		if(!$status_data) die("No build status yet. Build the sitemap first.");
		$url = null;
		switch($service) {
			case "google":
				$url = $status_data->google_url;
				break;
			case "bing":
				$url = $status_data->bing_url;
				break;
			case "ask":
				$url = $status_data->ask_url;
				break;			
		}
		if(empty($url)) die("Invalid ping url");

		echo '<html><head><title>Ping Test</title>';
		if(function_exists('wp_admin_css')) 
			wp_admin_css('css/global',true);
		echo '</head><body><h1>Ping Test</h1>';
		echo '<p>Trying to ping: <a href="' . $url . '">' . $url . '</a>. Ping results will be displayed below.</p>';
		$e_lvl = error_reporting(E_ALL);
		$e_disp = ini_set("display_errors", 1);
		if(!defined('WP_DEBUG')) 
			define('WP_DEBUG',true);
		echo '<h2>Errors, Warnings, Notices:</h2>';
		if(WP_DEBUG == false) 
			echo "<i>WP_DEBUG was set to false somewhere before. You might not see all debug information.</i><br />";
		if(ini_get("display_errors") != 1) 
			echo "<i>Your display_errors setting currently prevents the plugin from showing errors.</i><br />";

		$res = $this->rem_open($url);
		echo '<h2>Result:</h2>';
		echo wp_kses($res, array(
			'a' => array('href' => array()),
			'p' => array(), 
			'ul' => array(), 
			'ol' => array(), 
			'li' => array()));
		echo '<h2>Result (HTML):</h2>';
		echo htmlspecialchars($res);
		error_reporting($e_lvl);
		ini_set("display_errors",$e_disp);
		echo '</body></html>';
		exit;
	}
	function rem_open($url, $method = 'get', $post_data = null, $timeout = 10) {
		$ops = array();
		$ops['timeout'] = $timeout;
		
		if($method == 'get') {
			$resp = wp_remote_get($url, $ops);
		} else {
			$resp = wp_remote_post($url, array_merge($ops, array('body' => $post_data)));
		}
		
		if(is_wp_error($resp)){
			$errs = $resp->get_error_messages();
			$errs = htmlspecialchars(implode('; ', $errs));
			echo $errs;
			trigger_error('WP HTTP API Web Request failed: ' . $errs, E_USER_NOTICE);
			return false;
		}
		return $resp['body'];
	}
	function g_freq($curr_val) {
		$list = '';
		foreach($this->names as $k => $v) {
			$list .= "<option value=\"$k\" " . $this->g_sel_val($k, $curr_val) .">" . $v . "</option>";
		}
		return $list;
	}
	function g_prio_val($curr_val) {
		$curr_val = (float)$curr_val;
		$vals = '';
		for($i=0.0; $i<=1.0; $i += 0.1) {
			$v = number_format($i, 1, ".", "");
			$t = function_exists('number_format_i18n') ? number_format_i18n($i, 1) : number_format($i, 1);
			$vals .= "<option value=\"" . $v . "\" " . $this->g_sel_val("$i", "$curr_val") .">";
			$vals .= $t;
			$vals .= "</option>";
		}
		return $vals;
	}
	function g_chck_val($val, $equals) {
		if($val == $equals) 
			return $this->g_hattr("checked");
		else 
			return "";
	}
	function g_sel_val($val, $equals) {
		if($val == $equals) 
			return $this->g_hattr("selected");
		else 
			return "";
	}
	function g_hattr($attr, $value = NULL) {
		if($value == NULL) 
			$value = $attr;
		return " " . $attr . "=\"" . $value . "\" ";
	}
	function h_apply_pages() {
 		$page_urls = (!isset($_POST["b2xml_p_u"]) || 
			!is_array($_POST["b2xml_p_u"]) ? array() : $_POST["b2xml_p_u"]);
		
		$page_pr = (!isset($_POST["b2xml_p_p"]) || 
			!is_array($_POST["b2xml_p_p"]) ? array() : $_POST["b2xml_p_p"]);
		
		$page_f = (!isset($_POST["b2xml_p_f"]) || 
			!is_array($_POST["b2xml_p_f"]) ? array() : $_POST["b2xml_p_f"]);
		
		$page_m = (!isset($_POST["b2xml_p_m"]) || 
			!is_array($_POST["b2xml_p_m"]) ? array() : $_POST["b2xml_p_m"]);

		$pgss = array();
		if(isset($_POST["b2xml_p_k"]) && is_array($_POST["b2xml_p_k"])) {
			for($i=0; $i<count($_POST["b2xml_p_k"]); $i++) {
				$p = new B2XML_SMP();
				if(substr($page_urls[$i], 0, 4) == "www.") 
					$page_urls[$i] = "http://" . $page_urls[$i];
				$p->s_url($page_urls[$i]);
				$p->s_prio($page_pr[$i]);
				$p->s_freq($page_f[$i]);
				$lm = (!empty($page_m[$i]) ? strtotime($page_m[$i], time()) : -1);
				if($lm === -1) 
					$p->s_last_mode(0);
				else 
					$p->s_last_mode($lm);
				array_push($pgss, $p);
			}
		}
		return $pgss;
	}
	function g_ts_mysql($mysql_dt) {
		list($date, $hours) = split(' ', $mysql_dt);
		list($year, $month, $day) = split('-', $date);
		list($hour, $min, $sec) = split(':', $hours);
		return mktime(intval($hour), 
			intval($min), 
			intval($sec), 
			intval($month), 
			intval($day), 
			intval($year));
	}
	function g_bk_link() {
		return B2_XML_URL;
	}
	function generate_xml_sitemap(){
		global $wpdb, $posts, $wp_version;
		$this->init();
		if($this->g_option("memory") != ''){
			@ini_set("memory_limit", $this->g_option("memory"));
		}
		
		if($this->g_option("time") != -1){
			@set_time_limit($this->g_option("time"));
		}
		$status_data = new B2XML_Status();
		$this->is_active = true;
		if($this->g_option("xml")){
			$fn = $this->get_xml_path();
			$status_data->start_xml($this->get_xml_path(), $this->get_xml_url());
			if($this->is_fwr($fn)) {
				$this->fhnd = fopen($fn, "w");
				if(!$this->fhnd) 
					$status_data->end_xml(false, "Not openable");
				
			} else 
				$status_data->end_xml(false,"not writable");
		}
		if($this->is_zip_enabled()) {
			$fn = $this->get_zip_path();
			$status_data->start_zip($this->get_zip_path(), $this->get_zip_url());
			if($this->is_fwr($fn)) {
				$this->zhnd = gzopen($fn, "w1");
				if(!$this->zhnd) 
					$status_data->end_zip(false, "Not openable");
			} else 
				$status_data->end_zip(false, "not writable");
		}
		if(!$this->fhnd && !$this->zhnd) {
			$status_data->end_process();
			return;
		}
		$this->add_node(new B2XML_XMLE('<?xml version="1.0" encoding="UTF-8"' . '?' . '>'));
		$xslt = $this->get_xslt();
		
		if(!empty($xslt)) {
			$this->add_node(
				new B2XML_XMLE('<' . '?xml-stylesheet type="text/xsl" href="' . $xslt . '"?' . '>'));
		}
		
		$this->add_node(new B2XML_COM("generator=\"wordpress/" . get_bloginfo('version') . "\""));
		$this->add_node(new B2XML_COM("sitemap-generator-url=\"http://www.b2foundry.com\" sitemap-generator-version=\"" . B2XML_VERSION . "\""));
		$this->add_node(new B2XML_COM("generated-on=\"" . date(get_option("date_format") . " " . get_option("time_format")) . "\""));

		$comments = ($this->g_option("priority") != "" ? $this->get_comments() : array());
		$comment_count = (count($comments) > 0 ? $this->get_com_count($comments):0);

		$this->add_node(new B2XML_XMLE('<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"));
		
		$home = get_bloginfo('url');
		$homeID = 0;
		
		//Add the home page (WITH a slash!)
		if($this->g_option("in_home")) {
			if('page' == get_option('show_on_front') && get_option('page_on_front')) {
				$pof = get_option('page_on_front');
				$p = get_page($pof);
				if($p) {
					$homeID = $p->ID;
					$this->add_url(trailingslashit($home),
						$this->g_ts_mysql(($p->post_modified_gmt && 
							$p->post_modified_gmt != '0000-00-00 00:00:00' ? 
							$p->post_modified_gmt : $p->post_date_gmt)),
							$this->g_option("home"), $this->g_option("pr_home"));
				}
			} else {
				$this->add_url(trailingslashit($home), 
					$this->g_ts_mysql(get_lastpostmodified('GMT')), 
					$this->g_option("home"), 
					$this->g_option("pr_home"));
			}
		}
		if($this->g_option("in_posts") || $this->g_option("in_pages")) {
			$wp_compat = (floatval($wp_version) < 2.1);
			$use_qt = false;
			$excludes = $this->g_option('excludes');
			$ex_cats = $this->g_option("exclude_cats");
			
			if($ex_cats && count($ex_cats) > 0 && $this->is_tax_supported()) {
				$ex_cat_posts = get_objects_in_term($ex_cats,"category");
				if(count($ex_cat_posts) > 0) {
					$ex_pg = $wpdb->get_col("SELECT ID FROM `" . 
						$wpdb->posts . 
						"` WHERE post_type != 'post' AND ID IN ('" . 
						implode("','", $ex_cat_posts) . "')");
					$ex_pg = array_map('intval', $ex_pg);
					if(count($ex_pg)>0)	
						$ex_cat_posts = array_diff($ex_cat_posts, $ex_pg);
					if(count($ex_cat_posts)>0) 
						$excludes = array_merge($excludes, $ex_cat_posts);
				}
			}

			$cont_stmt = '';
			if($use_qt) {
				$cont_stmt .= ', post_content ';
			}
			$pp_stmt = '';
			$in_sp = ($this->g_option('in_posts_sub') === true);
			if($in_sp && $this->g_option('in_posts') === true) {
				$pd = '<!--nextpage-->';
				$pp_stmt = ", (character_length(`post_content`)  - character_length(REPLACE(`post_content`, '$pd', ''))) / " . 
					strlen($pd) . " as postPages";
			}
			$sql="SELECT `ID`, `post_author`, `post_date`, `post_date_gmt`, `post_status`, `post_name`, `post_modified`, `post_modified_gmt`, `post_parent`, `post_type` $postPageStmt $contentStmt FROM `" . 
				$wpdb->posts . "` WHERE ";
			$where = '(';
			if($this->g_option('in_posts')){
				if($wp_compat) 
					$where .= "(post_status = 'publish' AND post_date_gmt <= '" . gmdate('Y-m-d H:i:59') . "')";
				else 
					$where .= " (post_status = 'publish' AND (post_type = 'post' OR post_type = '')) ";
			}
			if($this->g_option('in_pages')) {
				if($this->g_option('in_posts')) {
					$where .= " OR ";
				}
				if($wp_compat) {
					$where .= " post_status='static' ";
				} else {
					$where .= " (post_status = 'publish' AND post_type = 'page') ";
				}
			}
			$where .= ") ";
			if(is_array($excludes) && count($excludes) > 0) {
				$where .= " AND ID NOT IN ('" . implode("','",$excludes) . "')";
			}
			$where .= " AND post_password = '' ORDER BY post_modified DESC";
			$sql .= $where;
			
			if($this->g_option("max_posts")>0) {
				$sql.=" LIMIT 0," . $this->g_option("max_posts");
			}

			$p_count = intval($wpdb->get_var("SELECT COUNT(*) AS cnt FROM `" . 
				$wpdb->posts . "` WHERE ". $where, 0, 0));
			$con = $pos_res = null;
			$con = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD, true);
			if(!$con) {
				trigger_error("MySQL Connection failed: " . mysql_error(), E_USER_NOTICE);
				return;
			}
			if(!mysql_select_db(DB_NAME, $con)) {
				trigger_error("MySQL DB Select failed: " . mysql_error(), E_USER_NOTICE);
				return;
			}
			$pos_res = mysql_unbuffered_query($sql, $con);
			if(!$pos_res) {
				trigger_error("MySQL unbuffered query failed: " . mysql_error(), E_USER_NOTICE);
				return;
			}
			
			if($pos_res) {
				$prio_p = NULL;
				if($this->g_option("priority") != '') {
					$prio_class=$this->g_option('priority');
					$prio_p = new $prio_class($comment_count, $p_count);
				}
				$z = 1;
				$zz = 1;
				$def_prio_post = $this->g_option('pr_posts');
				$def_prio_page = $this->g_option('pr_pages');
				
				$post_freq = $this->g_option('posts');
				$page_freq = $this->g_option('pages');
				
				$min_prio = $this->g_option('pr_posts_min');
				
				while($post = mysql_fetch_object($pos_res)) {
					$cache = array(&$post);
					update_post_cache($cache);
					$GLOBALS['post'] = &$post;
					$permalink = get_permalink($post->ID);
					if($permalink != $home && $post->ID != $homeID) {
						$is_page = false;
						if($wp_compat) {
							$is_page = ($post->post_status == 'static');
						} else {
							$is_page = ($post->post_type == 'page');
						}
						$prio = 0;
						if($is_page) {
							$prio = $def_prio_page;
						} else {
							$prio = $def_prio_post;
						}
						if($prio_p !== null && !$is_page) {
							$cmtcnt = (isset($comments[$post->ID]) ? $comments[$post->ID] : 0);
							$prio = $prio_p->get_post_priority($post->ID, $cmtcnt, $post);
						}
						if(!$is_page && $min_prio>0 && $prio < $min_prio) {
							$prio = $min_prio;
						}
						$this->add_url($permalink, 
							$this->g_ts_mysql(($post->post_modified_gmt && 
								$post->post_modified_gmt != '0000-00-00 00:00:00' ? 
								$post->post_modified_gmt : $post->post_date_gmt)),
								($is_page ? $page_freq : $post_freq), $prio);
						if($in_sp) {
							$sub_page = '';
							for($p = 1; $p <= $post->postPages; $p++) {
								if(get_option('permalink_structure') == '') {
									$sub_page = $permalink . '&amp;page=' . ($p + 1);
								} else {
									$sub_pate = trailingslashit($permalink) . 
										user_trailingslashit($p + 1, 'single_paged');
								}
								$this->add_url($sub_page, 
									$this->g_ts_mysql(($post->post_modified_gmt && 
										$post->post_modified_gmt != '0000-00-00 00:00:00' ? 
										$post->post_modified_gmt : $post->post_date_gmt)),
										($is_page ? $page_freq : $post_freq), $prio);
							}
						}
					}
					if($zz == 100 || $z == $p_count) {
						$status_data->save_step($z);
						$zz = 0;
					} else 
						$zz++;
					$z++;
					if(version_compare($wp_version,"2.5",">=")) {
						wp_cache_delete($post->ID, 'posts');
						wp_cache_delete($post->ID, 'post_meta');
						clean_object_term_cache($post->ID, 'post');
					} else {
						clean_post_cache($post->ID);
					}
				}
				unset($pos_res);
				unset($prio_p);
			}
		}
		if($this->g_option("in_cats")) {
			$ex_cats = $this->g_option("exclude_cats");
			if($ex_cats == null) 
				$ex_cats = array();
			if(!$this->is_tax_supported()) {
				$cat_res = $wpdb->get_results("
							SELECT
								c.cat_ID AS ID,
								MAX(p.post_modified_gmt) AS last_mod
							FROM
								`" . $wpdb->categories . "` c,
								`" . $wpdb->post2cat . "` pc,
								`" . $wpdb->posts . "` p
							WHERE
								pc.category_id = c.cat_ID
								AND p.ID = pc.post_id
								AND p.post_status = 'publish'
								AND p.post_type='post'
							GROUP
								BY c.cat_id
							");
				if($cat_res) {
					foreach($cat_res as $cat) {
						if($cat && $cat->ID && $cat->ID>0 && !in_array($cat->ID, $ex_cats)) {
							$this->add_url(get_category_link($cat->ID),
								$this->g_ts_mysql($cat->last_mod), 
								$this->g_option("cats"),
								$this->g_option("pr_cats"));
						}
					}
				}
			} else {
				$cats = get_terms("category", array("hide_empty" => true, "hierarchical" => false));
				if($cats && is_array($cats) && count($cats) > 0) {
					foreach($cats as $cat) {
						if(!in_array($cat->term_id, $ex_cats)) 
							$this->add_url(get_category_link($cat->term_id),
							0,
							$this->g_option("cats"),
							$this->g_option("pr_cats"));
					}
				}
			}
		}
		if($this->g_option("in_arch")) {
			$now = current_time('mysql');
			$arch_res = $wpdb->get_results("
						SELECT DISTINCT
							YEAR(post_date_gmt) AS `year`,
							MONTH(post_date_gmt) AS `month`,
							MAX(post_date_gmt) as last_mode,
							count(ID) as posts
						FROM
							$wpdb->posts
						WHERE
							post_date < '$now'
							AND post_status = 'publish'
							AND post_type = 'post'
							" . (floatval($wp_version) < 2.1?"AND {$wpdb->posts}.post_date_gmt <= '" . gmdate('Y-m-d H:i:59') . "'":"") . "
						GROUP BY
							YEAR(post_date_gmt),
							MONTH(post_date_gmt)
						ORDER BY
							post_date_gmt DESC");
			if ($arch_res) {
				foreach ($arch_res as $archs) {
					$url  = get_month_link($archs->year,   $archs->month);
					$cfreq = "";
					if($archs->month == date("n") && $archs->year == date("Y")) {
						$cfreq = $this->g_option("arch");
					} else { 
						$cfreq = $this->g_option("arch_old");
					}
					$this->add_url($url,
						$this->g_ts_mysql($archs->last_mode),
						$cfreq,
						$this->g_option("pr_arch"));
				}
			}
		}
		if($this->g_option("in_auth")) {
			$link_f = null;
			if(function_exists('get_author_posts_url')) {
				$link_f = 'get_author_posts_url';
			} else if(function_exists('get_author_link')) {
				$link_f = 'get_author_link';
			}
			if($link_f !== null) {
				$sql = "SELECT DISTINCT
							u.ID,
							u.user_nicename,
							MAX(p.post_modified_gmt) AS last_post
						FROM
							{$wpdb->users} u,
							{$wpdb->posts} p
						WHERE
							p.post_author = u.ID
							AND p.post_status = 'publish'
							AND p.post_type = 'post'
							AND p.post_password = ''
							" . (floatval($wp_version) < 2.1?"AND p.post_date_gmt <= '" . gmdate('Y-m-d H:i:59') . "'":"") . "
						GROUP BY
							u.ID,
							u.user_nicename";
							
				$authors = $wpdb->get_results($sql);
				if($authors && is_array($authors)) {
					foreach($authors as $author) {
						$url = ($link_f == 'get_author_posts_url' ? 
							get_author_posts_url($author->ID, $author->user_nicename) : 
							get_author_link(false, $author->ID, $author->user_nicename));
						$this->add_url($url,
							$this->g_ts_mysql($author->last_post),
							$this->g_option("auth"), 
							$this->g_option("pr_auth"));
					}
				}
			}
		}
		if($this->g_option("in_tags") && $this->is_tax_supported()) {
			$tags = get_terms("post_tag", array("hide_empty" => true, "hierarchical" => false));
			if($tags && is_array($tags) && count($tags) > 0) {
				foreach($tags as $tag) {
					$this->add_url(
						get_tag_link($tag->term_id),
						0,
						$this->g_option("tags"),
						$this->g_option("pr_tags"));
				}
			}
		}
		if($this->g_option("in_tax") && $this->is_tax_supported()) {
			$en_tax = $this->g_option("in_tax");
			$tax_list = array();
			foreach ($en_tax as $tax_n) {
				$tax = get_taxonomy($tax_n);
				if($tax) 
					$tax_list[] = $wpdb->escape($tax->name);
			}
			if(count($tax_list)>0) {
				$sql="
					SELECT
						t.*,
						tt.taxonomy AS _taxonomy,
						UNIX_TIMESTAMP(MAX(post_date_gmt)) as mode_date
					FROM
						{$wpdb->posts} p ,
						{$wpdb->term_relationships} r,
						{$wpdb->terms} t,
						{$wpdb->term_taxonomy} tt
					WHERE
						p.ID = r.object_id
						AND p.post_status = 'publish'
						AND p.post_type = 'post'
						AND p.post_password = ''
						AND r.term_taxonomy_id = t.term_id
						AND t.term_id = tt.term_id
						AND tt.count > 0
						AND tt.taxonomy IN ('" . implode("','",$tax_list) . "')
					GROUP BY
						t.term_id";
						
				$term_info = $wpdb->get_results($sql);
				foreach($term_info AS $term) {
					$this->add_url(get_term_link($term,$term->_taxonomy),
						$term->mode_date ,
						$this->g_option("tags"),
						$this->g_option("pr_tags"));
				}
			}
		}
		if($this->pages && is_array($this->pages) && count($this->pages) > 0) {
			foreach($this->pages AS $page) {
				$this->add_url($page->g_url(),
				$page->g_last_mode(),
				$page->g_freq(),
				$page->g_prio());
			}
		}
		do_action('b2xml_gen_sitemap');
		$this->add_node(new B2XML_XMLE("</urlset>"));

		$ping_url = '';
		if($this->g_option("xml")) {
			if($this->fhnd && fclose($this->fhnd)) {
				$this->fhnd = null;
				$status_data->end_xml(true);
				$ping_url = $this->get_xml_url();
			} else 
				$status_data->end_xml(false, "Could not close the sitemap file.");
		}
		if($this->is_zip_enabled()) {
			if($this->zhnd && fclose($this->zhnd)) {
				$this->zhnd = null;
				$status_data->end_zip(true);
				$ping_url=$this->get_zip_url();
			} else $status_data->end_zip(false,"Could not close the zipped sitemap file");
		}
		//Google
		if($this->g_option("ping") && !empty($ping_url)) {
			$sping_url = "http://www.google.com/webmasters/sitemaps/ping?sitemap=" . 
				urlencode($ping_url);
			$status_data->start_google_ping($sping_url);
			$pingres = $this->rem_open($sping_url);
			if($pingres == NULL || $pingres === false) {
				$status_data->end_google_ping(false, $this->last_error);
				trigger_error("Failed to ping Google: " . 
					htmlspecialchars(strip_tags($pingres)), E_USER_NOTICE);
			} else {
				$status_data->end_google_ping(true);
			}
		}
				
		//Bing
		if($this->g_option("ping_bing") && !empty($ping_url)) {
			$sping_url = "http://www.bing.com/webmaster/ping.aspx?siteMap=" . urlencode($ping_url);
			$status_data->start_bing_ping($sping_url);
			$pingres=$this->rem_open($sping_url);
			if($pingres == NULL || $pingres === false || 
				strpos($pingres, "Thanks for submitting your sitemap") === false) {
				trigger_error("Failed to ping Bing: " . 
					htmlspecialchars(strip_tags($pingres)), E_USER_NOTICE);
				$status_data->end_bing_ping(false, $this->last_error);
			} else {
				$status_data->end_bing_ping(true);
			}
		}

		//Ask.com
		if($this->g_option("ping_ask") && !empty($ping_url)) {
			$sping_url = "http://submissions.ask.com/ping?sitemap=" . urlencode($ping_url);
			$status_data->start_ask_ping($sping_url);
			$pingres=$this->rem_open($sping_url);
			if($pingres == NULL || $pingres ===false || 
				strpos($pingres,"successfully received and added") === false) {
				$status_data->end_ask_ping(false, $this->last_error);
				trigger_error("Failed to ping Ask.com: " . 
					htmlspecialchars(strip_tags($pingres)), E_USER_NOTICE);
			} else {
				$status_data->end_ask_ping(true);
			}
		}
		
		//Yahoo
		if($this->g_option("ping_yahoo") === true && 
			$this->g_option("yahoo_key") != "" && !empty($ping_url)) {
			$sping_url = "http://search.yahooapis.com/SiteExplorerService/V1/updateNotification?appid=" . 
			$this->g_option("yahoo_key") . "&url=" . urlencode($ping_url);
			$status_data->start_yahoo_ping($sping_url);
			$pingres = $this->rem_open($sping_url);
			if($pingres == NULL || $pingres ===false || 
				strpos(strtolower($pingres),"success") === false) {
				trigger_error("Failed to ping Yahoo: " . 
					htmlspecialchars(strip_tags($pingres)), E_USER_NOTICE);
				$status_data->end_yahoo_ping(false, $this->last_error);
			} else {
				$status_data->end_yahoo_ping(true);
			}
		}
		
		$status_data->end_process();
		$this->is_active = false;
		return $status_data;
	}
}
class B2XML_SMP {
	var $url;
	var $prio;
	var $freq;
	var $last_mode;
	function B2XML_SMP($url = "", $prio = 0.0, $freq = "never", $last_mode = 0) {
		$this->s_url($url);
		$this->s_prio($prio);
		$this->s_freq($freq);
		$this->s_last_mode($last_mode);
	}
	function g_url() {
		return $this->url;
	}
	function s_url($url) {
		$this->url = (string)$url;
	}
	function g_prio() {
		return $this->prio;
	}
	function s_prio($prio) {
		$this->prio = floatval($prio);
	}
	function g_freq() {
		return $this->freq;
	}
	function s_freq($freq) {
		$this->freq = (string)$freq;
	}
	function g_last_mode() {
		return $this->last_mode;
	}
	function s_last_mode($last_mode) {
		$this->last_mode = intval($last_mode);
	}
	function rend() {
		if($this->url == "/" || empty($this->url)) 
			return '';
		$r = "";
		$r .= "\t<url>\n";
		$r .= "\t\t<loc>" . $this->e_xml($this->url) . "</loc>\n";
		if($this->last_mode > 0) 
			$r .= "\t\t<lastmod>" . date('Y-m-d\TH:i:s+00:00',$this->last_mode) . "</lastmod>\n";
		if(!empty($this->freq)) 
			$r .= "\t\t<changefreq>" . $this->freq . "</changefreq>\n";
		if($this->prio !== false && $this->prio!=="") 
			$r .= "\t\t<priority>" . number_format($this->prio, 1) . "</priority>\n";
		$r .= "\t</url>\n";
		return $r;
	}
	function e_xml($string) {
		return str_replace(array( '&', '"', "'", '<', '>'), 
		array('&amp;', '&quot;', '&apos;' , '&lt;' , '&gt;'), $string);
	}
	
}

class B2XML_XMLE {
	
	var $xml;
	
	function B2XML_XMLE($xml) {
		$this->xml = $xml;
	}
	
	function rend() {
		return $this->xml;
	}
}
class B2XML_COM extends B2XML_XMLE {
	
	function rend() {
		return "<!-- " . $this->xml . " -->\n";
	}
}
