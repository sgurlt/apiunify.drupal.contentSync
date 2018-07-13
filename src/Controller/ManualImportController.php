<?php

namespace Drupal\drupal_content_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\drupal_content_sync\ApiUnifyPoolExport;
use Drupal\drupal_content_sync\Entity\Flow;
use Drupal\drupal_content_sync\Entity\Pool;
use Drupal\drupal_content_sync\ImportIntent;

/**
 * Provides a listing of Flow.
 */
class ManualImportController extends ControllerBase {

  /**
   * Render the content synchronization Angular frontend.
   *
   * @return array
   */
  public function content() {
    /*$config_path    = drupal_get_path('module', 'drupal_content_sync') . '/config/install';
    $source         = new \Drupal\Core\Config\FileStorage($config_path);
    $config_storage = \Drupal::service('config.storage');

    $configsNames = [
      'rest.resource.drupal_content_sync_import_entity',
    ];

    foreach ($configsNames as $name) {
      $config_storage->write($name, $source->read($name));
    }

    drupal_set_message('Imported config.');*/

    $config = [
      'pools' => [],
      'flows' => [],
      'api_version' => ApiUnifyPoolExport::CUSTOM_API_VERSION,
      'entity_types' => [],
    ];

    $pools = Pool::getAll();
    foreach ($pools as $id=>$pool) {
      $config['pools'][$pool->id] = [
        'id' => $pool->id,
        'label' => $pool->label,
        'site_id' => $pool->site_id,
      ];
    }

    foreach(Flow::getAll() as $id=>$flow) {
      $config['flows'][$flow->id] = [
        'id' => $flow->id,
        'name' => $flow->name,
      ];

      foreach ($flow->getEntityTypeConfig() as $definition) {
        if (!$flow->canImportEntity($definition['entity_type_name'], $definition['bundle_name'], ImportIntent::IMPORT_MANUALLY)) {
          continue;
        }
        $index = $definition['entity_type_name'] . '.' . $definition['bundle_name'];
        if (!isset($config['entity_types'][$index])) {
          $config['entity_types'][$index] = [
            'entity_type_name' => $definition['entity_type_name'],
            'bundle_name' => $definition['bundle_name'],
            'version' => $definition['version'],
            'pools' => [],
            'preview' => $definition['preview'],
          ];
        }
        else {
          if($config['entity_types'][$index]['preview']==Flow::PREVIEW_DISABLED || $definition['preview']!=Flow::PREVIEW_TABLE) {
            $config['entity_types'][$index]['preview'] = $definition['preview'];
          }
        }

        foreach ($definition['import_pools'] as $id => $action) {
          if (!isset($config['entity_types'][$index]['pools'][$id]) ||
            $action == Pool::POOL_USAGE_FORCE ||
            $config['entity_types'][$index]['pools'][$id] == Pool::POOL_USAGE_FORBID) {
            $config['entity_types'][$index]['pools'][$id] = $action;
          }
        }
      }
    }

    if(empty($config['entity_types'])) {
      drupal_set_message(t('This flow doesn\'t have an entity types to be imported manually.'));
    }

    return [
      '#theme' => 'drupal_content_sync_content_dashboard',
      '#configuration' => $config,
      '#attached' => ['library' => ['drupal_content_sync/drupal-content-synchronization']],
    ];
  }

}
