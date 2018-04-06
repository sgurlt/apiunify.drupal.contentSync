<?php

namespace Drupal\drupal_content_sync\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

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
  public static function supports($entity_type,$bundle);

  public function getAllowedExportOptions();

  public function getAllowedSyncImportOptions();

  public function getAllowedClonedImportOptions();

  public function getAllowedPreviewOptions();

  public function getAdvancedSettingsForEntityType();

  public function updateEntityTypeDefinition(&$definition);

  public function createEntity($base_data,&$field_data,$is_clone);

  public function updateEntity($entity,&$field_data);
}
