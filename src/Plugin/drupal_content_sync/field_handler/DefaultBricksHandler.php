<?php

namespace Drupal\drupal_content_sync\Plugin\drupal_content_sync\field_handler;


use Drupal\drupal_content_sync\Plugin\FieldHandlerBase;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;


/**
 * Class DefaultFieldHandler, providing a minimalistic implementation for any
 * field type.
 *
 * @FieldHandler(
 *   id = "drupal_content_sync_default_bricks_handler",
 *   label = @Translation("Default Bricks"),
 *   weight = 90
 * )
 *
 * @package Drupal\drupal_content_sync\Plugin\drupal_content_sync\field_handler
 */
class DefaultBricksHandler extends FieldHandlerBase {
  public function supports($entity_type,$bundle,$field_name,$field) {
    $allowed = ["bricks","bricks_revisioned"];
    if( in_array($field->getType(),$allowed)!==FALSE ) {
      return TRUE;
    }

    /*if( $field->getType()=="entity_reference" && $field->getSetting('target_type')=='brick_type' ) {
      return TRUE;
    }*/

    return FALSE;
  }

  public function getAllowedExportOptions($entity_type,$bundle) {
    return [
      DrupalContentSync::EXPORT_DISABLED,
      DrupalContentSync::EXPORT_AUTOMATICALLY,
    ];
  }

  public function getAllowedSyncImportOptions($entity_type,$bundle) {
    return [
      DrupalContentSync::IMPORT_DISABLED,
      DrupalContentSync::IMPORT_AUTOMATICALLY,
    ];
  }

  public function getAllowedClonedImportOptions($entity_type,$bundle) {
    return [
      DrupalContentSync::IMPORT_DISABLED,
      DrupalContentSync::IMPORT_AUTOMATICALLY,
    ];
  }

  public function getAdvancedSettings() {
    // Nothing special here
    return [];
  }

  public function getAdvancedSettingsForFieldAtEntityType($entity_type,$bundle) {
    // Nothing special here
    return [];
  }
}
