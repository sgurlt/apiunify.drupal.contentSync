<?php

namespace Drupal\drupal_content_sync\Plugin\drupal_content_sync\field_handler;

use Drupal\Core\Entity\RevisionableInterface;
use Drupal\drupal_content_sync\Plugin\FieldHandlerBase;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;
use Drupal\drupal_content_sync\ApiUnifyRequest;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Providing a minimalistic implementation for any field type.
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
   * {@inheritdoc}
   */
  public static function supports($entity_type, $bundle, $field_name, FieldDefinitionInterface $field) {
    if (!in_array($field->getType(), ["entity_reference","entity_reference_revisions"])) {
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
        '#default_value' => !empty($this->settings['handler_settings']['export_referenced_entities']) && $this->settings['handler_settings']['export_referenced_entities'] === 0 ? 0 : 1,
      ],
    ];
  }

  /**
   *
   */
  public function import(ApiUnifyRequest $request, EntityInterface $entity, $is_clone, $reason, $action) {
    // Deletion doesn't require any action on field basis for static data.
    if ($action == DrupalContentSync::ACTION_DELETE) {
      return FALSE;
    }

    $data = $request->getField($this->fieldName);

    if (empty($data)) {
      $entity->set($this->fieldName, NULL);
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

    return TRUE;
  }

  /**
   * @inheritdoc
   */
  public function export(ApiUnifyRequest $request, EntityInterface $entity, $reason, $action) {
    // Deletion doesn't require any action on field basis for static data.
    if ($action == DrupalContentSync::ACTION_DELETE) {
      return FALSE;
    }

    $entityFieldManager = \Drupal::service('entity_field.manager');
    $field_definitions  = $entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());
    $field_definition   = $field_definitions[$this->fieldName];
    $entityTypeManager  = \Drupal::entityTypeManager();

    $data   = $entity->get($this->fieldName)->getValue();
    $result = [];

    $export_referenced_entities = empty($this->settings['handler_settings']['export_referenced_entities']);

    foreach ($data as $value) {
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
        if( $export_referenced_entities ) {
          $result[] = $request->embedEntity($reference);
        }
        else {
          $result[] = $request->embedEntityDefinition(
            $reference->getEntityTypeId(),
            $reference->bundle(),
            $reference->uuid()
          );
        }
      }
    }

    $request->setField($this->fieldName, $result);

    return TRUE;
  }

}
