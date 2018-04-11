<?php

namespace Drupal\drupal_content_sync\Plugin\drupal_content_sync\field_handler;

use Drupal\drupal_content_sync\Plugin\FieldHandlerBase;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;
use Drupal\drupal_content_sync\ApiUnifyRequest;
use Drupal\Core\Entity\EntityInterface;
use Drupal\drupal_content_sync\SyncResult\SuccessResult;

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
  public static function supports($entity_type, $bundle, $field_name, $field) {
    $allowed = ["image", "file_uri", "file"];
    return in_array($field->getType(), $allowed) !== FALSE;
  }

  public function import(ApiUnifyRequest $request,EntityInterface $entity,$is_clone,$reason,$action) {
    // Deletion doesn't require any action on field basis for static data
    if( $action==DrupalContentSync::ACTION_DELETE ) {
      return TRUE;
    }

    $data = $request->getField($this->fieldName);

    if (empty($data)) {
      $entity->set($this->fieldName, NULL);
    }
    else {
      $file_ids = [];
      foreach ($data as $value) {
        $entity = $request->loadEmbeddedEntity($value);
        if( $entity ) {
          $file_ids[] = $entity->id();
        }
      }

      $entity->set($this->fieldName, $file_ids);
    }
  }

  /**
   * @inheritdoc
   */
  public function export(ApiUnifyRequest $request,EntityInterface $entity,$reason,$action) {
    // Deletion doesn't require any action on field basis for static data
    if( $action==DrupalContentSync::ACTION_DELETE ) {
      return new SuccessResult(SuccessResult::CODE_HANDLER_IGNORED);
    }

    $data   = $entity->get($this->fieldName);
    $result = [];

    foreach ($data as $key => $value) {
      $file = File::load($value['target_id']);
      if ($file) {
        $result[] = $request->embedEntity($file);
      }
    }

    $request->setField($this->fieldName,$result);

    return new SuccessResult();
  }

}
