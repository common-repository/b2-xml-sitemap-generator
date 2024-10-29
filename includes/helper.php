<?php
/*
 * Setup XML Sitemap Directory
 */
function setup_sitemap_directory(){
	$wpUpload = wp_upload_dir();
	if(!file_exists($wpUpload['basedir'].'/b2xml_sitemap/')){
	    $b2xml_sitemap_hnd = @mkdir($wpUpload['basedir'].'/b2xml_sitemap/');
		$b2xml_dir = $wpUpload['basedir'].'/b2xml_sitemap/';
		$stat = @stat(dirname($b2xml_dir));
		$dp = $stat['mode'] & 0007777;
		@chmod(dirname($b2xml_dir), $dp);
		$b2xml_dir = str_replace('\\', '/', $b2xml_dir);
		define('B2_XML_SITEMAP_DIR', $b2xml_dir);
		define('B2_XML_SITEMAP_URL', $wpUpload['baseurl'] . '/b2xml_sitemap/');
    }else{
		$b2xml_dir = $wpUpload['basedir'].'/b2xml_sitemap/';
		$b2xml_dir = str_replace('\\', '/', $b2xml_dir);
		define('B2_XML_SITEMAP_DIR', $b2xml_dir);
		define('B2_XML_SITEMAP_URL', $wpUpload['baseurl'] . '/b2xml_sitemap/');
	}
}