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
  public static function supports($entity_type,$bundle,$field_name,$field) {
    $allowed = ["image","file_uri","file"];
    return in_array($field->getType(),$allowed)!==FALSE;
  }

  public function setField($entity,&$data,$is_clone) {
    if (isset($data[$this->fieldName])) {
      if( $this->settings[($is_clone?'cloned':'sync').'_import']==DrupalContentSync::IMPORT_AUTOMATICALLY ) {
        $file_ids = [];
        foreach ($data[$this->fieldName] as $value) {
          $dirname = \Drupal::service('file_system')->dirname($value['file_uri']);
          file_prepare_directory($dirname, FILE_CREATE_DIRECTORY);
          $file = file_save_data(base64_decode($value['file_content']), $value['file_uri']);
          $file->setPermanent();
          $file->save();

          $file_ids[] = $file->id();
        }

        $entity->set($this->fieldName, $file_ids);
      }
    }
  }
}
