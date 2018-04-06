<?php

namespace Drupal\drupal_content_sync\Plugin\drupal_content_sync\entity_handler;


use Drupal\drupal_content_sync\Plugin\EntityHandlerBase;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;


/**
 * Class DefaultEntityHandler, providing a minimalistic implementation for any
 * entity type.
 *
 * @EntityHandler(
 *   id = "drupal_content_sync_default_entity_handler",
 *   label = @Translation("Default"),
 *   weight = 100
 * )
 *
 * @package Drupal\drupal_content_sync\Plugin\drupal_content_sync\entity_handler
 */
class DefaultEntityHandler extends EntityHandlerBase {
  public static function supports($entity_type,$bundle) {
    return $entity_type!='user';
  }

  public function getAllowedExportOptions() {
    return [
      DrupalContentSync::EXPORT_DISABLED,
      DrupalContentSync::EXPORT_AUTOMATICALLY,
      // Not manually as that requires UI and is not available for all entity
      // types. Advanced handlers will provide this.
    ];
  }

  public function getAllowedSyncImportOptions() {
    return [
      DrupalContentSync::IMPORT_DISABLED,
      DrupalContentSync::IMPORT_AUTOMATICALLY,
      DrupalContentSync::IMPORT_MANUALLY,
    ];
  }

  public function getAllowedClonedImportOptions() {
    return [
      DrupalContentSync::IMPORT_DISABLED,
      DrupalContentSync::IMPORT_AUTOMATICALLY,
      DrupalContentSync::IMPORT_MANUALLY,
    ];
  }

  public function getAllowedPreviewOptions() {
    return [
      'table' => 'Table',
      'preview_mode' => 'Preview mode',
    ];
  }
}
