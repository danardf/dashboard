<?php /* $Id: page.parking.php 2243 2006-08-12 17:13:17Z p_lindheimer $ */
//Copyright (C) 2006 Astrogen LLC 
//
//This program is free software; you can redistribute it and/or
//modify it under the terms of the GNU General Public License
//as published by the Free Software Foundation; either version 2
//of the License, or (at your option) any later version.
//
//This program is distributed in the hope that it will be useful,
//but WITHOUT ANY WARRANTY; without even the implied warranty of
//MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//GNU General Public License for more details.


$dispnum = 'sysinfo'; //used for switch on config.php
$action = isset($_REQUEST['action'])?$_REQUEST['action']:'';

$quietmode = isset($_REQUEST['quietmode'])?$_REQUEST['quietmode']:'';
$info = isset($_REQUEST['info'])?$_REQUEST['info']:false;

$title="freePBX: Sysinfo Info";
$message="System Info";

if (isset($_REQUEST['showall'])) {
	$_SESSION['syslog_showall'] = (bool)$_REQUEST['showall'];
}

//require_once('functions.inc.php');

define('BAR_WIDTH_LEFT', 400);
define('BAR_WIDTH_RIGHT', 200);

// AJAX update intervals (in seconds)
define('STATS_UPDATE_TIME', 6); // update interval for system uptime information
define('INFO_UPDATE_TIME', 30); // update interval for system uptime information

function draw_graph($text, $real_units, $val, $total = 100, $classes = null, $show_percent = true, $total_width = 200) {
	if ($classes == null) {
		$classes = array(
			0=>'graphok',
			70=>'graphwarn',
			90=>'grapherror',
		);
	}
	if ($total == 0) {
		$percent = ($val == 0) ? 0 : 100;
	} else {
		$percent = round($val/$total*100);
	}
	
	$graph_class = false;
	foreach ($classes as $limit=>$class) {
		if (!$graph_class) {
			$graph_class = $class;
		}
		if ($limit <= $percent) {
			$graph_class = $class;
		} else {
			break;
		}
	}
	$width = $total_width * ($percent/100);
	if ($width > $total_width) { 
		$width = $total_width;
	}
	
	$tooltip = $text.": ".$val.$real_units." / ".$total.$real_units." (".$percent."%)";
	$display_value = ($show_percent ? $percent."%" : $val.$real_units); 
	
	$out = "<div class=\"databox graphbox\" style=\"width:".$total_width."px;\">\n";
	$out .= " <div class=\"bargraph ".$graph_class."\" style=\"width:".$width."px;\"></div>\n";
	$out .= " <div class=\"dataname\">".$text."</div>\n";
	$out .= " <div class=\"datavalue\"><a href=\"#\" title=\"".$tooltip."\">".$display_value."</a></div>\n";
	$out .= "</div>\n";
	
	return $out;
}

function draw_status_box($text, $status, $tooltip = false, $total_width = 200) {
	switch ($status) {
		case "ok":
			$status_text = _("OK");
			$class = "graphok";
		break;
		case "warn":
			$status_text = _("Warn");
			$class = "graphwarn";
		break;
		case "error":
			$status_text = _("ERROR");
			$class = "grapherror";
		break;
		case "disabled":
			$status_text = "Disabled";
			$class = "";
		break;
	}
	if ($tooltip !== false) {
		$status_text = '<a href="#" title="'.$tooltip.'">'.$status_text.'</a>';
	}
	
	$out = "<div class=\"databox statusbox\" style=\"width:".$total_width."px;\">\n";
	$out .= " <div class=\"dataname\">".$text."</div>\n";
	$out .= " <div id=\"datavalue_".str_replace(" ","_",$text)."\" class=\"datavalue ".$class."\">".$status_text."</div>\n";
	$out .= "</div>\n";
	
	return $out;
}

function draw_box($text, $value, $total_width = 200) {
	$tooltip = $text.": ".$value;
	
	$out = "<div class=\"databox\" style=\"width:".$total_width."px;\">\n";
	$out .= " <div class=\"dataname\">".$text."</div>\n";
	$out .= " <div class=\"datavalue\"><a href=\"#\" title=\"".$tooltip."\">".$value."</a></div>\n";
	$out .= "</div>\n";
	
	return $out;
}

function time_string($seconds) {
    if ($seconds == 0) {
        return "0 "._("minutes");
    }

    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;

    $hours = floor($minutes / 60);
    $minutes = $minutes % 60;

    $days = floor($hours / 24);
    $hours = $hours % 24;
	
	$weeks = floor($days / 7);
    $days = $days % 7;
	
	$output = array();
	if ($weeks) { 
		$output[] = $weeks." ".($weeks == 1 ? _("week") : _("weeks"));
	}
	if ($days) { 
		$output[] = $days." ".($days == 1 ? _("days") : _("days"));
	}
	if ($hours) { 
		$output[] = $hours." ".($hours == 1 ? _("hour") : _("hours"));
	}
	if ($minutes) { 
		$output[] = $minutes." ".($minutes == 1 ? _("minute") : _("minutes"));
	}
	
    return implode(", ",$output);
}

function show_sysstats() {
	global $sysinfo;
	$out = '';
	
	$out .= "<h4>"._("Processor")."</h4>";
	$loadavg = $sysinfo->loadavg(true);
	$out .= draw_box(_("Load Average"), $loadavg['avg'][0]);
	$out .= draw_graph(_("CPU"), "", number_format($loadavg['cpupercent'],2), 100);
	
	$out .= "<h4>"._("Memory")."</h4>";
	$memory = $sysinfo->memory();
	$app_memory = isset($memory["ram"]["app"]) ? $memory["ram"]["app"] : $memory["ram"]["total"] - $memory["ram"]["t_free"];
	$out .= draw_graph(_("App Memory"), "MB", number_format($app_memory/1024,2), number_format($memory["ram"]["total"]/1024,2));
	$out .= draw_graph(_("Swap"), "MB", number_format(($memory["swap"]["total"]-$memory["swap"]["free"])/1024,2), number_format($memory["swap"]["total"]/1024,2));
	
	$out .= "<h4>"._("Disks")."</h4>";
	foreach ($sysinfo->filesystems() as $fs) {	
		$out .= draw_graph($fs["mount"], "GB", number_format($fs["used"]/1024/1024, 2), number_format($fs["size"]/1024/1024, 2));
	}
	
	$out .= "<h4>"._("Networks")."</h4>";
	foreach ($sysinfo->network() as $net_name=>$net) {
		$net_name = trim($net_name);
		if ($net_name == 'lo' || $net_name == 'sit0') continue;
		
		$tx = new average_rate_calculator($_SESSION["netstats"][$net_name]["tx"], 10); // 30s max age
		$rx = new average_rate_calculator($_SESSION["netstats"][$net_name]["rx"], 10); // 30s max age
		
		$rx->add( $net["rx_bytes"] );
		$tx->add( $net["tx_bytes"] );
		
		$out .= draw_box($net_name." "._("receive"), number_format($rx->average()/1000,2)." KB/s");
		$out .= draw_box($net_name." "._("transmit"), number_format($tx->average()/1000,2)." KB/s");
	}
	return $out;
}

function show_aststats() {
	global $amp_conf;
	global $astinfo;
	$out = '';
	
	$channels = $astinfo->get_channel_totals();
	// figure out max_calls
	
	// guess at the max calls: number of users
	if (!isset($_SESSION["calculated_max_calls"])) {
		// set max calls to either MAXCALLS in amportal.conf, or the number of users in the system
		$_SESSION["calculated_max_calls"] = (isset($amp_conf["MAXCALLS"]) ? $amp_conf["MAXCALLS"] : count(core_users_list()));
	}
	// we currently see more calls than we guessed, increase it
	if ($channels['total_calls'] > $_SESSION["calculated_max_calls"]) {
		$_SESSION["calculated_max_calls"] = $channels['total_calls'];
	}
	$max_calls = $_SESSION["calculated_max_calls"];
	
	$classes = array(0=>'graphok');
	$max_chans = $max_calls * 2;
	
	$out .= "<h4>"._("PBX Statistics")."</h4>";
	$out .= draw_graph(_('Total active calls'), '', $channels['total_calls'], $max_calls, $classes , false, BAR_WIDTH_LEFT);
	$out .= draw_graph(_('Internal calls'), '', $channels['internal_calls'], $max_calls, $classes , false, BAR_WIDTH_LEFT);
	$out .= draw_graph(_('External calls'), '', $channels['external_calls'], $max_calls, $classes , false, BAR_WIDTH_LEFT);
	$out .= draw_graph(_('Total active channels'), '', $channels['total_channels'], $max_chans, $classes , false, BAR_WIDTH_LEFT);
	
	$out .= "<h4>"._("Connections")."</h4>";
	
	$peers = $astinfo->get_peers();
	$out .= draw_graph(_('Phones Online'), '', $peers['sip_online']+$peers['iax2_online'], $peers['sip_total']+$peers['iax2_total'], $classes, false, BAR_WIDTH_LEFT);
	
	$regs = $astinfo->get_registrations();
	if ($regs['total'] > 0) {
		$out .= draw_graph(_('Provider Registrations'), '', $regs['registered'], $regs['total'], $classes , false, BAR_WIDTH_LEFT);
	}
	return $out;
}

function show_sysinfo() {
	global $sysinfo;
	global $astinfo;
	$out = '<table>';
	/*
	$out .= '<tr><th>Distro:</th><td>'.$sysinfo->distro().'</td></tr>';
	$out .= '<tr><th>Kernel:</th><td>'.$sysinfo->kernel().'</td></tr>';
	$cpu = $sysinfo->cpu_info();
	$out .= '<tr><th>CPU:</th><td>'.$cpu['model'].' '.$cpu['cpuspeed'].'</td></tr>';
	*/
	
	$out .= '<tr><th>'._('System Uptime').':</th><td>'.time_string($sysinfo->uptime()).'</td></tr>';
	$ast_uptime = $astinfo->get_uptime();
	$out .= '<tr><th>'._('Asterisk Uptime').':</th><td>'.$ast_uptime['system'].'</td></tr>';
	$out .= '<tr><th>'._('Last Reload').':</th><td>'.$ast_uptime['reload'].'</td></tr>';
	
	$out .= '</table>';
	return $out;
}

function show_procinfo() {
	global $procinfo;
	global $astinfo;
	global $amp_conf;
	$out = '';
	
	// asterisk
	if ($astver = $astinfo->check_asterisk()) {
		$out .= draw_status_box(_("Asterisk"), "ok", _('Asterisk is running: '.$astver));
	} else {
		$out .= draw_status_box(_("Asterisk"), "error", _('Asterisk is not running, this is a critical service!'));
	}
	
	// asterisk proxy (optionally)
	if (isset($amp_conf['ASTMANAGERPROXYPORT'])) {
		if ($procinfo->check_port($amp_conf['ASTMANAGERPROXYPORT'])) {
			$out .= draw_status_box(_("Manager Proxy"), "ok", _('Asterisk Manager Proxy is running'));
		} else {
			$out .= draw_status_box(_("Manager Proxy"), "warn", _('Asterisk Manager Proxy is not running, FreePBX will fall back to using Asterisk directly, which may result in poor performance'));
		}		
	}
	
	// fop
	if ($procinfo->check_fop_server()) {
		$out .= draw_status_box(_("Op Panel"), "ok", _('FOP Operator Panel Server is running'));
	} else {
		if (isset($amp_conf['FOPRUN']) && $amp_conf['FOPRUN']) {
			// it should be running
			$out .= draw_status_box(_("Op Panel"), "warn", _('FOP Operator Panel Server is not running, you will not be able to use the operator panel, but the system will run fine without it.'));
		} else {
			$out .= draw_status_box(_("Op Panel"), "disabled", _('FOP Operator Panel is disabled in amportal.conf'));
		}
	}
	
	// mysql
	if ($amp_conf['AMPDBENGINE'] == "mysql") {
		if ($procinfo->check_mysql()) {
			$out .= draw_status_box(_("MySQL"), "ok", _('MySQL Server is running'));
		} else {
			$out .= draw_status_box(_("MySQL"), "error", _('MySQL Server is not running, this is a critical service for the web interface and call logs!'));
		}
	}
	
	// web always runs .. HOWEVER, we can turn it off with dhtml
	$out .= draw_status_box(_("Web Server"), "ok", _('Web Server is running'));
	
	// ssh	
	if ($procinfo->check_port(22)) {
		$out .= draw_status_box(_("SSH Server"), "ok", _('SSH Server is running'));
	} else {
		$out .= draw_status_box(_("SSH Server"), "warn", _('SSH Server is not running, you will not be able to connect to the system console remotely'));
	}
	return $out;
}

function show_syslog(&$md5_checksum) {
	global $db;
	$out = '';
	$checksum = '';
	
	$notify_classes = array(
		NOTIFICATION_TYPE_CRITICAL => 'notify_critical',
		NOTIFICATION_TYPE_SECURITY => 'notify_security',
		NOTIFICATION_TYPE_UPDATE => 'notify_update',
		NOTIFICATION_TYPE_ERROR => 'notify_error',
		NOTIFICATION_TYPE_WARNING => 'notify_warning',
		NOTIFICATION_TYPE_NOTICE => 'notify_notice',
	);
	
	$notify =& notifications::create($db);
	
	$showall = (isset($_SESSION['syslog_showall']) ? $_SESSION['syslog_showall'] : false);
	
	$items = $notify->list_all($showall);
	
	if (count($items)) {
		$out .= '<ul>';
		foreach ($items as $item) {
			$checksum .= $item['module'].$item['id']; // checksum, so it is only updated on the page if this has changed
			
			$domid = "notify_item_".str_replace(' ','_',$item['module']).'_'.str_replace(' ','_',$item['id']);
			
			$out .= '<li id="'.$domid.'" ';
			if (isset($notify_classes[$item['level']])) {
				$out .= ' class="'.$notify_classes[$item['level']].'"';
			}
			$out .= '><div>';
			
			$out .= '<h4 class="syslog_text"><span>'.$item['display_text'].'</span>';
			if (!$item['reset']) {
				$out .= '<a class="notify_ignore_btn" title="'._('Ignore this').'" '.
				        'onclick="hide_notification(\''.$domid.'\', \''.$item['module'].'\', \''.$item['id'].'\');">'.
				        '<img src="'.dirname($_SERVER['PHP_SELF']).'/images/notify_delete.png" width="16" height="16" border="0" alt="'._('Ignore this').'" /></a>';
			}
			$out .= '</h4>';
			
			$out .= '<div class="syslog_detail">';
			$out .= nl2br($item['extended_text']);
			$out .= '<br/><span>'.sprintf('Added %s ago', time_string(time() - $item['timestamp'])).'<br/>'.
			        '('.$item['module'].'.'.$item['id'].')</span>';
			$out .= '</div>';
			
			$out .= '</div></li>';
		}
		$out .= '</ul>';
	} else {
		if ($showall) {
			$out .= _('No notifications');
		} else {
			$out .= _('No new notifications');
		}
	}
	
	$md5_checksum = md5($checksum);
	
	$out .= '<div id="syslog_button">';
	if ($showall) {
		$out .= '<a href="#" onclick="changeSyslog(0);">'._('show new').'</a>';
	} else {
		$out .= '<a href="#" onclick="changeSyslog(1);">'._('show all').'</a>';
	}
	$out .= '</div>';
	return $out;
}

function do_syslog_ack() {	
	global $db;
	$notify =& notifications::create($db);
	
	if (isset($_REQUEST['module']) && $_REQUEST['id']) {
		$notify->reset($_REQUEST['module'], $_REQUEST['id']);
	}
}

/********************************************************************************************/


define("IN_PHPSYSINFO", "1");
define("APP_ROOT", dirname(__FILE__).'/phpsysinfo');
include APP_ROOT."/common_functions.php";
include APP_ROOT."/class.".PHP_OS.".inc.php";
include_once "common/json.inc.php";
include dirname(__FILE__)."/class.astinfo.php";
include dirname(__FILE__)."/class.average_rate_calculator.php";
include dirname(__FILE__)."/class.procinfo.php";

$sysinfo = new sysinfo;
$astinfo = new astinfo($astman);
$procinfo = new procinfo;


if (!$quietmode) {
	?>
	
	<script language="javascript">
	$(document).ready(function(){
		$.ajaxTimeout( 20000 );
		scheduleInfoUpdate();
		scheduleStatsUpdate();
		
		makeSyslogClickable();
	});
	
	function makeSyslogClickable() {
		$('#syslog h4 span').click(function() {
			$(this).parent().next('div').slideToggle('fast');
		});
	}
	
	var syslog_md5;
	function updateInfo() {
		$.ajax({
			type: 'GET',
			url: "<?php echo $_SERVER["PHP_SELF"]; ?>?type=tool&display=<?php echo $module_page; ?>&quietmode=1&info=info", 
			dataType: 'json',
			success: function(data) {
				$('#procinfo').html(data.procinfo);
				$('#sysinfo').html(data.sysinfo);
				// only update syslog div if the md5 has changed
				if (syslog_md5 != data.syslog_md5) {
					$('#syslog').html(data.syslog);
					makeSyslogClickable();
					syslog_md5 = data.syslog_md5;
				}
				scheduleInfoUpdate();
			},
			error: function(reqObj, status) {
				$('#datavalue_Web_Server').text("ERROR");
				$('#datavalue_Web_Server').removeClass("graphok");
				$('#datavalue_Web_Server').addClass("grapherror");
			},
		});
	}
	function scheduleInfoUpdate() {
		setTimeout('updateInfo();',<?php echo INFO_UPDATE_TIME; ?>);
	}
	
	
	function updateStats() {
		$.ajax({
			type: 'GET',
			url: "<?php echo $_SERVER["PHP_SELF"]; ?>?type=tool&display=<?php echo $module_page; ?>&quietmode=1&info=stats", 
			dataType: 'json',
			success: function(data) {
				$('#sysstats').html(data.sysstats);
				$('#aststats').html(data.aststats);
				scheduleStatsUpdate();
			},
			error: function(reqObj, status) {
				$('#datavalue_Web_Server').text("ERROR");
				$('#datavalue_Web_Server').removeClass("graphok");
				$('#datavalue_Web_Server').addClass("grapherror");
				$('#syslog').prepend('<div class="warning">Warning: Update timed out<br/></div>');
			},
		});
	}
	function scheduleStatsUpdate() {
		setTimeout('updateStats();',<?php echo STATS_UPDATE_TIME; ?>);
	}
	
	
	function changeSyslog(showall) {
		$('#syslog_button').text('<?php echo _('loading...'); ?>');
		$('#syslog ul').animate({color:'#ccc'}, 'fast');
		$('#syslog').load("<?php echo $_SERVER["PHP_SELF"]; ?>?type=tool&display=<?php echo $module_page; ?>&quietmode=1&info=syslog&showall="+showall,{}, function() {
			makeSyslogClickable();
		});
	}

	function hide_notification(domid, module, id) {
		$('#'+domid).fadeOut('slow');
		$.post('config.php', {display:'<?php echo $module_page; ?>', quietmode:1, info:'syslog_ack', module:module, id:id});
	}
	</script>

	<h2>Dashboard</h2>
	</div>
	<div id="dashboard">
	<?php
	echo '<div id="sysinfo-left">';
	
	// regular page
	echo '<div id="syslog" class="infobox">';
	echo show_syslog($syslog_md5);
	// syslog_md5 is used by javascript updateInfo() to determine if the syslog div contents have changed
	echo '<script type="text/javascript"> syslog_md5 = "'.$syslog_md5.'"; </script>';
	//echo "log goes here<br/><br/><br/>";
	echo '</div>';
	
	echo '<div id="aststats" class="infobox">';
	echo show_aststats();
	echo '</div>';
	
	
	echo '<div id="sysinfo" class="infobox">';
	echo show_sysinfo();
	echo '</div>';
	
	
	
	echo '</div><div id="sysinfo-right">';
	
	
	
	echo '<div id="sysstats" class="infobox">';
	echo show_sysstats();
	echo '</div>';
	
	echo '<div id="procinfo" class="infobox">';
	echo show_procinfo();
	echo '</div>';
	
	echo '<div style="clear:both;"></div>';
	
	echo '</div></div>'; // #sysinfo, #sysinfo-right
	
	echo '<div class="content">';
} else {
	// Handle AJAX updates
	
	switch ($info) {
		case "sysstats":
			echo show_sysstats();
		break;
		case "aststats":
			echo show_aststats();
		break;
		case "procinfo":
			echo show_procinfo();
		break;
		case 'sysinfo':
			echo show_sysinfo();
		break;
		case 'syslog':
			echo show_syslog($syslog_md5);	
			// syslog_md5 is used by javascript updateInfo() to determine if the syslog div contents have changed
			echo '<script type="text/javascript"> syslog_md5 = "'.$syslog_md5.'"; </script>';
		break;
		case 'syslog_ack':
			do_syslog_ack();
		break;
		
		
		case 'info':
			$json = new Services_JSON();
			echo $json->encode(
				array(
					'procinfo'=>show_procinfo(),
					'sysinfo'=>show_sysinfo(),
					'syslog'=>show_syslog($syslog_md5),
					'syslog_md5'=>$syslog_md5,
				)
			);
		break;
		case 'stats':
			$json = new Services_JSON();
			echo $json->encode(
				array(
					'sysstats'=>show_sysstats(),
					'aststats'=>show_aststats(),
				)
			);
		break;
		case 'all':
			$json = new Services_JSON();
			echo $json->encode(
				array(
					'sysstats'=>show_sysstats(),
					'aststats'=>show_aststats(),
					'procinfo'=>show_procinfo(),
					'sysinfo'=>show_sysinfo(),
					'syslog'=>show_syslog(),
				)
			);
		break;
	}
}

?>
