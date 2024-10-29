<?php

class B2XML_Status{

	var $start_time = 0;
	var $end_time = 0;
	var $has_modified = true;
	var $mem_usage = 0;
	var $last_post = 0;
	var $last_time = 0;
	
	function B2XML_Status(){
		$exists = get_option('b2xml_status');
		if(!$exists){
			add_option('b2xml_status', '', null, 'no');
		}
		$this->update_status();
	}
	
	function &load_status(){
		$status = @get_option('b2xml_status');
		if(is_a($status,'B2XML_Status')) 
			return $status;
		else 
			return null;
	}
	
	function update_status(){
		update_option('b2xml_status', $this);
	}
	
	function end_process($has_modified = false){
		$this->end_time = $this->get_time_float();
		$this->set_mem_usage();
		$this->has_modified = $has_modified;
		$this->update_status();
	}
	
	function get_time_float(){
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}
	
	function set_mem_usage() {
		if(function_exists("memory_get_peak_usage")) {
			$this->mem_usage = memory_get_peak_usage(true);
		} else if(function_exists("memory_get_usage")) {
			$this->mem_usage =  memory_get_usage(true);
		}
	}
	
	function get_mem_usage() {
		return round($this->mem_usage / 1024 / 1024, 2);
	}
	
	function save_step($post_count) {
		$this->set_mem_usage();
		$this->last_post = $post_count;
		$this->last_time = $this->get_time_float();
		$this->update_status();
	}
	
	function get_time_elapsed() {
		return round($this->end_time - $this->start_time, 2);
	}
	
	function get_start_time() {
		return round($this->start_time, 2);
	}
	
	function get_last_time() {
		return round($this->last_time - $this->start_time,2);
	}
	
	function get_last_post() {
		return $this->last_post;
	}
	
	var $xml = false;
	var $xml_status = false;
	var $xml_path = '';
	var $xml_url = '';
	
	function start_xml($path, $url) {
		$this->xml = true;
		$this->xml_path = $path;
		$this->xml_url = $url;
		$this->update_status();
	}
	
	function end_xml($success) {
		$this->xml_status = $success;
		$this->update_status();
	}
	
	
	var $zip = false;
	var $zip_status = false;
	var $zip_path = '';
	var $zip_url = '';
	
	function start_zip($path, $url) {
		$this->zip = true;
		$this->zip_path = $path;
		$this->zip_url = $url;
		$this->update_status();
	}
	
	function end_zip($success) {
		$this->zip_status = $success;
		$this->update_status();
	}
	
	var $google = false;
	var $google_url = '';
	var $google_status = false;
	var $gog_start_time = 0;
	var $gog_end_time = 0;

	function start_google_ping($url) {
		$this->google = true;
		$this->google_url = $url;
		$this->gog_start_time = $this->get_time_float();
		$this->update_status();
	}
	
	function end_google_ping($success) {
		$this->gog_end_time = $this->get_time_float();
		$this->google_status = $success;
		$this->update_status();
	}
	
	function get_google_time() {
		return round($this->gog_end_time - $this->gog_start_time, 2);
	}
	
	var $yahoo = false;
	var $yahoo_url = '';
	var $yahoo_status = false;
	var $yahoo_start_time = 0;
	var $yahoo_end_time = 0;
	
	function start_yahoo_ping($url) {
		$this->yahoo = true;
		$this->yahoo_url = $url;
		$this->yahoo_start_time = $this->get_time_float();
		$this->update_status();
	}
	
	function end_yahoo_ping($success) {
		$this->yahoo_end_time = $this->get_time_float();
		$this->yahoo_status = $success;
		$this->update_status();
	}
	
	function get_yahoo_time() {
		return round($this->yahoo_end_time - $this->yahoo_start_time, 2);
	}
	
	var $ask = false;
	var $ask_url = '';
	var $ask_status = false;
	var $ask_start_time = 0;
	var $ask_end_time = 0;
	
	function start_ask_ping($url) {
		$this->ask = true;
		$this->ask_url = $url;
		$this->ask_start_time = $this->get_time_float();
		$this->update_status();
	}
	
	function end_ask_ping($success) {
		$this->ask_end_time = $this->get_time_float();
		$this->ask_status = $success;
		$this->update_status();
	}
	
	function get_ask_time() {
		return round($this->ask_end_time - $this->ask_start_time, 2);
	}
	
	var $bing = false;
	var $bing_url = '';
	var $bing_status = false;
	var $bing_start_time = 0;
	var $bing_end_time = 0;
	
	function start_bing_ping($url) {
		$this->bing = true;
		$this->bing_url = $url;
		$this->bing_start_time = $this->get_time_float();
		$this->update_status();
	}
	
	function end_bing_ping($success) {
		$this->bing_end_time = $this->get_time_float();
		$this->bing_status = $success;
		$this->update_status();
	}
	
	function get_bing_time() {
		return round($this->bing_end_time - $this->bing_start_time, 2);
	}
}