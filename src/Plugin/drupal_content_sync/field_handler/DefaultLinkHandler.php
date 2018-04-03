<?php

namespace Drupal\drupal_content_sync\Plugin\drupal_content_sync\field_handler;


use Drupal\drupal_content_sync\Plugin\FieldHandlerBase;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;


/**
 * Class DefaultFieldHandler, providing a minimalistic implementation for any
 * field type.
 *
 * @FieldHandler(
 *   id = "drupal_content_sync_default_link_handler",
 *   label = @Translation("Default Link"),
 *   weight = 90
 * )
 *
 * @package Drupal\drupal_content_sync\Plugin\drupal_content_sync\field_handler
 */
class DefaultLinkHandler extends FieldHandlerBase {
  public function supports($entity_type,$bundle,$field_name,$field) {
    $allowed = ["link"];
    return in_array($field->getType(),$allowed)!==FALSE;
  }

  public function getAllowedExportOptions($entity_type,$bundle) {
    return [
      DrupalContentSync::EXPORT_DISABLED,
      DrupalContentSync::EXPORT_AUTOMATICALLY,
    ];
  }

  public function getAllowedSyncImportOptions($entity_type,$bundle) {
    return [
      DrupalContentSync::IMPORT_DISABLED,
      DrupalContentSync::IMPORT_AUTOMATICALLY,
    ];
  }

  public function getAllowedClonedImportOptions($entity_type,$bundle) {
    return [
      DrupalContentSync::IMPORT_DISABLED,
      DrupalContentSync::IMPORT_AUTOMATICALLY,
    ];
  }

  public function getAdvancedSettings() {
    // Nothing special here
    return [];
  }

  public function getAdvancedSettingsForFieldAtEntityType($entity_type,$bundle) {
    // Nothing special here
    return [];
  }

  public function setField($field_config,$entity,$field_name,&$data,$is_clone) {
    if (isset($data[$field_name])) {
      if( $field_config[($is_clone?'cloned':'sync').'_import']==DrupalContentSync::IMPORT_AUTOMATICALLY ) {
        if (!isset($data[$field_name])) {
          return;
        }

        foreach ($data[$field_name] as &$link_element) {
          $uri = &$link_element['uri'];

          // Find the linked entity and replace it's id with the UUID
          // References have following pattern: entity:entity_type/entity_id
          preg_match('/^entity:(.*)\/(.*)$/', $uri, $found);

          if (!empty($found)) {
            $link_entity_type = $found[1];
            $link_entity_uuid = $found[2];
            $link_entity = $this->entityRepository->loadEntityByUuid($link_entity_type, $link_entity_uuid);
            if ($link_entity) {
              $uri = 'entity:' . $link_entity_type . '/' . $link_entity->id();
            }
          }
        }

        $entity->set($field_name, $data[$field_name]);
      }
    }
  }
}
