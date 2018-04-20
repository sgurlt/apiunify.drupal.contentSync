<?php

namespace Drupal\drupal_content_sync\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\drupal_content_sync\ApiUnifyRequest;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Specifies the publicly available methods of a field handler plugin that can
 * be used to export and import fields with API Unify.
 *
 * @see \Drupal\drupal_content_sync\Annotation\FieldHandler
 * @see \Drupal\drupal_content_sync\Plugin\FieldHandlerBase
 * @see \Drupal\drupal_content_sync\Plugin\Type\FieldHandlerPluginManager
 * @see \Drupal\drupal_content_sync\Entity\DrupalContentSync
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
   * Get a list of all allowed export options for this field. You can
   * either allow {@see DrupalContentSync::EXPORT_DISABLED} or
   * {@see DrupalContentSync::EXPORT_DISABLED} and
   * {@see DrupalContentSync::EXPORT_AUTOMATICALLY}.
   *
   * @return string[]
   */
  public function getAllowedExportOptions();

  /**
   * Get the allowed import options.
   *
   * Get a list of all allowed import options for this field. You can
   * either allow {@see DrupalContentSync::IMPORT_DISABLED} or
   * {@see DrupalContentSync::IMPORT_DISABLED} and
   * {@see DrupalContentSync::IMPORT_AUTOMATICALLY}.
   *
   * @return string[]
   */
  public function getAllowedImportOptions();

  /**
   * Get the handler settings.
   *
   * Return the actual form elements for any additional settings for this
   * handler.
   *
   * @return array
   */
  public function getHandlerSettings();

  /**
   * Update the entity type definition.
   *
   * Advanced entity type definition settings for API Unify backend. You
   * can usually ignore these.
   *
   * @param $definition
   *   The definition to be sent to API Unify.
   *   {@see ApiUnifyConfig}
   */
  public function updateEntityTypeDefinition(&$definition);

  /**
   * @param \Drupal\drupal_content_sync\ApiUnifyRequest $request
   *   The request containing all exported data.
   * @param FieldableEntityInterface $entity
   *   The entity to import.
   * @param bool $is_clone
   *   Whether or not the entity should be clone'd or sync'd.
   * @param string $reason
   *   {@see DrupalContentSync::IMPORT_*}.
   * @param string $action
   *   {@see DrupalContentSync::ACTION_*}.
   *
   * @throws \Drupal\drupal_content_sync\Exception\SyncException
   *
   * @return bool
   *   Whether or not the content has been imported. FALSE is a desired state,
   *   meaning the entity should not be imported according to config.
   */
  public function import(ApiUnifyRequest $request, FieldableEntityInterface $entity, $is_clone, $reason, $action);

  /**
   * @param \Drupal\drupal_content_sync\ApiUnifyRequest $request
   *   The request to store all relevant info at.
   * @param FieldableEntityInterface $entity
   *   The entity to export.
   * @param string $reason
   *   {@see DrupalContentSync::EXPORT_*}.
   * @param string $action
   *   {@see DrupalContentSync::ACTION_*}.
   *
   * @throws \Drupal\drupal_content_sync\Exception\SyncException
   *
   * @return bool
   *   Whether or not the content has been exported. FALSE is a desired state,
   *   meaning the entity should not be exported according to config.
   */
  public function export(ApiUnifyRequest $request, FieldableEntityInterface $entity, $reason, $action);

}
