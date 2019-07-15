<?php

function send_ssh_cmd($conf, $con, $ip_addr, $command){
	if($conf["ssh"]["use_php_ssh2"] == "yes" || $conf["ssh"]["use_php_ssh2"] == '1'){
		$ssh = ssh2_connect($ip_addr, 22);
		if($ssh === FALSE){
			return FALSE;
		}
		$ret = ssh2_auth_pubkey_file($ssh, "root", "/etc/openstack-cluster-installer/id_rsa.pub", "/etc/openstack-cluster-installer/id_rsa");
		if($ret === FALSE){
			return FALSE;
		}
		$stream = ssh2_exec($ssh, $command);
		if($stream === FALSE){
			return FALSE;
		}
		stream_set_blocking($stream, true);
		$data = "";
		while ($buf = fread($stream, 4096)) {
			$data .= $buf;
		}
		fclose($stream);
		ssh2_disconnect($ssh);
		return $data;
	}else{
		$cmd = "ssh -o \"StrictHostKeyChecking no\" -i /etc/openstack-cluster-installer/id_rsa root@$ip_addr \"$command\"";
		$output = array();
		$return_var = 0;
		exec($cmd, $output, $return_var);
		return implode($output, "\n");
	}
}

function scp_a_file($conf, $con, $ip_addr, $local_file, $remote_dest, $mode){
	if($conf["ssh"]["use_php_ssh2"] == "yes" || $conf["ssh"]["use_php_ssh2"] == '1'){
		$ssh = ssh2_connect($ip_addr, 22);
		ssh2_auth_pubkey_file($ssh, "root", "/etc/openstack-cluster-installer/id_rsa.pub", "/etc/openstack-cluster-installer/id_rsa");
		ssh2_scp_send($ssh, $local_file, $remote_dest, $mode);
		ssh2_disconnect($ssh);
	}else{
		$cmd = "scp -o \"StrictHostKeyChecking no\" -i /etc/openstack-cluster-installer/id_rsa $local_file root@$ip_addr:$remote_dest";
		$output = array();
		$return_var = 0;
		exec($cmd, $output, $return_var);
	}
}

?>