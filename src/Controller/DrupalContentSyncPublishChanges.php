<?php

namespace Drupal\drupal_content_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;
use Drupal\webhooks\Webhook;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Publish changes controller.
 */
class DrupalContentSyncPublishChanges extends ControllerBase {

  /**
   * Published entity to API Unify.
   */
  public function publishChanges($sync_id, EntityInterface $entity, $repeat = FALSE) {
    $sync = DrupalContentSync::load($sync_id);

    $client = \Drupal::httpClient();
    $url = sprintf('%s/drupal/%s/%s/%s/%s', $sync->{'url'}, $sync->{'site_id'}, $entity->getEntityTypeId(), $entity->bundle(), $entity->uuid());

    try {
      $is_new = $client->get($url)->getStatusCode() != 200;
    }
    catch (\Exception $exception) {
      $is_new = TRUE;
    }

    $event = implode(':', ['entity', $entity->getEntityType()->id(), $is_new ? 'create' : 'update' ]);

    /** @var \Drupal\webhooks\WebhooksService $webhooks_service */
    $webhooks_service = \Drupal::service('webhooks.service');

    // Load all webhooks for the occurring event.
    $webhook_configs = $webhooks_service->loadMultipleByEvent($event);

    /** @var \Drupal\webhooks\Entity\WebhookConfig $webhook_config */
    foreach ($webhook_configs as $webhook_config) {
      // Create the Webhook object.
      $webhook = new Webhook(
        ['entity' => $entity->toArray(), 'publish_changes' => TRUE],
        [],
        $event
      );
      // Send the Webhook object.
      $webhooks_service->send($webhook_config, $webhook);
    }

    // Make sure that existing entities on all sites
    // will be updated even if API Unify doesn't have them yet.
    // For instance, all sites already have node "Home page".
    // When create request will be triggered to API Unify it will try to create
    // this node on all sites which
    // won't make it updated because it's already created.
    if ($is_new && !$repeat) {
      $this->publishChanges($sync_id, $entity, TRUE);
    }

    drupal_set_message('The changes has been successfully pushed.');
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
