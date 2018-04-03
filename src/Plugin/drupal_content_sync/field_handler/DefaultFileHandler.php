<?php

namespace Drupal\drupal_content_sync\Plugin\drupal_content_sync\field_handler;


use Drupal\drupal_content_sync\Plugin\FieldHandlerBase;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;


/**
 * Class DefaultFieldHandler, providing a minimalistic implementation for any
 * field type.
 *
 * @FieldHandler(
 *   id = "drupal_content_sync_default_file_handler",
 *   label = @Translation("Default File"),
 *   weight = 90
 * )
 *
 * @package Drupal\drupal_content_sync\Plugin\drupal_content_sync\field_handler
 */
class DefaultFileHandler extends FieldHandlerBase {
  public function supports($entity_type,$bundle,$field_name,$field) {
    $allowed = ["image","file_uri","file"];
    return in_array($field->getType(),$allowed)!==FALSE;
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

  public function setField($field_config,$entity,$field_name,&$data,$is_clone) {
    if (isset($data[$field_name])) {
      if( $field_config[($is_clone?'cloned':'sync').'_import']==DrupalContentSync::IMPORT_AUTOMATICALLY ) {
        $file_ids = [];
        foreach ($data[$field_name] as $value) {
          $dirname = \Drupal::service('file_system')->dirname($value['file_uri']);
          file_prepare_directory($dirname, FILE_CREATE_DIRECTORY);
          $file = file_save_data(base64_decode($value['file_content']), $value['file_uri']);
          $file->setPermanent();
          $file->save();

          $file_ids[] = $file->id();
        }

        $entity->set($field_name, $file_ids);
      }
    }
  }
}
