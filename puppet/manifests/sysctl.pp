define oci::sysctl(
){
  # Setup some useful sysctl customization
  sysctl::value { 'net.ipv4.neigh.default.gc_thresh1':
    value  => '2048',
    target => '/etc/sysctl.d/40-ipv4-neigh-1.conf',
  }

  sysctl::value { 'net.ipv4.neigh.default.gc_thresh2':
    value  => '4096',
    target => '/etc/sysctl.d/40-ipv4-neigh-2.conf',
  }

  sysctl::value { 'net.ipv4.neigh.default.gc_thresh3':
    value  => '8192',
    target => '/etc/sysctl.d/40-ipv4-neigh-3.conf',
  }

  sysctl::value { 'net.netfilter.nf_conntrack_max':
    value  => '1048576',
    target => '/etc/sysctl.d/40-nf-conntrack-max-1.conf',
  }

  sysctl::value { 'net.nf_conntrack_max':
    value  => '1048576',
    target => '/etc/sysctl.d/40-nf-conntrack-max-2.conf',
  }

  sysctl::value { 'vm.swappiness':
    value  => '1',
    target => '/etc/sysctl.d/50-vm-swappiness.conf',
  }
}
