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
    if( in_array($type,['user','brick_type']) ) {
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

  public function setField($field_config,$entity,$field_name,&$data,$is_clone) {
    if (isset($data[$field_name])) {
      if( $field_config[($is_clone?'cloned':'sync').'_import']==DrupalContentSync::IMPORT_AUTOMATICALLY ) {
        if (empty($data[$field_name]) || !is_array($data[$field_name])) {
          return;
        }

        $reference_ids = [];
        foreach ($data[$field_name] as $value) {
          if (!isset($value['uuid'], $value['type'])) {
            continue;
          }

          try {
            $reference = $this->entityRepository->loadEntityByUuid($value['type'], $value['uuid']);
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
          catch (\Exception $exception) {
          }

          $entity->set($field_name, $reference_ids);
        }
      }
    }
  }
}
