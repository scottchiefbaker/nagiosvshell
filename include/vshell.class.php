<?php

$base_dir = dirname(__FILE__) . "/../";
$base_dir = realpath($base_dir) . "/";

// Include the old include file to get most of the pieces
require_once("$base_dir/include/inc.inc.php");

require_once("$base_dir/include/krumo/class.krumo.php");
require_once("$base_dir/include/global-functions.php");
require_once("$base_dir/include/sluz/sluz.class.php");

class vshell {

	public $sluz           = null;
	public $tac_data       = [];
	public $start_time     = 0;
	public $host_state_map = [ 0 => 'UP', 1 => 'DOWN', 2 => 'UNREACHABLE', 3 => 'UNKNOWN' ];
	public $svc_state_map  = [ 0 => 'OK', 1 => 'WARNING', 2 => 'CRITICAL', 3 => 'UNKNOWN' ];

	function __construct() {
		// Load language and other sitewide settings
		init_vshell();

		$this->start_time = microtime(1);

		global $NagiosUser;

		// Make sure we're logged in before showing any data
		if (!$NagiosUser->get_username()) {
			error_out("You must be logged in to view this page", 19313);
		}

		$this->sluz = new sluz;
		$this->sluz->assign('tac_data', get_tac_data());

		$icons = $this->get_icons();
		$this->sluz->assign('icons', $icons);
	}

	function get_icons() {
		$ret = [];

		$ret['list'] = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-card-list" viewBox="0 0 16 16"><path d="M14.5 3a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h13zm-13-1A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-13z"/><path d="M5 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 5 8zm0-2.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5zm0 5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5zm-1-5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0zM4 8a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0zm0 2.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0z"/></svg>';
		$ret['clock'] = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clock-fill" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/></svg>';
		$ret['trash'] = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash-fill" viewBox="0 0 16 16"><path d="M2.5 1a1 1 0 0 0-1 1v1a1 1 0 0 0 1 1H3v9a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V4h.5a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H10a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1H2.5zm3 4a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-1 0v-7a.5.5 0 0 1 .5-.5zM8 5a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-1 0v-7A.5.5 0 0 1 8 5zm3 .5v7a.5.5 0 0 1-1 0v-7a.5.5 0 0 1 1 0z"/></svg>';
		$ret['bell'] = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-bell-fill" viewBox="0 0 16 16"><path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2zm.995-14.901a1 1 0 1 0-1.99 0A5.002 5.002 0 0 0 3 6c0 1.098-.5 6-2 7h14c-1.5-1-2-5.902-2-7 0-2.42-1.72-4.44-4.005-4.901z"/></svg';
		$ret['calendar_add'] = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-calendar-plus-fill" viewBox="0 0 16 16"><path d="M4 .5a.5.5 0 0 0-1 0V1H2a2 2 0 0 0-2 2v1h16V3a2 2 0 0 0-2-2h-1V.5a.5.5 0 0 0-1 0V1H4V.5zM16 14V5H0v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2zM8.5 8.5V10H10a.5.5 0 0 1 0 1H8.5v1.5a.5.5 0 0 1-1 0V11H6a.5.5 0 0 1 0-1h1.5V8.5a.5.5 0 0 1 1 0z"/></svg>';
		$ret['wrench'] = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-wrench" viewBox="0 0 16 16"><path d="M.102 2.223A3.004 3.004 0 0 0 3.78 5.897l6.341 6.252A3.003 3.003 0 0 0 13 16a3 3 0 1 0-.851-5.878L5.897 3.781A3.004 3.004 0 0 0 2.223.1l2.141 2.142L4 4l-1.757.364L.102 2.223zm13.37 9.019.528.026.287.445.445.287.026.529L15 13l-.242.471-.026.529-.445.287-.287.445-.529.026L13 15l-.471-.242-.529-.026-.287-.445-.445-.287-.026-.529L11 13l.242-.471.026-.529.445-.287.287-.445.529-.026L13 11l.471.242z"/></svg>';
		$ret['globe'] = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-globe" viewBox="0 0 16 16"><path d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm7.5-6.923c-.67.204-1.335.82-1.887 1.855A7.97 7.97 0 0 0 5.145 4H7.5V1.077zM4.09 4a9.267 9.267 0 0 1 .64-1.539 6.7 6.7 0 0 1 .597-.933A7.025 7.025 0 0 0 2.255 4H4.09zm-.582 3.5c.03-.877.138-1.718.312-2.5H1.674a6.958 6.958 0 0 0-.656 2.5h2.49zM4.847 5a12.5 12.5 0 0 0-.338 2.5H7.5V5H4.847zM8.5 5v2.5h2.99a12.495 12.495 0 0 0-.337-2.5H8.5zM4.51 8.5a12.5 12.5 0 0 0 .337 2.5H7.5V8.5H4.51zm3.99 0V11h2.653c.187-.765.306-1.608.338-2.5H8.5zM5.145 12c.138.386.295.744.468 1.068.552 1.035 1.218 1.65 1.887 1.855V12H5.145zm.182 2.472a6.696 6.696 0 0 1-.597-.933A9.268 9.268 0 0 1 4.09 12H2.255a7.024 7.024 0 0 0 3.072 2.472zM3.82 11a13.652 13.652 0 0 1-.312-2.5h-2.49c.062.89.291 1.733.656 2.5H3.82zm6.853 3.472A7.024 7.024 0 0 0 13.745 12H11.91a9.27 9.27 0 0 1-.64 1.539 6.688 6.688 0 0 1-.597.933zM8.5 12v2.923c.67-.204 1.335-.82 1.887-1.855.173-.324.33-.682.468-1.068H8.5zm3.68-1h2.146c.365-.767.594-1.61.656-2.5h-2.49a13.65 13.65 0 0 1-.312 2.5zm2.802-3.5a6.959 6.959 0 0 0-.656-2.5H12.18c.174.782.282 1.623.312 2.5h2.49zM11.27 2.461c.247.464.462.98.64 1.539h1.835a7.024 7.024 0 0 0-3.072-2.472c.218.284.418.598.597.933zM10.855 4a7.966 7.966 0 0 0-.468-1.068C9.835 1.897 9.17 1.282 8.5 1.077V4h2.355z"/></svg>';

		return $ret;
	}

	function fetch($tpl) {
		$this->sluz->assign("__child_tpl", $tpl);

		$total = microtime(1) - $this->start_time;
		$total *= 1000;

		$this->sluz->assign('query_time_ms', intval($total));

		$skin = "tpls/skin.stpl";
		$ret  = $this->sluz->fetch($skin);

		if (!empty($_GET['debug'])) {
			k($this->sluz->tpl_vars);
		}

		return $ret;
	}

	function __destruct() {

	}

	function get_all_hosts($state_filter = "", $name_filter = "", $host_filter = "") {
		global $NagiosData;

		// The host data comes from two places so we have to merge them
		$hosts = hosts_and_services_data("hosts", $state_filter, $name_filter, $host_filter);
		$props = $NagiosData->properties['hosts_objs'];

		$ret = [];
		foreach ($hosts as $x) {
			$hn = $x['host_name'] ?? "";

			$prop = $props[$hn] ?? [];
			$y    = array_merge($prop, $x);

			$ret[$hn] = $y;
		}

		return $ret;
	}

	function get_all_services($state_filter = "", $name_filter = "", $host_filter = "") {
		$svcs  = hosts_and_services_data("services", $state_filter, $name_filter, $host_filter);
		$hosts = $this->get_all_hosts();

		$ret = [];
		foreach ($svcs as $x) {
			$hn = $x['host_name'] ?? "";

			// Find the host associated with this service
			$y  = $hosts[$hn] ?? [];

			$x['x_host_state_str'] = $y['state_str'] ?? "";
			$x['x_host_address']   = $y['address']   ?? '';

			$ret[$hn][] = $x;
		}

		return $ret;
	}

	function get_service_details($host_name, $svc_name) {
		$x   = $this->parse_nagios_status_file(OBJECTSFILE);
		$one = $x['service'][$host_name][$svc_name] ?? [];

		$y   = $this->parse_nagios_status_file(STATUSFILE);
		$two = $y['servicestatus'][$host_name][$svc_name] ?? [];

		$comments = $y['servicecomment'][$host_name][$svc_name] ?? [];

		$ret = array_merge($one, $two);
		$ret['comments'] = $comments;

		$state_id         = $ret['current_state'] ?? -1;
		$ret['state_str'] = $this->svc_state_map[$state_id];

		return $ret;
	}

	function get_host_data($name, $include_svcs = false) {
		global $NagiosData;

		// Merge the current status and the config data
		$x   = $this->parse_nagios_status_file(OBJECTSFILE);
		$one = $x['host'][$name] ?? [];
		$y   = $this->parse_nagios_status_file(STATUSFILE);
		$two = $y['hoststatus'][$name] ?? [];

		$ret = array_merge($one, $two);

		// Get the comments for this host
		$comments              = $y['hostcomment'][$name] ?? [];
		$ret['comments']       = $comments;

		// Get the groups this host is in
		$ret['host_groups']    = $this->get_hostgroups($name);
		$ret['host_group_str'] = join(", ", $ret['host_groups']);

		// Human string for the state
		$state_id         = $ret['current_state'] ?? -1;
		$ret['state_str'] = $this->host_state_map[$state_id];

		return $ret;
	}

	function get_hostgroups($host_filter = '') {
		$ret = [];

		// Get all the groups and their children
		$z   = $this->parse_nagios_status_file(OBJECTSFILE);
		$raw = $z['hostgroup'];

		// Loop through the list
		foreach ($raw as $x) {
			$name  = $x['hostgroup_name'] ?? "";
			$ret[] = $name;
		}

		// Loop through the above and find all the groups that contain that host
		if ($host_filter) {
			$data = $ret;
			$ret  = [];

			foreach ($raw as $x) {
				$name       = $x['hostgroup_name'] ?? "";
				$member_str = $x['members'] ?? "";
				$members    = preg_split("/,/", $member_str);
				$in_group   = in_array($host_filter, $members);

				if ($in_group) {
					$ret[] = $name;
				}
			}
		}

		ksort($ret, SORT_FLAG_CASE | SORT_NATURAL);

		return $ret;
	}

	function get_hostgroups_details() {
		global $NagiosData;
		$ret = [];

		// Get all the groups and their children
		$groups = $NagiosData->getProperty('hostgroups') ?? [];
		ksort($groups, SORT_FLAG_CASE | SORT_NATURAL);

		foreach ($groups as $group_name => $x) {
			foreach ($x as $host_name) {
				$details                      = $this->get_host_data($host_name);
				$ret[$group_name][$host_name] = $details;
			}
		}

		return $ret;
	}


	function get_hostgroup_members($name) {
		global $NagiosData;
		$ret = [];

		// Get all the groups and their children
		$groups = $NagiosData->getProperty('hostgroups') ?? [];
		ksort($groups, SORT_FLAG_CASE | SORT_NATURAL);

		$ret = $groups[$name] ?? [];

		return $ret;
	}

	// Get the raw configuration data as a hash
	// Note: This is a potential security leak so it's disabled for now
	function get_config_data() {
		return [];

		$types = [
			'hosts_objs',
			'services_objs',
			'hostgroups_objs',
			'servicegroups_objs',
			'timeperiods',
			'contacts',
			'contactgroups',
			'commands',
		];

		$ret = [];
		foreach ($types as $x) {
			$ret[$x] = object_data($x, '');
		}

		return $ret;
	}

	function parse_nagios_status_file($file) {
		$start = microtime(1);
		$fp    = fopen($file, 'r');

		if (!$fp) {
			error_out("Unable to open '$file' for reading");
		}

		// Basic memoizing to save time on repeat calls
		static $cache;
		if (!empty($cache[$file])) {
			return $cache[$file];
		}

		// Return object
		$ret     = [];
		// Section subheader
		$type  = '';

		while ($line = fgets($fp)) {
			$first_char = $line[0];
			$line       = trim($line);

			// If the line is a comment or blank skip it
			if ($first_char === "#" || !$line) {
				continue;
			}

			// Key/value: version=4.4.10
			if (!str_contains($line, '{') && preg_match("/(\w+)[=\s](.*)/", $line, $m)) {
				//kd($line, $file);
				$key = $m[1];
				$val = $m[2];

				$obj[$key] = $val;
			// Start of a section: hoststatus {
			} elseif (preg_match("/(\w+) \{/", $line, $m)) {
				//kd($line, $file);
				$type = $m[1];
			// End of a section
			} elseif (str_contains("}", $line)) {
				// Build a hash with appropriate sections
				if (in_array($type, ["hoststatus", "host"])) {
					$hn = $obj['host_name'];
					$ret[$type][$hn] = $obj;
				} elseif ($type === "hostcomment") {
					$hn = $obj['host_name'];
					$ret[$type][$hn][] = $obj;
				} elseif ($type === "servicecomment") {
					$hn = $obj['host_name'];
					$sn = $obj['service_description'];
					$ret[$type][$hn][$sn][] = $obj;
				} elseif (in_array($type, ["servicestatus", "service"])) {
					$hn = $obj['host_name'];
					$sn = $obj['service_description'];
					$ret[$type][$hn][$sn] = $obj;
					// These each have a single section only
				} elseif (in_array($type, ["info", "programstatus"])) {
					$ret[$type] = $obj;
				} else {
					// Store this object
					$ret[$type][] = $obj;
				}

				// Reset object for next loop
				$obj = [];
			} else {
				// Bees?
			}
		}

		$cache[$file] = $ret;

		if (!empty($_GET['debug'])) {
			printf("Total %d ms for $file<br />", (microtime(1) - $start) * 1000);
		}

		return $ret;
	}

}

// vim: tabstop=4 shiftwidth=4 noexpandtab autoindent softtabstop=4
