<?php

namespace Drupal\drupal_content_sync\Plugin\drupal_content_sync\field_handler;


use Drupal\drupal_content_sync\Plugin\FieldHandlerBase;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;


/**
 * Class DefaultFieldHandler, providing a minimalistic implementation for any
 * field type.
 *
 * @FieldHandler(
 *   id = "drupal_content_sync_default_panelizer_handler",
 *   label = @Translation("Default Panelizer"),
 *   weight = 90
 * )
 *
 * @package Drupal\drupal_content_sync\Plugin\drupal_content_sync\field_handler
 */
class DefaultPanelizerHandler extends FieldHandlerBase {
  public static function supports($entity_type,$bundle,$field_name,$field) {
    $allowed = ["panelizer"];
    return in_array($field->getType(),$allowed)!==FALSE;
  }
}
