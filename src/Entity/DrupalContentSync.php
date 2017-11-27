<?php

namespace Drupal\drupal_content_sync\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the DrupalContentSync entity.
 *
 * @ConfigEntityType(
 *   id = "drupal_content_sync",
 *   label = @Translation("DrupalContentSync Synchronization"),
 *   handlers = {
 *     "list_builder" = "Drupal\drupal_content_sync\Controller\DrupalContentSyncListBuilder",
 *     "form" = {
 *       "add" = "Drupal\drupal_content_sync\Form\DrupalContentSyncForm",
 *       "edit" = "Drupal\drupal_content_sync\Form\DrupalContentSyncForm",
 *       "delete" = "Drupal\drupal_content_sync\Form\DrupalContentSyncDeleteForm",
 *     }
 *   },
 *   config_prefix = "sync",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/system/drupal_content_sync/synchronizations/{drupal_content_sync}",
 *     "delete-form" = "/admin/config/system/drupal_content_sync/synchronizations/{drupal_content_sync}/delete",
 *   }
 * )
 */
class DrupalContentSync extends ConfigEntityBase implements DrupalContentSyncInterface {

  /**
   * The DrupalContentSync ID.
   *
   * @var string
   */
  public $id;

  /**
   * The DrupalContentSync name.
   *
   * @var string
   */
  public $name;

  /**
   * The DrupalContentSync entities.
   *
   * @var string
   */
  public $sync_entities;

}
