<?php

require_once("inc/read_conf.php");
require_once("inc/db.php");
require_once("inc/ssh.php");
require_once("inc/idna_convert.class.php");
require_once("inc/validate_param.php");
require_once("inc/slave_actions.php");
require_once("inc/auth.php");

$conf = readmyconf();
$con = connectme($conf);

function get_cluster_password($con, $conf, $cluster_id, $service_type, $password_type){
    $json["status"] = "success";
    $json["message"] = "Successfuly queried API.";
    $q = "SELECT pass,passtxt1,passtxt2 FROM passwords WHERE cluster='$cluster_id' AND service='$service_type' AND passtype='$password_type'";
    $r = mysqli_query($con, $q);
    if($r === FALSE){
        $json["status"] = "error";
        $json["message"] = mysqli_error($con);
        return $json;
    }
    $n = mysqli_num_rows($r);
    if($n != 1){
        # If password doesn't exist, generate it!
        insert_cluster_pass($con, $conf, $cluster_id, $service_type, $password_type);
        $q = "SELECT pass,passtxt1,passtxt2 FROM passwords WHERE cluster='$cluster_id' AND service='$service_type' AND passtype='$password_type'";
        $r = mysqli_query($con, $q);
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Cannot find password $password_type for service $service_type.";
            return $json;
        }
    }
    $pass_a = mysqli_fetch_array($r);
    if($service_type == "nova" && $password_type == "ssh"){
        $json["data"]["ssh_pub"] = $pass_a["passtxt1"];
        $json["data"]["ssh_priv"] = $pass_a["passtxt2"];
    }else{
        $json["data"] = $pass_a["pass"];
    }
    return $json;
}

function enc_get_mon_nodes($con,$conf){
    $q = "SELECT * FROM machines WHERE role='cephmon'";
    $r = mysqli_query($con, $q);
    if($r === FALSE){
        $json["status"] = "error";
        $json["message"] = mysqli_error($con);
        return $json;
    }
    $n = mysqli_num_rows($r);
    if($n < 1){
        // If there's no specific cephmon nodes, then controllers will assume that role
        $q = "SELECT * FROM machines WHERE role='controller'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n < 1){
            $json["status"] = "error";
            $json["message"] = "No Ceph MON or controllers in database.";
            return $json;
        }
        $role_to_select = "controller";
    }else{
        $role_to_select = "cephmon";
    }

    $q = "SELECT machines.hostname AS hostname, INET_NTOA(ips.ip) AS ipaddr FROM machines, ips, networks WHERE machines.role='$role_to_select' AND machines.cluster='1' AND machines.id=ips.machine AND ips.network=networks.id AND networks.is_public='no' AND networks.role!='vm-net' AND networks.role!='ovs-bridge' AND networks.role!='ceph-cluster'";

    $r = mysqli_query($con, $q);
    if($r === FALSE){
        $json["status"] = "error";
        $json["message"] = mysqli_error($con);
        return $json;
    }
    $n = mysqli_num_rows($r);

    $osd_members = "";
    $osd_ips = "";
    for($i=0;$i<$n;$i++){
        $machine = mysqli_fetch_array($r);
        $machine_hostname = $machine["hostname"];
        $machine_ipaddr   = $machine["ipaddr"];
        if($osd_members == ""){
            $osd_members = $machine_hostname;
            $osd_ips     = $machine_ipaddr;
        }else{
            $osd_members = $osd_members . "," . $machine_hostname;
            $osd_ips     = $osd_ips . "," . $machine_ipaddr;
        }
    }
    $json["status"] = "success";
    $json["message"] = "Successfuly queried API.";
    $json["data"]["osd_members"] = $osd_members;
    $json["data"]["osd_ips"] = $osd_ips;
    return $json;
}

function api_actions($con,$conf){
    $json["status"] = "success";
    $json["message"] = "Successfuly queried API.";
    if(!isset($_REQUEST["action"])){
        $json["status"] = "error";
        $json["message"] = "Error: no action specified.";
        return $json;
    }

    // If not on a trusted network, then we need authentication
    if(oci_ip_check($con, $conf) === FALSE){
        if((!isset($_REQUEST["oci_login"])) || $_REQUEST["oci_login"] == "" || (!isset($_REQUEST["oci_pass"])) || $_REQUEST["oci_pass"] == ""){
            $json["status"] = "error";
            $json["message"] = "Error: please set oci_login and oci_pass to authenticate.";
            return $json;
        }
        $ret = oci_auth_user($con, $conf, $_REQUEST["oci_login"], $_REQUEST["oci_pass"]);
        if($ret["status"] != "success"){
            return $ret;
        }
    }

    switch($_REQUEST["action"]){
    case "enc":
        // This returns the external node classifyer yaml file, to be used by puppet.

        // Get our machine object in db, using hostname to search, as this is
        // what the enc script is given as parameter
        $safe_hostname = safe_fqdn("hostname");
        $q = "SELECT * FROM machines WHERE hostname='$safe_hostname'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "No such hostname in database.";
            return $json;
        }
        $machine = mysqli_fetch_array($r);
        $machine_id   = $machine["id"];
        $machine_role = $machine["role"];
        $cluster_id   = $machine["cluster"];
        $machine_location = $machine["location_id"];

        $machine_networks = slave_fetch_networks($con, $conf, $machine_id);
        if(sizeof($machine_networks["networks"]) == 0){
            $json["status"] = "error";
            $json["message"] = "Machine has no network.";
            return $json;
        }

        // Fetch matching cluster object
        $q = "SELECT * FROM clusters WHERE id='$cluster_id'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Cluster not found in database.";
            return $json;
        }
        $cluster = mysqli_fetch_array($r);
        $cluster_domain = $cluster["domain"];
        $cluster_name = $cluster["name"];
        $cluster_statsd_hostname = $cluster["statsd_hostname"];
        $cluster_time_server_host = $cluster["time_server_host"];
        $amp_secgroup_list        = $cluster["amp_secgroup_list"];
        $amp_boot_network_list    = $cluster["amp_boot_network_list"];
        $disable_notifications    = $cluster["disable_notifications"];
        if($disable_notifications == "yes"){
            $disable_notifications = "true";
        }else{
            $disable_notifications = "false";
        }
        $enable_monitoring_graphs = $cluster["enable_monitoring_graphs"];
        if($enable_monitoring_graphs == "yes"){
            $enable_monitoring_graphs = "true";
        }else{
            $enable_monitoring_graphs = "false";
        }
        $monitoring_graphite_host = $cluster["monitoring_graphite_host"];
        $monitoring_graphite_port = $cluster["monitoring_graphite_port"];

        $machine_networks = slave_fetch_network_config($con, $conf, $machine_id);
        if(sizeof($machine_networks["networks"]) == 0){
            $json["status"]  = "error";
            $json["message"] = "No network configured for this machine.";
            return $out;
        }

        // We just fetch the first network that's not public and vm-trafic for now, maybe we'll need to be
        // more selective later, let's see...
        for($i=0;$i<sizeof($machine_networks["networks"]);$i++){
            if($machine_networks["networks"][$i]["is_public"] == 'no' && $machine_networks["networks"][$i]["role"] != "vm-net" && $machine_networks["networks"][$i]["role"] != "ovs-bridge" && $machine_networks["networks"][$i]["role"] != "ceph-cluster"){
                $network_id = $machine_networks["networks"][$i]["id"];
                continue;
            }
        }

        // Get this machine's IP and hostname
        $q = "SELECT machines.hostname AS hostname, INET_NTOA(ips.ip) AS ipaddr FROM machines, ips WHERE machines.id='$machine_id' AND machines.cluster='$cluster_id' AND machines.id=ips.machine  AND ips.network='$network_id'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "No such hostname in database when doing $q.";
            return $json;
        }
        $machine_netconf = mysqli_fetch_array($r);
        $machine_hostname = $machine_netconf["hostname"];
        $machine_ip = $machine_netconf["ipaddr"];

        // Get the API's VIP ip address
        $q = "SELECT * FROM networks WHERE cluster='$cluster_id' AND is_public='yes'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = "MySQL error when doing $q: " .mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Cannot find public network to find VIP when doing $q.";
            return $json;
        }

        $public_network = mysqli_fetch_array($r);
        $public_network_id = $public_network["id"];

        if($public_network["cidr"] == "32"){
            $vip_ipaddr  = $public_network["ip"];
            $vip_netmask = "32";
        }else{
            $q = "SELECT INET_NTOA(ips.ip) AS ipaddr FROM ips WHERE network='$public_network_id' AND usefor='vip' AND vip_usage='api'";
            $r = mysqli_query($con, $q);
            if($r === FALSE){
                $json["status"] = "error";
                $json["message"] = mysqli_error($con);
                return $json;
            }
            $n = mysqli_num_rows($r);
            if($n != 1){
                $json["status"] = "error";
                $json["message"] = "No such hostname in database when doing $q.";
                return $json;
            }
            $public_ip = mysqli_fetch_array($r);
            $vip_ipaddr  = $public_ip["ipaddr"];
            $vip_netmask = $public_network["cidr"];
        }
        if($cluster["vip_hostname"] == ""){
            $vip_hostname = $cluster["name"] . "-api." . $cluster["domain"];
        }else{
            $vip_hostname = $cluster["vip_hostname"];
        }

        $q = "SELECT * FROM networks WHERE id='$network_id'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = "MySQL error when doing $q: " .mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Cannot find network in database when doing $q.";
            return $json;
        }
        $network = mysqli_fetch_array($r);
        $network_ip = $network["ip"];
        $network_cidr = $network["cidr"];

        // Fetch all controllers
        
        $enc_amhn = "      all_masters:\n";
        $enc_amip = "      all_masters_ip:\n";
        $enc_nids = "      all_masters_ids:\n";
        $q = "SELECT machines.hostname AS hostname, machines.id AS nodeid, INET_NTOA(ips.ip) AS ipaddr FROM machines, ips, networks WHERE machines.role='controller' AND machines.cluster='$cluster_id' AND machines.id=ips.machine AND ips.network=networks.id AND networks.is_public='no' AND networks.role!='vm-net' AND networks.role!='ovs-bridge' AND networks.role!='ceph-cluster'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        for($i=0;$i<$n;$i++){
            $one_master = mysqli_fetch_array($r);
            $one_master_hostname = $one_master["hostname"];
            $one_master_ipaddr = $one_master["ipaddr"];
            $one_node_id = $one_master["nodeid"];
            $enc_amhn .= "         - $one_master_hostname\n";
            $enc_amip .= "         - $one_master_ipaddr\n";
            $enc_nids .= "         - $one_node_id\n";
        }

        // Fetch all swift stores
        $q = "SELECT machines.hostname AS hostname, machines.id AS nodeid, INET_NTOA(ips.ip) AS ipaddr FROM machines, ips WHERE machines.role='swiftstore' AND machines.cluster='$cluster_id' AND machines.id=ips.machine";
//        $q = "SELECT machines.hostname AS hostname, machines.id AS nodeid, INET_NTOA(ips.ip) AS ipaddr FROM machines, ips WHERE machines.role='swiftstore' AND machines.cluster='$cluster_id' AND machines.id=ips.machine AND ips.network='$network_id'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        for($i=0;$i<$n;$i++){
            $one_swiftstore = mysqli_fetch_array($r);
            $one_swiftstore_hostname = $one_swiftstore["hostname"];
            $one_swiftstore_ipaddr = $one_swiftstore["ipaddr"];
            $enc_allswiftstore_hostanme .= "         - $one_swiftstore_hostname\n";
            $enc_allswiftstore_ip .= "         - $one_swiftstore_ipaddr\n";
        }

        // Fetch all swift proxies
        $q = "SELECT machines.hostname AS hostname, machines.id AS nodeid, INET_NTOA(ips.ip) AS ipaddr FROM machines, ips WHERE machines.role='swiftproxy' AND machines.cluster='$cluster_id' AND machines.id=ips.machine";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        for($i=0;$i<$n;$i++){
            $one_swiftproxy = mysqli_fetch_array($r);
            $one_swiftproxy_hostname = $one_swiftproxy["hostname"];
            $one_swiftproxy_ipaddr = $one_swiftproxy["ipaddr"];
            $one_node_id = $one_swiftproxy["nodeid"];
            $enc_allswiftproxies_hostanme .= "         - $one_swiftproxy_hostname\n";
            $enc_allswiftproxies_ip .= "         - $one_swiftproxy_ipaddr\n";
        }

        // Get the number of compute nodes
        $q = "SELECT * FROM machines WHERE cluster='$cluster_id' AND role='compute'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $num_compute_nodes = mysqli_num_rows($r);

        // Get the number of network nodes
        $q = "SELECT * FROM machines WHERE cluster='$cluster_id' AND role='network'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $num_network_nodes = mysqli_num_rows($r);

        // Get the number of swiftstore nodes
        $q = "SELECT * FROM machines WHERE cluster='$cluster_id' AND role='swiftstore'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $num_swiftstore_nodes = mysqli_num_rows($r);

        // Get the number of volume nodes
        $q = "SELECT * FROM machines WHERE cluster='$cluster_id' AND role='volume'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $num_volume_nodes = mysqli_num_rows($r);

        // Get the number of cephosd nodes
        $q = "SELECT * FROM machines WHERE cluster='$cluster_id' AND role='cephosd'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $num_cephosd_nodes = mysqli_num_rows($r);

        // Get the number of cephmon nodes
        $q = "SELECT * FROM machines WHERE cluster='$cluster_id' AND role='cephmon'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $num_cephmon_nodes = mysqli_num_rows($r);

        $enc_file = "---\n";
        $enc_file .= "classes:\n";

        ##############################################################
        ### Load hiera from /etc/openstack-cluster-installer/hiera ###
        ##############################################################
        $hiera_dir  = "/etc/openstack-cluster-installer/hiera";
        $hiera_role = $hiera_dir . "/roles/" . $machine_role . ".yaml";
        if(file_exists($hiera_role) === TRUE){
            $enc_file .= file_get_contents($hiera_role);
        }
        $hiera_node = $hiera_dir . "/nodes/" . $safe_hostname . ".yaml";
        if(file_exists($hiera_node) === TRUE){
            $enc_file .= file_get_contents($hiera_node);
        }
        $hiera_all  = $hiera_dir . "/all.yaml";
        if(file_exists($hiera_all) === TRUE){
            $enc_file .= file_get_contents($hiera_all);
        }
        $hiera_cluster_role = $hiera_dir . "/clusters/" . $cluster["name"] . "/roles/" . $machine_role . ".yaml";
        if(file_exists($hiera_cluster_role) === TRUE){
            $enc_file .= file_get_contents($hiera_cluster_role);
        }
        $hiera_cluster_node = $hiera_dir . "/clusters/" . $cluster["name"] . "/nodes/" . $safe_hostname . ".yaml";
        if(file_exists($hiera_cluster_node) === TRUE){
            $enc_file .= file_get_contents($hiera_cluster_node);
        }
        $hiera_cluster_all = $hiera_dir . "/clusters/" . $cluster["name"] . "/all.yaml";
        if(file_exists($hiera_cluster_all) === TRUE){
            $enc_file .= file_get_contents($hiera_cluster_all);
        }

        // Get the first_master controler node
        $first_master_machine_id = $cluster["first_master_machine_id"];
        $q = "SELECT machines.hostname AS hostname, INET_NTOA(ips.ip) AS ipaddr FROM machines, ips, networks WHERE machines.id='$first_master_machine_id' AND machines.cluster='$cluster_id' AND machines.id=ips.machine AND ips.network=networks.id AND networks.is_public='no' AND networks.role!='vm-net' AND networks.role!='ovs-bridge' AND networks.role!='ceph-cluster'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Cannot find a single IP (found more or less than one IP) for this hostname's management network when doing: $q line ".__LINE__." file ".__FILE__;
            return $json;
        }
        $first_master = mysqli_fetch_array($r);
        $first_master_hostname = $first_master["hostname"];
        $first_master_ipaddr   = $first_master["ipaddr"];

        # Calculate the bridge list. If there's no network with bridge for this cluster, then we have a single br-ex.
        # Otherwise, we have a list of bridge from db.
        $q = "SELECT bridgename FROM networks WHERE cluster='$cluster_id' AND role='ovs-bridge'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $bridge_list = array();
        $n = mysqli_num_rows($r);
        if($n > 0){
            for($i=0;$i<$n;$i++){
                $one_network = mysqli_fetch_array($r);
                $bridge_list[] = $one_network["bridgename"];
            }
        }else{
            $bridge_list[] = "br-ex";
        }
        $enc_bridge_list = "      bridge_mapping_list:\n";
        $enc_external_netlist = "      external_network_list:\n";
        for($i=0;$i<sizeof($bridge_list);$i++){
            if($i == 0){
                $enc_bridge_list .= "         - external:" . $bridge_list[$i] ."\n";
                $enc_external_netlist .= "         - external\n";
            }else{
                $enc_bridge_list .= "         - external$i:" . $bridge_list[$i] ."\n";
                $enc_external_netlist .= "         - external$i\n";
            }
        }
        $enc_bridge_list .= $enc_external_netlist;

        $openstack_release = $conf["releasenames"]["openstack_release"];

        # See if we have machines with role SQL, if yes, find who's first_sql_machine_id.
        $q = "SELECT * FROM machines WHERE cluster='$cluster_id' AND role='sql'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n_sql_machines = mysqli_num_rows($r);

        $q = "SELECT * FROM machines WHERE cluster='$cluster_id' AND role='controller'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n_controller_machines = mysqli_num_rows($r);

        if($n_sql_machines > 0 || $n_controller_machines > 0){
            if($n_sql_machines > 0){
                $first_sql_machine_id = $cluster["first_sql_machine_id"];
                $vip_usage = "sql";
            }else{
                $first_sql_machine_id = $cluster["first_master_machine_id"];
                $vip_usage = "api";
            }
            if($first_sql_machine_id == $machine_id){
                $is_first_sql = "true";
            }else{
                $is_first_sql = "false";
            }

            // Get the first_sql sql node
            $q = "SELECT machines.hostname AS hostname, INET_NTOA(ips.ip) AS ipaddr FROM machines, ips, networks WHERE machines.id='$first_sql_machine_id' AND machines.cluster='$cluster_id' AND machines.id=ips.machine AND ips.network=networks.id AND networks.is_public='no' AND networks.role!='vm-net' AND networks.role!='ovs-bridge' AND networks.role!='ceph-cluster'";
            $r = mysqli_query($con, $q);
            if($r === FALSE){
                $json["status"] = "error";
                $json["message"] = mysqli_error($con);
                return $json;
            }
            $n = mysqli_num_rows($r);
            if($n != 1){
                $json["status"] = "error";
                $json["message"] = "Cannot find a single IP (found more or less than one IP) for this hostname's management network when doing: $q line ".__LINE__." file ".__FILE__;
                return $json;
            }
            $first_sql = mysqli_fetch_array($r);
            $first_sql_hostname = $first_sql["hostname"];
            $first_sql_ipaddr   = $first_sql["ipaddr"];

            $enc_ashn = "      all_sql:\n";
            $enc_asip = "      all_sql_ip:\n";
            $enc_snids = "      all_sql_ids:\n";

            # If there's SQL machines, that's what we search for,
            # otherwise, we do the query on controllers.
            if($n_sql_machines > 0){
                $qrole = 'sql';
            }else{
                $qrole = 'controller';
            }
            $q = "SELECT machines.hostname AS hostname, machines.id AS nodeid, INET_NTOA(ips.ip) AS ipaddr FROM machines, ips, networks WHERE machines.role='$qrole' AND machines.id=ips.machine AND ips.network=networks.id AND networks.is_public='no' AND networks.role!='vm-net' AND networks.role!='ovs-bridge' AND networks.role!='ceph-cluster'";
            $r = mysqli_query($con, $q);
            if($r === FALSE){
                $json["status"] = "error";
                $json["message"] = mysqli_error($con);
                return $json;
            }
            $n = mysqli_num_rows($r);
            for($i=0;$i<$n;$i++){
                $one_sql = mysqli_fetch_array($r);
                $one_sql_hostname = $one_sql["hostname"];
                $one_sql_ipaddr = $one_sql["ipaddr"];
                $one_sql_id = $one_sql["nodeid"];
                $enc_ashn .= "         - $one_sql_hostname\n";
                $enc_asip .= "         - $one_sql_ipaddr\n";
                $enc_snids .= "         - $one_sql_id\n";
            }

            $enc_non_master_sql = "      non_master_sql:\n";
            $enc_non_master_sql_ip = "      non_master_sql_ip:\n";
            $q = "SELECT machines.hostname AS hostname, machines.id AS nodeid, INET_NTOA(ips.ip) AS ipaddr FROM machines, ips, networks WHERE machines.role='$qrole' AND machines.id=ips.machine AND ips.network=networks.id AND networks.is_public='no' AND networks.role!='vm-net' AND networks.role!='ovs-bridge' AND networks.role!='ceph-cluster' AND machines.hostname!='$first_sql_hostname'";
            $r = mysqli_query($con, $q);
            if($r === FALSE){
                $json["status"] = "error";
                $json["message"] = mysqli_error($con);
                return $json;
            }
            $n = mysqli_num_rows($r);
            for($i=0;$i<$n;$i++){
                $one_sql = mysqli_fetch_array($r);
                $one_sql_hostname = $one_sql["hostname"];
                $one_sql_ipaddr = $one_sql["ipaddr"];
                $one_sql_id = $one_sql["nodeid"];
                $enc_non_master_sql .= "         - $one_sql_hostname\n";
                $enc_non_master_sql_ip .= "         - $one_sql_ipaddr\n";
            }

            $q = "SELECT INET_NTOA(ips.ip) AS ipaddr, networks.iface1 AS iface1, networks.iface2 AS iface2, networks.vlan AS vlan, networks.cidr AS cidr FROM ips,networks WHERE ips.usefor='vip' AND ips.vip_usage='$vip_usage' AND networks.cluster='$cluster_id' AND ips.network=networks.id";
            $r = mysqli_query($con, $q);
            if($r === FALSE){
                $json["status"] = "error";
                $json["message"] = mysqli_error($con);
                return $json;
            }
            $n = mysqli_num_rows($r);
            if($n != 1){
                $json["status"] = "error";
                $json["message"] = "Cannot find a single IP (found more or less than one IP) for this SQL's VIP when doing: $q line ".__LINE__." file ".__FILE__;
                return $json;
            }
            $vip_sql = mysqli_fetch_array($r);
            $vip_sql_ip = $vip_sql["ipaddr"];
            $vip_sql_iface1 = $vip_sql["iface1"];
            $vip_sql_iface2 = $vip_sql["iface2"];
            $vip_sql_vlan   = $vip_sql["vlan"];
            $vip_sql_netmask= $vip_sql["cidr"];
            if(is_null($vip_sql_vlan) && $vip_sql_vlan != ""){
                $vip_sql_iface = "vlan" . $vip_sql_vlan;
            }else{
                if($vip_sql_iface2 != "none"){
                    $vip_sql_iface = "bond0";
                }else{
                    $vip_sql_iface = $vip_sql_iface1;
                }
            }
        }elseif($n_controller_machines > 0){
            $first_sql_hostname = $first_master_hostname;
            $first_sql_ipaddr   = $first_master_ipaddr;
        }

        ###############################
        ### Role specific ENC output ##
        ###############################
        switch($machine["role"]){
        case "controller":
            if($machine_id == $first_master_machine_id){
                $is_first_master = "true";
            }else{
                $is_first_master = "false";
            }

            // Start writing oci::controller class parameters
            $enc_file .= "   oci::controller:\n";
            $enc_file .= "      openstack_release: $openstack_release\n";
            $enc_file .= "      cluster_name: $cluster_name\n";
            $enc_file .= "      machine_hostname: $machine_hostname\n";
            $enc_file .= "      machine_ip: $machine_ip\n";
            $enc_file .= "      etc_hosts: ".base64_etc_hosts($con, $conf, $machine_id)."\n";
            $enc_file .= "      time_server_host: $cluster_time_server_host\n";
            $enc_file .= $enc_bridge_list;
            if($num_compute_nodes > 0 && $num_network_nodes == 0){
                $enc_file .= "      machine_iface: br-ex\n";
            }else{
                $enc_file .= "      machine_iface: eth0\n";
            }

            $enc_file .= "      first_sql: $first_sql_hostname\n";
            $enc_file .= "      first_sql_ip: $first_sql_ipaddr\n";
            $enc_file .= "      is_first_sql: $is_first_sql\n";
            $enc_file .= "      sql_vip_ip: $vip_sql_ip\n";
            $enc_file .= "      sql_vip_netmask: $vip_sql_netmask\n";
            $enc_file .= "      sql_vip_iface: $vip_sql_iface\n";

            $enc_file .= $enc_ashn;
            $enc_file .= $enc_asip;
            $enc_file .= $enc_snids;
            $enc_file .= $enc_non_master_sql;
            $enc_file .= $enc_non_master_sql_ip;

            $enc_file .= "      amp_secgroup_list: $amp_secgroup_list\n";
            $enc_file .= "      amp_boot_network_list: $amp_boot_network_list\n";

            if($num_cephosd_nodes > 0){
                $enc_file .= "      cinder_backup_backend: ceph\n";
            }elseif($num_swiftstore_nodes > 0){
                $enc_file .= "      cinder_backup_backend: swift\n";
            }else{
                $enc_file .= "      cinder_backup_backend: none\n";
            }

            // Get all IPs from compute and volume nodes, that's needed to allow
            // SQL queries from them, as cinder-volume may run there.
            $q = "SELECT machines.hostname AS hostname, INET_NTOA(ips.ip) AS ipaddr FROM machines, ips, networks WHERE machines.role='compute' AND machines.cluster='$cluster_id' AND machines.id=ips.machine AND ips.network=networks.id AND networks.is_public='no' AND networks.role!='vm-net' AND networks.role!='ovs-bridge' AND networks.role!='ceph-cluster'";
            $r = mysqli_query($con, $q);
            if($r === FALSE){
                $json["status"] = "error";
                $json["message"] = mysqli_error($con);
                return $json;
            }
            $n = mysqli_num_rows($r);
            $enc_comp_list = "      compute_nodes:\n";
            $enc_compip_list = "      compute_nodes_ip:\n";
            for($i=0;$i<$n;$i++){
                $one_compute = mysqli_fetch_array($r);
                $one_compute_hostname = $one_compute["hostname"];
                $one_compute_ip = $one_compute["ipaddr"];
                $enc_comp_list .= "         - $one_compute_hostname\n";
                $enc_compip_list .= "         - $one_compute_ip\n";
            }
            $enc_file .= $enc_comp_list;
            $enc_file .= $enc_compip_list;

            // Same with volume nodes
            $q = "SELECT machines.hostname AS hostname, INET_NTOA(ips.ip) AS ipaddr FROM machines, ips, networks WHERE machines.role='volume' AND machines.cluster='$cluster_id' AND machines.id=ips.machine AND ips.network=networks.id AND networks.is_public='no' AND networks.role!='vm-net' AND networks.role!='ovs-bridge' AND networks.role!='ceph-cluster'";
            $r = mysqli_query($con, $q);
            if($r === FALSE){
                $json["status"] = "error";
                $json["message"] = mysqli_error($con);
                return $json;
            }
            $n = mysqli_num_rows($r);
            $enc_volu_list = "      volume_nodes:\n";
            $enc_voluip_list = "      volume_nodes_ip:\n";
            for($i=0;$i<$n;$i++){
                $one_volume = mysqli_fetch_array($r);
                $enc_volu_hostname = $one_volume["hostname"];
                $enc_volu_ip = $one_volume["ipaddr"];
                $enc_volu_list .= "         - $enc_volu_hostname\n";
                $enc_voluip_list .= "         - $enc_volu_ip\n";
            }
            $enc_file .= $enc_volu_list;
            $enc_file .= $enc_voluip_list;

            // Get the IP for VM trafic.
            $q = "SELECT machines.hostname AS hostname, INET_NTOA(ips.ip) AS ipaddr, networks.mtu AS mtu FROM machines, ips, networks WHERE machines.id='$machine_id' AND machines.cluster='$cluster_id' AND machines.id=ips.machine AND ips.network=networks.id AND networks.is_public='no' AND networks.role='vm-net'";
            $r = mysqli_query($con, $q);
            if($r === FALSE){
                $json["status"] = "error";
                $json["message"] = mysqli_error($con);
                return $json;
            }
            $n = mysqli_num_rows($r);
            if($n == 1){
                $vmnet_ip_array = mysqli_fetch_array($r);
                $vmnet_ip = $vmnet_ip_array["ipaddr"];
                $vmnet_mtu = $vmnet_ip_array["mtu"];
                $enc_file .= "      vmnet_ip: $vmnet_ip\n";
                $enc_file .= "      vmnet_mtu: $vmnet_mtu\n";
            // If we don't find a VMNet IP, then let's use the management network IP instead.
            }else{
                $enc_file .= "      vmnet_ip: $machine_ip\n";
                $enc_file .= "      vmnet_mtu: 0\n";
            }

            $enc_file .= "      is_first_master: $is_first_master\n";
            $enc_file .= "      first_master: $first_master_hostname\n";
            $enc_file .= "      first_master_ip: $first_master_ipaddr\n";
            $enc_file .= "      vip_hostname: $vip_hostname\n";
            $enc_file .= "      vip_ipaddr: $vip_ipaddr\n";
            $enc_file .= "      vip_netmask: $vip_netmask\n";
            if($cluster["swift_proxy_hostname"] == ""){
                $enc_file .= "      swiftproxy_hostname: none\n";
            }else{
                $enc_file .= "      swiftproxy_hostname: " . $cluster["swift_proxy_hostname"] ."\n";
            }
            $enc_file .= "      haproxy_custom_url: " . $cluster["haproxy_custom_url"] . "\n";
            $enc_file .= "      network_ipaddr: $network_ip\n";
            $enc_file .= "      network_cidr: $network_cidr\n";
            $enc_file .= "      other_masters:\n";

            $enc_omip  = "      other_masters_ip:\n";
            // Fetch all other controllers (ie: all but this machine we're setting-up)
            $q = "SELECT machines.hostname AS hostname, INET_NTOA(ips.ip) AS ipaddr FROM machines, ips, networks WHERE machines.role='controller' AND machines.cluster='$cluster_id' AND machines.id != '$machine_id' AND machines.id=ips.machine AND ips.network=networks.id AND networks.is_public='no' AND networks.role!='vm-net' AND networks.role!='ovs-bridge' AND networks.role!='ceph-cluster'";
            $r = mysqli_query($con, $q);
            if($r === FALSE){
                $json["status"] = "error";
                $json["message"] = mysqli_error($con);
                return $json;
            }
            $n = mysqli_num_rows($r);
            for($i=0;$i<$n;$i++){
                $one_other_master = mysqli_fetch_array($r);
                $one_other_master_hostname = $one_other_master["hostname"];
                $one_other_master_ipaddr = $one_other_master["ipaddr"];
                $enc_file .= "         - $one_other_master_hostname\n";
                $enc_omip .= "         - $one_other_master_ipaddr\n";
            }
            $enc_file .= $enc_omip;

            $enc_file .= $enc_amhn;
            $enc_file .= $enc_amip;
            $enc_file .= $enc_nids;

            $enc_file .= "      all_swiftproxy:\n";
            $enc_file .= $enc_allswiftproxies_hostanme;
            $enc_file .= "      all_swiftproxy_ip:\n";
            $enc_file .= $enc_allswiftproxies_ip;

            if($n_sql_machines > 0){
                $enc_file .= "      has_subrole_db: false\n";
            }

            if($num_compute_nodes > 0){
                $enc_file .= "      has_subrole_glance: true\n";
                $enc_file .= "      has_subrole_nova: true\n";
                $enc_file .= "      has_subrole_neutron: true\n";
                $enc_file .= "      has_subrole_aodh: true\n";
                $enc_file .= "      has_subrole_octavia: true\n";
                $enc_file .= "      has_subrole_magnum: true\n";
            }

            if($num_cephmon_nodes > 0){
                $enc_file .= "      cluster_has_mons: true\n";
            }else{
                $enc_file .= "      cluster_has_mons: false\n";
            }

            if($num_cephosd_nodes > 0 || $num_volume_nodes > 0){
                $enc_file .= "      has_subrole_cinder: true\n";
                if($num_volume_nodes > 0){
                    $enc_file .= "      cluster_has_cinder_volumes: true\n";
                }
                $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'libvirtuuid');
                if($json["status"] != "success"){ return $json; }
                $enc_file .= "      ceph_libvirtuuid: " . $json["data"] . "\n";
            }
            if($num_cephosd_nodes > 0){
                $enc_file .= "      cluster_has_osds: true\n";

                $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'fsid');
                if($json["status"] != "success"){ return $json; }
                $enc_file .= "      ceph_fsid: " . $json["data"] . "\n";

                $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'bootstraposdkey');
                if($json["status"] != "success"){ return $json; }
                $enc_file .= "      ceph_bootstrap_osd_key: " . $json["data"] . "\n";

                $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'adminkey');
                if($json["status"] != "success"){ return $json; }
                $enc_file .= "      ceph_admin_key: " . $json["data"] . "\n";

                $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'openstackkey');
                if($json["status"] != "success"){ return $json; }
                $enc_file .= "      ceph_openstack_key: " . $json["data"] . "\n";

                $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'monkey');
                if($json["status"] != "success"){ return $json; }
                $enc_file .= "      ceph_mon_key: " . $json["data"] . "\n";

                $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'mgrkey');
                if($json["status"] != "success"){ return $json; }
                $enc_file .= "      ceph_mgr_key: " . $json["data"] . "\n";

                $ret = enc_get_mon_nodes($con,$conf);
                if($ret["status"] != "success"){
                    return $ret;
                }
                $enc_file .= "      ceph_mon_initial_members: " . $ret["data"]["osd_members"] . "\n";
                $enc_file .= "      ceph_mon_host: " . $ret["data"]["osd_ips"] . "\n";
                $enc_file .= "      has_subrole_gnocchi: true\n";
                $enc_file .= "      has_subrole_ceilometer: true\n";
                $enc_file .= "      has_subrole_panko: true\n";
                $enc_file .= "      has_subrole_cloudkitty: true\n";
            }else{
                $enc_file .= "      cluster_has_osds: false\n";
            }

            if($num_cephosd_nodes > 0){
                $glance_backend = 'ceph';
            }elseif($num_swiftstore_nodes > 0){
                $glance_backend = 'swift';

                $json = get_cluster_password($con, $conf, $cluster_id, 'glance', 'onswift');
                if($json["status"] != "success"){ return $json; }
                $enc_file .= "      pass_glance_onswift: " . $json["data"] . "\n";
            }elseif($num_volume_nodes > 0){
                $glance_backend = 'cinder';
            }else{
                $glance_backend = 'file';
            }
            $enc_file .= "      glance_backend: " . $glance_backend . "\n";

            // Send all passwords
            $json = get_cluster_password($con, $conf, $cluster_id, 'mysql', 'rootuser');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_mysql_rootuser: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'mysql', 'backup');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_mysql_backup: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'rabbitmq', 'cookie');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_rabbitmq_cookie: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'keystone', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_keystone_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'keystone', 'messaging');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_keystone_messaging: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'keystone', 'adminuser');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_keystone_adminuser: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'keystone', 'credential1');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_keystone_credkey1: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'keystone', 'credential2');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_keystone_credkey2: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'keystone', 'fernetkey1');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_keystone_fernkey1: " . base64_encode(substr($json["data"],0,32)) . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'keystone', 'fernetkey2');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_keystone_fernkey2: " . base64_encode(substr($json["data"],0,32)) . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'nova', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_nova_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'nova', 'apidb');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_nova_apidb: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'nova', 'messaging');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_nova_messaging: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'nova', 'authtoken');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_nova_authtoken: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'novaneutron', 'shared_secret');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_metadata_proxy_shared_secret: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'placement', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_placement_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'placement', 'authtoken');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_placement_authtoken: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'glance', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_glance_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'glance', 'messaging');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_glance_messaging: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'glance', 'authtoken');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_glance_authtoken: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'neutron', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_neutron_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'neutron', 'messaging');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_neutron_messaging: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'neutron', 'authtoken');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_neutron_authtoken: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'cinder', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_cinder_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'cinder', 'messaging');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_cinder_messaging: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'cinder', 'authtoken');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_cinder_authtoken: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'heat', 'encryptkey');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_heat_encryptkey: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'heat', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_heat_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'heat', 'messaging');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_heat_messaging: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'heat', 'authtoken');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_heat_authtoken: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'heat', 'keystone_domain');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_heat_keystone_domain: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'swift', 'authtoken');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_swift_authtoken: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'swift', 'encryption');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_swift_encryption: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'horizon', 'secretkey');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_horizon_secretkey: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'barbican', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_barbican_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'barbican', 'messaging');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_barbican_messaging: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'barbican', 'authtoken');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_barbican_authtoken: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'openstackkey');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      ceph_openstack_key: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'gnocchi', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_gnocchi_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'gnocchi', 'messaging');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_gnocchi_messaging: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'gnocchi', 'authtoken');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_gnocchi_authtoken: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'panko', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_panko_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'panko', 'messaging');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_panko_messaging: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'panko', 'authtoken');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_panko_authtoken: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'ceilometer', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_ceilometer_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'ceilometer', 'messaging');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_ceilometer_messaging: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'ceilometer', 'authtoken');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_ceilometer_authtoken: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'ceilometer', 'telemetry');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_ceilometer_telemetry: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'cloudkitty', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_cloudkitty_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'cloudkitty', 'messaging');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_cloudkitty_messaging: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'cloudkitty', 'authtoken');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_cloudkitty_authtoken: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'redis', 'redis');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_redis: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'aodh', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_aodh_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'aodh', 'messaging');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_aodh_messaging: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'aodh', 'authtoken');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_aodh_authtoken: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'octavia', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_octavia_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'octavia', 'messaging');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_octavia_messaging: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'octavia', 'authtoken');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_octavia_authtoken: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'octavia', 'heatbeatkey');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_octavia_heatbeatkey: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'magnum', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_magnum_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'magnum', 'messaging');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_magnum_messaging: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'magnum', 'authtoken');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_magnum_authtoken: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'magnum', 'domain');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_magnum_domain: " . $json["data"] . "\n";

            $enc_file .= "      disable_notifications: " . $disable_notifications . "\n";
            $enc_file .= "      enable_monitoring_graphs: " . $enable_monitoring_graphs . "\n";
            $enc_file .= "      monitoring_graphite_host: " . $monitoring_graphite_host . "\n";
            $enc_file .= "      monitoring_graphite_port: " . $monitoring_graphite_port . "\n";

            break;

        case "sql":
            $enc_file .= "   oci::sql:\n";
            $enc_file .= "      cluster_name: $cluster_name\n";
            $enc_file .= "      machine_hostname: $machine_hostname\n";
            $enc_file .= "      machine_ip: $machine_ip\n";
            $enc_file .= "      etc_hosts: ".base64_etc_hosts($con, $conf, $machine_id)."\n";
            $enc_file .= "      time_server_host: $cluster_time_server_host\n";
            $enc_file .= "      network_ipaddr: $network_ip\n";
            $enc_file .= "      network_cidr: $network_cidr\n";
            $enc_file .= "      first_sql: $first_sql_hostname\n";
            $enc_file .= "      first_sql_ip: $first_sql_ipaddr\n";
            $enc_file .= "      is_first_sql: $is_first_sql\n";
            $enc_file .= "      sql_vip_ip: $vip_sql_ip\n";
            $enc_file .= "      sql_vip_netmask: $vip_sql_netmask\n";
            $enc_file .= "      sql_vip_iface: $vip_sql_iface\n";

            $enc_file .= $enc_ashn;
            $enc_file .= $enc_asip;
            $enc_file .= $enc_snids;
            $enc_file .= $enc_non_master_sql;
            $enc_file .= $enc_non_master_sql_ip;

            if($num_compute_nodes > 0){
                $enc_file .= "      has_subrole_glance: true\n";
                $enc_file .= "      has_subrole_nova: true\n";
                $enc_file .= "      has_subrole_neutron: true\n";
                $enc_file .= "      has_subrole_aodh: true\n";
                $enc_file .= "      has_subrole_octavia: true\n";
            }else{
                $enc_file .= "      has_subrole_glance: false\n";
                $enc_file .= "      has_subrole_nova: false\n";
                $enc_file .= "      has_subrole_neutron: false\n";
                $enc_file .= "      has_subrole_aodh: false\n";
                $enc_file .= "      has_subrole_octavia: false\n";
            }
            if($num_cephosd_nodes > 0 || $num_volume_nodes > 0){
                $enc_file .= "      has_subrole_cinder: true\n";
            }else{
                $enc_file .= "      has_subrole_cinder: false\n";
            }
            if($num_cephosd_nodes > 0){
                $enc_file .= "      has_subrole_gnocchi: true\n";
                $enc_file .= "      has_subrole_ceilometer: true\n";
                $enc_file .= "      has_subrole_panko: true\n";
                $enc_file .= "      has_subrole_cloudkitty: true\n";
            }else{
                $enc_file .= "      has_subrole_gnocchi: false\n";
                $enc_file .= "      has_subrole_ceilometer: false\n";
                $enc_file .= "      has_subrole_panko: false\n";
                $enc_file .= "      has_subrole_cloudkitty: false\n";
            }
            $enc_file .= "      has_subrole_heat: true\n";
            $enc_file .= "      has_subrole_barbican: true\n";


            // Send all passwords
            $json = get_cluster_password($con, $conf, $cluster_id, 'mysql', 'rootuser');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_mysql_rootuser: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'mysql', 'backup');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_mysql_backup: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'keystone', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_keystone_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'nova', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_nova_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'nova', 'apidb');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_nova_apidb: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'placement', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_placement_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'glance', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_glance_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'neutron', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_neutron_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'cinder', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_cinder_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'heat', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_heat_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'barbican', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_barbican_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'gnocchi', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_gnocchi_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'panko', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_panko_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'ceilometer', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_ceilometer_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'cloudkitty', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_cloudkitty_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'aodh', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_aodh_db: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'octavia', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_octavia_db: " . $json["data"] . "\n";
            break;

        case "swiftproxy":
            $enc_file .= "   oci::swiftproxy:\n";
            $enc_file .= "      machine_hostname: $machine_hostname\n";
            $enc_file .= "      machine_ip: $machine_ip\n";
            $enc_file .= "      etc_hosts: ".base64_etc_hosts($con, $conf, $machine_id)."\n";
            $enc_file .= "      time_server_host: $cluster_time_server_host\n";
            $enc_file .= "      first_master: $first_master_hostname\n";
            $enc_file .= "      first_master_ip: $first_master_ipaddr\n";
            $enc_file .= "      vip_hostname: $vip_hostname\n";
            $enc_file .= "      vip_ipaddr: $vip_ipaddr\n";
            $enc_file .= "      vip_netmask: $vip_netmask\n";
            $enc_file .= "      network_ipaddr: $network_ip\n";
            $enc_file .= "      network_cidr: $network_cidr\n";
            $enc_file .= "      statsd_hostname: $cluster_statsd_hostname\n";
            $enc_file .= $enc_amhn;
            $enc_file .= $enc_amip;

            $enc_file .= "      all_swiftstore:\n";
            $enc_file .= $enc_allswiftstore_hostanme;
            $enc_file .= "      all_swiftstore_ip:\n";
            $enc_file .= $enc_allswiftstore_ip;
            $enc_file .= "      all_swiftproxy:\n";
            $enc_file .= $enc_allswiftproxies_hostanme;
            $enc_file .= "      all_swiftproxy_ip:\n";
            $enc_file .= $enc_allswiftproxies_ip;
            if($cluster["swift_proxy_hostname"] == ""){
                $enc_file .= "      swiftproxy_hostname: none\n";
            }else{
                $enc_file .= "      swiftproxy_hostname: " . $cluster["swift_proxy_hostname"] ."\n";
            }

            $enc_file .= "      swift_disable_encryption: " . $cluster["swift_disable_encryption"] ."\n";

            $q = "SELECT swiftregions.id AS region_id FROM machines,locations,swiftregions WHERE locations.swiftregion=swiftregions.name AND locations.id=machines.location_id AND machines.id='$machine_id'";
            $r = mysqli_query($con, $q);
            if($r === FALSE){
                $json["status"] = "error";
                $json["message"] = mysqli_error($con);
                return $json;
            }
            $n = mysqli_num_rows($r);
            if($n != 1){
                $json["status"] = "error";
                $json["message"] = "Cannot find swiftregion ID";
                return $json;
            }
            $swiftregion = mysqli_fetch_array($r);
            $swiftregion_id = $swiftregion["region_id"];
            $enc_file .= "      swiftregion_id: $swiftregion_id\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'swift', 'authtoken');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_swift_authtoken: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'swift', 'hashpathsuffix');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_swift_hashpathsuffix: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'swift', 'hashpathprefix');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_swift_hashpathprefix: " . $json["data"] . "\n";

            $enc_file .= "      swift_encryption_key_id: \"" . $cluster["swift_encryption_key_id"] . "\"\n";

            break;

        case "swiftstore":
            $enc_file .= "   oci::swiftstore:\n";
            $enc_file .= "      machine_hostname: $machine_hostname\n";
            $enc_file .= "      machine_ip: $machine_ip\n";
            $enc_file .= "      etc_hosts: ".base64_etc_hosts($con, $conf, $machine_id)."\n";
            $enc_file .= "      time_server_host: $cluster_time_server_host\n";
            $enc_file .= "      network_ipaddr: $network_ip\n";
            $enc_file .= "      network_cidr: $network_cidr\n";
            $enc_file .= "      zoneid: $machine_location\n";
            $enc_file .= "      block_devices:\n";


            if($machine["install_on_raid"] == "no"){
                $q = "SELECT * FROM blockdevices WHERE machine_id='$machine_id' AND name NOT LIKE '%da'";
            }else{
                switch($machine["raid_type"]){
                case "0":
                case "1":
                    $q = "SELECT * FROM blockdevices WHERE machine_id='$machine_id' AND name NOT LIKE '".$machine["raid_dev0"]."' AND name NOT LIKE '".$machine["raid_dev1"]."'";
                    break;
                case "10":
                    $q = "SELECT * FROM blockdevices WHERE machine_id='$machine_id' AND name NOT LIKE '".$machine["raid_dev0"]."' AND name NOT LIKE '".$machine["raid_dev1"]."' AND name NOT LIKE '".$machine["raid_dev2"]."' AND name NOT LIKE '".$machine["raid_dev3"]."'";
                    break;
                case "5":
                default:
                    die("Not supported yet.");
                    break;
                }
            }
            $r = mysqli_query($con, $q);
            $n = mysqli_num_rows($r);
            if($n < 1){
                $json["status"] = "error";
                $json["message"] = "Cannot find block devices.";
                return $json;
            }
            for($i=0;$i<$n;$i++){
                $a = mysqli_fetch_array($r);
                $hdd_name = $a["name"];
                $enc_file .= "         - $hdd_name\n";
            }

            $enc_file .= "      statsd_hostname: $cluster_statsd_hostname\n";

            $enc_file .= "      all_swiftstore:\n";
            $enc_file .= $enc_allswiftstore_hostanme;
            $enc_file .= "      all_swiftstore_ip:\n";
            $enc_file .= $enc_allswiftstore_ip;
            $enc_file .= "      all_swiftproxy:\n";
            $enc_file .= $enc_allswiftproxies_hostanme;
            $enc_file .= "      all_swiftproxy_ip:\n";
            $enc_file .= $enc_allswiftproxies_ip;

            $json = get_cluster_password($con, $conf, $cluster_id, 'swift', 'hashpathsuffix');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_swift_hashpathsuffix: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'swift', 'hashpathprefix');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_swift_hashpathprefix: " . $json["data"] . "\n";

            break;

        case "compute":
            $enc_file .= "   oci::compute:\n";
            $enc_file .= "      openstack_release: $openstack_release\n";
            $enc_file .= "      machine_hostname: $machine_hostname\n";
            $enc_file .= "      cluster_name: $cluster_name\n";
            $enc_file .= "      machine_ip: $machine_ip\n";
            $enc_file .= "      etc_hosts: ".base64_etc_hosts($con, $conf, $machine_id)."\n";
            $enc_file .= "      time_server_host: $cluster_time_server_host\n";
            $enc_file .= $enc_bridge_list;
            $enc_file .= "      first_master: $first_master_hostname\n";
            $enc_file .= "      first_master_ip: $first_master_ipaddr\n";
            $enc_file .= "      cluster_domain: $cluster_domain\n";
            $enc_file .= "      sql_vip_ip: $vip_sql_ip\n";

            // Get the IP for VM trafic.
            $q = "SELECT machines.hostname AS hostname, INET_NTOA(ips.ip) AS ipaddr, networks.mtu AS mtu FROM machines, ips, networks WHERE machines.id='$machine_id' AND machines.cluster='$cluster_id' AND machines.id=ips.machine AND ips.network=networks.id AND networks.is_public='no' AND networks.role='vm-net'";
            $r = mysqli_query($con, $q);
            if($r === FALSE){
                $json["status"] = "error";
                $json["message"] = mysqli_error($con);
                return $json;
            }
            $n = mysqli_num_rows($r);
            if($n == 1){
                $vmnet_ip_array = mysqli_fetch_array($r);
                $vmnet_ip = $vmnet_ip_array["ipaddr"];
                $vmnet_mtu = $vmnet_ip_array["mtu"];
                $enc_file .= "      vmnet_ip: $vmnet_ip\n";
                $enc_file .= "      vmnet_mtu: $vmnet_mtu\n";
            // If we don't find a VMNet IP, then let's use the management network IP instead.
            }else{
                $enc_file .= "      vmnet_ip: $machine_ip\n";
                $enc_file .= "      vmnet_mtu: 0\n";
            }

            $enc_file .= $enc_amhn;
            $enc_file .= $enc_amip;
            $enc_file .= "      vip_hostname: $vip_hostname\n";
            $enc_file .= "      vip_ipaddr: $vip_ipaddr\n";
            $enc_file .= "      vip_netmask: $vip_netmask\n";
            $enc_file .= "      network_ipaddr: $network_ip\n";
            $enc_file .= "      network_cidr: $network_cidr\n";

            $enc_file .= "      use_ceph_if_available: ".$machine["use_ceph_if_available"]."\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'cinder', 'messaging');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_cinder_messaging: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'cinder', 'authtoken');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_cinder_authtoken: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'cinder', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_cinder_db: " . $json["data"] . "\n";

            if($num_cephosd_nodes > 0){
                $enc_file .= "      cluster_has_osds: true\n";
                $enc_file .= "      has_subrole_ceilometer: true\n";

                $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'fsid');
                if($json["status"] != "success"){ return $json; }
                $enc_file .= "      ceph_fsid: " . $json["data"] . "\n";

                $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'libvirtuuid');
                if($json["status"] != "success"){ return $json; }
                $enc_file .= "      ceph_libvirtuuid: " . $json["data"] . "\n";

                $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'bootstraposdkey');
                if($json["status"] != "success"){ return $json; }
                $enc_file .= "      ceph_bootstrap_osd_key: " . $json["data"] . "\n";

                $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'adminkey');
                if($json["status"] != "success"){ return $json; }
                $enc_file .= "      ceph_admin_key: " . $json["data"] . "\n";

                $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'openstackkey');
                if($json["status"] != "success"){ return $json; }
                $enc_file .= "      ceph_openstack_key: " . $json["data"] . "\n";

                $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'monkey');
                if($json["status"] != "success"){ return $json; }
                $enc_file .= "      ceph_mon_key: " . $json["data"] . "\n";

                $ret = enc_get_mon_nodes($con,$conf);
                if($ret["status"] != "success"){
                    return $ret;
                }
                $enc_file .= "      ceph_mon_initial_members: " . $ret["data"]["osd_members"] . "\n";
                $enc_file .= "      ceph_mon_host: " . $ret["data"]["osd_ips"] . "\n";

                $json = get_cluster_password($con, $conf, $cluster_id, 'ceilometer', 'telemetry');
                if($json["status"] != "success"){ return $json; }
                $enc_file .= "      pass_ceilometer_telemetry: " . $json["data"] . "\n";

                $json = get_cluster_password($con, $conf, $cluster_id, 'ceilometer', 'messaging');
                if($json["status"] != "success"){ return $json; }
                $enc_file .= "      pass_ceilometer_messaging: " . $json["data"] . "\n";

                $json = get_cluster_password($con, $conf, $cluster_id, 'ceilometer', 'authtoken');
                if($json["status"] != "success"){ return $json; }
                $enc_file .= "      pass_ceilometer_authtoken: " . $json["data"] . "\n";

            }else{
                $enc_file .= "      cluster_has_osds: false\n";
            }

            $json = get_cluster_password($con, $conf, $cluster_id, 'nova', 'messaging');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_nova_messaging: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'nova', 'authtoken');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_nova_authtoken: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'nova', 'ssh');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_nova_ssh_pub: " . unserialize($json["data"]["ssh_pub"]) . "\n";
            $enc_file .= "      pass_nova_ssh_priv: " . base64_encode(unserialize($json["data"]["ssh_priv"])) . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'novaneutron', 'shared_secret');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_metadata_proxy_shared_secret: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'neutron', 'messaging');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_neutron_messaging: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'neutron', 'authtoken');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_neutron_authtoken: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'placement', 'authtoken');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_placement_authtoken: " . $json["data"] . "\n";

            $enc_file .= "      disable_notifications: " . $disable_notifications . "\n";
            $enc_file .= "      enable_monitoring_graphs: " . $enable_monitoring_graphs . "\n";
            $enc_file .= "      monitoring_graphite_host: " . $monitoring_graphite_host . "\n";
            $enc_file .= "      monitoring_graphite_port: " . $monitoring_graphite_port . "\n";
            break;

        case "cephosd":
            $enc_file .= "   oci::cephosd:\n";
            $enc_file .= "      machine_hostname: $machine_hostname\n";
            $enc_file .= "      machine_ip: $machine_ip\n";
            $enc_file .= "      etc_hosts: ".base64_etc_hosts($con, $conf, $machine_id)."\n";
            $enc_file .= "      time_server_host: $cluster_time_server_host\n";
            $enc_file .= "      vip_hostname: $vip_hostname\n";
            $enc_file .= "      vip_ipaddr: $vip_ipaddr\n";
            $enc_file .= "      vip_netmask: $vip_netmask\n";
            $enc_file .= "      network_ipaddr: $network_ip\n";
            $enc_file .= "      network_cidr: $network_cidr\n";
            $enc_file .= "      block_devices:\n";

            $q = "SELECT * FROM blockdevices WHERE machine_id='$machine_id' AND name NOT LIKE '%da'";
            $r = mysqli_query($con, $q);
            $n = mysqli_num_rows($r);
            if($n < 1){
                $json["status"] = "error";
                $json["message"] = "Cannot find block devices.";
                return $json;
            }
            for($i=0;$i<$n;$i++){
                $a = mysqli_fetch_array($r);
                $hdd_name = $a["name"];
                $enc_file .= "         - $hdd_name\n";
            }

            $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'fsid');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      ceph_fsid: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'bootstraposdkey');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      ceph_bootstrap_osd_key: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'adminkey');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      ceph_admin_key: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'openstackkey');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      ceph_openstack_key: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'monkey');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      ceph_mon_key: " . $json["data"] . "\n";

            $ret = enc_get_mon_nodes($con,$conf);
            if($ret["status"] != "success"){
                return $ret;
            }
            $enc_file .= "      ceph_mon_initial_members: " . $ret["data"]["osd_members"] . "\n";
            $enc_file .= "      ceph_mon_host: " . $ret["data"]["osd_ips"] . "\n";

            // Get the IP for the cluster network.
            $q = "SELECT machines.hostname AS hostname, INET_NTOA(ips.ip) AS ipaddr, networks.mtu AS mtu, networks.ip AS networkaddr, networks.cidr AS networkcidr FROM machines, ips, networks WHERE machines.id='$machine_id' AND machines.cluster='$cluster_id' AND machines.id=ips.machine AND ips.network=networks.id AND networks.is_public='no' AND networks.role='ceph-cluster'";
            $r = mysqli_query($con, $q);
            if($r === FALSE){
                $json["status"] = "error";
                $json["message"] = mysqli_error($con);
                return $json;
            }
            $n = mysqli_num_rows($r);
            if($n == 1){
                $cephnet_ip_array = mysqli_fetch_array($r);
                $cephnet_ip          = $cephnet_ip_array["ipaddr"];
                $cephnet_mtu         = $cephnet_ip_array["mtu"];
                $cephnet_networkaddr = $cephnet_ip_array["networkaddr"];
                $cephnet_networkcidr = $cephnet_ip_array["networkcidr"];
                $enc_file .= "      use_ceph_cluster_net: true\n";
                $enc_file .= "      cephnet_ip: $cephnet_ip\n";
                $enc_file .= "      cephnet_network_addr: $cephnet_networkaddr\n";
                $enc_file .= "      cephnet_network_cidr: $cephnet_networkcidr\n";
                $enc_file .= "      cephnet_mtu: $cephnet_mtu\n";
            // If we don't find a ceph-cluster IP, then let's use the management network IP instead.
            }else{
                $enc_file .= "      use_ceph_cluster_net: false\n";
                $enc_file .= "      cephnet_ip: $machine_ip\n";
                $enc_file .= "      cephnet_mtu: 0\n";
            }

            break;

        case "cephmon":
            $enc_file .= "   oci::cephmon:\n";
            $enc_file .= "      machine_hostname: $machine_hostname\n";
            $enc_file .= "      machine_ip: $machine_ip\n";
            $enc_file .= "      etc_hosts: ".base64_etc_hosts($con, $conf, $machine_id)."\n";
            $enc_file .= "      time_server_host: $cluster_time_server_host\n";
            $enc_file .= "      vip_hostname: $vip_hostname\n";
            $enc_file .= "      vip_ipaddr: $vip_ipaddr\n";
            $enc_file .= "      vip_netmask: $vip_netmask\n";
            $enc_file .= "      network_ipaddr: $network_ip\n";
            $enc_file .= "      network_cidr: $network_cidr\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'fsid');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      ceph_fsid: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'bootstraposdkey');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      ceph_bootstrap_osd_key: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'adminkey');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      ceph_admin_key: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'openstackkey');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      ceph_openstack_key: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'monkey');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      ceph_mon_key: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'ceph', 'mgrkey');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      ceph_mgr_key: " . $json["data"] . "\n";

            $ret = enc_get_mon_nodes($con,$conf);
            if($ret["status"] != "success"){
                return $ret;
            }
            $enc_file .= "      ceph_mon_initial_members: " . $ret["data"]["osd_members"] . "\n";
            $enc_file .= "      ceph_mon_host: " . $ret["data"]["osd_ips"] . "\n";
            break;

        case "volume":
            $enc_file .= "   oci::volume:\n";
            $enc_file .= "      machine_hostname: $machine_hostname\n";
            $enc_file .= "      machine_ip: $machine_ip\n";
            $enc_file .= "      etc_hosts: ".base64_etc_hosts($con, $conf, $machine_id)."\n";
            $enc_file .= "      time_server_host: $cluster_time_server_host\n";
            $enc_file .= $enc_amhn;
            $enc_file .= $enc_amip;
            $enc_file .= "      first_master: $first_master_hostname\n";
            $enc_file .= "      first_master_ip: $first_master_ipaddr\n";
            $enc_file .= "      sql_vip_ip: $vip_sql_ip\n";
            $enc_file .= "      vip_hostname: $vip_hostname\n";
            $enc_file .= "      vip_ipaddr: $vip_ipaddr\n";
            $enc_file .= "      vip_netmask: $vip_netmask\n";
            $enc_file .= "      network_ipaddr: $network_ip\n";
            $enc_file .= "      network_cidr: $network_cidr\n";

            $enc_file .= "      block_devices:\n";
            $q = "SELECT * FROM blockdevices WHERE machine_id='$machine_id' AND name NOT LIKE '%da'";
            $r = mysqli_query($con, $q);
            $n = mysqli_num_rows($r);
            if($n < 1){
                $json["status"] = "error";
                $json["message"] = "Cannot find block devices.";
                return $json;
            }
            for($i=0;$i<$n;$i++){
                $a = mysqli_fetch_array($r);
                $hdd_name = $a["name"];
                $enc_file .= "         - $hdd_name\n";
            }

            $volume_node_number = explode("-", explode(".", $machine_hostname)[0])[2];
            $enc_file .= "      vgname: " . $cluster_name . "vol" . $volume_node_number . "vg0" ."\n";

            if($num_cephosd_nodes > 0){
                $enc_file .= "      backup_backend: ceph\n";
            }elseif($num_swiftstore_nodes > 0){
                $enc_file .= "      backup_backend: swift\n";
            }else{
                $enc_file .= "      backup_backend: none\n";
            }

            $json = get_cluster_password($con, $conf, $cluster_id, 'cinder', 'messaging');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_cinder_messaging: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'cinder', 'authtoken');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_cinder_authtoken: " . $json["data"] . "\n";

            $json = get_cluster_password($con, $conf, $cluster_id, 'cinder', 'db');
            if($json["status"] != "success"){ return $json; }
            $enc_file .= "      pass_cinder_db: " . $json["data"] . "\n";

            break;

        case "debmirror":
            $enc_file .= "   oci::debmirror:\n";
            $enc_file .= "      machine_hostname: $machine_hostname\n";
            $enc_file .= "      machine_ip: $machine_ip\n";
            $enc_file .= "      etc_hosts: ".base64_etc_hosts($con, $conf, $machine_id)."\n";
            $enc_file .= "      time_server_host: $cluster_time_server_host\n";
            break;

        default:
            // If we don't know the role, then it's a custom one. Just output the
            // "standard" enc yaml file.
            break;
        }

        $json["data"] = $enc_file;
        return $json;
    case "machine_show_from_hostname":
        $safe_hostname = safe_fqdn("hostname");
        $r = mysqli_query($con, "SELECT * FROM machines WHERE hostname='$safe_hostname'");
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "No such hostname in database.";
            return $json;
        }
        $a = mysqli_fetch_array($r);
        $json["data"] = $a;
        return $json;
        break;
    case "cluster_show":
        $safe_cluster_name = safe_fqdn("name");
        if($safe_cluster_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid cluster name.";
            return $json;
        }
        $q = "SELECT * FROM clusters WHERE name='$safe_cluster_name'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Error: cluster not found.";
            return $json;
        }
        $json["data"] = mysqli_fetch_array($r);
        return $json;
        break;
    case "cluster_set":
        $safe_cluster_name = safe_fqdn("name");
        if($safe_cluster_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid cluster name.";
            return $json;
        }
        $q = "SELECT * FROM clusters WHERE name='$safe_cluster_name'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Error: cluster not found.";
            return $json;
        }
        $update = "";

        // Swift part power
        if(isset($_REQUEST["swift_part_power"])){
            $safe_swift_part_power = safe_int("swift_part_power");
            if($safe_swift_part_power === FALSE){
                $json["status"] = "error";
                $json["message"] = "Error: not valid swift part power.";
                return $json;
            }
            if($update != ""){
                $update .= ", ";
            }
            $update .= "swift_part_power='$safe_swift_part_power'";
        }

        // Swift proxy hostname
        if(isset($_REQUEST["swift_proxy_hostname"])){
            $safe_swift_proxy_hostname = safe_fqdn("swift_proxy_hostname");
            if($safe_swift_proxy_hostname === FALSE){
                $json["status"] = "error";
                $json["message"] = "Error: not a valid swift proxy hostname.";
                return $json;
            }
            if($update != ""){
                $update .= ", ";
            }
            $update .= "swift_proxy_hostname='$safe_swift_proxy_hostname'";
        }

        // Swift encryption key ID
        if(isset($_REQUEST["swift_encryption_key_id"])){
            $safe_swift_encryption_key_id = safe_uuid("swift_encryption_key_id");
            if($safe_swift_encryption_key_id === FALSE){
                $json["status"] = "error";
                $json["message"] = "Error: swift_encryption_key_id is not an UUID.";
                return $json;
            }
            if($update != ""){
                $update .= ", ";
            }
            $update .= "swift_encryption_key_id='$safe_swift_encryption_key_id'";
        }

        // Swift disable encryption
        if(isset($_REQUEST["swift_disable_encryption"])){
            if($_REQUEST["swift_disable_encryption"] == "yes"){
                $safe_swift_disable_encryption = "yes";
            }else{
                $safe_swift_disable_encryption = "no";
            }
            if($update != ""){
                $update .= ", ";
            }
            $update .= "swift_disable_encryption='$safe_swift_disable_encryption'";
        }

        // Time server host
        if(isset($_REQUEST["time_server_host"])){
            $safe_time_server_host = safe_fqdn("time_server_host");
            if($safe_swift_part_power === FALSE){
                $json["status"] = "error";
                $json["message"] = "Error: not valid time server host.";
                return $json;
            }
            if($update != ""){
                $update .= ", ";
            }
            $update .= "time_server_host='$safe_time_server_host'";
        }

        if(isset($_REQUEST["amp_secgroup_list"])){
            $safe_amp_secgroup_list = safe_uuid_list("amp_secgroup_list");
            if($safe_amp_secgroup_list === FALSE){
                $json["status"] = "error";
                $json["message"] = "Error: not valid UUID list for amp_secgroup_list.";
                return $json;
            }
            if($update != ""){
                $update .= ", ";
            }
            $update .= "amp_secgroup_list='$safe_amp_secgroup_list'";
        }

        if(isset($_REQUEST["amp_boot_network_list"])){
            $safe_amp_boot_network_list = safe_uuid_list("amp_boot_network_list");
            if($safe_amp_boot_network_list === FALSE){
                $json["status"] = "error";
                $json["message"] = "Error: not valid UUID list for amp_boot_network_list.";
                return $json;
            }
            if($update != ""){
                $update .= ", ";
            }
            $update .= "amp_boot_network_list='$safe_amp_boot_network_list'";
        }

        if(isset($_REQUEST["disable_notifications"])){
            if($_REQUEST["disable_notifications"] == "yes"){
                $safe_disable_notifications = "yes";
            }else{
                $safe_disable_notifications = "no";
            }
            if($update != ""){
                $update .= ", ";
            }
            $update .= "disable_notifications='$safe_disable_notifications'";
        }

        if(isset($_REQUEST["enable_monitoring_graphs"])){
            if($_REQUEST["enable_monitoring_graphs"] == "yes"){
                $safe_enable_monitoring_graphs = "yes";
            }else{
                $safe_enable_monitoring_graphs = "no";
            }
            if($update != ""){
                $update .= ", ";
            }
            $update .= "enable_monitoring_graphs='$safe_enable_monitoring_graphs'";
        }

        if(isset($_REQUEST["monitoring_graphite_host"])){
            $safe_monitoring_graphite_host = safe_fqdn("monitoring_graphite_host");
            if($safe_monitoring_graphite_host === FALSE){
                $json["status"] = "error";
                $json["message"] = "Error: not valid monitoring graphite host.";
                return $json;
            }
            if($update != ""){
                $update .= ", ";
            }
            $update .= "monitoring_graphite_host='$safe_monitoring_graphite_host'";
        }

        if(isset($_REQUEST["monitoring_graphite_port"])){
            $safe_monitoring_graphite_port = safe_int("monitoring_graphite_port");
            if($safe_monitoring_graphite_port === FALSE){
                $json["status"] = "error";
                $json["message"] = "Error: not valid monitoring graphite port.";
                return $json;
            }
            if($update != ""){
                $update .= ", ";
            }
            $update .= "monitoring_graphite_port='$safe_monitoring_graphite_port'";
        }

        $q = "UPDATE clusters SET $update WHERE name='$safe_cluster_name'";
        if($update != ""){
            $r = mysqli_query($con, $q);
        }

        return $json;
        break;
    case "cluster_show_ips":
        $safe_cluster_name = safe_fqdn("name");
        if($safe_cluster_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid cluster name.";
            return $json;
        }
        $q = "SELECT * FROM clusters WHERE name='$safe_cluster_name'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Error: cluster not found.";
            return $json;
        }
        $cluster = mysqli_fetch_array($r);
        $cluster_id = $cluster["id"];
        $q = "SELECT machines.hostname AS hostname, INET_NTOA(ips.ip) AS ipaddr FROM machines,ips WHERE machines.cluster='$cluster_id' AND machines.id=ips.machine";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        for($i=0;$i<$n;$i++){
            $json["data"][] = mysqli_fetch_array($r);
        }
        return $json;
        break;
    case "machine_destroy":
        $safe_machine_serial = safe_serial("machine_serial");
        if($safe_machine_serial === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid chassis serial number.";
            return $json;
        }
        $r = mysqli_query($con, "SELECT * FROM machines WHERE serial='$safe_machine_serial'");
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con). " doing $q";
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Error: machine with serial $safe_machine_serial doesn't exist.";
            return $json;
        }
        $machine = mysqli_fetch_array($r);
        $machine_id      = $machine["id"];

        $q = "DELETE FROM blockdevices WHERE machine_id='$machine_id'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con). " doing $q";
            return $json;
        }

        $q = "DELETE FROM ifnames WHERE machine_id='$machine_id'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con). " doing $q";
            return $json;
        }

        $q = "DELETE FROM ips WHERE machine='$machine_id'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con). " doing $q";
            return $json;
        }

        $q = "DELETE FROM machines WHERE id='$machine_id'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con). " doing $q";
            return $json;
        }
        return $json;
        break;
    case "get_machine_status":
        # Validate IPv4
        $safe_ip = safe_ipv4("ipaddr");
        if($safe_ip === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: wrong ipaddr param.";
            return $json;
        }
        if(check_machine_with_ip_exists($safe_ip) === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: machine with ip $safe_ip doesn't exist.";
            return $json;
        }
        # Fetch machine info and output
        $r = mysqli_query($con, "SELECT * FROM machines WHERE ipaddr='$safe_ip'");
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $install_machine = mysqli_fetch_array($r);
        $json["data"] = $install_machine;
        return $json;
        break;
    case "machine_list":
        $r = mysqli_query($con, "SELECT * FROM machines");
        $n = mysqli_num_rows($r);
        $json["data"] = array();
        for($i=0;$i<$n;$i++){
            $a = mysqli_fetch_array($r);
            $json["data"][$i] = $a;
            $allfld = array("hostname","notes","loc_dc","loc_row","loc_rack","loc_u_start","loc_u_end","ladvd_report");
            foreach ($allfld as &$fld){
                if($json["data"][$i][$fld] == ""){
                    $json["data"][$i][$fld] = "-";
                }
            }
        }
        return $json;
        break;
    case "machine_show":
        // Check the serial
        $safe_machine_serial = safe_serial("machine_serial");
        if($safe_machine_serial === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid chassis serial number.";
            return $json;
        }
        $r = mysqli_query($con, "SELECT * FROM machines WHERE serial='$safe_machine_serial'");
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Cannot find a machine with this serial.";
            return $json;
        }
        $machine = mysqli_fetch_array($r);
        $json["data"] = $machine;

        $r = mysqli_query($con, "SELECT * FROM clusters WHERE id='".$machine["cluster"]."'");
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $cluster = mysqli_fetch_array($r);
        $json["data"]["machine_cluster"] = $cluster;

        $r = mysqli_query($con, "SELECT * FROM blockdevices WHERE machine_id='".$machine["id"]."'");
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        $blockdevs = [];
        for($i=0;$i<$n;$i++){
            $blockdevs[] = mysqli_fetch_array($r);
        }
        $json["data"]["machine_blockdevices"] = $blockdevs;

        $r = mysqli_query($con, "SELECT INET_NTOA(ips.ip) as ipaddr,networks.name AS networkname FROM ips,networks WHERE machine='".$machine["id"]."' AND ips.network=networks.id");
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        $ips = [];
        for($i=0;$i<$n;$i++){
            $ips[] = mysqli_fetch_array($r);
        }
        $json["data"]["machine_ips"] = $ips;

        $r = mysqli_query($con, "SELECT * FROM ifnames WHERE machine_id='".$machine["id"]."'");
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        $ifs = [];
        for($i=0;$i<$n;$i++){
            $ifs[] = mysqli_fetch_array($r);
        }
        $json["data"]["machine_ifs"] = $ifs;

        return $json;
        break;
    case "machine_set":
        $safe_machine_serial = safe_serial("machine_serial");
        if($safe_machine_serial === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid chassis serial number.";
            return $json;
        }
        $r = mysqli_query($con, "SELECT * FROM machines WHERE serial='$safe_machine_serial'");
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Cannot find a machine with this serial.";
            return $json;
        }
        $machine = mysqli_fetch_array($r);

        $Q_ADDON = "";

        if(isset($_REQUEST["use_ceph"])){
            if($_REQUEST["use_ceph"] == "yes"){
                $Q_ADDON .= "use_ceph_if_available='yes'";
            }else{
                $Q_ADDON .= "use_ceph_if_available='no'";
            }
        }

        if(isset($_REQUEST["install_on_raid"])){
            if($Q_ADDON != ""){
                $Q_ADDON .= ", ";
            }
            if($_REQUEST["install_on_raid"] == "yes"){
                $Q_ADDON .= "install_on_raid='yes'";
            }else{
                $Q_ADDON .= "install_on_raid='no'";
            }
        }

        if(isset($_REQUEST["raid_type"])){
            if($Q_ADDON != ""){
                $Q_ADDON .= ", ";
            }
            switch($_REQUEST["raid_type"]){
            case "0":
                $Q_ADDON .= "raid_type='0'";
                break;
            default:
            case "1":
                $Q_ADDON .= "raid_type='1'";
                break;
            case "5":
                $Q_ADDON .= "raid_type='5'";
                break;
            case "10":
                $Q_ADDON .= "raid_type='10'";
                break;
            }
        }

        if(isset($_REQUEST["raid_dev0"])){
            $raid_dev0 = safe_blockdev_name("raid_dev0");
            if($raid_dev0 === FALSE){
                $json["status"] = "error";
                $json["message"] = "Error: not valid raid device name.";
                return $json;
            }
            if($Q_ADDON != ""){
                $Q_ADDON .= ", ";
            }
            $Q_ADDON .= "raid_dev0='$raid_dev0'";
        }

        if(isset($_REQUEST["raid_dev1"])){
            $raid_dev1 = safe_blockdev_name("raid_dev1");
            if($raid_dev1 === FALSE){
                $json["status"] = "error";
                $json["message"] = "Error: not valid raid device name.";
                return $json;
            }
            if($Q_ADDON != ""){
                $Q_ADDON .= ", ";
            }
            $Q_ADDON .= "raid_dev1='$raid_dev1'";
        }

        if(isset($_REQUEST["raid_dev2"])){
            $raid_dev2 = safe_blockdev_name("raid_dev2");
            if($raid_dev2 === FALSE){
                $json["status"] = "error";
                $json["message"] = "Error: not valid raid device name.";
                return $json;
            }
            if($Q_ADDON != ""){
                $Q_ADDON .= ", ";
            }
            $Q_ADDON .= "raid_dev2='$raid_dev2'";
        }

        if(isset($_REQUEST["raid_dev3"])){
            $raid_dev3 = safe_blockdev_name("raid_dev3");
            if($raid_dev3 === FALSE){
                $json["status"] = "error";
                $json["message"] = "Error: not valid raid device name.";
                return $json;
            }
            if($Q_ADDON != ""){
                $Q_ADDON .= ", ";
            }
            $Q_ADDON .= "raid_dev3='$raid_dev3'";
        }

        if(isset($_REQUEST["serial_console_device"])){
            $serial_console_device = safe_blockdev_name("serial_console_device");
            if($serial_console_device === FALSE){
                $json["status"] = "error";
                $json["message"] = "Error: not valid serial device name.";
                return $json;
            }
            if($Q_ADDON != ""){
                $Q_ADDON .= ", ";
            }
            $Q_ADDON .= "serial_console_dev='$serial_console_device'";
        }

        $location_param_list = [ "loc_dc", "loc_row", "loc_rack", "loc_u_start", "loc_u_end" ];
        foreach ($location_param_list as &$one_param){
            if(isset($_REQUEST[$one_param])){
                $safe_param_value = safe_fqdn($one_param);
                if($safe_param_value === FALSE){
                    $json["status"] = "error";
                    $json["message"] = "Error: not valid $one_param parameter.";
                    return $json;
                }
                if($Q_ADDON != ""){
                    $Q_ADDON .= ", ";
                }
                $Q_ADDON .= "$one_param='$safe_param_value'";
            }
        }

        $q = "UPDATE machines SET $Q_ADDON WHERE serial='$safe_machine_serial'";
        $r = mysqli_query($con, $q);

        return $json;
        break;
    # Remove a machine from a cluster
    case "machine_remove":
        // Check the serial
        $safe_machine_serial = safe_serial("machine_serial");
        if($safe_machine_serial === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid chassis serial number.";
            return $json;
        }
        $r = mysqli_query($con, "SELECT * FROM machines WHERE serial='$safe_machine_serial'");
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Error: machine with serial $safe_machine_serial doesn't exist.";
            return $json;
        }
        $machine = mysqli_fetch_array($r);
        $machine_status  = $machine["status"];
        $machine_id      = $machine["id"];
        $machine_cluster = $machine["cluster"];
        $machine_role    = $machine["role"];

        $q = "UPDATE machines SET cluster=NULL WHERE id='$machine_id'";
        $r = mysqli_query($con, $q);

        // If role is controller, check if there's another controller left. If there's at least one, then
        // we must set any of these controllers as first master
        if($machine_role == "controller"){
            $q = "SELECT first_master_machine_id FROM clusters WHERE id='$machine_cluster'";
            $r = mysqli_query($con, $q);
            $cluster = mysqli_fetch_array($r);
            $fm_id = $cluster["first_master_machine_id"];;
            if($fm_id == $machine_id){
                $q = "SELECT * FROM machines WHERE cluster='$machine_cluster' AND role='controller' LIMIT 1;";
                $r = mysqli_query($con, $q);
                $n = mysqli_num_rows($r);
                // No controler left: we set the first_master as NULL
                if($n == 0){
                    $q = "UPDATE clusters SET first_master_machine_id=NULL WHERE id='$machine_cluster'";
                    $r = mysqli_query($con, $q);
                // We just set the first controler we find left as first_master
                }else{
                    $first_master = mysqli_fetch_array($r);
                    $new_fm_id = $first_master["id"];
                    $q = "UPDATE clusters SET first_master_machine_id='$new_fm_id' WHERE id='$machine_cluster'";
                    $r = mysqli_query($con, $q);
                }
            }
        }

        // If role is sql, check if there's another sql node left. If there's at least one, then
        // we must set a sql node as first master
        if($machine_role == "sql"){
            $q = "SELECT first_sql_machine_id FROM clusters WHERE id='$machine_cluster'";
            $r = mysqli_query($con, $q);
            $cluster = mysqli_fetch_array($r);
            $fsql_id = $cluster["first_master_machine_id"];;
            if($fsql_id == $machine_id){
                $q = "SELECT * FROM machines WHERE cluster='$machine_cluster' AND role='sql' LIMIT 1;";
                $r = mysqli_query($con, $q);
                $n = mysqli_num_rows($r);
                // No sql node left: we set the first_master as NULL
                if($n == 0){
                    $q = "UPDATE clusters SET first_sql_machine_id=NULL WHERE id='$machine_cluster'";
                    $r = mysqli_query($con, $q);
                // We just set the first sql node we find left as first_sql
                }else{
                    $first_master = mysqli_fetch_array($r);
                    $new_fsql_id = $first_master["id"];
                    $q = "UPDATE clusters SET first_sql_machine_id='$new_fsql_id' WHERE id='$machine_cluster'";
                    $r = mysqli_query($con, $q);
                }
            }
        }

        // Delete all IPs of the machine in the DB
        $q = "DELETE FROM ips WHERE machine='$machine_id'";
        $r = mysqli_query($con, $q);
        return $json;
        break;
    # Add a machine to a cluster
    case "machine_add":
        // Check the serial
        $safe_machine_serial = safe_serial("machine_serial");
        if($safe_machine_serial === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid chassis serial number.";
            return $json;
        }
        $r = mysqli_query($con, "SELECT * FROM machines WHERE serial='$safe_machine_serial'");
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Error: machine with serial $safe_machine_serial doesn't exist.";
            return $json;
        }
        $machine = mysqli_fetch_array($r);
        $machine_status  = $machine["status"];
        $machine_id      = $machine["id"];
        $machine_cluster = $machine["cluster"];

        // Check status of that machine
        if($machine_status != "live"){
            $json["status"] = "error";
            $json["message"] = "Error: machine isn't running live image.";
            return $json;
        }
        if(!is_null($machine["cluster"])){
            $json["status"] = "error";
            $json["message"] = "Error: machine already enrolled in a cluster.";
            return $json;
        }

        if($machine["cluster"] !== NULL){
            $json["status"] = "error";
            $json["message"] = "Error: machine with serial $safe_machine_serial is already enrolled.";
            return $json;
        }

        // Check cluster name and fetch its id
        $safe_cluster_name = safe_fqdn("cluster_name");
        if($safe_cluster_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid cluster name.";
            return $json;
        }
        $r = mysqli_query($con, "SELECT * FROM clusters WHERE name='$safe_cluster_name'");
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Error: cluster name $safe_cluster_name doesn't exist.";
            return $json;
        }
        $cluster = mysqli_fetch_array($r);
        $safe_cluster_id     = $cluster["id"];

        // Check role name
        $safe_role_name = safe_fqdn("role_name");
        if($safe_role_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid role name.";
            return $json;
        }
        $r = mysqli_query($con, "SELECT id FROM roles WHERE name='$safe_role_name'");
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Error: role name $safe_role_name doesn't exist.";
            return $json;
        }
        $role = mysqli_fetch_array($r);
        $safe_role_id = $role["id"];

        // Check location name
        $safe_location_name = safe_fqdn("location_name");
        if($safe_location_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid location name.";
            return $json;
        }
        $r = mysqli_query($con, "SELECT id FROM locations WHERE name='$safe_location_name'");
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Error: location name $safe_location_name doesn't exist.";
            return $json;
        }
        $location = mysqli_fetch_array($r);
        $safe_location_id = $location["id"];

        $json = add_node_to_cluster($con, $conf, $machine_id, $safe_cluster_id, $safe_role_name, $safe_location_id);
        return $json;
        break;
    case "location_list":
        $r = mysqli_query($con, "SELECT * FROM locations ORDER BY id");
        $n = mysqli_num_rows($r);
        $json["data"] = [];
        for($i=0;$i<$n;$i++){
            $a = mysqli_fetch_array($r);
            $json["data"][$i] = $a;
        }
        return $json;
        break;
    case "location_create":
        # Validate location name
        $safe_location_name = safe_fqdn("name");
        if($safe_location_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: location name format.";
            return $json;
        }
        $q = "SELECT * FROM locations WHERE name='$safe_location_name'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 0){
            $json["status"] = "error";
            $json["message"] = "Error: location name already exists.";
            return $json;
        }
        # Validate swiftregion
        $safe_swiftregion_name = safe_fqdn("swiftregion");
        if($safe_swiftregion_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: swiftregion name format.";
            return $json;
        }
        $q = "SELECT * FROM swiftregions WHERE name='$safe_swiftregion_name'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Error: swiftregion name not found.";
            return $json;
        }

        $r = mysqli_query($con, "INSERT INTO locations (name, swiftregion) VALUES ('$safe_location_name', '$safe_swiftregion_name')");
        return $json;
        break;
    case "location_delete":
        # Validate location name
        $safe_location_name = safe_fqdn("name");
        if($safe_location_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: location name format.";
            return $json;
        }
        $q = "SELECT * FROM locations WHERE name='$safe_location_name'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Error: location name not found.";
            return $json;
        }
        $r = mysqli_query($con, "DELETE FROM locations WHERE name='$safe_location_name'");
        return $json;
        break;
    case "swiftregion_list":
        $r = mysqli_query($con, "SELECT * FROM swiftregions ORDER BY id");
        $n = mysqli_num_rows($r);
        $json["data"] = [];
        for($i=0;$i<$n;$i++){
            $a = mysqli_fetch_array($r);
            $json["data"][$i] = $a;
        }
        return $json;
        break;
    case "swiftregion_create":
        # Validate swiftregion name
        $safe_swiftregion_name = safe_fqdn("name");
        if($safe_swiftregion_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: swiftregion name format.";
            return $json;
        }
        $q = "SELECT * FROM swiftregions WHERE name='$safe_swiftregion_name'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 0){
            $json["status"] = "error";
            $json["message"] = "Error: swiftregion name already exists.";
            return $json;
        }
        $q = "INSERT INTO swiftregions (name) VALUES ('$safe_swiftregion_name')";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con) . " with query $q";
            return $json;
        }
        return $json;
        break;
    case "swiftregion_delete":
        # Validate swiftregion name
        $safe_swiftregion_name = safe_fqdn("name");
        if($safe_swiftregion_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: swiftregion name format.";
            return $json;
        }
        $q = "SELECT * FROM swiftregions WHERE name='$safe_swiftregion_name'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Error: swiftregion name not found.";
            return $json;
        }
        $r = mysqli_query($con, "DELETE FROM swiftregions WHERE name='$safe_swiftregion_name'");
        return $json;
        break;
    case "machine_reboot_on_hdd":
        # Validate serial
        $safe_machine_serial = safe_serial("serial");
        if($safe_machine_serial === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid chassis serial number.";
            return $json;
        }
        $r = mysqli_query($con, "SELECT * FROM machines WHERE serial='$safe_machine_serial'");
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Error: machine with serial $safe_machine_serial doesn't exist.";
            return $json;
        }
        $install_machine = mysqli_fetch_array($r);
        if($install_machine["status"] == "firstboot" || $install_machine["status"] == "installed"){
            $json["message"] = "Warning: machine already started on HDD.";
            return $json;
        }

        ipmi_set_boot_device($con, $conf, $install_machine["id"], "disk");

        # Fix status in db
        $r = mysqli_query($con, "UPDATE machines SET status='firstboot' WHERE serial='$safe_machine_serial'");
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        # Perform reboot
        $ret = send_ssh_cmd($conf, $con, $install_machine["ipaddr"], "shutdown -r now");
        return $json;
        break;
    case "machine_reboot_on_live":
        # Validate serial
        $safe_machine_serial = safe_serial("serial");
        if($safe_machine_serial === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid chassis serial number.";
            return $json;
        }
        $r = mysqli_query($con, "SELECT * FROM machines WHERE serial='$safe_machine_serial'");
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Error: machine with serial $safe_machine_serial doesn't exist.";
            return $json;
        }
        $install_machine = mysqli_fetch_array($r);
        if($install_machine["status"] == "live"){
            $json["message"] = "Warning: machine already started on Live image.";
            return $json;
        }

        ipmi_set_boot_device($con, $conf, $install_machine["id"], "pxe");

        # Fix status in db
        $q = "UPDATE machines SET status='bootinglive' WHERE serial='$safe_machine_serial'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $ret = send_ssh_cmd($conf, $con, $install_machine["ipaddr"], "shutdown -r now");
        return $json;
        break;
    case "machine_display_install_cmd":
        # Validate serial
        $safe_machine_serial = safe_serial("serial");
        if($safe_machine_serial === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid chassis serial number.";
            return $json;
        }
        $r = mysqli_query($con, "SELECT * FROM machines WHERE serial='$safe_machine_serial'");
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Error: machine with serial $safe_machine_serial doesn't exist.";
            return $json;
        }
        $install_machine = mysqli_fetch_array($r);
        $machine_id = $install_machine["id"];

        $slave_install_return = slave_install_server_os_command($con, $conf, $machine_id);
        if($slave_install_return["status"] != "success"){
            $json["status"] = "error";
            $json["message"] = "Error while calculating installation command line for host $safe_machine_serial: ".$slave_install_return["message"];
            return $json;
        }
        $json["data"] .= "Running: ". $slave_install_return["cmd"];
        return $json;
        break;
    case "machine_install_os":
        # Validate serial
        $safe_machine_serial = safe_serial("serial");
        if($safe_machine_serial === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid chassis serial number.";
            return $json;
        }
        $r = mysqli_query($con, "SELECT * FROM machines WHERE serial='$safe_machine_serial'");
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Error: machine with serial $safe_machine_serial doesn't exist.";
            return $json;
        }
        $install_machine = mysqli_fetch_array($r);
        $machine_id = $install_machine["id"];

        $slave_install_return = slave_install_server_os_command($con, $conf, $machine_id);
        if($slave_install_return["status"] != "success"){
            $json["status"] = "error";
            $json["message"] = "Error while calculating installation command line for host $safe_machine_serial: ".$slave_install_return["message"];
            return $json;
        }
        $json["data"] .= "Running: ". $slave_install_return["cmd"];
        slave_install_os($con, $conf, $machine_id, $slave_install_return["cmd"]);
        return $json;
        break;
    case "machine_install_log":
        # Validate serial
        $safe_machine_serial = safe_serial("serial");
        if($safe_machine_serial === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid chassis serial number.";
            return $json;
        }
        $r = mysqli_query($con, "SELECT * FROM machines WHERE serial='$safe_machine_serial'");
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Error: machine with serial $safe_machine_serial doesn't exist.";
            return $json;
        }
        $install_machine = mysqli_fetch_array($r);
        $machine_id = $install_machine["id"];
        $machine_ip = $install_machine["ipaddr"];
        $machine_status = $install_machine["status"];
        if($machine_status == "live"){
            $json["data"] = "Machine is on live, not installing...";
        }elseif($machine_status == "installing"){
            $json["data"] = send_ssh_cmd($conf, $con, $machine_ip, "tail -n 80 /var/log/oci.log");
        }elseif($machine_status == "installed"){
            $json["data"] = send_ssh_cmd($conf, $con, $machine_ip, "tail -n 80 /var/log/puppet-first-run");
        }else{
            $json["data"] = "Machine is booting...";
        }
        return $json;
        break;
    case "cluster_list":
        $q = "SELECT * FROM clusters";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        for($i=0;$i<$n;$i++){
            $a = mysqli_fetch_array($r);
            $json["data"][$i] = $a;
        }
        return $json;
        break;
    case "cluster_create":
        // Validate name
        $safe_cluster_name = safe_fqdn("name");
        if($safe_cluster_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid cluster name.";
            return $json;
        }
        $q = "SELECT * FROM clusters WHERE name='$safe_cluster_name'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 0){
            $json["status"] = "error";
            $json["message"] = "Error: cluster name already exists.";
            return $json;
        }

        // Validate domain
        $safe_domain_name = safe_fqdn("domain");
        if($safe_domain_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid domain name.";
            return $json;
        }
        $json = new_cluster($con, $conf, $safe_cluster_name, $safe_domain_name);
        return $json;
        break;
    case "cluster_delete":
        // Validate name
        $safe_cluster_name = safe_fqdn("name");
        if($safe_cluster_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid cluster name.";
            return $json;
        }
        $q = "SELECT * FROM clusters WHERE name='$safe_cluster_name'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Error: cluster name doesn't exist.";
            return $json;
        }

        return cluster_delete($con, $conf, $safe_cluster_name);
        break;
    case "cluster_show_networks":
        // Validate name
        $safe_cluster_name = safe_fqdn("cluster_name");
        if($safe_cluster_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid cluster name.";
            return $json;
        }
        $q = "SELECT * FROM clusters WHERE name='$safe_cluster_name'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Error: cluster name doesn't exist.";
            return $json;
        }
        $cluster = mysqli_fetch_array($r);
        $cluster_id = $cluster["id"];

        $q = "SELECT * FROM networks WHERE cluster='$cluster_id'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        for($i=0;$i<$n;$i++){
            $a = mysqli_fetch_array($r);
            $json["data"][$i] = $a;
        }
        return $json;
        break;
    case "cluster_show_machines":
        // Validate name
        $safe_cluster_name = safe_fqdn("cluster_name");
        if($safe_cluster_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid cluster name.";
            return $json;
        }
        $q = "SELECT * FROM clusters WHERE name='$safe_cluster_name'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Error: cluster name doesn't exist.";
            return $json;
        }
        $cluster = mysqli_fetch_array($r);
        $cluster_id = $cluster["id"];

        $q = "SELECT * FROM machines WHERE cluster='$cluster_id'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        for($i=0;$i<$n;$i++){
            $a = mysqli_fetch_array($r);
            $json["data"][$i] = $a;
        }
        return $json;
        break;
    case "network_list":
        $q = "SELECT networks.id AS id,networks.name AS name,networks.ip AS ip,networks.cidr AS cidr,networks.is_public AS is_public,networks.cluster AS cluster,networks.role AS role,networks.iface1 AS iface1,networks.iface2 AS iface2,networks.bridgename AS bridgename,networks.vlan AS vlan,networks.mtu AS mtu,locations.name AS location FROM networks,locations WHERE locations.id=networks.location_id";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        for($i=0;$i<$n;$i++){
            $a = mysqli_fetch_array($r);
            $json["data"][$i] = $a;
        }
        return $json;
        break;
    case "network_delete":
        # Validate network name
        $safe_network_name = safe_fqdn("name");
        if($safe_network_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: network name format.";
            return $json;
        }
        $q = "SELECT * FROM networks WHERE name='$safe_network_name'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Error: network name not found.";
            return $json;
        }
        $r = mysqli_query($con, "DELETE FROM networks WHERE name='$safe_network_name'");
        return $json;
        break;
    case "new_network":
        // Check cluster name
        $safe_network_name = safe_fqdn("name");
        if($safe_network_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid network name.";
            return $json;
        }
        $q = "SELECT * FROM networks WHERE name='$safe_network_name'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 0){
            $json["status"] = "error";
            $json["message"] = "Error: network name already exists.";
            return $json;
        }

        // Check location
        $safe_location_name = safe_fqdn("location");
        if($safe_location_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid location name.";
        }
        $q = "SELECT * FROM locations WHERE name='$safe_location_name'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Error: location name doesn't exist. $q";
            return $json;
        }
        $location = mysqli_fetch_array($r);
        $safe_location_id = $location["id"];

        // Check IP
        $safe_network_ip = safe_ipv4("ipaddr");
        if($safe_network_ip === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: wrong ipaddr param.";
            return $json;
        }

        // Check mask
        $safe_cidr_mask = safe_int("cidr_mask");
        if($safe_cidr_mask === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: wrong cidr param.";
            return $json;
        }
        if($safe_cidr_mask > 32 || $safe_cidr_mask < 8){
            $json["status"] = "error";
            $json["message"] = "Error: CIDR mask must be between 8 and 32.";
            return $json;
        }

        // Check is_public
        if(!isset($_REQUEST["is_public"])){
            $json["status"] = "error";
            $json["message"] = "Error: is_public not set.";
            return $json;
        }
        if($_REQUEST["is_public"] == "yes"){
            $safe_is_public = "yes";
        }else{
            $safe_is_public = "no";
        }
        $q = "INSERT INTO networks (name, location_id, ip, cidr, is_public) VALUES ('$safe_network_name', '$safe_location_id', '$safe_network_ip', '$safe_cidr_mask', '$safe_is_public')";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = "Query: $q error: ".mysqli_error($con);
            return $json;
        }
        return $json;
        break;
    case "network_set":
        // Check network name is valid and exists
        $safe_network_name = safe_fqdn("network_name");
        if($safe_network_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid network name.";
            return $json;
        }
        $q = "SELECT * FROM networks WHERE name='$safe_network_name'";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Cannot find network by that name.";
            return $json;
        }
        $network = mysqli_fetch_array($r);

        $update = "";

        if(isset($_REQUEST["role"])){
            switch($_REQUEST["role"]){
            case "all":
                $safe_role_name = "all";
                break;
            case "vm-net":
                $safe_role_name = "vm-net";
                break;
            case "ovs-bridge":
                $safe_role_name = "ovs-bridge";
                break;
            case "ceph-cluster":
                $safe_role_name = "ceph-cluster";
                break;
            default:
                $safe_role_name = safe_fqdn("role");
                if($safe_role_name === FALSE){
                    $json["status"] = "error";
                    $json["message"] = "Wrong role name format.";
                    return $json;
                }
                $q = "SELECT * FROM roles WHERE name='$safe_role_name'";
                $r = mysqli_query($con, $q);
                if($r === FALSE){
                    $json["status"] = "error";
                    $json["message"] = mysqli_error($con);
                    return $json;
                }
                $n = mysqli_num_rows($r);
                if($n != 1){
                    $json["status"] = "error";
                    $json["message"] = "Error: no role by that name.";
                    return $json;
                }
                break;
            }
            $update .= "role='$safe_role_name'";
        }
        if(isset($_REQUEST["iface1_name"])){
            $safe_eth1_name = safe_ethname("iface1_name");
            if($safe_eth1_name === FALSE){
                    $json["status"] = "error";
                    $json["message"] = "Wrong eth1 name format here.";
                    return $json;
            }
            if($update != ""){
                $update .= ", ";
            }
            $update .= "iface1='$safe_eth1_name'";
        }

        if(isset($_REQUEST["iface2_name"])){
            $safe_eth2_name = safe_ethname("iface2_name");
            if($safe_eth2_name === FALSE){
                    $json["status"] = "error";
                    $json["message"] = "Wrong eth2 name format.";
                    return $json;
            }
            if($update != ""){
                $update .= ", ";
            }
            $update .= "iface2='$safe_eth2_name'";
        }

        if(isset($_REQUEST["ip"])){
            $safe_ip = safe_ipv4("ip");
            if($safe_ip === FALSE){
                    $json["status"] = "error";
                    $json["message"] = "Wrong ip address format.";
                    return $json;
            }
            if($update != ""){
                $update .= ", ";
            }
            $update .= "ip='$safe_ip'";
        }

        if(isset($_REQUEST["cidr"])){
            $safe_cidr = safe_int("cidr");
            if($safe_cidr === FALSE || $safe_cidr < 0 || $safe_cidr > 32){
                    $json["status"] = "error";
                    $json["message"] = "Wrong cidr format.";
                    return $json;
            }
            if($update != ""){
                $update .= ", ";
            }
            $update .= "cidr='$safe_cidr'";
        }

        if(isset($_REQUEST["is_public"])){
            if($_REQUEST["is_public"] == "yes"){
                $ispublic = "yes";
            }else{
                $ispublic = "no";
            }
            if($update != ""){
                $update .= ", ";
            }
            $update .= "is_public='$ispublic'";
        }

        if(isset($_REQUEST["vlan"])){
            if($_REQUEST["vlan"] == "null" || $_REQUEST["vlan"] == "0"){
                if($update != ""){
                    $update .= ", ";
                }
                $update .= "vlan=null";
            }else{
                $safe_vlan = safe_int("vlan");
                if($safe_vlan === FALSE || $safe_vlan < 1 || $safe_vlan > 70000){
                        $json["status"] = "error";
                        $json["message"] = "Wrong vlan format.";
                        return $json;
                }
                if($update != ""){
                    $update .= ", ";
                }
                $update .= "vlan='$safe_vlan'";
            }
        }

        if(isset($_REQUEST["mtu"])){
            $safe_mtu = safe_int("mtu");
            if($safe_mtu === FALSE || $safe_mtu < 0 || $safe_mtu > 9999){
                $json["status"] = "error";
                $json["message"] = "Wrong mtu format.";
                return $json;
            }
            if($update != ""){
                $update .= ", ";
            }
            $update .= "mtu='$safe_mtu'";
        }

        if(isset($_REQUEST["location"])){
            $safe_location = safe_fqdn("location");
            if($safe_location === FALSE){
                $json["status"] = "error";
                $json["message"] = "Wrong location format.";
                return $json;
            }
            $q = "SELECT * FROM locations WHERE name='$safe_location'";
            $r = mysqli_query($con, $q);
            if($r === FALSE){
                $json["status"] = "error";
                $json["message"] = mysqli_error($con);
                return $json;
            }
            $n = mysqli_num_rows($r);
            if($n != 1){
                $json["status"] = "error";
                $json["message"] = "Error: location name not found.";
                return $json;
            }
            $location = mysqli_fetch_array($r);
            $location_id = $location["id"];
            if($update != ""){
                $update .= ", ";
            }
            $update .= "location_id='$location_id'";
        }
        if(isset($_REQUEST["bridgename"])){
            $safe_bridgename = safe_fqdn("bridgename");
            if($safe_bridgename === FALSE){
                $json["status"] = "error";
                $json["message"] = "Wrong bridge name format.";
                return $json;
            }
            if($update != ""){
                $update .= ", ";
            }
            $update .= "bridgename='$safe_bridgename'";
        }

        $q = "UPDATE networks SET $update WHERE name='$safe_network_name'";
        if($update != ""){
            $r = mysqli_query($con, $q);
        }
        return $json;
        break;
    case "network_add":
        // Check network name is valid and exists
        $safe_network_name = safe_fqdn("network_name");
        if($safe_network_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid network name.";
            return $json;
        }
        $q = "SELECT * FROM networks WHERE name='$safe_network_name'";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Cannot find network by that name.";
            return $json;
        }

        // Validate cluster name and fetch its id
        $safe_cluster_name = safe_fqdn("cluster_name");
        if($safe_cluster_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid cluster name.";
            return $json;
        }
        $q = "SELECT * FROM clusters WHERE name='$safe_cluster_name'";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Cannot find cluster by that name.";
            return $json;
        }
        $cluster = mysqli_fetch_array($r);
        $safe_cluster_id = $cluster["id"];

        // Check role name
        $safe_role_name = safe_fqdn("role_name");
        if($safe_role_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid role name.";
            return $json;
        }
        if($safe_role_name == "all" || $safe_role_name == "vm-net" || $safe_role_name == 'ovs-bridge' || $safe_role_name == 'ceph-cluster'){
            $sql_role = "'$safe_role_name'";
        }else{
            $r = mysqli_query($con, "SELECT id FROM roles WHERE name='$safe_role_name'");
            if($r === FALSE){
                $json["status"] = "error";
                $json["message"] = mysqli_error($con);
                return $json;
            }
            $n = mysqli_num_rows($r);
            if($n != 1){
                $json["status"] = "error";
                $json["message"] = "Error: role name $safe_role_name doesn't exist.";
                return $json;
            }
            $sql_role = "'$safe_role_name'";
        }


        // Check network card 1 param
        $safe_iface1 = safe_fqdn("iface1");
        if($safe_iface1 === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid iface 1 name.";
            return $json;
        }
        switch($safe_iface1){
        case "eth0":
        case "eth1":
        case "eth2":
        case "eth3":
        case "eth4":
        case "eth5":
        case "10m1":
        case "10m2":
        case "10m3":
        case "10m4":
        case "100m1":
        case "100m2":
        case "100m3":
        case "100m4":
        case "1g1":
        case "1g2":
        case "1g3":
        case "1g4":
        case "10g1":
        case "10g2":
        case "10g3":
        case "10g4":
            break;
        default:
            $json["status"] = "error";
            $json["message"] = "Error: not valid iface 1 name.";
            return $json;
        }

        // Check network card 2 param
        $safe_iface2 = safe_fqdn("iface2");
        if($safe_iface2 === FALSE){
            $json["status"] = "error";
            $json["message"] = "Error: not valid iface 2 name.";
            return $json;
        }
        switch($safe_iface2){
        case "none":
        case "eth0":
        case "eth1":
        case "eth2":
        case "eth3":
        case "eth4":
        case "eth5":
        case "10m1":
        case "10m2":
        case "10m3":
        case "10m4":
        case "100m1":
        case "100m2":
        case "100m3":
        case "100m4":
        case "1g1":
        case "1g2":
        case "1g3":
        case "1g4":
        case "10g1":
        case "10g2":
        case "10g3":
        case "10g4":
            break;
        default:
            $json["status"] = "error";
            $json["message"] = "Error: not valid iface 2 name.";
            return $json;
        }
        $q = "UPDATE networks SET cluster='$safe_cluster_id', role=$sql_role, iface1='$safe_iface1', iface2='$safe_iface2' WHERE name='$safe_network_name'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $q = "SELECT * FROM networks WHERE name='$safe_network_name'";
        $r = mysqli_query($con, $q);
        $network = mysqli_fetch_array($r);
        // If role is controller or all, then assign a VIP
        if(($network["role"] == "controller" || $network["role"] == "all") && ($network["is_public"] == "yes") ){
            $ret = reserve_ip_address($con, $conf, $network["id"], 0, "vip");
            if($ret["status"] != "success"){
                $json["status"] = "error";
                $json["message"] = $ret["message"];
                return $json;
            }
        }
        if($network["is_public"] == "no" && $network["role"] != "ovs-bridge"){
            $ret = reserve_ip_to_all_slaves_of_network($con, $conf, $safe_cluster_id, $network["id"], $safe_role_name);
            if($ret["status"] != "success"){
                $json["status"] = "error";
                $json["message"] = $ret["message"];
                return $json;
            }
        }
        return $json;
        break;
    case "network_remove":
        $safe_network_name = safe_fqdn("network_name");
        if($safe_network_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Network name in wrong format.";
            return $json;
        }
        $q = "SELECT * FROM networks WHERE name='$safe_network_name'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Cannot find network by that ID.";
            return $json;
        }
        $network = mysqli_fetch_array($r);
        $safe_network_id = $network["id"];

        $q = "UPDATE networks SET cluster=NULL, role=NULL WHERE id='$safe_network_id'";
        $r = mysqli_query($con, $q);
        $q = "DELETE FROM ips WHERE network='$safe_network_id'";
        $r = mysqli_query($con, $q);
        return $json;
        break;
    case "machine_set_ipmi":
        $safe_machine_serial = safe_serial("serial");
        if($safe_machine_serial === FALSE){
            $json["status"] = "error";
            $json["message"] = "Wrong machine serial number format.";
            return $json;
        }
        $q = "SELECT * FROM machines WHERE serial='$safe_machine_serial'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "No machine with serial $safe_machine_serial in database.";
            return $json;
        }
        $machine = mysqli_fetch_array($r);
        $safe_machine_id = $machine["id"];
        
        if(!isset($_REQUEST["ipmi_use"])){
            $json["status"] = "error";
            $json["message"] = "ipmi_use param not set.";
            return $json;
        }
        if($_REQUEST["ipmi_use"] != 'yes'){
            $q = "UPDATE machines SET ipmi_use='no' WHERE id='$safe_machine_id'";
            $r = mysqli_query($con, $q);
            return $json;
        }

        $safe_ipmi_addr = safe_fqdn("ipmi_addr");
        if($safe_ipmi_addr === FALSE){
            $safe_ipmi_addr = safe_ipv4("ipmi_addr");
            if($safe_ipmi_addr === FALSE){
                $json["status"] = "error";
                $json["message"] = "IPMI address in wrong format.";
                return $json;
            }
        }
        if(isset($_REQUEST["ipmi_default_gw"])){
            $safe_ipmi_default_gw = safe_ipv4("ipmi_default_gw");
            if($safe_ipmi_default_gw === FALSE){
                $json["status"] = "error";
                $json["message"] = "IPMI default gw in wrong format.";
                return $json;
            }
        }
        if(isset($_REQUEST["ipmi_netmask"])){
            $safe_ipmi_netmask = safe_ipv4("ipmi_netmask");
            if($safe_ipmi_netmask === FALSE){
                $json["status"] = "error";
                $json["message"] = "IPMI netmask in wrong format.";
                return $json;
            }
        }
        if(isset($_REQUEST["ipmi_call_chassis_bootdev"])){
            if($_REQUEST["ipmi_call_chassis_bootdev"] == "yes"){
                $safe_ipmi_call_chassis_bootdev = "yes";
            }else{
                $safe_ipmi_call_chassis_bootdev = "no";
            }
            $q = "UPDATE machines SET ipmi_call_chassis_bootdev='$safe_ipmi_call_chassis_bootdev' WHERE id='$safe_machine_id'";
            $r = mysqli_query($con, $q);
        }
        $safe_ipmi_port = safe_int("ipmi_port");
        if($safe_ipmi_port === FALSE){
            $out .= "IPMI port in wrong format.";
            return $out;
        }
        $safe_ipmi_username = safe_fqdn("ipmi_username");
        if($safe_ipmi_username === FALSE){
            $json["status"] = "error";
            $json["message"] = "IPMI username in wrong format.";
            return $json;
        }
        $safe_ipmi_password = safe_password("ipmi_password");
        if($safe_ipmi_password === FALSE){
            $json["status"] = "error";
            $json["message"] = "IPMI password in wrong format.";
            return $json;
        }
        if(isset($_REQUEST["perform_ipmitool_cmd"]) && $_REQUEST["perform_ipmitool_cmd"] == "yes"){
            if($machine["product_name"] == "CL2600 Gen10" || $machine["product_name"] == "CL2800 Gen10"){
                // What's below is proven to work with HP Cloud Line CL2600 and CL2800 machines
                // Set the username
                $cmd = "ipmitool user set name 3 " . $safe_ipmi_username;
                $json["message"] .= "\n$cmd";
                send_ssh_cmd($conf, $con, $machine["ipaddr"], $cmd);

                // Set the password
                $cmd = "ipmitool user set password 3 " . $safe_ipmi_password;
                $json["message"] .= "\n$cmd";
                send_ssh_cmd($conf, $con, $machine["ipaddr"], $cmd);

                // Enable the user
                $cmd = "ipmitool user enable 3";
                $json["message"] .= "\n$cmd";
                send_ssh_cmd($conf, $con, $machine["ipaddr"], $cmd);

                // Set user privileges for channel 1
                $cmd = "ipmitool priv 3 4 1";
                $json["message"] .= "\n$cmd";
                send_ssh_cmd($conf, $con, $machine["ipaddr"], $cmd);

                // Set user privileges for channel 8
                $cmd = "ipmitool priv 3 4 8";
                $json["message"] .= "\n$cmd";
                send_ssh_cmd($conf, $con, $machine["ipaddr"], $cmd);

                // Set user channel access for channel 1
                $cmd = "ipmitool channel setaccess 1 3 link=on ipmi=on callin=on privilege=4";
                $json["message"] .= "\n$cmd";
                send_ssh_cmd($conf, $con, $machine["ipaddr"], $cmd);

                // Set user channel access for channel 8
                $cmd = "ipmitool channel setaccess 8 3 link=on ipmi=on callin=on privilege=4";
                $json["message"] .= "\n$cmd";
                send_ssh_cmd($conf, $con, $machine["ipaddr"], $cmd);

// This may eventually be needed if we also want to change the default Administrator password
// ipmitool user set name 4 Administrator
// ipmitool user set password 4 XXXXXX
// ipmitool user enable 4
// ipmitool user priv 4 4 1
// ipmitool user priv 4 4 8
// ipmitool channel setaccess 1 4 link=on ipmi=on callin=on privilege=4
// ipmitool channel setaccess 8 4 link=on ipmi=on callin=on privilege=4

                // Set DHCP Off
                $cmd = "ipmitool lan set 1 ipsrc static";
                $json["message"] .= "\n$cmd";
                send_ssh_cmd($conf, $con, $machine["ipaddr"], $cmd);

                // Set the IP address
                $cmd = "ipmitool lan set 1 ipaddr " . $safe_ipmi_addr;
                $json["message"] .= "\n$cmd";
                send_ssh_cmd($conf, $con, $machine["ipaddr"], $cmd);

                // Set the default GW
                $cmd = "ipmitool lan set 1 defgw ipaddr " . $safe_ipmi_default_gw;
                $json["message"] .= "\n$cmd";
                send_ssh_cmd($conf, $con, $machine["ipaddr"], $cmd);
//
// ipmitool lan set 1 ipsrc static
// ipmitool lan set 1 ipaddr <IP-IPMI>
// ipmitool lan set 1 defgw ipaddr <IPMI-GW>
// ipmitool lan set 1 netmask 255.255.255.0
//
// ipmitool lan set 8 ipsrc static
// ipmitool lan set 8 ipaddr <IP-IPMI>
// ipmitool lan set 8 defgw ipaddr <IPMI-GW>
// ipmitool lan set 8 netmask 255.255.255.0
            }else{
                // What's below is proven to work with Dell r440 and r640 machines
                // Set the username
                $cmd = "ipmitool user set name 2 " . $safe_ipmi_username;
                $json["message"] .= "\n$cmd";
                send_ssh_cmd($conf, $con, $machine["ipaddr"], $cmd);

                // Set the password
                $cmd = "ipmitool user set password 2 " . $safe_ipmi_password;
                $json["message"] .= "\n$cmd";
                send_ssh_cmd($conf, $con, $machine["ipaddr"], $cmd);

                // Set DHCP off
                $cmd = "ipmitool lan set 1 ipsrc static";
                $json["message"] .= "\n$cmd";
                send_ssh_cmd($conf, $con, $machine["ipaddr"], $cmd);

                // Set the IP address
                $cmd = "ipmitool lan set 1 ipaddr " . $safe_ipmi_addr;
                $json["message"] .= "\n$cmd";
                send_ssh_cmd($conf, $con, $machine["ipaddr"], $cmd);

                // Set the default GW
                $cmd = "ipmitool lan set 1 defgw ipaddr " . $safe_ipmi_default_gw;
                $json["message"] .= "\n$cmd";
                send_ssh_cmd($conf, $con, $machine["ipaddr"], $cmd);

                // Set the default GW
                $cmd = "ipmitool lan set 1 netmask " . $safe_ipmi_netmask;
                $json["message"] .= "\n$cmd";
                send_ssh_cmd($conf, $con, $machine["ipaddr"], $cmd);

                $cmd = "ipmitool lan print 1";
                $json["message"] .= "\n$cmd";
                $out = send_ssh_cmd($conf, $con, $machine["ipaddr"], $cmd);
                $json["message"] .= "\n$out";
            }
        }

        $q = "UPDATE machines SET ipmi_use='yes', ipmi_addr='$safe_ipmi_addr', ipmi_port='$safe_ipmi_port', ipmi_username='$safe_ipmi_username', ipmi_password='$safe_ipmi_password' WHERE id='$safe_machine_id'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        return $json;
        break;
    case "ipmi_show_cmd_console":
        $safe_machine_serial = safe_serial("serial");
        if($safe_machine_serial === FALSE){
            $json["status"] = "error";
            $json["message"] = "Wrong machine serial number format.";
            return $json;
        }
        $q = "SELECT * FROM machines WHERE serial='$safe_machine_serial'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "No machine with serial $safe_machine_serial in database.";
            return $json;
        }
        $machine = mysqli_fetch_array($r);
        $cmd = "ipmitool -I lanplus -H " . $machine["ipmi_addr"] . " -p " . $machine["ipmi_port"] . " -U " . $machine["ipmi_username"] ." -P " . $machine["ipmi_password"] . " sol activate";
        $json["message"] .= "\n$cmd";
        return $json;
        break;
    case "ipmi_reboot_on_hdd":
        $safe_machine_serial = safe_serial("serial");
        if($safe_machine_serial === FALSE){
            $json["status"] = "error";
            $json["message"] = "Wrong machine serial number format.";
            return $json;
        }
        $q = "SELECT * FROM machines WHERE serial='$safe_machine_serial'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "No machine with serial $safe_machine_serial in database.";
            return $json;
        }
        $machine = mysqli_fetch_array($r);
        $machine_id    = $machine["id"];
        $ipmi_addr     = $machine["ipmi_addr"];
        $ipmi_port     = $machine["ipmi_port"];
        $ipmi_username = $machine["ipmi_username"];
        $ipmi_password = $machine["ipmi_password"];

        ipmi_set_boot_device($con, $conf, $machine_id, "disk");

        $r = mysqli_query($con, "UPDATE machines SET status='firstboot' WHERE serial='$safe_machine_serial'");

        $cmd = "ipmitool -I lanplus -H $ipmi_addr -p $ipmi_port -U $ipmi_username -P $ipmi_password power cycle";
        $output = "";
        $return_var = 0;
        exec($cmd, $output, $return_var);
        return $json;
        break;
    case "ipmi_reboot_on_live":
        $safe_machine_serial = safe_serial("serial");
        if($safe_machine_serial === FALSE){
            $json["status"] = "error";
            $json["message"] = "Wrong machine serial number format.";
            return $json;
        }
        $q = "SELECT * FROM machines WHERE serial='$safe_machine_serial'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "No machine with serial $safe_machine_serial in database.";
            return $json;
        }
        $machine = mysqli_fetch_array($r);
        $machine_id    = $machine["id"];
        $ipmi_addr     = $machine["ipmi_addr"];
        $ipmi_port     = $machine["ipmi_port"];
        $ipmi_username = $machine["ipmi_username"];
        $ipmi_password = $machine["ipmi_password"];

        ipmi_set_boot_device($con, $conf, $machine_id, "pxe");

        $r = mysqli_query($con, "UPDATE machines SET status='bootinglive' WHERE serial='$safe_machine_serial'");

        $cmd = "ipmitool -I lanplus -H $ipmi_addr -p $ipmi_port -U $ipmi_username -P $ipmi_password power cycle";
        $output = "";
        $return_var = 0;
        exec($cmd, $output, $return_var);
        return $json;
        break;
    case "swift_caculate_ring":
        $safe_cluster_name = safe_fqdn("cluster_name");
        if($safe_cluster_name === FALSE){
            $out .= "Wrong cluster_name format.";
            print($out);
            die();
        }
        $q = "SELECT * FROM clusters WHERE name='$safe_cluster_name'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "No cluster with name $safe_cluster_name in database.";
            return $json;
        }
        $cluster = mysqli_fetch_array($r);

        $json["data"] = build_swift_ring($con, $conf, $cluster["id"], "no");

        return $json;
        break;
    case "role_list":
        $r = mysqli_query($con, "SELECT * FROM roles");
        $n = mysqli_num_rows($r);
        $json["data"] = array();
        for($i=0;$i<$n;$i++){
            $a = mysqli_fetch_array($r);
            $json["data"][$i] = $a;
        }
        return $json;
        break;
    case "role_create":
        $safe_role_name = safe_fqdn("name");
        if($safe_role_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Wrong role name format.";
            return $json;
        }
        $q = "SELECT * FROM roles WHERE name='$safe_role_name'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 0){
            $json["status"] = "error";
            $json["message"] = "Error: role name already exists.";
            return $json;
        }
        $r = mysqli_query($con, "INSERT INTO roles (name) VALUES ('$safe_role_name')");
        return $json;
        break;
    case "role_delete":
        $safe_role_name = safe_fqdn("name");
        if($safe_role_name === FALSE){
            $json["status"] = "error";
            $json["message"] = "Wrong role name format.";
            return $json;
        }
        $q = "SELECT * FROM roles WHERE name='$safe_role_name'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n != 1){
            $json["status"] = "error";
            $json["message"] = "Error: no role by that name.";
            return $json;
        }
        $r = mysqli_query($con, "DELETE FROM roles WHERE name='$safe_role_name'");
        return $json;
        break;
    default:
        $json["status"] = "error";
        $json["message"] = "Error: no action by that name.";
        return $json;
        break;
    }
    
}

$out_json = api_actions($con,$conf);
print(json_encode($out_json) . "\n");
?>
