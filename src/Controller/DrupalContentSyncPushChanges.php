<?php

namespace Drupal\drupal_content_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\drupal_content_sync\DrupalContentSync;
use Drupal\drupal_content_sync\Entity\Flow;
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
    $sync = Flow::load($sync_id);
    if (!DrupalContentSync::exportEntityFromUi(
      $entity,
      Flow::EXPORT_MANUALLY,
      Flow::ACTION_UPDATE,
      $sync
    )) {
      $messenger = \Drupal::messenger();
      $messenger->addWarning(t('%label is not configured to be exported with Drupal Content Sync.', ['%label' => $entity->label()]));
    }

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
