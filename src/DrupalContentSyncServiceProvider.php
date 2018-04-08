<?php

namespace Drupal\drupal_content_sync;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;

/**
 * Drupal Content Sync Service Provider.
 */
class DrupalContentSyncServiceProvider extends ServiceProviderBase implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('webhooks.service');
    $definition->setClass('Drupal\drupal_content_sync\DrupalContentSyncWebhookService');
  }

}
