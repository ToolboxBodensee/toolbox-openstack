define oci::vip(
  $vip_ip      = undef,
  $vip_netmask = undef,
  $vip_iface   = undef,
){
  cs_primitive { $name:
    primitive_class => 'ocf',
    primitive_type  => 'IPaddr2',
    provided_by     => 'heartbeat',
    parameters      => { 'ip' => $vip_ip, 'cidr_netmask' => $vip_netmask, 'nic' => $sql_iface },
    operations      => { 'monitor' => { 'interval' => '10s' } },
    require         => Cs_property['no-quorum-policy'],
  }
}
