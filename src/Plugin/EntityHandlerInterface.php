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
   * @ToDo: Add description.
   */
  public function import(ApiUnifyRequest $request,$is_clone,$reason,$action);

  public function export(ApiUnifyRequest $request,EntityInterface $entity,$reason,$action);

}
