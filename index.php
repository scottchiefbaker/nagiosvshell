<?php   //main index page for nagios V-Shell, handles all page routing and viewing


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

//hide error output in browser
//ini_set('display_errors','Off');

//session_start(); //no need for sessions at this time
//ob_start();

/////Benchmarking//
/*
$time = microtime();
$time = explode(' ', $time);
$time = $time[1] + $time[0];
$start = $time;
*/

$username = false;
//////////////USE TO OVERRIDE APACHE AUTHENTICATION LOGIC: ///////////////////////////////
//////////UNCOMMENTING THIS WILL LEAVE YOUR MONITORING ENVIRONMENT WIDE OPEN!!! ///////////////////////////
//$username = 'nagiosadmin';


include (dirname(__FILE__) . '/include/inc.inc.php'); //master include file

//load language and other sitewide settings
init_vshell();

//needs a username to do anything
if($NagiosUser->get_username())  //if logged in, display the page
{
	//set_perms($username); //set global $authorization
	page_router();
}

//$hosts = $NagiosData->getProperty('hosts_objs');
//$hosts = $NagiosUser->get_authorized_hosts();
//echo "<pre>".print_r($hosts,true)."</pre>";

/*
$time = microtime();
$time = explode(' ', $time);
$time = $time[1] + $time[0];
$finish = $time;
$total_time = round(($finish - $start), 4);
echo 'Page generated in '.$total_time.' seconds.'."\n";
echo "TOTAL MEMORY PEAK: ".memory_get_peak_usage()."<br />";
*/
//ob_end_flush();
?>
