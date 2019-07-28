<?php

function machine_list($con){
    $q = "SELECT * FROM clusters ORDER BY id";
    $r = mysqli_query($con, $q);
    $n = mysqli_num_rows($r);
    $clusters = [];
    for($i=0; $i<$n; $i++){
        $a = mysqli_fetch_array($r);
        $clusters[ $a["id"] ] = $a;
    }

    if(isset($_REQUEST["subaction"]) && $_REQUEST["subaction"] == "edit_ipmi"){
        $mlist = "<h2>Edit IPMI settings for this machine</h2>";
        $safe_machine_id = safe_int("machine_id");
        if($safe_machine_id === FALSE){
            $mlist .= "Machine ID in wrong format.";
            return $mlist;
        }
        $q = "SELECT * FROM machines WHERE id='$safe_machine_id'";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n != 1){
            $mlist .= "Cannot find machine by this ID.";
            return $mlist;
        }
        $a = mysqli_fetch_array($r);

        if($a["cluster"] != NULL){
            $clid    = $a["cluster"];
            $cluster = $clusters[$clid]["name"];
        }else{
            $cluster = "Not in a cluster";
        }

        $ipaddr   = $a["ipaddr"];
        $serial   = $a["serial"];
        $prodname = $a["product_name"];
        $role     = $a["role"];
        $hostname = $a["hostname"];
        $ipmi_use      = $a["ipmi_use"];
        $ipmi_call_chassis_bootdev = $a["ipmi_call_chassis_bootdev"];
        $ipmi_addr     = $a["ipmi_addr"];
        $ipmi_port     = $a["ipmi_port"];
        $ipmi_username = $a["ipmi_username"];
        $ipmi_password = $a["ipmi_password"];

        if($ipmi_use == "yes"){
            $selector_ipmi_use = " checked";
        }else{
            $selector_ipmi_use = "";
        }

        if($ipmi_call_chassis_bootdev == "yes"){
            $selector_ipmi_call_chassis_bootdev = " checked";
        }else{
            $selector_ipmi_call_chassis_bootdev = "";
        }

        $mlist .= "<table><tr><th>IP address:</th><td>$ipaddr</td></tr>";
        $mlist .= "<th>Serial:</th><td>$serial</td></tr>";
        $mlist .= "<th>Product name:</th><td>$prodname</td></tr>";
        $mlist .= "<th>Hostname:</th><td>$hostname</td></tr>";
        $mlist .= "<th>Cluster:</th><td>$cluster</td></tr>";
        $mlist .= "<form action='?'><input type='hidden' name='action' value='set_ipmi'><input type='hidden' name='machine_id' value='$safe_machine_id'>";
        $mlist .= "<th>Use IPMI:</th><td><input type='checkbox' name='ipmi_use' value='yes'$selector_ipmi_use></td></tr>";
        $mlist .= "<th>Call IPMI chassis bootdev:</th><td><input type='checkbox' name='ipmi_call_chassis_bootdev' value='yes'$selector_ipmi_call_chassis_bootdev></td></tr>";
        $mlist .= "<th>IPMI address:</th><td><input type='text' name='ipmi_addr' value='$ipmi_addr'></td></tr>";
        $mlist .= "<th>IPMI port:</th><td><input type='text' name='ipmi_port' value='$ipmi_port'></td></tr>";
        $mlist .= "<th>IPMI username:</th><td><input type='text' name='ipmi_username' value='$ipmi_username'></td></tr>";
        $mlist .= "<th>IPMI password:</th><td><input type='text' name='ipmi_password' value='$ipmi_password'></td></tr>";
        $mlist .= "<th></th><td><input type='submit' name='save' value='Save'></td></tr>";
        $mlist .= "</form></table>";
        return $mlist;
    }

    $r = mysqli_query($con, "SELECT id,memory,ipaddr,serial,product_name,hostname,installed,lastseen,status,role,cluster, TIME_TO_SEC(TIMEDIFF(NOW(),lastseen)) AS timediff,ipmi_use,notes,ladvd_report,bios_version,ipmi_firmware_version,ipmi_detected_ip FROM machines ORDER BY product_name, serial");
    $n = mysqli_num_rows($r);
    $mlist = "Total number of machines: $n<br><table border='1'>
<tr><th>Current IP address<br>Serial, Product name</th><th>IPMI</th><th>Memory</th><th>HDD</th><th>iface</th><th>ladvd</th><th>status</th><th>Last seen</th><th>Role</th><th>Cluster</th><th>Hostname</th><th>Action</th><th>Reboots</th><th>Notes</th></tr>\n";
    for($i=0; $i<$n; $i++){
        $a = mysqli_fetch_array($r);
        $machine_id = $a["id"];
        $ipaddr     = $a["ipaddr"];
        $serial     = $a["serial"];
        $prodname   = $a["product_name"];
        $bios_vers  = $a["bios_version"];
        $ipmi_vers  = $a["ipmi_firmware_version"];
        $ipmi_de_ip = $a["ipmi_detected_ip"];
        $role       = $a["role"];
        $ipmi_use   = $a["ipmi_use"];
        $notes      = $a["notes"];
        $ladvd_report = $a["ladvd_report"];
        if($a["cluster"] != NULL){
            $clid    = $a["cluster"];
            $cluster = $clusters[$clid]["name"];
        }else{
            $cluster = "Not in a cluster";
        }

        $mlist .= "<tr>";

        $mlist .= "<td><div class='tooltip'>$ipaddr<br><b>$serial</b>, $prodname<span class='tooltiptext'>BIOS vers: $bios_vers<br>IPMI vers: $ipmi_vers<br>IPMI IP: $ipmi_de_ip</span></div></td>";
        $mlist .= "<td><a href='?subaction=edit_ipmi&machine_id=$machine_id'>". $a["ipmi_use"] ."</a></td>";
        $mlist .= "<td>". mb_to_smart_size($a["memory"]) ."</td>";

        $blockdevs = "";
        $q = "SELECT MIN(name) AS first_dev, MAX(name) AS last_dev,size_mb, COUNT(id) AS numdev FROM blockdevices WHERE machine_id='".$a["id"]."' GROUP BY size_mb ORDER BY name";
        $r2 = mysqli_query($con, $q);
        $n2 = mysqli_num_rows($r2);
        for($i2=0; $i2<$n2; $i2++){
            $a2 = mysqli_fetch_array($r2);
            if($a2["numdev"] == 1){
                $blockdevs .= $a2["first_dev"] . ": " . mb_to_smart_size($a2["size_mb"]) . "<br>";
            }else{
                $blockdevs .= $a2["first_dev"] . '-' . $a2["last_dev"] . ": " . $a2["numdev"] . "x" . mb_to_smart_size($a2["size_mb"]) . "<br>";
            }
        }

        $mlist .= "<td>".$blockdevs."</td>";

        $ifaces = "";
        $r2 = mysqli_query($con, "SELECT MIN(name) AS first_dev, MAX(name) AS last_dev, COUNT(id) AS numdev, name, macaddr, max_speed FROM ifnames WHERE machine_id='".$a["id"]."' GROUP BY max_speed ORDER BY name");
        $n2 = mysqli_num_rows($r2);
        for($i2=0; $i2<$n2; $i2++){
            $a2 = mysqli_fetch_array($r2);

            if($a2["max_speed"] >= 1000){
              $speed = $a2["max_speed"] / 1000 . "Gb/s";
            }else{
              $speed = $a2["max_speed"] . "Mb/s";
            }
            if($a2["numdev"] == 1){
                $ifaces .= $a2["name"] . ": " . ", " . $speed . "<br>";
            }else{
                $ifaces .= $a2["first_dev"] . "-" . $a2["last_dev"] . ": " . $a2["numdev"] . "x" . $speed . "<br>";
            }
        }

        $ifaces_tooltip = "";
        $r2 = mysqli_query($con, "SELECT * FROM ifnames WHERE machine_id='".$a["id"]."'");
        $n2 = mysqli_num_rows($r2);
        for($i2=0; $i2<$n2; $i2++){
            $a2 = mysqli_fetch_array($r2);
            if($a2["max_speed"] >= 1000){
              $speed = $a2["max_speed"] / 1000 . "Gb/s";
            }else{
              $speed = $a2["max_speed"] . "Mb/s";
            }
            $ifaces_tooltip .= $a2["name"] . ":&nbsp;" . $a2["macaddr"] . ",&nbsp;" . $speed . "<br>";
        }

        $mlist .= "<td><div class='tooltip'>$ifaces<span class='tooltiptext'>$ifaces_tooltip</span></div></td>";
        $mlist .= "<td>$ladvd_report</td>";
        $mlist .= "<td>".$a["status"]."</td>";
        $mlist .= "<td>".seconds_to_duration($a["timediff"])."</td>";
        $mlist .= "<td>$role</td>";
        $mlist .= "<td>$cluster</td>";
        # HOSTNAME field or value
        if($a["status"] == "live"){
            $mlist .= "<td><form action='?'><input type='text' size='30' name='hostname' value='".$a["hostname"]."'></td>";
        }else{
            $mlist .= "<td>".$a["hostname"]."</td>";
        }
        if($ipmi_use == "yes"){
            $ipmi_powercycle_button = "<form action='?'><td><input type='hidden' name='action' value='ipmi_reboot_on_hdd'><input type='hidden' name='ipaddr' value='$ipaddr'><input type='submit' value='IPMI reboot on HDD'></td></form>";
            $ipmi_powercycle_button .= "<form action='?'><td><input type='hidden' name='action' value='ipmi_reboot_on_live'><input type='hidden' name='ipaddr' value='$ipaddr'><input type='submit' value='IPMI reboot on live'></td></form>";
        }else{
            $ipmi_powercycle_button = "";
        }
        # INSTALL button
        switch($a["status"]){
        case "live":
            $mlist .= "<td><input type='hidden' name='action' value='install_os'><input type='hidden' name='ipaddr' value='$ipaddr'><input type='submit' text='Install' value='Install'></form></td>";
            break;
        case "installing":
            $mlist .= "<td><a target='_blank' href='view_log.php?ipaddr=$ipaddr'>view log</a></td>";
            break;
        default:
            $mlist .= "<td></td>";
            break;
        }
        # REBOOT button
        switch($a["status"]){
        case "live":
            $mlist .= "<td><center><table><tr><form action='?'><td><input type='hidden' name='action' value='reboot_on_hdd'><input type='hidden' name='ipaddr' value='$ipaddr'><input type='submit' text='Reboot on HDD' value='Reboot on HDD'></td></form>$ipmi_powercycle_button</tr></table></center></td>";
            break;
        case "installed":
            $mlist .= "<td><center><table><tr><form action='?'><td><input type='hidden' name='action' value='reboot_on_live'><input type='hidden' name='ipaddr' value='$ipaddr'><input type='submit' text='Reboot on live' value='Reboot on Live'></td></form>$ipmi_powercycle_button</tr></table></center></td>";
            break;
        case "installing":
        case "firstboot":
        default:
            $mlist .= "<td><table>$ipmi_powercycle_button</table></td>";
            break;
        }
        $mlist .= "<form action='?'><input type='hidden' name='action' value='edit_machine_notes'><input type='hidden' name='ipaddr' value='$ipaddr'><td><input type='text' size='10' name='notes' value='$notes'><input type='submit' name='submit' value='Save'></td></form>";
        $mlist .= "</tr>\n";
    }
    $mlist .= "</table>\n";
    return $mlist;
}

function cluster_list($con,$conf){
    $clist = "<table border='1'>
<tr><th>Name</th><th>Domain</th><th>Action</th></tr>\n";
    $r = mysqli_query($con, "SELECT * FROM clusters");
    $n = mysqli_num_rows($r);
    for($i=0; $i<$n; $i++){
        $a = mysqli_fetch_array($r);
        $clist .= "<tr><td>".$a["name"]."</td><td>".$a["domain"]."</td><td><form action='?'><input type='hidden' name='h-open-tab' value='Clusters'><input type='hidden' name='action' value='delete_cluster'><input type='hidden' name='id' value='".$a["id"]."'><input type='submit' text='Delete cluster' value='Delete cluster'></form></td></tr>\n";
    }
    $clist .= "<tr><form action='?'><td><input type='hidden' name='h-open-tab' value='Clusters'><input type='text' size='40' name='name' value=''></td><td><input type='text' size='40' name='domain' value=''></td>";
    $clist .= "<td><center><input type='hidden' name='action' value='new_cluster'><input type='submit' text='New cluster' value='New cluster'></form></center></td></tr>\n";
    $clist .= "</table>\n";
    return $clist;
}

function get_machine_id_from_ip($con, $safe_ip){
    $r = mysqli_query($con, "SELECT * FROM machines WHERE ipaddr='$safe_ip'");
    $n = mysqli_num_rows($r);
    $machine = mysqli_fetch_array($r);
    return $machine["id"];
}

function clusterButtonList($con){
    $out = "";
    $r = mysqli_query($con, "SELECT * FROM clusters");
    $n = mysqli_num_rows($r);
    for($i=0;$i<$n;$i++){
        $a = mysqli_fetch_array($r);
        $name = $a["name"];
        $domain = $a["domain"];
        $out .= "<button id=\"".$name."Bt\" class=\"vtablinks\" onclick=\"openVTab(event, '".$name."')\" >$name&nbsp;$domain</button>\n";
    }
    return $out;
}

function role_selector($con, $conf, $role, $has_all){
    $out = "";
    $r = mysqli_query($con, "SELECT * FROM roles");
    $n = mysqli_num_rows($r);
    $out .= "<select name='role'>";
    if($has_all == "yes"){
        $out .= "<option value='all'>All</option>\n";
        $out .= "<option value='norole'>No role</option>\n";
        $out .= "<option value='vm-net'>VM Trafic</option>\n";
        $out .= "<option value='ovs-bridge'>OVS Bridge</option>\n";
        $out .= "<option value='ceph-cluster'>Ceph cluster network</option>\n";
    }
    for($i=0;$i<$n;$i++){
        $a = mysqli_fetch_array($r);
        $name = $a["name"];
        if($role == $name){
            $selected = " selected";
        }else{
            $selected = "";
        }
        $out .= "<option value='$name'$selected>$name</option>\n";
    }
    $out .= "</select>";
    return $out;
}

function ethtype_to_nice_display($con, $conf, $ethtype){
    switch($ethtype){
    case "eth0":
    case "eth1":
    case "eth2":
    case "eth3":
        return $ethtype;
        break;
    case "10m1":
        return "1st 10MB/s eth";
        break;
    case "10m2":
        return "2nd 10MB/s eth";
        break;
    case "10m3":
        return "3rd 10MB/s eth";
        break;
    case "10m4":
        return "4th 10MB/s eth";
        break;
    case "100m1":
        return "1st 100MB/s eth";
        break;
    case "100m2":
        return "2nd 100MB/s eth";
        break;
    case "100m3":
        return "3rd 100MB/s eth";
        break;
    case "100m4":
        return "4th 100MB/s eth";
        break;
    case "1g1":
        return "1st 1GB/s eth";
        break;
    case "1g2":
        return "2nd 1GB/s eth";
        break;
    case "1g3":
        return "3rd 1GB/s eth";
        break;
    case "1g4":
        return "4th 1GB/s eth";
        break;
    case "10g1":
        return "1st 10GB/s eth";
        break;
    case "10g2":
        return "2nd 10GB/s eth";
        break;
    case "10g3":
        return "3rd 10GB/s eth";
        break;
    case "10g4":
        return "4th 10GB/s eth";
        break;
    default:
        return "Weirdo iface";
    }
}

function cluster_config_list($con,$conf){
    $out = "";
    $r = mysqli_query($con, "SELECT * FROM clusters");
    $n = mysqli_num_rows($r);
    for($i=0;$i<$n;$i++){
        $a = mysqli_fetch_array($r);
        $name = $a["name"];
        $cluster_id = $a["id"];
        $cluster_fm = $a["first_master_machine_id"]; 
        $domain = $a["domain"];
        $swift_part_power        = $a["swift_part_power"];
        $swift_replicas          = $a["swift_replicas"];
        $swift_min_part_hours    = $a["swift_min_part_hours"];
        $vip_hostname            = $a["vip_hostname"];
        $swift_proxy_hostname    = $a["swift_proxy_hostname"];
        $swift_encryption_key_id = $a["swift_encryption_key_id"];
        $haproxy_custom_url      = $a["haproxy_custom_url"];
        $statsd_hostname         = $a["statsd_hostname"];
        $out .= "<div id=\"$name\" class=\"vtabcontent\" style=\"display:none;\">\n";

        // General cluster info
        $out .= "<table border='0'><tr><td valign='top'>";
        
        $out .= "<h2>Machines count</h2>";
        // Get all role counts for this cluster
        $q = "SELECT * FROM rolecounts WHERE cluster='$cluster_id'";
        $r_role_counts = mysqli_query($con, $q);
        $n_roles = mysqli_num_rows($r_role_counts);
        for($j=0;$j<$n_roles;$j++){
            // Fetch matching role name
            $a_role = mysqli_fetch_array($r_role_counts);
            $role_id = $a_role["role"];
            $q = "SELECT name FROM roles WHERE id='$role_id'";
            $r_role_name = mysqli_query($con, $q);
            $a_my_role = mysqli_fetch_array($r_role_name);
            $role_name = $a_my_role["name"];
            // Count how many machines by that role on this cluster
            $q = "SELECT count(role) AS rolecount FROM machines WHERE cluster='$cluster_id' AND role='$role_name'";
            $res = mysqli_query($con, $q);
            $row = mysqli_fetch_row($res);
            $role_count = $row[0];
            if($role_count != 0){
                $out .= $role_name . ": $role_count<br>";
            }
        }

        $out .= "</td><td valign='top'>";

        /////////////////////////////////
        // First master selection form //
        /////////////////////////////////
        $q = "SELECT * FROM machines WHERE cluster='$cluster_id' AND role='controller'";
        $r_fm = mysqli_query($con, $q);
        $n_fm = mysqli_num_rows($r_fm);

        if($n_fm > 0){
            $out .= "<h2>Cluster's first master</h2>";
            $out .= "<form action='?'><input type='hidden' name='v-open-tab' value='$name'><input type='hidden' name='h-open-tab' value='Clusters'><input type='hidden' name='action' value='select_frist_master_id'><input type='hidden' name='cluster-id' value='$cluster_id'><select name='first_master_id'>";
            for($j=0;$j<$n_fm;$j++){
                $a_fm = mysqli_fetch_array($r_fm);
                $fm_id = $a_fm["id"];
                $fm_hostname = $a_fm["hostname"];
                if($cluster_fm == $fm_id){
                    $selector = " selected";
                }else{
                    $selector = "";
                }
                $out .= "<option value='$fm_id'$selector>$fm_hostname</option>";
            }
            $out .= "</select><input type='submit' name='submit' value='Ok'></form>";
        }

        $out .= "</td><td valign='top'>";

        ////////////////////////////
        // Cluster's network form //
        ////////////////////////////
        $out .= "<h2>Cluster's networks</h2>";

        $out .= "<table border='1'>
<tr><th>Network</th><th>Location</th><th>VIP</th><th>Role</th><th>Interfaces &amp; bonds</th><th>VLAN</th><th>Action</th></tr>\n";


        $q_n = "SELECT * FROM networks WHERE cluster='$cluster_id'";
        $r_n = mysqli_query($con, $q_n);
        $n_n = mysqli_num_rows($r_n);
        for($j=0;$j<$n_n;$j++){
            $a_n = mysqli_fetch_array($r_n);
            $n_id     = $a_n["id"];
            $n_name   = $a_n["name"];
            $n_ip     = $a_n["ip"];
            $n_cidr   = $a_n["cidr"];
            $n_role   = $a_n["role"];
            $n_iface1 = ethtype_to_nice_display($con, $conf, $a_n["iface1"]);
            if($a_n["iface2"] != "none"){
                $n_iface2 = ethtype_to_nice_display($con, $conf, $a_n["iface2"]);
            }else{
                $n_iface2 = $a_n["iface2"];
            }
            $n_ifaces = $n_iface1;
            if($n_iface2 != "none"){
                $n_ifaces .= " bond with " . $n_iface2;
            }
            $n_locid  = $a_n["location_id"];

            if(is_null($a_n["vlan"])){
                $n_vlan = "No";
            }else{
                $n_vlan = $a_n["vlan"];
            }

            $qnl = "SELECT * FROM locations WHERE id='$n_locid'";
            $rnl = mysqli_query($con, $qnl);
            $anl = mysqli_fetch_array($rnl);
            $location_name = $anl["name"];

            if(is_null($n_role)){
                $n_role = "All roles";
            }
            $q_vip = "SELECT INET_NTOA(ip) AS addr FROM ips WHERE network='$n_id' AND usefor='vip'";
            $r_vip = mysqli_query($con, $q_vip);
            $n_vip = mysqli_num_rows($r_vip);
            if($n_vip == 1){
                $a_vip = mysqli_fetch_array($r_vip);
                $vip_addr = $a_vip["addr"];
            }else{
                $vip_addr = "";
            }

            $out .= "<tr><td>$n_name: $n_ip/$n_cidr</td><td>$location_name</td><td>$vip_addr</td><td>$n_role</td><td>$n_ifaces</td><td>$n_vlan</td><td><form action='?'><input type='hidden' name='v-open-tab' value='$name'><input type='hidden' name='h-open-tab' value='Clusters'><input type='hidden' name='action' value='delete_network_from_cluster'><input type='hidden' name='cluster_id' value='$cluster_id'><input type='hidden' name='network_id' value='$n_id'><input type='submit' value='Delete'></form></td></tr>";
        }

        $q_n = "SELECT * FROM networks WHERE cluster IS NULL";
        $r_n = mysqli_query($con, $q_n);
        $n_n = mysqli_num_rows($r_n);
        if($n_n > 0){
            $out .= "<tr><td>";
            $out .= "<form action='?'><input type='hidden' name='v-open-tab' value='$name'><input type='hidden' name='h-open-tab' value='Clusters'><input type='hidden' name='action' value='add_network_to_cluster'><input type='hidden' name='cluster_id' value='$cluster_id'><select name='network_id'>";
            for($j=0;$j<$n_n;$j++){
                $a_n = mysqli_fetch_array($r_n);
                $n_id   = $a_n["id"];
                $n_name = $a_n["name"];
                $n_ip   = $a_n["ip"];
                $n_cidr = $a_n["cidr"];
                $out .= "<option value='$n_id'>$n_name: $n_ip/$n_cidr</option>";
            }
            $out .= "<select>";
            $out .= "</td><td></td><td></td><td>".role_selector($con, $conf, "none", "yes")."</td>";
            $out .= "<td><select name='iface1'>
<option value='eth0'>eth0</option>
<option value='eth1'>eth1</option>
<option value='eth2'>eth2</option>
<option value='eth3'>eth3</option>
<option value='eth4'>eth4</option>
<option value='eth5'>eth5</option>
<option value='10m1'>10 Mb/s, 1st card</option>
<option value='10m2'>10 Mb/s, 2nd card</option>
<option value='10m3'>10 Mb/s, 3rd card</option>
<option value='10m4'>10 Mb/s, 4th card</option>
<option value='100m1'>100 Mb/s, 1st card</option>
<option value='100m2'>100 Mb/s, 2nd card</option>
<option value='100m3'>100 Mb/s, 3rd card</option>
<option value='100m4'>100 Mb/s, 4th card</option>
<option value='1g1'>1 Gb/s, 1st card</option>
<option value='1g2'>1 Gb/s, 2nd card</option>
<option value='1g3'>1 Gb/s, 3rd card</option>
<option value='1g4'>1 Gb/s, 4rd card</option>
<option value='10g1'>10 Gb/s, 1st card</option>
<option value='10g2'>10 Gb/s, 2nd card</option>
<option value='10g3'>10 Gb/s, 3rd card</option>
<option value='10g4'>10 Gb/s, 4th card</option>
</select>";
            $out .= "<select name='iface2'>
<option value='none'>None</option>
<option value='eth0'>eth0</option>
<option value='eth1'>eth1</option>
<option value='eth2'>eth2</option>
<option value='eth3'>eth3</option>
<option value='eth4'>eth4</option>
<option value='eth5'>eth5</option>
<option value='10m1'>10 Mb/s, 1st card</option>
<option value='10m2'>10 Mb/s, 2nd card</option>
<option value='10m3'>10 Mb/s, 3rd card</option>
<option value='10m4'>10 Mb/s, 4th card</option>
<option value='100m1'>100 Mb/s, 1st card</option>
<option value='100m2'>100 Mb/s, 2nd card</option>
<option value='100m3'>100 Mb/s, 3rd card</option>
<option value='100m4'>100 Mb/s, 4th card</option>
<option value='1g1'>1 Gb/s, 1st card</option>
<option value='1g2'>1 Gb/s, 2nd card</option>
<option value='1g3'>1 Gb/s, 3rd card</option>
<option value='1g4'>1 Gb/s, 4rd card</option>
<option value='10g1'>10 Gb/s, 1st card</option>
<option value='10g2'>10 Gb/s, 2nd card</option>
<option value='10g3'>10 Gb/s, 3rd card</option>
<option value='10g4'>10 Gb/s, 4th card</option>
</select></td>";
            $out .= "<td><input type='text' name='vlanid'></td>";
            $out .= "<td><input type='submit' text='Add' value='Add'></form></td></tr>";
        }
        $out .= "</table>";

        $out .= "</td></table>";

        /////////////////////////
        // Swift configuration //
        /////////////////////////
        $out .= "<table><tr><td>";
        $out .= "<h2>Cluster configuration:</h2>";
        $out .= "<table><form action=\"?\"><input type='hidden' name='v-open-tab' value='$name'><input type='hidden' name='h-open-tab' value='Clusters'><input type='hidden' name='action' value='configure_cluster_options'><input type='hidden' name='cluster_id' value='$cluster_id'><tr><td align='right'>Swift max num of partition:</td><td><input type='input' name='swift_part_power' value='$swift_part_power' size='8'></td></tr>
<tr><td align='right'>Swift number of replicas:</td><td><input type='input' name='swift_replicas' value='$swift_replicas' size='4'></td></tr>
<tr><td align='right'>Swift min part hours:</td><td><input type='input' name='swift_min_part_hours' value='$swift_min_part_hours' size='4'></td></tr>
<tr><td align='right'>VIP hostname (blank defaults to $name-api):</td><td><input type='input' name='vip_hostname' value='$vip_hostname' size='30'></td></tr>
<tr><td align='right'>Swift proxy hostname (blank: default to using VIP on controller):</td><td><input type='input' name='swift_proxy_hostname' value='$swift_proxy_hostname' size='30'></td></tr>
<tr><td align='right'>Swift encryption key id (blank: no encryption):</td><td><input type='input' name='swift_encryption_key_id' value='$swift_encryption_key_id' size='30'></td></tr>
<tr><td align='right'>Haproxy custom url redirect (blank: no redirect):</td><td><input type='input' name='haproxy_custom_url' value='$haproxy_custom_url' size='30'></td></tr>
<tr><td align='right'>Statsd hostname (empty = no swift stats logging):</td><td><input type='input' name='statsd_hostname' value='$statsd_hostname' size='30'></td></tr>
<tr><td></td><td><input type='submit' name='submit' value='Save'></td></form></tr>
</table><br>
<a target='_blank' href='build_swift_ring.php?cluster_id=$cluster_id'>(re-)build Swift rings</a>";
        $out .= "</td></tr></table>";

        ////////////////////////////////////////////
        // Add and remove machines to the cluster //
        ////////////////////////////////////////////
        $out .= "<h2>Machines in the cluster</h2>";
        $rm = mysqli_query($con, "SELECT * FROM machines WHERE cluster='".$a["id"]."' ORDER BY location_id,role");
        $nm = mysqli_num_rows($rm);
        $out .= "<table border='1'>
<tr><th>Notes</th><th>Location</th><th>Hostname</th><th>IP(s)</th><th>Memory</th><th>HDD</th><th>Role</th><th>status</th><th>Action</th></tr>\n";
        for($j=0;$j<$nm;$j++){
            $am = mysqli_fetch_array($rm);

            $blockdevs = "";
            $q2 = "SELECT MIN(name) AS first_dev, MAX(name) AS last_dev,size_mb, COUNT(id) AS numdev FROM blockdevices WHERE machine_id='".$am["id"]."' GROUP BY size_mb ORDER BY name";
            $r2 = mysqli_query($con, $q2);
            $n2 = mysqli_num_rows($r2);
            for($i2=0; $i2<$n2; $i2++){
                $a2 = mysqli_fetch_array($r2);
                if($a2["numdev"] == 1){
                    $blockdevs .= $a2["first_dev"] . ": " . mb_to_smart_size($a2["size_mb"]) . "<br>";
                }else{
                    $blockdevs .= $a2["first_dev"] . '-' . $a2["last_dev"] . ": " . $a2["numdev"] . "x" . mb_to_smart_size($a2["size_mb"]) . "<br>";
                }
            }

            $q_mip = "SELECT INET_NTOA(ip) AS addr FROM ips WHERE machine='".$am["id"]."' ORDER BY network";
            $r_mip = mysqli_query($con, $q_mip);
            $n_mip = mysqli_num_rows($r_mip);
            $my_addrs = "";
            for($k=0;$k<$n_mip;$k++){
                $a_mip = mysqli_fetch_array($r_mip);
                if($my_addrs == ""){
                    $my_addrs .= $a_mip["addr"];
                }else{
                    $my_addrs .= "<br>".$a_mip["addr"];
                }
            }

            $out .= "<tr>";

            $qloc = "SELECT name FROM locations WHERE id='".$am["location_id"]."'";
            $rloc = mysqli_query($con, $qloc);
            $aloc = mysqli_fetch_array($rloc);
            $nloc = $aloc["name"];
            $out .= "<td>".$am["notes"]."</td>";
            $out .= "<td>$nloc</td>";

            $out .= "<td>".$am["hostname"]."</td>";
            $out .= "<td>$my_addrs</td>";
            $out .= "<td>". mb_to_smart_size($am["memory"]) ."</td>";
            $out .= "<td>$blockdevs</td>";
            $out .= "<td>".$am["role"]."</td>";
            $out .= "<td>".$am["status"]."</td>";
            $out .= "<td><form action='?'><input type='hidden' name='v-open-tab' value='$name'><input type='hidden' name='h-open-tab' value='Clusters'><input type='hidden' name='action' value='remove_from_cluster'><input type='hidden' name='machine-id' value='".$am["id"]."'><input type='hidden' name='cluster-id' value='$cluster_id'><input type='submit' text='Remove' value='Remove'></form></td>";
            $out .= "</tr>";
        }
        $out .= "</table>";
        $out .= "<h2>Available machines</h2>";
        $rm = mysqli_query($con, "SELECT * FROM machines WHERE cluster IS NULL ORDER BY product_name, serial");
        $nm = mysqli_num_rows($rm);
        $out .= "<form action='?'><input type='hidden' name='h-open-tab' value='Clusters'><input type='hidden' name='action' value='add_machines_to_cluster'><table border='1'>
<tr><th>Notes</th><th>Cur. IP</th><th>Serial</th><th>Memory</th><th>HDD</th><th>status</th><th>Select</th></tr>\n";
        for($j=0;$j<$nm;$j++){
            $am = mysqli_fetch_array($rm);

            $blockdevs = "";
            $q2 = "SELECT MIN(name) AS first_dev, MAX(name) AS last_dev,size_mb, COUNT(id) AS numdev FROM blockdevices WHERE machine_id='".$am["id"]."' GROUP BY size_mb ORDER BY name";
            $r2 = mysqli_query($con, $q2);
            $n2 = mysqli_num_rows($r2);
            for($i2=0; $i2<$n2; $i2++){
                $a2 = mysqli_fetch_array($r2);
                if($a2["numdev"] == 1){
                    $blockdevs .= $a2["first_dev"] . ": " . mb_to_smart_size($a2["size_mb"]) . "<br>";
                }else{
                    $blockdevs .= $a2["first_dev"] . '-' . $a2["last_dev"] . ": " . $a2["numdev"] . "x" . mb_to_smart_size($a2["size_mb"]) . "<br>";
                }
            }

            $out .= "<tr>";
            $out .= "<td>".$am["notes"]."</td>";
            $out .= "<td>".$am["ipaddr"]."</td>";
            $out .= "<td>".$am["serial"]." / " . $am["product_name"] ."</td>";
            $out .= "<td>". mb_to_smart_size($am["memory"]) ."</td>";
            $out .= "<td>$blockdevs</td>";
            $out .= "<td>".$am["status"]."</td>";
            $out .= "<td><input type='checkbox' name='id[]' value='".$am["id"]."'></td>";
            $out .= "</tr>";
        }
        $out .= "</table>";
        $out .= "<select name=\"role\">";
        $qr = "SELECT * FROM roles";
        $rr = mysqli_query($con, $qr);
        $nr = mysqli_num_rows($rr);
        for($j=0;$j<$nr;$j++){
            $a = mysqli_fetch_array($rr);
            $role_name = $a["name"];
            $out .= "<option value=\"$role_name\">$role_name</option>";
        }
        $out .= "</select>";
        $out .= location_popup($con, -1);
        $out .= "<input type='hidden' name='v-open-tab' value='$name'><input type='hidden' name='cluster-id' value='$cluster_id'><input type=\"submit\" text=\"Add to cluster\" value=\"Add to cluster\"></form>";
        $out .= "</div>\n";
    }
    return $out;
}

function role_list($con,$conf){
    $out = "";
    $out .= "<table border='1'>
<tr><th>Role name</th><th>Action</th></tr>\n";
    $r = mysqli_query($con, "SELECT * FROM roles");
    $n = mysqli_num_rows($r);
    for($i=0;$i<$n;$i++){
        $a = mysqli_fetch_array($r);
        $name = $a["name"];
        $id   = $a["id"];
        $out .= "<tr><td>$name</td><td><form action='?'><input type='hidden' name='h-open-tab' value='Roles'><input type='hidden' name='action' value='delete_role'><input type='hidden' name='id' value='$id'><input type=\"submit\" text=\"Delete\" value=\"Delete\"></form></td></tr>";
    }
    $out .= "<td><form action='?'><input type='text' name='name'></td><td><input type='hidden' name='h-open-tab' value='Roles'><input type='hidden' name='action' value='add_role'><input type=\"submit\" text=\"Add\" value=\"Add\"></form></td>";
    $out .= "</table>";
    return $out;
}

function cidr_selector($cidr){
    $out = "";
    $out .= "<select name='cidr'>";
    for($i=8;$i<=32;$i++){
        if($i == $cidr){
            $selected = ' selected';
        }else{
            $selected = '';
        }
        $out .= "<option value='$i' $selected>/$i</option>";
    }
    $out .= "</select>";
    return $out;
}

function location_popup($con, $location_id){
        $lp = "<select name='location_id'>";
        $ql = "SELECT * FROM locations";
        $rl = mysqli_query($con, $ql);
        $nl = mysqli_num_rows($rl);
        for($j=0;$j<$nl;$j++){
            $al = mysqli_fetch_array($rl);
            $al_id   = $al["id"];
            $al_name = $al["name"];
            if($location_id == $al_id){
                $selected = ' selected';
            }else{
                $selected = '';
            }
            $lp .= "<option value='$al_id' $selected>$al_name</option>";
        }
        $lp .= "</select>";
        return $lp;
}

function mtu_popup($con, $mtu){
    $selected_none = "";
    $selected_1492 = "";
    $selected_1500 = "";
    $selected_9000 = "";
    switch($mtu){
    case "1492":
        $selected_1492 = " selected";
        break;
    case "1500":
        $selected_1500 = " selected";
        break;
    case "9000":
        $selected_9000 = " selected";
        break;
    default:
        $selected_none = " selected";
        break;
    }
    $out = "<select name='mtu'>
<option value='0'$selected_1492>None</option>
<option value='1492'$selected_1492>1492</option>
<option value='1500'$selected_1500>1500</option>
<option value='9000'$selected_9000>9000</option>
</select>";
    return $out;
}

function network_list($con,$conf){
    $out = "";
    $out .= "<table border='1'>
<tr><th>Network name</th><th>Network ip</th><th>CIDR</th><th>MTU</th><th>Is public</th><th>Location</th><th>Action</th></tr>\n";
    $q = "SELECT * FROM networks ORDER BY ip";
    $r = mysqli_query($con, $q);
    $n = mysqli_num_rows($r);
    for($i=0;$i<$n;$i++){
        $a = mysqli_fetch_array($r);
        $id        = $a["id"];
        $name      = $a["name"];
        $ip        = $a["ip"];
        $cidr      = $a["cidr"];
        $mtu       = $a["mtu"];
        $is_public = $a["is_public"];
        $location_id = $a["location_id"];
        if($is_public == "yes"){
            $public_selected = " checked";
        }else{
            $public_selected = "";
        }

        $out .= "<tr><form action='?'><td><input type='text' name='name' value='$name'></td><td><input type='text' name='ip' value='$ip'></td>
<td>".cidr_selector($cidr)."</td>
<td>".mtu_popup($con, $mtu)."</td>
<td><input type='checkbox' name='is_public' value='yes'$public_selected></td>
<td>".location_popup($con, $location_id)."</td>
<td><input type='hidden' name='id' value='$id'><input type='hidden' name='h-open-tab' value='Networks'><input type='hidden' name='action' value='edit_network'><input type=\"submit\" text=\"Save\" value=\"Save\"></form>
<form action='?'><input type='hidden' name='id' value='$id'><input type='hidden' name='h-open-tab' value='Networks'><input type='hidden' name='action' value='delete_network'><input type=\"submit\" text=\"Delete\" value=\"Delete\"></form>
</td></tr>";
    }
    $out .= "<tr><form action='?'><td><input type='text' name='name'></td><td><input type='text' name='ip'></td>
<td>".cidr_selector(24)."</td>
<td>".mtu_popup($con, 9000)."</td>
<td><input type='checkbox' name='is_public' value='yes'></td>
<td>".location_popup($con, -1)."</td>
<td><input type='hidden' name='h-open-tab' value='Networks'><input type='hidden' name='action' value='create_network'><input type=\"submit\" text=\"Add\" value=\"Add\"></form></td></tr>";
    $out .= "</table>";
    return $out;
}

function location_list($con,$conf){
    $out = "";
    $out .= "<table border='1'>
<tr><th>Location name</th><th>Swift region</th><th>Action</th></tr>\n";
    $r = mysqli_query($con, "SELECT * FROM locations");
    $n = mysqli_num_rows($r);
    for($i=0;$i<$n;$i++){
        $a = mysqli_fetch_array($r);
        $name = $a["name"];
        $id   = $a["id"];
        $sr   = $a["swiftregion"];
        $out .= "<tr><td>$name</td><td>$sr</td><td><form action='?'><input type='hidden' name='h-open-tab' value='Locations'><input type='hidden' name='action' value='delete_location'><input type='hidden' name='id' value='$id'><input type=\"submit\" text=\"Delete\" value=\"Delete\"></form></td></tr>";
    }

    $q = "SELECT * FROM swiftregions ORDER BY name";
    $r = mysqli_query($con, $q);
    $n = mysqli_num_rows($r);
    $dropdown = "<select name='swiftregion'><option value=''>None</option>";
    for($i=0;$i<$n;$i++){
        $a = mysqli_fetch_array($r);
        $name = $a["name"];
        $dropdown .= "<option value='$name'>$name</option>";
    }
    $dropdown .= "</select>";
    $out .= "<form action='?'><td><input type='text' name='name'></td><td>$dropdown</td><td><input type='hidden' name='h-open-tab' value='Locations'><input type='hidden' name='action' value='add_location'><input type=\"submit\" text=\"Add\" value=\"Add\"></form></td>";
    $out .= "</table>";
    return $out;
}

function swift_regions_list($con,$conf){
    $out = "";
    $out .= "<table border='1'>
<tr><th>Location name</th><th>Action</th></tr>\n";
    $r = mysqli_query($con, "SELECT * FROM swiftregions");
    $n = mysqli_num_rows($r);
    for($i=0;$i<$n;$i++){
        $a = mysqli_fetch_array($r);
        $name = $a["name"];
        $id   = $a["id"];
        $out .= "<tr><td>$name</td><td><form action='?'><input type='hidden' name='h-open-tab' value='Swift-regions'><input type='hidden' name='action' value='delete_swiftregion'><input type='hidden' name='id' value='$id'><input type=\"submit\" text=\"Delete\" value=\"Delete\"></form></td></tr>";
    }
    $out .= "<td><form action='?'><input type='text' name='name'></td><td><input type='hidden' name='h-open-tab' value='Swift-regions'><input type='hidden' name='action' value='add_swiftregion'><input type=\"submit\" text=\"Add\" value=\"Add\"></form></td>";
    
    $out .= "</table>";
    return $out;
}

?>
