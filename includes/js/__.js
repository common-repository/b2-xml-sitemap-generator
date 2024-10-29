jQuery(document).ready(function($){
	$("#btn_save_xml_settings").click(function(e){
		e.preventDefault();
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'b2_xml_ajax', 
				b2xml_action: '0x001', 
				auto_enabled: get_checked_val('auto_enabled'),
				robots: get_checked_val('robots'),
				ping: get_checked_val('ping'),
				ping_bing: get_checked_val('ping_bing'),
				ping_ask: get_checked_val('ping_ask'),
				ping_yahoo: get_checked_val('ping_yahoo'),
				yahoo_key: $('#yahoo_key').val(),
				in_last_mode: get_checked_val('in_last_mode'),
				in_home: get_checked_val('in_home'),
				in_posts: get_checked_val('in_posts'),
				in_posts_sub: get_checked_val('in_posts_sub'),
				in_pages: get_checked_val('in_pages'),
				in_cats: get_checked_val('in_cats'),
				in_arch: get_checked_val('in_arch'),
				in_auth: get_checked_val('in_auth'),
				in_tag: get_checked_val('in_tag'),
				exclude_cats: get_ex_cats('post_category'),
				excludes: $('#excludes').val(),
				priority: $('input[name="priority"]:checked').val(),
				pr_home: $('#pr_home').val(),
				pr_posts: $('#pr_posts').val(),
				pr_pages: $('#pr_pages').val(),
				pr_cats: $('#pr_cats').val(),
				pr_arch: $('#pr_arch').val(),
				pr_auth: $('#pr_auth').val(),
				pr_tags: $('#pr_tags').val(),
				home: $('#home').val(),
				posts: $('#posts').val(),
				pages: $('#pages').val(),
				cats: $('#cats').val(),
				arch: $('#arch').val(),
				arch: $('#arch_old').val(),
				auth: $('#auth').val(),
				tags: $('#tags').val()
			},
			success: function(r){
				$('#sys-msg').html('<strong>' + r + '</strong>');
			},
			error: function(e){
				alert("error");
			}
		});
	});
	$("#btn_reset_xml_settings").click(function(e){
		e.preventDefault();
		var hnd = $(this);
		var ok = confirm("Are you sure?");
		if(ok){
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'b2_xml_ajax', 
					b2xml_action: '0x002'
				},success: function(r){
					$('#sys-msg').html('<strong>' + r + '</strong>');
				}
			});
		}else{
			return;
		}
	});
	$("#btn_rbld_xml_sitemap,#btn_crt_xml_sitemap").click(function(e){
		e.preventDefault();
		var hnd = $(this);
		hnd.text("Building sitemap...");
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'b2_xml_ajax', 
				b2xml_action: '0x003'
			},success: function(r){
				hnd.text("Built. Redirecting...");
				window.location = r;
			}
		});
	});
});
function get_ex_cats(elm){
	var x = '';
	jQuery("input[name='" + elm + "[]']").each(function(i){
		var y = jQuery(this).attr("id");
		if(get_checked_val(y) == "1"){
			x += jQuery(this).val() + ",";
		}
	});
	if(x != ''){
		x = x.substr(0, x.length - 1);
	}
	return x;
}
function get_checked_val(elm){
	return jQuery("#" + elm + ":checked").length > 0 ? "1" : "0";
}