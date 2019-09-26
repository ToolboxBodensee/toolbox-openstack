class oci::chrony(
	$time_server_host = "0.debian.pool.ntp.org"
){
  class { '::chrony':
    servers          => $time_server_host,
    makestep_seconds => '120',
    makestep_updates => '-1',
    log_options      => 'tracking measurements statistics',
  }
}