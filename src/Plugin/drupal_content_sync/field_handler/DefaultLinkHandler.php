<?php

namespace Drupal\drupal_content_sync\Plugin\drupal_content_sync\field_handler;

use Drupal\drupal_content_sync\Plugin\FieldHandlerBase;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;
use Drupal\drupal_content_sync\ApiUnifyRequest;
use Drupal\Core\Entity\EntityInterface;

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

  /**
   * @ToDo: Add description.
   */
  public static function supports($entity_type, $bundle, $field_name, $field) {
    $allowed = ["link"];
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
      $result = [];

      foreach ($data as &$link_element) {
        if( empty($link_element['uri']) ) {
          $reference = $request->loadEmbeddedEntity($link_element);
          if( $reference ) {
            $result[] = [
              'uri' => 'entity:' . $reference->getEntityType() . '/' . $reference->id()
            ];
          }
        }
        else {
          $result[] = [
            'uri' => $link_element['uri']
          ];
        }
      }

      $entity->set($this->fieldName, $result);
    }

    return TRUE;
  }

  public function export(ApiUnifyRequest $request,EntityInterface $entity,$reason,$action) {
    // Deletion doesn't require any action on field basis for static data
    if( $action==DrupalContentSync::ACTION_DELETE ) {
      return TRUE;
    }

    $data = $entity->get($this->fieldName);

    $result = [];

    foreach ($data as $key => $value) {
      $uri = &$data[$key]['uri'];
      // Find the linked entity and replace it's id with the UUID
      // References have following pattern: entity:entity_type/entity_id.
      preg_match('/^entity:(.*)\/(\d*)$/', $uri, $found);
      if (empty($found)) {
        $result[] = [
          'uri'     => $uri,
        ];
      }
      else {
        // @TODO Add option "auto export / import" just as reference fields do

        $link_entity_type = $found[1];
        $link_entity_id   = $found[2];
        $entity_manager   = \Drupal::entityTypeManager();
        $link_entity      = $entity_manager->getStorage($link_entity_type)
          ->load($link_entity_id);

        if (empty($link_entity)) {
          continue;
        }

        if( !$this->sync->supportsEntity($link_entity) ) {
          continue;
        }

        $result[] = $request->getEmbedEntityDefinition(
          $link_entity->getEntityType(),
          $link_entity->bundle(),
          $link_entity->uuid()
        );
      }
    }

    $request->setField($this->fieldName,$result);

    return TRUE;
  }

}
