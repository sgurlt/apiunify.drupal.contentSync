<?php

namespace Drupal\drupal_content_sync\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\drupal_content_sync\ApiUnifyRequest;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

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
  public static function supports($entity_type, $bundle, $field_name, FieldDefinitionInterface $field);

  /**
   * Get the allowed export options.
   *
   * Get a list of all allowed export options for this field. Typically you
   * either allow DISABLED or DISABLED and AUTOMATICALLY.
   *
   * @return array
   * DrupalContentSyncEXPORT_
   */
  public function getAllowedExportOptions();

  /**
   * Get allowed sync import options.
   *
   * Get a list of all allowed import options for this field for synchronized
   * imports. Typically you either allow DISABLED or DISABLED and AUTOMATICALLY.
   *
   * @return array
   * DrupalContentSyncIMPORT_
   */
  public function getAllowedSyncImportOptions();

  /**
   * Get allowed allowed cloned import options.
   *
   * Get a list of all allowed import options for this field for cloned
   * imports. Typically you either allow DISABLED or DISABLED and AUTOMATICALLY.
   *
   * @return mixed
   */
  public function getAllowedClonedImportOptions();

  /**
   * Get the handler settings.
   *
   * Return the actual form elements for the settings declared via
   * ::getAllowedClonedImportOptions().
   *
   * @return mixed
   */
  public function getHandlerSettings();

  /**
   * Update the entity type definition.
   *
   * Advanced entity type definition settings for the Node.js backend. You
   * can usually ignore these.
   *
   * @return bool
   */
  public function updateEntityTypeDefinition(&$definition);

  /**
   * @param \Drupal\drupal_content_sync\ApiUnifyRequest $request
   *   The request
   *   containing all relevant data and where the result is stored as well.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to import.
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
  public function import(ApiUnifyRequest $request, EntityInterface $entity, $is_clone, $reason, $action);

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
