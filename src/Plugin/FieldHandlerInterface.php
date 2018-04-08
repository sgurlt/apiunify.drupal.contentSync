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
interface FieldHandlerInterface extends PluginInspectionInterface {

  /**
   * Check if this handler supports the given field instance.
   *
   * @param string $entity_type
   * @param string $bundle
   * @param string $field_name
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
   *
   * @return bool
   */
  public static function supports($entity_type, $bundle, $field_name, $field);

  /**
   * Get a list of all allowed export options for this field. Typically you
   * either allow DISABLED or DISABLED and AUTOMATICALLY.
   *
   * @param string $entity_type
   * @param string $bundle
   * @param string $field_name
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
   *
   * @return arrayDrupalContentSyncEXPORT_
   */
  public function getAllowedExportOptions();

  /**
   * Get a list of all allowed import options for this field for synchronized
   * imports. Typically you either allow DISABLED or DISABLED and AUTOMATICALLY.
   *
   * @param string $entity_type
   * @param string $bundle
   * @param string $field_name
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
   *
   * @return arrayDrupalContentSyncIMPORT_
   */
  public function getAllowedSyncImportOptions();

  /**
   * Get a list of all allowed import options for this field for cloned
   * imports. Typically you either allow DISABLED or DISABLED and AUTOMATICALLY.
   *
   * @param string $entity_type
   * @param string $bundle
   * @param string $field_name
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
   *
   * @return mixed
   */
  public function getAllowedClonedImportOptions();

  /**
   * Return the actual form elements for the settings declared via
   * ::getAllowedClonedImportOptions().
   *
   * @param string $entity_type
   * @param string $bundle
   * @param string $field_name
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
   *
   * @return mixed
   */
  public function getHandlerSettings();

  /**
   * Advanced entity type definition settings for the Node.js backend. You
   * can usually ignore these.
   *
   * @param string $entity_type
   * @param string $bundle
   * @param string $field_name
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
   *
   * @return bool
   */
  public function updateEntityTypeDefinition(&$definition);

  /**
   * Restore a serialized field value.
   *
   * @param $field_config
   *   array The settings defined for this field via UI.
   * @param $entity
   *   \Drupal\Core\Entity\Entity The entity to alter.
   * @param $field_name
   *   string The name of the field.
   * @param $data
   *   array The data, as provided by another site via ::getField()
   * @param $is_clone
   *   boolean Whether this is cloned (synchronized otherwise)
   *
   * @return bool
   */
  public function setField($entity, &$data, $is_clone);

  /**
   * Serialize a field value.
   *
   * @param $field_config
   *   array The settings defined for this field via UI.
   * @param $entity
   *   \Drupal\Core\Entity\Entity The entity to alter.
   * @param $field_name
   *   string The name of the field.
   *
   * @return array The data as it should be given to ::setField()
   */
  public function getField($entity);

}
