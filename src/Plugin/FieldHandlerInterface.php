<?php

namespace Drupal\drupal_content_sync\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\drupal_content_sync\ApiUnifyRequest;
use Drupal\Core\Entity\EntityInterface;

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

  public function allowsImport(ApiUnifyRequest $request,EntityInterface $entity,$is_clone,$reason,$action);
  public function import(ApiUnifyRequest $request,EntityInterface $entity,$is_clone,$reason,$action);

  public function allowsExport(ApiUnifyRequest $request,EntityInterface $entity,$reason,$action);
  public function export(ApiUnifyRequest $request,EntityInterface $entity,$reason,$action);

}
