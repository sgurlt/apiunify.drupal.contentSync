<?php

namespace Drupal\drupal_content_sync;

use Drupal\drupal_content_sync\Entity\Pool;
use GuzzleHttp\Exception\RequestException;

/**
 *
 */
class ApiUnifyPoolExport extends ApiUnifyExport {
  /**
   * @var string POOL_SITE_ID
   *   The virtual site id for the pool and it's connections / synchronizations.
   */
  const POOL_SITE_ID = '_pool';

  /**
   * @var string CUSTOM_API_VERSION
   *   The API version used to identify APIs as. Breaking changes in
   *   Flow will require this version to be increased and all
   *   synchronization entities to be re-saved via update hook.
   */
  const CUSTOM_API_VERSION = '1.0';

  /**
   * @var \Drupal\drupal_content_sync\Entity\Pool
   */
  protected $pool;

  /**
   * ApiUnifyConfig constructor.
   *
   * @param \Drupal\drupal_content_sync\Entity\Pool $pool
   *   The pool this exporter is used for.
   */
  public function __construct(Pool $pool) {
    parent::__construct();

    $this->pool = $pool;
  }

  /**
   * Get the absolute URL that API Unify should use to create, update or delete
   * an entity.
   *
   * @param string $api_id
   * @param string $entity_type_name
   * @param string $bundle_name
   * @param string $version
   * @param string $entity_uuid
   *
   * @return string
   */
  public static function getInternalUrl($api_id, $entity_type_name, $bundle_name, $version, $entity_uuid = NULL) {
    global $base_url;

    // @ToDo: Move to method.
    // Check if the base_url is overwritten within the settings.
    $dcs_settings = \Drupal::config('drupal_content_sync.settings');
    $dcs_base_url = $dcs_settings->get('dcs_base_url');
    if(isset($dcs_settings) && $dcs_base_url != '') {
      $export_url  = $dcs_base_url;
    } else {
      $export_url = $base_url;
    }

    $url = sprintf('%s/rest/dcs/%s/%s/%s/%s',
      $export_url,
      $api_id,
      $entity_type_name,
      $bundle_name,
      $version
    );
    if ($entity_uuid) {
      $url .= '/' . $entity_uuid;
    }
    $url .= '?_format=json&is_dependency=[is_dependency]&is_manual=[is_manual]';
    return $url;
  }

  /**
   * Initialize.
   *
   * Method do create all Drupal Content Sync
   * entities which are needed for a synchronization.
   *
   * @return bool
   */
  public function export() {
    $url = $this->pool->getBackendUrl();

    // Check if a connection to Drupal Content Sync can be established.
    try {
      // Create "drupal" API entity.
      $this->sendEntityRequest($url . '/api_unify-api_unify-api-0_1', [
        'json' => [
          'id' => 'drupal-' . ApiUnifyPoolExport::CUSTOM_API_VERSION,
          'name' => 'drupal',
          'version' => ApiUnifyPoolExport::CUSTOM_API_VERSION,
        ],
      ]);
      // Create the child entity.
      $this->sendEntityRequest($url . '/api_unify-api_unify-api-0_1', [
        'json' => [
          'id' => $this->pool->id . '-' . ApiUnifyPoolExport::CUSTOM_API_VERSION,
          'name' => $this->pool->label(),
          'version' => ApiUnifyPoolExport::CUSTOM_API_VERSION,
          'parent_id' => 'drupal-' . ApiUnifyPoolExport::CUSTOM_API_VERSION,
        ],
      ]);

      // Create the instance entity.
      $this->sendEntityRequest($url . '/api_unify-api_unify-instance-0_1', [
        'json' => [
          'id' => $this->pool->site_id,
          'api_id' => $this->pool->id . '-' . ApiUnifyPoolExport::CUSTOM_API_VERSION,
        ],
      ]);

      // Create the preview connection entity.
      $this->sendEntityRequest($url . '/api_unify-api_unify-connection-0_1', [
        'json' => [
          'id' => ApiUnifyFlowExport::PREVIEW_CONNECTION_ID,
          'name' => 'Drupal preview connection',
          'hash' => 'drupal/drupal-content-sync/preview',
          'usage' => 'EXTERNAL',
          'status' => 'READY',
          'entity_type_id' => ApiUnifyFlowExport::PREVIEW_ENTITY_ID,
          'options' => [
            'crud' => [
              'read_list' => [],
            ],
            'static_values' => [],
          ],
        ],
      ]);
    }
    catch (RequestException $e) {
      $messenger = \Drupal::messenger();
      $messenger->addWarning($e->getMessage());
      return FALSE;
    }
    catch (\Exception $e) {
      $messenger = \Drupal::messenger();
      $messenger->addWarning($e->getMessage());
      return FALSE;
    }
    return TRUE;
  }

  public function remove($removedOnly = TRUE) {
    return TRUE;
  }

}
