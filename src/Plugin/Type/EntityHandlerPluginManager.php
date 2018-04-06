<?php

namespace Drupal\drupal_content_sync\Plugin\Type;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages discovery and instantiation of resource plugins.
 *
 * @see \Drupal\rest\Annotation\RestResource
 * @see \Drupal\rest\Plugin\ResourceBase
 * @see \Drupal\rest\Plugin\ResourceInterface
 * @see plugin_api
 */
class EntityHandlerPluginManager extends DefaultPluginManager {

  /**
   * Constructs a new \Drupal\drupal_content_sync\Plugin\Type\EntityHandlerPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/drupal_content_sync/entity_handler', $namespaces, $module_handler, 'Drupal\drupal_content_sync\Plugin\EntityHandlerInterface', 'Drupal\drupal_content_sync\Annotation\EntityHandler');

    $this->setCacheBackend($cache_backend, 'dcs_entity_handler_plugins');
    $this->alterInfo('dcs_entity_handler');
  }

  /**
   * {@inheritdoc}
   *
   * @deprecated in Drupal 8.2.0.
   *   Use Drupal\rest\Plugin\Type\ResourcePluginManager::createInstance()
   *   instead.
   *
   * @see https://www.drupal.org/node/2874934
   */
  public function getInstance(array $options) {
    if (isset($options['id'])) {
      return $this->createInstance($options['id']);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function findDefinitions() {
    $definitions = parent::findDefinitions();
    uasort($definitions, function ($a, $b) {
      return $a['weight'] <=> $b['weight'];
    });
    return $definitions;
  }

  /**
   *
   * @return array
   */
  public function getHandlerOptions($entity_type,$bundle,$labels_only=FALSE) {
    $options = [];

    foreach($this->getDefinitions() as $id=> $definition) {
      if( !$definition['class']::supports($entity_type,$bundle) ) {
        continue;
      }
      $options[$id] = $labels_only ? $definition['label']->render() : $definition;
    }

    return $options;
  }

}