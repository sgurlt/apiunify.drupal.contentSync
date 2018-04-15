<?php

namespace Drupal\drupal_content_sync\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\drupal_content_sync\ApiUnifyRequest;
use Drupal\drupal_content_sync\Exception\SyncException;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\Core\Url;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\user\Entity\User;

/**
 * Defines the DrupalContentSync entity.
 *
 * @ConfigEntityType(
 *   id = "drupal_content_sync",
 *   label = @Translation("DrupalContentSync Synchronization"),
 *   handlers = {
 *     "list_builder" = "Drupal\drupal_content_sync\Controller\DrupalContentSyncListBuilder",
 *     "form" = {
 *       "add" = "Drupal\drupal_content_sync\Form\DrupalContentSyncForm",
 *       "edit" = "Drupal\drupal_content_sync\Form\DrupalContentSyncForm",
 *       "delete" = "Drupal\drupal_content_sync\Form\DrupalContentSyncDeleteForm",
 *     }
 *   },
 *   config_prefix = "sync",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/system/drupal_content_sync/synchronizations/{drupal_content_sync}",
 *     "delete-form" = "/admin/config/system/drupal_content_sync/synchronizations/{drupal_content_sync}/delete",
 *   }
 * )
 */
class DrupalContentSync extends ConfigEntityBase implements DrupalContentSyncInterface {
  // Configuration option only
  // > dependent export still enabled unless ::HANDLER_IGNORE is used.
  const EXPORT_DISABLED         = 'disabled';
  // Both configuration option and export reason.
  const EXPORT_AUTOMATICALLY    = 'automatically';
  const EXPORT_MANUALLY         = 'manually';
  // Export reason only.
  const EXPORT_AS_DEPENDENCY    = 'dependency';
  // Export reason only. Can be used programmatically for custom workflows.
  const EXPORT_FORCED           = 'forced';

  // Configuration option only
  // > dependent import still enabled unless ::HANDLER_IGNORE is used.
  const IMPORT_DISABLED         = 'disabled';
  // Both configuration option and import reason.
  const IMPORT_AUTOMATICALLY    = 'automatically';
  const IMPORT_MANUALLY         = 'manually';
  // Import reason only.
  const IMPORT_AS_DEPENDENCY    = 'dependency';
  // Import reason only. Can be used programmatically for custom workflows.
  const IMPORT_FORCED           = 'forced';

  // Ignore this entity type / bundle / field completely.
  const HANDLER_IGNORE          = 'ignore';

  // The virtual site id for the pool and it's connections / synchronizations.
  const POOL_SITE_ID            = '_pool';

  const PREVIEW_CONNECTION_ID   = 'drupal_drupal-content-sync_preview';
  const PREVIEW_ENTITY_ID       = 'drupal-synchronization-entity_preview-0_1';
  const PREVIEW_ENTITY_VERSION  = '0.1';

  const CUSTOM_API_VERSION      = '1.0';

  const READ_LIST_ENTITY_ID     = '0';

  const ACTION_CREATE           = 'create';
  const ACTION_UPDATE           = 'update';
  const ACTION_DELETE           = 'delete';

  /**
   * The DrupalContentSync ID.
   *
   * @var string
   */
  public $id;

  /**
   * The DrupalContentSync name.
   *
   * @var string
   */
  public $name;

  /**
   * The DrupalContentSync entities.
   *
   * @var array
   */
  public $sync_entities;

  protected $client;

  protected $toBeDeleted = [];
  protected $unifyData   = [];

  protected $dataCleanPrepared = FALSE;

  /**
   * Acts on a saved entity before the insert or update hook is invoked.
   *
   * Used after the entity is saved, but before invoking the insert or update
   * hook. Note that in case of translatable content entities this callback is
   * only fired on their current translation. It is up to the developer to
   * iterate over all translations if needed.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage object.
   * @param bool $update
   *   TRUE if the entity has been updated, or FALSE if it has been inserted.
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    if (!$this->initialize()) {
      drupal_set_message('The communication with the Drupal Content Sync Server failed.' .
        ' Therefore the synchronization entity could not be saved. For more' .
        ' information see the error output above.', 'warning');
      return;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);

    try {
      foreach ($entities as $name => $entity) {
        $entity->client = \Drupal::httpClient();

        $entity->prepareDataCleaning($entity->url);
        $entity->cleanUnifyData();
      }
    }
    catch (RequestException $e) {
      drupal_set_message('The API Unify server is offline or has some problems. Please, check the server', 'error');
      throw new AccessDeniedHttpException();
    }
  }

  /**
   * @ToDo: Add description.
   */
  public static function getEntityTypeVersion($type_name, $bundle_name) {
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $field_definitions = $entityFieldManager->getFieldDefinitions($type_name, $bundle_name);

    $field_definitions_array = (array) $field_definitions;
    unset($field_definitions_array['field_drupal_content_synced']);

    $field_names = array_keys($field_definitions_array);
    sort($field_names);

    $version = md5(json_encode($field_names));

    return $version;
  }

  /**
   * Method do create all Drupal Content Sync entities which are needed for a snychronization.
   *
   * @return bool
   */
  protected function initialize() {
    $url          = $this->{'url'};
    $this->client = \Drupal::httpClient();

    // Check if a connection to Drupal Content Sync can be established.
    try {
      // Create "drupal" API entity.
      $this->sendEntityRequest($url . '/api_unify-api_unify-api-0_1', [
        'json' => [
          'id' => 'drupal-'.self::CUSTOM_API_VERSION,
          'name' => 'drupal',
          'version' => self::CUSTOM_API_VERSION,
        ],
      ]);
      // Create the child entity.
      $this->sendEntityRequest($url . '/api_unify-api_unify-api-0_1', [
        'json' => [
          'id' => $this->{'api'} . '-'.self::CUSTOM_API_VERSION,
          'name' => $this->{'api'},
          'version' => self::CUSTOM_API_VERSION,
          'parent_id' => 'drupal-'.self::CUSTOM_API_VERSION,
        ],
      ]);

      // Create the instance entity.
      $this->sendEntityRequest($url . '/api_unify-api_unify-instance-0_1', [
        'json' => [
          'id' => $this->{'site_id'},
          'api_id' => $this->{'api'} . '-'.self::CUSTOM_API_VERSION,
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
      drupal_set_message($e->getMessage(), 'warning');
      return FALSE;
    }
    catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'warning');
      return FALSE;
    }
    return TRUE;
  }

  /**
   * @ToDo: Add description.
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
   * @ToDo: Add description.
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
   * @ToDo: Add description.
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
   * @ToDo: Add description.
   */
  public function getExternalUrl($entity_type_name, $bundle_name, $entity_uuid = NULL) {
    $url = $this->{'url'} . '/' . self::getExternalConnectionPath(
      $this->{'api'},
      $this->{'site_id'},
      $entity_type_name,
      $bundle_name,
      $this->sync_entities[$entity_type_name . '-' . $bundle_name]['version']
    );
    if ($entity_uuid) {
      $url .= '/' . $entity_uuid;
    }
    return $url;
  }

  /**
   * @ToDo: Add description.
   */
  public static function getInternalUrl($api_id, $entity_type_name, $bundle_name, $version, $entity_uuid = NULL) {
    global $base_url;

    $url = sprintf('%s/drupal_content_sync_entity_resource/%s/%s/%s/%s',
      $base_url,
      $api_id,
      $entity_type_name,
      $bundle_name,
      $version
    );
    if ($entity_uuid) {
      $url .= '/' . $entity_uuid;
    }
    $url .= '?_format=json';
    return $url;
  }

  /**
   * @ToDo: Add description.
   */
  public static function getInternalCreateItemUrl($api_id, $entity_type_name, $bundle_name, $version) {
    return self::getInternalUrl($api_id, $entity_type_name, $bundle_name, $version) . '&is_clone=[is_clone]';
  }

  /**
   * @ToDo: Add description.
   */
  public static function getInternalUpdateItemUrl($api_id, $entity_type_name, $bundle_name, $version) {
    return self::getInternalUrl($api_id, $entity_type_name, $bundle_name, $version, '[id]');
  }

  /**
   * @ToDo: Add description.
   */
  public static function getInternalDeleteItemUrl($api_id, $entity_type_name, $bundle_name, $version) {
    return self::getInternalUrl($api_id, $entity_type_name, $bundle_name, $version, '[id]');
  }

  /**
   * @ToDo: Add description.
   */
  public static function getInternalReadListUrl($api_id, $entity_type_name, $bundle_name, $version) {
    return self::getInternalUrl($api_id, $entity_type_name, $bundle_name, $version, self::READ_LIST_ENTITY_ID);
  }

  public static function getExportSynchronizationForEntity($entity,$reason) {
    $drupal_content_syncs = self::getAll();

    foreach ($drupal_content_syncs as $sync) {
      if($sync->exportsEntity($entity,$reason)) {
        return $sync;
      }
    }

    return NULL;
  }

  public function exportsEntity($entity,$reason) {
    // @TODO For menu items, use $menu_item->getBaseId()

    $config = $this->getEntityTypeConfig( $entity->getEntityTypeId(), $entity->bundle() );
    if(empty($config) || $config['handler']==self::HANDLER_IGNORE) {
      return FALSE;
    }

    // If any handler is available, we can export this entity
    if($reason==self::EXPORT_AS_DEPENDENCY || $reason==self::EXPORT_FORCED) {
      return TRUE;
    }

    return $config['export']==$reason;
  }

  public static $all = NULL;
  /**
   * Load all drupal_content_sync entities and add overrides from global $config.
   *
   * @return \Drupal\drupal_content_sync\Entity\DrupalContentSync[]
   */
  public static function getAll() {
    if(self::$all!==NULL){
      return self::$all;
    }

    $configurations = \Drupal::entityTypeManager()
      ->getStorage('drupal_content_sync')
      ->loadMultiple();

    foreach ($configurations as $id => &$configuration) {
      global $config;
      $config_name = 'drupal_content_sync.drupal_content_sync.' . $id;
      if (!isset($config[$config_name]) || empty($config[$config_name])) {
        continue;
      }
      foreach ($config[$config_name] as $key => $new_value) {
        $configuration->set($key, $new_value);
      }
      $configuration->getEntityTypeConfig();
    }

    return self::$all=$configurations;
  }

  public static function getImportSynchronizationsForEntityType($entity_type_name,$bundle_name,$reason,$is_clone=FALSE) {
    $drupal_content_syncs = self::getAll();

    $result = [];

    foreach ($drupal_content_syncs as $sync) {
      if($sync->importsEntity($entity_type_name,$bundle_name,$reason,$is_clone)) {
        $result[] = $sync;
      }
    }

    return $result;
  }

  public static function getImportSynchronizationForApiAndEntityType($api,$entity_type_name,$bundle_name,$reason,$is_clone=FALSE) {
    $drupal_content_syncs = self::getAll();

    foreach ($drupal_content_syncs as $sync) {
      if( $api && $sync->api!=$api ) {
        continue;
      }
      if($sync->importsEntity($entity_type_name,$bundle_name,$reason,$is_clone)) {
        return $sync;
      }
    }

    return NULL;
  }

  public function importsEntity($entity_type_name,$bundle_name,$reason,$is_clone=FALSE) {
    $config = $this->getEntityTypeConfig($entity_type_name, $bundle_name );
    if(empty($config) || $config['handler']==self::HANDLER_IGNORE) {
      return FALSE;
    }
    // If any handler is available, we can import this entity
    if($reason==self::IMPORT_AS_DEPENDENCY || $reason==self::IMPORT_FORCED) {
      return TRUE;
    }
    return $config[($is_clone?'cloned':'sync').'_import']==$reason;
  }

  public function supportsEntity($entity) {
    return $this->getEntityTypeConfig($entity->getEntityTypeId(), $entity->bundle() )['handler']!=self::HANDLER_IGNORE;
  }

  /**
   * @param string $entity_type_name
   * @param string $entity_bundle
   * @param array $data
   * @param bool $is_clone
   * @param string $reason
   * @param string $action
   *
   * @throws \Drupal\drupal_content_sync\Exception\SyncException
   *
   * @return bool
   */
  public function importEntity($entity_type_name, $entity_bundle, $data, $is_clone, $reason, $action=self::ACTION_CREATE) {
    // @TODO Save state in custom entity for each entity
    //$meta = ***load_meta_info***($entity_type_name,$data['uuid']);
    //if($meta && $action==self::ACTION_CREATE) {
    //  $action = self::ACTION_UPDATE;
    //}

    // @TODO If the entity was deleted, we ignore it
    //if( $meta && $meta->isDeleted() && $reason!=self::IMPORT_FORCED ) {
    //  return TRUE;
    //}}

    $request  = new ApiUnifyRequest($this,$entity_type_name,$entity_bundle,$data);

    $config   = $this->getEntityTypeConfig($entity_type_name,$entity_bundle);
    $handler  = $this->getEntityTypeHandler($config);

   $result  = $handler->import($request, $is_clone, $reason, $action);

    // Don't save meta entity if entity wasn't imported anyway
    if( !$result ) {
      return FALSE;
    }

    // @TODO Save meta information
    //if( $result ) {
    //  if( $meta ) {
    //    $meta->setLastImport(time());
    //    $meta->save();
    //  }
    //  else {
    //    ***create_meta*** [
    //      'source_url' => $request->getField('url')
    //    ]
    //  }
    //}

    return $result;
  }

  /**
   * @param array &$result
   * @param EntityInterface $entity
   * @param string $reason
   * @param string $action
   *
   * @throws \Drupal\drupal_content_sync\Exception\SyncException
   *
   * @return bool Whether or not the export could be gotten.
   */
  public function getSerializedEntity(&$result,$entity,$reason,$action=self::ACTION_UPDATE) {
    $entity_type    = $entity->getEntityTypeId();
    $entity_bundle  = $entity->bundle();
    $entity_uuid    = $entity->uuid();

    $config   = $this->getEntityTypeConfig($entity_type,$entity_bundle);
    $handler  = $this->getEntityTypeHandler($config);

    $request  = new ApiUnifyRequest($this,$entity_type,$entity_bundle);
    $request->setUuid($entity_uuid);

    $status = $handler->export($request,$entity,$reason,$action);
    if( !$status ) {
      return FALSE;
    }

    $result = $request->getData();

    return TRUE;
  }

  /**
   * @param EntityInterface $entity
   * @param string $reason
   * @param string $action
   *
   * @throws \Drupal\drupal_content_sync\Exception\SyncException
   *
   * @return bool Whether or not the entity has actually been exported.
   */
  public function exportEntity($entity,$reason,$action=self::ACTION_UPDATE) {
    if (method_exists($entity, 'getUntranslated')) {
      $entity = $entity->getUntranslated();
    }

    // @TODO: Save state in custom entity for each entity
    //$meta = ***load_meta_info***($entity);
    //if(!$meta && $action==self::ACTION_UPDATE) {
    //  $action = self::ACTION_CREATE;
    //}

    // @TODO If the entity didn't change, it doesn't have to be re-exported
    //if( $meta && $meta->getLastExport()>$entity->changed() && $reason!=self::EXPORT_FORCED ) {
    //  return TRUE;
    //}}

    $entity_type    = $entity->getEntityTypeId();
    $entity_bundle  = $entity->bundle();
    $entity_uuid    = $entity->uuid();

    /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository */
    $entity_repository = \Drupal::service('entity.repository');

    if ($action!=self::ACTION_DELETE) {
      $body   = NULL;
      $status = $this->getSerializedEntity($body,$entity,$reason,$action);

      // Handler chose to deliberately ignore this entity,
      // e.g. a node that wasn't published yet and is not exported unpublished
      if( !$status ) {
        return FALSE;
      }

      if (!empty($body['embed_entities'])) {
        foreach ($body['embed_entities'] as $data) {
          try {
            $embed_entity = $entity_repository->loadEntityByUuid($data['entity_type'], $data['uuid']);
          }
          catch(\Exception $e) {
            throw new SyncException(SyncException::CODE_UNEXPECTED_EXCEPTION,$e);
          }

          if( !$this->supportsEntity($embed_entity) ) {
            continue;
          }

          $this->exportEntity($embed_entity,$reason,$action==self::ACTION_UPDATE);
        }
      }
    }

    $url = $this->getExternalUrl($entity_type,$entity_bundle,$action==self::ACTION_CREATE?NULL:$entity_uuid);

    $headers  = [
      'Content-Type' => 'application/json',
    ];

    $methods = [
      self::ACTION_CREATE => 'post',
      self::ACTION_UPDATE => 'put',
      self::ACTION_DELETE => 'delete',
    ];

    try {
      $client = \Drupal::httpClient();
      $response = $client->request(
        $methods[$action],
        $url,
        array_merge(['headers' => $headers],$body?['body' => json_encode($body)]:[])
      );
    }
    catch (\Exception $e) {
      \Drupal::logger('drupal_content_sync')->error(
        'Failed to export entity @entity_type-@entity_bundle @entity_uuid to @url' . PHP_EOL . '@message',
        [
          '@entity_type' => $entity_type,
          '@entity_bundle' => $entity_bundle,
          '@entity_uuid' => $entity_uuid,
          '@message' => $e->getMessage(),
          '@url' => $url,
        ]
      );
      throw new SyncException(SyncException::CODE_EXPORT_REQUEST_FAILED, $e);
    }

    if( $response->getStatusCode()!=200 && $response->getStatusCode()!=201 ) {
      \Drupal::logger('drupal_content_sync')->error(
        'Failed to export entity @entity_type-@entity_bundle @entity_uuid to @url' . PHP_EOL . 'Got status code @status_code @reason_phrase with body:'.PHP_EOL.'@body',
        [
          '@entity_type' => $entity_type,
          '@entity_bundle' => $entity_bundle,
          '@entity_uuid' => $entity_uuid,
          '@status_code' => $response->getStatusCode(),
          '@reason_phrase' => $response->getReasonPhrase(),
          '@message' => $response->getBody().'',
          '@url' => $url,
        ]
      );
      throw new SyncException(SyncException::CODE_EXPORT_REQUEST_FAILED);
    }

    // @TODO Uncomment when ready
    //if( $meta ) {
    //  $meta->setLastExport(time());
    //}
    //else {
    //  $meta = ***create_meta_info***([
    //    'entity_type' => $entity_type,
    //    'uuid' => $entity_uuid,
    //    'last_export' => time(),
    //  ]);
    //}

    return TRUE;
  }

  public function getEntityTypeConfig($entity_type=NULL,$entity_bundle=NULL) {
    $entity_types = $this->sync_entities;

    $result = [];

    foreach ($entity_types as $id => &$type) {
      // Ignore field definitions.
      if (substr_count($id, '-') != 1) {
        continue;
      }

      preg_match('/^(.+)-(.+)$/', $id, $matches);

      $entity_type_name = $matches[1];
      $bundle_name      = $matches[2];

      if( $entity_type && $entity_type_name!=$entity_type ) {
        continue;
      }
      if( $entity_bundle && $bundle_name!=$entity_bundle ) {
        continue;
      }

      // If this is called before being saved, we want to have version etc.
      // available still
      if( empty($type['version']) ) {
        $type['version']          = DrupalContentSync::getEntityTypeVersion($entity_type_name, $bundle_name);
        $type['entity_type_name'] = $entity_type_name;
        $type['bundle_name']      = $bundle_name;
      }

      if( $entity_type && $entity_bundle ) {
        return $type;
      }

      $result[$id] = $type;
    }

    return $result;
  }

  /**
   * @param $config
   *
   * @return \Drupal\drupal_content_sync\Plugin\EntityHandlerInterface
   */
  public function getEntityTypeHandler($config) {
    $entityPluginManager = \Drupal::service('plugin.manager.dcs_entity_handler');

    $handler = $entityPluginManager->createInstance(
      $config['handler'],
      [
        'entity_type_name' => $config['entity_type_name'],
        'bundle_name' => $config['bundle_name'],
        'settings' => $config,
        'sync' => $this,
      ]
    );

    return $handler;
  }

  /**
   * @param $entity_type_name
   * @param $bundle_name
   * @param $field_name
   *
   * @return \Drupal\drupal_content_sync\Plugin\FieldHandlerInterface
   */
  public function getFieldHandler($entity_type_name,$bundle_name,$field_name) {
    $fieldPluginManager = \Drupal::service('plugin.manager.dcs_field_handler');

    $key = $entity_type_name . '-' . $bundle_name . '-' . $field_name;
    if( empty($this->sync_entities[$key]) ) {
      return NULL;
    }

    if( $this->sync_entities[$key]['handler']==self::HANDLER_IGNORE ) {
      return NULL;
    }

    $entityFieldManager = \Drupal::service('entity_field.manager');
    $field_definition = $entityFieldManager->getFieldDefinitions($entity_type_name, $bundle_name)[$field_name];

    $handler = $fieldPluginManager->createInstance(
      $this->sync_entities[$key]['handler'],
      [
        'entity_type_name' => $entity_type_name,
        'bundle_name' => $bundle_name,
        'field_name' => $field_name,
        'field_definition' => $field_definition,
        'settings' => $this->sync_entities[$key],
        'sync' => $this,
      ]
    );

    return $handler;
  }

  /**
   * @ToDo: Add description.
   */
  protected function createEntityTypes() {
    global $base_url;

    $url              = $this->{'url'};
    $api              = $this->{'api'};
    $site_id          = $this->{'site_id'};
    $localConnections = [];

    $entity_types = $this->sync_entities;

    foreach ($this->getEntityTypeConfig() as $id=>$type) {
      $entity_type_name = $type['entity_type_name'];
      $bundle_name      = $type['bundle_name'];
      $version          = $type['version'];

      if ($type['handler'] != self::HANDLER_IGNORE) {
        $handler = $this->getEntityTypeHandler($type);

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
          'api_id' => $this->{'api'} . '-'.self::CUSTOM_API_VERSION,
        ];

        $handler->updateEntityTypeDefinition($entity_type);

        foreach ($fields as $key => $field) {
          if (!isset($entity_types[$id . '-' . $key]) || $entity_types[$id . '-' . $key]['handler'] == self::HANDLER_IGNORE) {
            continue;
          }

          $field_handler = $this->getFieldHandler($entity_type_name,$bundle_name,$key);

          $entity_type['new_properties'][$key] = [
            'type' => 'object',
            'default_value' => NULL,
            'multiple' => TRUE,
          ];

          $field_handler->updateEntityTypeDefinition($entity_type);
        }

        try {
          $this->prepareDataCleaning($url);

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

          if ($type['export'] == self::EXPORT_AUTOMATICALLY) {
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
                'create_entities' => $type['sync_import'] == 'automatically' || $type['cloned_import'] == 'automatically',
                'update_entities' => TRUE,
                'delete_entities' => boolval($type['delete_entity']),
                'clone_entities' => $type['cloned_import'] == 'automatically',
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

          if ($type['export'] != self::EXPORT_DISABLED) {
            $this->sendEntityRequest($url . '/api_unify-api_unify-connection_synchronisation-0_1', [
              'json' => [
                'id' => $local_connection_id . '--to--pool',
                'name' => 'Synchronization for ' . $entity_type_name . '/' . $bundle_name . '/' . $version . ' from ' . $site_id . ' -> Pool',
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
                'source_connection_id' => $local_connection_id,
                'destination_connection_id' => $pool_connection_id,
              ],
            ]);
          }

          break;

        }
        catch (RequestException $e) {
          drupal_set_message($e->getMessage(), 'error');
          return;
        }
      }
    }
    $this->cleanUnifyData();
    $this->{'local_connections'} = json_encode($localConnections);
  }

  /**
   * @param $url
   * @param $arguments
   *
   * @return bool
   */
  protected function sendEntityRequest($url, $arguments) {
    $result = FALSE;

    if (!empty($arguments['json']['id'])) {
      $entityId = $arguments['json']['id'];
      $method   = $this->checkEntityExists($url, $entityId) ? 'patch' : 'post';

      if ('patch' == $method) {
        $url .= '/' . $arguments['json']['id'];
      }

      $url .= (strpos($url, '?') === FALSE ? '?' : '&') . 'async=yes';

      try {
        $this->client->{$method}($url, $arguments);
        $result = TRUE;
      }
      catch (RequestException $e) {
        drupal_set_message($e->getMessage(), 'error');
      }
    }
    else {
      drupal_set_message("Entity doesn't have id. Please check.");
    }

    return $result;
  }

  /**
   * @ToDo: Add description.
   */
  protected function generateUrl($url, $parameters = []) {
    $resultUrl = Url::fromUri($url, [
      'query' => $parameters,
    ]);

    return $resultUrl->toUriString();
  }

  /**
   * @ToDo: Add description.
   */
  protected function getEntitiesByUrl($baseUrl, $parameters = []) {
    $result = [];
    $url    = $this->generateUrl($baseUrl, $parameters + ['items_per_page' => 999999]);

    $responce = $this->client->get($url);
    $body     = $responce->getBody()->getContents();
    $body     = json_decode($body);

    foreach ($body->items as $value) {
      if (!empty($value->id)) {
        $result[] = $value->id;
      }
    }

    return $result;
  }

  /**
   * @param $entityId
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

  /**
   * @ToDo: Add description.
   */
  protected function getRelatedEntities($url, $fieldName, $value) {
    $query = '{"operator":"==","values":[{"source":"data","field":"' . $fieldName . '"},{"source":"value","value":"' . $value . '"}]}';

    return $this->getEntitiesByUrl($url, ['condition' => $query]);
  }

  /**
   * @ToDo: Add description.
   */
  protected function prepareDataCleaning($url) {
    if (!$this->dataCleanPrepared) {
      $result = [];
      $parentLevel = TRUE;
      $requestUrls = [
        [
          'url'   => 'api_unify-api_unify-connection-0_1',
          'field' => 'instance_id',
          'value' => $this->{'site_id'},
        ],
        [
          'url'   => 'api_unify-api_unify-connection_synchronisation-0_1',
          'field' => 'source_connection_id',
          'value' => NULL,
        ],
        [
          'url'   => 'api_unify-api_unify-connection_synchronisation-0_1',
          'field' => 'destination_connection_id',
          'value' => NULL,
        ],
      ];

      foreach ($requestUrls as $requestUrl => $data) {
        $requestUrl = $url . '/' . $data['url'];

        if ($parentLevel) {
          $parentItems = $this->getRelatedEntities($requestUrl, $data['field'], $data['value']);
          $result = array_fill_keys($parentItems, $requestUrl);
          $parentLevel = !$parentLevel;

          continue;
        }
        else {
          foreach ($parentItems as $id) {
            $childs = $this->getRelatedEntities($requestUrl, $data['field'], $id);
            $childs = array_fill_keys($childs, $requestUrl);
            $result = array_merge($result, $childs);
          }
        }
      }

      $this->toBeDeleted       = $result;
      $this->dataCleanPrepared = TRUE;
    }

    return $this->toBeDeleted;
  }

  /**
   * @ToDo: Add description.
   */
  protected function cleanUnifyData() {
    try {
      foreach ($this->toBeDeleted as $id => $url) {
        // $responce = $this->client->delete($url . '/' . $id);.
      }
    }
    catch (RequestException $e) {
      drupal_set_message($e->getMessage(), 'error');
      return FALSE;
    }

    return TRUE;
  }

}
