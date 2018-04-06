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
  public static function supports($entity_type,$bundle,$field_name,$field) {
    if( $field->getType()!="entity_reference" ) {
      return FALSE;
    }

    $type = $field->getSetting('target_type');
    if( in_array($type,['user','brick_type']) ) {
      return FALSE;
    }

    return TRUE;
  }

  public function getAdvancedSettingsForFieldAtEntityType() {
    return [
      'export_referenced_entities' => [
        '#type' => 'checkbox',
        '#title' => 'Export referenced entities',
        '#default_value' => $this->settings['export_referenced_entities']===0 ? 0 : 1,
      ],
      'sync_import_referenced_entities' => [
        '#type' => 'checkbox',
        '#title' => 'Import referenced entities (sync)',
        '#default_value' => $this->settings['sync_import_referenced_entities']===0 ? 0 : 1,
      ],
      'cloned_import_referenced_entities' => [
        '#type' => 'checkbox',
        '#title' => 'Import referenced entities (clone)',
        '#default_value' => $this->settings['cloned_import_referenced_entities']===0 ? 0 : 1,
      ],
    ];
  }

  public function setField($entity,&$data,$is_clone) {
    if (isset($data[$this->fieldName])) {
      if( $this->settings[($is_clone?'cloned':'sync').'_import']==DrupalContentSync::IMPORT_AUTOMATICALLY ) {
        if (empty($data[$this->fieldName]) || !is_array($data[$this->fieldName])) {
          return;
        }

        $reference_ids = [];
        foreach ($data[$this->fieldName] as $value) {
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

          $entity->set($this->fieldName, $reference_ids);
        }
      }
    }
  }
}
