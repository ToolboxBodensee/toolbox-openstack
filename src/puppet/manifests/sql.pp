# a sql node through the OCI's ENC:
#
#---
#classes:
#   oci::sql:
#      machine_hostname: z-sql-1.infomaniak.ch
#      machine_ip: 192.168.101.14
#      time_server_host: ntp.infomaniak.ch
#      network_ipaddr: 192.168.101.0
#      network_cidr: 24
#      first_sql: z-sql-1.infomaniak.ch
#      first_sql_ip: 192.168.101.14
#      is_first_sql: true
#      pass_mysql_rootuser: 58db6fbfc79c59cc761d032a298777a2fe6759996e9e24782275399cf4acfcb5
#      pass_keystone_db: ec847594163ef92e7354ee7af5f74bfd54aabf8a6835f0a2a9503fa0ef0430c3
#      pass_nova_db: e19eabe56e48dc4593566ef5c087e6a471f90af6a478f413d8d828e7150b88e7
#      pass_nova_apidb: c5733b71416b60f8500f9ef54306fd6b529a10f7952c32f86d3fca992645ad9b
#      pass_placement_db: cfbf38c7c9254d8783831ce2c6422fde511682422bd6e3c52370cb328b6921a6
#      pass_glance_db: 3ea88d7e2a3c4b9680498c41d181fbac0e84811834e7c7047f0740cb71f84e1f
#      pass_neutron_db: 2d75322e104c4ee4308e5af2bb415fa010dc976453da3ce5400e2b00a81fe0e7
#      pass_cinder_db: 3b00e2621e549f03ddf5d9e094d687a3801893ed25d451411fdc2f307099e153
#      pass_heat_db: f451a897f111204d06ae39eed29b9df3ab601d122437b6bfcaa8c76e4349c69d
#      pass_barbican_db: ed3f08745afbbc89197c415f9d54680c93f1f059e74d578932694d36415bf623
#      pass_gnocchi_db: AQBDu8Fcb/X0MRAAmxRvmLQNXVAowc+9pwtnSQ==
#      pass_panko_db: 3e2e648bce9278451e0b0090dc488bb02f7c19587354edf8038dfa8717b40fb1
#      pass_ceilometer_db: 399499c1e0091dfcc87577c2ab30b1f6c978b816245feeb84d075e2806167cda
#      pass_cloudkitty_db: 83996d58ca8cf63b0f1483227a68751d40c8de80acdb0bdd867c0cda8d8c1f9c
#      pass_aodh_db: c05fd571789c188d2dcbba6a3d32b1bd963ccb0fd4ad444e6e723b48ea14c4a6
#      pass_octavia_db: 6fc376c60298dea2744abefec1383a4040b3a577e93bd35f6d3d7ba8dd311694
#
# This is re-used in the oci_controller class below
#
class oci::sql(
  $cluster_name           = undef,
  $machine_hostname       = undef,
  $machine_ip             = undef,
  $etc_hosts              = undef,
  $time_server_host       = undef,
  $network_ipaddr         = undef,
  $network_cidr           = undef,

  $first_sql              = undef,
  $first_sql_ip           = undef,
  $is_first_sql           = undef,
  $sql_vip_ip             = undef,
  $sql_vip_netmask        = undef,
  $sql_vip_iface          = undef,

  $all_sql                = undef,
  $all_sql_ip             = undef,
  $all_sql_ids            = undef,
  $non_master_sql         = undef,
  $non_master_sql_ip      = undef,

  $has_subrole_glance     = true,
  $has_subrole_nova       = true,
  $has_subrole_neutron    = true,
  $has_subrole_aodh       = true,
  $has_subrole_octavia    = true,
  $has_subrole_cinder     = true,
  $has_subrole_gnocchi    = true,
  $has_subrole_ceilometer = true,
  $has_subrole_panko      = true,
  $has_subrole_cloudkitty = true,
  $has_subrole_heat       = true,
  $has_subrole_barbican   = true,

  $pass_mysql_rootuser    = undef,
  $pass_mysql_backup      = undef,
  $pass_keystone_db       = undef,
  $pass_nova_db           = undef,
  $pass_nova_apidb        = undef,
  $pass_placement_db      = undef,
  $pass_glance_db         = undef,
  $pass_neutron_db        = undef,
  $pass_cinder_db         = undef,
  $pass_heat_db           = undef,
  $pass_barbican_db       = undef,
  $pass_gnocchi_db        = undef,
  $pass_panko_db          = undef,
  $pass_ceilometer_db     = undef,
  $pass_cloudkitty_db     = undef,
  $pass_aodh_db           = undef,
  $pass_octavia_db        = undef,
){

  ::oci::sysctl { 'oci-rox': }

  # Right on time!
  class { '::oci::chrony': time_server_host => $time_server_host, }

  class { '::oci::haproxy': }

  class { '::oci::sql::haproxy':
    sql_vip_ip        => $sql_vip_ip,
    first_sql         => $first_sql,
    first_sql_ip      => $first_sql_ip,
    non_master_sql    => $non_master_sql,
    non_master_sql_ip => $non_master_sql_ip,
  }

  ################################
  ### Add a virtual IP address ###
  ################################
  class {'::oci::corosync':
    machine_ip      => $machine_ip,
    all_machine_ips => $all_sql_ip,
    all_machine_ids => $all_sql_ids,
  }
  oci::vip {'openstack-sql-vip':
    vip_ip      => $sql_vip_ip,
    vip_netmask => $sql_vip_netmask,
    vip_iface   => $vip_sql_iface,
  }

  class {'::oci::sql::galera':
    machine_ip          => $machine_ip,
    all_sql             => $all_sql,
    all_sql_ip          => $all_sql_ip,
    first_sql           => $first_sql,
    pass_mysql_rootuser => $pass_mysql_rootuser,
    pass_mysql_backup   => $pass_mysql_backup,
  }

  if $is_first_sql {
    class { '::keystone::db::mysql':
      dbname   => 'keystonedb',
      password => $pass_keystone_db,
      allowed_hosts => '%',
      require  => Exec['galera-size-is-correct'],
    }
    if $has_subrole_glance {
      class { '::glance::db::mysql':
        dbname   => 'glancedb',
        password => $pass_glance_db,
        allowed_hosts => '%',
        require  => Exec['galera-size-is-correct'],
      }
    }
    if $has_subrole_heat {
      class { '::heat::db::mysql':
        dbname   => 'heatdb',
        password => $pass_heat_db,
        allowed_hosts => '%',
        require  => Exec['galera-size-is-correct'],
      }
    }
    if $has_subrole_barbican {
      class { '::barbican::db::mysql':
        dbname   => 'barbicandb',
        password => $pass_barbican_db,
        allowed_hosts => '%',
        require  => Exec['galera-size-is-correct'],
      }
    }
    if $has_subrole_nova {
      class { '::nova::db::mysql':
        user     => 'nova',
        dbname   => 'novadb',
        password => $pass_nova_db,
        allowed_hosts => '%',
        require  => Exec['galera-size-is-correct'],
      }
      class { '::nova::db::mysql_api':
        user     => 'novaapi',
        dbname   => 'novaapidb',
        password => $pass_nova_apidb,
        allowed_hosts => '%',
        require  => Exec['galera-size-is-correct'],
      }
      class { '::placement::db::mysql':
        user => 'placement',
        dbname => 'placementdb',
        password => $pass_placement_db,
        allowed_hosts => '%',
        require  => Exec['galera-size-is-correct'],
      }
    }
    if $has_subrole_neutron {
      class { '::neutron::db::mysql':
        dbname   => 'neutrondb',
        password => $pass_neutron_db,
        allowed_hosts => '%',
        require  => Exec['galera-size-is-correct'],
      }
    }
    if $has_subrole_cinder {
      class { '::cinder::db::mysql':
        dbname        => 'cinderdb',
        password      => $pass_cinder_db,
        allowed_hosts => '%',
        require  => Exec['galera-size-is-correct'],
      }
    }
    if $has_subrole_gnocchi {
      class { '::gnocchi::db::mysql':
        dbname   => 'gnocchidb',
        password => $pass_gnocchi_db,
        allowed_hosts => '%',
        require  => Exec['galera-size-is-correct'],
      }
    }
    if $has_subrole_panko {
      class { '::panko::db::mysql':
        dbname   => 'pankodb',
        password => $pass_panko_db,
        allowed_hosts => '%',
        require  => Exec['galera-size-is-correct'],
      }
    }
    if $has_subrole_cloudkitty {
      class { '::cloudkitty::db::mysql':
        dbname   => 'cloudkittydb',
        password => $pass_cloudkitty_db,
        allowed_hosts => '%',
        require  => Exec['galera-size-is-correct'],
      }
    }
    if $has_subrole_aodh {
      class { '::aodh::db::mysql':
        dbname   => 'aodhdb',
        password => $pass_aodh_db,
        allowed_hosts => '%',
        require  => Exec['galera-size-is-correct'],
      }
    }
    if $has_subrole_octavia {
      class { '::octavia::db::mysql':
        dbname   => 'octaviadb',
        password => $pass_octavia_db,
        allowed_hosts => '%',
        require  => Exec['galera-size-is-correct'],
      }
    }
  }
}
