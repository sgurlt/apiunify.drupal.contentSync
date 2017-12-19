<?php

namespace Drupal\drupal_content_sync;

use Drupal\webhooks\Entity\WebhookConfig;
use Drupal\webhooks\Event\SendEvent;
use Drupal\webhooks\Event\WebhookEvents;
use Drupal\webhooks\Webhook;
use Drupal\webhooks\WebhooksService;
use Drupal\Component\Uuid\Php as Uuid;

/**
 * Drupal Content Sync Webhook Service.
 */
class DrupalContentSyncWebhookService extends WebhooksService {

  /**
   * @const DRUPAL_CONTENT_SYNC_PAYLOAD_URL
   */
  const DRUPAL_CONTENT_SYNC_PAYLOAD_URL = '[drupal-content-sync-url]';

  /**
   * An array of entities that has been exported.
   *
   * @var array
   */
  protected $exportedEntities = [];

  /**
   * Send a webhook.
   *
   * @param \Drupal\webhooks\Entity\WebhookConfig $webhook_config
   *   A webhook config entity.
   * @param \Drupal\webhooks\Webhook $webhook
   *   A webhook object.
   */
  public function send(WebhookConfig $webhook_config, Webhook $webhook) {
    if (self::DRUPAL_CONTENT_SYNC_PAYLOAD_URL !== $webhook_config->getPayloadUrl()) {
      parent::send($webhook_config, $webhook);
      return;
    }

    $drupal_content_syncs = $this->entityTypeManager
      ->getStorage('drupal_content_sync')
      ->loadMultiple();

    $entity_event = $webhook->getEvent();
    preg_match('/^.+:(.+):(.+)$/', $entity_event, $matches);
    $entity_type = $matches[1];
    $event_type = $matches[2];
    $entity_bundle = NULL;
    $entity_id = NULL;

    $entity_payload = $webhook->getPayload();

    //Skip entities without the field_drupal_content_synced or if it's true
    if (isset($entity_payload['entity']['field_drupal_content_synced']) &&
      !empty($synced = $entity_payload['entity']['field_drupal_content_synced']) &&
      $synced) {
      return;
    }

    if (isset($entity_payload['entity']['uuid'])) {
      $entity_id = reset($entity_payload['entity']['uuid'])['value'];
    }

    if ('taxonomy_term' === $entity_type) {
      $entity_bundle = reset($entity_payload['entity']['vid'])['target_id'];
    }
    elseif ('node' === $entity_type) {
      $entity_bundle = reset($entity_payload['entity']['type'])['target_id'];
    }
    elseif ('file' === $entity_type) {
      if ($entity_payload['entity']['status'][0]['value']) {

      }
      $entity_bundle = $entity_type;
    }
    elseif (isset($entity_payload['entity']['bundle'])) {
      $bundle = reset($entity_payload['entity']['bundle']);
      if (isset($bundle['target_id'])) {
        $entity_bundle = ['target_id'];
      }
      elseif (isset($bundle['value'])) {
        $entity_bundle = $bundle['value'];
      }
    }
    elseif (isset($entity_payload['entity']['type'])) {
      $entity_bundle = reset($entity_payload['entity']['type'])['target_id'];
    }

    if (is_null($entity_type) || is_null($entity_bundle)) {
      return;
    }

    /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository */
    $entity_repository = \Drupal::service('entity.repository');

    foreach ($drupal_content_syncs as $sync) {
      $sync_entities = json_decode($sync->{'sync_entities'}, TRUE);

      foreach ($sync_entities as $sync_entity_key => $sync_entity) {
        preg_match('/^(.+)-(.+)$/', $sync_entity_key, $matches);

        $sync_entity_type_key = $matches[1];
        $sync_entity_bundle_key = $matches[2];

        $is_exported = (bool) $sync_entity['export'];

        if ($is_exported && $sync_entity_type_key === $entity_type && $sync_entity_bundle_key === $entity_bundle) {
          if ('delete' != $event_type) {
            $this->preFormatEntity($webhook, $sync->{'site_id'}, $entity_type, $entity_bundle);

            $preprocessed_entity = $webhook->getPayload();
            if (!empty($preprocessed_entity['embed_entities'])) {
              foreach ($preprocessed_entity['embed_entities'] as $data) {
                if (in_array($data['uuid'], $this->exportedEntities)) {
                  // Make sure that we won't export one entity twice.
                  continue;
                }

                $this->exportedEntities[] = $data['uuid'];

                try {
                  if ($embed_entity = $entity_repository->loadEntityByUuid($data['type'], $data['uuid'])) {
                    $event = implode(':', ['entity', $embed_entity->getEntityTypeId(), 'create']);
                    $embed_entity_webhook = new Webhook(['entity' => $embed_entity->toArray()], [], $event);

                    $webhook_config->set('payload_url', self::DRUPAL_CONTENT_SYNC_PAYLOAD_URL);
                    $this->send($webhook_config, $embed_entity_webhook);
                  }
                }
                catch (\Exception $exception) {
                }
              }
            }
          }

          if ('create' === $event_type) {
            $url = sprintf('%s/drupal/%s/%s/%s', $sync->{'url'}, $sync->{'site_id'}, $entity_type, $entity_bundle);
          }
          else {
            $url = sprintf('%s/drupal/%s/%s/%s/%s', $sync->{'url'}, $sync->{'site_id'}, $entity_type, $entity_bundle, $entity_id);
          }

          $webhook_config->set('payload_url', $url);
          $this->sendRequest($webhook_config, $webhook, $event_type);
        }
      }
    }
  }

  protected function preFormatEntity(Webhook &$webhook, $site_id, $entity_type, $bundle) {
    $entity_data = $webhook->getPayload();
    $webhook->setPayload(_drupal_content_sync_preprocess_entity($entity_data['entity'], $entity_type, $bundle, $site_id, true));
  }

  /**
   * Send a webhook.
   *
   * @param \Drupal\webhooks\Entity\WebhookConfig $webhook_config
   *   A webhook config entity.
   * @param \Drupal\webhooks\Webhook $webhook
   *   A webhook object.
   * @param string
   *   A type of request.
   */
  protected function sendRequest(WebhookConfig $webhook_config, Webhook $webhook, $type) {
    $uuid = new Uuid();
    $webhook->setUuid($uuid->generate());
    if ($secret = $webhook_config->getSecret()) {
      $webhook->setSecret($secret);
      $webhook->setSignature();
    }

    $headers = $webhook->getHeaders();
    $body = self::encode(
      $webhook->getPayload(),
      $webhook_config->getContentType()
    );

    try {
      switch ($type) {
        case 'create':
          $this->client->post(
            $webhook_config->getPayloadUrl(),
            ['headers' => $headers, 'body' => $body]
          );
          break;

        case 'update':
          $this->client->put(
            $webhook_config->getPayloadUrl(),
            ['headers' => $headers, 'body' => $body]
          );
          break;

        case 'delete':
          $this->client->delete(
            $webhook_config->getPayloadUrl(),
            ['headers' => $headers]
          );
          break;
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('webhooks')->error(
        'Could not send Webhook @webhook: @message, url: @url',
        ['@webhook' => $webhook_config->id(), '@message' => $e->getMessage(), '@url' => $webhook_config->getPayloadUrl()]
      );
    }

    // Dispatch Webhook Send event.
    $this->eventDispatcher->dispatch(
      WebhookEvents::SEND,
      new SendEvent($webhook_config, $webhook)
    );

  }
}
