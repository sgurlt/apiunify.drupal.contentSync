<?php

namespace Drupal\drupal_content_sync\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use \Drupal\drupal_content_sync\Form\DrupalContentSyncForm;

/**
 * Defines the Example entity.
 *
 * @ConfigEntityType(
 *   id = "dcs_pool",
 *   label = @Translation("Pool"),
 *   handlers = {
 *     "list_builder" = "Drupal\drupal_content_sync\Controller\PoolListBuilder",
 *     "form" = {
 *       "add" = "Drupal\drupal_content_sync\Form\PoolForm",
 *       "edit" = "Drupal\drupal_content_sync\Form\PoolForm",
 *       "delete" = "Drupal\drupal_content_sync\Form\PoolDeleteForm",
 *     }
 *   },
 *   config_prefix = "pool",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/services/drupal_content_sync/pool/{dcs_pool}/edit",
 *     "delete-form" = "/admin/config/services/drupal_content_sync/synchronizations/{dcs_pool}/delete",
 *   }
 * )
 */
class Pool extends ConfigEntityBase implements PoolInterface {

  /**
   * The Example ID.
   *
   * @var string
   */
  public $id;

  /**
   * The Example label.
   *
   * @var string
   */
  public $label;

  /**
   * The Example label.
   *
   * @var string
   */
  public $backend_url;

  /**
   * The Example label.
   *
   * @var string
   */
  public $site_id;

  /**
   *
   */
  public static $all = NULL;

  /**
   * Returns the Drupal Content Sync Backend URL for this pool.
   *
   * @return string
   */
  public function getBackendUrl() {
    return $this->backend_url;
  }

  /**
   * Returns the site id this pool.
   *
   * @return string
   */
  public function getSiteId() {
    return $this->site_id;
  }

  /**
   *
   * Load all dcs_pool entities.
   *
   * @return \Drupal\drupal_content_sync\Entity\DrupalContentSync[]
   */
  public static function getAll() {

    /**
     * @var \Drupal\drupal_content_sync\Entity\DrupalContentSync[] $configurations
     */
    $configurations = \Drupal::entityTypeManager()
      ->getStorage('dcs_pool')
      ->loadMultiple();

    return $configurations;
  }

  /**
   * Returns an list of pools that can be selected for an entity type.
   *
   * @param null $entity_type
   */
  public static function getSelectablePools($entity_type, $bundle) {

    // Get all available flows.
    $flows = DrupalContentSync::getAll();
    $configs = [];
    $selectable_pools = [];
    $selectable_flows = [];
    foreach ($flows as $flow_id => $flow) {
      $flow_entity_config = $flow->getEntityTypeConfig($entity_type, $bundle);
      if ($flow_entity_config['handler'] != 'ignore' && $flow_entity_config['export'] != 'disabled') {

        $selectable_flows[$flow_id] = $flow;

        $configs[$flow_id] = [
          'flow_label' => $flow->label(),
          'flow' => $flow->getEntityTypeConfig($entity_type, $bundle)
        ];

      }
    }
    foreach ($configs as $config_id => $config) {
      if (in_array('allow', $config['flow']['export_pools'])) {
        $selectable_pools[$config_id]['flow_label'] = $config['flow_label'];
        $selectable_pools[$config_id]['widget_type'] = $config['flow']['pool_export_widget_type'];
        foreach ($config['flow']['export_pools'] as $pool_id => $export_pool) {

          // Filter out all pools with configuration "allow".
          if ($export_pool == DrupalContentSyncForm::POOL_ALLOW) {
            $pool_entity = \Drupal::entityTypeManager()->getStorage('dcs_pool')->loadByProperties(['id' => $pool_id]);
            $pool_entity = reset($pool_entity);
            $selectable_pools[$config_id]['pools'][$pool_id] = $pool_entity->label();
          }
        }
      }

    }
    return $selectable_pools;
  }
}
