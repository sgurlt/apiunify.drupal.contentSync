<?php

namespace Drupal\drupal_content_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Publish changes controller.
 */
class DrupalContentSyncPublishChanges extends ControllerBase {

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * DrupalContentSyncPublishChanges constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

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
      $this->messenger->addMessage('The changes has been successfully pushed.');
    }
    else {
      $this->messenger->addError('An error occured while pushing the changes to the ' .
        'Drupal Content Sync backend. Please try again later.');
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
