<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Nagios Config and directory locations
|--------------------------------------------------------------------------
|
| These prefs are used when checking and setting modes when working
| with the file system.  The defaults are fine on servers with proper
| security, but you may wish (or even need) to change the values in
| certain environments (Apache running a separate process for each
| user, PHP under CGI with Apache suEXEC, etc.).  Octal values should
| always be used to set the mode correctly.
|
*/

# TODO: Update location of vshell.conf to be dynamic
$conf_file = parse_ini_file('/etc/vshell2.conf');

// application information
define('VERSION', '2.0.0'); 
define('LANG', $conf_file['LANG']); 

// server root information 
define('BASEURL', $conf_file['BASEURL']);
define('SERVERBASE', 'http://localhost'); //http://<address> 
define('DIRBASE', dirname(__FILE__)); //assigns current directory as root 
define('STATICURL', '/'.BASEURL.'/static');
define('IMAGESURL', STATICURL.'/images');

// nagios core locations 
define('COREURL', '/'.$conf_file['COREURL']); //Nagios core web URL 
define('CORECGI', COREURL.'/cgi-bin/'); //Nagios core CGI root 
define('CORECMD', CORECGI.'cmd.cgi?'); //nagios core system command cgi 

// data intervals
define('TTL', $conf_file['TTL']); 
define('UPDATEINTERVAL', $conf_file['UPDATEINTERVAL']); 

// data files for building main arrays 
define('STATUSFILE', $conf_file['STATUSFILE']); //status.dat file generated by nagios 
define('OBJECTSFILE', $conf_file['OBJECTSFILE']); //main object configuration file generated by nagios
define('CGICFG', $conf_file['CGICFG']); //cfg file with permissions cfg 


/* Custom Library Directories */

define('FACTORY','libraries/factory/');

/*
|--------------------------------------------------------------------------
| File and Directory Modes
|--------------------------------------------------------------------------
|
| These prefs are used when checking and setting modes when working
| with the file system.  The defaults are fine on servers with proper
| security, but you may wish (or even need) to change the values in
| certain environments (Apache running a separate process for each
| user, PHP under CGI with Apache suEXEC, etc.).  Octal values should
| always be used to set the mode correctly.
|
*/
define('FILE_READ_MODE', 0644);
define('FILE_WRITE_MODE', 0666);
define('DIR_READ_MODE', 0755);
define('DIR_WRITE_MODE', 0777);

/*
|--------------------------------------------------------------------------
| File Stream Modes
|--------------------------------------------------------------------------
|
| These modes are used when working with fopen()/popen()
|
*/

define('FOPEN_READ',							'rb');
define('FOPEN_READ_WRITE',						'r+b');
define('FOPEN_WRITE_CREATE_DESTRUCTIVE',		'wb'); // truncates existing file data, use with care
define('FOPEN_READ_WRITE_CREATE_DESTRUCTIVE',	'w+b'); // truncates existing file data, use with care
define('FOPEN_WRITE_CREATE',					'ab');
define('FOPEN_READ_WRITE_CREATE',				'a+b');
define('FOPEN_WRITE_CREATE_STRICT',				'xb');
define('FOPEN_READ_WRITE_CREATE_STRICT',		'x+b');






/* End of file constants.php */
/* Location: ./application/config/constants.php */
