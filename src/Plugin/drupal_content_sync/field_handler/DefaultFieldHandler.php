<?php

namespace Drupal\drupal_content_sync\Plugin\drupal_content_sync\field_handler;


use Drupal\drupal_content_sync\Plugin\FieldHandlerBase;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;


/**
 * Class DefaultFieldHandler, providing a minimalistic implementation for any
 * field type.
 *
 * @FieldHandler(
 *   id = "drupal_content_sync_default_field_handler",
 *   label = @Translation("Default"),
 *   weight = 100
 * )
 *
 * @package Drupal\drupal_content_sync\Plugin\drupal_content_sync\field_handler
 */
class DefaultFieldHandler extends FieldHandlerBase {
  public static function supports($entity_type,$bundle,$field_name,$field) {
    $allowed = ["string","integer","uuid","language","created","string_long","boolean","changed","datetime","map","text_with_summary","uri","email","path","timestamp","text_long","metatag"];
    return in_array($field->getType(),$allowed)!==FALSE;
  }
}
