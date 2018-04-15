<?php

namespace Drupal\drupal_content_sync\Plugin;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\drupal_content_sync\ApiUnifyRequest;

/**
 * Specifies the publicly available methods of a resource plugin.
 *
 * @see \Drupal\rest\Annotation\RestResource
 * @see \Drupal\rest\Plugin\Type\ResourcePluginManager
 * @see \Drupal\rest\Plugin\ResourceBase
 * @see plugin_api
 *
 * @ingroup third_party
 */
interface EntityHandlerInterface extends PluginInspectionInterface {

  /**
   * @ToDo: Add description.
   */
  public static function supports($entity_type, $bundle);

  /**
   * @ToDo: Add description.
   */
  public function getAllowedExportOptions();

  /**
   * @ToDo: Add description.
   */
  public function getAllowedSyncImportOptions();

  /**
   * @ToDo: Add description.
   */
  public function getAllowedClonedImportOptions();

  /**
   * @ToDo: Add description.
   */
  public function getAllowedPreviewOptions();

  /**
   * @ToDo: Add description.
   */
  public function getHandlerSettings();

  /**
   * @ToDo: Add description.
   */
  public function updateEntityTypeDefinition(&$definition);

  /**
   * @param \Drupal\drupal_content_sync\ApiUnifyRequest $request
   *   The request
   *   containing all relevant data and where the result is stored as well.
   * @param bool $is_clone
   *   Whether or not the entity should be clone'd or sync'd.
   * @param string $reason
   *   See DrupalContentSync::IMPORT_*.
   * @param string $action
   *   See DrupalContentSync::ACTION_*.
   *
   * @throws \Drupal\drupal_content_sync\Exception\SyncException
   *
   * @return bool Whether or not the content has been imported. FALSE is a
   *   desired state, meaning nothing was to do according to config.
   */
  public function import(ApiUnifyRequest $request, $is_clone, $reason, $action);

  /**
   * @param \Drupal\drupal_content_sync\ApiUnifyRequest $request
   *   The request to
   *   store all relevant info at.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to export.
   * @param string $reason
   *   See DrupalContentSync::EXPORT_*.
   * @param string $action
   *   See DrupalContentSync::ACTION_*.
   *
   * @throws \Drupal\drupal_content_sync\Exception\SyncException
   *
   * @return bool Whether or not the content has been exported. FALSE is a
   *   desired state, meaning nothing was to do according to config.
   */
  public function export(ApiUnifyRequest $request, EntityInterface $entity, $reason, $action);

}
