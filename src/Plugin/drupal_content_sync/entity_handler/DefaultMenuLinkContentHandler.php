<?php

namespace Drupal\drupal_content_sync\Plugin\drupal_content_sync\entity_handler;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\drupal_content_sync\ApiUnifyRequest;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;
use Drupal\drupal_content_sync\Entity\DrupalContentSyncMetaInformation;
use Drupal\drupal_content_sync\Plugin\EntityHandlerBase;

/**
 * Class DefaultMenuLinkContentHandler, providing a minimalistic implementation
 * for menu items, making sure they're referenced correctly by UUID.
 *
 * @EntityHandler(
 *   id = "drupal_content_sync_default_menu_link_content_handler",
 *   label = @Translation("Default Menu Link Content"),
 *   weight = 100
 * )
 *
 * @package Drupal\drupal_content_sync\Plugin\drupal_content_sync\entity_handler
 */
class DefaultMenuLinkContentHandler extends EntityHandlerBase {

  /**
   * @inheritdoc
   */
  public static function supports($entity_type, $bundle) {
    return $entity_type == 'menu_link_content';
  }

  /**
   * @inheritdoc
   */
  public function getAllowedPreviewOptions() {
    return [
      'table' => 'Table',
    ];
  }

  /**
   * @inheritdoc
   */
  public function getHandlerSettings() {
    $menus  = menu_ui_get_menus();
    return [
      'ignore_unpublished' => [
        '#type' => 'checkbox',
        '#title' => 'Ignore disabled',
        '#default_value' => $this->settings['handler_settings']['ignore_unpublished'] === 0 ? 0 : 1,
      ],
      'restrict_menus' => [
        '#type' => 'checkboxes',
        '#title' => 'Restrict to menus',
        '#default_value' => $this->settings['handler_settings']['restrict_menus'],
        '#options' => $menus,
      ],
    ];
  }

  /**
   * @inheritdoc
   */
  public function ignoreImport(ApiUnifyRequest $request, $is_clone, $reason, $action) {
    // Not published? Ignore this revision then.
    if ((empty($request->getField('enabled')) || !$request->getField('enabled')[0]['value']) && $this->settings['handler_settings']['ignore_unpublished']) {
      // Unless it's a delete, then it won't have a status and is independent
      // of published state, so we don't ignore the import.
      if ($action != DrupalContentSync::ACTION_DELETE) {
        return TRUE;
      }
    }

    if(!empty($this->settings['handler_settings']['restrict_menus'])) {
      $menu = $request->getField('menu_name')[0]['value'];
      if(empty($this->settings['handler_settings']['restrict_menus'][$menu])) {
        return TRUE;
      }
    }

    $link = $request->getField('link');
    if(isset($link[0]['uri'])) {
      $uri  = $link[0]['uri'];
      preg_match('/^internal:/([a-z_0-9]+)\/([a-z0-9-]+)$/', $uri, $found);
      if(!empty($found)) {
        $request->setField('enabled',[['value'=>0]]);
      }
    }
    elseif(!empty($link[0][ApiUnifyRequest::ENTITY_TYPE_KEY]) && !empty($link[0][ApiUnifyRequest::UUID_KEY])) {
      $request->setField('enabled',[['value'=>0]]);
    }

    return parent::ignoreImport($request, $is_clone, $reason, $action);
  }

  /**
   * @inheritdoc
   */
  public function ignoreExport(ApiUnifyRequest $request, FieldableEntityInterface $entity, $reason, $action) {
    /**
     * @var \Drupal\menu_link_content\Entity\MenuLinkContent $entity
     */
    if (!$entity->isEnabled() && $this->settings['handler_settings']['ignore_unpublished']) {
      return TRUE;
    }

    if(!empty($this->settings['handler_settings']['restrict_menus'])) {
      $menu = $entity->getMenuName();
      if(empty($this->settings['handler_settings']['restrict_menus'][$menu])) {
        return TRUE;
      }
    }

    $uri = $entity->get('link')->getValue()[0]['uri'];
    if(substr($uri,0,7)=='entity:') {
      preg_match('/^entity:(.*)\/(\d*)$/', $uri, $found);
      // This means we're already dealing with a UUID that has not been resolved
      // locally yet. So there's no sense in exporting this back to the pool.
      if (empty($found)) {
        return TRUE;
      }
      else {
        $link_entity_type = $found[1];
        $link_entity_id   = $found[2];
        $entity_manager   = \Drupal::entityTypeManager();
        $reference        = $entity_manager->getStorage($link_entity_type)
          ->load($link_entity_id);
        // Dead reference > ignore
        if(empty($reference)) {
          return TRUE;
        }

        // Sync not supported > Ignore
        if (!$this->sync->supportsEntity($reference)) {
          return TRUE;
        }

        $meta_infos = DrupalContentSyncMetaInformation::getInfoForEntity($link_entity_type,$reference->uuid(),$this->sync->api);
        $exported   = FALSE;
        foreach($meta_infos as $sync=>$info) {
          if(!$info || !$info->getLastExport()) {
            continue;
          }
          $exported = TRUE;
        }
        if(!$exported && !$this->sync->isExporting($link_entity_type,$reference->uuid())) {
          return TRUE;
        }
      }
    }

    return parent::ignoreExport($request, $entity, $request, $action);
  }

}