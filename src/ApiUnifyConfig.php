<?php

namespace Drupal\drupal_content_sync;

use Drupal\drupal_content_sync\Entity\DrupalContentSync;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Site\Settings;

/**
 * Class ApiUnifyConfig used to export the Synchronization config to the API
 * Unify backend.
 */
class ApiUnifyConfig {

  /**
   * @var string POOL_SITE_ID
   *   The virtual site id for the pool and it's connections / synchronizations.
   */
  const POOL_SITE_ID = '_pool';

  /**
   * @var string PREVIEW_CONNECTION_ID
   *   The unique connection ID in API Unify used to store preview entities at.
   */
  const PREVIEW_CONNECTION_ID = 'drupal_drupal-content-sync_preview';
  /**
   * @var string PREVIEW_ENTITY_ID
   *   The entity type ID from API Unify used to store preview entities as.
   */
  const PREVIEW_ENTITY_ID = 'drupal-synchronization-entity_preview-0_1';
  /**
   * @var string PREVIEW_ENTITY_VERSION
   *   The preview entity version (see above).
   */
  const PREVIEW_ENTITY_VERSION = '0.1';

  /**
   * @var string CUSTOM_API_VERSION
   *   The API version used to identify APIs as. Breaking changes in
   *   DrupalContentSync will require this version to be increased and all
   *   synchronization entities to be re-saved via update hook.
   */
  const CUSTOM_API_VERSION = '1.0';

  /**
   * @var string READ_LIST_ENTITY_ID
   *   "ID" used to perform list requests in the
   *   {@see DrupalContentSyncEntityResource}. Should be refactored later.
   */
  const READ_LIST_ENTITY_ID = '0';

  /**
   * @var string DEPENDENCY_CONNECTION_ID
   *   The format for connection IDs. Must be used consequently to allow
   *   references to be resolved correctly.
   */
  const DEPENDENCY_CONNECTION_ID = 'drupal-[api.name]-[instance.id]-[entity_type.name_space]-[entity_type.name]-[entity_type.version]';
  /**
   * @var string POOL_DEPENDENCY_CONNECTION_ID
   *   Same as {@see DrupalContentSync::DEPENDENCY_CONNECTION_ID} but for the
   *   pool connection.
   */
  const POOL_DEPENDENCY_CONNECTION_ID = 'drupal-[api.name]-' . self::POOL_SITE_ID . '-[entity_type.name_space]-[entity_type.name]-[entity_type.version]';

  /**
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * @var \Drupal\drupal_content_sync\Entity\DrupalContentSync
   */
  protected $sync;

  /**
   * @var array
   *   All entities that have to be deleted after new export.
   */
  protected $toBeDeleted = [];

  /**
   * @var array
   *   A list of existing entities, cached for better performance.
   */
  protected $unifyData = [];

  /**
   * @var bool
   *   Whether entities can be deleted.
   */
  protected $dataCleanPrepared = FALSE;

  /**
   * ApiUnifyConfig constructor.
   *
   * @param \Drupal\drupal_content_sync\Entity\DrupalContentSync $sync
   *   The sync this exporter is used for.
   */
  public function __construct(DrupalContentSync $sync) {
    // Check if the site id got set within the settings*.php.
    // @ToDo: POOL_REFACTOR
    return;
    $dcs_settings = Settings::get('drupal_content_sync');
    if (!is_null($dcs_settings) && isset($dcs_settings[$sync->id])) {
      $sync->site_id = $dcs_settings[$sync->id];
    }
    $this->sync   = $sync;
    $this->client = \Drupal::httpClient();
  }

  /**
   * Get the API Unify connection ID for the given entity type config.
   *
   * @param string $api_id
   *   API ID from this config.
   * @param string $site_id
   *   ID from this site from this config.
   * @param string $entity_type_name
   *   The entity type.
   * @param string $bundle_name
   *   The bundle.
   * @param string $version
   *   The version. {@see DrupalContentSync::getEntityTypeVersion}.
   *
   * @return string A unique connection ID.
   */
  public static function getExternalConnectionId($api_id, $site_id, $entity_type_name, $bundle_name, $version) {
    return sprintf('drupal-%s-%s-%s-%s-%s',
      $api_id,
      $site_id,
      $entity_type_name,
      $bundle_name,
      $version
    );
  }

  /**
   * Get the API Unify entity type ID for the given entity type config.
   *
   * @param string $api_id
   *   API ID from this config.
   * @param string $entity_type_name
   *   The entity type.
   * @param string $bundle_name
   *   The bundle.
   * @param string $version
   *   The version. {@see DrupalContentSync::getEntityTypeVersion}.
   *
   * @return string A unique entity type ID.
   */
  public static function getExternalEntityTypeId($api_id, $entity_type_name, $bundle_name, $version) {
    return sprintf('drupal-%s-%s-%s-%s',
      $api_id,
      $entity_type_name,
      $bundle_name,
      $version
    );
  }

  /**
   * Get the API Unify connection path for the given entity type config.
   *
   * @param string $api_id
   *   API ID from this config.
   * @param string $site_id
   *   ID from this site from this config.
   * @param string $entity_type_name
   *   The entity type.
   * @param string $bundle_name
   *   The bundle.
   * @param string $version
   *   The version. {@see DrupalContentSync::getEntityTypeVersion}.
   *
   * @return string A unique connection path.
   */
  public static function getExternalConnectionPath($api_id, $site_id, $entity_type_name, $bundle_name, $version) {
    return sprintf('drupal/%s/%s/%s/%s/%s',
      $api_id,
      $site_id,
      $entity_type_name,
      $bundle_name,
      $version
    );
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

    $url = sprintf('%s/rest/dcs/%s/%s/%s/%s',
      $base_url,
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
   * Wrapper for {@see DrupalContentSync::getInternalUrl} for the "create_item"
   * operation.
   *
   * @param $api_id
   * @param $entity_type_name
   * @param $bundle_name
   * @param $version
   *
   * @return string
   */
  public static function getInternalCreateItemUrl($api_id, $entity_type_name, $bundle_name, $version) {
    return self::getInternalUrl($api_id, $entity_type_name, $bundle_name, $version) . '&is_clone=[is_clone]';
  }

  /**
   * Wrapper for {@see DrupalContentSync::getInternalUrl} for the "update_item"
   * operation.
   *
   * @param $api_id
   * @param $entity_type_name
   * @param $bundle_name
   * @param $version
   *
   * @return string
   */
  public static function getInternalUpdateItemUrl($api_id, $entity_type_name, $bundle_name, $version) {
    return self::getInternalUrl($api_id, $entity_type_name, $bundle_name, $version, '[id]');
  }

  /**
   * Wrapper for {@see DrupalContentSync::getInternalUrl} for the "delete_item"
   * operation.
   *
   * @param $api_id
   * @param $entity_type_name
   * @param $bundle_name
   * @param $version
   *
   * @return string
   */
  public static function getInternalDeleteItemUrl($api_id, $entity_type_name, $bundle_name, $version) {
    return self::getInternalUrl($api_id, $entity_type_name, $bundle_name, $version, '[id]');
  }

  /**
   * Wrapper for {@see DrupalContentSync::getInternalUrl} for the "read_list"
   * operation.
   *
   * @param $api_id
   * @param $entity_type_name
   * @param $bundle_name
   * @param $version
   *
   * @return string
   */
  public static function getInternalReadListUrl($api_id, $entity_type_name, $bundle_name, $version) {
    return self::getInternalUrl($api_id, $entity_type_name, $bundle_name, $version, self::READ_LIST_ENTITY_ID);
  }

  /**
   * Initialize.
   *
   * Method do create all Drupal Content Sync
   * entities which are needed for a synchronization.
   *
   * @return bool
   */
  public function exportConfig() {
    $url = $this->sync->url;

    // Check if a connection to Drupal Content Sync can be established.
    try {
      // Create "drupal" API entity.
      $this->sendEntityRequest($url . '/api_unify-api_unify-api-0_1', [
        'json' => [
          'id' => 'drupal-' . self::CUSTOM_API_VERSION,
          'name' => 'drupal',
          'version' => self::CUSTOM_API_VERSION,
        ],
      ]);
      // Create the child entity.
      $this->sendEntityRequest($url . '/api_unify-api_unify-api-0_1', [
        'json' => [
          'id' => $this->sync->api . '-' . self::CUSTOM_API_VERSION,
          'name' => $this->sync->api,
          'version' => self::CUSTOM_API_VERSION,
          'parent_id' => 'drupal-' . self::CUSTOM_API_VERSION,
        ],
      ]);

      // Create the instance entity.
      $this->sendEntityRequest($url . '/api_unify-api_unify-instance-0_1', [
        'json' => [
          'id' => $this->sync->site_id,
          'api_id' => $this->sync->api . '-' . self::CUSTOM_API_VERSION,
        ],
      ]);

      // Create the preview connection entity.
      $this->sendEntityRequest($url . '/api_unify-api_unify-connection-0_1', [
        'json' => [
          'id' => self::PREVIEW_CONNECTION_ID,
          'name' => 'Drupal preview connection',
          'hash' => 'drupal/drupal-content-sync/preview',
          'usage' => 'EXTERNAL',
          'status' => 'READY',
          'entity_type_id' => self::PREVIEW_ENTITY_ID,
          'options' => [
            'crud' => [
              'read_list' => [],
            ],
            'static_values' => [],
          ],
        ],
      ]);

      $this->createEntityTypes();
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

  /**
   * Create all entity types, connections and synchronizations as required.
   *
   * @throws \Exception If the user profile for import is not available.
   */
  protected function createEntityTypes() {
    global $base_url;

    $url          = $this->sync->url;
    $api          = $this->sync->api;
    $site_id      = $this->sync->site_id;
    $entity_types = $this->sync->sync_entities;

    $localConnections = [];

    foreach ($this->sync->getEntityTypeConfig() as $id => $type) {
      $entity_type_name = $type['entity_type_name'];
      $bundle_name      = $type['bundle_name'];
      $version          = $type['version'];

      if ($type['handler'] == DrupalContentSync::HANDLER_IGNORE) {
        continue;
      }
      $handler = $this->sync->getEntityTypeHandler($type);

      $entityFieldManager = \Drupal::service('entity_field.manager');
      /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $fields */
      $fields = $entityFieldManager->getFieldDefinitions($entity_type_name, $bundle_name);

      $entity_type_id = self::getExternalEntityTypeId($api, $entity_type_name, $bundle_name, $version);
      $entity_type = [
        'id' => $entity_type_id,
        'name_space' => $entity_type_name,
        'name' => $bundle_name,
        'version' => $version,
        'base_class' => "api-unify/services/drupal/v0.1/models/base.model",
        'custom' => TRUE,
        'new_properties' => [
          'source' => [
            'type' => 'reference',
            'default_value' => NULL,
            'connection_identifiers' => [
              [
                'properties' => [
                  'id' => 'source_connection_id',
                ],
              ],
            ],
            'model_identifiers' => [
              [
                'properties' => [
                  'id' => 'source_id',
                ],
              ],
            ],
            'multiple' => FALSE,
          ],
          'source_id' => [
            'type' => 'id',
            'default_value' => NULL,
          ],
          'source_connection_id' => [
            'type' => 'id',
            'default_value' => NULL,
          ],
          'preview' => [
            'type' => 'string',
            'default_value' => NULL,
          ],
          'url' => [
            'type' => 'string',
            'default_value' => NULL,
          ],
          'apiu_translation' => [
            'type' => 'object',
            'default_value' => NULL,
          ],
          'metadata' => [
            'type' => 'object',
            'default_value' => NULL,
          ],
          'embed_entities' => [
            'type' => 'object',
            'default_value' => NULL,
            'multiple' => TRUE,
          ],
          'title' => [
            'type' => 'string',
            'default_value' => NULL,
          ],
          'created' => [
            'type' => 'int',
            'default_value' => NULL,
          ],
          'changed' => [
            'type' => 'int',
            'default_value' => NULL,
          ],
          'uuid' => [
            'type' => 'string',
            'default_value' => NULL,
          ],
        ],
        'new_property_lists' => [
          'list' => [
            '_resource_url' => 'value',
            '_resource_connection_id' => 'value',
            'id' => 'value',
          ],
          'reference' => [
            '_resource_url' => 'value',
            '_resource_connection_id' => 'value',
            'id' => 'value',
          ],
          'details' => [
            '_resource_url' => 'value',
            '_resource_connection_id' => 'value',
            'id' => 'value',
            'source' => 'reference',
            'apiu_translation' => 'value',
            'metadata' => 'value',
            'embed_entities' => 'value',
            'title' => 'value',
            'created' => 'value',
            'changed' => 'value',
            'uuid' => 'value',
            'url'=>'value',
          ],
          'database' => [
            'id' => 'value',
            'source_id' => 'value',
            'source_connection_id' => 'value',
            'preview' => 'value',
            'url' => 'value',
            'apiu_translation' => 'value',
            'metadata' => 'value',
            'embed_entities' => 'value',
            'title' => 'value',
            'created' => 'value',
            'changed' => 'value',
            'uuid' => 'value',
          ],
          'modifiable' => [
            'title' => 'value',
            'preview' => 'value',
            'url' => 'value',
            'apiu_translation' => 'value',
            'metadata' => 'value',
            'embed_entities' => 'value',
          ],
          'required' => [
            'uuid' => 'value',
          ],
        ],
        'api_id' => $this->sync->api . '-' . self::CUSTOM_API_VERSION,
      ];

      $handler->updateEntityTypeDefinition($entity_type);

      foreach ($fields as $key => $field) {
        if (!isset($entity_types[$id . '-' . $key]) || $entity_types[$id . '-' . $key]['handler'] == DrupalContentSync::HANDLER_IGNORE) {
          continue;
        }

        $field_handler = $this->sync->getFieldHandler($entity_type_name, $bundle_name, $key);

        $entity_type['new_properties'][$key] = [
          'type' => 'object',
          'default_value' => NULL,
          'multiple' => TRUE,
        ];

        $field_handler->updateEntityTypeDefinition($entity_type);
      }

      try {
        // Create the entity type.
        $this->sendEntityRequest($url . '/api_unify-api_unify-entity_type-0_1', [
          'json' => $entity_type,
        ]);

        $pool_connection_id = self::getExternalConnectionId($api, self::POOL_SITE_ID, $entity_type_name, $bundle_name, $version);
        // Create the pool connection entity for this entity type.
        $this->sendEntityRequest($url . '/api_unify-api_unify-connection-0_1', [
          'json' => [
            'id' => $pool_connection_id,
            'name' => 'Drupal pool connection for ' . $entity_type_name . '-' . $bundle_name . '-' . $version,
            'hash' => self::getExternalConnectionPath($api, self::POOL_SITE_ID, $entity_type_name, $bundle_name, $version),
            'usage' => 'EXTERNAL',
            'status' => 'READY',
            'entity_type_id' => $entity_type_id,
          ],
        ]);

        // Create a synchronization from the pool to the preview connection.
        $this->sendEntityRequest($url . '/api_unify-api_unify-connection_synchronisation-0_1', [
          'json' => [
            'id' => $pool_connection_id . '--to--preview',
            'name' => 'Synchronization Pool ' . $entity_type_name . '-' . $bundle_name . ' -> Preview',
            'options' => [
              'create_entities' => TRUE,
              'update_entities' => TRUE,
              'delete_entities' => TRUE,
              'clone_entities' => FALSE,
              'update_none_when_loading' => TRUE,
              'exclude_reference_properties' => [
                'pSource',
              ],
            ],
            'status' => 'READY',
            'source_connection_id' => $pool_connection_id,
            'destination_connection_id' => self::PREVIEW_CONNECTION_ID,
          ],
        ]);

        $user = User::load(DRUPAL_CONTENT_SYNC_USER_ID);
        // During the installation from an existing config for some reason DRUPAL_CONTENT_SYNC_USER_ID is not set right after the installation of the module, so we've to double check that...
        // @ToDo: Why?
        if (is_null(DRUPAL_CONTENT_SYNC_USER_ID)) {
          $user = User::load(\Drupal::service('keyvalue.database')->get('drupal_content_sync_user')->get('uid'));
        }

        if (!$user) {
          throw new \Exception(
            t("Drupal Content Sync User not found. Encrypted data can't be saved")
          );
        }

        $userData = \Drupal::service('user.data');
        $data     = $userData->get('drupal_content_sync', $user->id(), 'sync_data');

        if (!$data) {
          throw new \Exception(t("No credentials for sync user found."));
        }

        $encryption_profile = EncryptionProfile::load(DRUPAL_CONTENT_SYNC_PROFILE_NAME);

        foreach ($data as $key => $value) {
          $data[$key] = \Drupal::service('encryption')
            ->decrypt($value, $encryption_profile);
        }

        $crud_operations = [
          'create_item' => [
            'url' => self::getInternalCreateItemUrl($api, $entity_type_name, $bundle_name, $version),
          ],
          'update_item' => [
            'url' => self::getInternalUpdateItemUrl($api, $entity_type_name, $bundle_name, $version),
          ],
          'delete_item' => [
            'url' => self::getInternalDeleteItemUrl($api, $entity_type_name, $bundle_name, $version),
          ],
        ];
        $connection_options = [
          'authentication' => [
            'type' => 'drupal8_services',
            'username' => $data['userName'],
            'password' => $data['userPass'],
            'base_url' => $base_url,
          ],
          'crud' => &$crud_operations,
        ];

        if ($type['export'] == DrupalContentSync::EXPORT_AUTOMATICALLY) {
          $crud_operations['read_list']['url'] = self::getInternalReadListUrl($api, $entity_type_name, $bundle_name, $version);
        }

        $local_connection_id = self::getExternalConnectionId($api, $site_id, $entity_type_name, $bundle_name, $version);
        // Create the instance connection entity for this entity type.
        $this->sendEntityRequest($url . '/api_unify-api_unify-connection-0_1', [
          'json' => [
            'id' => $local_connection_id,
            'name' => 'Drupal connection on ' . $site_id . ' for ' . $entity_type_name . '-' . $bundle_name . '-' . $version,
            'hash' => self::getExternalConnectionPath($api, $site_id, $entity_type_name, $bundle_name, $version),
            'usage' => 'EXTERNAL',
            'status' => 'READY',
            'entity_type_id' => $entity_type_id,
            'instance_id' => $site_id,
            'options' => $connection_options,
          ],
        ]);
        $localConnections[] = $local_connection_id;

        // Create a synchronization from the pool to the local connection.
        $this->sendEntityRequest($url . '/api_unify-api_unify-connection_synchronisation-0_1', [
          'json' => [
            'id' => $local_connection_id . '--to--drupal',
            'name' => 'Synchronization for ' . $entity_type_name . '/' . $bundle_name . '/' . $version . ' from Pool -> ' . $site_id,
            'options' => [
              'dependency_connection_id'  => self::DEPENDENCY_CONNECTION_ID,
              'create_entities' => $type['import'] != DrupalContentSync::IMPORT_DISABLED,
              'update_entities' => $type['import'] != DrupalContentSync::IMPORT_DISABLED && !$type['import_clone'],
              'delete_entities' => $type['import'] != DrupalContentSync::IMPORT_DISABLED && boolval($type['import_deletion_settings']['import_deletion']),
              'clone_entities'  => boolval($type['import_clone']),
              'dependent_entities_only'  => $type['import'] == DrupalContentSync::IMPORT_AS_DEPENDENCY,
              'update_none_when_loading' => TRUE,
              'exclude_reference_properties' => [
                'pSource',
              ],
            ],
            'status' => 'READY',
            'source_connection_id' => $pool_connection_id,
            'destination_connection_id' => $local_connection_id,
          ],
        ]);

        $this->sendEntityRequest($url . '/api_unify-api_unify-connection_synchronisation-0_1', [
          'json' => [
            'id' => $local_connection_id . '--to--pool',
            'name' => 'Synchronization for ' . $entity_type_name . '/' . $bundle_name . '/' . $version . ' from ' . $site_id . ' -> Pool',
            'options' => [
              'dependency_connection_id'  => self::POOL_DEPENDENCY_CONNECTION_ID,
              // As entities will only be sent to API Unify if the sync config
              // allows it, the synchronization entity doesn't need to filter
              // any further
              // 'create_entities' => TRUE,
              // 'update_entities' => TRUE,
              // 'delete_entities' => TRUE,
              // 'clone_entities'  => FALSE,
              // 'dependent_entities_only'  => FALSE,.
              'create_entities' => $type['export'] != DrupalContentSync::EXPORT_DISABLED,
              'update_entities' => $type['export'] != DrupalContentSync::EXPORT_DISABLED,
              'delete_entities' => $type['export'] != DrupalContentSync::EXPORT_DISABLED && boolval($type['export_deletion_settings']['export_deletion']),
              'dependent_entities_only'  => $type['export'] == DrupalContentSync::EXPORT_AS_DEPENDENCY,
              'update_none_when_loading' => TRUE,
              'exclude_reference_properties' => [
                'pSource',
              ],
            ],
            'status' => 'READY',
            'source_connection_id' => $local_connection_id,
            'destination_connection_id' => $pool_connection_id,
          ],
        ]);
      }
      catch (RequestException $e) {
        $messenger = \Drupal::messenger();
        $messenger->addError($e->getMessage());
        return;
      }
    }

    $this->sync->local_connections = $localConnections;
  }

  /**
   * Delete the synchronizations from this connection.
   */
  public function deleteConfig($removedOnly=TRUE) {
    $condition = [
      'operator'  => '==',
      'values'    => [
        [
          'source'  => 'entity',
          'field'   => 'instance_id',
        ],
        [
          'source'  => 'value',
          'value'   => $this->sync->site_id,
        ]
      ],
    ];
    $url      = $this->generateUrl(
      $this->sync->url . '/api_unify-api_unify-connection-0_1',
      [
        'items_per_page'  => '99999',
        'condition' => json_encode($condition),
      ]
    );
    $response = $this->client->{'get'}($url);
    $body     = json_decode($response->getBody(),TRUE);
    $connections = [];
    foreach($body['items'] as $reference) {
      $connections[] = $reference['id'];
    }
    $importConnections = $connections;
    $exportConnections = $connections;

    if( $removedOnly ) {
      $existingExport = [];
      $existingImport = [];
      foreach ($this->sync->getEntityTypeConfig() as $config) {
        $id = self::getExternalConnectionId(
          $this->sync->api,
          $this->sync->site_id,
          $config['entity_type_name'],
          $config['bundle_name'],
          $config['version']
        );
        if($config['export']!=DrupalContentSync::EXPORT_DISABLED) {
          $existingExport[] = $id;
        }
        if($config['import']!=DrupalContentSync::IMPORT_DISABLED) {
          $existingImport[] = $id;
        }
      }
      $importConnections  = array_diff($importConnections,$existingImport);
      $exportConnections  = array_diff($exportConnections,$existingExport);
    }
    $condition = NULL;
    if(count($exportConnections)>0) {
      $condition  = [
        'operator'    => 'in',
        'values'      => [
          [
            'source'    => 'entity',
            'field'     => 'source_connection_id',
          ],
          [
            'source'    => 'value',
            'value'     => $exportConnections,
          ],
        ],
      ];
    }
    if(count($importConnections)>0) {
      $importCondition = [
        'operator'    => 'in',
        'values'      => [
          [
            'source'    => 'entity',
            'field'     => 'destination_connection_id',
          ],
          [
            'source'    => 'value',
            'value'     => $importConnections,
          ],
        ],
      ];
      if( $condition ) {
        $condition = [
          'operator'    => 'or',
          'conditions'  => [
            $condition,
            $importCondition,
          ],
        ];
      }
      else {
        $condition  = $importCondition;
      }
    }

    if(!$condition) {
      return;
    }

    $url = $this->generateUrl(
      $this->sync->url . '/api_unify-api_unify-connection_synchronisation-0_1',
      [
        'condition' => json_encode($condition),
      ]
    );
    $this->client->{'delete'}($url);
  }

  /**
   * Send a request to the API Unify backend.
   * Requests will be passed to $this->>client.
   *
   * @param string $url
   * @param array $arguments
   *
   * @return bool
   */
  protected function sendEntityRequest($url, $arguments) {
    $entityId = $arguments['json']['id'];
    $method   = $this->checkEntityExists($url, $entityId) ? 'patch' : 'post';

    if ('patch' == $method) {
      $url .= '/' . $arguments['json']['id'];
    }

    // $url .= (strpos($url, '?') === FALSE ? '?' : '&') . 'async=yes';.
    try {
      $this->client->{$method}($url, $arguments);
      return TRUE;
    }
    catch (RequestException $e) {
      $messenger = \Drupal::messenger();
      $messenger->addError($e->getMessage());
      return FALSE;
    }
  }

  /**
   * Get a URL string from the given url with additional query parameters.
   *
   * @param $url
   * @param array $parameters
   *
   * @return string
   */
  protected function generateUrl($url, $parameters = []) {
    $resultUrl = Url::fromUri($url, [
      'query' => $parameters,
    ]);

    return $resultUrl->toUriString();
  }

  /**
   * Get all entities for the given URL from the API Unify backend.
   *
   * @param string $baseUrl
   * @param array $parameters
   *
   * @return array
   */
  protected function getEntitiesByUrl($baseUrl, $parameters = []) {
    $result = [];
    $url    = $this->generateUrl($baseUrl, $parameters + ['items_per_page' => 999999]);

    $response = $this->client->get($url);
    $body     = $response->getBody()->getContents();
    $body     = json_decode($body);

    foreach ($body->items as $value) {
      if (!empty($value->id)) {
        $result[] = $value->id;
      }
    }

    return $result;
  }

  /**
   * Check whether or not the given entity already exists.
   *
   * @param string $url
   * @param string $entityId
   *
   * @return bool
   */
  protected function checkEntityExists($url, $entityId) {
    if (empty($this->unifyData[$url])) {
      $this->unifyData[$url] = $this->getEntitiesByUrl($url);
    }

    $entityIndex = array_search($entityId, $this->unifyData[$url]);
    $entityExists = (FALSE !== $entityIndex);

    if ($entityExists) {
      if (array_key_exists($entityId, $this->toBeDeleted)) {
        unset($this->toBeDeleted[$entityId]);
      }
    }

    return $entityExists;
  }

}
