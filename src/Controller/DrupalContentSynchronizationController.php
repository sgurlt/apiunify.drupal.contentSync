<?php

namespace Drupal\drupal_content_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;

/**
 * Provides a listing of DrupalContentSync.
 */
class DrupalContentSynchronizationController extends ControllerBase {

  /**
   * Render the content synchronization Angular frontend.
   *
   * @param \Drupal\drupal_content_sync\Entity\DrupalContentSync $drupal_content_sync
   *
   * @return array
   */
  public function content(DrupalContentSync $drupal_content_sync) {
    $config = [
      'api' => $drupal_content_sync->get('api') . '-0.1',
      'url' => $drupal_content_sync->get('url'),
      'site_id' => $drupal_content_sync->get('site_id'),
      'local_connections' => json_decode($drupal_content_sync->get('local_connections')),
      'types' => [],
    ];

    foreach (json_decode($drupal_content_sync->get('sync_entities')) as $type) {
      if ($type->preview != 'excluded') {
        $config['types'][] = [
          'id' => 'drupal-' . $type->entity_type . '-' . $type->entity_bundle . '-' . $type->version_hash,
          'name' => $type->display_name,
          'html_preview' => $type->preview == 'preview_mode',
        ];
      }
    }

    return [
      '#theme' => 'drupal_content_sync_content_dashboard',
      '#configuration' => json_encode($config),
      '#attached' => ['library' => ['drupal_content_sync/drupal-content-synchronization']],
    ];
  }

}
