<?php

$base_dir = dirname(__FILE__) . "/../";

require_once("$base_dir/include/krumo/class.krumo.php");
require_once("$base_dir/include/global-functions.php");
require_once("$base_dir/include/sluz/sluz.class.php");

class vshell {

	public $version        = "3.0.1";
	public $base_dir       = "";
	public $username       = "";
	public $sluz           = null;
	public $tac_data       = [];
	public $perms          = [];
	public $start_time     = 0;
	public $result_limit   = 0;
	public $host_state_map = [ -1 => 'Bees?', 0 => 'UP', 1 => 'DOWN', 2 => 'UNREACHABLE', 3 => 'UNKNOWN' ];
	public $svc_state_map  = [ -1 => 'Bees?', 0 => 'OK', 1 => 'WARNING', 2 => 'CRITICAL', 3 => 'UNKNOWN' ];

	function __construct($opts = []) {
		$this->start_time = microtime(1);
		$this->base_dir   = dirname(__FILE__) . "/../";

		$simple = $opts['simple'] ?? false;

		// Make sure we're logged in before showing any data
		$this->username = $_SERVER['REMOTE_USER'] ?? "";
		if (!$this->username) {
			$this->error_out("You must be logged in to view this page", 19313);
		}

		$this->sluz = new sluz;

		$icons = $this->get_icons();
		$this->sluz->assign('icons', $icons);
		$this->sluz->assign('username', $this->username);
		$this->sluz->assign('global', $this->get_global_vars($simple));
		$this->sluz->assign('VSHELL_VERSION', $this->version);

		$this->perms = $this->get_user_perms(CGICFG, $this->username);
		$this->sluz->assign('perms', $this->perms);
	}

	function get_tac_data() {
		$hosts = $this->get_all_hosts();
		$svcs  = $this->get_all_services();

		$ret = [];

		//////////////////////////////////////////////////////////////////////

		$ret['host']['state']['UP']          = 0;
		$ret['host']['state']['DOWN']        = 0;
		$ret['host']['state']['UNREACHABLE'] = 0;

		$count = 0;
		foreach ($hosts as $x) {
			// Process the state of all the hosts
			$state = $x['state_str'];
			increment($ret['host']['state'][$state], 1);

			// Look for specific modifiers for each host
			$types = ['is_flapping', 'active_checks_enabled', 'passive_checks_enabled', 'event_handler_enabled', 'flap_detection_enabled', 'notifications_enabled'];
			foreach ($types as $type) {
				$val = $x[$type];

				increment($ret['host'][$type], $val);
			}

			$count++;
		}

		$down_hosts        = $ret['host']['state']['DOWN']        ?? -1;
		$unreachable_hosts = $ret['host']['state']['UNREACHABLE'] ?? -1;
		$all_hosts_up      = (($down_hosts === 0) && ($unreachable_hosts === 0));

		$ret['host']['all_up']      = $all_hosts_up;
		$ret['host']['total_count'] = $count;

		//////////////////////////////////////////////////////////////////////

		$ret['service']['state']['OK']       = 0;
		$ret['service']['state']['WARNING']  = 0;
		$ret['service']['state']['CRITICAL'] = 0;
		$ret['service']['state']['UNKNOWN']  = 0;

		$count = 0;
		foreach ($svcs as $host_name) {
			foreach ($host_name as $svc_name => $x) {
				// Count the svc states
				$state = $x['state_str'];
				increment($ret['service']['state'][$state], 1);

				// Lookup individual metrics
				$types = ['active_checks_enabled', 'event_handler_enabled', 'flap_detection_enabled', 'notifications_enabled', 'passive_checks_enabled'];
				foreach ($types as $type) {
					$val = $x[$type] ?? 0;

					increment($ret['service'][$type], $val);
				}

				$count++;
			}
		}

		$warn_svcs = $ret['service']['state']['WARNING']  ?? -1;
		$crit_svcs = $ret['service']['state']['CRITICAL'] ?? -1;
		$unkn_svcs = $ret['service']['state']['UNKNOWN']  ?? -1;

		$all_svcs_up = (($warn_svcs === 0) && ($crit_svcs === 0) && ($unkn_svcs === 0));

		$ret['service']['all_up']      = $all_svcs_up;
		$ret['service']['total_count'] = $count;

		//////////////////////////////////////////////////////////////////////

		$htotal = $ret['host']['total_count'];
		$stotal = $ret['service']['total_count'];

		$ret["servicesFlappingDisabled"]      = $stotal - $ret['service']['flap_detection_enabled'];
		$ret["servicesNotificationsDisabled"] = $stotal - $ret['service']['notifications_enabled'];
		$ret["servicesEventHandlerDisabled"]  = $stotal - $ret['service']['event_handler_enabled'];
		$ret["servicesActiveChecksDisabled"]  = $stotal - $ret['service']['active_checks_enabled'];
		$ret["servicesPassiveChecksDisabled"] = $stotal - $ret['service']['passive_checks_enabled'];

		$ret["hostsFlappingDisabled"]         = $htotal - $ret['host']['flap_detection_enabled'];
		$ret["hostsNotificationsDisabled"]    = $htotal - $ret['host']['notifications_enabled'];
		$ret["hostsEventHandlerDisabled"]     = $htotal - $ret['host']['event_handler_enabled'];
		$ret["hostsActiveChecksDisabled"]     = $htotal - $ret['host']['active_checks_enabled'];
		$ret["hostsPassiveChecksDisabled"]    = $htotal - $ret['host']['passive_checks_enabled'];

		return $ret;
	}

	function get_global_vars($simple = false) {
		$base_dir = dirname(__FILE__) . "/../";
		$ini_path = "$base_dir/config/vshell.conf";

		if (is_readable($ini_path)) {
			$ini_array = parse_ini_file($ini_path);
		} elseif (is_readable("/etc/vshell.conf")) {
			$ini_array = parse_ini_file("/etc/vshell.conf");
		} else {
			$this->error_out("Missing configuration. Unable to load <code>config/vshell.conf</code>", 19023);
		}

		$nagios_cfg_file = $ini_array['NAGIOSCFG'] ?? "";

		if (!$nagios_cfg_file) {
			$this->error_out("<code>NAGIOSCFG</code> directive not found in <b>vshell.conf</b>",96309);
		}

		if (!is_readable($nagios_cfg_file)) {
			$this->error_out("Unable to read <code>$nagios_cfg_file</code>", 13931);
		}

		$nagios_cfg = $this->parse_nagios_config($nagios_cfg_file);

		if ($_SERVER['HTTPS']) {
			$proto = "https";
		} else {
			$proto = "http";
		}
		$server_base = $_SERVER['SERVER_NAME'] ?? $_SERVER['SERVER_ADDR'];
		$base        = "$proto://$server_base";

		// If it's a non-standard HTTP port we append that
		if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) {
			$base .= ':' . $_SERVER['SERVER_PORT'];
		}
		$core_url = $base . '/' . $ini_array["COREURL"] . '/';
		$core_cgi = $core_url . "cgi-bin/";
		$core_cmd = $core_cgi . "cmd.cgi";

		// Nagios core locations
		define('COREURL', $core_url); // Nagios core web URL
		define('CORECGI', $core_cgi); // Nagios core CGI root
		define('CORECMD', $core_cmd); // Nagios core system command cgi
		define("CGICFG" , $ini_array["CGICFG"] ?? "");

		// Data files for building main arrays
		define("STATUSFILE" , $nagios_cfg['status_file']       ?? "");
		define("OBJECTSFILE", $nagios_cfg['object_cache_file'] ?? "");
		define("CMDFILE"    , $nagios_cfg['command_file']      ?? "");

		//////////////////////////////////////////////////////////////

		if (!$simple) {
			$raw = $this->parse_nagios_status_file(STATUSFILE);
		}

		$program    = $raw['programstatus'] ?? [];
		$enable_not = $program['enable_notifications'] ?? 0;
		$ne         = $enable_not === "1";

		$ret['notifications_enabled'] = $ne;
		$ret['cmd_file']              = CMDFILE;
		$ret['cmd_file_writable']     = is_writable(CMDFILE);
		$ret['html_refresh_seconds']  = intval($ini_array['REFRESH_SECONDS'] ?? 60);
		$ret['core_url']              = $core_url;

		// Number of records to show on a page (for pagination)
		$this->result_limit = intval($ini_array['RESULT_LIMIT'] ?? 0);

		return $ret;
	}

	function get_icons() {
		$ret = [];

		$ret['list'] = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-card-list" viewBox="0 0 16 16"><path d="M14.5 3a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h13zm-13-1A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-13z"/><path d="M5 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 5 8zm0-2.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5zm0 5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5zm-1-5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0zM4 8a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0zm0 2.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0z"/></svg>';
		$ret['clock'] = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clock-fill" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/></svg>';
		$ret['trash'] = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash-fill" viewBox="0 0 16 16"><path d="M2.5 1a1 1 0 0 0-1 1v1a1 1 0 0 0 1 1H3v9a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V4h.5a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H10a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1H2.5zm3 4a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-1 0v-7a.5.5 0 0 1 .5-.5zM8 5a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-1 0v-7A.5.5 0 0 1 8 5zm3 .5v7a.5.5 0 0 1-1 0v-7a.5.5 0 0 1 1 0z"/></svg>';
		$ret['bell'] = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-bell-fill" viewBox="0 0 16 16"><path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2zm.995-14.901a1 1 0 1 0-1.99 0A5.002 5.002 0 0 0 3 6c0 1.098-.5 6-2 7h14c-1.5-1-2-5.902-2-7 0-2.42-1.72-4.44-4.005-4.901z"/></svg';
		$ret['no_bell'] = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-bell-slash-fill" viewBox="0 0 16 16"><path d="M5.164 14H15c-1.5-1-2-5.902-2-7 0-.264-.02-.523-.06-.776L5.164 14zm6.288-10.617A4.988 4.988 0 0 0 8.995 2.1a1 1 0 1 0-1.99 0A5.002 5.002 0 0 0 3 7c0 .898-.335 4.342-1.278 6.113l9.73-9.73zM10 15a2 2 0 1 1-4 0h4zm-9.375.625a.53.53 0 0 0 .75.75l14.75-14.75a.53.53 0 0 0-.75-.75L.625 15.625z"/></svg>';
		$ret['calendar_add'] = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-calendar-plus-fill" viewBox="0 0 16 16"><path d="M4 .5a.5.5 0 0 0-1 0V1H2a2 2 0 0 0-2 2v1h16V3a2 2 0 0 0-2-2h-1V.5a.5.5 0 0 0-1 0V1H4V.5zM16 14V5H0v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2zM8.5 8.5V10H10a.5.5 0 0 1 0 1H8.5v1.5a.5.5 0 0 1-1 0V11H6a.5.5 0 0 1 0-1h1.5V8.5a.5.5 0 0 1 1 0z"/></svg>';
		$ret['wrench'] = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-wrench" viewBox="0 0 16 16"><path d="M.102 2.223A3.004 3.004 0 0 0 3.78 5.897l6.341 6.252A3.003 3.003 0 0 0 13 16a3 3 0 1 0-.851-5.878L5.897 3.781A3.004 3.004 0 0 0 2.223.1l2.141 2.142L4 4l-1.757.364L.102 2.223zm13.37 9.019.528.026.287.445.445.287.026.529L15 13l-.242.471-.026.529-.445.287-.287.445-.529.026L13 15l-.471-.242-.529-.026-.287-.445-.445-.287-.026-.529L11 13l.242-.471.026-.529.445-.287.287-.445.529-.026L13 11l.471.242z"/></svg>';
		$ret['globe'] = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-globe" viewBox="0 0 16 16"><path d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm7.5-6.923c-.67.204-1.335.82-1.887 1.855A7.97 7.97 0 0 0 5.145 4H7.5V1.077zM4.09 4a9.267 9.267 0 0 1 .64-1.539 6.7 6.7 0 0 1 .597-.933A7.025 7.025 0 0 0 2.255 4H4.09zm-.582 3.5c.03-.877.138-1.718.312-2.5H1.674a6.958 6.958 0 0 0-.656 2.5h2.49zM4.847 5a12.5 12.5 0 0 0-.338 2.5H7.5V5H4.847zM8.5 5v2.5h2.99a12.495 12.495 0 0 0-.337-2.5H8.5zM4.51 8.5a12.5 12.5 0 0 0 .337 2.5H7.5V8.5H4.51zm3.99 0V11h2.653c.187-.765.306-1.608.338-2.5H8.5zM5.145 12c.138.386.295.744.468 1.068.552 1.035 1.218 1.65 1.887 1.855V12H5.145zm.182 2.472a6.696 6.696 0 0 1-.597-.933A9.268 9.268 0 0 1 4.09 12H2.255a7.024 7.024 0 0 0 3.072 2.472zM3.82 11a13.652 13.652 0 0 1-.312-2.5h-2.49c.062.89.291 1.733.656 2.5H3.82zm6.853 3.472A7.024 7.024 0 0 0 13.745 12H11.91a9.27 9.27 0 0 1-.64 1.539 6.688 6.688 0 0 1-.597.933zM8.5 12v2.923c.67-.204 1.335-.82 1.887-1.855.173-.324.33-.682.468-1.068H8.5zm3.68-1h2.146c.365-.767.594-1.61.656-2.5h-2.49a13.65 13.65 0 0 1-.312 2.5zm2.802-3.5a6.959 6.959 0 0 0-.656-2.5H12.18c.174.782.282 1.623.312 2.5h2.49zM11.27 2.461c.247.464.462.98.64 1.539h1.835a7.024 7.024 0 0 0-3.072-2.472c.218.284.418.598.597.933zM10.855 4a7.966 7.966 0 0 0-.468-1.068C9.835 1.897 9.17 1.282 8.5 1.077V4h2.355z"/></svg>';
		$ret['speech_bubble'] = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chat" viewBox="0 0 16 16"><path d="M2.678 11.894a1 1 0 0 1 .287.801 10.97 10.97 0 0 1-.398 2c1.395-.323 2.247-.697 2.634-.893a1 1 0 0 1 .71-.074A8.06 8.06 0 0 0 8 14c3.996 0 7-2.807 7-6 0-3.192-3.004-6-7-6S1 4.808 1 8c0 1.468.617 2.83 1.678 3.894zm-.493 3.905a21.682 21.682 0 0 1-.713.129c-.2.032-.352-.176-.273-.362a9.68 9.68 0 0 0 .244-.637l.003-.01c.248-.72.45-1.548.524-2.319C.743 11.37 0 9.76 0 8c0-3.866 3.582-7 8-7s8 3.134 8 7-3.582 7-8 7a9.06 9.06 0 0 1-2.347-.306c-.52.263-1.639.742-3.468 1.105z"/></svg>';
		$ret['circle_slash'] = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-slash-circle-fill" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-4.646-2.646a.5.5 0 0 0-.708-.708l-6 6a.5.5 0 0 0 .708.708l6-6z"/></svg>';
		$ret['arrow_up_down'] = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-down-up" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M11.5 15a.5.5 0 0 0 .5-.5V2.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L11 2.707V14.5a.5.5 0 0 0 .5.5zm-7-14a.5.5 0 0 1 .5.5v11.793l3.146-3.147a.5.5 0 0 1 .708.708l-4 4a.5.5 0 0 1-.708 0l-4-4a.5.5 0 0 1 .708-.708L4 13.293V1.5a.5.5 0 0 1 .5-.5z"/></svg>';
		$ret['alarm_clock'] = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-alarm-fill" viewBox="0 0 16 16"><path d="M6 .5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1H9v1.07a7.001 7.001 0 0 1 3.274 12.474l.601.602a.5.5 0 0 1-.707.708l-.746-.746A6.97 6.97 0 0 1 8 16a6.97 6.97 0 0 1-3.422-.892l-.746.746a.5.5 0 0 1-.707-.708l.602-.602A7.001 7.001 0 0 1 7 2.07V1h-.5A.5.5 0 0 1 6 .5zm2.5 5a.5.5 0 0 0-1 0v3.362l-1.429 2.38a.5.5 0 1 0 .858.515l1.5-2.5A.5.5 0 0 0 8.5 9V5.5zM.86 5.387A2.5 2.5 0 1 1 4.387 1.86 8.035 8.035 0 0 0 .86 5.387zM11.613 1.86a2.5 2.5 0 1 1 3.527 3.527 8.035 8.035 0 0 0-3.527-3.527z"/></svg>';
		$ret['github'] = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-github" viewBox="0 0 16 16"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.012 8.012 0 0 0 16 8c0-4.42-3.58-8-8-8z"/></svg>';
		$ret['tools'] = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-tools" viewBox="0 0 16 16"><path d="M1 0 0 1l2.2 3.081a1 1 0 0 0 .815.419h.07a1 1 0 0 1 .708.293l2.675 2.675-2.617 2.654A3.003 3.003 0 0 0 0 13a3 3 0 1 0 5.878-.851l2.654-2.617.968.968-.305.914a1 1 0 0 0 .242 1.023l3.27 3.27a.997.997 0 0 0 1.414 0l1.586-1.586a.997.997 0 0 0 0-1.414l-3.27-3.27a1 1 0 0 0-1.023-.242L10.5 9.5l-.96-.96 2.68-2.643A3.005 3.005 0 0 0 16 3c0-.269-.035-.53-.102-.777l-2.14 2.141L12 4l-.364-1.757L13.777.102a3 3 0 0 0-3.675 3.68L7.462 6.46 4.793 3.793a1 1 0 0 1-.293-.707v-.071a1 1 0 0 0-.419-.814L1 0Zm9.646 10.646a.5.5 0 0 1 .708 0l2.914 2.915a.5.5 0 0 1-.707.707l-2.915-2.914a.5.5 0 0 1 0-.708ZM3 11l.471.242.529.026.287.445.445.287.026.529L5 13l-.242.471-.026.529-.445.287-.287.445-.529.026L3 15l-.471-.242L2 14.732l-.287-.445L1.268 14l-.026-.529L1 13l.242-.471.026-.529.445-.287.287-.445.529-.026L3 11Z"/></svg>';

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
		$ret = $this->get_host_data_raw();

		foreach ($ret as $x) {
			$hn       = $x['host_name']     ?? "";
			$state_id = $x['current_state'] ?? -1;

			$ret[$hn]['state_str'] = $this->host_state_map[$state_id];

			// Work around for PENDING states
			if ($x['current_state'] == 0 && $x['last_check'] == 0) {
				$ret[$hn]['state_str'] = 'PENDING';
				$ret[$hn]['plugin_output'] = "No data received yet";
				$ret[$hn]['duration']      = "N/A";
				$ret[$hn]['attempt']       = "N/A";
				$ret[$hn]['last_check']    = "N/A";
			}

		}

		$has_filters = ($state_filter || $name_filter || $host_filter);
		if ($has_filters) {
			$ret = $this->filter_host_results($ret, $state_filter, $name_filter, $host_filter);
		}

		return $ret;
	}

	function filter_host_results($data, $state_filter, $name_filter, $host_filter) {
		$no_filters = (!$state_filter && !$name_filter && !$host_filter);

		if ($no_filters) {
			return $data;
		}

		// We're using Vim smartcasing here. If there is a capital we do a case SENSITIVE search
		$case_sensitive = "i"; // Default to case IN-sensitive
		if (preg_match("/[A-Z]/", $name_filter)) {
			$case_sensitive = "";
		}

		//k([$state_filter, $name_filter, $host_filter]);

		$ret = [];
		foreach ($data as $x) {
			$state_str = $x['state_str']           ?? "";
			$host_name = $x['host_name']           ?? "";
			$svc_name  = $x['service_description'] ?? "";

			// If we're looking for a specific state we catch it here
			// "PROBLEMS" is a specific case where anything that is not "UP" is flagged
			$state_filter_match = ($state_filter === $state_str) || (($state_filter === "PROBLEMS") && ($state_str !== "UP"));

			// Apply various filters
			if ($state_filter && !$state_filter_match) {
				continue;
			}

			if ($host_filter && ($host_filter !== $host_name)) {
				continue;
			}

			if ($name_filter && (!preg_match("/$name_filter/$case_sensitive", $host_name) && !preg_match("/$name_filter/$case_sensitive", $svc_name))) {
				continue;
			}

			$ret[$host_name] = $x;
		}

		return $ret;
	}

	function filter_service_results($data, $state_filter, $name_filter, $host_filter) {
		$no_filters = (!$state_filter && !$name_filter && !$host_filter);

		if ($no_filters) {
			return $data;
		}

		// We're using Vim smartcasing here. If there is a capital we do a case SENSITIVE search
		$case_sensitive = "i";
		if (preg_match("/[A-Z]/", $name_filter)) {
			$case_sensitive = "";
		}

		//k([$state_filter, $name_filter, $host_filter]);

		$ret = [];
		foreach ($data as $host_name => $svc) {
			foreach ($svc as $x) {
				$state_str = $x['state_str']           ?? "";
				$host_name = $x['host_name']           ?? "";
				$svc_name  = $x['service_description'] ?? "";

				// If we're looking for a specific state we catch it here
				// "PROBLEMS" is a specific case where anything that is not "OK" is flagged
				$state_filter_match = ($state_filter === $state_str) || (($state_filter === "PROBLEMS") && ($state_str !== "OK"));

				// Apply various filters
				if ($state_filter && !$state_filter_match) {
					continue;
				}

				if ($host_filter && ($host_filter !== $host_name)) {
					continue;
				}

				if ($name_filter && (!preg_match("/$name_filter/$case_sensitive", $host_name) && !preg_match("/$name_filter/$case_sensitive", $svc_name))) {
					continue;
				}

				$ret[$host_name][$svc_name] = $x;
			}
		}

		return $ret;
	}

	function get_all_services($state_filter = "", $name_filter = "", $host_filter = "") {
		$raw   = $this->parse_nagios_status_file(STATUSFILE);
		$svcs  = $raw['servicestatus'] ?? [];
		$hosts = $this->get_all_hosts();

		$comments = $raw['servicecomment'] ?? [];

		// We need to add a couple things from the host table so we loop through and pull em out
		foreach ($svcs as $host_name => $svc) {
			foreach ($svc as $x) {
				$hn = $x['host_name'] ?? "";
				$sn = $x['service_description'] ?? "";

				// Find the host associated with this service
				$y = $hosts[$hn] ?? [];

				$state_id      = $x['current_state'] ?? -1;
				$host_comments = $y['comments']      ?? [];
				$svc_comments  = $comments[$hn][$sn] ?? [];

				$svcs[$hn][$sn]['state_str']                   = $this->svc_state_map[$state_id];
				$svcs[$hn][$sn]['x_host_state']                = $state_id;
				$svcs[$hn][$sn]['x_host_state_str']            = $y['state_str']                     ?? '';
				$svcs[$hn][$sn]['x_host_address']              = $y['address']                       ?? '';
				$svcs[$hn][$sn]['x_host_problem_acknowledged'] = $y['problem_has_been_acknowledged'] ?? '';
				$svcs[$hn][$sn]['x_host_comments']             = $host_comments;
				$svcs[$hn][$sn]['comments']                    = $svc_comments;

				// Work around for PENDING states
				if ($x['current_state'] == 0 && $x['last_check'] == 0) {
					$svcs[$hn][$sn]['state_str']     = 'PENDING';
					$svcs[$hn][$sn]['plugin_output'] = "No data received yet";
					$svcs[$hn][$sn]['duration']      = "N/A";
					$svcs[$hn][$sn]['attempt']       = "N/A";
					$svcs[$hn][$sn]['last_check']    = "N/A";
				}

			}
		}

		$has_filters = ($state_filter || $name_filter || $host_filter);
		if ($has_filters) {
			$svcs = $this->filter_service_results($svcs, $state_filter, $name_filter, $host_filter);
		}

		ksort($svcs, SORT_FLAG_CASE | SORT_NATURAL);

		return $svcs;
	}

	function get_service_details_raw() {
		$x   = $this->parse_nagios_objects_file(OBJECTSFILE);
		$one = $x['service'] ?? [];
		$y   = $this->parse_nagios_status_file(STATUSFILE);
		$two = $y['servicestatus'] ?? [];

		$ret = [];
		foreach ($one as $host_name => $obj) {
			foreach ($obj as $svc) {
				$svc_name = $svc['service_description'];
				$obj      = $one[$host_name][$svc_name] ?? [];
				$status   = $two[$host_name][$svc_name] ?? [];

				$ret[$host_name][$svc_name] = array_merge($obj, $status);
			}
		}

		$all_comments = $y['servicecomment'] ?? [];

		// Loop through each service and put in the comments
		foreach ($ret as $host_name => $svc) {
			foreach ($svc as $x) {
				$svc_name     = $x['service_description'];
				$svc_comments = $all_comments[$host_name][$svc_name] ?? [];

				$ret[$host_name][$svc_name]['comments'] = $svc_comments;
			}

		}

		return $ret;
	}

	function get_service_details($host_name, $svc_name) {
		$raw = $this->get_service_details_raw();
		$ret = $raw[$host_name][$svc_name] ?? [];

		$state_id         = $ret['current_state'] ?? -1;
		$ret['state_str'] = $this->svc_state_map[$state_id];

		// Work around for PENDING states
		if ($ret['current_state'] == 0 && $ret['last_check'] == 0) {
			$ret['state_str']     = 'PENDING';
			$ret['plugin_output'] = "No data received yet";
			$ret['duration']      = "N/A";
			$ret['attempt']       = "N/A";
			$ret['last_check']    = "N/A";
		}

		return $ret;
	}

	function get_host_data_raw() {
		// Merge the current status and the config data
		$x   = $this->parse_nagios_objects_file(OBJECTSFILE);
		$one = $x['host'] ?? [];
		$y   = $this->parse_nagios_status_file(STATUSFILE);
		$two = $y['hoststatus'] ?? [];

		$ret = [];
		foreach ($one as $host_name => $obj) {
			$obj    = $one[$host_name] ?? [];
			$status = $two[$host_name] ?? [];

			$ret[$host_name] = array_merge($obj, $status);
		}

		// Get the comments for this host
		$all_comments = $y['hostcomment'] ?? [];

		// Loop through each host comments and put them in the return array
		foreach ($ret as $host_name => $x) {
			$host_comments = $all_comments[$host_name] ?? [];
			$ret[$host_name]['comments'] = $host_comments;
		}

		return $ret;
	}

	function get_host_data($name) {
		$raw = $this->get_host_data_raw();
		$ret = $raw[$name] ?? [];

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
		$x  = $this->parse_nagios_objects_file(OBJECTSFILE);
		$hg = $x['hostgroup'] ?? [];

		// Loop through the list
		foreach ($hg as $x) {
			$name  = $x['hostgroup_name'] ?? "";
			$ret[] = $name;
		}

		// Loop through the above and find all the groups that contain that host
		if ($host_filter) {
			$data = $ret;
			$ret  = [];

			foreach ($hg as $x) {
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
		$ret = [];

		// Get the raw data
		$x      = $this->parse_nagios_objects_file(OBJECTSFILE);
		$hg     = $x['hostgroup'] ?? [];
		$groups = [];

		// Pull out each group and get the members
		foreach ($hg as $y) {
			$name    = $y['hostgroup_name'] ?? "";
			$raw     = $y['members']        ?? "";
			$members = preg_split("/,/", $raw);

			$groups[$name] = $members;
		}

		// Get the details for each individual host
		foreach ($groups as $group_name => $x) {
			// Sort the hosts inside the group
			ksort($x, SORT_FLAG_CASE | SORT_NATURAL);
			foreach ($x as $host_name) {
				$details                      = $this->get_host_data($host_name);
				$ret[$group_name][$host_name] = $details;
			}
		}

		ksort($ret, SORT_FLAG_CASE | SORT_NATURAL);

		return $ret;
	}

	function get_hostgroup_members($name) {
		$ret = [];

		// Get the raw data
		$x      = $this->parse_nagios_objects_file(OBJECTSFILE);
		$hg     = $x['hostgroup'] ?? [];
		$groups = [];

		// Pull out each group and get the members
		foreach ($hg as $y) {
			$host_name = $y['hostgroup_name'] ?? "";
			$raw       = $y['members']        ?? "";
			$members   = preg_split("/,/", $raw);

			$groups[$host_name] = $members;
		}

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

	function parse_nagios_status_file($file = STATUSFILE, $use_cache = true) {
		$start = microtime(1);
		$fp    = fopen($file, 'r');

		if (!$fp) {
			$this->error_out("Unable to open '$file' for reading");
		}

		// Basic memoizing to save time on repeat calls
		static $cache;
		if ($use_cache && !empty($cache[$file])) {
			return $cache[$file];
		}

		// Return object
		$ret  = [];
		// Section subheader
		$type = '';
		$obj  = [];

		while ($line = fgets($fp)) {
			$first_char = $line[0];

			// If the line is a comment or blank skip it
			if ($first_char === "#") {
				continue;
			}

			// Key/value: entry_time=1689262170
			if (str_contains($line, "=")) {
				$line  = trim($line);
				$parts = explode("=", $line, 2);
				//kd($line, $file);

				$key = $parts[0] ?? "";
				$val = $parts[1] ?? "";

				$obj[$key] = $val;
			// Start of a section: hoststatus {
			} elseif (str_contains($line, "{")) {
				$parts = explode(" {", $line, 2);

				$str = $parts[0] ?? "";
				$str = str_replace("define ", "", $str);

				$type = $str;
			// End of a section
			} elseif (str_contains($line, "}")) {
				// Build a hash with appropriate sections
				if ($type === "servicestatus") {
					$hn = $obj['host_name'] ?? "";
					$sn = $obj['service_description'] ?? "";
					$ret[$type][$hn][$sn] = $obj;
				} elseif ($type === "hoststatus" ) {
					$hn = $obj['host_name'] ?? "";
					$ret[$type][$hn] = $obj;
				} elseif ($type === "hostcomment") {
					$hn = $obj['host_name'];
					$ret[$type][$hn][] = $obj;
				} elseif ($type === "servicecomment") {
					$hn = $obj['host_name'];
					$sn = $obj['service_description'];
					$ret[$type][$hn][$sn][] = $obj;
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

		//if (!empty($_GET['debug'])) {
		//    printf("StatusFile: Parsed $file in %d ms<br />", (microtime(1) - $start) * 1000);
		//}

		return $ret;
	}

	function parse_nagios_objects_file($file = OBJECTSFILE, $use_cache = true) {
		$start = microtime(1);
		$fp    = fopen($file, 'r');

		if (!$fp) {
			$this->error_out("Unable to open '$file' for reading");
		}

		// Basic memoizing to save time on repeat calls
		static $cache;
		if ($use_cache && !empty($cache[$file])) {
			return $cache[$file];
		}

		// Return object
		$ret  = [];
		// Section subheader
		$type = '';

		while ($line = fgets($fp)) {
			$first_char = $line[0];
			$line       = trim($line);

			// If the line is a comment or blank skip it
			if ($first_char === "#") {
				continue;
			}

			// Key/value: low_flap_threshold  0.000000
			if (str_contains($line, "\t")) {
				$line  = trim($line);
				$parts = explode("\t", $line, 2);

				$key = $parts[0] ?? "";
				$val = $parts[1] ?? "";

				//kd([$line, $key, $val]);

				$obj[$key] = $val;
			// Start of a section: define hoststatus {
			} elseif (str_contains($line, "{")) {
				// We want the middle word of this line
				$parts = explode(" ", $line);
				$str   = $parts[1] ?? "";

				$type = $str;
			// End of a section
			} elseif ($line === "}") {
				// Build a hash with appropriate sections
				if ($type === "host") {
					$hn = $obj['host_name'];
					$ret[$type][$hn] = $obj;
				} elseif ($type === 'service') {
					$hn = $obj['host_name'];
					$sn = $obj['service_description'];
					$ret[$type][$hn][$sn] = $obj;
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

		//if (!empty($_GET['debug'])) {
		//    printf("ObjectFile: Parsed $file in %d ms<br />", (microtime(1) - $start) * 1000);
		//}

		return $ret;
	}

	function parse_nagios_status_file_old($file) {
		$start = microtime(1);
		$fp    = fopen($file, 'r');

		if (!$fp) {
			$this->error_out("Unable to open '$file' for reading");
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

			// Key/value: entry_time=1689262170
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

		//if (!empty($_GET['debug'])) {
		//    printf("StatusOld: Parsed $file in %d ms<br />", (microtime(1) - $start) * 1000);
		//}

		return $ret;
	}

	function error_out($err_msg, $err_num) {
		$tpl = $this->base_dir . "/tpls/error_out.stpl";

		$this->sluz->assign("err_msg", $err_msg);
		$this->sluz->assign("err_num", $err_num);

		print $this->sluz->fetch($tpl);
		exit(7);
	}

	function get_user_perms($file, $username) {
		if (!is_readable($file)) {
			$this->error_out("Unable to read permission file '$file'", 48242);
		}

		// Loop through each line of the file
		$ret   = [];
		$lines = file($file);
		foreach ($lines as $line) {
			// Skip any comments
			if (str_starts_with($line, '#')) {
				continue;
			}

			// If the line is a key = val we parse it
			if (preg_match("/(.+?)\s*=\s*(.+)/", $line, $m)) {
				$key = $m[1] ?? "";
				$val = $m[2] ?? "";

				// If it's one of the authorized lines we pull it out
				if (str_starts_with($key, "authorized")) {
					$val = preg_split("/\s*,\s*/", $val);
					sort($val);

					$key = str_replace("authorized_for_", '', $key);

					if (in_array($username, $val)) {
						$ret[$key] = true;
					} else {
						$ret[$key] = false;
					}
				}
			}
		}

		return $ret;
	}

	public function record_limit($data, $limit, $offset) {
		$ret = [];

		$count = 0; // Total number of elements
		$shown = 0; // Number of elements that have been shown

		// Here we whittle down the service array (two levels deep)
		// based on the offset/limit
		foreach ($data as $host => $x) {
			foreach ($x as $y) {
				$name = $y['service_description'];

				// If we fall in the pagination range we add it to the hash
				if (($shown < $limit) && ($count >= $offset)) {
					$ret[$host][$name] = $y;
					$shown++;
				}

				$count++;
			}
		}

		// Figure out what page we're currently showing (based on offset)
		// and how many total pages we will need
		if ($limit) {
			$current_page = intval(ceil($offset / $limit));
			$num_pages    = intval(ceil($count / $limit));
		} else {
			$current_page = 1;
			$num_pages    = 1;
		}

		// We build the array of page offsets based on the total number
		// of elements and page limit. This is for: Page 1, Page 2, Page 3, etc
		$offsets = [];
		for ($i = 0; $i < $num_pages; $i++) {
			$diff = abs($current_page - $i);
			// Only show X pages on either side of current page
			if ($diff <= 4) {
				$offsets[$i] = $i * $limit;
			}
		}

		// Calculate the previous and next offsets
		$prev_offset = $offset - $limit;
		$next_offset = $offset + $limit;

		$obj = [
			'total'        => $count,
			'offset'       => $offset,
			'prev_offset'  => $prev_offset,
			'next_offset'  => $next_offset,
			'page_limit'   => $limit,
			'current_page' => $current_page,
			'page_list'    => $offsets,
			'num_pages'    => $num_pages,
			'shown'        => $shown,
		];

		// Return value is an array of the whittled down data, and a hash of
		// pagination information
		$ret = [$ret, $obj];

		return $ret;
	}

	public function get_service_config_info($host_name, $svc_name) {
		$x   = $this->parse_nagios_objects_file(OBJECTSFILE);
		$one = $x['service'] ?? [];

		$ret = $one[$host_name][$svc_name] ?? [];

		return $ret;
	}

	public function get_host_config_info($host_name) {
		$x   = $this->parse_nagios_objects_file(OBJECTSFILE);
		$one = $x['host'] ?? [];

		$ret = $one[$host_name];

		return $ret;
	}

	private function parse_nagios_config($file) {
		$lines = file($file);
		$ret   = [];

		foreach ($lines as $line) {
			$line  = trim($line);
			$first = $line[0] ?? "";

			if ($first === "#" || !$line) {
				continue;
			}

			if (str_contains($line, '=')) {
				$parts = explode("=", $line, 2);

				$key = $parts[0] ?? "";
				$val = $parts[1] ?? "";

				$ret[$key] = $val;
			}
		}

		return $ret;
	}
}

// vim: tabstop=4 shiftwidth=4 noexpandtab autoindent softtabstop=4
