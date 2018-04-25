<?php

namespace Drupal\drupal_content_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Push changes controller.
 */
class DrupalContentSyncPushChanges extends ControllerBase {

  /**
   * Published entity to API Unify.
   *
   * @param string $sync_id
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function pushChanges($sync_id, FieldableEntityInterface $entity) {
    $sync = DrupalContentSync::load($sync_id);
    _drupal_content_sync_export_entity(
      $entity,
      DrupalContentSync::EXPORT_MANUALLY,
      DrupalContentSync::ACTION_CREATE,
      $sync
    );

    return new RedirectResponse('/');
  }

  /**
   * Returns an read_list entities for API Unify.
   *
   * TODO Should be removed when read_list will be allowed to omit.
   */
  public function pushChangesEntitiesList() {
    return new Response('[]');
  }

}
