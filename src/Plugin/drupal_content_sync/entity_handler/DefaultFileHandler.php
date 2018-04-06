<?php

namespace Drupal\drupal_content_sync\Plugin\drupal_content_sync\entity_handler;


use Drupal\drupal_content_sync\Plugin\EntityHandlerBase;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;


/**
 * Class DefaultEntityHandler, providing a minimalistic implementation for any
 * entity type.
 *
 * @EntityHandler(
 *   id = "drupal_content_sync_default_file_handler",
 *   label = @Translation("Default File"),
 *   weight = 90
 * )
 *
 * @package Drupal\drupal_content_sync\Plugin\drupal_content_sync\entity_handler
 */
class DefaultFileHandler extends EntityHandlerBase {
  public static function supports($entity_type,$bundle) {
    return $entity_type=='file';
  }

  public function getAllowedExportOptions() {
    return [
      DrupalContentSync::EXPORT_DISABLED,
      DrupalContentSync::EXPORT_AUTOMATICALLY,
      DrupalContentSync::EXPORT_MANUALLY,
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

  public function updateEntityTypeDefinition(&$definition) {
    parent::updateEntityTypeDefinition($definition);

    $definition['new_properties']['apiu_file_content'] = [
      'type' => 'string',
      'default_value' => NULL,
    ];
    $definition['new_property_lists']['details']['apiu_file_content'] = 'value';
    $definition['new_property_lists']['filesystem']['apiu_file_content'] = 'value';
    $definition['new_property_lists']['modifiable']['apiu_file_content'] = 'value';
    $definition['new_property_lists']['required']['apiu_file_content'] = 'value';
  }

  public function createEntity($base_data,&$field_data,$is_clone) {
    if (!empty($field_data['uri'][0]['value'])) {
      $uri = $field_data['uri'][0]['value'];
    } elseif (!empty($field_data['uri'])) {
      $uri = $field_data['uri'];
    } else {
      return FALSE;
    }

    $directory = \Drupal::service('file_system')->dirname($uri);
    $was_prepared = file_prepare_directory($directory, FILE_CREATE_DIRECTORY);

    if ($was_prepared && !empty($field_data['apiu_file_content'])) {
      $entity = file_save_data(base64_decode($field_data['apiu_file_content']), $uri);
      $entity->setPermanent();
      $entity->set('uuid', $field_data['uuid']);
      $entity->save();
    }

    return $entity;
  }

  public function updateEntity($entity,&$field_data) {
    if (empty($field_data['apiu_file_content'])) {
      $content = file_get_contents($entity->getFileUri());
      if (!empty($content)) {
        $field_data['apiu_file_content'] = base64_encode($content);
      }
    }
  }
}