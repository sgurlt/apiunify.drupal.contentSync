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
      'api' => $drupal_content_sync->get('api') . '-'.DrupalContentSync::CUSTOM_API_VERSION,
      'url' => $drupal_content_sync->get('url'),
      'site_id' => $drupal_content_sync->get('site_id'),
      'local_connections' => json_decode($drupal_content_sync->get('local_connections')),
      'types' => [],
    ];

    foreach ($drupal_content_sync->get('sync_entities') as $type) {
      if( $type['handler']==DrupalContentSync::HANDLER_IGNORE ) {
        continue;
      }
      if ($type['preview'] == 'excluded') {
        continue;
      }

      $config['types'][] = [
        'id' => DrupalContentSync::getExternalConnectionId(
          $drupal_content_sync->api,
          $drupal_content_sync->site_id,
          $type['entity_type_name'],
          $type['bundle_name'],
          $type['version']
        ),
        'name' => $type['entity_type_name'].'_'.$type['bundle_name'],
        'html_preview' => $type['preview'] == 'preview_mode',
      ];
    }

    return [
      '#theme' => 'drupal_content_sync_content_dashboard',
      '#configuration' => json_encode($config),
      '#attached' => ['library' => ['drupal_content_sync/drupal-content-synchronization']],
    ];
  }

}
