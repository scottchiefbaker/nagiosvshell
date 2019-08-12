<?php //host details view page

//this page will be an include for the command_router.php controller file
		//expecting $dets array of processed status details
//include additional items by first adding them to the arrays on command_router.php

function get_host_details($dets)
{
	global $NagiosUser;

	if (!empty($_GET['debug'])) {
		k($dets);
	}

	$page="

	<h3>".gettext('Host Status Detail')."</h3>
	<div class='detailWrapper'>
	<h4 id=\"host\" data-target=\"host\" data-value=\"" . $dets['Host']. "\">" . gettext('Host') . ": {$dets['Host']}</h4>
	<h5 class=\"margin-bottom\">".gettext('Member of').": {$dets['MemberOf']}</h5>
	<h6><a href='index.php?type=services&host_filter={$dets['Host']}' title='".gettext('See All Services For This Host')."'>".gettext('See All Services For This Host')."</a></h6>
	<div class='detailcontainer'>
	<fieldset class='hostdetails'>
	<legend>".gettext('Advanced Details')."</legend>
	<table>
		<tr><td class=\"hostdetail_key\">" . gettext('Current State')      . "</td><td class='{$dets['State']}'>{$dets['State']}</td></tr>
		<tr><td class=\"hostdetail_key\">" . gettext('Status Information') . "</td><td><div class='td_maxwidth'>{$dets['StatusInformation']}</div></td></tr>
		<tr><td class=\"hostdetail_key\">" . gettext('State Type')         . "</td><td>{$dets['StateType']}</td></tr>
		<tr><td class=\"hostdetail_key\">" . gettext('Current Check')      . "</td><td>{$dets['CurrentCheck']}</td></tr>
		<tr><td class=\"hostdetail_key\">" . gettext('Next Check')         . "</td><td>{$dets['NextCheckFmt']}</td></tr>
		<tr><td class=\"hostdetail_key\">" . gettext('Check Type')         . "</td><td>{$dets['CheckType']}</td></tr>
		<tr><td class=\"hostdetail_key\">" . gettext('Last State Change')  . "</td><td>{$dets['LastStateChangeFmt']}</td></tr>
		<tr><td class=\"hostdetail_key\">" . gettext('Last Check')         . "</td><td>{$dets['LastCheckFmt']}</td></tr>
		<tr><td class=\"hostdetail_key\">" . gettext('Last Notification')  . "</td><td>{$dets['LastNotification']}</td></tr>
		<tr><td class=\"hostdetail_key\">" . gettext('Check Latency')      . "</td><td>{$dets['CheckLatency']}</td></tr>
		<tr><td class=\"hostdetail_key\">" . gettext('Execution Time')     . "</td><td>{$dets['ExecutionTime']}</td></tr>
		<tr><td class=\"hostdetail_key\">" . gettext('State Change')       . "</td><td>{$dets['StateChange']}</td></tr>
		<tr><td class=\"hostdetail_key\">" . gettext('Performance Data')   . "</td><td><div class='td_maxwidth'>{$dets['PerformanceData']}</div></td></tr>

	</table>

	</fieldset>
	</div><!-- end detailcontainer -->

	<div class='rightContainer'>

	";

	if (strtolower($dets['Notifications']) === "enabled") {
		$notify_check = "checked";
	} else {
		$notify_check = "";
	}

	if (strtolower($dets['FlapDetection']) === "enabled") {
		$flap_check = "checked";
	} else {
		$flap_check = "";
	}

	if (strtolower($dets['ActiveChecks']) === "enabled") {
		$active_check = "checked";
	} else {
		$active_check = "";
	}

	if (strtolower($dets['PassiveChecks']) === "enabled") {
		$passive_check = "checked";
	} else {
		$passive_check = "";
	}

	$obsess_check = '';

	/////////////////////////////////////////////////////////////////////////////////

	if(!$NagiosUser->if_has_authKey('authorized_for_read_only'))
	$page.="

	<fieldset class='attributes'>
		<legend>".gettext('Service Attributes')."</legend>
		<table>
			<tr>
				<td class=\"" . strtolower($dets['ActiveChecks']) . "\">".gettext('Active Checks').": {$dets['ActiveChecks']}</td>
				<td class=\"center\">
					<div class=\"\">
						<label class=\"switch\">
							<input data-cmd=\"_HOST_CHECK\" type=\"checkbox\" class=\"input_toggle\" " . $active_check . " />
							<span class=\"slider round\"></span>
						</label>
					</div>
				</td>
			</tr>
			<tr>
				<td class=\"" . strtolower($dets['PassiveChecks']) . "\">".gettext('Passive Checks').": {$dets['PassiveChecks']}</td>
				<td class=\"center\">
					<div class=\"\">
						<label class=\"switch\">
							<input data-cmd=\"_PASSIVE_HOST_CHECKS\" type=\"checkbox\" class=\"input_toggle\" " . $passive_check . " />
							<span class=\"slider round\"></span>
						</label>
					</div>
				</td>
			</tr>
			<!--
			<tr>
				<td class=\"" . strtolower($dets['Obsession']) . "\">".gettext('Obsession').": {$dets['Obsession']}</td>
				<td class=\"center\">
					<div class=\"\">
						<label class=\"switch\">
							<input data-cmd=\"_OBSESS\" type=\"checkbox\" class=\"input_toggle\" " . $obsess_check . " />
							<span class=\"slider round\"></span>
						</label>
					</div>
				</td>
			</tr>
			-->
			<tr>
				<td class=\"" . strtolower($dets['Notifications']) . "\">".gettext('Notifications').": {$dets['Notifications']}</td>
				<td class=\"center\">
					<div class=\"\">
						<label class=\"switch\">
							<input data-cmd=\"_HOST_NOTIFICATIONS\" type=\"checkbox\" class=\"input_toggle\" " . $notify_check . " />
							<span class=\"slider round\"></span>
						</label>
					</div>
				</td>
			</tr>
			<tr>
				<td class=\"" . strtolower($dets['FlapDetection']) . "\">".gettext('Flap Detection').": {$dets['FlapDetection']}</td>
				<td class=\"center\">
					<div class=\"\">
						<label class=\"switch\">
							<input data-cmd=\"_HOST_FLAP_DETECTION\" type=\"checkbox\" class=\"input_toggle\" " . $flap_check . " />
							<span class=\"slider round\"></span>
						</label>
					</div>
				</td>
			</tr>
		</table>
	</fieldset>


		<!-- Nagios Core Command Table -->
		<fieldset class='corecommands'>
			<legend>".gettext('Core Commands')."</legend>
			<table>
				<tr>
					<td>".gettext('Locate host on map')."</td>
					<td class=\"center\"><a href='{$dets['MapHost']}' title='".gettext('Map Host')."'><img src='views/images/statusmapballoon.png' class='iconLink' alt='Map' /></a></td>
				</tr>
				<tr>
					<td>".gettext('Send custom notification')."</td>
					<td class=\"center\"><a href='{$dets['CmdCustomNotification']}' title='".gettext('Send Custom Notification')."'><img src='views/images/notification.gif' class='iconLink' alt='Notification' /></a></td></tr>
				<tr>
					<td>".gettext('Schedule downtime')."</td>
					<td class=\"center\"><a href='{$dets['CmdScheduleDowntime']}' title='".gettext('Schedule Downtime')."'><img src='views/images/downtime.png' class='iconLink' alt='Downtime' /></a></td></tr>
				<tr>
					<td>".gettext('Schedule downtime for this host and all services')."</td>
					<td class=\"center\"><a href='{$dets['CmdScheduleDowntimeAll']}' title='".gettext('Schedule Recursive Downtime')."'><img src='views/images/downtime.png' class='iconLink' alt='Downtime' /></a></td></tr>
				<tr>
					<td>".gettext('Schedule a check for all services of this host')."</td>
					<td class=\"center\"><a href='{$dets['CmdScheduleChecks']}' title='".gettext('Schedule Check')."'><img src='views/images/schedulecheck.png' class='iconLink' alt='Schedule' /></a></td></tr>
				<tr>
					<td>{$dets['AckTitle']}</td>
					<td class=\"center\"><a href='{$dets['CmdAcknowledge']}' title='{$dets['AckTitle']}'><img src='views/images/ack.png' class='iconLink' alt='Acknowledge' /></a></td>
				</tr>
			</table>

			<div class=\"\" style=\"margin-top: 0.5em;\">
				<a class='label' href='{$dets['CoreLink']}' title='".gettext('See This Host In Nagios Core')."'>".gettext('See This Host In Nagios Core')."</a>
			</div>
		</fieldset>
		"; //end if authorized for object

	$page.="
	</div><!-- end rightContainer -->
	</div><!-- end detailWrapper -->


	<!-- begin comment table -->
	<div class='commentTable'>
	";

	if(!$NagiosUser->if_has_authKey('authorized_for_read_only'))
	{
		$page .="
		<h5 class='commentTable'>".gettext('Comments')."</h5>
		<p class='commentTable'><a class='label' href='{$dets['AddComment']}' title='".gettext('Add Comment')."'>".gettext('Add Comment')."</a></p>

		<table class='commentTable'><tr><th>".gettext('Author')."</th><th>".gettext('Entry Time')."</th><th>".gettext('Comment')."</th><th>".gettext('Actions')."</th></tr>
		";
		//host comments table from Nagios core

		//print host comments in table rows if any exist
		//see display_functions.php for function
		$page .= get_host_comments($dets['Host']);
		//close comment table
		$page .= '</table>';
	}
	$page.='</div><br />';
	return $page;
}

?>
