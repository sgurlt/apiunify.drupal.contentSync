<?php

namespace Drupal\drupal_content_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\drupal_content_sync\ApiUnifyConfig;
use Drupal\drupal_content_sync\Entity\Flow;

/**
 * Provides a listing of Flow.
 */
class DrupalContentSynchronizationController extends ControllerBase {

  /**
   * Render the content synchronization Angular frontend.
   *
   * @param \Drupal\drupal_content_sync\Entity\Flow $drupal_content_sync
   *
   * @return array
   */
  public function content(Flow $drupal_content_sync) {
    $config = [
      'api' => $drupal_content_sync->get('api') . '-' . ApiUnifyConfig::CUSTOM_API_VERSION,
      'url' => $drupal_content_sync->get('url'),
      'site_id' => $drupal_content_sync->get('site_id'),
      'local_connections' => $drupal_content_sync->local_connections,
      'types' => [],
    ];

    foreach ($drupal_content_sync->get('sync_entities') as $type) {
      if ($type['handler'] == Flow::HANDLER_IGNORE || $type['handler'] == 'drupal_content_sync_default_field_handler') {
        continue;
      }
      if (isset($type['preview']) && $type['preview'] == 'excluded') {
        continue;
      }

      $config['types'][] = [
        'id' => ApiUnifyConfig::getExternalConnectionId(
          $drupal_content_sync->api,
          $drupal_content_sync->site_id,
          $type['entity_type_name'],
          $type['bundle_name'],
          $type['version']
        ),
        'name' => $type['entity_type_name'] . '_' . $type['bundle_name'],
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
