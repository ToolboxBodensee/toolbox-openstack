class oci::swiftproxy(
  $machine_hostname           = undef,
  $machine_ip                 = undef,
  $etc_hosts                  = undef,
  $time_server_host           = undef,
  $first_master               = undef,
  $first_master_ip            = undef,
  $vip_hostname               = undef,
  $vip_ipaddr                 = undef,
  $vip_netmask                = undef,
  $network_ipaddr             = undef,
  $network_cidr               = undef,
  $all_masters                = undef,
  $all_masters_ip             = undef,
  $all_swiftstore_ip          = undef,
  $all_swiftstore             = undef,
  $all_swiftproxy             = undef,
  $all_swiftproxy_ip          = undef,
  $swiftproxy_hostname        = 'none',
  $swiftregion_id             = undef,
  $statsd_hostname            = undef,
  $pass_swift_authtoken       = undef,
  $pass_swift_hashpathsuffix  = undef,
  $pass_swift_hashpathprefix  = undef,
  $swift_encryption_key_id    = undef,
  $max_containers_per_account = 0,
  $max_containers_whitelist   = undef,
  $use_ssl                    = true,
  $swift_disable_encryption   = undef,
){

  if $use_ssl {
    $proto = 'https'
    $api_port = 443
  } else {
    $proto = 'http'
    $api_port = 80
  }

  $base_url = "${proto}://${vip_hostname}"
  $keystone_auth_uri  = "${base_url}/identity"
  $keystone_admin_uri = "${base_url}/identity-admin"
  $memcached_servers  = ["127.0.0.1:11211"]

  ::oci::sysctl { 'oci-rox': }

  class { '::oci::etchosts': etc_hosts_file => $etc_hosts, }

  # Right on time!
  class { '::oci::chrony': time_server_host => $time_server_host, }

  package { 'python-keystonemiddleware':
    ensure => present,
    notify => Service['swift-proxy'],
  }

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

  # Get memcached
  class { '::memcached':
    listen_ip => $machine_ip,
    udp_port  => 0,
  }

  if $swiftproxy_hostname == "none" {
    debug("OCI will *NOT* setup a swiftproxy direct access.")
  } else {
    debug("OCI now setting-up a swiftproxy direct access.")
    if $use_ssl {
      file { "/etc/haproxy/ssl":
        ensure                  => directory,
        owner                   => 'root',
        mode                    => '0755',
        selinux_ignore_defaults => true,
        require       => Package['haproxy'],
      }->
      file { "/etc/haproxy/ssl/private":
        ensure                  => directory,
        owner                   => 'root',
        mode                    => '0755',
        selinux_ignore_defaults => true,
      }->
      file { "/etc/haproxy/ssl/private/oci-pki-swiftproxy.pem":
        ensure                  => present,
        owner                   => "haproxy",
        source                  => "/etc/ssl/private/oci-pki-swiftproxy.pem",
        selinux_ignore_defaults => true,
        mode                    => '0600',
      }
    }

    class { 'haproxy':
      global_options   => {
        'maxconn' => '40960',
        'user'    => 'haproxy',
        'group'   => 'haproxy',
        'daemon'  => '',
        'nbproc'  => '4',
        'ssl-default-bind-ciphers'   => 'ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-SHA384:ECDHE-RSA-AES256-SHA384:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA256',
        'ssl-default-bind-options'   => 'no-sslv3 no-tlsv10 no-tlsv11 no-tls-tickets',
        'ssl-default-server-ciphers' => 'ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-SHA384:ECDHE-RSA-AES256-SHA384:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA256',
        'ssl-default-server-options' => 'no-sslv3 no-tlsv10 no-tlsv11 no-tls-tickets',
      },
      defaults_options => {
        'mode'    => 'http',
      },
      merge_options => true,
    }

    # Global frontend for all services with dispatch depending on the URL
    haproxy::frontend { 'openstackfe':
      mode      => 'http',
      bind      => { "0.0.0.0:${api_port}" => ['ssl', 'crt', '/etc/haproxy/ssl/private/oci-pki-swiftproxy.pem'] },
      options   => [
        { 'acl'         => 'url_swift path_beg -i /object'},
        { 'use_backend' => 'swiftbe if url_swift'},
        { 'use_backend' => 'swiftbe_always'},
        # Set HSTS (63072000 seconds = 2 years)
        { 'http-response' => 'set-header Strict-Transport-Security max-age=63072000'},
      ]
    }

    # This backend + backend-member is for the /object version, as
    # advertized in our endpoints.
    haproxy::backend { 'swiftbe':
      options => [
         { 'option' => 'httpchk GET /healthcheck' },
         { 'option' => 'forwardfor' },
         { 'mode' => 'http' },
         { 'balance' => 'source' },
         { 'reqrep'  => '^([^\ ]*\ /)object[/]?(.*)     \1\2'},
      ],
    }

    haproxy::balancermember { 'swiftbm':
      listening_service => 'swiftbe',
      ipaddresses       => [ $machine_ip ],
      server_names      => [ $machine_hostname ],
      ports             => 8080,
      options           => 'check',
    }

    # This backend + backend-member is for without the /object version, as
    # per what will S3 clients use.
    haproxy::backend { 'swiftbe_always':
      options => [
         { 'option' => 'httpchk GET /healthcheck' },
         { 'option' => 'forwardfor' },
         { 'mode' => 'http' },
         { 'balance' => 'source' },
      ],
    }

    haproxy::balancermember { 'swiftbm_always':
      listening_service => 'swiftbe_always',
      ipaddresses       => [ $machine_ip ],
      server_names      => [ $machine_hostname ],
      ports             => 8080,
      options           => 'check',
    }
  }


  class { '::swift':
    swift_hash_path_suffix => $pass_swift_hashpathsuffix,
    swift_hash_path_prefix => $pass_swift_hashpathprefix,
  }

  class { '::swift::proxy::s3api': }
  class { 'swift::proxy::s3token':
    auth_uri => "${keystone_auth_uri}/v3",
  }

  # Because there's no ca_file option in castellan, we must
  # allow swiftproxy to run without encryption  in case we're
  # running on a PoC without a real certificate for the API
  if($swift_disable_encryption =='yes' or $swift_encryption_key_id == ''){
    $disable_encryption = true
  }
  $pipeline_start = [ 'catch_errors', 'healthcheck', 'proxy-logging', 'cache', 'container_sync', 'bulk', 'ratelimit', 'authtoken', 's3api', 's3token', 'keystone', 'copy', 'container-quotas', 'account-quotas', 'slo', 'dlo', 'versioned_writes' ]
  if $swift_encryption_key_id == "" {
    $pipeline_kms = $pipeline_start
  } else {
    $pipeline_kms = concat($pipeline_start, [ 'kms_keymaster', 'encryption' ])
  }
  $pipeline = concat($pipeline_kms, [ 'proxy-logging', 'proxy-server' ])

  package { 'barbicanclient':
    name   => 'python-barbicanclient',
    ensure => latest,
  }->
  package { 'castellan':
    name   => 'python-castellan',
    ensure => latest,
  }->
  class { '::swift::proxy':
    proxy_local_net_ip         => $machine_ip,
    port                       => '8080',
    account_autocreate         => true,
    pipeline                   => $pipeline,
    node_timeout               => 30,
    read_affinity              => "r${swiftregion_id}=100",
    write_affinity             => "r${swiftregion_id}",
    max_containers_per_account => $max_containers_per_account,
    max_containers_whitelist   => $max_containers_whitelist,
    workers                    => 20,
  }
  swift_proxy_config {
    'app:proxy-server/conn_timeout': value => '2';
  }
  include ::swift::proxy::catch_errors
  include ::swift::proxy::healthcheck
  include ::swift::proxy::kms_keymaster
  class { '::swift::proxy::encryption':
    disable_encryption => $disable_encryption,
  }
  class { '::swift::proxy::cache':
    memcache_servers => ["${::fqdn}:11211"],
  }
  include ::swift::proxy::encryption
  include ::swift::proxy::proxy_logging
  include ::swift::proxy::container_sync
  include ::swift::proxy::bulk
  include ::swift::proxy::ratelimit
  include ::swift::proxy::keystone
  include ::swift::proxy::copy
  include ::swift::proxy::container_quotas
  include ::swift::proxy::account_quotas
  include ::swift::proxy::slo
  include ::swift::proxy::dlo
  include ::swift::proxy::versioned_writes

  class { '::swift::proxy::authtoken':
    auth_uri                => "${keystone_auth_uri}/v3",
    auth_url                => $keystone_admin_uri,
    password                => $pass_swift_authtoken,
    delay_auth_decision     => 'True',
    cache                   => 'swift.cache',
    include_service_catalog => 'False',
  }

  if $statsd_hostname == ''{
    swift_proxy_config {
      'filter:authtoken/cafile': value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
    }
  } else {
    swift_proxy_config {
      'filter:authtoken/cafile':          value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'DEFAULT/log_statsd_host':          value => $statsd_hostname;
      'DEFAULT/log_statsd_metric_prefix': value => $machine_hostname;
    }
  }

  class { '::swift::keymaster':
    api_class     => 'barbican',
    key_id        => $swift_encryption_key_id,
    password      => $pass_swift_authtoken,
    auth_endpoint => "${keystone_auth_uri}/v3",
    project_name  => 'services',
  }
}
