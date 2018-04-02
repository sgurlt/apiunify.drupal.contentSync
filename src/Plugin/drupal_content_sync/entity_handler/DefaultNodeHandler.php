<?php

namespace Drupal\drupal_content_sync\Plugin\drupal_content_sync\entity_handler;


use Drupal\drupal_content_sync\Plugin\EntityHandlerBase;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;


/**
 * Class DefaultEntityHandler, providing a minimalistic implementation for any
 * entity type.
 *
 * @EntityHandler(
 *   id = "drupal_content_sync_default_node_handler",
 *   label = @Translation("Node"),
 *   weight = 90
 * )
 *
 * @package Drupal\drupal_content_sync\Plugin\drupal_content_sync\entity_handler
 */
class DefaultNodeHandler extends EntityHandlerBase {
  public function supports($entity_type,$bundle) {
    return $entity_type=='node';
  }

  public function getAllowedExportOptions($entity_type,$bundle) {
    return [
      DrupalContentSync::EXPORT_DISABLED,
      DrupalContentSync::EXPORT_AUTOMATICALLY,
      DrupalContentSync::EXPORT_MANUALLY,
    ];
  }

  public function getAllowedSyncImportOptions($entity_type,$bundle) {
    return [
      DrupalContentSync::IMPORT_DISABLED,
      DrupalContentSync::IMPORT_AUTOMATICALLY,
      DrupalContentSync::IMPORT_MANUALLY,
    ];
  }

  public function getAllowedClonedImportOptions($entity_type,$bundle) {
    return [
      DrupalContentSync::IMPORT_DISABLED,
      DrupalContentSync::IMPORT_AUTOMATICALLY,
      DrupalContentSync::IMPORT_MANUALLY,
    ];
  }

  public function getAllowedPreviewOptions($entity_type,$bundle) {
    return [
      'table' => 'Table',
      'preview_mode' => 'Preview mode',
    ];
  }

  public function getAdvancedSettings() {
    return [
      'export_published_only' => 'Export published only',
      'sync_import_published_only' => 'Sync import published only',
      'cloned_import_published_only' => 'Clone import published only',
      'sync_menu_items' => 'Sync menu items',
    ];
  }

  public function getAdvancedSettingsForEntityType($entity_type,$bundle,$default_values) {
    return [
      'export_published_only' => [
        '#type' => 'checkbox',
        '#title' => 'Published only',
        '#title_display' => 'invisible',
        '#default_value' => $default_values['export_published_only']===0 ? 0 : 1,
      ],
      'sync_import_published_only' => [
        '#type' => 'checkbox',
        '#title' => 'Published only',
        '#title_display' => 'invisible',
        '#default_value' => $default_values['sync_import_published_only']===0 ? 0 : 1,
      ],
      'cloned_import_published_only' => [
        '#type' => 'checkbox',
        '#title' => 'Published only',
        '#title_display' => 'invisible',
        '#default_value' => $default_values['cloned_import_published_only']===0 ? 0 : 1,
      ],
      'sync_menu_items' => [
        '#type' => 'checkbox',
        '#title' => 'Sync menu items',
        '#title_display' => 'invisible',
        '#default_value' => isset($default_values['sync_menu_items']) ? $default_values['sync_menu_items'] == 1 : 0,
      ],
    ];
  }
}
