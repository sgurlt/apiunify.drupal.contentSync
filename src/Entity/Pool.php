<?php

namespace Drupal\drupal_content_sync\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

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

  // Your specific configuration property get/set methods go here,
  // implementing the interface.
}