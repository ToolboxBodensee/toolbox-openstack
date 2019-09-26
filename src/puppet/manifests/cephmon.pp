# Install and configure a CEPH MON node for OCI
#
# == Parameters
#
# [*ceph_admin_key*]
# example: 'AQCTg71RsNIHORAAW+O6FCMZWBjmVfMIPk3MhQ=='
#
# [*ceph_mon_key*]
# example: 'AQDesGZSsC7KJBAAw+W/Z4eGSQGAIbxWjxjvfw=='
#
# [*bootstrap_osd_key*]
# example: 'AQABsWZSgEDmJhAAkAGSOOAJwrMHrM5Pz5On1A=='
#
# [*fsid*]
# example: '066F558C-6789-4A93-AAF1-5AF1BA01A3AD'
#
# [*ceph_mon_initial_members*]
# example: 'mon1,mon2,mon3'
#
# [*ceph_mon_host*]
# example: '<ip of mon1>,<ip of mon2>,<ip of mon3>'
#
class oci::cephmon(
  $machine_hostname         = undef,
  $machine_ip               = undef,
  $etc_hosts                = undef,
  $time_server_host         = undef,
  $all_masters              = undef,
  $all_masters_ip           = undef,
  $vip_hostname             = undef,
  $vip_ipaddr               = undef,
  $vip_netmask              = undef,
  $network_ipaddr           = undef,
  $network_cidr             = undef,
  $use_ssl                  = true,

  $ceph_admin_key           = undef,
  $ceph_openstack_key       = undef,
  $ceph_mon_key             = undef,
  $ceph_bootstrap_osd_key   = undef,
  $ceph_fsid                = undef,
  $ceph_mon_initial_members = undef,
  $ceph_mon_host            = undef,
  $ceph_mgr_key             = undef,
){

  $mon_keyring_path = "/tmp/ceph-${machine_hostname}.keyring"

  ::oci::sysctl { 'oci-rox': }

  class { '::oci::etchosts': etc_hosts_file => $etc_hosts, }

  # Right on time!
  class { '::oci::chrony': time_server_host => $time_server_host, }

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
  }->
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
osd crush chooseleaf type = 0
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
