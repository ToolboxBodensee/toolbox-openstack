<?php

function perform_actions($con,$conf){
    $out = "";
    if(!isset($_REQUEST["action"])){
        return $out;
    }

    switch($_REQUEST["action"]){
    case "install_os":
        # Validate IPv4
        $safe_ip = safe_ipv4("ipaddr");
        if($safe_ip === FALSE){
            $out .= "Wrong ipv4 format.";
            return $out;
        }
        if(check_machine_with_ip_exists($con, $safe_ip) === FALSE){
            $out .= "Cannot find machine with this IP address.";
            return $out;
        }
        # Get ID from IPv4
        $machine_id = get_machine_id_from_ip($con, $safe_ip);

        # Validate hostname, commit it to DB before calculating
        # command line and launching install
        $safe_fqdn = safe_fqdn("hostname");
        if($safe_fqdn === FALSE){
            $out = "Missing or wrong hostname.";
            return $out;
        }
        $r = mysqli_query($con, "UPDATE machines SET hostname='$safe_fqdn' WHERE ipaddr='$safe_ip'");

        $slave_install_return = slave_install_server_os_command($con, $conf, $machine_id);
        if($slave_install_return["status"] != "success"){
          return "Error while calculating installation command line for host $safe_fqdn: ".$slave_install_return["message"];
        }
        $out .= "Running: ". $slave_install_return["cmd"];
        slave_install_os($con, $conf, $machine_id, $slave_install_return["cmd"]);
        return $out;
        break;
    case "reboot_on_live":
        # Validate IPv4
        $safe_ip = safe_ipv4("ipaddr");
        if($safe_ip === FALSE){
            $out .= "Wrong ipv4 format.";
            return $out;
        }
        if(check_machine_with_ip_exists($con, $safe_ip) === FALSE){
            $out .= "Cannot find machine with this IP address.";
            return $out;
        }
        $q = "SELECT id FROM machines WHERE ipaddr='$safe_ip'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $out = mysqli_error($con);
            return $out;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            return "No such machine with IP $safe_ip.";
        }
        $machine = mysqli_fetch_array($r);
        $machine_id = $machine["id"];

        ipmi_set_boot_device($con, $conf, $machine_id, "pxe");

        $r = mysqli_query($con, "UPDATE machines SET status='bootinglive' WHERE ipaddr='$safe_ip'");
        $ret = send_ssh_cmd($conf, $con, $_REQUEST["ipaddr"], "shutdown -r now");
        break;
    case "reboot_on_hdd":
        # Validate IPv4
        $safe_ip = safe_ipv4("ipaddr");
        if($safe_ip === FALSE){
            $out .= "Wrong ipv4 format.";
            return $out;
        }
        if(check_machine_with_ip_exists($con, $safe_ip) === FALSE){
            $out .= "Cannot find machine with this IP address.";
            return $out;
        }

        $q = "SELECT id FROM machines WHERE ipaddr='$safe_ip'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $out = mysqli_error($con);
            return $out;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            return "No such machine with IP $safe_ip.";
        }
        $machine = mysqli_fetch_array($r);
        $machine_id = $machine["id"];

        ipmi_set_boot_device($con, $conf, $machine_id, "disk");

        $r = mysqli_query($con, "UPDATE machines SET status='firstboot' WHERE ipaddr='$safe_ip'");
        $ret = send_ssh_cmd($conf, $con, $_REQUEST["ipaddr"], "shutdown -r now");
        break;
    case "ipmi_reboot_on_hdd":
        // ipmitool -I lanplus -H 192.168.100.1 -U ipmiusr -p 9002 -P test power cycle
        $safe_ip = safe_ipv4("ipaddr");
        if($safe_ip === FALSE){
            $out .= "Wrong ipv4 format.";
            return $out;
        }
        if(check_machine_with_ip_exists($con, $safe_ip) === FALSE){
            $out .= "Cannot find machine with this IP address.";
            return $out;
        }

        $q = "SELECT id FROM machines WHERE ipaddr='$safe_ip'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $out = mysqli_error($con);
            return $out;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            return "No such machine with IP $safe_ip.";
        }
        $machine = mysqli_fetch_array($r);
        $machine_id = $machine["id"];

        ipmi_set_boot_device($con, $conf, $machine_id, "disk");

        $r = mysqli_query($con, "UPDATE machines SET status='firstboot' WHERE ipaddr='$safe_ip'");
        $q = "SELECT * FROM machines WHERE ipaddr='$safe_ip'";
        $r = mysqli_query($con, $q);
        $machine = mysqli_fetch_array($r);
        $ipmi_addr     = $machine["ipmi_addr"];
        $ipmi_port     = $machine["ipmi_port"];
        $ipmi_username = $machine["ipmi_username"];
        $ipmi_password = $machine["ipmi_password"];
        $cmd = "ipmitool -I lanplus -H $ipmi_addr -p $ipmi_port -U $ipmi_username -P $ipmi_password power cycle";
        $output = "";
        $return_var = 0;
        exec($cmd, $output, $return_var);
        break;
    case "ipmi_reboot_on_live":
        // ipmitool -I lanplus -H 192.168.100.1 -U ipmiusr -p 9002 -P test power cycle
        $safe_ip = safe_ipv4("ipaddr");
        if($safe_ip === FALSE){
            $out .= "Wrong ipv4 format.";
            return $out;
        }
        if(check_machine_with_ip_exists($con, $safe_ip) === FALSE){
            $out .= "Cannot find machine with this IP address.";
            return $out;
        }

        $q = "SELECT id FROM machines WHERE ipaddr='$safe_ip'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $out = mysqli_error($con);
            return $out;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            return "No such machine with IP $safe_ip.";
        }
        $machine = mysqli_fetch_array($r);
        $machine_id = $machine["id"];

        ipmi_set_boot_device($con, $conf, $machine_id, "pxe");

        $r = mysqli_query($con, "UPDATE machines SET status='bootinglive' WHERE ipaddr='$safe_ip'");
        $q = "SELECT * FROM machines WHERE ipaddr='$safe_ip'";
        $r = mysqli_query($con, $q);
        $machine = mysqli_fetch_array($r);
        $ipmi_addr     = $machine["ipmi_addr"];
        $ipmi_port     = $machine["ipmi_port"];
        $ipmi_username = $machine["ipmi_username"];
        $ipmi_password = $machine["ipmi_password"];
        $cmd = "ipmitool -I lanplus -H $ipmi_addr -p $ipmi_port -U $ipmi_username -P $ipmi_password power cycle";
        $output = "";
        $return_var = 0;
        exec($cmd, $output, $return_var);
        break;
    case "edit_machine_notes":
        $safe_ip = safe_ipv4("ipaddr");
        if($safe_ip === FALSE){
            $out .= "Wrong ipv4 format.";
            return $out;
        }
        if(check_machine_with_ip_exists($con, $safe_ip) === FALSE){
            $out .= "Cannot find machine with this IP address.";
            return $out;
        }
        $r = mysqli_query($con, "UPDATE machines SET notes='".  mysqli_real_escape_string($con, $_REQUEST["notes"]) ."' WHERE ipaddr='$safe_ip'");
        break;
    case "new_cluster":
        $safe_domain = safe_fqdn("domain");
        if($safe_domain === FALSE){
            $out .= "Domain name in wrong format.";
            return $out;
        }
        $safe_cluster = safe_cluster_name("name");
        if($safe_cluster === FALSE){
            $out .= "Cluster name in wrong format.";
        }
        $ret = new_cluster($con, $conf, $safe_cluster, $safe_domain);
        if($ret["status"] != "success"){
            $out .= "Error creating cluster: ". $ret["message"];
            return $out;
        }
        break;
    case "delete_cluster":
        $safe_id = safe_int("id");
        if($safe_id === FALSE){
            $out .= "Cluster ID in wrong format.";
            return $out;
        }
        if(check_cluster_with_id_exists($con, $safe_id) === FALSE){
            $out .= "Cluster with ID $safe_id not found.";
            return $out;
        }
        $q = "DELETE FROM clusters WHERE id='$safe_id'";
        $r = mysqli_query($con, $q);
        $q = "UPDATE machines SET cluster=NULL, role='' WHERE cluster='$safe_id'";
        $r = mysqli_query($con, $q);
        $q = "DELETE FROM rolecounts WHERE cluster='$safe_id'";
        $r = mysqli_query($con, $q);
        break;
    case "remove_from_cluster":
        // TODO: Check validity of cluster-id and machine-id in the DB before the UPDATE call
        $safe_machine_id = safe_int("machine-id");
        if($safe_machine_id === FALSE){
            $out .= "Machine ID in wrong format.";
            return $out;
        }
        $q = "SELECT role, cluster FROM machines WHERE id='$safe_machine_id';";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n == 0){
            $out = "No machine by this ID.";
            return $out;
        }
        $machine = mysqli_fetch_array($r);
        $safe_role = $machine["role"];
        $safe_cluster_id = $machine["cluster"];

        $q = "UPDATE machines SET cluster=NULL WHERE id='$safe_machine_id'";
        $r = mysqli_query($con, $q);

        // If role is controller, check if there's already controller. If there's none, then
        // we must set a controller as first master
        if($safe_role == "controller"){
            $q = "SELECT first_master_machine_id FROM clusters WHERE id='$safe_cluster_id'";
            $r = mysqli_query($con, $q);
            $cluster = mysqli_fetch_array($r);
            $fm_id = $cluster["first_master_machine_id"];;
            if($fm_id == $safe_machine_id){
                $q = "SELECT * FROM machines WHERE cluster='$safe_cluster_id' AND role='controller' LIMIT 1;";
                $r = mysqli_query($con, $q);
                $n = mysqli_num_rows($r);
                // No controler left: we set the first_master as NULL
                if($n == 0){
                    $q = "UPDATE clusters SET first_master_machine_id=NULL WHERE id='$safe_cluster_id'";
                    $r = mysqli_query($con, $q);
                // We just set the first controler we find left as first_master
                }else{
                    $first_master = mysqli_fetch_array($r);
                    $new_fm_id = $first_master["id"];
                    $q = "UPDATE clusters SET first_master_machine_id='$new_fm_id' WHERE id='$safe_cluster_id'";
                    $r = mysqli_query($con, $q);
                }
            }
        }
        // Delete all IPs of the machine in the DB
        $q = "DELETE FROM ips WHERE machine='$safe_machine_id'";
        $r = mysqli_query($con, $q);
        return $out;
        break;
    case "add_machines_to_cluster":
        // Request like: Array ( [id] => Array ( [0] => 5 [1] => 6 ) [role] => controller [cluster-id] => 2 ) 
        // Validate cluster ID, and fetch its name and domain
        $safe_cluster_id = safe_int("cluster-id");
        if($safe_cluster_id === FALSE){
            $out .= "Cluster ID in wrong format.";
            return $out;
        }
        $q = "SELECT * FROM clusters WHERE id='$safe_cluster_id'";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n != 1){
            $out .= "Cluster with id $safe_cluster_id not found.";
            return $out;
        }
        $cluster = mysqli_fetch_array($r);
        $cluster_name = $cluster["name"];
        $cluster_domain = $cluster["domain"];

        // Validate roles
        if(!isset($_REQUEST["role"])){
            $out .= "No role selected.";
            return $out;
        }
        $safe_role = safe_fqdn("role");
        if($safe_role === FALSE){
            $out .= "Role not in correct format.";
            return $out;
        }
        $q = "SELECT * FROM roles WHERE name='$safe_role'";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n != 1){
            $out .= "Role not found in db.";
            return $out;
        }

        // Find role count
        $a = mysqli_fetch_array($r);
        $safe_role_id = $a["id"];
        $q = "SELECT * FROM rolecounts WHERE cluster='$safe_cluster_id' AND role='$safe_role_id'";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n == 0){
            $role_count = 0;
            $q = "INSERT INTO rolecounts (cluster, role, count) VALUES ('$safe_cluster_id', '$safe_role_id', '0')";
            $r = mysqli_query($con, $q);
            $role_count_id = mysqli_insert_id($con);
        }else{
            $a = mysqli_fetch_array($r);
            $role_count = $a["count"];
            $role_count_id = $a["id"];
        }

        // Validate machine IDs.
        $num_machines = 0;
        foreach($_REQUEST['id'] as $machine_id){
            $reg = '/^[0-9]{1,12}$/';
	    if(!preg_match($reg,$machine_id)){
	        $out .= "Machine IDs in wrong format.";
	        return $out;
            }
            $num_machines += 1;
        }
        $safe_machine_id_array = $_REQUEST['id'];

        // Validate location_id
        if(!isset($_REQUEST["location_id"])){
            $out .= "No location selected.";
            return $out;
        }
        $safe_location_id = safe_int("location_id");
        if($safe_role === FALSE){
            $out .= "Location not in correct format.";
            return $out;
        }

        // For all machines, set role, cluster and hostname
        foreach($safe_machine_id_array as $safe_machine_id){
            $ret = add_node_to_cluster($con, $conf, $safe_machine_id, $safe_cluster_id, $safe_role, $safe_location_id);
            if($ret["status"] != "success"){
                $out = $ret["message"];
            }
        }
        return $out;
        break;
    case "add_role":
        $safe_role_name = safe_fqdn("name");
        if($safe_role_name === FALSE){
            $out .= "Role name in wrong format.";
            return $out;
        }
        $q = "INSERT INTO roles (name) VALUES ('$safe_role_name')";
        $r = mysqli_query($con, $q);
        break;
    case "delete_role":
        $safe_role_id = safe_int("id");
        if($safe_role_id === FALSE){
            $out .= "Role id is wrong.";
            return $out;
        }
        $q = "DELETE FROM roles WHERE id='$safe_role_id'";
        $r = mysqli_query($con, $q);
        break;
    case "add_location":
        $safe_location_name = safe_fqdn("name");
        if($safe_location_name === FALSE){
            $out .= "Location name in wrong format.";
            return $out;
        }
        $safe_swiftregion_name = safe_fqdn("swiftregion");
        if($safe_location_name === FALSE){
            $out .= "Swift region name in wrong format.";
            return $out;
        }
        $q = "INSERT INTO locations (name, swiftregion) VALUES ('$safe_location_name', '$safe_swiftregion_name')";
        $r = mysqli_query($con, $q);
        break;
    case "delete_location":
        $safe_location_id = safe_int("id");
        if($safe_location_id === FALSE){
            $out .= "Location id is wrong.";
            return $out;
        }
        $q = "DELETE FROM locations WHERE id='$safe_location_id'";
        $r = mysqli_query($con, $q);
        break;
    case "select_frist_master_id":
        $safe_fm_id = safe_int("first_master_id");
        if($safe_fm_id === FALSE){
            $out .= "Wrong format for first master ID.";
            return $out;
        }
        $safe_cluster_id = safe_int("cluster-id");
        if($safe_cluster_id === FALSE){
            $out .= "Wrong format for cluster ID.";
            return $out;
        }
        $q = "SELECT * FROM machines WHERE cluster='$safe_cluster_id' AND id='$safe_fm_id'";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n == 0){
            $out .= "No such machine in the cluster.";
            return $out;
        }
        $q = "UPDATE clusters SET first_master_machine_id='$safe_fm_id' WHERE id='$safe_cluster_id'";
        $r = mysqli_query($con, $q);
        break;
    case "create_network":
        $safe_network_name = safe_fqdn("name");
        if($safe_network_name === FALSE){
            $out .= "Network name in wrong format.";
            return $out;
        }
        $safe_ip = safe_ipv4("ip");
        if($safe_ip === FALSE){
            $out .= "IP in wrong format.";
            return $out;
        }
        $safe_cidr = safe_int("cidr");
        if($safe_cidr === FALSE || $safe_cidr < 8 || $safe_cidr > 32){
            $out .= "CIDR in wrong format.";
            return $out;
        }
        $safe_mtu = safe_int("mtu");
        if($safe_mtu === FALSE || $safe_mtu < 0 || $safe_cidr > 9000){
            $out .= "MTU is in wrong format.";
            return $out;
        }
        if( isset($_REQUEST["is_public"]) && $_REQUEST["is_public"] == "yes"){
            $safe_is_public = "yes";
        }else{
            $safe_is_public = "no";
        }
        $safe_location_id = safe_int("location_id");
        if($safe_location_id === FALSE){
            $out .= "Location ID in wrong format.";
            return $out;
        }
        $q = "INSERT INTO networks (name, ip, cidr, mtu, is_public, location_id) VALUES ('$safe_network_name', '$safe_ip', '$safe_cidr', '$safe_mtu', '$safe_is_public', '$safe_location_id')";
        $r = mysqli_query($con, $q);
        break;
    case "edit_network":
        $safe_network_name = safe_fqdn("name");
        if($safe_network_name === FALSE){
            $out .= "Network name in wrong format.";
            return $out;
        }
        $safe_ip = safe_ipv4("ip");
        if($safe_ip === FALSE){
            $out .= "IP in wrong format.";
            return $out;
        }
        $safe_cidr = safe_int("cidr");
        if($safe_cidr === FALSE || $safe_cidr < 8 || $safe_cidr > 32){
            $out .= "CIDR in wrong format.";
            return $out;
        }
        $safe_mtu = safe_int("mtu");
        if($safe_mtu === FALSE || $safe_mtu < 0 || $safe_cidr > 9000){
            $out .= "MTU is in wrong format.";
            return $out;
        }
        $safe_id = safe_int("id");
        if($safe_id === FALSE){
            $out .= "Network ID in wrong format.";
            return $out;
        }
        $q = "SELECT * FROM networks WHERE id='$safe_id'";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n != 1){
            $out .= "Cannot find network by that ID.";
            return $out;
        }
        $safe_location_id = safe_int("location_id");
        if($safe_location_id === FALSE){
            $out .= "Network location ID in wrong format.";
            return $out;
        }
        $q = "SELECT * FROM locations WHERE id='$safe_location_id'";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n != 1){
            $out .= "Cannot find location by that ID.";
            return $out;
        }

        if( isset($_REQUEST["is_public"]) && $_REQUEST["is_public"] == "yes"){
            $safe_is_public = "yes";
        }else{
            $safe_is_public = "no";
        }
        $q = "UPDATE networks SET name='$safe_network_name', ip='$safe_ip', cidr='$safe_cidr', mtu='$safe_mtu', is_public='$safe_is_public', location_id='$safe_location_id' WHERE id='$safe_id';";
        $r = mysqli_query($con, $q);
        break;
    case "delete_network":
        $safe_id = safe_int("id");
        if($safe_id === FALSE){
            $out .= "ID in wrong format.";
            return $out;
        }
        $q = "SELECT * FROM networks WHERE id='$safe_id'";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n != 1){
            $out .= "Cannot find network by that ID.";
            return $out;
        }
        $q = "DELETE FROM networks WHERE id='$safe_id';";
        $r = mysqli_query($con, $q);
        break;
    case "add_network_to_cluster":
        $safe_network_id = safe_int("network_id");
        if($safe_network_id === FALSE){
            $out .= "Network ID in wrong format.";
            return $out;
        }
        $q = "SELECT * FROM networks WHERE id='$safe_network_id'";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n != 1){
            $out .= "Cannot find network by that ID.";
            return $out;
        }
        $safe_role_name = safe_fqdn("role");
        if($safe_role_name === FALSE){
            $out .= "Role name in wrong format.";
            return $out;
        }
        if($safe_role_name == "all"){
            $sql_role = "'all'";
        }else{
            $q = "SELECT * FROM roles WHERE name='$safe_role_name'";
            $r = mysqli_query($con, $q);
            $n = mysqli_num_rows($r);
            if($n != 1 && $safe_role_name != "vm-net" && $safe_role_name != "ovs-bridge" && $safe_role_name != "ceph-cluster"){
                $out .= "Cannot find role by that name.";
                return $out;
            }
            $sql_role = "'$safe_role_name'";
        }

        $safe_cluster_id = safe_int("cluster_id");
        if($safe_cluster_id === FALSE){
            $out .= "Cluster ID in wrong format.";
            return $out;
        }
        $q = "SELECT * FROM clusters WHERE id='$safe_cluster_id'";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n != 1){
            $out .= "Cannot find cluster by that ID.";
            return $out;
        }

        $safe_iface1 = safe_fqdn("iface1");
        if($safe_cluster_id === FALSE){
            $out .= "Iface 1 in wrong format.";
            return $out;
        }
        $safe_iface2 = safe_fqdn("iface2");
        if($safe_cluster_id === FALSE){
            $out .= "Iface 1 in wrong format.";
            return $out;
        }

        if(!isset($_REQUEST["vlanid"])){
            $out .= "VLAN id not set.";
            return $out;
        }
        if($_REQUEST["vlanid"] == ""){
            $safe_vlanid = "NULL";
        }else{
            $safe_vlanid = safe_int("vlanid");
            $safe_vlanid = "'$safe_vlanid'";
        }

        $q = "UPDATE networks SET cluster='$safe_cluster_id', role=$sql_role, iface1='$safe_iface1', iface2='$safe_iface2', vlan=$safe_vlanid WHERE id='$safe_network_id'";
        $r = mysqli_query($con, $q);

        $q = "SELECT * FROM networks WHERE id='$safe_network_id'";
        $r = mysqli_query($con, $q);
        $network = mysqli_fetch_array($r);
        // Reserve the VIP
        if(($safe_role_name == "all" || $safe_role_name == "controller") && ($network["is_public"] == "yes") ){
            $ret = reserve_ip_address($con, $conf, $safe_network_id, 0, "vip");
            if($ret["status"] != "success"){
                return $ret["message"];
            }
        }
        if($network["is_public"] == "no" && $network["role"] != "ovs-bridge"){
            $ret = reserve_ip_to_all_slaves_of_network($con, $conf, $safe_cluster_id, $safe_network_id, $safe_role_name);
        }
        if($ret["status"] != "success"){
            return $ret["message"];
        }
        break;
    case "delete_network_from_cluster":
        // http://bdbdev-ogiz.infomaniak.ch/oci/?v-open-tab=zigo&h-open-tab=Clusters&action=delete_network_from_cluster&cluster_id=7&network_id=4
        $safe_network_id = safe_int("network_id");
        if($safe_network_id === FALSE){
            $out .= "Network ID in wrong format.";
            return $out;
        }
        $q = "SELECT * FROM networks WHERE id='$safe_network_id'";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n != 1){
            $out .= "Cannot find network by that ID.";
            return $out;
        }
        
        $safe_cluster_id = safe_int("cluster_id");
        if($safe_cluster_id === FALSE){
            $out .= "Cluster ID in wrong format.";
            return $out;
        }
        $q = "SELECT * FROM clusters WHERE id='$safe_cluster_id'";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n != 1){
            $out .= "Cannot find cluster by that ID.";
            return $out;
        }

        $q = "UPDATE networks SET cluster=NULL, role=NULL WHERE id='$safe_network_id'";
        $r = mysqli_query($con, $q);
        $q = "DELETE FROM ips WHERE network='$safe_network_id'";
        $r = mysqli_query($con, $q);
        break;
    case "set_ipmi":
        // http://bdbdev-ogiz.infomaniak.ch/oci/?action=set_ipmi&machine_id=18&ipmi_use=yes&ipmi_addr=192.168.100.1&ipmi_port=623&ipmi_username=ipmiusr&ipmi_password=test&save=Save
        $safe_machine_id = safe_int("machine_id");
        if($safe_machine_id === FALSE){
            $out .= "Machine id is wrong.";
            return $out;
        }
        if(!isset($_REQUEST["ipmi_use"])){
            $q = "UPDATE machines SET ipmi_use='no' WHERE id='$safe_machine_id'";
            $r = mysqli_query($con, $q);
            return $out;
        }
        if(isset($_REQUEST["ipmi_call_chassis_bootdev"])){
            $ipmi_call_chassis_bootdev = "yes";
        }else{
            $ipmi_call_chassis_bootdev = "no";
        }
        $safe_ipmi_addr = safe_fqdn("ipmi_addr");
        if($safe_ipmi_addr === FALSE){
            $safe_ipmi_addr = safe_ipv4("ipmi_addr");
            if($safe_ipmi_addr === FALSE){
                $out .= "IPMI address in wrong format.";
                return $out;
            }
        }
        $safe_ipmi_port = safe_int("ipmi_port");
        if($safe_ipmi_port === FALSE){
            $out .= "IPMI port in wrong format.";
            return $out;
        }
        $safe_ipmi_username = safe_fqdn("ipmi_username");
        if($safe_ipmi_username === FALSE){
            $out .= "IPMI username in wrong format.";
            return $out;
        }
        $safe_ipmi_password = safe_password("ipmi_password");
        if($safe_ipmi_password === FALSE){
            $out .= "IPMI password in wrong format.";
            return $out;
        }
        $q = "UPDATE machines SET ipmi_use='yes', ipmi_call_chassis_bootdev='$ipmi_call_chassis_bootdev', ipmi_addr='$safe_ipmi_addr', ipmi_port='$safe_ipmi_port', ipmi_username='$safe_ipmi_username', ipmi_password='$safe_ipmi_password' WHERE id='$safe_machine_id'";
        $r = mysqli_query($con, $q);
        break;
    case "configure_cluster_options":
        $safe_cluster_id = safe_int("cluster_id");
        if($safe_cluster_id === FALSE){
            $out .= "Cluster id is wrong.";
            return $out;
        }
        $safe_swift_part_power = safe_int("swift_part_power");
        if($safe_swift_part_power === FALSE){
            $out .= "swift_part_power is wrong.";
            return $out;
        }
        $safe_swift_replicas = safe_int("swift_replicas");
        if($safe_swift_replicas === FALSE){
            $out .= "swift_replicas is wrong.";
            return $out;
        }
        $safe_min_part_hours = safe_int("swift_min_part_hours");
        if($safe_min_part_hours === FALSE){
            $out .= "min_part_hours is wrong.";
            return $out;
        }
        if(isset($_REQUEST["vip_hostname"]) && $_REQUEST["vip_hostname"] == ""){
            $safe_vip_hostname = "";
        }else{
            $safe_vip_hostname = safe_fqdn("vip_hostname");
            if($safe_vip_hostname === FALSE){
                $out .= "VIP hostname is wrong.";
                return $out;
            }
        }
        if(isset($_REQUEST["swift_proxy_hostname"]) && $_REQUEST["swift_proxy_hostname"] == ""){
            $safe_swift_proxy_hostname = "";
        }else{
            $safe_swift_proxy_hostname = safe_fqdn("swift_proxy_hostname");
            if($safe_swift_proxy_hostname === FALSE){
                $out .= "Wrong swift_proxy_hostname";
                return $out;
            }
        }

        if(isset($_REQUEST["swift_encryption_key_id"]) && $_REQUEST["swift_encryption_key_id"] == ""){
            $safe_swift_encryption_key_id = "";
        }else{
            $safe_swift_encryption_key_id = safe_uuid("swift_encryption_key_id");
            if($safe_swift_encryption_key_id === FALSE){
                $out .= "Wrong swift_encryption_key_id";
                return $out;
            }
        }

        if(isset($_REQUEST["haproxy_custom_url"]) && $_REQUEST["haproxy_custom_url"] == ""){
            $haproxy_custom_url = "";
        }else{
            $safe_haproxy_custom_url = safe_url("haproxy_custom_url");
            if($safe_haproxy_custom_url === FALSE){
                $out .= "Wrong haproxy_custom_url";
                return $out;
            }
        }
        if(isset($_REQUEST["statsd_hostname"]) && $_REQUEST["statsd_hostname"] == ""){
            $safe_statsd_hostname = "";
        }else{
            $safe_statsd_hostname = safe_fqdn("statsd_hostname");
            if($safe_statsd_hostname === FALSE){
                $out .= "Wrong statsd hostname";
                return $out;
            }
        }

        $q = "SELECT * FROM clusters WHERE id='$safe_cluster_id'";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n != 1){
            $out .= "Cannot find cluster by that ID.";
            return $out;
        }
        $cluster = mysqli_fetch_array($r);
        $cluster_vip_hostname = $cluster["vip_hostname"];
        $cluster_name         = $cluster["name"];
        $cluster_domain       = $cluster["domain"];

        if($safe_vip_hostname == ""){
            $api_hostname = $cluster_name . "-api." . $cluster_domain;
        }else{
            $api_hostname = $cluster_vip_hostname;
        }

        $api_cert_path = "/var/lib/oci/ssl/slave-nodes/" . $api_hostname;
        if(!file_exists($api_cert_path)){
            $cmd = "sudo /usr/bin/oci-gen-slave-node-cert $api_hostname";
            $output = array();
            $return_var = 0;
            exec($cmd, $output, $return_var);
        }

        if($safe_swift_proxy_hostname != ""){
            $proxy_cert_path = "/var/lib/oci/ssl/slave-nodes/" . $safe_swift_proxy_hostname;
            if(!file_exists($proxy_cert_path)){
                $cmd = "sudo /usr/bin/oci-gen-slave-node-cert $safe_swift_proxy_hostname";
                $output = array();
                $return_var = 0;
                exec($cmd, $output, $return_var);
            }
        }

        $q = "UPDATE clusters SET swift_part_power='$safe_swift_part_power', swift_replicas='$safe_swift_replicas', swift_min_part_hours='$safe_min_part_hours', vip_hostname='$safe_vip_hostname', swift_proxy_hostname='$safe_swift_proxy_hostname', swift_encryption_key_id='$safe_swift_encryption_key_id', haproxy_custom_url='$safe_haproxy_custom_url', statsd_hostname='$safe_statsd_hostname' WHERE id='$safe_cluster_id'";
        $r = mysqli_query($con, $q);
        break;
    case "add_swiftregion":
        // http://bdbdev-ogiz.infomaniak.ch/oci/?name=public&h-open-tab=Swift-regions&action=add_swiftregion
        $safe_name = safe_fqdn("name");
        if($safe_name === FALSE){
            $out .= "Swift zone name is wrong.";
            return $out;
        }
        $q = "INSERT INTO swiftregions (name) VALUES ('$safe_name')";
        $r = mysqli_query($con, $q);
        break;
    case "delete_swiftregion":
        // http://bdbdev-ogiz.infomaniak.ch/oci/?h-open-tab=Swift-regions&action=delete_swiftregion&id=2
        $safe_swiftregion_id = safe_int("id");
        if($safe_swiftregion_id === FALSE){
            $out .= "Swift zone id is wrong.";
            return $out;
        }
        $q = "DELETE FROM swiftregions WHERE id='$safe_swiftregion_id'";
        $r = mysqli_query($con, $q);	
        break;
    default:
        break;
    }
}

?>
