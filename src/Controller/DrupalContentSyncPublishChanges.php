<?php

namespace Drupal\drupal_content_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Publish changes controller.
 */
class DrupalContentSyncPublishChanges extends ControllerBase {

  /**
   * Published entity to API Unify.
   */
  public function publishChanges($sync_id, EntityInterface $entity) {
    $sync   = DrupalContentSync::load($sync_id);
    $result = $sync->exportEntity(
      $entity,
      DrupalContentSync::EXPORT_MANUALLY
    );

    if ($result) {
      drupal_set_message('The changes has been successfully pushed.');
    }
    else {
      drupal_set_message('An error occured while pushing the changes to the ' .
        'Drupal Content Sync backend. Please try again later', 'error');
    }

    return new RedirectResponse('/');
  }

  /**
   * Returns an read_list entities for API Unify.
   *
   * TODO Should be removed when read_list will be allowed to omit.
   */
  public function publishChangesEntitiesList() {
    return new Response('[]');
  }

}
