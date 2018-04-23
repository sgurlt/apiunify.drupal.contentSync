<?php

namespace Drupal\drupal_content_sync\Plugin\drupal_content_sync\entity_handler;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\drupal_content_sync\Exception\SyncException;
use Drupal\drupal_content_sync\Plugin\EntityHandlerBase;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;
use Drupal\drupal_content_sync\ApiUnifyRequest;
use Drupal\file\Entity\File;

/**
 * Class DefaultFileHandler, providing proper file handling capabilities.
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

  /**
   * @inheritdoc
   */
  public static function supports($entity_type, $bundle) {
    return $entity_type == 'file';
  }

  /**
   * @inheritdoc
   */
  public function getAllowedExportOptions() {
    return [
      DrupalContentSync::EXPORT_DISABLED,
      DrupalContentSync::EXPORT_AUTOMATICALLY,
      DrupalContentSync::EXPORT_AS_DEPENDENCY,
      DrupalContentSync::EXPORT_MANUALLY,
    ];
  }

  /**
   * @inheritdoc
   */
  public function getAllowedImportOptions() {
    return [
      DrupalContentSync::IMPORT_DISABLED,
      DrupalContentSync::IMPORT_AUTOMATICALLY,
      DrupalContentSync::IMPORT_AS_DEPENDENCY,
      DrupalContentSync::IMPORT_MANUALLY,
    ];
  }

  /**
   * @inheritdoc
   */
  public function getAllowedPreviewOptions() {
    return [
      'table' => 'Table',
      'preview_mode' => 'Preview mode',
    ];
  }

  /**
   * @inheritdoc
   */
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

  /**
   *
   */
  public function getForbiddenFields() {
    return array_merge(
      parent::getForbiddenFields(),
      [
        'uri',
        'filemime',
        'filesize',
      ]
    );
  }

  /**
   * @inheritdoc
   */
  public function import(ApiUnifyRequest $request, $is_clone, $reason, $action) {
    /**
     * @var \Drupal\Core\Entity\FieldableEntityInterface $entity
     */
    $entity = $this->loadEntity($request);

    if ($action == DrupalContentSync::ACTION_DELETE) {
      if ($entity) {
        return $this->deleteEntity($entity);
      }
      return FALSE;
    }

    $uri = $request->getField('uri');
    if (empty($uri)) {
      throw new SyncException(SyncException::CODE_INVALID_IMPORT_REQUEST);
    }
    if (!empty($uri[0]['value'])) {
      $uri = $uri[0]['value'];
    }

    $content = $request->getField('apiu_file_content');
    if (!$content) {
      throw new SyncException(SyncException::CODE_INVALID_IMPORT_REQUEST);
    }

    if ($action == DrupalContentSync::ACTION_CREATE) {
      if (!$is_clone) {
        $file = File::load($request->getUuid());
        if ($file) {
          if (file_save_data(base64_decode($content), $file->getFileUri(), FILE_EXISTS_REPLACE)) {
            return TRUE;
          }
          throw new SyncException(SyncException::CODE_ENTITY_API_FAILURE);
        }
      }

      $directory = \Drupal::service('file_system')->dirname($uri);
      $was_prepared = file_prepare_directory($directory, FILE_CREATE_DIRECTORY);

      if ($was_prepared) {
        $entity = file_save_data(base64_decode($content), $uri);
        $entity->setPermanent();
        if (!$is_clone) {
          $entity->set('uuid', $request->getUuid());
        }
        $entity->save();
      }

      return TRUE;
    }
    if ($action == DrupalContentSync::ACTION_UPDATE) {
      $content = $request->getField('apiu_file_content');
      if (!$content) {
        throw new SyncException(SyncException::CODE_INVALID_IMPORT_REQUEST);
      }

      if (file_save_data(base64_decode($content), $uri, FILE_EXISTS_REPLACE)) {
        return TRUE;
      };
      throw new SyncException(SyncException::CODE_ENTITY_API_FAILURE);
    }

    throw new SyncException(SyncException::CODE_INVALID_IMPORT_REQUEST);
  }

  /**
   * @inheritdoc
   */
  public function export(ApiUnifyRequest $request, FieldableEntityInterface $entity, $reason, $action) {
    /**
     * @var \Drupal\file\FileInterface $entity
     */

    if (!parent::export($request, $entity, $request, $action)) {
      return FALSE;
    }

    // Base Info.
    $uri = $entity->getFileUri();
    $request->setField('apiu_file_content', base64_encode(file_get_contents($uri)));
    $request->setField('uri', [['value' => $uri]]);
    $request->setField('title', $entity->getFilename());

    // Preview.
    $request->setField('preview', '<img style="max-height: 200px" src="' . file_create_url($uri) . '"/>');

    // No Translations, No Menu items compared to EntityHandlerBase.
    // Source URL.
    $this->setSourceUrl($request, $entity);

    return TRUE;
  }

}
