<?php //header.php  this page contains all of the html head information, used as an include file



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


function display_header($page_title='Nagios Visual Shell', $notification_str = "")
{
	$jquery_path    = 'js/jquery-3.5.1.min.js';
	$header_js_path = 'js/global.js';
	$navlinks       = build_nav_links();
	$coreurl        = COREURL;

	$type = get_in($_GET,['type']);

	$extra_js = "";
	if ($type === "servicedetail") {
		$extra_js .= "<script src=\"js/service_details.js\"></script>\n";
	} elseif ($type === "hostdetail") {
		$extra_js .= "<script src=\"js/host_details.js\"></script>\n";
	}

	$header = '
<!DOCTYPE html>
<html>
<head profile="http://dublincore.org">
<title>'.$page_title.'</title>

<meta name="description" content="Nagios VShell" />
<meta name="keywords" content="Nagios VShell" />
<meta http-equiv="refresh" content="120">

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

<link rel="stylesheet" href="css/bootstrap.min.css" type="text/css" media="screen" />
<link rel="stylesheet" href="css/style.css" type="text/css" media="screen" />
<link rel="stylesheet" href="css/mobile.css" type="text/css" media="screen" />
<link rel="stylesheet" href="css/slider.css" type="text/css" media="screen" />

<script type="text/javascript" src="' . $jquery_path    . '"></script>
<script type="text/javascript" src="' . $header_js_path . '"></script>
<script src="js/popper.min.js"></script>
<script src="js/bootstrap.min.js"></script>
' . $extra_js . '

</head>
<body class="container-fluid">
	<div class="row">
		<div id="logoDiv" class="col pl-0">
				<a href="index.php"><img src="views/images/vshell.png" /></a>
			</div>

			<div class="corelink text-right col pr-0">
				<a class="label" href="' . $coreurl . '" target="_blank" title="' . gettext('Access Nagios Core') . '"><img class="nagios_logo" title="Access Nagios Core" src="views/images/nagios_logo.png" /></a>
			</div>
		</div>

	' . clear_cache_link() . '

	<div class="topnav row">
		' . $navlinks . '
	</div>

	' . $notification_str . '

<div class="main">

';
	return $header;
}

?>
