<?php

namespace Drupal\drupal_content_sync\Plugin\drupal_content_sync\entity_handler;

use Drupal\Core\Entity\EntityInterface;
use Drupal\drupal_content_sync\ApiUnifyRequest;
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

  /**
   * @ToDo: Add description.
   */
  public static function supports($entity_type, $bundle) {
    return $entity_type == 'node';
  }

  /**
   * @ToDo: Add description.
   */
  public function getAllowedExportOptions() {
    return [
      DrupalContentSync::EXPORT_DISABLED,
      DrupalContentSync::EXPORT_AUTOMATICALLY,
      DrupalContentSync::EXPORT_AS_DEPENDENCY,
      DrupalContentSync::EXPORT_MANUALLY,
    ];
  }

  /**
   * @ToDo: Add description.
   */
  public function getAllowedImportOptions() {
    return [
      DrupalContentSync::IMPORT_DISABLED,
      DrupalContentSync::IMPORT_AUTOMATICALLY,
      DrupalContentSync::IMPORT_AS_DEPENDENCY,
      DrupalContentSync::IMPORT_MANUALLY,
    ];
  }

  /**
   * @ToDo: Add description.
   */
  public function getAllowedPreviewOptions() {
    return [
      'table' => 'Table',
      'preview_mode' => 'Preview mode',
    ];
  }

  /**
   * @ToDo: Add description.
   */
  public function getHandlerSettings() {
    return [
      'ignore_unpublished' => [
        '#type' => 'checkbox',
        '#title' => 'Ignore unpublished content',
        '#default_value' => $this->settings['handler_settings']['ignore_unpublished'] === 0 ? 0 : 1,
      ],
      'restrict_editing' => [
        '#type' => 'checkbox',
        '#title' => 'Restrict editing of imported content',
        '#default_value' => isset($this->settings['handler_settings']['restrict_editing']) ? $this->settings['handler_settings']['restrict_editing'] == 1 : 0,
      ],
    ];
  }

  /**
   * @inheritdoc
   */
  public function ignoreImport(ApiUnifyRequest $request, $is_clone, $reason, $action) {
    if (empty($request->getField('status')) && $this->settings['handler_settings']['ignore_unpublished']) {
      return TRUE;
    }

    return parent::ignoreImport($request, $is_clone, $reason, $action);
  }

  /**
   * @inheritdoc
   */
  public function ignoreExport(ApiUnifyRequest $request, EntityInterface $entity, $reason, $action) {
    if (!$entity->isPublished() && $this->settings['handler_settings']['ignore_unpublished']) {
      return TRUE;
    }

    return parent::ignoreExport($request, $entity, $request, $action);
  }

}
