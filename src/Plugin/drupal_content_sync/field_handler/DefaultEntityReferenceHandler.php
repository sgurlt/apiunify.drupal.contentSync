<?php

namespace Drupal\drupal_content_sync\Plugin\drupal_content_sync\field_handler;

use Drupal\Core\Entity\RevisionableInterface;
use Drupal\drupal_content_sync\Entity\MetaInformation;
use Drupal\drupal_content_sync\ExportIntent;
use Drupal\drupal_content_sync\ImportIntent;
use Drupal\drupal_content_sync\Plugin\FieldHandlerBase;
use Drupal\drupal_content_sync\SyncIntent;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\paragraphs\Entity\Paragraph;

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
    if (!in_array($field->getType(), ["entity_reference", "entity_reference_revisions"])) {
      return FALSE;
    }

    $type = $field->getSetting('target_type');
    if (in_array($type, ['user', 'brick_type'])) {
      return FALSE;
    }

    return TRUE;
  }

  protected function shouldExportReferencedEntities() {
    return !empty($this->settings['handler_settings']['export_referenced_entities']) && $this->settings['handler_settings']['export_referenced_entities'] === 0 ? 0 : 1;
  }

  /**
   * @inheritdoc
   */
  public function getHandlerSettings() {
    $options = [
      'export_referenced_entities' => [
        '#type' => 'checkbox',
        '#title' => 'Export referenced entities',
        '#default_value' => $this->shouldExportReferencedEntities(),
      ],
    ];

    if ($this->fieldDefinition->getFieldStorageDefinition()->isMultiple()) {
      $options['merge_local_changes'] = [
        '#type' => 'checkbox',
        '#title' => 'Merge local changes',
        '#default_value' => !empty($this->settings['handler_settings']['merge_local_changes']) && $this->settings['handler_settings']['merge_local_changes'] === 0 ? 0 : 1,
      ];
    }

    return $options;
  }

  /**
   * @inheritdoc
   */
  public function import(ImportIntent $intent) {
    $action = $intent->getAction();
    /**
     * @var \Drupal\Core\Entity\FieldableEntityInterface $entity
     */
    $entity = $intent->getEntity();

    // Deletion doesn't require any action on field basis for static data.
    if ($action == SyncIntent::ACTION_DELETE) {
      return FALSE;
    }

    $data = $intent->getField($this->fieldName);

    $entityFieldManager = \Drupal::service('entity_field.manager');
    $field_definitions = $entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());
    /**
     * @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition
     */
    $field_definition = $field_definitions[$this->fieldName];
    $entityTypeManager = \Drupal::entityTypeManager();
    $reference_type = $field_definition
      ->getFieldStorageDefinition()
      ->getPropertyDefinition('entity')
      ->getTargetDefinition()
      ->getEntityTypeId();
    $storage = $entityTypeManager
      ->getStorage($reference_type);

    $merge = !empty($this->settings['handler_settings']['merge_local_changes']);

    if (!$merge && $intent->shouldMergeChanges()) {
      return FALSE;
    }

    if (empty($data) && !$merge) {
      $entity->set($this->fieldName, NULL);
    }
    else {
      $reference_ids = [];
      $ids = [];
      foreach ($data as $value) {
        $reference = $intent->loadEmbeddedEntity($value);
        if ($reference) {
          $reference_data = [
            'target_id' => $reference->id(),
          ];
          $ids[] = $reference->id();

          if ($reference instanceof RevisionableInterface) {
            $reference_data['target_revision_id'] = $reference->getRevisionId();
          }

          $reference_ids[] = $reference_data;
        }
      }
      $overwrite_ids = $reference_ids;

      if ($merge && $intent->shouldMergeChanges()) {
        $last_overwrite_values     = $intent->getMetaData(['field', $this->fieldName, 'last_overwrite_values']);
        $last_imported_order       = $intent->getMetaData(['field', $this->fieldName, 'last_imported_values']);
        $previous                  = $entity->get($this->fieldName)->getValue();
        $previous_ids              = [];
        $previous_id_to_definition = [];
        foreach ($previous as $value) {
          if (empty($value['target_id'])) {
            continue;
          }
          $previous_id_to_definition[$value['target_id']] = $value;
          $previous_ids[] = $value['target_id'];
        }

        // Check if there actually are any local overrides => otherwise just
        // overwrite local references with new references and new order.
        if (!is_null($last_imported_order)) {
          $merged     = [];
          $merged_ids = [];

          // First add all existing entities to the new value (merged items)
          foreach ($previous_ids as $target_id) {
            $reference = $storage
              ->load($target_id);
            if (!$reference) {
              continue;
            }

            // Removed from remote => remove locally.
            if (!in_array($target_id, $ids)) {
              $info = MetaInformation::getInfoForEntity($reference->getEntityTypeId(), $reference->uuid(), $intent->getFlow(), $intent->getPool());
              // But only if it was actually imported.
              if ($info && !$info->isSourceEntity()) {
                continue;
              }
            }

            $merged[] = $previous_id_to_definition[$target_id];
            $merged_ids[] = $target_id;
          }

          // Next add all newly added items where they fit best.
          if (count($reference_ids)) {
            for ($i = 0; $i < count($reference_ids); $i++) {
              $def = $reference_ids[$i];
              $id = $def['target_id'];
              // Already present? Ignore.
              if (in_array($id, $merged_ids)) {
                continue;
              }

              // Deleted locally? Ignore.
              if (in_array($def, $last_overwrite_values)) {
                continue;
              }

              // Get the index of the item before this one, so we can add ours
              // after it. If this doesn't work, it will be the first item
              // in the new item set.
              $n = $i - 1;
              $index = FALSE;
              while ($index === FALSE && $n >= 0) {
                $index = array_search($reference_ids[$n]['target_id'], $merged_ids);
                $n--;
              }

              // First and unfound come first.
              if ($i === 0 || $index === FALSE) {
                array_unshift($merged, $def);
                array_unshift($merged_ids, $id);
                continue;
              }
              // Everything else comes behind the last item that exists.
              array_splice($merged, $index + 1, 0, [$def]);
              array_splice($merged_ids, $index + 1, 0, $id);
            }
          }

          $reference_ids = $merged;
          $ids           = $merged_ids;
        }
      }

      if($this->fieldDefinition->getSetting('target_type')=='paragraph') {
        foreach ($reference_ids as $def) {
          $paragraph = Paragraph::load($def['target_id']);
          if(!$paragraph->getParentEntity()) {
            /**
             * @var \Drupal\Core\Entity\ContentEntityInterface $entity
             */
            $paragraph->setParentEntity($entity,$this->fieldName);
          }
        }
      }

      if (!$merge || !$intent->shouldMergeChanges() || $overwrite_ids !== $last_overwrite_values) {
        $entity->set($this->fieldName, count($reference_ids) ? $reference_ids : NULL);
        $intent->setMetaData([
          'field',
          $this->fieldName,
          'last_imported_values',
        ], $ids);
        $intent->setMetaData([
          'field',
          $this->fieldName,
          'last_overwrite_values',
        ], $overwrite_ids);
      }
    }

    return TRUE;
  }

  /**
   * @inheritdoc
   */
  public function export(ExportIntent $intent) {
    $action = $intent->getAction();
    /**
     * @var \Drupal\Core\Entity\FieldableEntityInterface $entity
     */
    $entity = $intent->getEntity();

    // Deletion doesn't require any action on field basis for static data.
    if ($action == SyncIntent::ACTION_DELETE) {
      return FALSE;
    }

    $entityFieldManager = \Drupal::service('entity_field.manager');
    $field_definitions = $entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());
    /**
     * @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition
     */
    $field_definition = $field_definitions[$this->fieldName];
    $entityTypeManager = \Drupal::entityTypeManager();
    $reference_type = $field_definition
      ->getFieldStorageDefinition()
      ->getPropertyDefinition('entity')
      ->getTargetDefinition()
      ->getEntityTypeId();
    $storage = $entityTypeManager
      ->getStorage($reference_type);

    $data   = $entity->get($this->fieldName)->getValue();
    $result = [];

    $export_referenced_entities = $this->shouldExportReferencedEntities();

    foreach ($data as $delta=>$value) {
      if (empty($value['target_id'])) {
        continue;
      }

      $target_id = $value['target_id'];
      $reference = $storage
        ->load($target_id);

      if (!$reference || $reference->uuid() == $intent->getUuid()) {
        continue;
      }

      if ($export_referenced_entities) {
        _drupal_content_sync_save_temp_meta($intent->getEntityType(), $intent->getUuid(), $this->fieldName, $delta, $reference->getEntityTypeId(), $reference->bundle(), $reference->uuid() );
        $result[] = $intent->embedEntity($reference);
      }
      else {
        $result[] = $intent->embedEntityDefinition(
          $reference->getEntityTypeId(),
          $reference->bundle(),
          $reference->uuid()
        );
      }
    }

    $intent->setField($this->fieldName, $result);

    return TRUE;
  }

}
