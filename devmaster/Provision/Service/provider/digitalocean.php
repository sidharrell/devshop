<?php

use DigitalOceanV2\Adapter\BuzzAdapter;
use DigitalOceanV2\DigitalOceanV2;


class Provision_Service_provider_digital_ocean extends Provision_Service_provider {

  public $provider = 'digital_ocean';


  function verify_server_cmd() {

    $digitalocean = $this->load_api();
    $droplet = $digitalocean->droplet();
    $cloud = $droplet->getById($this->server->provider_server_identifier);
    if ($cloud->status == 'active') {

      $ips = array();
      foreach ($cloud->networks as $network) {
        $ips[] = $network->ipAddress;
      }

      drush_set_option('ip_addresses', $ips);
      drush_log('[DEVSHOP] Cloud Server IPs updated.', 'ok');
    }
    else {
      drush_set_error('DEVSHOP_CLOUD_SERVER_NOT_ACTIVE', dt('The remote cloud server is not in an active state.'));
    }
  }


  /**
   * This method is called once per server verify, triggered from hosting task.
   *
   * $this->server: Provision_Context_server
   */
  function save_server() {
    // Look for provider_server_identifier
    $server_identifier = $this->server->provider_server_identifier;

    // If server ID is already found, move on.
    if (!empty($server_identifier)) {
      drush_log('[DEVSHOP] Server Identifier Found: ' . $server_identifier . '  Not creating new server.', 'ok');
    }
    // If there is no server ID, create the server.
    else {
      drush_log('[DEVSHOP] Server Identifier not found.  Creating new server!', 'ok');

      $options = $this->server->provider_options;
      $digitalocean = $this->load_api();
      $droplet = $digitalocean->droplet();

      //$cloud_config = !empty($options['cloud_config']) ? $options['cloud_config'] :  $this->default_cloud_config();

      if ($options['remote_server']) {
        $cloud_config = $this->default_cloud_config();
      }

      $created = $droplet->create($this->server->remote_host, $options['region'], $options['size'], $options['image'],
        $options['backups'], $options['ipv6'], $options['private_networking'], array_filter($options['keys']), $cloud_config);

      $this->server->setProperty('provider_server_identifier', $created->id);
      drush_log("[DEVSHOP] Server Identifier found: $created->id. Assumed server was created.", 'ok');
    }
  }

  function load_api(){
    $token = drush_get_option('digital_ocean_token');
    require_once dirname(__FILE__) . '/digital-ocean-master/vendor/autoload.php';
    require_once dirname(__FILE__) . '/digital-ocean-master/src/DigitalOceanV2.php';

    $adapter = new BuzzAdapter($token);
    $digitalocean = new DigitalOceanV2($adapter);
    return $digitalocean;
  }


  function default_cloud_config() {

    $config = "#cloud-config
users:
  - name: aegir
    groups: sudo, www-data
    shell: /bin/bash
    homedir: /var/aegir
    sudo: ['ALL=(ALL) NOPASSWD:ALL']
    ssh-authorized-keys:
      - SSHKEY
runcmd:
  - ln -s /var/aegir/config/nginx.conf /etc/nginx/conf.d/aegir.conf
  - mysql -u root -p -e 'GRANT ALL PRIVILEGES ON *.* TO root@aegir_server_ip IDENTIFIED BY 'password' WITH GRANT OPTION'
  - mysql -u root -p -e 'FLUSH PRIVILEGES'
";
    $ssh_key = file_get_contents('/var/aegir/.ssh/id_rsa.pub');
    $config = str_replace('SSHKEY', $ssh_key, $config);

    // todo replace nginx symlink with selected http service type.
    // a2enmod rewrite
    // ln -s /var/aegir/config/apache.conf /etc/apache2/conf.d/aegir.conf

    return $config;
  }


}