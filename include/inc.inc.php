<?php //inc.inc.php master include file


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

$base_dir = dirname(__FILE__) . "/../";
$base_dir = realpath($base_dir) . "/";

//include files for local directory
include("$base_dir/include/constants.inc.php"); //DO NOT ADD INCLUDES ABOVE THIS LINE
include("$base_dir/include/krumo/class.krumo.php"); //DO NOT ADD INCLUDES ABOVE THIS LINE
include("$base_dir/include/global-functions.php"); //DO NOT ADD INCLUDES ABOVE THIS LINE

//main include files for each directory
include("$base_dir/data/data.inc.php");
include("$base_dir/controllers/controllers.inc.php");
include("$base_dir/views/views.inc.php");

include("$base_dir/include/session.inc.php");
include("$base_dir/include/sluz/sluz.class.php");

function error_out($msg, $num) {
	global $base_dir;

	$tpl = "$base_dir/include/html5-template.html";
	$str = file_get_contents($tpl);

	$body  = "<h3 class=\"\">VShell Error #$num</h3>";
	$body .= "<div><b>Message:</b> $msg</div>";

	$str = preg_replace("/\{\\\$title\}/", "Error #$num", $str);
	$str = preg_replace("/\{\\\$body\}/", $body, $str);

	die($str);
}

class vshell {

	public $sluz       = null;
	public $tac_data   = [];
	public $start_time = 0;

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

		//kd([$skin, $tpl]);

		return $ret;
	}

	function __destruct() {

	}
}
