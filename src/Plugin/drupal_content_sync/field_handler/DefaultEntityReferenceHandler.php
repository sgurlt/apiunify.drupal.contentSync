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
 *   id = "drupal_content_sync_default_entity_reference_handler",
 *   label = @Translation("Default Entity Reference"),
 *   weight = 90
 * )
 *
 * @package Drupal\drupal_content_sync\Plugin\drupal_content_sync\field_handler
 */
class DefaultEntityReferenceHandler extends FieldHandlerBase {

  /**
   * @ToDo: Add description.
   */
  public static function supports($entity_type, $bundle, $field_name, $field) {
    if ($field->getType() != "entity_reference") {
      return FALSE;
    }

    $type = $field->getSetting('target_type');
    if (in_array($type, ['user', 'brick_type'])) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * @ToDo: Add description.
   */
  public function getHandlerSettings() {
    return [
      'export_referenced_entities' => [
        '#type' => 'checkbox',
        '#title' => 'Export referenced entities',
        '#default_value' => $this->settings['handler_settings']['export_referenced_entities'] === 0 ? 0 : 1,
      ],
      'sync_import_referenced_entities' => [
        '#type' => 'checkbox',
        '#title' => 'Import referenced entities (sync)',
        '#default_value' => $this->settings['handler_settings']['sync_import_referenced_entities'] === 0 ? 0 : 1,
      ],
      'cloned_import_referenced_entities' => [
        '#type' => 'checkbox',
        '#title' => 'Import referenced entities (clone)',
        '#default_value' => $this->settings['handler_settings']['cloned_import_referenced_entities'] === 0 ? 0 : 1,
      ],
    ];
  }

  public function import(ApiUnifyRequest $request,EntityInterface $entity,$is_clone,$reason,$action) {
    // Deletion doesn't require any action on field basis for static data
    if( $action==DrupalContentSync::ACTION_DELETE ) {
      return new SuccessResult();
    }

    $data = $request->getField($this->fieldName);

    if( empty($data) ) {
      $entity->set($this->fieldName,NULL);
    }
    else {
      $reference_ids = [];
      foreach ($data as $value) {
        $reference = $request->loadEmbeddedEntity($value);
        if ($reference) {
          $reference_data = [
            'target_id' => $reference->id(),
          ];

          if ($reference instanceof RevisionableInterface) {
            $reference_data['target_revision_id'] = $reference->getRevisionId();
          }

          $reference_ids[] = $reference_data;
        }
      }

      $entity->set($this->fieldName, $reference_ids);
    }

    return new SuccessResult();
  }

  /**
   * @inheritdoc
   */
  public function export(ApiUnifyRequest $request,EntityInterface $entity,$reason,$action) {
    // Deletion doesn't require any action on field basis for static data
    if( $action==DrupalContentSync::ACTION_DELETE ) {
      return new SuccessResult(SuccessResult::CODE_HANDLER_IGNORED);
    }

    $entityFieldManager = Drupal::service('entity_field.manager');
    $field_definitions  = $entityFieldManager->getFieldDefinitions($entity->getEntityType(), $entity->bundle());
    $field_definition   = $field_definitions[$this->fieldName];
    $entityTypeManager  = \Drupal::entityTypeManager();

    $data   = $entity->get($this->fieldName);
    $result = [];

    foreach ($data as $key => $value) {
      if (empty($value['target_id'])) {
        continue;
      }

      $target_id      = $value['target_id'];
      $reference_type = $field_definition
        ->getFieldStorageDefinition()
        ->getPropertyDefinition('entity')
        ->getTargetDefinition()
        ->getEntityTypeId();

      $reference = $entityTypeManager
        ->getStorage($reference_type)
        ->load($target_id);

      if ($reference && $reference->uuid() != $request->getUuid()) {
        $result[] = $request->embedEntity($reference);
      }
    }

    $request->setField($this->fieldName,$result);

    return new SuccessResult();
  }

}
