class oci::swiftstore(
  $machine_hostname          = undef,
  $machine_ip                = undef,
  $etc_hosts                 = undef,
  $time_server_host          = undef,
  $network_ipaddr            = undef,
  $network_cidr              = undef,
  $block_devices             = undef,
  $statsd_hostname           = undef,
  $pass_swift_hashpathsuffix = undef,
  $pass_swift_hashpathprefix = undef,
  $zoneid                    = undef,
  $use_ssl                   = true,
  $all_swiftstore_ip         = undef,
  $all_swiftstore            = undef,
  $all_swiftproxy            = undef,
  $all_swiftproxy_ip         = undef,
){

  ::oci::sysctl { 'oci-rox': }

  class { '::oci::etchosts': etc_hosts_file => $etc_hosts, }

  # Right on time!
  class { '::oci::chrony': time_server_host => $time_server_host, }

  # rsyslog correctly
  package { 'rsyslog':
    ensure => present,
  }
  service { 'rsyslog':
    ensure  => running,
    enable  => true,
    require => Package['rsyslog'],
  }

  file { '/var/log/swift':
    ensure => directory,
    mode   => '0750',
  }

  file { '/etc/rsyslog.d/10-swift.conf':
    ensure  => present,
    source  => "puppet:///modules/${module_name}/rsyslog-swift.conf",
    require => [Package['rsyslog'], File['/var/log/swift']],
    notify  => Service['rsyslog'],
  }

  File<| title == '/var/log/swift' |> {
    owner => 'swift',
    group => 'adm'
  }

  # Install memcache
  class { '::memcached':
    listen_ip => '127.0.0.1',
    udp_port  => 0,
  }

  # Fireall object, account and container servers,
  # so that only our management network has access to it.
  # First, general definirion (could be in global site.pp)
  resources { "firewall":
    purge   => true
  }
  class { 'firewall': }

  $all_allowed_ips = concat($all_swiftproxy_ip, $all_swiftstore_ip)

  $all_allowed_ips.each |Integer $index, String $value| {
    $val1 = $index*2+100
    $val2 = $index*2+101
    firewall { "${val1} Allow ${value} to access to swift container and account servers":
      proto       => tcp,
      action      => accept,
      source      => "${value}",
      dport       => '6001-6002',
    }->
    firewall { "${val2} Allow ${value} to access to swift object servers":
      proto       => tcp,
      action      => accept,
      source      => "${value}",
      dport       => '6200-6229',
    }
  }

  firewall { '801 Jump to LOGDROP for container and account servers':
    proto       => tcp,
    jump        => 'LOGDROP',
    dport       => '6001-6002',
  }

  firewall { '801 Jump to LOGDROP for object servers':
    proto       => tcp,
    jump        => 'LOGDROP',
    dport       => '6200-6229',
  }

  firewallchain { 'LOGDROP:filter:IPv4':
    ensure  => present,
  }

  firewall { '901 LOG rule for dropped packets':
    chain       => 'LOGDROP',
    proto       => tcp,
    jump        => 'LOG',
    log_level   => '6',
    log_prefix  => 'swift dropped packet',
    limit       => '1/sec',
  }
  firewall { "902 Deny all access to container and account server":
    chain       => 'LOGDROP',
    proto       => tcp,
    action      => drop,
    dport       => '6001-6002',
  }
  firewall { "903 Deny all access to object server":
    chain       => 'LOGDROP',
    proto       => tcp,
    action      => drop,
    dport       => '6200-6229',
  }

  class { 'swift':
    swift_hash_path_suffix => $pass_swift_hashpathsuffix,
    swift_hash_path_prefix => $pass_swift_hashpathprefix,
  }

  class { '::swift::storage':
    storage_local_net_ip => $machine_ip,
  }
  include swift::storage::container
  include swift::storage::account
  include swift::storage::object

  if $statsd_hostname == ''{
    $statsd_enabled = false
  } else {
    $statsd_enabled = true
  }

  swift::storage::server { '6002':
    type                 => 'account',
    devices              => '/srv/node',
    config_file_path     => 'account-server.conf',
    storage_local_net_ip => "${machine_ip}",
    pipeline             => ['healthcheck', 'recon', 'account-server'],
    statsd_enabled           => $statsd_enabled,
    log_statsd_host          => $statsd_hostname,
    log_statsd_metric_prefix => $machine_hostname,
  }

  swift::storage::server { '6001':
    type                 => 'container',
    devices              => '/srv/node',
    config_file_path     => 'container-server.conf',
    storage_local_net_ip => "${machine_ip}",
    pipeline             => ['healthcheck', 'recon', 'container-server'],
    statsd_enabled           => $statsd_enabled,
    log_statsd_host          => $statsd_hostname,
    log_statsd_metric_prefix => $machine_hostname,
  }

  swift::storage::server { '6200':
    type                   => 'object',
    devices                => '/srv/node',
    config_file_path       => 'object-server.conf',
    storage_local_net_ip   => "${machine_ip}",
    pipeline               => ['healthcheck', 'recon', 'object-server'],
    servers_per_port       => 1,
    replicator_concurrency => 10,
    statsd_enabled           => $statsd_enabled,
    log_statsd_host          => $statsd_hostname,
    log_statsd_metric_prefix => $machine_hostname,
  }

  $block_devices.each |Integer $index, String $value| {
    swift::storage::disk { "${value}":
      mount_type => 'uuid',
      require    => Class['swift'],
    }->
    exec { "fix-unix-right-of-${value}":
      path    => "/bin",
      command => "chown swift:swift /srv/node/${value}",
      unless  => "cat /proc/mounts | grep -E ^/dev/${value}",
    }
  }

  $rings = [
    'account',
    'object',
    'container']
  swift::storage::filter::recon { $rings: }
  swift::storage::filter::healthcheck { $rings: }

  Swift::Ringsync<<||>>
}
