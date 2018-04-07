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
 *   label = @Translation("Default Node"),
 *   weight = 90
 * )
 *
 * @package Drupal\drupal_content_sync\Plugin\drupal_content_sync\entity_handler
 */
class DefaultNodeHandler extends EntityHandlerBase {
  public static function supports($entity_type,$bundle) {
    return $entity_type=='node';
  }

  public function getAllowedExportOptions() {
    return [
      DrupalContentSync::EXPORT_DISABLED,
      DrupalContentSync::EXPORT_AUTOMATICALLY,
      DrupalContentSync::EXPORT_MANUALLY,
    ];
  }

  public function getAllowedSyncImportOptions() {
    return [
      DrupalContentSync::IMPORT_DISABLED,
      DrupalContentSync::IMPORT_AUTOMATICALLY,
      DrupalContentSync::IMPORT_MANUALLY,
    ];
  }

  public function getAllowedClonedImportOptions() {
    return [
      DrupalContentSync::IMPORT_DISABLED,
      DrupalContentSync::IMPORT_AUTOMATICALLY,
      DrupalContentSync::IMPORT_MANUALLY,
    ];
  }

  public function getAllowedPreviewOptions() {
    return [
      'table' => 'Table',
      'preview_mode' => 'Preview mode',
    ];
  }

  public function getHandlerSettings() {
    return [
      'export_published_only' => [
        '#type' => 'checkbox',
        '#title' => 'Export published only',
        '#default_value' => $this->settings['handler_settings']['export_published_only']===0 ? 0 : 1,
      ],
      'sync_import_published_only' => [
        '#type' => 'checkbox',
        '#title' => 'Import published only (sync)',
        '#default_value' => $this->settings['handler_settings']['sync_import_published_only']===0 ? 0 : 1,
      ],
      'cloned_import_published_only' => [
        '#type' => 'checkbox',
        '#title' => 'Import published only (clone)',
        '#default_value' => $this->settings['handler_settings']['cloned_import_published_only']===0 ? 0 : 1,
      ],
      'sync_menu_items' => [
        '#type' => 'checkbox',
        '#title' => 'Sync menu items',
        '#default_value' => isset($this->settings['handler_settings']['sync_menu_items']) ? $this->settings['handler_settings']['sync_menu_items'] == 1 : 0,
      ],
      'restrict_editing' => [
        '#type' => 'checkbox',
        '#title' => 'Restrict editing of synchronized content',
        '#default_value' => isset($this->settings['handler_settings']['restrict_editing']) ? $this->settings['handler_settings']['restrict_editing'] == 1 : 0,
      ],
    ];
  }
}
