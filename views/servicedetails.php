<?php //service details view page


// Nagios V-Shell
// Copyright (c) 2010 Nagios Enterprises, LLC.
// Written by Mike Guthrie <mguthrie@nagios.com>
//
// LICENSE:
//
// This work is made available to you under the terms of Version 2 of
// the GNU General Public License. A copy of that license should have
// been provided with this software, but in any event can be obtained
// from http://www.fsf.org.
//
// This work is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
// General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
// 02110-1301 or visit their web page on the internet at
// http://www.fsf.org.
//
//
// CONTRIBUTION POLICY:
//
// (The following paragraph is not intended to limit the rights granted
// to you to modify and distribute this software under the terms of
// licenses that may apply to the software.)
//
// Contributions to this software are subject to your understanding and acceptance of
// the terms and conditions of the Nagios Contributor Agreement, which can be found
// online at:
//
// http://www.nagios.com/legal/contributoragreement/
//
//
// DISCLAIMER:
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
// INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A
// PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
// HOLDERS BE LIABLE FOR ANY CLAIM FOR DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
// OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE
// GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, STRICT LIABILITY, TORT (INCLUDING
// NEGLIGENCE OR OTHERWISE) OR OTHER ACTION, ARISING FROM, OUT OF OR IN CONNECTION
// WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.


function get_service_details($dets)
{
	global $NagiosUser;

	$page="

	<h3>".gettext('Service Status Detail')."</h3>
	<div class='detailWrapper'>

	<h4>".gettext('Service').": {$dets['Service']}</h4>
	<h4>".gettext('Host').": <a href='index.php?type=hostdetail&name_filter={$dets['Host']}' title='".gettext('Host Details')."'>{$dets['Host']}</a></h4>
	<h5 class=\"margin-bottom\">".gettext('Member of').": {$dets['MemberOf']}</h5>
	<h6><a href='index.php?type=services&host_filter={$dets['Host']}' title='".gettext('See All Services For This Host')."'>".gettext('See All Services For This Host')."</a></h6>

	<div class='detailcontainer'>
	<fieldset class='servicedetails'>
	<legend>".gettext('Advanced Details')."</legend>
	<table class='details'>
		<tr><td class=\"servicedetail_key\">" . gettext('Service State') . "</td><td class='{$dets['State']}'>{$dets['State']}</td></tr>
	";

	if ($NagiosUser->if_has_authKey('authorized_for_configuration_information')) {
		$page .="<tr><td class=\"servicedetail_key\">" . gettext('Check Command') . "</td><td><div class='td_maxwidth'>{$dets['CheckCommand']}</div></td></tr>";
	}

	$page.="
		<tr><td class=\"servicedetail_key\">" . gettext('Plugin Output')     . "</td><td><div class='td_maxwidth'>{$dets['Output']}</div></td></tr>
		<tr><td class=\"servicedetail_key\">" . gettext('State Type')        . "</td><td>{$dets['StateType']}</td></tr>
		<tr><td class=\"servicedetail_key\">" . gettext('Current Check')     . "</td><td>{$dets['CurrentCheck']}</td></tr>
		<tr><td class=\"servicedetail_key\">" . gettext('Next Check')        . "</td><td>{$dets['NextCheckFmt']}</td></tr>
		<tr><td class=\"servicedetail_key\">" . gettext('Check Type')        . "</td><td>{$dets['CheckType']}</td></tr>
		<tr><td class=\"servicedetail_key\">" . gettext('Last Check')        . "</td><td>{$dets['LastCheckFmt']} ago</td></tr>
		<tr><td class=\"servicedetail_key\">" . gettext('Last State Change') . "</td><td>{$dets['LastStateChangeFmt']} ago</td></tr>
		<tr><td class=\"servicedetail_key\">" . gettext('Last Notification') . "</td><td>{$dets['LastNotification']}</td></tr>
		<tr><td class=\"servicedetail_key\">" . gettext('Check Latency')     . "</td><td>{$dets['CheckLatency']}</td></tr>
		<tr><td class=\"servicedetail_key\">" . gettext('Execution Time')    . "</td><td>{$dets['ExecutionTime']}</td></tr>
		<tr><td class=\"servicedetail_key\">" . gettext('State Change')      . "</td><td>{$dets['StateChange']}</td></tr>
		<tr><td class=\"servicedetail_key\">" . gettext('Performance Data')  . "</td><td><div class='td_maxwidth'>{$dets['PerformanceData']}</div></td></tr>

	</table>

	</fieldset>
	</div><!-- end detailcontainer -->

	<div class='rightContainer'>
	";

	if (!$NagiosUser->if_has_authKey('authorized_for_read_only')) {
	$page .= "<fieldset class='attributes'>
		<legend>".gettext('Service Attributes')."</legend>
		<table>
			<tr>
				<td class='{$dets['ActiveChecks']}'>".gettext('Active Checks').": {$dets['ActiveChecks']}</td>
				<td class=\"center\"><a href='{$dets['CmdActiveChecks']}'><img src='views/images/action_small.gif' title='".gettext('Toggle Active Checks')."' class='iconLink' alt='Toggle' /></a></td>
			</tr>
			<tr>
				<td class='{$dets['PassiveChecks']}'>".gettext('Passive Checks').": {$dets['PassiveChecks']}</td>
				<td class=\"center\"><a href='{$dets['CmdPassiveChecks']}'><img src='views/images/action_small.gif' title='".gettext('Toggle Passive Checks')."' class='iconLink' alt='Toggle' /></a></td>
			</tr>
			<tr>
				<td class='{$dets['Obsession']}'>".gettext('Obsession').": {$dets['Obsession']}</td>
				<td class=\"center\"><a href='{$dets['CmdObsession']}'><img src='views/images/action_small.gif' title='".gettext('Toggle Obsession')."' class='iconLink' alt='Toggle' /></a></td>
			</tr>
			<tr>
				<td class='{$dets['Notifications']}'>".gettext('Notifications').": {$dets['Notifications']}</td>
				<td class=\"center\"><a href='{$dets['CmdNotifications']}'><img src='views/images/action_small.gif' title='".gettext('Toggle Notifications')."' class='iconLink' alt='Toggle' /></a></td>
			</tr>
			<tr>
				<td class='{$dets['FlapDetection']}'>".gettext('Flap Detection').": {$dets['FlapDetection']}</td>
				<td class=\"center\"><a href='{$dets['CmdFlapDetection']}'><img src='views/images/action_small.gif' title='".gettext('Toggle Flap Detection')."' class='iconLink' alt='Toggle' /></a></td>
			</tr>
		</table>
	</fieldset>

		<!-- Nagios Core Command Table -->

		<fieldset class='corecommands'>
			<legend>".gettext('Core Commands')."</legend>
			<table>

				<tr>
					<td>".gettext('Send custom notification')."</td>
					<td class=\"center\"><a href='{$dets['CmdCustomNotification']}' title='".gettext('Send Custom Notification')."'><img src='views/images/notification.gif' class='iconLink' alt='Notification' /></a></td>
				</tr>
				<tr>
					<td>".gettext('Schedule downtime')."</td>
					<td class=\"center\"><a href='{$dets['CmdScheduleDowntime']}' title='".gettext('Schedule Downtime')."'><img src='views/images/downtime.png' class='iconLink' alt='Downtime' /></a></td></tr>
				<tr>
					<td>".gettext('Reschedule Next Check')."</td>
					<td class=\"center\"><a href='{$dets['CmdScheduleChecks']}' title='".gettext('Schedule Check')."'><img src='views/images/schedulecheck.png' class='iconLink' alt='Schedule' /></a></td>
				</tr>
				<tr>
					<td>{$dets['AckTitle']}</td>
					<td class=\"center\"><a href='{$dets['CmdAcknowledge']}' title='{$dets['AckTitle']}'><img src='views/images/ack.png' class='iconLink' alt='Acknowledge' /></a></td>
				</tr>
			</table>

			<div class=\"\" style=\"margin-top: 0.5em;\">
				<a class='label' href='{$dets['CoreLink']}' title='".gettext('See This Service In Nagios Core')."'>".gettext('See This Service In Nagios Core')."</a>
			</div>
		</fieldset>"; //end if authorized for commands
	}

	$page .="
	</div><!-- end rightContainer -->
	</div><!-- end detailWrapper -->

	<!-- begin comment table -->
	<div class='commentTable'>
	";

	if(!$NagiosUser->if_has_authKey('authorized_for_read_only'))
	{
		$page.="
		<h5 class='commentTable'>".gettext('Comments')."</h5>
		<p class='commentTable'><a class='label' href='{$dets['AddComment']}' title='".gettext('Add Comment')."'>".gettext('Add Comment')."</a></p>
		<table class='commentTable'><tr><th>".gettext('Author')."</th><th>".gettext('Entry Time')."</th><th>".gettext('Comment')."</th><th>".gettext('Actions')."</th></tr>
		";
		//print service comments in table rows if any exist
		//see display_functions.php for function
		$page .= get_service_comments($dets['Host'], $dets['Service']);
		//close comment table
		$page .= '</table>';
	}
	$page.='</div><br />';
	return $page;
}


?>
