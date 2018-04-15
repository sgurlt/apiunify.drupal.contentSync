<?php

namespace Drupal\drupal_content_sync\Plugin\drupal_content_sync\field_handler;

use Drupal\drupal_content_sync\Plugin\FieldHandlerBase;

/**
 * Providing a minimalistic implementation for any field type.
 *
 * @FieldHandler(
 *   id = "drupal_content_sync_default_bricks_handler",
 *   label = @Translation("Default Bricks"),
 *   weight = 90
 * )
 *
 * @package Drupal\drupal_content_sync\Plugin\drupal_content_sync\field_handler
 */
class DefaultBricksHandler extends FieldHandlerBase {

  /**
   *
   */
  public static function supports($entity_type, $bundle, $field_name, $field) {
    // @TODO Implement this handler.
    return FALSE;

    $allowed = ["bricks", "bricks_revisioned"];
    if (in_array($field->getType(), $allowed) !== FALSE) {
      return TRUE;
    }

    /*if( $field->getType()=="entity_reference" && $field->getSetting('target_type')=='brick_type' ) {
    return TRUE;
    }*/

    return FALSE;
  }

}
