# a controller node through the OCI's ENC:
#
#---
#classes:
#   oci_controller:
#      is_first_master: true
#      first_master: zigo-controller-node-3.infomaniak.ch
#      first_master_ip: 192.168.100.40
#      vip_hostname: zigo-api.infomaniak.ch
#      vip_ipaddr: 192.168.101.2
#      network_ipaddr: 192.168.101.0
#      network_cidr: 24
#      other_masters:
#         - zigo-controller-node-4.infomaniak.ch
#         - zigo-controller-node-5.infomaniak.ch
#      other_masters_ip:
#         - 192.168.100.41
#         - 192.168.100.32
#      all_masters:
#         - zigo-controller-node-3.infomaniak.ch
#         - zigo-controller-node-4.infomaniak.ch
#         - zigo-controller-node-5.infomaniak.ch
#      all_masters_ip:
#         - 192.168.100.40
#         - 192.168.100.41
#         - 192.168.100.32
#
# This is re-used in the oci_controller class below
#
class oci::controller(
  $openstack_release        = undef,
  $cluster_name             = undef,
  $machine_hostname         = undef,
  $machine_ip               = undef,
  $etc_hosts                = undef,
  $time_server_host         = undef,
  $bridge_mapping_list      = undef,
  $external_network_list    = undef,
  $machine_iface            = undef,
  $compute_nodes            = undef,
  $compute_nodes_ip         = undef,
  $volume_nodes             = undef,
  $volume_nodes_ip          = undef,
  $vmnet_ip                 = undef,
  $vmnet_mtu                = undef,
  $is_first_master          = false,
  $first_master             = undef,
  $first_master_ip          = undef,
  $other_masters            = undef,
  $other_masters_ip         = undef,
  $vip_hostname             = undef,
  $vip_ipaddr               = undef,
  $vip_netmask              = undef,
  $swiftproxy_hostname      = 'none',
  $network_ipaddr           = undef,
  $network_cidr             = undef,
  $all_masters              = undef,
  $all_masters_ip           = undef,
  $all_masters_ids          = undef,

  $has_subrole_db           = true,

  $first_sql                = undef,
  $first_sql_ip             = undef,
  $is_first_sql             = undef,
  $sql_vip_ip               = undef,
  $sql_vip_netmask          = undef,
  $sql_vip_iface            = undef,

  $amp_secgroup_list        = undef,
  $amp_boot_network_list    = undef,

  $all_sql                  = undef,
  $all_sql_ip               = undef,
  $all_sql_ids              = undef,
  $non_master_sql           = undef,
  $non_master_sql_ip        = undef,

  $has_subrole_messaging    = true,
  $has_subrole_api_keystone = true,
  $has_subrole_heat         = true,
  $has_subrole_glance       = false,
  $has_subrole_nova         = false,
  $has_subrole_neutron      = false,
  $has_subrole_swift        = true,
  $has_subrole_horizon      = true,
  $has_subrole_barbican     = true,
  $has_subrole_cinder       = false,
  $has_subrole_gnocchi      = false,
  $has_subrole_ceilometer   = false,
  $has_subrole_panko        = false,
  $has_subrole_cloudkitty   = false,
  $has_subrole_aodh         = false,
  $has_subrole_octavia      = false,
  $has_subrole_magnum       = false,

  $glance_backend           = undef,
  $pass_glance_onswift      = undef,

  $cinder_backup_backend    = 'none',

  $haproxy_custom_url       = undef,
  $use_ssl                  = true,
  $rabbit_env               = {},
  $all_swiftproxy           = undef,
  $all_swiftproxy_ip        = undef,
  $pass_mysql_rootuser      = undef,
  $pass_mysql_backup        = undef,
  $pass_rabbitmq_cookie     = undef,
  $pass_keystone_db         = undef,
  $pass_keystone_messaging  = undef,
  $pass_keystone_adminuser  = undef,
  $pass_keystone_credkey1   = undef,
  $pass_keystone_credkey2   = undef,
  $pass_keystone_fernkey1   = undef,
  $pass_keystone_fernkey2   = undef,
  $pass_nova_db             = undef,
  $pass_nova_apidb          = undef,
  $pass_nova_messaging      = undef,
  $pass_nova_authtoken      = undef,
  $pass_metadata_proxy_shared_secret = undef,
  $pass_placement_db        = undef,
  $pass_placement_authtoken = undef,
  $pass_glance_db           = undef,
  $pass_glance_messaging    = undef,
  $pass_glance_authtoken    = undef,
  $pass_cinder_db           = undef,
  $pass_cinder_messaging    = undef,
  $pass_cinder_authtoken    = undef,
  $pass_neutron_db          = undef,
  $pass_neutron_messaging   = undef,
  $pass_neutron_authtoken   = undef,
  $pass_heat_encryptkey     = undef,
  $pass_heat_db             = undef,
  $pass_heat_messaging      = undef,
  $pass_heat_authtoken      = undef,
  $pass_heat_keystone_domain = undef,
  $pass_swift_authtoken     = undef,
  $pass_swift_encryption    = undef,
  $pass_horizon_secretkey   = undef,
  $pass_barbican_db         = undef,
  $pass_barbican_messaging  = undef,
  $pass_barbican_authtoken  = undef,
  $pass_gnocchi_db          = undef,
  $pass_gnocchi_messaging   = undef,
  $pass_gnocchi_authtoken   = undef,
  $pass_gnocchi_rscuuid     = undef,
  $pass_panko_db            = undef,
  $pass_panko_messaging     = undef,
  $pass_panko_authtoken     = undef,
  $pass_ceilometer_db        = undef,
  $pass_ceilometer_messaging = undef,
  $pass_ceilometer_authtoken = undef,
  $pass_ceilometer_telemetry = undef,
  $pass_cloudkitty_db       = undef,
  $pass_cloudkitty_messaging = undef,
  $pass_cloudkitty_authtoken = undef,
  $pass_redis               = undef,
  $pass_aodh_db             = undef,
  $pass_aodh_messaging      = undef,
  $pass_aodh_authtoken      = undef,
  $pass_octavia_db          = undef,
  $pass_octavia_messaging   = undef,
  $pass_octavia_authtoken   = undef,
  $pass_octavia_heatbeatkey = undef,
  $pass_magnum_db           = undef,
  $pass_magnum_messaging    = undef,
  $pass_magnum_authtoken    = undef,
  $pass_magnum_domain       = undef,

  $cluster_has_cinder_volumes = false,

  $cluster_has_osds         = false,
  $cluster_has_mons         = false,

  $ceph_fsid                = undef,
  $ceph_bootstrap_osd_key   = undef,
  $ceph_admin_key           = undef,
  $ceph_openstack_key       = undef,
  $ceph_mon_key             = undef,
  $ceph_mon_initial_members = undef,
  $ceph_mon_host            = undef,
  $ceph_mgr_key             = undef,
  $ceph_libvirtuuid         = undef,

  $disable_notifications    = false,
  $enable_monitoring_graphs = false,
  $monitoring_graphite_host = undef,
  $monitoring_graphite_port = '2003',
){

  if $use_ssl {
    $proto = 'https'
    $messaging_default_port = '5671'
    $messaging_notify_port = '5671'
    $api_port = 443
  } else {
    $proto = 'http'
    $messaging_default_port = '5672'
    $messaging_notify_port = '5672'
    $api_port = 80
  }
  $messaging_default_proto = 'rabbit'
  $messaging_notify_proto = 'rabbit'

  if $has_subrole_db {
    $sql_host = $vip_ipaddr
  }else{
    $sql_host = $sql_vip_ip
  }

  $base_url = "${proto}://${vip_hostname}"
  $keystone_auth_uri  = "${base_url}:${api_port}/identity"
  $keystone_admin_uri = "${base_url}:${api_port}/identity"
  #$memcached_servers  = ["127.0.0.1:11211"]
  $memcached_string = join([join($all_masters_ip,':11211,'), ':11211'],'')
  $memcached_servers  = ["${memcached_string}"]

    ensure_resource('file', '/root/oci-openrc', {
      'ensure'  => 'present',
      'content' => "
export OS_AUTH_TYPE=password
export OS_PROJECT_DOMAIN_NAME='default'
export OS_USER_DOMAIN_NAME='default'
export OS_PROJECT_NAME='admin'
export OS_USERNAME='admin'
export OS_PASSWORD='${pass_keystone_adminuser}'
export OS_AUTH_URL='https://${vip_hostname}/identity/v3'
export OS_IDENTITY_API_VERSION=3
export OS_IMAGE_API_VERSION=2
export OS_CACERT=/etc/ssl/certs/oci-pki-oci-ca-chain.pem
",
    })

    ensure_resource('file', '/root/octavia-openrc', {
      'ensure'  => 'present',
      'content' => "
export OS_AUTH_TYPE=password
export OS_PROJECT_DOMAIN_NAME='default'
export OS_USER_DOMAIN_NAME='default'
export OS_PROJECT_NAME='services'
export OS_USERNAME='octavia'
export OS_PASSWORD='${pass_octavia_authtoken}'
export OS_AUTH_URL='https://${vip_hostname}/identity/v3'
export OS_IDENTITY_API_VERSION=3
export OS_IMAGE_API_VERSION=2
export OS_CACERT=/etc/ssl/certs/oci-pki-oci-ca-chain.pem
",
    })

  if $vip_netmask == 32 {
    $vip_iface = 'lo'
  } else {
    $vip_iface = $machine_iface
  }

  $haproxy_api_backend_options = 'check-ssl ssl verify required ca-file /etc/ssl/certs/oci-pki-oci-ca-chain.pem'
  $haproxy_api_backend_options_noverify = 'check-ssl ssl verify none'

  ################################
  ### Add a virtual IP address ###
  ################################
  # Setup corosync
  class { 'corosync':
    authkey                  => '/var/lib/puppet/ssl/certs/ca.pem',
    bind_address             => $machine_ip,
    unicast_addresses        => $all_masters_ip,
    cluster_name             => 'mycluster',
    enable_secauth           => true,
    set_votequorum           => true,
    quorum_members           => $all_masters_ip,
    quorum_members_ids       => $all_masters_ids,
    log_stderr               => false,
    log_function_name        => true,
    syslog_priority          => 'debug',
    debug                    => true,
  }
  corosync::service { 'pacemaker':
    version => '0',
  }->
  cs_property { 'stonith-enabled':
    value   => 'false',
  }->
  cs_property { 'no-quorum-policy':
    value   => 'stop',
  }->
  cs_primitive { 'openstack-api-vip':
    primitive_class => 'ocf',
    primitive_type  => 'IPaddr2',
    provided_by     => 'heartbeat',
    parameters      => { 'ip' => $vip_ipaddr, 'cidr_netmask' => $vip_netmask, 'nic' => $vip_iface },
    operations      => { 'monitor' => { 'interval' => '10s' } },
    before          => Anchor['keystone::service::begin'],
  }
  # Eventually, a VIP for MariaDB
#  if $has_subrole_db {
#    oci::vip {'openstack-sql-vip':
#      vip_ip      => $sql_vip_ip,
#      vip_netmask => $sql_vip_netmask,
#      vip_iface   => $vip_sql_iface,
#    }
#  }

  #########################
  ### Setup firewalling ###
  #########################
  # Note: This only closes ports on the VIP, as everything else
  # is not accessible from the outside anyway.

  # Drop all services on public IP address, just let https APIs

  # First, general definirion (could be in global site.pp)
  resources { "firewall":
    purge   => true
  }
  class { 'firewall': }

  if $volume_nodes_ip {
    $all_sql_allowed_ips = concat($compute_nodes_ip, $volume_nodes_ip)
  }else{
    $all_sql_allowed_ips = $compute_nodes_ip
  }
  $all_sql_allowed_ips2 = concat($all_masters_ip,$all_sql_allowed_ips)
  $all_sql_allowed_ips3 = concat(['127.0.0.0/8'],$all_sql_allowed_ips2)
  $all_sql_allowed_ips4 = concat(["${vip_ipaddr}/${vip_netmask}"],$all_sql_allowed_ips2)

  $all_sql_allowed_ips4.each |Integer $index, String $value| {
    $val1 = $index+100
    firewall { "${val1} Allow ${value} to access MySQL VIP":
      proto       => tcp,
      action      => accept,
      source      => "${value}",
      dport       => [3306, 4567],
    }
  }

  # Define firewall rules
  firewall { '801 deny public access to http':
    proto       => tcp,
    action      => drop,
    destination => "${vip_ipaddr}/${vip_netmask}",
    dport       => 80,
  }->
  firewall { '802 deny public access to mysql':
    proto       => tcp,
    action      => drop,
    destination => "${vip_ipaddr}/${vip_netmask}",
    dport       => [3306, 4567],
  }->
  firewall { '803 deny public access to bgpd':
    proto       => tcp,
    action      => drop,
    destination => "${vip_ipaddr}/${vip_netmask}",
    dport       => 179,
  }->
  firewall { '804 deny public access to epmd and rabbitmq':
    proto       => tcp,
    action      => drop,
    destination => "${vip_ipaddr}/${vip_netmask}",
    dport       => [4369, 5671, 5672, 15671, 25672],
  }->
  firewall { '805 deny public access to rpcbind':
    proto       => tcp,
    action      => drop,
    destination => "${vip_ipaddr}/${vip_netmask}",
    dport       => [111],
  }->
  firewall { '806 deny public access to xinetd':
    proto       => tcp,
    action      => drop,
    destination => "${vip_ipaddr}/${vip_netmask}",
    dport       => [9200],
  }->
  firewall { '807 deny public access to horizon without haproxy':
    proto       => tcp,
    action      => drop,
    destination => "${vip_ipaddr}/${vip_netmask}",
    dport       => [7080, 7443],
  }->
  firewall { '808 deny public access to octavia API without haproxy':
    proto       => tcp,
    action      => drop,
    destination => "${vip_ipaddr}/${vip_netmask}",
    dport       => [9876],
  }->
  firewall { '809 deny public access to magnum API without haproxy':
    proto       => tcp,
    action      => drop,
    destination => "${vip_ipaddr}/${vip_netmask}",
    dport       => [9511],
  }

  ####################################
  ### Setup a Zookeeper in cluster ###
  ####################################
  # needed for Telemetry / metric / rating
  if $has_subrole_gnocchi {
    $all_masters_ip.each |Integer $index, String $value| {
      if($machine_ip == $value){
        class { 'zookeeper':
          id        => String($index + 1),
          client_ip => $machine_ip,
          servers   => $all_masters_ip,
          purge_interval => 6,
        }
      }
    }
  }

  ::oci::sysctl { 'oci-rox': }

  class { '::oci::etchosts': etc_hosts_file => $etc_hosts, }

  # Right on time!
  class { '::oci::chrony': time_server_host => $time_server_host, }

  # Add haproxy that will listen on the virtual IPs, and load balance
  # to the different API daemons using tcp mode (as the APIs will do
  # full SSL already).
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
    file { "/etc/haproxy/ssl/private/oci-pki-api.pem":
      ensure                  => present,
      owner                   => "haproxy",
      source                  => "/etc/ssl/private/oci-pki-api.pem",
      selinux_ignore_defaults => true,
      mode                    => '0600',
    }
  }
  $haproxy_schedule1 = ['heat::db::begin', 'barbican::db::begin']
  if $has_subrole_nova {
    if $openstack_release == 'rocky' {
      $haproxy_schedule2 = concat($haproxy_schedule1, ['nova::db::begin', 'neutron::db::begin'])
    }else{
      $haproxy_schedule2 = concat($haproxy_schedule1, ['nova::db::begin', 'placement::db::begin', 'neutron::db::begin'])
    }
  } else {
    $haproxy_schedule2 = $haproxy_schedule1
  }
  if $has_subrole_cinder {
    $haproxy_schedule3 = concat($haproxy_schedule2, ['cinder::db::begin'])
  } else {
    $haproxy_schedule3 = $haproxy_schedule2
  }
  if $has_subrole_glance {
    $haproxy_schedule4 = concat($haproxy_schedule3, ['glance::db::begin'])
  } else {
    $haproxy_schedule4 = $haproxy_schedule3
  }
  if $has_subrole_aodh {
    $haproxy_schedule5 = concat($haproxy_schedule4, ['aodh::db::begin'])
  } else {
    $haproxy_schedule5 = $haproxy_schedule4
  }
  if $has_subrole_octavia {
    $haproxy_schedule6 = concat($haproxy_schedule5, ['octavia::db::begin'])
  } else {
    $haproxy_schedule6 = $haproxy_schedule5
  }
  if $has_subrole_panko {
    $haproxy_schedule7 = concat($haproxy_schedule6, ['panko::db::begin'])
  } else {
    $haproxy_schedule7 = $haproxy_schedule6
  }
  if $has_subrole_gnocchi {
    $haproxy_schedule8 = concat($haproxy_schedule7, ['gnocchi::db::begin'])
  } else {
    $haproxy_schedule8 = $haproxy_schedule7
  }
  if $has_subrole_cloudkitty {
    $haproxy_schedule9 = concat($haproxy_schedule8, ['cloudkitty::db::begin'])
  } else {
    $haproxy_schedule9 = $haproxy_schedule8
  }
  if $has_subrole_magnum {
    $haproxy_schedule10 = concat($haproxy_schedule9, ['magnum::db::begin'])
  } else {
    $haproxy_schedule10 = $haproxy_schedule9
  }
  $haproxy_schedule = $haproxy_schedule10

  sysctl::value { 'net.ipv4.ip_nonlocal_bind':
    value => "1",
    target => '/etc/sysctl.d/ip-nonlocal-bind.conf',
  }->
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
        'timeout' => [
          'http-request 10s',
          'queue 1m',
          'connect 10s',
          'client 1m',
          'server 1m',
          'check 10s',
        ],
    },
    merge_options => true,
    before => Anchor[$haproxy_schedule],
  }

  # If SQL is on the controllers, define front-end and back-end for SQL
  if $has_subrole_db {
    haproxy::frontend { 'galerafe':
      mode      => 'tcp',
      bind      => { "${vip_ipaddr}:3306" => [] },
      options   => [
        { 'timeout'         => 'client 3600s'},
        { 'default_backend' => 'galerabe'},
      ],
      before => Anchor[$haproxy_schedule],
    }
    haproxy::backend { 'galerabe':
      options => [
         { 'mode'    => 'tcp' },
         { 'balance' => 'roundrobin' },
         { 'timeout' => 'check 5000' },
         { 'timeout' => 'server 3600s' },
      ],
      before => Anchor[$haproxy_schedule],
    }
    haproxy::balancermember { 'galerabm':
      listening_service => 'galerabe',
      ipaddresses       => $first_sql_ip,
      server_names      => $first_sql,
      options           => 'check inter 4000 port 9200 fall 3 rise 5',
      before => Anchor[$haproxy_schedule],
    }
    haproxy::balancermember { 'galerabmback':
      listening_service => 'galerabe',
      ipaddresses       => $non_master_sql_ip,
      server_names      => $non_master_sql,
      options           => 'check inter 4000 port 9200 fall 3 rise 5 backup',
      before => Anchor[$haproxy_schedule],
    }
  }

  $haproxy_options_keystone = [
    # Set HSTS (63072000 seconds = 2 years)
    { 'http-response' => 'set-header Strict-Transport-Security max-age=63072000'},
    # So that we log real customer's IPs in our logs
    { 'option'        => "forwardfor except $vip_ipaddr"},
    { 'acl'           => 'url_keystone_admin path_beg -i /identity-admin'},
    { 'use_backend'   => 'keystoneadmbe if url_keystone_admin'},
    { 'acl'           => 'url_keystone path_beg -i /identity'},
    { 'use_backend'   => 'keystonepubbe if url_keystone'},
  ]

  $glance_haproxy_options = [
    { 'acl'         => 'url_glance path_beg -i /image'},
    { 'use_backend' => 'glancebe if url_glance'},
  ]

  $swift_haproxy_options = [
    { 'acl'         => 'url_swift path_beg -i /object'},
    { 'use_backend' => 'swiftbe if url_swift'},
  ]

  $heat_haproxy_options = [
    { 'acl'         => 'url_heatcfn path_beg -i /orchestration-cfn'},
    { 'use_backend' => 'heatcfnbe if url_heatcfn'},
    { 'acl'         => 'url_heat path_beg -i /orchestration-api'},
    { 'use_backend' => 'heatbe if url_heat'},
  ]

  $horizon_haproxy_options = [
    { 'acl'         => 'url_horizon path_beg -i /horizon'},
    { 'use_backend' => 'horizonbe if url_horizon'},
  ]

  $barbican_haproxy_options = [
    { 'acl'         => 'url_barbican path_beg -i /keymanager'},
    { 'use_backend' => 'barbicanbe if url_barbican'},
  ]

  $nova_haproxy_options = [
    { 'acl'         => 'url_nova path_beg -i /compute'},
    { 'use_backend' => 'novabe if url_nova'},
    { 'acl'         => 'url_placement path_beg -i /placement'},
    { 'use_backend' => 'placementbe if url_placement'},
    { 'acl'         => 'url_novnc path_beg -i /novnc'},
    { 'use_backend' => 'novncbe if url_novnc'},
    { 'acl'         => 'url_websockify path_beg -i /websockify'},
    { 'use_backend' => 'websockifybe if url_websockify'},
  ]

  $neutron_haproxy_options = [
    { 'acl'         => 'url_neutron path_beg -i /network'},
    { 'use_backend' => 'neutronbe if url_neutron'},
  ]

  $cinder_haproxy_options = [
    { 'acl'         => 'url_cinder path_beg -i /volume'},
    { 'use_backend' => 'cinderbe if url_cinder'},
  ]

  $gnocchi_haproxy_options = [
    { 'acl'         => 'url_gnocchi path_beg -i /metric'},
    { 'use_backend' => 'gnocchibe if url_gnocchi'},
  ]

  $cloudkitty_haproxy_options = [
    { 'acl'         => 'url_cloudkitty path_beg -i /rating'},
    { 'use_backend' => 'cloudkittybe if url_cloudkitty'},
  ]

  $aodh_haproxy_options = [
    { 'acl'         => 'url_aodh path_beg -i /alarm'},
    { 'use_backend' => 'aodhbe if url_aodh'},
  ]

  $octavia_haproxy_options = [
    { 'acl'         => 'url_octavia path_beg -i /loadbalance'},
    { 'use_backend' => 'octaviabe if url_octavia'},
  ]

  $magnum_haproxy_options = [
    { 'acl'         => 'url_magnum path_beg -i /containers'},
    { 'use_backend' => 'magnumbe if url_magnum'},
  ]

  $panko_haproxy_options = [
    { 'acl'         => 'url_panko path_beg -i /event'},
    { 'use_backend' => 'pankobe if url_panko'},
  ]


  $custom_haproxy_options = [
    { 'acl'         => 'url_slash path /'},
    { 'redirect'    => "location $haproxy_custom_url if url_slash"},
  ]

  if $has_subrole_glance {
    $haproxy_options_with_glance = concat($haproxy_options_keystone, $glance_haproxy_options)
  } else {
    $haproxy_options_with_glance = $haproxy_options_keystone
  }

  if $has_subrole_swift and $swiftproxy_hostname == 'none' {
    $haproxy_options_with_swift = concat($haproxy_options_with_glance, $swift_haproxy_options)
  } else {
    $haproxy_options_with_swift = $haproxy_options_with_glance
  }

  if $has_subrole_heat {
    $haproxy_options_with_heat = concat($haproxy_options_with_swift, $heat_haproxy_options)
  } else {
    $haproxy_options_with_heat = $haproxy_options_with_swift
  }

  if $has_subrole_horizon {
    $haproxy_options_with_horizon = concat($haproxy_options_with_heat, $horizon_haproxy_options)
  } else {
    $haproxy_options_with_horizon = $haproxy_options_with_heat
  }

  if $has_subrole_barbican {
    $haproxy_options_with_barbican = concat($haproxy_options_with_horizon, $barbican_haproxy_options)
  } else {
    $haproxy_options_with_barbican = $haproxy_options_with_horizon
  }

  if $has_subrole_nova {
    $haproxy_options_with_nova = concat($haproxy_options_with_barbican, $nova_haproxy_options)
  } else {
    $haproxy_options_with_nova = $haproxy_options_with_barbican
  }

  if $has_subrole_neutron {
    $haproxy_options_with_neutron = concat($haproxy_options_with_nova, $neutron_haproxy_options)
  } else {
    $haproxy_options_with_neutron = $haproxy_options_with_nova
  }

  if $has_subrole_cinder {
    $haproxy_options_with_cinder = concat($haproxy_options_with_neutron, $cinder_haproxy_options)
  } else {
    $haproxy_options_with_cinder = $haproxy_options_with_neutron
  }

  if $has_subrole_gnocchi {
    $haproxy_options_with_gnocchi = concat($haproxy_options_with_cinder, $gnocchi_haproxy_options)
  } else {
    $haproxy_options_with_gnocchi = $haproxy_options_with_cinder
  }

  if $has_subrole_cloudkitty {
    $haproxy_options_with_cloudkitty = concat($haproxy_options_with_gnocchi, $cloudkitty_haproxy_options)
  } else {
    $haproxy_options_with_cloudkitty = $haproxy_options_with_gnocchi
  }

  if $has_subrole_aodh {
    $haproxy_options_with_aodh = concat($haproxy_options_with_cloudkitty, $aodh_haproxy_options)
  } else {
    $haproxy_options_with_aodh = $haproxy_options_with_cloudkitty
  }

  if $has_subrole_octavia {
    $haproxy_options_with_octavia = concat($haproxy_options_with_aodh, $octavia_haproxy_options)
  } else {
    $haproxy_options_with_octavia = $haproxy_options_with_aodh
  }

  if $has_subrole_magnum {
    $haproxy_options_with_magnum = concat($haproxy_options_with_octavia, $magnum_haproxy_options)
  } else {
    $haproxy_options_with_magnum = $haproxy_options_with_octavia
  }

  if $has_subrole_panko {
    $haproxy_options_with_panko = concat($haproxy_options_with_magnum, $panko_haproxy_options)
  } else {
    $haproxy_options_with_panko = $haproxy_options_with_magnum
  }

  if $haproxy_custom_url {
    $haproxy_options_with_custom = concat($haproxy_options_with_panko, $custom_haproxy_options)
  } else {
    $haproxy_options_with_custom = $haproxy_options_with_panko
  }

  # Global frontend for all services with dispatch depending on the URL
  haproxy::frontend { 'openstackfe':
    mode      => 'http',
    bind      => { "${vip_ipaddr}:${api_port}" => ['ssl', 'crt', '/etc/haproxy/ssl/private/oci-pki-api.pem', 'crt', '/etc/haproxy/ssl/private/'] },
    options   => $haproxy_options_with_custom,
  }

  # Keystone public
  haproxy::backend { 'keystonepubbe':
    options => [
       { 'option' => 'forwardfor' },
       { 'option' => 'httpchk GET /healthcheck' },
       { 'http-request'=> "set-header X-Client-IP %[src]"},
       { 'mode' => 'http' },
       { 'balance' => 'roundrobin' },
       { 'reqrep'  => '^([^\ ]*\ /)identity[/]?(.*)     \1\2'},
       { 'rspirep' => '^Location:\ (https://[^/]*)/(.*)$ Location:\ \1/identity/\2'},
    ],
  }
  haproxy::balancermember { 'keystonepubbm':
    listening_service => 'keystonepubbe',
    ipaddresses       => $all_masters_ip,
    server_names      => $all_masters,
    ports             => 5000,
    options           => $haproxy_api_backend_options,
  }
# TODO: haproxy::balancermember needs an option like this one:
#  ssl verify required ca-file /etc/haproxy/cert.d/key1.pem check

  # keystone admin
  haproxy::backend { 'keystoneadmbe':
    options => [
       { 'option' => 'forwardfor' },
       { 'option' => 'httpchk GET /healthcheck' },
       { 'http-request'=> "set-header X-Client-IP %[src]"},
       { 'mode' => 'http' },
       { 'balance' => 'roundrobin' },
       { 'reqrep'  => '^([^\ ]*\ /)identity-admin[/]?(.*)     \1\2'},
       { 'rspirep' => '^Location:\ (https://[^/]*)/(.*)$ Location:\ \1/identity-admin/\2'},
    ],
  }
  haproxy::balancermember { 'keystoneadmbm':
    listening_service => 'keystoneadmbe',
    ipaddresses       => $all_masters_ip,
    server_names      => $all_masters,
    ports             => 35357,
    options           => $haproxy_api_backend_options,
  }

  # glance API
  if $has_subrole_glance {
    haproxy::backend { 'glancebe':
      options => [
         { 'option' => 'forwardfor' },
         { 'mode' => 'http' },
         { 'balance' => 'source' },
         { 'reqrep'  => '^([^\ ]*\ /)image[/]?(.*)     \1\2'},
      ],
    }
    if $glance_backend == 'file'{
      haproxy::balancermember { 'glancebm':
        listening_service => 'glancebe',
        ipaddresses       => [ "${first_master_ip}", ],
        server_names      => [ "${first_master}", ],
        ports             => 9292,
        options           => $haproxy_api_backend_options_noverify,
      }
    } else {
      haproxy::balancermember { 'glancebm':
        listening_service => 'glancebe',
        ipaddresses       => $all_masters_ip,
        server_names      => $all_masters,
        ports             => 9292,
        options           => $haproxy_api_backend_options_noverify,
      }
    }
  }

  # swift proxy
  if $has_subrole_swift and $swiftproxy_hostname == 'none' {
    haproxy::backend { 'swiftbe':
      options => [
         { 'option' => 'httpchk GET /healthcheck' },
         { 'option' => 'forwardfor' },
         { 'mode' => 'http' },
         { 'balance' => 'roundrobin' },
         { 'reqrep'  => '^([^\ ]*\ /)object[/]?(.*)     \1\2'},
      ],
    }
    haproxy::balancermember { 'swiftbm':
      listening_service => 'swiftbe',
      ipaddresses       => $all_swiftproxy_ip,
      server_names      => $all_swiftproxy,
      ports             => 8080,
      options           => 'check',
    }
  }

  # heat
  if $has_subrole_heat {
    haproxy::backend { 'heatbe':
      options => [
         { 'option' => 'forwardfor' },
         { 'mode' => 'http' },
         { 'balance' => 'roundrobin' },
         { 'reqrep'  => '^([^\ ]*\ /)orchestration-api[/]?(.*)     \1\2'},
         { 'rspirep' => '^Location:\ (https://[^/]*)/(.*)$ Location:\ \1/orchestration-api/\2'},
      ],
    }
    haproxy::balancermember { 'heatbm':
      listening_service => 'heatbe',
      ipaddresses       => $all_masters_ip,
      server_names      => $all_masters,
      ports             => 8004,
      options           => $haproxy_api_backend_options,
    }
    haproxy::backend { 'heatcfnbe':
      options => [
         { 'option' => 'forwardfor' },
         { 'mode' => 'http' },
         { 'balance' => 'roundrobin' },
         { 'reqrep'  => '^([^\ ]*\ /)orchestration-cfn[/]?(.*)     \1\2'},
         { 'rspirep' => '^Location:\ (https://[^/]*)/(.*)$ Location:\ \1/orchestration-cfn/\2'},
      ],
    }
    haproxy::balancermember { 'heatcfnbm':
      listening_service => 'heatcfnbe',
      ipaddresses       => $all_masters_ip,
      server_names      => $all_masters,
      ports             => 8000,
      options           => $haproxy_api_backend_options,
    }
  }

  if $has_subrole_horizon {
    haproxy::backend { 'horizonbe':
      options => [
         { 'option' => 'forwardfor' },
         { 'mode' => 'http' },
         { 'balance' => 'source' },
      ],
    }
    haproxy::balancermember { 'horizonbm':
      listening_service => 'horizonbe',
      ipaddresses       => $all_masters_ip,
      server_names      => $all_masters,
      ports             => 7443,
      options           => $haproxy_api_backend_options,
    }
  }

  if $has_subrole_barbican {
    haproxy::backend { 'barbicanbe':
      options => [
         { 'option' => 'forwardfor' },
         { 'mode' => 'http' },
         { 'balance' => 'roundrobin' },
         { 'reqrep'  => '^([^\ ]*\ /)keymanager[/]?(.*)     \1\2'},
      ],
    }
    haproxy::balancermember { 'barbicanbm':
      listening_service => 'barbicanbe',
      ipaddresses       => $all_masters_ip,
      server_names      => $all_masters,
      ports             => 9311,
      options           => $haproxy_api_backend_options,
    }
  }

  if $has_subrole_nova {
    haproxy::backend { 'novabe':
      options => [
         { 'option' => 'forwardfor' },
         { 'mode' => 'http' },
         { 'balance' => 'source' },
         { 'reqrep'  => '^([^\ ]*\ /)compute[/]?(.*)     \1\2'},
         { 'rspirep' => '^Location:\ (https://[^/]*)/(.*)$ Location:\ \1/compute/\2'},
      ],
    }
    haproxy::balancermember { 'novabm':
      listening_service => 'novabe',
      ipaddresses       => $all_masters_ip,
      server_names      => $all_masters,
      ports             => 8774,
      options           => $haproxy_api_backend_options,
    }

    haproxy::backend { 'placementbe':
      options => [
         { 'option' => 'forwardfor' },
         { 'mode' => 'http' },
         { 'balance' => 'roundrobin' },
         { 'reqrep'  => '^([^\ ]*\ /)placement[/]?(.*)     \1\2'},
      ],
    }
    haproxy::balancermember { 'placementbm':
      listening_service => 'placementbe',
      ipaddresses       => $all_masters_ip,
      server_names      => $all_masters,
      ports             => 8778,
      options           => $haproxy_api_backend_options,
    }

    haproxy::backend { 'novncbe':
      options => [
         { 'option' => 'forwardfor' },
         { 'mode' => 'http' },
         { 'balance' => 'source' },
         { 'reqrep'  => '^([^\ ]*\ /)novnc[/]?(.*)     \1\2'},
      ],
    }
    haproxy::balancermember { 'novncbm':
      listening_service => 'novncbe',
      ipaddresses       => $all_masters_ip,
      server_names      => $all_masters,
      ports             => 6080,
      options           => 'check',
    }

    haproxy::backend { 'websockifybe':
      options => [
         { 'option'  => 'forwardfor' },
         { 'mode'    => 'http' },
         { 'balance' => 'source' },
         { 'acl'     => 'hdr_connection_upgrade hdr(Connection)                 -i upgrade'},
         { 'acl'     => 'hdr_upgrade_websocket  hdr(Upgrade)                    -i websocket'},
         { 'acl'     => 'hdr_websocket_key      hdr_cnt(Sec-WebSocket-Key)      eq 1'},
         { 'acl'     => 'hdr_websocket_version  hdr_cnt(Sec-WebSocket-Version)  eq 1'},
      ],
    }
    haproxy::balancermember { 'websockifybm':
      listening_service => 'websockifybe',
      ipaddresses       => $all_masters_ip,
      server_names      => $all_masters,
      ports             => 6080,
      options           => 'check',
    }
  }

  if $has_subrole_neutron {
    haproxy::backend { 'neutronbe':
      options => [
         { 'option' => 'forwardfor' },
         { 'mode' => 'http' },
         { 'balance' => 'roundrobin' },
         { 'reqrep'  => '^([^\ ]*\ /)network[/]?(.*)     \1\2'},
      ],
    }
    haproxy::balancermember { 'neutronbm':
      listening_service => 'neutronbe',
      ipaddresses       => $all_masters_ip,
      server_names      => $all_masters,
      ports             => 9696,
      options           => $haproxy_api_backend_options,
    }
  }

  if $has_subrole_cinder {
    haproxy::backend { 'cinderbe':
      options => [
         { 'option' => 'forwardfor' },
         { 'mode' => 'http' },
         { 'balance' => 'roundrobin' },
         { 'reqrep'  => '^([^\ ]*\ /)volume[/]?(.*)     \1\2'},
      ],
    }
    haproxy::balancermember { 'cinderbm':
      listening_service => 'cinderbe',
      ipaddresses       => $all_masters_ip,
      server_names      => $all_masters,
      ports             => 8776,
      options           => $haproxy_api_backend_options,
    }
  }

  if $has_subrole_gnocchi {
    haproxy::backend { 'gnocchibe':
      options => [
         { 'option' => 'forwardfor' },
         { 'option' => 'httpchk GET /healthcheck' },
         { 'mode' => 'http' },
         { 'balance' => 'roundrobin' },
         { 'reqrep'  => '^([^\ ]*\ /)metric[/]?(.*)     \1\2'},
      ],
    }
    haproxy::balancermember { 'gnocchibm':
      listening_service => 'gnocchibe',
      ipaddresses       => $all_masters_ip,
      server_names      => $all_masters,
      ports             => 8041,
      options           => $haproxy_api_backend_options,
    }
  }

  if $has_subrole_cloudkitty {
    haproxy::backend { 'cloudkittybe':
      options => [
         { 'option' => 'forwardfor' },
         { 'mode' => 'http' },
         { 'balance' => 'roundrobin' },
         { 'reqrep'  => '^([^\ ]*\ /)rating[/]?(.*)     \1\2'},
         { 'rspirep' => '^Location:\ (https://[^/]*)/(.*)$ Location:\ \1/rating/\2'},
      ],
    }
    haproxy::balancermember { 'cloudkittybm':
      listening_service => 'cloudkittybe',
      ipaddresses       => $all_masters_ip,
      server_names      => $all_masters,
      ports             => 8889,
      options           => $haproxy_api_backend_options,
    }
  }

  if $has_subrole_aodh {
    haproxy::backend { 'aodhbe':
      options => [
         { 'option' => 'forwardfor' },
         { 'option' => 'httpchk GET /healthcheck' },
         { 'mode' => 'http' },
         { 'balance' => 'roundrobin' },
         { 'reqrep'  => '^([^\ ]*\ /)alarm[/]?(.*)     \1\2'},
      ],
    }
    haproxy::balancermember { 'aodhbm':
      listening_service => 'aodhbe',
      ipaddresses       => $all_masters_ip,
      server_names      => $all_masters,
      ports             => 8042,
      options           => $haproxy_api_backend_options,
    }
  }

  if $has_subrole_octavia {
    haproxy::backend { 'octaviabe':
      options => [
         { 'option' => 'forwardfor' },
         { 'mode' => 'http' },
         { 'balance' => 'roundrobin' },
         { 'reqrep'  => '^([^\ ]*\ /)loadbalance[/]?(.*)     \1\2'},
      ],
    }
    haproxy::balancermember { 'octaviabm':
      listening_service => 'octaviabe',
      ipaddresses       => $all_masters_ip,
      server_names      => $all_masters,
      ports             => 9876,
      options           => $haproxy_api_backend_options,
    }
  }

  if $has_subrole_magnum {
    haproxy::backend { 'magnumbe':
      options => [
         { 'option' => 'forwardfor' },
         { 'mode' => 'http' },
         { 'balance' => 'roundrobin' },
         { 'reqrep'  => '^([^\ ]*\ /)containers[/]?(.*)     \1\2'},
         { 'rspirep' => '^Location:\ (https://[^/]*)/(.*)$ Location:\ \1/containers/\2'},
      ],
    }
    haproxy::balancermember { 'magnumbm':
      listening_service => 'magnumbe',
      ipaddresses       => $all_masters_ip,
      server_names      => $all_masters,
      ports             => 9511,
      options           => $haproxy_api_backend_options,
    }
  }

  if $has_subrole_panko {
    haproxy::backend { 'pankobe':
      options => [
         { 'option' => 'forwardfor' },
         { 'mode' => 'http' },
         { 'balance' => 'roundrobin' },
         { 'reqrep'  => '^([^\ ]*\ /)event[/]?(.*)     \1\2'},
      ],
    }
    haproxy::balancermember { 'pankobm':
      listening_service => 'pankobe',
      ipaddresses       => $all_masters_ip,
      server_names      => $all_masters,
      ports             => 8777,
      options           => $haproxy_api_backend_options,
    }
  }

  #######################
  ### Setup databases ###
  #######################
  if $has_subrole_db {
    if $facts['os']['lsb'] != undef{
      $mycodename = $facts['os']['lsb']['distcodename']
    }else{
      $mycodename = $facts['os']['distro']['codename']
    }
    if $mycodename != 'stretch'{
      package { 'mariadb-backup':
        ensure => present,
        before => Class['galera'],
      }
      $wsrep_sst_method = 'mariabackup'
    }else{
      $wsrep_sst_method = 'mariabackup'
    }
    class { 'galera':
      galera_servers      => $all_masters_ip,
      galera_master       => $first_master,
      local_ip            => $machine_ip,
      package_ensure      => 'latest',
      mysql_package_name  => 'mariadb-server',
      client_package_name => 'default-mysql-client',
      vendor_type         => 'mariadb',
      root_password       => $pass_mysql_rootuser,
      status_password     => $pass_mysql_rootuser,
      deb_sysmaint_password => $pass_mysql_rootuser,
      configure_repo      => false,
      configure_firewall  => false,
      galera_package_name => 'galera-3',
      override_options => {
        'mysqld' => {
          'bind_address'                    => $machine_ip,
          'wait_timeout'                    => '28800',
          'interactive_timeout'             => '30',
          'connect_timeout'                 => '30',
          'character_set_server'            => 'utf8',
          'collation_server'                => 'utf8_general_ci',
          'max_connections'                 => '1000',
          'max_user_connections'            => '500',
          'binlog_cache_size'               => '1M',
          'log-bin'                         => 'mysql-bin',
          'binlog_format'                   => 'ROW',
          'performance_schema'              => '1',
          'log_warnings'                    => '2',
          'wsrep_sst_auth'                  => "backup:${pass_mysql_backup}",
          'wsrep_sst_method'                => $wsrep_sst_method,
          'wsrep_cluster_name'              => $cluster_name,
          'wsrep_node_name'                 => $machine_hostname,
        }
      },
      require             => Cs_primitive['openstack-api-vip'],
    }->
    mysql_user { 'backup@%':
      ensure        => present,
      password_hash => mysql_password($pass_mysql_backup),
    }->
    mysql_grant{'backup@%/*.*':
      ensure     => 'present',
      options    => ['GRANT'],
      privileges => ['ALL'],
      table      => '*.*',
      user       => 'backup@%',
    }->

    # Wait until SHOW STATUS LIKE 'wsrep_cluster_status' shows Primary
    exec {'galera-is-up':
      command => "/usr/bin/oci-wait-for-sql \"SHOW STATUS LIKE 'wsrep_cluster_status'\" Primary mysql 4800",
      unless  => '/bin/false # comment to satisfy puppet syntax requirements',
      timeout => 5000,
    }

    # Wait until SHOW STATUS LIKE 'wsrep_connected' shows ON
    exec {'galera-wsrep-connected-on':
      command => "/usr/bin/oci-wait-for-sql \"SHOW STATUS LIKE 'wsrep_connected'\" ON mysql 4800",
      unless  => '/bin/false # comment to satisfy puppet syntax requirements',
      require => Exec['galera-is-up'],
      timeout => 5000,
    }

    # Wait until SHOW STATUS LIKE 'wsrep_local_state_comment' shows Synced
    exec {'galera-is-synced':
      command => "/usr/bin/oci-wait-for-sql \"SHOW STATUS LIKE 'wsrep_local_state_comment'\" Synced mysql 4800",
      unless  => '/bin/false # comment to satisfy puppet syntax requirements',
      require => Exec['galera-wsrep-connected-on'],
      timeout => 5000,
    }

    # Wait until all nodes are connected to the cluster
    $galera_cluster_num_of_nodes = sprintf('%i', $all_masters.size)
    exec {'galera-size-is-correct':
      command => "/usr/bin/oci-wait-for-sql \"SHOW STATUS LIKE 'wsrep_cluster_size'\" ${galera_cluster_num_of_nodes} mysql 4800",
      unless  => '/bin/false # comment to satisfy puppet syntax requirements',
      require => Exec['galera-is-synced'],
      timeout => 5000,
    }


    if $is_first_master {
      class { '::keystone::db::mysql':
        dbname   => 'keystonedb',
        password => $pass_keystone_db,
        allowed_hosts => '%',
        require  => Exec['galera-size-is-correct'],
#        require  => Class['oci::sql::galera'],
        before   => Anchor['keystone::service::begin'],
      }
    } else {
      exec {'wait-for-keystone-dbuser':
        command => "/usr/bin/oci-wait-for-sql \"SELECT User FROM user WHERE User='keystone'\" keystone mysql 4800",
        unless  => '/bin/false # comment to satisfy puppet syntax requirements',
        require => Exec['galera-size-is-correct'],
        before  => Anchor['keystone::service::begin'],
        timeout => 5000,
      }
    }
    if $has_subrole_glance {
      if $is_first_master {
        class { '::glance::db::mysql':
          dbname   => 'glancedb',
          password => $pass_glance_db,
          allowed_hosts => '%',
          require  => Exec['galera-size-is-correct'],
#          require  => Class['oci::sql::galera'],
          before   => Anchor['glance::service::begin'],
        }
      } else {
        exec { 'glance-db-user':
          command => "/usr/bin/oci-wait-for-sql \"SELECT User FROM user WHERE User='glance'\" glance mysql 4800",
          unless  => '/bin/false # comment to satisfy puppet syntax requirements',
          require => Exec['galera-size-is-correct'],
          before  => Anchor['glance::service::begin'],
          timeout => 5000,
        }
      }
    }
    if $has_subrole_heat {
      if $is_first_master {
        class { '::heat::db::mysql':
          dbname   => 'heatdb',
          password => $pass_heat_db,
          allowed_hosts => '%',
          require  => Exec['galera-size-is-correct'],
#          require  => Class['oci::sql::galera'],
          before   => Anchor['heat::service::begin'],
        }
      } else {
        exec { 'heat-db-user':
          command => "/usr/bin/oci-wait-for-sql \"SELECT User FROM user WHERE User='heat'\" heat mysql 4800",
          unless  => '/bin/false # comment to satisfy puppet syntax requirements',
          require => Exec['galera-size-is-correct'],
#          require  => Class['oci::sql::galera'],
          before  => Anchor['heat::service::begin'],
          timeout => 5000,
        }
      }
    }
    if $has_subrole_barbican {
      if $is_first_master {
        class { '::barbican::db::mysql':
          dbname   => 'barbicandb',
          password => $pass_barbican_db,
          allowed_hosts => '%',
          require  => Exec['galera-size-is-correct'],
#          require  => Class['oci::sql::galera'],
          before   => Anchor['barbican::service::begin'],
        }
      } else {
        exec { 'barbican-db-user':
          command => "/usr/bin/oci-wait-for-sql \"SELECT User FROM user WHERE User='barbican'\" barbican mysql 4800",
          unless  => '/bin/false # comment to satisfy puppet syntax requirements',
          require => Exec['galera-size-is-correct'],
#          require  => Class['oci::sql::galera'],
          before  => Anchor['barbican::service::begin'],
          timeout => 5000,
        }
      }
    }
    if $has_subrole_nova {
      if $is_first_master {
        class { '::nova::db::mysql':
          user     => 'nova',
          dbname   => 'novadb',
          password => $pass_nova_db,
          allowed_hosts => '%',
          require  => Exec['galera-size-is-correct'],
          before   => Class['::nova::cell_v2::simple_setup'],
          notify   => Service['nova-conductor', 'nova-scheduler'],
        }
        class { '::nova::db::mysql_api':
          user     => 'novaapi',
          dbname   => 'novaapidb',
          password => $pass_nova_apidb,
          allowed_hosts => '%',
          require  => Exec['galera-size-is-correct'],
          before   => Class['::nova::cell_v2::simple_setup'],
        }
        if $openstack_release == 'rocky'{
          class { '::nova::db::mysql_placement':
            user     => 'placement',
            dbname   => 'placementdb',
            password => $pass_placement_db,
            allowed_hosts => '%',
            require  => Exec['galera-size-is-correct'],
            before   => Anchor['nova::service::begin'],
          }
        }else{
          class { '::placement::db::mysql':
            user => 'placement',
            dbname => 'placementdb',
            password => $pass_placement_db,
            allowed_hosts => '%',
            require  => Exec['galera-size-is-correct'],
            before   => Anchor['nova::service::begin'],
          }
        }
      } else {
        exec { 'nova-db-user':
          command => "/usr/bin/oci-wait-for-sql \"SELECT User FROM user WHERE User='nova'\" nova mysql 4800",
          unless  => '/bin/false # comment to satisfy puppet syntax requirements',
          require => Exec['galera-size-is-correct'],
          before  => Class['::nova::cell_v2::simple_setup'],
          timeout => 5000,
        }
        exec { 'novaapi-db-user':
          command => "/usr/bin/oci-wait-for-sql \"SELECT User FROM user WHERE User='novaapi'\" novaapi mysql 4800",
          unless  => '/bin/false # comment to satisfy puppet syntax requirements',
          require => Exec['galera-size-is-correct'],
          before  => Class['::nova::cell_v2::simple_setup'],
          timeout => 5000,
        }
        exec { 'placement-db-user':
          command => "/usr/bin/oci-wait-for-sql \"SELECT User FROM user WHERE User='placement'\" placement mysql 4800",
          unless  => '/bin/false # comment to satisfy puppet syntax requirements',
          require => Exec['galera-size-is-correct'],
          before  => Anchor['nova::service::begin'],
          timeout => 5000,
        }
      }
    }
    if $has_subrole_neutron {
      if $is_first_master {
        class { '::neutron::db::mysql':
          dbname   => 'neutrondb',
          password => $pass_neutron_db,
          allowed_hosts => '%',
          require  => Exec['galera-size-is-correct'],
          before   => Anchor['neutron::service::begin'],
        }
      } else {
        exec { 'neutron-db-user':
          command => "/usr/bin/oci-wait-for-sql \"SELECT User FROM user WHERE User='neutron'\" neutron mysql 4800",
          unless  => '/bin/false # comment to satisfy puppet syntax requirements',
          require => Exec['galera-size-is-correct'],
          before  => Anchor['neutron::service::begin'],
          timeout => 5000,
        }
      }
    }
    if $has_subrole_cinder {
      if $is_first_master {
        class { '::cinder::db::mysql':
          dbname        => 'cinderdb',
          password      => $pass_cinder_db,
          allowed_hosts => '%',
          require       => Exec['galera-size-is-correct'],
#          require  => Class['oci::sql::galera'],
          before        => Anchor['cinder::service::begin'],
        }
      } else {
        exec { 'cinder-db-user':
          command => "/usr/bin/oci-wait-for-sql \"SELECT User FROM user WHERE User='cinder'\" cinder mysql 4800",
          unless  => '/bin/false # comment to satisfy puppet syntax requirements',
          require => Exec['galera-size-is-correct'],
#          require  => Class['oci::sql::galera'],
          before  => Anchor['cinder::service::begin'],
          timeout => 5000,
        }
      }
    }
    if $has_subrole_gnocchi {
      if $is_first_master {
        class { '::gnocchi::db::mysql':
          dbname   => 'gnocchidb',
          password => $pass_gnocchi_db,
          allowed_hosts => '%',
          require  => Exec['galera-size-is-correct'],
#          require  => Class['oci::sql::galera'],
          before   => Anchor['gnocchi::service::begin'],
        }
      } else {
        exec { 'gnocchi-db-user':
          command => "/usr/bin/oci-wait-for-sql \"SELECT User FROM user WHERE User='gnocchi'\" gnocchi mysql 4800",
          unless  => '/bin/false # comment to satisfy puppet syntax requirements',
          require => Exec['galera-size-is-correct'],
#          require  => Class['oci::sql::galera'],
          before  => Anchor['gnocchi::service::begin'],
          timeout => 5000,
        }
      }
    }
    if $has_subrole_panko {
      if $is_first_master {
        class { '::panko::db::mysql':
          dbname   => 'pankodb',
          password => $pass_panko_db,
          allowed_hosts => '%',
          require  => Exec['galera-size-is-correct'],
#          require  => Class['oci::sql::galera'],
          before   => Anchor['panko::service::begin'],
        }
      } else {
        exec { 'panko-db-user':
          command => "/usr/bin/oci-wait-for-sql \"SELECT User FROM user WHERE User='panko'\" panko mysql 4800",
          unless  => '/bin/false # comment to satisfy puppet syntax requirements',
          require => Exec['galera-size-is-correct'],
#          require  => Class['oci::sql::galera'],
          before  => Anchor['panko::service::begin'],
          timeout => 5000,
        }
      }
    }
    if $has_subrole_cloudkitty {
      if $is_first_master {
        class { '::cloudkitty::db::mysql':
          dbname   => 'cloudkittydb',
          password => $pass_cloudkitty_db,
          allowed_hosts => '%',
          require  => Exec['galera-size-is-correct'],
#          require  => Class['oci::sql::galera'],
          before   => Anchor['cloudkitty::service::begin'],
        }
      } else {
        exec { 'cloudkitty-db-user':
          command => "/usr/bin/oci-wait-for-sql \"SELECT User FROM user WHERE User='cloudkitty'\" cloudkitty mysql 4800",
          unless  => '/bin/false # comment to satisfy puppet syntax requirements',
          require => Exec['galera-size-is-correct'],
#          require  => Class['oci::sql::galera'],
          before  => Anchor['cloudkitty::service::begin'],
          timeout => 5000,
        }
      }
    }
    if $has_subrole_aodh {
      if $is_first_master {
        class { '::aodh::db::mysql':
          dbname   => 'aodhdb',
          password => $pass_aodh_db,
          allowed_hosts => '%',
          require  => Exec['galera-size-is-correct'],
#          require  => Class['oci::sql::galera'],
          before   => Anchor['aodh::service::begin'],
        }
      } else {
        exec { 'aodh-db-user':
          command => "/usr/bin/oci-wait-for-sql \"SELECT User FROM user WHERE User='aodh'\" aodh mysql 4800",
          unless  => '/bin/false # comment to satisfy puppet syntax requirements',
          require => Exec['galera-size-is-correct'],
#          require  => Class['oci::sql::galera'],
          before  => Anchor['aodh::service::begin'],
          timeout => 5000,
        }
      }
    }
    if $has_subrole_octavia {
      if $is_first_master {
        class { '::octavia::db::mysql':
          dbname   => 'octaviadb',
          password => $pass_octavia_db,
          allowed_hosts => '%',
          require  => Exec['galera-size-is-correct'],
#          require  => Class['oci::sql::galera'],
          before   => Anchor['octavia::service::begin'],
        }
      } else {
        exec { 'octavia-db-user':
          command => "/usr/bin/oci-wait-for-sql \"SELECT User FROM user WHERE User='octavia'\" octavia mysql 4800",
          unless  => '/bin/false # comment to satisfy puppet syntax requirements',
          require => Exec['galera-size-is-correct'],
#          require  => Class['oci::sql::galera'],
          before  => Anchor['octavia::service::begin'],
          timeout => 5000,
        }
      }
    }
    if $has_subrole_magnum {
      if $is_first_master {
        class { '::magnum::db::mysql':
          dbname   => 'magnumdb',
          password => $pass_magnum_db,
          allowed_hosts => '%',
          require  => Exec['galera-size-is-correct'],
#          require  => Class['oci::sql::galera'],
          before   => Anchor['magnum::service::begin'],
        }
      } else {
        exec { 'magnum-db-user':
          command => "/usr/bin/oci-wait-for-sql \"SELECT User FROM user WHERE User='magnum'\" magnum mysql 4800",
          unless  => '/bin/false # comment to satisfy puppet syntax requirements',
          require => Exec['galera-size-is-correct'],
#          require  => Class['oci::sql::galera'],
          before  => Anchor['magnum::service::begin'],
          timeout => 5000,
        }
      }
    }
  }  

  ######################
  ### Setup RabbitMQ ###
  ######################
  if $has_subrole_messaging {
    if $has_subrole_db {
      Exec['galera-size-is-correct'] -> Class['::rabbitmq']
#      Class['oci::sql::galera'] -> Class['::rabbitmq']
    }
    if $use_ssl {
      file { "/etc/rabbitmq/ssl/private":
        ensure                  => directory,
        owner                   => 'root',
        mode                    => '0755',
        require                 => File['/etc/rabbitmq/ssl'],
        selinux_ignore_defaults => true,
      }->
      file { "/etc/rabbitmq/ssl/public":
        ensure                  => directory,
        owner                   => 'root',
        mode                    => '0755',
        selinux_ignore_defaults => true,
      }->
      file { "/etc/rabbitmq/ssl/private/${::fqdn}.key":
        ensure                  => present,
        owner                   => "rabbitmq",
        source                  => "/etc/ssl/private/ssl-cert-snakeoil.key",
        selinux_ignore_defaults => true,
        mode                    => '0600',
      }->
      file { "/etc/rabbitmq/ssl/public/${::fqdn}.crt":
        ensure                  => present,
        owner                   => "rabbitmq",
        source                  => '/etc/ssl/certs/ssl-cert-snakeoil.pem',
        selinux_ignore_defaults => true,
        mode                    => '0644',
        notify        => Service['rabbitmq-server'],
      }
      $rabbit_ssl_cert = "/etc/rabbitmq/ssl/public/${::fqdn}.crt"
      $rabbit_ssl_key  = "/etc/rabbitmq/ssl/private/${::fqdn}.key"
      $rabbit_ssl_ca   = '/etc/ssl/certs/oci-pki-oci-ca-chain.pem'
    } else {
      $rabbit_ssl_cert = UNSET
      $rabbit_ssl_key  = UNSET
      $rabbit_ssl_ca   = UNSET
    }

    class { '::rabbitmq':
      package_ensure           => 'latest',
      delete_guest_user        => true,
      node_ip_address          => $machine_ip,
      ssl_interface            => $machine_ip,
      ssl                      => $use_ssl,
      ssl_only                 => $use_ssl,
      ssl_cacert               => $rabbit_ssl_ca,
      ssl_cert                 => $rabbit_ssl_cert,
      ssl_key                  => $rabbit_ssl_key,
      environment_variables    => $rabbit_env,
      repos_ensure             => false,
      # Clustering options...
      config_cluster           => true,
      cluster_nodes            => $all_masters,
      cluster_node_type        => 'ram',
      erlang_cookie            => $pass_rabbitmq_cookie,
      wipe_db_on_cookie_change => true,
    }->
    rabbitmq_vhost { '/':
      provider => 'rabbitmqctl',
      require  => Class['::rabbitmq'],
    }->
    rabbitmq_user { 'keystone':
      admin    => true,
      password => $pass_keystone_messaging,
    }->
    rabbitmq_user_permissions { "keystone@/":
      configure_permission => '.*',
      write_permission     => '.*',
      read_permission      => '.*',
      provider             => 'rabbitmqctl',
      require              => Class['::rabbitmq'],
    }->
    exec { 'auto-join-rabbit-cluster':
      command => "/usr/bin/oci-auto-join-rabbitmq-cluster ${first_master}",
      unless  => "/bin/false",
    }

    if $has_subrole_glance {
      rabbitmq_user { 'glance':
        admin    => true,
        password => $pass_glance_messaging,
      }->
      rabbitmq_user_permissions { "glance@/":
        configure_permission => '.*',
        write_permission     => '.*',
        read_permission      => '.*',
        provider             => 'rabbitmqctl',
        require              => Class['::rabbitmq'],
        before               => Anchor['glance::service::begin'],
      }
    }

    if $is_first_master {
      if $has_subrole_heat {
        rabbitmq_user { 'heat':
          admin    => true,
          password => $pass_heat_messaging,
        }->
        rabbitmq_user_permissions { "heat@/":
          configure_permission => '.*',
          write_permission     => '.*',
          read_permission      => '.*',
          provider             => 'rabbitmqctl',
          require              => Class['::rabbitmq'],
          before               => Anchor['heat::service::begin'],
        }
      }

      if $has_subrole_barbican {
        rabbitmq_user { 'barbican':
          admin    => true,
          password => $pass_barbican_messaging,
        }->
        rabbitmq_user_permissions { "barbican@/":
          configure_permission => '.*',
          write_permission     => '.*',
          read_permission      => '.*',
          provider             => 'rabbitmqctl',
          require              => Class['::rabbitmq'],
          before               => Anchor['barbican::service::begin'],
        }
      }

      if $has_subrole_nova {
        rabbitmq_user { 'nova':
          admin    => true,
          password => $pass_nova_messaging,
        }->
        rabbitmq_user_permissions { "nova@/":
          configure_permission => '.*',
          write_permission     => '.*',
          read_permission      => '.*',
          provider             => 'rabbitmqctl',
          require              => Class['::rabbitmq'],
          before               => Anchor['nova::service::begin'],
        }
      }
      if $has_subrole_neutron {
        rabbitmq_user { 'neutron':
          admin    => true,
          password => $pass_neutron_messaging,
        }->
        rabbitmq_user_permissions { "neutron@/":
          configure_permission => '.*',
          write_permission     => '.*',
          read_permission      => '.*',
          provider             => 'rabbitmqctl',
          require              => Class['::rabbitmq'],
          before               => Anchor['neutron::service::begin'],
        }
      }
      if $has_subrole_cinder {
        rabbitmq_user { 'cinder':
          admin    => true,
          password => $pass_cinder_messaging,
        }->
        rabbitmq_user_permissions { "cinder@/":
          configure_permission => '.*',
          write_permission     => '.*',
          read_permission      => '.*',
          provider             => 'rabbitmqctl',
          require              => Class['::rabbitmq'],
          before               => Anchor['cinder::service::begin'],
        }
      }
      if $has_subrole_gnocchi {
        rabbitmq_user { 'gnocchi':
          admin    => true,
          password => $pass_gnocchi_messaging,
        }->
        rabbitmq_user_permissions { "gnocchi@/":
          configure_permission => '.*',
          write_permission     => '.*',
          read_permission      => '.*',
          provider             => 'rabbitmqctl',
          require              => Class['::rabbitmq'],
          before               => Anchor['gnocchi::service::begin'],
        }
      }
      if $has_subrole_panko {
        rabbitmq_user { 'panko':
          admin    => true,
          password => $pass_panko_messaging,
        }->
        rabbitmq_user_permissions { "panko@/":
          configure_permission => '.*',
          write_permission     => '.*',
          read_permission      => '.*',
          provider             => 'rabbitmqctl',
          require              => Class['::rabbitmq'],
          before               => Anchor['panko::service::begin'],
        }
      }
      if $has_subrole_ceilometer {
        rabbitmq_user { 'ceilometer':
          admin    => true,
          password => $pass_ceilometer_messaging,
        }->
        rabbitmq_user_permissions { "ceilometer@/":
          configure_permission => '.*',
          write_permission     => '.*',
          read_permission      => '.*',
          provider             => 'rabbitmqctl',
          require              => Class['::rabbitmq'],
          before               => Anchor['ceilometer::service::begin'],
        }
      }
      if $has_subrole_cloudkitty {
        rabbitmq_user { 'cloudkitty':
          admin    => true,
          password => $pass_cloudkitty_messaging,
        }->
        rabbitmq_user_permissions { "cloudkitty@/":
          configure_permission => '.*',
          write_permission     => '.*',
          read_permission      => '.*',
          provider             => 'rabbitmqctl',
          require              => Class['::rabbitmq'],
          before               => Anchor['cloudkitty::service::begin'],
        }
      }
      if $has_subrole_aodh {
        rabbitmq_user { 'aodh':
          admin    => true,
          password => $pass_aodh_messaging,
        }->
        rabbitmq_user_permissions { "aodh@/":
          configure_permission => '.*',
          write_permission     => '.*',
          read_permission      => '.*',
          provider             => 'rabbitmqctl',
          require              => Class['::rabbitmq'],
          before               => Anchor['aodh::service::begin'],
        }
      }
      if $has_subrole_octavia {
        rabbitmq_user { 'octavia':
          admin    => true,
          password => $pass_octavia_messaging,
        }->
        rabbitmq_user_permissions { "octavia@/":
          configure_permission => '.*',
          write_permission     => '.*',
          read_permission      => '.*',
          provider             => 'rabbitmqctl',
          require              => Class['::rabbitmq'],
          before               => Anchor['octavia::service::begin'],
        }
      }
      if $has_subrole_magnum {
        rabbitmq_user { 'magnum':
          admin    => true,
          password => $pass_magnum_messaging,
        }->
        rabbitmq_user_permissions { "magnum@/":
          configure_permission => '.*',
          write_permission     => '.*',
          read_permission      => '.*',
          provider             => 'rabbitmqctl',
          require              => Class['::rabbitmq'],
          before               => Anchor['magnum::service::begin'],
        }
      }
    }
  }

  #######################
  ### Setup memcached ###
  #######################
  class { '::memcached':
    listen_ip => $machine_ip,
    udp_port  => 0,
  }

  # Configure Apache to use mod-wsgi-py3, not mod-wsgi
  if $has_subrole_api_keystone {
    # Set python3 for mod-wsgi
    include ::apache::params
    class { '::apache':
      mod_packages => merge($::apache::params::mod_packages, {
        'wsgi' => 'libapache2-mod-wsgi-py3',
      })
    }
  }

  if $has_subrole_cinder or $has_subrole_nova or $has_subrole_horizon {
    include ::apache
  }

  if $has_subrole_api_keystone {
    ##############################
    ### Setup keystone cluster ###
    ##############################
    if $has_subrole_db {
      Class['galera'] -> Anchor['keystone::install::begin']
    }

    class { '::keystone::client': }
    class { '::keystone::cron::token_flush': }

    if $use_ssl {
      if $openstack_release == 'rocky'{
        file { "/etc/keystone/ssl":
          ensure                  => directory,
          owner                   => 'root',
          mode                    => '0755',
          selinux_ignore_defaults => true,
          require       => Package['keystone'],
        }->
        file { "/etc/keystone/ssl/private":
          ensure                  => directory,
          owner                   => 'root',
          mode                    => '0755',
          selinux_ignore_defaults => true,
        }->
        file { "/etc/keystone/ssl/public":
          ensure                  => directory,
          owner                   => 'root',
          mode                    => '0755',
          selinux_ignore_defaults => true,
        }->
        file { "/etc/keystone/ssl/private/${::fqdn}.key":
          ensure                  => present,
          owner                   => "keystone",
          source                  => "/etc/ssl/private/ssl-cert-snakeoil.key",
          selinux_ignore_defaults => true,
          mode                    => '0600',
        }->
        file { "/etc/keystone/ssl/public/${::fqdn}.crt":
          ensure                  => present,
          owner                   => "keystone",
          source                  => '/etc/ssl/certs/ssl-cert-snakeoil.pem',
          selinux_ignore_defaults => true,
          mode                    => '0644',
          notify        => Service['httpd'],
        }
      }else{
        ::oci::sslkeypair {'keystone':
          notify_service_name => 'keystone',
        }
      }
      $keystone_key_file = "/etc/keystone/ssl/private/${::fqdn}.pem"
      $keystone_crt_file = "/etc/keystone/ssl/public/${::fqdn}.crt"
    } else {
      $keystone_key_file = undef
      $keystone_crt_file = undef
    }

    if $openstack_release == 'rocky'{
      ensure_resource('file', '/etc/apache2/sites-available/wsgi-keystone.conf', {
        'ensure'  => 'present',
        'content' => '',
        require   => Package['keystone'],
      })
    }

    if $disable_notifications {
      $keystone_notif_transport_url = ''
    } else {
      $keystone_notif_transport_url = os_transport_url({
                                        'transport' => 'rabbit',
                                        'hosts'     => fqdn_rotate($all_masters),
                                        'port'      => '5671',
                                        'username'  => 'keystone',
                                        'password'  => $pass_keystone_messaging,
                                      })
    }
    if $openstack_release == 'rocky'{
      class { '::keystone':
        debug                      => true,
        database_connection        => "mysql+pymysql://keystone:${pass_keystone_db}@${sql_host}/keystonedb",
        database_idle_timeout      => 1800,
        catalog_type               => 'sql',
        admin_token                => $pass_keystone_adminuser,
        admin_password             => $pass_keystone_adminuser,
        enabled                    => true,
        service_name               => 'httpd',
        enable_ssl                 => $use_ssl,
        public_bind_host           => "${::fqdn}",
        admin_bind_host            => "${::fqdn}",
        manage_policyrcd           => true,
        enable_credential_setup    => true,
        credential_key_repository  => '/etc/keystone/credential-keys',
        credential_keys            => { '/etc/keystone/credential-keys/0' => { 'content' => $pass_keystone_credkey1 },
                                        '/etc/keystone/credential-keys/1' => { 'content' => $pass_keystone_credkey2 },
                                      },
        enable_fernet_setup        => true,
        fernet_replace_keys	 => false,
        fernet_key_repository      => '/etc/keystone/fernet-keys',
        fernet_max_active_keys     => 4,
        fernet_keys                => { '/etc/keystone/fernet-keys/0' => { 'content' => $pass_keystone_fernkey1 },
# With fernet_replace_keys => false, if  we don't have the "1" key
# then re-applying puppet is fine, and initial setup is ok too.
#                                      '/etc/keystone/fernet-keys/1' => { 'content' => $pass_keystone_fernkey2 },
                                      },
        token_expiration           => 604800,
        admin_endpoint             => "${keystone_admin_uri}",
        public_endpoint            => "https://${vip_hostname}:${api_port}/identity",
        default_transport_url      => os_transport_url({
          'transport' => 'rabbit',
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => '5671',
          'username'  => 'keystone',
          'password'  => $pass_keystone_messaging,
        }),
        notification_transport_url => $keystone_notif_transport_url,
        rabbit_use_ssl             => $use_ssl,
        kombu_ssl_ca_certs         => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
        rabbit_ha_queues           => true,
        cache_backend              => 'dogpile.cache.memcached',
        memcache_servers           => $memcached_servers,
      }
      class { '::keystone::wsgi::apache':
        bind_host         => $machine_ip,
        admin_bind_host   => $machine_ip,
        ssl               => $use_ssl,
        ssl_key           => "/etc/keystone/ssl/private/${::fqdn}.key",
        ssl_cert          => "/etc/keystone/ssl/public/${::fqdn}.crt",
        workers           => 2,
        access_log_format => '%{X-Forwarded-For}i %l %u %t \"%r\" %>s %b %D \"%{Referer}i\" \"%{User-Agent}i\"',
      }
    } else {
      class { '::keystone':
        database_connection        => "mysql+pymysql://keystone:${pass_keystone_db}@${sql_host}/keystonedb",
        database_idle_timeout      => 1800,
        catalog_type               => 'sql',
        admin_token                => $pass_keystone_adminuser,
        admin_password             => $pass_keystone_adminuser,
        enabled                    => true,
        service_name               => 'keystone',
        enable_ssl                 => $use_ssl,
        public_bind_host           => "${::fqdn}",
        admin_bind_host            => "${::fqdn}",
        manage_policyrcd           => true,
        enable_credential_setup    => true,
        credential_key_repository  => '/etc/keystone/credential-keys',
        credential_keys            => { '/etc/keystone/credential-keys/0' => { 'content' => $pass_keystone_credkey1 },
                                        '/etc/keystone/credential-keys/1' => { 'content' => $pass_keystone_credkey2 },
                                      },
        enable_fernet_setup        => true,
        fernet_replace_keys	 => false,
        fernet_key_repository      => '/etc/keystone/fernet-keys',
        fernet_max_active_keys     => 4,
        fernet_keys                => { '/etc/keystone/fernet-keys/0' => { 'content' => $pass_keystone_fernkey1 },
# With fernet_replace_keys => false, if  we don't have the "1" key
# then re-applying puppet is fine, and initial setup is ok too.
#                                      '/etc/keystone/fernet-keys/1' => { 'content' => $pass_keystone_fernkey2 },
                                      },
        token_expiration           => 604800,
        admin_endpoint             => "${keystone_admin_uri}",
        public_endpoint            => "https://${vip_hostname}:${api_port}/identity",
        default_transport_url      => os_transport_url({
          'transport' => 'rabbit',
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => '5671',
          'username'  => 'keystone',
          'password'  => $pass_keystone_messaging,
        }),
        notification_transport_url => $keystone_notif_transport_url,
        rabbit_use_ssl             => $use_ssl,
        kombu_ssl_ca_certs         => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
        rabbit_ha_queues           => true,
        cache_backend              => 'dogpile.cache.memcached',
        cache_memcache_servers     => $memcached_servers,
      }
    }
    class { '::keystone::roles::admin':
      email    => 'production@infomaniak.com',
      password => $pass_keystone_adminuser,
    }->
    class { '::keystone::endpoint':
      public_url => "${base_url}/identity",
      admin_url  => "${keystone_admin_uri}",
    }
    class { '::keystone::disable_admin_token_auth': }
    class { '::openstack_extras::auth_file':
      password       => $pass_keystone_adminuser,
      project_domain => 'default',
      user_domain    => 'default',
      auth_url       => "${base_url}:${api_port}/identity/v3/",
    }
    keystone_role { 'creator':
      ensure => present,
    }
    keystone_role { 'member':
      ensure => present,
    }
  }

  #######################
  ### Setup Ceph keys ###
  #######################
  if $cluster_has_osds {
    $mon_keyring_path = "/tmp/ceph-${machine_hostname}.keyring"
    class { 'ceph':
      fsid                => $ceph_fsid,
      ensure              => 'present',
      authentication_type => 'cephx',
      mon_initial_members => $ceph_mon_initial_members,
      mon_host            => $ceph_mon_host,
    }->
    ceph::key { 'client.admin':
      secret  => $ceph_admin_key,
      cap_mon => 'allow *',
      cap_osd => 'allow *',
      cap_mds => 'allow',
    }->
    ceph::key { 'client.openstack':
      secret  => $ceph_openstack_key,
      mode    => '0644',
      cap_mon => 'profile rbd',
      cap_osd => 'profile rbd pool=cinder, profile rbd pool=nova, profile rbd pool=glance, profile rbd pool=gnocchi, profile rbd pool=cinderback',
    }->
    ceph::key { 'client.bootstrap-osd':
      secret       => $ceph_bootstrap_osd_key,
      keyring_path => '/var/lib/ceph/bootstrap-osd/ceph.keyring',
      cap_mon      => 'allow profile bootstrap-osd',
    }

    if $cluster_has_mons {
      warning('Cluster has OSD nodes: will not setup MON and MGR on the controller nodes.')
    }else{
      exec { 'create-tmp-keyring':
        command => "/bin/true # comment to satisfy puppet syntax requirements
set -ex

cat > /etc/ceph/ceph.conf<< EOF
[global]
fsid = ${ceph_fsid}
mon_initial_members = ${ceph_mon_initial_members}
mon_host = ${ceph_mon_host}
auth cluster required = cephx
auth service required = cephx
auth client required = cephx
osd journal size = 1024
osd pool default size = 3
osd pool default min size = 2
osd pool default pg num = 333
osd pool default pgp num = 333
osd crush chooseleaf type = 1
EOF

cat > ${mon_keyring_path}<< EOF
[mon.]
    key = ${ceph_mon_key}
    caps mon = \"allow *\"
EOF

mon_data=\$(ceph-mon --cluster ceph --id ${machine_hostname} --show-config-value mon_data)

ceph-authtool ${mon_keyring_path} --import-keyring /etc/ceph/ceph.client.admin.keyring
ceph-authtool ${mon_keyring_path} --import-keyring /etc/ceph/ceph.client.openstack.keyring
ceph-authtool ${mon_keyring_path} --import-keyring /var/lib/ceph/bootstrap-osd/ceph.keyring
chmod 0444 ${mon_keyring_path}
chown ceph:ceph ${mon_keyring_path}

rm -f /tmp/monmap
monmaptool --create --add ${machine_hostname} ${machine_ip} --fsid ${ceph_fsid} /tmp/monmap
MACHINE_IPS=\$(echo ${ceph_mon_host} | tr ',' ' ')
CNT=1
for i in \$(echo ${ceph_mon_initial_members} | tr ',' ' ') ; do
        if [ \$i = ${machine_hostname} ] ; then
                echo -n ''
        else
                IP=\$(echo \$MACHINE_IPS | awk '{print \$'\$CNT'}')
                monmaptool --add \$i \$IP /tmp/monmap
        fi
        CNT=\$((\${CNT} + 1))
done

chown ceph:ceph /tmp/monmap

sudo -u ceph mkdir -p \$mon_data
sudo -u ceph ceph-mon --cluster ceph --mkfs -i ${machine_hostname} --monmap /tmp/monmap --keyring ${mon_keyring_path}
",
        unless  => "/bin/true # comment to satisfy puppet syntax requirements
set -ex
mon_data=\$(ceph-mon --cluster ceph --id ${machine_hostname} --show-config-value mon_data) || exit 1
# if ceph-mon fails then the mon is probably not configured yet
test -e \$mon_data/done
",
        require => Ceph::Key['client.bootstrap-osd'],
      }->
      ceph::mon { $machine_hostname:
        keyring     => $mon_keyring_path,
        public_addr => $machine_ip,
      }->
      ceph::mgr { $machine_hostname:
        key        => $ceph_mgr_key,
        inject_key => true,
      }
    }
    $ceph_pools = ['glance', 'nova', 'cinder', 'gnocchi', 'cinderback']
    ceph::pool { $ceph_pools: }
  }

  ####################
  ### Setup Glance ###
  ####################
  if $has_subrole_glance {
    if $has_subrole_db {
      Class['galera'] -> Anchor['glance::install::begin']
    }
    if $use_ssl {
      ::oci::sslkeypair {'glance':
        notify_service_name => 'glance-api',
      }
      $glance_key_file = "/etc/glance/ssl/private/${::fqdn}.pem"
      $glance_crt_file = "/etc/glance/ssl/public/${::fqdn}.crt"
    } else {
      $glance_key_file = undef
      $glance_crt_file = undef
    }

    include ::glance
    include ::glance::client
    if $is_first_master {
      class { '::glance::keystone::auth':
        public_url   => "${base_url}/image",
        internal_url => "${base_url}/image",
        admin_url    => "${base_url}/image",
        password     => $pass_glance_authtoken,
      }
    }

    class { '::glance::api::authtoken':
      password             => $pass_glance_authtoken,
      auth_url             => $keystone_admin_uri,
      www_authenticate_uri => $keystone_auth_uri,
      memcached_servers    => $memcached_servers,
      cafile               => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
    }

    case $glance_backend {
      # This is there just in case, but doesn't really work, since
      # there's no mech to sync files between controller nodes.
      'file': {
        include ::glance::backend::file
        $backend_stores = ['file']
        $glance_default_store = 'file'
      }
      # This will be in use in priority, if OCI sees some OSD nodes.
      'ceph': {
        class { '::glance::backend::rbd':
          rbd_store_user => 'openstack',
          rbd_store_pool => 'glance',
        }
        $backend_stores = ['rbd', 'file']
        $glance_default_store = 'rbd'
        # make sure ceph pool exists before running Glance API
        Exec['create-glance'] -> Service['glance-api']
      }
      # Used if no OSD nodes are setup, but some swift store are present.
      'swift': {
        $backend_stores = ['swift', 'file']
        class { '::glance::backend::swift':
          swift_store_user                    => 'services:glance',
          swift_store_key                     => $pass_glance_onswift,
          swift_store_create_container_on_put => 'True',
          swift_store_auth_address            => "${keystone_auth_uri}/v3",
          swift_store_auth_version            => '3',
        }
        $glance_default_store = 'swift'
      }
      'cinder': {
        $backend_stores = ['cinder', 'file']
        class { '::glance::backend::cinder':
          cinder_ca_certificates_file => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
        }
      }
    }

    if $openstack_release == 'rocky'{
      class { '::glance::api':
        debug               => true,
        database_connection => "mysql+pymysql://glance:${pass_glance_db}@${sql_host}/glancedb?charset=utf8",
        database_idle_timeout      => 1800,
        workers             => 2,
        use_stderr          => true,
        stores              => $backend_stores,
        default_store       => $glance_default_store,
        bind_host           => $machine_ip,
        cert_file           => $glance_crt_file,
        key_file            => $glance_key_file,
        enable_v1_api       => false,
        enable_v2_api       => true,
      }
    } else {
      class { '::glance::api':
        database_connection => "mysql+pymysql://glance:${pass_glance_db}@${sql_host}/glancedb?charset=utf8",
        database_idle_timeout      => 1800,
        workers             => 2,
        stores              => $backend_stores,
        default_store       => $glance_default_store,
        bind_host           => $machine_ip,
        cert_file           => $glance_crt_file,
        key_file            => $glance_key_file,
        enable_v1_api       => false,
        enable_v2_api       => true,
      }
      class { '::glance::api::logging':
        debug => true,
      }
    }

    if $disable_notifications {
      $glance_notif_transport_url = ''
    } else {
      $glance_notif_transport_url = os_transport_url({
                                      'transport' => $messaging_notify_proto,
                                      'hosts'     => fqdn_rotate($all_masters),
                                      'port'      => $messaging_notify_port,
                                      'username'  => 'glance',
                                      'password'  => $pass_glance_messaging,
                                    })
    }
    class { '::glance::notify::rabbitmq':
      default_transport_url      => os_transport_url({
        'transport' => $messaging_default_proto,
        'hosts'     => fqdn_rotate($all_masters),
        'port'      => $messaging_default_port,
        'username'  => 'glance',
        'password'  => $pass_glance_messaging,
      }),
      notification_transport_url => $glance_notif_transport_url,
      notification_driver        => 'messagingv2',
      rabbit_use_ssl             => $use_ssl,
      rabbit_ha_queues           => true,
      kombu_ssl_ca_certs         => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
    }

    class { '::glance::config':
      api_config => {
        'DEFAULT/public_endpoint' => { value => "${base_url}/image"},
      }
    }
  }

  ###################
  ### Setup Swift ###
  ###################
  if $has_subrole_swift  {
    if $swiftproxy_hostname == 'none' {
      $swiftproxy_baseurl = $base_url
    } else {
      $swiftproxy_baseurl = "${proto}://${swiftproxy_hostname}"
    }
    if $is_first_master {
      class { '::swift::keystone::auth':
        public_url            => "${swiftproxy_baseurl}/object/v1/AUTH_%(tenant_id)s",
        admin_url             => "${swiftproxy_baseurl}/object",
        internal_url          => "${swiftproxy_baseurl}/object/v1/AUTH_%(tenant_id)s",
        password              => $pass_swift_authtoken,
        operator_roles        => ['admin', 'SwiftOperator', 'ResellerAdmin'],
        configure_s3_endpoint => false,
      }
    }
  }

  ##################
  ### Setup Heat ###
  ##################
  if $has_subrole_heat {
    if $has_subrole_db {
      Class['galera'] -> Anchor['heat::install::begin']
      Exec['galera-size-is-correct'] -> Package['heat dashboard']
#      Class['oci::sql::galera'] -> Package['heat dashboard']
    }
    if $use_ssl {
      oci::sslkeypair { 'heat':
        notify_service_name => 'heat-api',
      }
      $heat_key_file = "/etc/heat/ssl/private/${::fqdn}.pem"
      $heat_crt_file = "/etc/heat/ssl/public/${::fqdn}.crt"
    } else {
      $heat_key_file = undef
      $heat_crt_file = undef
    }
    class { '::heat::keystone::authtoken':
      password             => $pass_heat_authtoken,
      auth_url             => $keystone_admin_uri,
      www_authenticate_uri => $keystone_auth_uri,
      memcached_servers    => $memcached_servers,
      cafile               => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
    }
    if $is_first_master {
      class { '::heat::keystone::auth':
        public_url   => "${base_url}/orchestration-api/v1/%(tenant_id)s",
        internal_url => "${base_url}/orchestration-api/v1/%(tenant_id)s",
        admin_url    => "${base_url}/orchestration-api/v1/%(tenant_id)s",
        password     => $pass_heat_authtoken,
      }
      class { '::heat::keystone::auth_cfn':
        public_url   => "${base_url}/orchestration-cfn/v1",
        internal_url => "${base_url}/orchestration-cfn/v1",
        admin_url    => "${base_url}/orchestration-cfn/v1",
        password     => $pass_heat_authtoken,
      }
    }

    if $disable_notifications {
      $heat_notif_transport_url = ''
    } else {
      $heat_notif_transport_url = os_transport_url({
                                    'transport' => $messaging_notify_proto,
                                    'hosts'     => fqdn_rotate($all_masters),
                                    'port'      => $messaging_notify_port,
                                    'username'  => 'heat',
                                    'password'  => $pass_heat_messaging,
                                  })
    }
    if $openstack_release == 'rocky'{
      class { '::heat':
        debug                      => true,
        default_transport_url      => os_transport_url({
          'transport' => $messaging_default_proto,
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => $messaging_default_port,
          'username'  => 'heat',
          'password'  => $pass_heat_messaging,
        }),
        notification_transport_url => $heat_notif_transport_url,
        host                       => $machine_hostname,
        rabbit_use_ssl             => $use_ssl,
        rabbit_ha_queues           => true,
        kombu_ssl_ca_certs         => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
        database_connection        => "mysql+pymysql://heat:${pass_heat_db}@${sql_host}/heatdb?charset=utf8",
        database_idle_timeout      => 1800,
        notification_driver        => 'messagingv2',
      }
    } else {
      class { '::heat':
        default_transport_url      => os_transport_url({
          'transport' => $messaging_default_proto,
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => $messaging_default_port,
          'username'  => 'heat',
          'password'  => $pass_heat_messaging,
        }),
        notification_transport_url => $heat_notif_transport_url,
        host                       => $machine_hostname,
        rabbit_use_ssl             => $use_ssl,
        rabbit_ha_queues           => true,
        kombu_ssl_ca_certs         => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
        database_connection        => "mysql+pymysql://heat:${pass_heat_db}@${sql_host}/heatdb?charset=utf8",
        database_idle_timeout      => 1800,
        notification_driver        => 'messagingv2',
      }
      class { '::heat::logging':
        debug => true,
      }
    }
    class { '::heat::keystone::domain':
      domain_password => $pass_heat_keystone_domain,
    }
    class { '::heat::client': }
    class { '::heat::api':
      service_name => 'heat-api',
    }
    class { '::heat::engine':
      auth_encryption_key           => $pass_heat_encryptkey[0,32],
#      heat_metadata_server_url      => "${base_url}:8000/orchestration-cfn",
#      heat_waitcondition_server_url => "${base_url}:8000/orchestration-cfn/v1/waitcondition",
    }
    class { '::heat::api_cfn':
      service_name => 'heat-api-cfn',
    }
    class { '::heat::cron::purge_deleted': }
    package { 'heat dashboard':
      name => 'python3-heat-dashboard',
      ensure => installed,
    }
    heat_config {
      'clients/ca_file':           value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'clients_aodh/ca_file':      value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'clients_barbican/ca_file':  value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'clients_cinder/ca_file':    value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'clients_designate/ca_file': value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'clients_glance/ca_file':    value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'clients_heat/ca_file':      value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'clients_keystone/ca_file':  value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'clients_magnum/ca_file':    value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'clients_manila/ca_file':    value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'clients_mistral/ca_file':   value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'clients_monasca/ca_file':   value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'clients_neutron/ca_file':   value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'clients_nova/ca_file':      value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'clients_octavia/ca_file':   value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'clients_sahara/ca_file':    value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'clients_senlin/ca_file':    value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'clients_swift/ca_file':     value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'clients_trove/ca_file':     value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'clients_zaqar/ca_file':     value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
    }


  }

  #####################
  ### Setup Horizon ###
  #####################
  if $has_subrole_horizon {
    if $has_subrole_db {
      Class['galera'] -> Anchor['horizon::install::begin']
    }
    if $use_ssl {
      file { "/etc/openstack-dashboard/ssl":
        ensure                  => directory,
        owner                   => 'root',
        mode                    => '0755',
        selinux_ignore_defaults => true,
        require                 => Class['::horizon'],
      }->
      file { "/etc/openstack-dashboard/ssl/private":
        ensure                  => directory,
        owner                   => 'root',
        mode                    => '0755',
        selinux_ignore_defaults => true,
      }->
      file { "/etc/openstack-dashboard/ssl/public":
        ensure                  => directory,
        owner                   => 'root',
        mode                    => '0755',
        selinux_ignore_defaults => true,
      }->
      file { "/etc/openstack-dashboard/ssl/private/${::fqdn}.pem":
        ensure                  => present,
        owner                   => "www-data",
        source                  => "/etc/ssl/private/ssl-cert-snakeoil.key",
        selinux_ignore_defaults => true,
        mode                    => '0600',
      }->
      file { "/etc/openstack-dashboard/ssl/public/${::fqdn}.crt":
        ensure                  => present,
        owner                   => "www-data",
        source                  => '/etc/ssl/certs/ssl-cert-snakeoil.pem',
        selinux_ignore_defaults => true,
        mode                    => '0644',
        notify                  => Service['httpd'],
      }

      $horizon_key_file = "/etc/openstack-dashboard/ssl/private/${::fqdn}.pem"
      $horizon_crt_file = "/etc/openstack-dashboard/ssl/public/${::fqdn}.crt"
    } else {
      $horizon_key_file = undef
      $horizon_crt_file = undef
    }

    case $cinder_backup_backend {
      'ceph': {
        $enable_backup             = true
        $create_volume             = true
        $disable_instance_snapshot = false
        $disable_volume_snapshot   = false
      }
      'swift': {
        $enable_backup             = true
        $create_volume             = true
        $disable_instance_snapshot = false
        $disable_volume_snapshot   = false
      }
      'none': {
        $enable_backup             = false
        $create_volume             = false
        $disable_instance_snapshot = true
        $disable_volume_snapshot   = true
      }
    }

    class { '::horizon':
      secret_key       => $pass_horizon_secretkey,
      # TODO: Fix this with a more secure stuff, probably the FQDN of the API.
      allowed_hosts    => '*',
      listen_ssl       => true,
      ssl_redirect     => false,
      http_port       => '7080',
      https_port       => '7443',
      horizon_cert     => $horizon_crt_file,
      horizon_key      => $horizon_key_file,
      horizon_ca       => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
      keystone_url     => "${keystone_auth_uri}/v3",
      compress_offline => true,
      cinder_options  => { 'enable_backup' => $enable_backup },
      neutron_options  => { 'enable_ha_router' => true },
      instance_options => {
        'create_volume'             => $create_volume,
        'disable_instance_snapshot' => $disable_instance_snapshot,
        'disable_volume_snapshot'   => $disable_volume_snapshot },
    }
  }
  ######################
  ### Setup Barbican ###
  ######################
  if $has_subrole_barbican {
    if $has_subrole_db {
      Class['galera'] -> Anchor['barbican::install::begin']
    }
    if $use_ssl {
      oci::sslkeypair {'barbican':
        notify_service_name => 'barbican-api',
      }
      $barbican_key_file = "/etc/barbican/ssl/private/${::fqdn}.pem"
      $barbican_crt_file = "/etc/barbican/ssl/public/${::fqdn}.crt"
    } else {
      $barbican_key_file = undef
      $barbican_crt_file = undef
    }
    include ::barbican
    class { '::barbican::db':
      database_connection   => "mysql+pymysql://barbican:${pass_barbican_db}@${sql_host}/barbicandb?charset=utf8",
      database_idle_timeout => 1800,
    }
    if $is_first_master {
      class { '::barbican::keystone::auth':
        public_url   => "${base_url}/keymanager",
        internal_url => "${base_url}/keymanager",
        admin_url    => "${base_url}/keymanager",
        password     => $pass_barbican_authtoken,
      }
    }
    include ::barbican::quota
    include ::barbican::keystone::notification
    class { '::barbican::api::logging':
      debug => true,
    }
    class { '::barbican::keystone::authtoken':
      password             => $pass_barbican_authtoken,
      auth_url             => $keystone_admin_uri,
      www_authenticate_uri => $keystone_auth_uri,
      memcached_servers    => $memcached_servers,
      cafile               => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
    }

    if $disable_notifications {
      $barbican_notif_transport_url = ''
    } else {
      $barbican_notif_transport_url = os_transport_url({
                                        'transport' => $messaging_default_proto,
                                        'hosts'     => fqdn_rotate($all_masters),
                                        'port'      => $messaging_default_port,
                                        'username'  => 'barbican',
                                        'password'  => $pass_barbican_messaging,
                                      })
    }
    class { '::barbican::api':
      default_transport_url       => os_transport_url({
        'transport' => $messaging_default_proto,
        'hosts'     => fqdn_rotate($all_masters),
        'port'      => $messaging_default_port,
        'username'  => 'barbican',
        'password'  => $pass_barbican_messaging,
      }),
      notification_transport_url  => $barbican_notif_transport_url,
      host_href                   => "${base_url}/keymanager",
      auth_strategy               => 'keystone',
      enabled_certificate_plugins => ['simple_certificate'],
      db_auto_create              => false,
      rabbit_use_ssl              => $use_ssl,
      rabbit_ha_queues           => true,
      kombu_ssl_ca_certs         => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
    }
  }

  #######################
  ### Setup Placement ###
  #######################
  if $has_subrole_nova and $openstack_release != 'rocky'{
    if $has_subrole_db {
      Class['galera'] -> Anchor['placement::install::begin']
    }
    if $use_ssl {
      oci::sslkeypair {'placement':
        notify_service_name => 'placement-api',
      }
      $placement_key_file = "/etc/placement/ssl/private/${::fqdn}.pem"
      $placement_crt_file = "/etc/placement/ssl/public/${::fqdn}.crt"
    } else {
      $placement_key_file = undef
      $placement_crt_file = undef
    }

    include ::placement
  
    if $is_first_master {
      class { '::placement::keystone::auth':
        public_url   => "${base_url}/placement",
        internal_url => "${base_url}/placement",
        admin_url    => "${base_url}/placement",
        password     => $pass_placement_authtoken,
      }
    }
    class { '::placement::keystone::authtoken':
      password             => $pass_placement_authtoken,
      auth_url             => $keystone_admin_uri,
      www_authenticate_uri => $keystone_auth_uri,
      memcached_servers    => $memcached_servers,
      cafile               => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
    }
    class { '::placement::logging':
      debug => true,
    }
    class { '::placement::db':
      database_connection   => "mysql+pymysql://placement:${pass_placement_db}@${sql_host}/placementdb?charset=utf8",
    }
    class { '::placement::api':
      sync_db => true,
    }
  }

  ##################
  ### Setup Nova ###
  ##################
  if $has_subrole_nova {
    if $has_subrole_db {
      Class['galera'] -> Anchor['nova::install::begin']
    }
    if $use_ssl {
      oci::sslkeypair {'nova':
        notify_service_name => 'httpd',
      }
      $nova_key_file = "/etc/nova/ssl/private/${::fqdn}.pem"
      $nova_crt_file = "/etc/nova/ssl/public/${::fqdn}.crt"
    } else {
      $nova_key_file = undef
      $nova_crt_file = undef
    }
    class { '::nova::cell_v2::simple_setup':
      require => Exec['nova-db-sync-api'],
    }

    if $is_first_master {
      class { '::nova::keystone::auth':
        public_url   => "${base_url}/compute/v2.1",
        internal_url => "${base_url}/compute/v2.1",
        admin_url    => "${base_url}/compute/v2.1",
        password     => $pass_nova_authtoken,
      }
    }

    if $is_first_master {
      if $openstack_release == 'rocky'{
        class { '::nova::keystone::auth_placement':
          public_url   => "${base_url}/placement",
          internal_url => "${base_url}/placement",
          admin_url    => "${base_url}/placement",
          password     => $pass_placement_authtoken,
        }
      }
    }

    class { '::nova::keystone::authtoken':
      password             => $pass_nova_authtoken,
      auth_url             => $keystone_admin_uri,
      www_authenticate_uri => $keystone_auth_uri,
      memcached_servers    => $memcached_servers,
      cafile               => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
    }

    class { '::nova::logging':
      debug => true,
    }

    if $disable_notifications {
      $nova_notif_transport_url = ''
    } else {
      $nova_notif_transport_url = os_transport_url({
                                    'transport' => $messaging_notify_proto,
                                    'hosts'     => fqdn_rotate($all_masters),
                                    'port'      => $messaging_notify_port,
                                    'username'  => 'nova',
                                    'password'  => $pass_nova_messaging,
                                  })
    }
    if $openstack_release == 'rocky'{
      class { '::nova':
        default_transport_url      => os_transport_url({
          'transport' => $messaging_default_proto,
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => $messaging_default_port,
          'username'  => 'nova',
          'password'  => $pass_nova_messaging,
        }),
        notification_transport_url    => $nova_notif_transport_url,
        database_connection           => "mysql+pymysql://nova:${pass_nova_db}@${sql_host}/novadb?charset=utf8",
        api_database_connection       => "mysql+pymysql://novaapi:${pass_nova_apidb}@${sql_host}/novaapidb?charset=utf8",
        placement_database_connection => "mysql+pymysql://placement:${pass_placement_db}@${sql_host}/placementdb?charset=utf8",
        database_idle_timeout         => 1800,
        rabbit_use_ssl                => $use_ssl,
        rabbit_ha_queues              => true,
        kombu_ssl_ca_certs            => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
        amqp_sasl_mechanisms          => 'PLAIN',
        use_ipv6                      => false,
        glance_api_servers            => "${base_url}/image",
        notification_driver           => 'messagingv2',
        notify_on_state_change        => 'vm_and_task_state',
        before                        => Class['::nova::cell_v2::simple_setup'],
      }
    }else{
      class { '::nova':
        default_transport_url      => os_transport_url({
          'transport' => $messaging_default_proto,
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => $messaging_default_port,
          'username'  => 'nova',
          'password'  => $pass_nova_messaging,
        }),
        notification_transport_url    => $nova_notif_transport_url,
        database_connection           => "mysql+pymysql://nova:${pass_nova_db}@${sql_host}/novadb?charset=utf8",
        api_database_connection       => "mysql+pymysql://novaapi:${pass_nova_apidb}@${sql_host}/novaapidb?charset=utf8",
        database_idle_timeout         => 1800,
        rabbit_use_ssl                => $use_ssl,
        rabbit_ha_queues              => true,
        kombu_ssl_ca_certs            => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
        amqp_sasl_mechanisms          => 'PLAIN',
        use_ipv6                      => false,
        glance_api_servers            => "${base_url}/image",
        notification_driver           => 'messagingv2',
        notify_on_state_change        => 'vm_and_task_state',
        before                        => Class['::nova::cell_v2::simple_setup'],
      }
    }

    class { '::nova::api':
      api_bind_address                     => $machine_ip,
      neutron_metadata_proxy_shared_secret => $pass_metadata_proxy_shared_secret,
      metadata_workers                     => 2,
      sync_db_api                          => true,
      service_name                         => 'httpd',
      allow_resize_to_same_host            => true,
    }

    class { '::nova::wsgi::apache_api':
      bind_host => $machine_ip,
      ssl_key   => $nova_key_file,
      ssl_cert  => $nova_crt_file,
      ssl       => $use_ssl,
      workers   => '2',
    }

    class { '::nova::placement':
      auth_url => $keystone_admin_uri,
      password => $pass_placement_authtoken,
    }

    class { '::nova::client': }
    class { '::nova::conductor':
      workers => 4,
    }
    class { '::nova::consoleauth': }
    class { '::nova::cron::archive_deleted_rows': }

    if $openstack_release == 'rocky'{
      class { '::nova::scheduler': }
    }else{
      class { '::nova::scheduler':
        workers => '4',
      }
    }
    class { '::nova::scheduler::filter': }
    class { '::nova::vncproxy':
      host          => $machine_ip,
      vncproxy_path => "/novnc/vnc_auto.html",
    }
    class { '::nova::vncproxy::common':
	vncproxy_protocol => 'https',
	vncproxy_host     => "${vip_hostname}",
	vncproxy_path     => "/novnc/vnc_auto.html",
	vncproxy_port     => "443",
    }

    nova_config {
      'neutron/cafile':            value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'glance/cafile':             value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'keystone/cafile':           value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'placement/cafile':          value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'cinder/cafile':             value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'service_user/cafile':       value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
    }
    if $openstack_release == 'rocky'{
      nova_config {
        'scheduler/workers':         value => '4';
      }
    }

    if $openstack_release == 'rocky'{
      class { '::nova::network::neutron':
        neutron_auth_url      => "${keystone_auth_uri}/v3",
        neutron_url           => "${base_url}/network",
        neutron_password      => $pass_neutron_authtoken,
        default_floating_pool => 'public',
        dhcp_domain           => '',
      }
      nova_config {
        'neutron/endpoint_override': value => "${base_url}/network";
      }
    }else{
      class { '::nova::network::neutron':
        neutron_auth_url          => "${keystone_auth_uri}/v3",
        neutron_url               => "${base_url}/network",
        neutron_password          => $pass_neutron_authtoken,
        default_floating_pool     => 'public',
        dhcp_domain               => '',
        neutron_endpoint_override => "${base_url}/network",
      }
    }
  }
  #####################
  ### Setup Neutron ###
  #####################
  if $has_subrole_neutron {
    if $has_subrole_db {
      Class['galera'] -> Anchor['neutron::install::begin']
    }
    if $use_ssl {
      oci::sslkeypair {'neutron':
        notify_service_name => 'neutron-api',
      }
      $neutron_key_file = "/etc/neutron/ssl/private/${::fqdn}.pem"
      $neutron_crt_file = "/etc/neutron/ssl/public/${::fqdn}.crt"
    } else {
      $neutron_key_file = undef
      $neutron_crt_file = undef
    }

    if $is_first_master {
      class { '::neutron::keystone::auth':
        public_url   => "${base_url}/network",
        internal_url => "${base_url}/network",
        admin_url    => "${base_url}/network",
        password     => $pass_neutron_authtoken,
      }
    }

#    package { 'l2gw networking':
#      name => 'python3-networking-l2gw',
#      ensure => installed,
#      notify => Anchor['neutron::service::begin'],
#    }->
#    package { 'lbaas neutron':
#      name => 'python3-neutron-lbaas',
#      ensure => installed,
#      notify => Anchor['neutron::service::begin'],
#    }->

    if $vmnet_mtu == 0 {
      $vmnet_mtu_real = 1500
    }else{
      $vmnet_mtu_real = $vmnet_mtu
    }

    if $disable_notifications {
      $neutron_notif_transport_url = ''
    } else {
      $neutron_notif_transport_url = os_transport_url({
                                       'transport' => $messaging_notify_proto,
                                       'hosts'     => fqdn_rotate($all_masters),
                                       'port'      => $messaging_notify_port,
                                       'username'  => 'neutron',
                                       'password'  => $pass_neutron_messaging,
                                     })
    }
    if $openstack_release == 'rocky'{
      class { '::neutron':
        debug                      => true,
        default_transport_url      => os_transport_url({
          'transport' => $messaging_default_proto,
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => $messaging_default_port,
          'username'  => 'neutron',
          'password'  => $pass_neutron_messaging,
        }),
        notification_transport_url => $neutron_notif_transport_url,
        rabbit_use_ssl             => $use_ssl,
        rabbit_ha_queues           => true,
        kombu_ssl_ca_certs         => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
        amqp_sasl_mechanisms       => 'PLAIN',
        allow_overlapping_ips      => true,
        core_plugin                => 'ml2',
        service_plugins            => ['router', 'metering', 'qos', 'trunk', 'firewall_v2', 'segments', ],
        bind_host                  => $machine_ip,
        use_ssl                    => $use_ssl,
        cert_file                  => $neutron_crt_file,
        key_file                   => $neutron_key_file,
        notification_driver        => 'messagingv2',
        global_physnet_mtu         => $vmnet_mtu_real,
      }
    }else{
      class { '::neutron':
        default_transport_url      => os_transport_url({
          'transport' => $messaging_default_proto,
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => $messaging_default_port,
          'username'  => 'neutron',
          'password'  => $pass_neutron_messaging,
        }),
        notification_transport_url => $neutron_notif_transport_url,
        rabbit_use_ssl             => $use_ssl,
        rabbit_ha_queues           => true,
        kombu_ssl_ca_certs         => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
        amqp_sasl_mechanisms       => 'PLAIN',
        allow_overlapping_ips      => true,
        core_plugin                => 'ml2',
        service_plugins            => ['router', 'metering', 'qos', 'trunk', 'firewall_v2', 'segments', ],
        bind_host                  => $machine_ip,
        use_ssl                    => $use_ssl,
        cert_file                  => $neutron_crt_file,
        key_file                   => $neutron_key_file,
        notification_driver        => 'messagingv2',
        global_physnet_mtu         => $vmnet_mtu_real,
      }
      class { '::neutron::logging':
        debug => true,
      }
    }
    class { '::neutron::client': }
    class { '::neutron::keystone::authtoken':
      password             => $pass_neutron_authtoken,
      auth_url             => $keystone_admin_uri,
      www_authenticate_uri => $keystone_auth_uri,
      memcached_servers    => $memcached_servers,
      cafile               => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
    }
    Service<| title == 'neutron-server'|> -> Openstacklib::Service_validation<| title == 'neutron-server' |> -> Neutron_network<||>
    class { '::neutron::server':
      database_connection   => "mysql+pymysql://neutron:${pass_neutron_db}@${sql_host}/neutrondb?charset=utf8",
      database_idle_timeout => 1800,
      sync_db             => true,
      api_workers         => 2,
      rpc_workers         => 2,
      validate            => true,
      router_distributed  => true,
      allow_automatic_l3agent_failover => true,
      enable_dvr          => true,
      ensure_fwaas_package => true,
      service_providers => [
        'LOADBALANCERV2:Octavia:neutron_lbaas.drivers.octavia.driver.OctaviaDriver:default',
      ]
#        'L2GW:l2gw:networking_l2gw.services.l2gateway.service_drivers.L2gwDriver:default'
    }

    neutron_config {
      'nova/cafile':                       value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
    }

    class { '::neutron::agents::ml2::ovs':
      local_ip                   => $vmnet_ip,
      tunnel_types               => ['vxlan'],
      bridge_uplinks             => ['eth0'],
      bridge_mappings            => $bridge_mapping_list,
#      extensions                 => 'fwaas_v2',
      extensions                 => '',
      l2_population              => true,
      arp_responder              => false,
      firewall_driver            => 'iptables_hybrid',
      drop_flows_on_start        => false,
      enable_distributed_routing => true,
      manage_vswitch             => false,
    }

    class { '::neutron::agents::l3':
      interface_driver => 'openvswitch',
      debug            => true,
      agent_mode       => 'dvr_snat',
      ha_enabled       => false,
#      extensions       => 'fwaas_v2',
      extensions       => '',
    }

    neutron_l3_agent_config {
      'DEFAULT/external_network_bridge': value => '';
    }

    class { '::neutron::plugins::ml2':
      type_drivers         => ['flat', 'vxlan', 'vlan', ],
      tenant_network_types => ['flat', 'vxlan', 'vlan', ],
      extension_drivers    => 'port_security,qos',
      mechanism_drivers    => 'openvswitch,l2population',
      firewall_driver      => 'iptables_v2',
      flat_networks        => $external_network_list,
      network_vlan_ranges  => $external_network_list,
      vni_ranges           => '1000:9999',
      path_mtu             => $vmnet_mtu,
    }

    class { '::neutron::services::lbaas::octavia':
      base_url          => "${base_url}/loadbalance",
      allocates_vip     => true,
      auth_url          => "${base_url}:${api_port}/identity/v3/",
      admin_user        => 'octavia',
      admin_tenant_name => 'services',
      admin_password    => $pass_octavia_authtoken,
    }
    class { '::neutron::server::notifications':
      auth_url => $keystone_admin_uri,
      password => $pass_nova_authtoken,
    }
  }
  ####################
  ### Setup Cinder ###
  ####################
  if $has_subrole_cinder {
    if $has_subrole_db {
      Class['galera'] -> Anchor['cinder::install::begin']
    }
    if $use_ssl {
      oci::sslkeypair {'cinder':
        notify_service_name => 'httpd',
      }
      $cinder_key_file = "/etc/cinder/ssl/private/${::fqdn}.pem"
      $cinder_crt_file = "/etc/cinder/ssl/public/${::fqdn}.crt"
    } else {
      $cinder_key_file = undef
      $cinder_crt_file = undef
    }

    # Cinder endpoints
    if $is_first_master {
      class { '::cinder::keystone::auth':
        public_url      => "${base_url}/volume/v1/%(tenant_id)s",
        internal_url    => "${base_url}/volume/v1/%(tenant_id)s",
        admin_url       => "${base_url}/volume/v1/%(tenant_id)s",
        public_url_v2   => "${base_url}/volume/v2/%(tenant_id)s",
        internal_url_v2 => "${base_url}/volume/v2/%(tenant_id)s",
        admin_url_v2    => "${base_url}/volume/v2/%(tenant_id)s",
        public_url_v3   => "${base_url}/volume/v3/%(tenant_id)s",
        internal_url_v3 => "${base_url}/volume/v3/%(tenant_id)s",
        admin_url_v3    => "${base_url}/volume/v3/%(tenant_id)s",
        password        => $pass_cinder_authtoken,
      }
    }
    if $openstack_release == 'rocky'{
      class { '::cinder':
        debug                 => true,
        default_transport_url => os_transport_url({
          'transport' => $messaging_default_proto,
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => $messaging_default_port,
          'username'  => 'cinder',
          'password'  => $pass_cinder_messaging,
        }),
        database_connection   => "mysql+pymysql://cinder:${pass_cinder_db}@${sql_host}/cinderdb?charset=utf8",
        database_idle_timeout => 1800,
        rabbit_use_ssl        => $use_ssl,
        rabbit_ha_queues      => true,
        kombu_ssl_ca_certs    => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
      }
    }else{
      class { '::cinder':
        default_transport_url => os_transport_url({
          'transport' => $messaging_default_proto,
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => $messaging_default_port,
          'username'  => 'cinder',
          'password'  => $pass_cinder_messaging,
        }),
        database_connection   => "mysql+pymysql://cinder:${pass_cinder_db}@${sql_host}/cinderdb?charset=utf8",
        database_idle_timeout => 1800,
        rabbit_use_ssl        => $use_ssl,
        rabbit_ha_queues      => true,
        kombu_ssl_ca_certs    => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
      }
      class { '::cinder::logging':
        debug => true,
      }
    }
    class { '::cinder::keystone::authtoken':
      password             => $pass_cinder_authtoken,
      auth_url             => $keystone_admin_uri,
      www_authenticate_uri => $keystone_auth_uri,
      memcached_servers    => $memcached_servers,
      cafile               => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
    }
    if $cluster_has_cinder_volumes{
      $default_volume_type = 'LVM_1'
    }else{
      $default_volume_type = 'CEPH_1'
    }
    class { '::cinder::api':
      # Must match what we have in the volumes as enabled backend.
      default_volume_type        => $default_volume_type,
      public_endpoint            => "${base_url}/volume",
      service_name               => 'httpd',
      keymgr_backend             => 'castellan.key_manager.barbican_key_manager.BarbicanKeyManager',
      keymgr_encryption_api_url  => "${base_url}/keymanager",
      keymgr_encryption_auth_url => "${keystone_auth_uri}/v3",
    }
    class { '::cinder::wsgi::apache':
      bind_host => $machine_ip,
      ssl       => $use_ssl,
      ssl_key   => $cinder_key_file,
      ssl_cert  => $cinder_crt_file,
      workers   => 2,
    }
    class { '::cinder::quota': }
    class { '::cinder::scheduler': }
    class { '::cinder::scheduler::filter': }
    class { '::cinder::cron::db_purge': }
    class { '::cinder::glance':
      glance_api_servers => "${base_url}/image",
    }
  }
  #####################
  ### Setup Gnocchi ###
  #####################
  if $has_subrole_gnocchi {
    if $has_subrole_db {
      Class['galera'] -> Anchor['gnocchi::install::begin']
    }
    if $use_ssl {
      oci::sslkeypair {'gnocchi':
        notify_service_name => 'gnocchi-api',
      }
      $gnocchi_key_file = "/etc/gnocchi/ssl/private/${::fqdn}.pem"
      $gnocchi_crt_file = "/etc/gnocchi/ssl/public/${::fqdn}.crt"
    } else {
      $gnocchi_key_file = undef
      $gnocchi_crt_file = undef
    }
    if $openstack_release == 'rocky'{
      class { '::gnocchi':
        debug                 => true,
        database_connection   => "mysql+pymysql://gnocchi:${pass_gnocchi_db}@${sql_host}/gnocchidb?charset=utf8",
      }
    }else{
      class { '::gnocchi':
        database_connection   => "mysql+pymysql://gnocchi:${pass_gnocchi_db}@${sql_host}/gnocchidb?charset=utf8",
      }
      class { '::gnocchi::logging':
        debug => true,
      }
    }
    if $is_first_master {
      class { '::gnocchi::keystone::auth':
        public_url   => "${base_url}/metric",
        internal_url => "${base_url}/metric",
        admin_url    => "${base_url}/metric",
        password     => $pass_gnocchi_authtoken,
      }
    }
    class { '::gnocchi::keystone::authtoken':
      password             => $pass_gnocchi_authtoken,
      auth_url             => $keystone_admin_uri,
      www_authenticate_uri => $keystone_auth_uri,
      memcached_servers    => $memcached_servers,
      cafile               => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
    }
    class { '::gnocchi::api':
      enabled      => true,
      service_name => 'gnocchi-api',
      sync_db      => true,
    }
    class { '::gnocchi::client': }
    class { '::gnocchi::metricd':
      workers       => 2,
      cleanup_delay => 20,
    }
    class { '::gnocchi::storage':
      metric_processing_delay => 10,
#      coordination_url        => "redis://${first_master_ip}:6379/",
      coordination_url        => "zookeeper://${first_master_ip}:2181/",
    }
    class { '::gnocchi::storage::ceph':
      ceph_username => 'openstack',
      ceph_keyring  => '/etc/ceph/ceph.client.openstack.keyring',
      manage_cradox => false,
      manage_rados  => true,
    }

    gnocchi_config {
      'database/connection': value => "mysql+pymysql://gnocchi:${pass_gnocchi_db}@${sql_host}/gnocchidb?charset=utf8", secret => true;
    }

    # make sure ceph pool exists before running gnocchi (dbsync & services)
    Exec['create-gnocchi'] -> Exec['gnocchi-db-sync']

    class { '::gnocchi::statsd':
      archive_policy_name => 'high',
      flush_delay         => '100',
      # random datas:
      resource_id         => $pass_gnocchi_rscuuid,
    }
  }
  ###################
  ### Setup Panko ###
  ###################
  if $has_subrole_panko {
    if $use_ssl {
      oci::sslkeypair {'panko':
        notify_service_name => 'panko-api',
      }
      $panko_key_file = "/etc/panko/ssl/private/${::fqdn}.pem"
      $panko_crt_file = "/etc/panko/ssl/public/${::fqdn}.crt"
    } else {
      $panko_key_file = undef
      $panko_crt_file = undef
    }
    include ::panko
    class { '::panko::db':
      database_connection   => "mysql+pymysql://panko:${pass_panko_db}@${sql_host}/pankodb?charset=utf8",
      database_idle_timeout => 1800,
    }
    if $is_first_master {
      class { '::panko::keystone::auth':
        public_url   => "${base_url}/event",
        internal_url => "${base_url}/event",
        admin_url    => "${base_url}/event",
        password     => $pass_panko_authtoken,
      }
    }
    class { '::panko::keystone::authtoken':
      password             => $pass_panko_authtoken,
      auth_url             => $keystone_admin_uri,
      www_authenticate_uri => $keystone_auth_uri,
      memcached_servers    => $memcached_servers,
      cafile               => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
    }
    class { '::panko::api':
      sync_db      => true,
      enabled      => true,
    }
    if $openstack_release != 'rocky'{
      class { '::panko::logging':
        debug => true,
      }
    }
  }

  ########################
  ### Setup Ceilometer ###
  ########################
  if $has_subrole_ceilometer {
    if $openstack_release == 'rocky'{
      class { '::ceilometer':
        debug                      => true,
        telemetry_secret           => $pass_ceilometer_telemetry,
        default_transport_url      => os_transport_url({
          'transport' => 'rabbit',
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => '5671',
          'username'  => 'ceilometer',
          'password'  => $pass_ceilometer_messaging,
        }),
        notification_transport_url => os_transport_url({
          'transport' => 'rabbit',
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => '5671',
          'username'  => 'ceilometer',
          'password'  => $pass_ceilometer_messaging,
        }),
        rabbit_use_ssl             => $use_ssl,
        rabbit_ha_queues           => true,
        kombu_ssl_ca_certs         => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
        amqp_sasl_mechanisms       => 'PLAIN',
        memcache_servers           => $memcached_servers,
      }
    }else{
      class { '::ceilometer':
        telemetry_secret           => $pass_ceilometer_telemetry,
        default_transport_url      => os_transport_url({
          'transport' => 'rabbit',
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => '5671',
          'username'  => 'ceilometer',
          'password'  => $pass_ceilometer_messaging,
        }),
        notification_transport_url => os_transport_url({
          'transport' => 'rabbit',
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => '5671',
          'username'  => 'ceilometer',
          'password'  => $pass_ceilometer_messaging,
        }),
        rabbit_use_ssl             => $use_ssl,
        rabbit_ha_queues           => true,
        kombu_ssl_ca_certs         => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
        amqp_sasl_mechanisms       => 'PLAIN',
        memcache_servers           => $memcached_servers,
      }
      class { '::ceilometer::logging':
        debug => true,
      }

    }

    # We don't need the endpoint, but we need it as this
    # configures the user and pass for keystone_authtoken.
    class { '::ceilometer::keystone::auth':
      password           => $pass_ceilometer_authtoken,
      configure_endpoint => false,
    }

    class { '::ceilometer::keystone::authtoken':
      password             => $pass_ceilometer_authtoken,
      auth_url             => $keystone_admin_uri,
      www_authenticate_uri => $keystone_auth_uri,
      memcached_servers    => $memcached_servers,
      cafile               => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
    }
    include ::ceilometer::db::sync
    Class['ceilometer::agent::auth'] -> Exec['ceilometer-upgrade']
    if $is_first_master {
      Class['gnocchi::keystone::auth'] -> Exec['ceilometer-upgrade']
    }

    $sample_pipeline_publishers = ['gnocchi://']
    $event_pipeline_publishers = ['gnocchi://', 'panko://']

    class { '::ceilometer::agent::notification':
      notification_workers      => '2',
      manage_pipeline           => true,
      pipeline_publishers       => $sample_pipeline_publishers,
      manage_event_pipeline     => true,
      event_pipeline_publishers => $event_pipeline_publishers,
    }
    class { '::ceilometer::agent::central':
#      coordination_url => "redis://${first_master_ip}:6379/",
      coordination_url => "zookeeper://${first_master_ip}:2181/",
    }
    class { '::ceilometer::agent::auth':
      auth_password => $pass_ceilometer_authtoken,
      auth_url      => $keystone_auth_uri,
      auth_cacert   => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
    }
    class { '::ceilometer::agent::polling':
      manage_polling    => true,
      compute_namespace => true,
      # That's each 5 minutes:
      polling_interval  => 300,
    }
    # Looks like the above sets ca_file instead of cafile.
    ceilometer_config {
      'service_credentials/cafile': value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'service_credentials/www_authenticate_uri': value => "${keystone_auth_uri}";
    }
  }
  ########################
  ### Setup CloudKitty ###
  ########################
  if $has_subrole_cloudkitty {
    if $has_subrole_db {
      Class['galera'] -> Anchor['cloudkitty::install::begin']
      Exec['galera-size-is-correct'] -> Package['cloudkitty dashboard']
#      Class['oci::sql::galera'] -> Package['cloudkitty dashboard']
    }
    if $use_ssl {
      oci::sslkeypair {'cloudkitty':
        notify_service_name => 'cloudkitty-api',
      }
      $cloudkitty_key_file = "/etc/cloudkitty/ssl/private/${::fqdn}.pem"
      $cloudkitty_crt_file = "/etc/cloudkitty/ssl/public/${::fqdn}.crt"
    } else {
      $cloudkitty_key_file = undef
      $cloudkitty_crt_file = undef
    }
    # Note: ::cloudkitty::db *MUST* be declared before ::cloudkitty,
    # because of the include in the ::cloudkitty class.
    class { '::cloudkitty::db':
      database_connection   => "mysql+pymysql://cloudkitty:${pass_cloudkitty_db}@${sql_host}/cloudkittydb?charset=utf8",
      database_idle_timeout => 1800,
    }
    if $disable_notifications {
      $cloudkitty_notif_transport_url = ''
    } else {
      $cloudkitty_notif_transport_url = os_transport_url({
                                          'transport' => 'rabbit',
                                          'hosts'     => fqdn_rotate($all_masters),
                                          'port'      => '5671',
                                          'username'  => 'cloudkitty',
                                          'password'  => $pass_cloudkitty_messaging,
                                        })
    }
    if $openstack_release == 'rocky'{
      class { '::cloudkitty':
        default_transport_url      => os_transport_url({
          'transport' => 'rabbit',
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => '5671',
          'username'  => 'cloudkitty',
          'password'  => $pass_cloudkitty_messaging,
        }),
        notification_transport_url => $cloudkitty_notif_transport_url,
        rabbit_use_ssl             => $use_ssl,
        rabbit_ha_queues           => true,
        kombu_ssl_ca_certs         => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
        amqp_sasl_mechanisms       => 'PLAIN',
        storage_backend            => 'sqlalchemy',
        tenant_fetcher_backend     => 'keystone',
        auth_section               => 'keystone_authtoken',
        host                       => $machine_hostname,
      }
    }else{
      class { '::cloudkitty':
        default_transport_url      => os_transport_url({
          'transport' => 'rabbit',
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => '5671',
          'username'  => 'cloudkitty',
          'password'  => $pass_cloudkitty_messaging,
        }),
        notification_transport_url => $cloudkitty_notif_transport_url,
        rabbit_use_ssl             => $use_ssl,
        rabbit_ha_queues           => true,
        kombu_ssl_ca_certs         => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
        amqp_sasl_mechanisms       => 'PLAIN',
        storage_backend            => 'sqlalchemy',
        storage_version            => '1',
        tenant_fetcher_backend     => 'keystone',
        auth_section               => 'keystone_authtoken',
        host                       => $machine_hostname,
      }
    }
    if $is_first_master {
      class { '::cloudkitty::keystone::auth':
        public_url   => "${base_url}/rating",
        internal_url => "${base_url}/rating",
        admin_url    => "${base_url}/rating",
        password     => $pass_cloudkitty_authtoken,
      }
    }
    class { '::cloudkitty::keystone::authtoken':
      password             => $pass_cloudkitty_authtoken,
      auth_url             => $keystone_admin_uri,
      www_authenticate_uri => $keystone_auth_uri,
      memcached_servers    => $memcached_servers,
      cafile               => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
    }
    class { '::cloudkitty::client': }
    class { '::cloudkitty::api':
      sync_db      => true,
    }

    cloudkitty_config {
      'keystone_fetcher/cafile':      value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'gnocchi_collector/cafile':     value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'coordination/backend_url':     value => "zookeeper://${first_master_ip}:2181/";
      'fetcher/backend':              value => 'gnocchi';
      'fetcher_gnocchi/cafile':       value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'fetcher_gnocchi/auth_section': value => 'keystone_authtoken';
    }

    class { '::cloudkitty::processor': }

    package { 'cloudkitty dashboard':
      name => 'python3-cloudkitty-dashboard',
      ensure => installed,
    }
  }

  ##################
  ### Setup Aodh ###
  ##################
  if $has_subrole_aodh {
    if $has_subrole_db {
      Class['galera'] -> Anchor['aodh::install::begin']
    }
    if $use_ssl {
      oci::sslkeypair {'aodh':
        notify_service_name => 'aodh-api',
      }
      $aodh_key_file = "/etc/aodh/ssl/private/${::fqdn}.pem"
      $aodh_crt_file = "/etc/aodh/ssl/public/${::fqdn}.crt"
    } else {
      $aodh_key_file = undef
      $aodh_crt_file = undef
    }
    if $disable_notifications {
      $aodh_notif_transport_url = ''
    }else{
      $aodh_notif_transport_url = os_transport_url({
                                  'transport' => $messaging_notify_proto,
                                  'hosts'     => fqdn_rotate($all_masters),
                                  'port'      => $messaging_notify_port,
                                  'username'  => 'aodh',
                                  'password'  => $pass_aodh_messaging,
                                })
    }
    if $openstack_release == 'rocky'{
      class { '::aodh':
        debug                      => true,
        default_transport_url      => os_transport_url({
          'transport' => $messaging_default_proto,
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => $messaging_default_port,
          'username'  => 'aodh',
          'password'  => $pass_aodh_messaging,
        }),
        notification_transport_url => $aodh_notif_transport_url,
        rabbit_use_ssl             => $use_ssl,
        rabbit_ha_queues           => true,
        kombu_ssl_ca_certs         => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
        amqp_sasl_mechanisms       => 'PLAIN',
        database_connection        => "mysql+pymysql://aodh:${pass_aodh_db}@${sql_host}/aodhdb?charset=utf8",
        database_idle_timeout      => 1800,
        notification_driver        => 'messagingv2',
      }
    }else{
      class { '::aodh':
        default_transport_url      => os_transport_url({
          'transport' => $messaging_default_proto,
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => $messaging_default_port,
          'username'  => 'aodh',
          'password'  => $pass_aodh_messaging,
        }),
        notification_transport_url => $aodh_notif_transport_url,
        rabbit_use_ssl             => $use_ssl,
        rabbit_ha_queues           => true,
        kombu_ssl_ca_certs         => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
        amqp_sasl_mechanisms       => 'PLAIN',
        database_connection        => "mysql+pymysql://aodh:${pass_aodh_db}@${sql_host}/aodhdb?charset=utf8",
        database_idle_timeout      => 1800,
        notification_driver        => 'messagingv2',
      }
      class { '::aodh::logging':
        debug => true,
      }
    }
    if $is_first_master {
      class { '::aodh::keystone::auth':
        public_url   => "${base_url}/alarm",
        internal_url => "${base_url}/alarm",
        admin_url    => "${base_url}/alarm",
        password     => $pass_aodh_authtoken,
      }
    }
    class { '::aodh::keystone::authtoken':
      password             => $pass_aodh_authtoken,
      auth_url             => $keystone_admin_uri,
      www_authenticate_uri => $keystone_auth_uri,
      memcached_servers    => $memcached_servers,
      cafile               => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
    }
    class { '::aodh::api':
      enabled      => true,
      service_name => 'aodh-api',
      sync_db      => true,
    }
    class { '::aodh::auth':
      auth_url      => $keystone_auth_uri,
      auth_cacert   => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
      auth_password => $pass_aodh_authtoken,
    }
    class { '::aodh::client': }
    class { '::aodh::notifier': }
    class { '::aodh::listener': }
    class { '::aodh::evaluator':
      evaluation_interval => 10,
    }
  }
  #####################
  ### Setup Octavia ###
  #####################
  if $has_subrole_octavia {
    if $has_subrole_db {
      Class['galera'] -> Anchor['octavia::install::begin']
      Exec['galera-size-is-correct'] -> Package['octavia dashboard']
#      Class['oci::sql::galera'] -> Package['octavia dashboard']
    }
    if $use_ssl {
      oci::sslkeypair {'octavia':
        notify_service_name => 'octavia-api',
      }
      $octavia_key_file = "/etc/octavia/ssl/private/${::fqdn}.pem"
      $octavia_crt_file = "/etc/octavia/ssl/public/${::fqdn}.crt"
    } else {
      $octavia_key_file = undef
      $octavia_crt_file = undef
    }
    class { '::octavia::db':
      database_connection   => "mysql+pymysql://octavia:${pass_octavia_db}@${sql_host}/octaviadb?charset=utf8",
      database_idle_timeout => 1800,
    }
    if $disable_notifications {
      $octavia_notif_transport_url = ''
    }else{
      $octavia_notif_transport_url = os_transport_url({
                                       'transport' => $messaging_notify_proto,
                                       'hosts'     => fqdn_rotate($all_masters),
                                       'port'      => $messaging_notify_port,
                                       'username'  => 'octavia',
                                       'password'  => $pass_octavia_messaging,
                                     })
    }
    class { '::octavia':
      default_transport_url      => os_transport_url({
        'transport' => $messaging_default_proto,
        'hosts'     => fqdn_rotate($all_masters),
        'port'      => $messaging_default_port,
        'username'  => 'octavia',
        'password'  => $pass_octavia_messaging,
      }),
      notification_transport_url => $octavia_notif_transport_url,
      rabbit_use_ssl             => $use_ssl,
      rabbit_ha_queues           => true,
      kombu_ssl_ca_certs         => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
      amqp_sasl_mechanisms       => 'PLAIN',
      notification_driver        => 'messagingv2',
    }
    if $is_first_master {
      class { '::octavia::keystone::auth':
        public_url   => "${base_url}/loadbalance",
        internal_url => "${base_url}/loadbalance",
        admin_url    => "${base_url}/loadbalance",
        password     => $pass_octavia_authtoken,
      }
    }
    class { '::octavia::keystone::authtoken':
      password             => $pass_octavia_authtoken,
      auth_url             => $keystone_admin_uri,
      www_authenticate_uri => $keystone_auth_uri,
      memcached_servers    => $memcached_servers,
      cafile               => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
    }
    class { '::octavia::service_auth':
      auth_url            => $keystone_admin_uri,
      username            => 'octavia',
      project_name        => 'services',
      password            => $pass_octavia_authtoken,
      user_domain_name    => 'default',
      project_domain_name => 'default',
      auth_type           => 'password',
    }

    class { '::octavia::api':
      enabled      => true,
      sync_db      => true,
    }

    octavia_config {
      'certificates/ca_certificates_file':      value => '/etc/octavia/certs/server_ca.cert.pem';
      'certificates/ca_certificate':            value => '/etc/octavia/certs/server_ca.cert.pem';
      'certificates/ca_private_key':            value => '/etc/octavia/certs/server_ca.key.pem';
      'certificates/ca_private_key_passphrase': value => 'octavia';
      'controller_worker/client_ca':            value => '/etc/octavia/certs/client_ca.cert.pem';
      'haproxy_amphora/client_cert':            value => '/etc/octavia/certs/client.cert-and-key.pem';
      'haproxy_amphora/server_ca':              value => '/etc/octavia/certs/server_ca.cert.pem';
      'glance/ca_certificates_file':            value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'neutron/ca_certificates_file':           value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'nova/ca_certificates_file':              value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'service_auth/cafile':                    value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
    }

    class { '::octavia::housekeeping':
      spare_amphorae_pool_size => '5',
    }
    class { '::octavia::health_manager':
      heartbeat_key => $pass_octavia_heatbeatkey,
      ip            => $machine_ip,
    }
#    if $openstack_release == 'rocky' {
      class { '::octavia::worker':
        amp_image_tag         => 'amphora',
        loadbalancer_topology => 'ACTIVE_STANDBY',
        amp_secgroup_list     => $amp_secgroup_list,
        amp_boot_network_list => $amp_boot_network_list,
      }
#    }else{
#      class { '::octavia::worker': }
#      class { '::octavia::controller':
#        amp_image_tag         => 'amphora',
#        loadbalancer_topology => 'ACTIVE_STANDBY',
#        amp_secgroup_list     => $amp_secgroup_list,
#        amp_boot_network_list => $amp_boot_network_list,
#      }
#    }
    package { 'octavia dashboard':
      name => 'python3-octavia-dashboard',
      ensure => installed,
    }
  }
  ####################
  ### Setup Magnum ###
  ####################
  if $has_subrole_magnum {
    if $has_subrole_db {
      Class['galera'] -> Anchor['magnum::install::begin']
      Exec['galera-size-is-correct'] -> Package['magnum ui']
    }
    if $use_ssl {
      oci::sslkeypair {'magnum':
        notify_service_name => 'magnum-api',
      }
      $magnum_key_file = "/etc/magnum/ssl/private/${::fqdn}.pem"
      $magnum_crt_file = "/etc/magnum/ssl/public/${::fqdn}.crt"
    } else {
      $magnum_key_file = undef
      $magnum_crt_file = undef
    }
    class { '::magnum::db':
      database_connection   => "mysql+pymysql://magnum:${pass_magnum_db}@${sql_host}/magnumdb?charset=utf8",
      database_idle_timeout => 1800,
    }
    class { '::magnum::keystone::domain':
      cluster_user_trust => 'true',
      domain_admin       => 'magnum_admin',
      domain_admin_email => 'root@localhost',
      domain_password    => $pass_magnum_domain,
      roles              => 'admin',
    }
    class { '::magnum::keystone::authtoken':
      password             => $pass_magnum_authtoken,
      auth_url             => "${keystone_admin_uri}/v3",
      www_authenticate_uri => "${keystone_auth_uri}/v3",
      memcached_servers    => $memcached_servers,
      cafile               => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
    }
    class { '::magnum::api':
      service_name  => 'magnum-api',
      host          => $machine_ip,
      enabled_ssl   => $use_ssl,
      ssl_cert_file => $magnum_crt_file,
      ssl_key_file  => $magnum_key_file,
      sync_db       => true,
    }

    if $is_first_master {
      class { '::magnum::keystone::auth':
        public_url   => "${base_url}/containers/v1",
        internal_url => "${base_url}/containers/v1",
        admin_url    => "${base_url}/containers/v1",
        password     => $pass_magnum_authtoken,
      }
    }

    if $disable_notifications {
      $magnum_notif_transport_url = ''
    }else{
      $magnum_notif_transport_url = os_transport_url({
                                      'transport' => $messaging_notify_proto,
                                      'hosts'     => fqdn_rotate($all_masters),
                                      'port'      => $messaging_notify_port,
                                      'username'  => 'magnum',
                                      'password'  => $pass_magnum_messaging,
                                    })
    }
    if $openstack_release == 'rocky' {
      class { '::magnum':
        default_transport_url      => os_transport_url({
          'transport' => $messaging_default_proto,
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => $messaging_default_port,
          'username'  => 'magnum',
          'password'  => $pass_magnum_messaging,
        }),
        notification_transport_url => $magnum_notif_transport_url,
        rabbit_use_ssl             => $use_ssl,
        rabbit_ha_queues           => true,
        kombu_ssl_ca_certs         => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
        notification_driver        => 'messagingv2',
      }
    }else{
      class { '::magnum':
        default_transport_url      => os_transport_url({
          'transport' => $messaging_default_proto,
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => $messaging_default_port,
          'username'  => 'magnum',
          'password'  => $pass_magnum_messaging,
        }),
        notification_transport_url => $magnum_notif_transport_url,
        rabbit_use_ssl             => $use_ssl,
        rabbit_ha_queues           => true,
        kombu_ssl_ca_certs         => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
        amqp_sasl_mechanisms       => 'PLAIN',
        notification_driver        => 'messagingv2',
      }
    }

    class { '::magnum::conductor':
      workers => '4',
    }

    class { '::magnum::client': }

    class { '::magnum::certificates':
      cert_manager_type => 'barbican'
#      cert_manager_type => 'local'
    }

    magnum_config {
      'keystone_auth/auth_section': value => 'keystone_authtoken';
      'glance_client/ca_file':      value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'heat_client/ca_file':        value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'neutron_client/ca_file':     value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'nova_client/ca_file':        value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'octavia_client/ca_file':     value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
    }

    package { 'magnum ui':
      name => 'python3-magnum-ui',
      ensure => installed,
    }
  }
}
