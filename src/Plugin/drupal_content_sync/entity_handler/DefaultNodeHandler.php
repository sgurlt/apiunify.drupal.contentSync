<?php

namespace Drupal\drupal_content_sync\Plugin\drupal_content_sync\entity_handler;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\drupal_content_sync\ApiUnifyRequest;
use Drupal\drupal_content_sync\Plugin\EntityHandlerBase;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;

/**
 * Class DefaultNodeHandler, providing proper handling for published/unpublished
 * content.
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
   * @inheritdoc
   */
  public static function supports($entity_type, $bundle) {
    return $entity_type == 'node';
  }

  /**
   * @inheritdoc
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
   * @inheritdoc
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
   * @inheritdoc
   */
  public function getAllowedPreviewOptions() {
    return [
      'table' => 'Table',
      'preview_mode' => 'Preview mode',
    ];
  }

  /**
   * @inheritdoc
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
    // Not published? Ignore this revision then.
    if (empty($request->getField('status')) && $this->settings['handler_settings']['ignore_unpublished']) {
      // Unless it's a delete, then it won't have a status and is independent
      // of published state, so we don't ignore it.
      if ($action != DrupalContentSync::ACTION_DELETE) {
        return TRUE;
      }
    }

    return parent::ignoreImport($request, $is_clone, $reason, $action);
  }

  /**
   * @inheritdoc
   */
  public function ignoreExport(ApiUnifyRequest $request, FieldableEntityInterface $entity, $reason, $action) {
    /**
     * @var \Drupal\node\NodeInterface $entity
     */
    if (!$entity->isPublished() && $this->settings['handler_settings']['ignore_unpublished']) {
      return TRUE;
    }

    return parent::ignoreExport($request, $entity, $request, $action);
  }

}
