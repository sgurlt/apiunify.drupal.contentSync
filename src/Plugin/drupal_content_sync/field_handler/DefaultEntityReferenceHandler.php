<?php

namespace Drupal\drupal_content_sync\Plugin\drupal_content_sync\field_handler;


use Drupal\drupal_content_sync\Plugin\FieldHandlerBase;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;


/**
 * Class DefaultFieldHandler, providing a minimalistic implementation for any
 * field type.
 *
 * @FieldHandler(
 *   id = "drupal_content_sync_default_entity_reference_handler",
 *   label = @Translation("Default Entity Reference"),
 *   weight = 90
 * )
 *
 * @package Drupal\drupal_content_sync\Plugin\drupal_content_sync\field_handler
 */
class DefaultEntityReferenceHandler extends FieldHandlerBase {
  public function supports($entity_type,$bundle,$field_name,$field) {
    if( $field->getType()!="entity_reference" ) {
      return FALSE;
    }

    $type = $field->getSetting('target_type');
    if( $type=='user' ) {
      return FALSE;
    }

    return TRUE;
  }

  public function getAllowedExportOptions($entity_type,$bundle,$field_name,$field) {
    return [
      DrupalContentSync::EXPORT_DISABLED,
      DrupalContentSync::EXPORT_AUTOMATICALLY,
    ];
  }

  public function getAllowedSyncImportOptions($entity_type,$bundle,$field_name,$field) {
    return [
      DrupalContentSync::IMPORT_DISABLED,
      DrupalContentSync::IMPORT_AUTOMATICALLY,
    ];
  }

  public function getAllowedClonedImportOptions($entity_type,$bundle,$field_name,$field) {
    return [
      DrupalContentSync::IMPORT_DISABLED,
      DrupalContentSync::IMPORT_AUTOMATICALLY,
    ];
  }

  public function getAdvancedSettings() {
    return [
      'export_referenced_entities' => 'Export referenced entities',
      'sync_import_referenced_entities' => 'Import referenced entities (sync)',
      'cloned_import_referenced_entities' => 'Import referenced entities (clone)',
    ];
  }

  public function getAdvancedSettingsForFieldAtEntityType($entity_type,$bundle,$field_name,$field,$default_values) {
    return [
      'export_referenced_entities' => [
        '#type' => 'checkbox',
        '#title' => 'Export referenced entities',
        '#title_display' => 'invisible',
        '#default_value' => $default_values['export_referenced_entities']===0 ? 0 : 1,
      ],
      'sync_import_referenced_entities' => [
        '#type' => 'checkbox',
        '#title' => 'Import referenced entities (sync)',
        '#title_display' => 'invisible',
        '#default_value' => $default_values['sync_import_referenced_entities']===0 ? 0 : 1,
      ],
      'cloned_import_referenced_entities' => [
        '#type' => 'checkbox',
        '#title' => 'Import referenced entities (clone)',
        '#title_display' => 'invisible',
        '#default_value' => $default_values['cloned_import_referenced_entities']===0 ? 0 : 1,
      ],
    ];
  }
}
