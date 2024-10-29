<?php

/*

Plugin Name: B2 XML Sitemaps

Plugin URI: http://www.b2foundry.com/products

Description: This plugin helps you generate XML sitemap autmoatically to help Search Engines like Google/Bing/Ask.com to index your site more efficiently.  

Version: 2.1

Author: B2 Foundry

Author URI: http://www.b2foundry.com/

*/



define('B2_XML_BASE', plugin_basename(__FILE__));

define('B2_XML_PATH', trailingslashit(dirname(__FILE__)));

$plugin_url = plugin_dir_url(__FILE__);

if(preg_match('/^https/',$plugin_url) && !preg_match('/^https/', get_bloginfo('url')))

    $_sd = preg_replace('/^https/', 'http', $plugin_url);

define('B2_XML_URL', $plugin_url);

define('B2_XML_PL_URL', 'options-general.php?page=' . B2_XML_BASE);

define('B2XML_VERSION', '2.0');



require_once 'includes/helper.php';

require_once 'includes/ajax.php';

setup_sitemap_directory();



class B2XML_EntryPoint{

	var $page_title = 'B2 XML Sitemap Generator';

	var $menu_title = 'B2 XML Sitemap';

	var $capability = 'level_10';

	var $engine = null;

	

	function B2XML_EntryPoint(){

		$this->b2xml_setup_memory();

		add_action('init', array(&$this, 'b2xml_setup'));

	}

	function b2xml_setup(){

		add_action(

			'admin_menu', 

			array(&$this, 'b2xml_register_settings_page')

		);

		add_action(

			'admin_print_scripts', 

			array(&$this,'b2xml_print_js')

		);

		add_action(

			'admin_print_styles', 

			array(&$this,'b2xml_print_css')

		);	

		add_filter(

			'plugin_row_meta', 

			array(&$this, 'b2xml_get_extra_links'),

			10,

			2

		);

		add_action(

			'delete_post', 

			array(&$this, 'b2xml_auto_build_check'),

			9999,

			1

		);

		add_action(

			'publish_post', 

			array(&$this, 'b2xml_auto_build_check'),

			9999,

			1

		);

		add_action(

			'publish_page', 

			array(&$this, 'b2xml_auto_build_check'),

			9999,

			1

		);

		add_action(

			'b2xml_cron_job', 

			array(&$this, 'b2xml_auto_build'),

			1,

			0

		);

		add_action(

			'b2xml_cron_rejob', 

			array(&$this, 'b2xml_build'),

			1,

			0

		);

		add_action(

			'do_robots', 

			array(&$this, 'b2xml_robo_build'),

			100,

			0

		);

	}



	/*

	 * Register the default Settings page in the Tools section of admin panel 

	 */

	function b2xml_register_settings_page(){

		if(function_exists('add_options_page')){

			add_options_page(

				$this->page_title,

				$this->menu_title,

				$this->capability,

				B2_XML_BASE,

				array(&$this, 'b2xml_laod_config_page')

			);

		}

	}

	

	function b2xml_print_js(){

		if(isset($_GET['page']) && $_GET['page'] == B2_XML_BASE){

			wp_enqueue_script(

				'b2xml_js', B2_XML_URL.'/includes/js/__.js',

				array('jquery')

			);

			wp_print_scripts('jquery-ui-core');

			wp_print_scripts('jquery-ui-sortable');

			wp_enqueue_script('postbox');

			wp_enqueue_script('dashboard');

			wp_enqueue_script('thickbox');

		}

	}

	

	function b2xml_print_css(){

		if(isset($_GET['page']) && $_GET['page'] == B2_XML_BASE){

			wp_enqueue_style('dashboard');

			wp_enqueue_style('thickbox');

			wp_enqueue_style('global');

			wp_enqueue_style('wp-admin');

			wp_enqueue_style('b2xml_css', B2_XML_URL . '/includes/styles/__.css');

		}

	}

	

	function b2xml_laod_config_page(){

		$this->b2xml_set_top();

		if(function_exists("wp_next_scheduled")) {

			$job = wp_next_scheduled('b2xml_cron_job');

			if($job){ //Scheduled

				$dif = (time() - $job) * (-1);

				if($dif <= 0) {

					$dm = 'Please wait. Your sitemap is being refreshed at the moment.';

				} else {

					$dm = str_replace(

						"%s",

						$dif,

						'Your sitemap will be refreshed in %s seconds.'

					);

				}

				$html = '<div class="updated">

							<strong><p>'. $dm . '</p></strong>

						</div>';

			}

		}

		if(get_option('blog_public') != 1) {

			$html .= '<div class="error">

						<p>

						' . 

						str_replace(

							"%s",

							"options-privacy.php",

							'Your website is currently blocking search engines! <a href="%s">Change Settings</a>.') . 

						'</p>

					</div>';

		}

		$status_data = &B2XML_Status::load_status();

		$id = 'b2xml';

		$heading = '';

		if($status_data == null){

			$heading = 'Generate Sitemap for the first time';

			$html .= '<ul>

						<li>

							You haven\'t created any sitemap yet. <a href="#" 

							class="button" id="btn_crt_xml_sitemap">Create Now</a>

						</li>

					</ul>';

		}else{

			$start_time = $status_data->get_start_time();

			$heading = 'Build Status';

			$html .= '<ul>';

			if($status_data->end_time !== 0){

				if($status_data->xml){

					if($status_data->xml_status){

						$readable = is_readable(

								$status_data->xml_path

							) ? filemtime($status_data->xml_path) : false;

						if($readable !== false){

							$html .= '<li>Last <a href="'. $status_data->xml_url . '">sitemap</a>' . 

								' was built on <strong>' . 

									date(get_option('date_format'),$readable) . ' ' . 

									date(get_option('time_format'),$readable) . 

									'</strong></li>';

						}else{

							$html .= '<li class="b2xml-error">

									An error occured while accessing the sitemap file you created last time</li>';

						}

					}

				}

				if($status_data->zip){

					if($status_data->zip_status){

						$readable = is_readable(

								$status_data->zip_path

							) ? filemtime($status_data->zip_path) : false;

						if($readable !== false){

							$html .= '<li>Last <a href="'. $status_data->zip_url . '">compressed sitemap</a>' . 

								' was built on <strong>' . 

									date(get_option('date_format'),$readable) . ' ' . 

									date(get_option('time_format'),$readable) . 

									'</strong></li>';

						}else{

							$html .= '<li class="b2xml-error">

									An error occured while accessing the sitemap compressed file you created last time</li>';

						}

					}

				}

				if($status_data->google){

					if($status_data->google_status){

						$html .= '<li><strong>Google</strong> was notified successfully about changes.</li>';

						$html .= '<li class="b2xml-state">Time taken: [' . $status_data->get_google_time() . ']</li>';

					} else {

						$html .= '<li class="b2xml-error">An error occured while notifying Google.</li>';

					}

				}

				if($status_data->bing){

					if($status_data->bing_status){

						$html .= '<li><strong>Bing</strong> was notified successfully about changes.</li>';

						$html .= '<li class="b2xml-state">Time taken: [' . $status_data->get_bing_time() . ']</li>';

					} else {

						$html .= '<li class="b2xml-error">An error occured while notifying Bing.</li>';

					}

				}

				/*
				 * Yahoo! is gone, only bing
				 * if($status_data->yahoo){

					if($status_data->yahoo_status){

						$html .= '<li><strong>Yahoo</strong> was notified successfully about changes.</li>';

						$html .= '<li class="b2xml-state">Time taken: [' . $status_data->get_yahoo_time() . ']</li>';

					} else {

						$html .= '<li class="b2xml-error">An error occured while notifying Yahoo.</li>';

					}

				}*/

				if($status_data->ask){

					if($status_data->ask_status){

						$html .= '<li><strong>Ask.com</strong> was notified successfully about changes.</li>';

						$html .= '<li class="b2xml-state">Time taken: [' . $status_data->get_ask_time() . ']</li>';

					} else {

						$html .= '<li class="b2xml-error">An error occured while notifying Ask.com.</li>';

					}

				}

				$elapsed = $status_data->get_time_elapsed();

				$mem_usage = $status_data->get_mem_usage();

				

				if($mem_usage > 0) {

					$html .= '<li>Memeory usage: <strong>'. $mem_usage . 'MB</strong></li>';

				} 

				$html .= '<a id="btn_rbld_xml_sitemap" href="#" class="button">

					Rebuild now</a>';

			}

		}

		$this->b2xml_metabox($id, $heading, $html);

		$heading = 'Sitemap Settings';

		$html = '<ul>

			<li>

				<input type="checkbox" id="auto_enabled" name="auto_enabled"';

		if($this->engine->g_option('auto_enabled') == true){

			$html .= ' checked="checked"';

		}

		$html .= ' /><label for="auto_enabled">Rebuild sitemap when contents change</label></li>';

		$html .= '<li>

				<input type="checkbox" id="robots" name="robots"';

		if($this->engine->g_option('robots') == true){

			$html .= ' checked="checked"';

		}

		$html .= ' /><label for="robots">Add sitemap URL to wordpress generated virtual robots.txt.</label></li>';

		$html .= '<li><input type="checkbox" id="ping" name="ping" ' . 

		($this->engine->g_option("ping") == true ? "checked=\"checked\"" : "") . 

				'/>

				<label for="ping">Notify Google about sitemap changes</label><br />

				<small>Registraton is not required. You can check crawl status by logging into your <a href="https://www.google.com/webmasters/tools" target="_blank">Google Webmaster Tools</a>. Use <a href="http://b2foundry.com/porudcts" target="_blank">B2SEO plugin</a> to easily verfiy your site with google.</small></li>';

		$html .= '<li><input type="checkbox" id="ping_bing" name="ping_bing" ' . 

		($this->engine->g_option("ping_bing") == true ? "checked=\"checked\"" : "") . 

				'/>

				<label for="ping_bing">Notify Bing about sitemap changes</label><br />

				<small>Registraton is not required. You can check crawl status by logging into your <a href="http://www.bing.com/webmaster" target="_blank">Bing Webmaster Tools</a>. Use <a href="http://b2foundry.com/porudcts" target="_blank">B2SEO plugin</a> to easily verfiy your site with bing.</small></li>';

		$html .= '<li><input type="checkbox" id="ping_ask" name="ping_ask" ' . 

		($this->engine->g_option("ping_ask") == true ? "checked=\"checked\"" : "") . 

				'/>

				<label for="ping_ask">Notify Ask.com about sitemap changes</label><br />

				<small>Registraton is not required. </small></li>';

		/*
		 * Yahoo! is gone!! 
		 * $html .= '<li><input type="checkbox" id="ping_yahoo" name="ping_yahoo" ' . 

		($this->engine->g_option("ping_yahoo") == true ? "checked=\"checked\"" : "") . 

				'/>

				<label for="ping_yahoo">Notify Yahoo about sitemap changes</label><br />

				<label for="yahoo_key">Yahoo Key: </label><input type="text" value="' . 

				$this->engine->g_option('yahoo_key') . 

				'" id="yahoo_key" name="yahoo_key" />

				<small>This is the default key provided by <a href="http://b2foundry.com" target="_blank">B2foundry</a>. However you can requrest your own <a href="http://developer.apps.yahoo.com/wsregapp/" target="_blank">here</a></small></li>';
		*/
		$this->b2xml_metabox($id, $heading, $html);

		

		$heading = 'Includes';

		$html = '<ul>

					<li>

						<label for="in_last_mode">

							<input type="checkbox" id="in_last_mode" name="in_last_mode" ' . 

							($this->engine->g_option("in_last_mode") == true ? "checked=\"checked\"":"") . 

							'/>Include last modified time<br /><small>Helps search engines to know when contents changed</small>

						</label>

					</li>

					<li>

						<label for="in_home">

							<input type="checkbox" id="in_home" name="in_home" ' . 

							($this->engine->g_option("in_home") == true ? "checked=\"checked\"":"") . 

							'/>Include homepage

						</label>

					</li>

					<li>

						<label for="in_posts">

							<input type="checkbox" id="in_posts" name="in_posts" ' . 

							($this->engine->g_option("in_posts") == true ? "checked=\"checked\"":"") . 

							'/>Include posts

						</label>

					</li>

					<li>

						<label for="in_posts_sub">

							<input type="checkbox" id="in_posts_sub" name="in_posts_sub" ' . 

							($this->engine->g_option("in_posts_sub") == true ? "checked=\"checked\"":"") . 

							'/>Include sub posts

						</label>

					</li>

					<li>

						<label for="in_pages">

							<input type="checkbox" id="in_pages" name="in_pages" ' . 

							($this->engine->g_option("in_pages") == true ? "checked=\"checked\"":"") . 

							'/>Include pages

						</label>

					</li>

					<li>

						<label for="in_cats">

							<input type="checkbox" id="in_cats" name="in_cats" ' . 

							($this->engine->g_option("in_cats") == true ? "checked=\"checked\"":"") . 

							'/>Include categories

						</label>

					</li>

					<li>

						<label for="in_arch">

							<input type="checkbox" id="in_arch" name="in_arch" ' . 

							($this->engine->g_option("in_arch") == true ? "checked=\"checked\"":"") . 

							'/>Include archives

						</label>

					</li>

					<li>

						<label for="in_auth">

							<input type="checkbox" id="in_auth" name="in_auth" ' . 

							($this->engine->g_option("in_auth") == true ? "checked=\"checked\"":"") . 

							'/>Include authors

						</label>

					</li>

					<li>

						<label for="in_tag">

							<input type="checkbox" id="in_tag" name="in_tag" ' . 

							($this->engine->g_option("in_tag") == true ? "checked=\"checked\"":"") . 

							'/>Include tags

						</label>

					</li>

				</ul>';

		$this->b2xml_metabox($id, $heading, $html);

		$heading = 'Excludes';

		$html = '<strong>Categories</strong><br /><ul class="ex-cat">%ld-cat%</ul>';

		$html .= '<div class="ex-list">

					<label for="excludes">Post/Page exclude list: <small>Comma separated list of post/page IDs</small><br />

					<input name="excludes" id="excludes" type="text" style="width:400px;" value="' . 

						implode(",", $this->engine->g_option("excludes")) . '" /></label><br />

				</div>';

		$this->b2xml_metabox($id, $heading, $html);

		$heading = 'Post Priority Settings';

		$html = '<p>Please select the priority calculation method:</p>

				<ul>

					<li><p><input type="radio" name="priority" id="priority_0" value="" '. 

					$this->engine->g_chck_val($this->engine->g_option("priority"), "") . 

					'" /> 

					<label for="priority_0">No priority method.</label><br />

					<small>All posts will have the default priority settings</small></p>

					</li>';

		for($i=0; $i<count($this->engine->priorities); $i++) {

			$html .= '<li><p><input type="radio" id="priority_' . $i .

				'" name="priority" value="' . $this->engine->priorities[$i] . 

				'" ' .  

				$this->engine->g_chck_val($this->engine->g_option("priority"),

				$this->engine->priorities[$i]) . 

				'" /> <label for="priority_' . $i . '">' . 

				call_user_func(array(&$this->engine->priorities[$i], 'get_name'))  . 

				'</label><br /><small>' . 

				call_user_func(array(&$this->engine->priorities[$i], 'get_desc')) . 

				'</small></p></li>';

		}

		$html .= '</ul>';

		$this->b2xml_metabox($id, $heading, $html);

		$heading = 'Priorities';

		$html = '<ul>

					<li>

						<label for="pr_home">

							<select id="pr_home" name="pr_home">' . 

							$this->engine->g_prio_val($this->engine->g_option("pr_home")) . 

							'</select>

							Homepage

						</label>

					</li>

					<li>

						<label for="pr_posts">

							<select id="pr_posts" name="pr_posts">' . 

							$this->engine->g_prio_val($this->engine->g_option("pr_posts")) . 

							'</select>

							Posts

						</label>

					</li>

					<li>

						<label for="pr_pages">

							<select id="pr_pages" name="pr_pages">' . 

							$this->engine->g_prio_val($this->engine->g_option("pr_pages")) . 

							'</select>

							Pages

						</label>

					</li>

					<li>

						<label for="pr_cats">

							<select id="pr_cats" name="pr_cats">' . 

							$this->engine->g_prio_val($this->engine->g_option("pr_cats")) . 

							'</select>

							Categories

						</label>

					</li>

					<li>

						<label for="pr_arch">

							<select id="pr_arch" name="pr_arch">' . 

							$this->engine->g_prio_val($this->engine->g_option("pr_arch")) . 

							'</select>

							Archives

						</label>

					</li>

					<li>

						<label for="pr_auth">

							<select id="pr_auth" name="pr_auth">' . 

							$this->engine->g_prio_val($this->engine->g_option("pr_auth")) . 

							'</select>

							Authors

						</label>

					</li>

					<li>

						<label for="pr_tags">

							<select id="pr_tags" name="pr_tags">' . 

							$this->engine->g_prio_val($this->engine->g_option("pr_tags")) . 

							'</select>

							Tags

						</label>

					</li>

				</ul>';

		$this->b2xml_metabox($id, $heading, $html);

		$heading = 'Frequencies';

		$html = '<p><strong>Note</strong><br />

					Please set change frequencies for each item. It is however possible that crawlers may crawl less frequent item more frequently and vice versa.

				</p>

				<ul>

					<li>

						<label for="home">

							<select id="home" name="home">' . 

							$this->engine->g_freq($this->engine->g_option("home")) . '</select>

							Homepage

						</label>

					</li>

					<li>

						<label for="posts">

							<select id="posts" name="posts">' . 

							$this->engine->g_freq($this->engine->g_option("posts")) . '</select>

							Posts

						</label>

					</li>

					<li>

						<label for="pages">

							<select id="pages" name="pages">' . 

							$this->engine->g_freq($this->engine->g_option("pages")) . '</select>

							Pages

						</label>

					</li>

					<li>

						<label for="cats">

							<select id="cats" name="cats">' . 

							$this->engine->g_freq($this->engine->g_option("cats")) . '</select>

							Categories

						</label>

					</li>

					<li>

						<label for="auth">

							<select id="auth" name="auth">' . 

							$this->engine->g_freq($this->engine->g_option("auth")) . '</select>

							Authors Archive

						</label>

					</li>

					<li>

						<label for="arch">

							<select id="arch" name="arch">' . 

							$this->engine->g_freq($this->engine->g_option("arch")) . '</select>

							Current Archive (This month archive)

						</label>

					</li>

					<li>

						<label for="arch_old">

							<select id="arch_old" name="arch_old">' . 

							$this->engine->g_freq($this->engine->g_option("arch_old")) . '</select>

							Old Arvhices

						</label>

					</li>

					<li>

						<label for="tags">

							<select id="tags" name="tags">' . 

							$this->engine->g_freq($this->engine->g_option("tags")) . '</select>

							Tags

						</label>

					</li>

				</ul>';

		$this->b2xml_metabox($id, $heading, $html);

		?>

		<p id="sys-msg"></p>

		<input type="submit" class="button-primary" id="btn_save_xml_settings" value="Save Settings" />

		<input type="submit" class="button" id="btn_reset_xml_settings" value="Reset Settings" />

		<?php

		$this->b2xml_set_bottom();

	}



	function b2xml_set_top(){

		?>

		<div class="wrap">

			<h2 id="b2xml-title">B2 XML Sitemap Settings</h2>

			<div class="postbox-container" style="width:68%;">

				<div class="metabox-holder">	

					<div class="meta-box-sortables">

		<?php

	}

	

	function b2xml_set_bottom(){

		?>

                    </div>

                </div>

            </div>

            <?php $this->b2xml_sidebar(); ?>

		</div>

		<?php

	}

	

	function b2xml_metabox($id, $title, $html, $innerID = false){

        ?>

            <div id="<?php echo $id; ?>" class="postbox">

                <div class="handlediv" title="Click to toggle"><br /></div>

                <h3 class="hndle"><span><?php echo $title; ?></span></h3>

                <div <?php echo ($innerID ? 'id="' . $id . '-1"' : ''); ?> 

					class="inside">

					<?php if(strpos($html, '%ld-cat%') === false){

						echo $html;

					}else{

						$temp = explode('%ld-cat%', $html);

						echo $temp[0];

						wp_category_checklist(0, 0, $this->engine->g_option("exclude_cats"),false);

						echo $temp[1];

					}

                    ?>

                </div>

            </div>

        <?php

	}

	

	function b2xml_sidebar(){

        ?>

            <div class="postbox-container" style="margin-left: 10px;width:30%;">

                <div class="metabox-holder">	

                    <div class="meta-box-sortables">

						<?php

						$_h = '<p>For any problems or suggestions please contact us at <a href="mailto:support@b2foundry.com?subject=[B2 XML Sitemap] Report">support@b2foundry.com</a>.</p>';

						$this->b2xml_metabox($this->_hk.'suggestions', 'Plugin Support', $_h);
						$_h = '<a href="http://issuu.com/b2foundry/docs/b2foundry-seo-b2xml?mode=window&backgroundColor=%23ffffff" target="_blank"><img style="width: 236px; height: auto; display: block; margin: 0 auto;" src="' . B2_XML_URL . 'b2xml.png" /></a>';
						$this->b2xml_metabox('insmanual', 'B2 XML Instruction Manual', $_h);
						$this->b2xml_metabox('Plugins','B2 Foundry\'s Products','<ul>
							<li><a href="http://www.b2foundry.com/products#b2-seo-plugin" target="_blank">B2 SEO</a></li>
							<li><a href="http://issuu.com/b2foundry/docs/basic-seo?mode=window&backgroundColor=%23ffffff" target="_blank">Beginner\'s Guide to SEO for WordPress</a></li>
							<li><a href="http://www.b2foundry.com" target="_blank">B2 Foundry\'s Web Design + Dev Services</a></li>
						</ul>');

						?>

                    </div>

                    <br/><br/><br/>

                </div>

            </div>

        <?php

	}

	

	function b2xml_get_extra_links($links, $file){

		if($file == B2_XML_BASE){

			$links[] = '<a href="options-general.php?page=' . B2_XML_BASE .'">Settings</a>';

		}

		return $links;

	}

	

	function b2xml_auto_build_check($args){

		$this->engine->check_auto_build($args);

	}

	

	function b2xml_auto_build(){

		$this->engine->generate_xml_sitemap();

	}

	

	function b2xml_build(){

		$this->engine->create_now();

	}

	

	function b2xml_robo_build(){

		$this->engine->d_robo();

	}

	

	function b2xml_check_manual_build(){

		$this->engine->check_manual_build();

	}

	

	/* 

	 * Set Memory Limits, Include core @Arne

	 */

	function b2xml_setup_memory(){

		$memory = abs(intval(@ini_get('memory_limit')));

		if($memory && $memory < 64) {

			@ini_set('memory_limit', '64M');

		}

		$time = abs(intval(@ini_get("max_execution_time")));

		if($time != 0 && $time < 120) {

			@set_time_limit(120);

		}

		$this->b2xml_load_libraries();

	}

	/*

	 * Load essential libraries

	 */

	function b2xml_load_libraries(){

		require_once(B2_XML_PATH . 'includes/data_class.php');

		require_once(B2_XML_PATH . 'includes/priorities.php');

		require_once(B2_XML_PATH . 'includes/b2xml_builder.php');

		

		B2XML_Builder::enable_xml();

		$this->engine = &B2XML_Builder::get_instance();

		$this->engine->init();

	}

}

if(defined('ABSPATH') && defined('WPINC')) {

	global $b2xml_entry;

	$b2xml_entry = &new B2XML_EntryPoint();

}

