<?php

class B2XML_PRIO_B{
	var $total_comments = 0;
	var $total_posts = 0;
	function get_name() {
		return "";
	}
	function get_desc() {
		return "";
	}
	function B2XML_PRIO_B($total_comments,$total_posts) {
		$this->total_comments = $total_comments;
		$this->total_posts = $total_posts;
	}
	function get_post_priority($postID, $comment_count) {
		return 0;
	}
}
class B2XML_PRIO_BCC extends B2XML_PRIO_B{
	function get_name() {
		return "Comment Count";
	}
	function get_desc() {
		return "Sets priority based on number of comments per post";
	}
	function B2XML_PRIO_BCC($total_commetns,$total_posts) {
		parent::B2XML_PRIO_B($total_commetns, $total_posts);
	}
	function get_post_priority($postID,$comment_count) {
		$prio = 0;
		if($this->total_commetns > 0 && $comment_count > 0) {
			$prio = round(($comment_count*100/$this->total_commetns)/100, 1);
		} else {
			$prio = 0;
		}
		return $prio;
	}
}
class B2XML_PRIO_BAC extends B2XML_PRIO_B{
	var $average = 0.0;
	function get_name() {
		return "Average Comment Count";
	}
	function get_desc() {
		return "Sets priority based on average number of comments";
	}
	function B2XML_PRIO_BAC($total_commetns,$total_posts) {
		parent::B2XML_PRIO_B($total_commetns, $total_posts);
		if($this->total_commetns > 0 && $this->total_posts > 0) {
			$this->average = (double) $this->total_commetns / $this->total_posts;
		}
	}
	function get_post_priority($postID,$comment_count) {
		$prio = 0;
		if($this->average == 0) {
			if($comment_count > 0)	
				$prio = 1;
			else 
				$prio = 0;
		} else {
			$prio = $comment_count/$this->average;
			if($prio > 1) 
				$prio = 1;
			else if($prio < 0) 
				$prio = 0;
		}
		return round($prio,1);
	}
}