<?php

require_once("inc/read_conf.php");
require_once("inc/db.php");
require_once("inc/ssh.php");
require_once("inc/idna_convert.class.php");
require_once("inc/validate_param.php");
require_once("inc/slave_actions.php");
require_once("inc/utility.php");
require_once("inc/actions.php");
require_once("inc/forms.php");
require_once("inc/header.php");
require_once("inc/auth.php");

$conf = readmyconf();
$con = connectme($conf);

session_start();
$text_login = oci_auth_me_please($con, $conf);
if(isset($_SESSION['login']) && $_SESSION['login'] == '1'){
	// User is authenticated
	$out_message = perform_actions($con,$conf);
	$page_header = page_header($con,$conf);
	print($page_header . "<body onload='myLoadEvent()'>
<font color='white'>Logged in as " . $_SESSION['email'] . ".</font> <a href='?login_action=logout'>-> logout</a>
<center><h1>OpenStack cluster Installer Web GUI</h1></center>
<div class=\"tab\">
  <button id=\"MachinesBt\" class=\"tablinks\" onclick=\"openTab(event, 'Machines')\">Machines</button>
  <button id=\"ClustersBt\" class=\"tablinks\" onclick=\"openTab(event, 'Clusters')\">Clusters</button>
  <button id=\"RolesBt\" class=\"tablinks\" onclick=\"openTab(event, 'Roles')\">Roles</button>
  <button id=\"NetworksBt\" class=\"tablinks\" onclick=\"openTab(event, 'Networks')\">Networks</button>
  <button id=\"LocationsBt\" class=\"tablinks\" onclick=\"openTab(event, 'Locations')\">Locations</button>
  <button id=\"Swift-regionsBt\" class=\"tablinks\" onclick=\"openTab(event, 'Swift-regions')\">Swift-regions</button>
</div>

<div id=\"Machines\" class=\"tabcontent\">
    <center>");
    print(machine_list($con));
    print($out_message);
    print("
    </center>
</div>
<div id=\"Clusters\" class=\"tabcontent\">
    <div class=\"vtab\">
        <button class=\"vtablinks active\" onclick=\"openVTab(event, 'ClusterLifcycle')\" id=\"defaultOpenCluster\">Clusters Lifecycle</button>\n");
        print(clusterButtonList($con));
        print("
    </div>
    <div id=\"ClusterLifcycle\" class=\"vtabcontent\">");
        print(cluster_list($con,$conf));
        print("
    </div>");
    print(cluster_config_list($con,$conf));
    print("
</div>
<div id=\"Roles\" class=\"tabcontent\">
    <center>");
    print(role_list($con,$conf));
    print("
    </center>
</div>
<div id=\"Networks\" class=\"tabcontent\">");
print(network_list($con,$conf));
print("</div>
<div id=\"Locations\" class=\"tabcontent\">");
print(location_list($con,$conf));
print("</div>
<div id=\"Swift-regions\" class=\"tabcontent\">");
print(swift_regions_list($con,$conf));
print("</div>
");

print("</body></html>");
}else{
	// User is *not* authenticated. Show a login form.
	print("<html><head><link rel='stylesheet' href='oci.css'>
<style>
body {font-family: Arial;}
</style>
</head>
<body>
<center><h1>OpenStack cluster Installer Web GUI</h1>
  $text_login
  <table>
    <tr><form action='?' method='POST'><input type='hidden' name='login_action' value='login'><td style='text-align: right'>Email:</td><td><input type='text' name='email'></td></tr>
    <tr><td style='text-align: right'>Password:</td><td><input type='password' name='password'></td></tr>
    <tr><td style='text-align: right'></td><td><input type='submit' name='login' value='Login'></form></td></tr>
  </table>
</center></body>
");
}
?>
