<?php

namespace Drupal\drupal_content_sync\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 *
 */
class DrupalContentSyncIntroduction extends ControllerBase {

  /**
   *
   */
  public function content() {
    return [
      '#theme' => 'drupal_content_sync_introduction',
    ];
  }

}
