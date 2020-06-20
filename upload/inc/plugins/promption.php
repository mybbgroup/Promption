<?php
/**
 *
 * @category   MyBB Plugin
 * @package    Promption
 * @author     effone <me@eff.one>
 */

if (!defined('IN_MYBB')) {
    die('Direct access prohibited.');
}
$plugins->add_hook('usercp_options_end', 'promption_prepare');
$plugins->add_hook('xmlhttp', 'promption_process');

function promption_info()
{
    return array(
        'name' => 'Promption',
        'description' => "Instantly update profile options as you change.",
        'website' => 'https://mybb.group/thread-Promption',
        'author' => 'effone',
        'authorsite' => 'https://eff.one',
        'version' => '1.0',
        'compatibility' => '18*',
        'codename' => 'promption',
    );
}

function promption_activate()
{
}

function promption_deactivate()
{
}

function promption_prepare(){
	global $headerinclude;
	$headerinclude .= "
	<script type=\"text/javascript\">
	if(use_xmlhttprequest == 1){
		$(function(){
			$(\"[name='regsubmit']\").hide();
			$(document).on('change keyup','select, input', function(){
				$.ajax({
					url: 'xmlhttp.php',
					type: 'post',
					data: {
						my_post_key: my_post_key,
						action: 'promption',
						optname: $(this).attr('name'),
						optval: $(this).is('select') ? $(this).val() : this.checked ? 1 : 0
					},
					complete: function (r)
					{
						var response = r.responseText.split('|');
						$.jGrowl(response[0], {theme:'jgrowl_' + response[1]});
					}
				});
			});
		});
	}
	</script>
	";
}

function promption_process(){
	global $mybb, $lang;
	if($mybb->user['uid'] && $mybb->input['action'] == "promption" && $mybb->request_method == "post" && !empty($mybb->input['optname'])){
		if(!verify_post_check($mybb->input['my_post_key'], true)){
			die($lang->invalid_post_code."|error");
		}

		$optname = $mybb->get_input('optname');
		switch ($optname) {
			case 'timezoneoffset':
				global $db;
				$optname = 'timezone';
				$optval = $db->escape_string($mybb->get_input('timezoneoffset'));
				break;
			
			case 'language':
			case 'threadmode':
				$optval = $mybb->get_input('optval');
				break;
			
			case 'tpp':
			case 'ppp':
				if(!$mybb->settings['user'.$optname.'options']){
					$lang->load('promption');
					die($lang->sprintf($lang->setting_disabled, $optname)."|error");
				}
				$optval = $mybb->get_input('optval', MyBB::INPUT_INT);
				break;
			
			default:
				$optval = $mybb->get_input('optval', MyBB::INPUT_INT);
				break;
		}
		
		$basics = array("uid", "style", "dateformat", "timeformat", "timezone", "language", "usergroup", "additionalgroups");
		$options = array("allownotices", "hideemail", "subscriptionmethod", "invisible", "dstcorrection", "threadmode", "showimages", "showvideos", "showsigs", "showavatars", "showquickreply", "receivepms", "pmnotice", "receivefrombuddy", "daysprune", "tpp", "ppp", "showcodebuttons", "sourceeditor", "pmnotify", "buddyrequestspm", "buddyrequestsauto", "showredirect", "classicpostbit");
		
		$user = array();
		foreach(array_merge($basics, $options) as $optn){
			$optv = ($optn == $optname) ? $optval : $mybb->user[$optn];
			if(in_array($optn, $options)){
				$user['options'][$optn] = $optv;
			} else {
				$user[$optn] = $optv;
			}
		}
		
		// Set up user handler.
		require_once MYBB_ROOT."inc/datahandlers/user.php";
		$userhandler = new UserDataHandler("update");
		$userhandler->set_data($user);
		$lang->load('promption');
		if(!$userhandler->validate_user())
		{
			die($lang->sprintf($lang->setting_failed, $optname)."|error");
		}
		else
		{
			$userhandler->update_user();
			$mybb->user[$optname] = $optval;
			die($lang->sprintf($lang->setting_updated, $optname)."|success");
		}
	}
}