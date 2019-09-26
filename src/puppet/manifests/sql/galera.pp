class oci::sql::galera(
  $machine_ip          = undef,
  $all_sql             = undef,
  $all_sql_ip          = undef,
  $first_sql           = undef,
  $pass_mysql_rootuser = undef,
  $pass_mysql_backup   = undef,
){
  class { 'galera':
    galera_servers        => $all_sql_ip,
    galera_master         => $first_sql,
    package_ensure        => 'latest',
    mysql_package_name    => 'mariadb-server',
    client_package_name   => 'default-mysql-client',
    vendor_type           => 'mariadb',
    root_password         => $pass_mysql_rootuser,
    status_password       => $pass_mysql_rootuser,
    deb_sysmaint_password => $pass_mysql_rootuser,
    configure_repo        => false,
    configure_firewall    => false,
    galera_package_name   => 'galera-3',
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
        'wsrep_sst_method'                => 'xtrabackup-v2',
      }
    }
  }

  # Wait until SHOW STATUS LIKE 'wsrep_cluster_status' shows Primary
  exec {'galera-is-up':
    command => "/usr/bin/oci-wait-for-sql \"SHOW STATUS LIKE 'wsrep_cluster_status'\" Primary mysql 2800",
    unless  => '/bin/false # comment to satisfy puppet syntax requirements',
    require  => Class['galera'],
    timeout => 3000,
  }

  # Wait until SHOW STATUS LIKE 'wsrep_connected' shows ON
  exec {'galera-wsrep-connected-on':
    command => "/usr/bin/oci-wait-for-sql \"SHOW STATUS LIKE 'wsrep_connected'\" ON mysql 2800",
    unless  => '/bin/false # comment to satisfy puppet syntax requirements',
    require => Exec['galera-is-up'],
    timeout => 3000,
  }

  # Wait until SHOW STATUS LIKE 'wsrep_local_state_comment' shows Synced
  exec {'galera-is-synced':
    command => "/usr/bin/oci-wait-for-sql \"SHOW STATUS LIKE 'wsrep_local_state_comment'\" Synced mysql 280",
    unless  => '/bin/false # comment to satisfy puppet syntax requirements',
    require => Exec['galera-wsrep-connected-on'],
    timeout => 3000,
  }

  # Wait until all nodes are connected to the cluster
  $galera_cluster_num_of_nodes = sprintf('%i', $all_sql.size)
  exec {'galera-size-is-correct':
    command => "/usr/bin/oci-wait-for-sql \"SHOW STATUS LIKE 'wsrep_cluster_size'\" ${galera_cluster_num_of_nodes} mysql 2800",
    unless  => '/bin/false # comment to satisfy puppet syntax requirements',
    require => Exec['galera-is-synced'],
    timeout => 3000,
  }

  mysql_user { 'backup@%':
    ensure        => present,
    password_hash => mysql_password($pass_mysql_backup),
    require       => Exec['galera-size-is-correct'],
  }->
  mysql_grant{'backup@%/*.*':
    ensure     => 'present',
    options    => ['GRANT'],
    privileges => ['ALL'],
    table      => '*.*',
    user       => 'backup@%',
  }
}