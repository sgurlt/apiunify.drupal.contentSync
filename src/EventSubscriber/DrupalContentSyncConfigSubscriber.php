<?php

namespace Drupal\drupal_content_sync\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\user\Entity\User;

/**
 * A subscriber triggering a config when certain configuration changes.
 */
class DrupalContentSyncConfigSubscriber implements EventSubscriberInterface {

  /**
   * Change form display for paragraph.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The Event to process.
   */
  public function onSave(ConfigCrudEvent $event) {

    $saved_config = $event->getConfig();
    if ($saved_config->getName() == 'encrypt.profile.drupal_content_sync') {
      $data = [
        'userName' => 'Drupal Content Sync',
        'userPass' => user_password(),
      ];
      drupal_content_sync_encrypt_values($data);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::SAVE][] = ['onSave'];
    return $events;
  }

}
