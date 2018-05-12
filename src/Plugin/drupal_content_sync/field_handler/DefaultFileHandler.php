<?php

namespace Drupal\drupal_content_sync\Plugin\drupal_content_sync\field_handler;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\drupal_content_sync\Plugin\FieldHandlerBase;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;
use Drupal\drupal_content_sync\ApiUnifyRequest;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\file\Entity\File;

/**
 * Providing a minimalistic implementation for any field type.
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

  /**
   * {@inheritdoc}
   */
  public static function supports($entity_type, $bundle, $field_name, FieldDefinitionInterface $field) {
    $allowed = ["image", "file_uri", "file"];
    return in_array($field->getType(), $allowed) !== FALSE;
  }

  /**
   * @inheritdoc
   */
  public function import(ApiUnifyRequest $request, FieldableEntityInterface $entity, $is_clone, $reason, $action, $merge_only) {
    // Deletion doesn't require any action on field basis for static data.
    if ($action == DrupalContentSync::ACTION_DELETE) {
      return FALSE;
    }

    if ($merge_only) {
      return FALSE;
    }

    $data = $request->getField($this->fieldName);

    if (empty($data)) {
      $entity->set($this->fieldName, NULL);
    }
    else {
      $file_ids = [];
      foreach ($data as $value) {
        $file = $request->loadEmbeddedEntity($value);
        if ($file) {
          $file_ids[] = [
            'target_id' => $file->id(),
            'alt' => $value['alt'],
            'title' => $value['title'],
          ];
        }
      }

      $entity->set($this->fieldName, $file_ids);
    }

    return TRUE;
  }

  /**
   * @inheritdoc
   */
  public function export(ApiUnifyRequest $request, FieldableEntityInterface $entity, $reason, $action) {
    // Deletion doesn't require any action on field basis for static data.
    if ($action == DrupalContentSync::ACTION_DELETE) {
      return FALSE;
    }

    $result = [];

    if ($this->fieldDefinition->getType() == 'uri') {
      $data = $entity->get($this->fieldName)->getValue();

      foreach ($data as $value) {
        $files = \Drupal::entityTypeManager()
          ->getStorage('file')
          ->loadByProperties(['uri' => $value['value']]);
        $file = empty($files) ? NULL : reset($files);
        if ($file) {
          $result[] = $request->embedEntity($file);
        }
      }
    }
    else {
      $item = $entity->get($this->fieldName);

      if (!empty($item->target_id)) {
        $file = File::load($item->target_id);
        if ($file) {
          $result[] = $request->embedEntity($file, [
            'alt' => $item->alt,
            'title' => $item->title,
          ]);
        }
      }
    }

    $request->setField($this->fieldName, $result);

    return TRUE;
  }

}
