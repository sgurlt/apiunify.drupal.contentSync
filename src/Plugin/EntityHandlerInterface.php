<?php

namespace Drupal\drupal_content_sync\Plugin;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\drupal_content_sync\ApiUnifyRequest;

/**
 * Specifies the publicly available methods of an entity handler plugin that can
 * be used to export and import entities with API Unify.
 *
 * @see \Drupal\drupal_content_sync\Annotation\EntityHandler
 * @see \Drupal\drupal_content_sync\Plugin\EntityHandlerBase
 * @see \Drupal\drupal_content_sync\Plugin\Type\EntityHandlerPluginManager
 * @see \Drupal\drupal_content_sync\Entity\DrupalContentSync
 * @see plugin_api
 *
 * @ingroup third_party
 */
interface EntityHandlerInterface extends PluginInspectionInterface {
  /**
   * Check if this handler supports the given entity type.
   *
   * @param string $entity_type
   * @param string $bundle
   * @return bool
   */
  public static function supports($entity_type, $bundle);

  /**
   * Get the allowed export options.
   *
   * Get a list of all allowed export options for this entity.
   * @see DrupalContentSync::EXPORT_*
   *
   * @return string[]
   */
  public function getAllowedExportOptions();

  /**
   * Get the allowed import options.
   *
   * Get a list of all allowed import options for this field.
   * @see DrupalContentSync::IMPORT_*
   *
   * @return string[]
   */
  public function getAllowedImportOptions();

  /**
   * @return string[]
   *   Provide the allowed preview options used for display when manually
   * importing entities.
   */
  public function getAllowedPreviewOptions();

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
   * Advanced entity type definition settings for the API Unify. You
   * can usually ignore these.
   */
  public function updateEntityTypeDefinition(&$definition);

  /**
   * Provide a list of fields that are not allowed to be exported or imported.
   * These fields typically contain all label fields that are exported
   * separately anyway (we don't want to set IDs and revision IDs of entities
   * for example, but only use the UUID for references).
   *
   * @return string[]
   */
  public function getForbiddenFields();

  /**
   * @param \Drupal\drupal_content_sync\ApiUnifyRequest $request
   *   The request
   *   containing all relevant data and where the result is stored as well.
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
   *   meaning nothing should be imported according to config.
   */
  public function import(ApiUnifyRequest $request, $is_clone, $reason, $action);

  /**
   * @param \Drupal\drupal_content_sync\ApiUnifyRequest $request
   *   The request to store all relevant info at.
   * @param \Drupal\Core\Entity\EntityInterface $entity
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
   *   meaning nothing should be exported according to config.
   */
  public function export(ApiUnifyRequest $request, EntityInterface $entity, $reason, $action);

}
