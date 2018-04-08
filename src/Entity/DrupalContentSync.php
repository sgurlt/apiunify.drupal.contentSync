<?php

namespace Drupal\drupal_content_sync\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\Core\Url;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
  const EXPORT_DISABLED = 'disabled';
  // Both configuration option and export reason.
  const EXPORT_AUTOMATICALLY = 'automatically';
  const EXPORT_MANUALLY      = 'manually';
  // Export reason only.
  const EXPORT_AS_DEPENDENCY = 'dependency';

  // Configuration option only
  // > dependent import still enabled unless ::HANDLER_IGNORE is used.
  const IMPORT_DISABLED = 'disabled';
  // Both configuration option and import reason.
  const IMPORT_AUTOMATICALLY = 'automatically';
  const IMPORT_MANUALLY      = 'manually';
  // Import reason only.
  const IMPORT_AS_DEPENDENCY = 'dependency';

  // Ignore this entity type / bundle / field completely.
  const HANDLER_IGNORE = 'ignore';

  // The virtual site id for the pool and it's connections / synchronizations.
  const POOL_SITE_ID = '_pool';

  const PREVIEW_CONNECTION_ID  = 'drupal_drupal-content-sync_preview';
  const PREVIEW_ENTITY_ID      = 'drupal-synchronization-entity_preview-0_1';
  const PREVIEW_ENTITY_VERSION = '0.1';

  const READ_LIST_ENTITY_ID = '0';

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
  protected $entityFieldManager;

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

    $this->entityFieldManager = \Drupal::service('entity_field.manager');

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
      $this->client->get($url . '/status');

      // Create "drupal" API entity.
      $this->sendEntityRequest($url . '/api_unify-api_unify-api-0_1', [
        'json' => [
          'id' => 'drupal-1.0',
          'name' => 'drupal',
          'version' => '1.0',
        ],
      ]);
      // Create the child entity.
      $this->sendEntityRequest($url . '/api_unify-api_unify-api-0_1', [
        'json' => [
          'id' => $this->{'api'} . '-1.0',
          'name' => $this->{'api'},
          'version' => '1.0',
          'parent_id' => 'drupal-1.0',
        ],
      ]);

      // Create the instance entity.
      $this->sendEntityRequest($url . '/api_unify-api_unify-instance-0_1', [
        'json' => [
          'id' => $this->{'site_id'},
          'api_id' => $this->{'api'} . '-1.0',
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
  public static function getInternalUrl($entity_type_name, $bundle_name, $version, $entity_uuid = NULL) {
    global $base_url;

    $url = sprintf('%s/drupal_content_sync_entity_resource/%s/',
      $base_url,
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
  public static function getInternalCreateItemUrl($entity_type_name, $bundle_name, $version) {
    return self::getInternalUrl($entity_type_name, $bundle_name, $version) . '&is_clone=[is_clone]';
  }

  /**
   * @ToDo: Add description.
   */
  public static function getInternalUpdateItemUrl($entity_type_name, $bundle_name, $version) {
    return self::getInternalUrl($entity_type_name, $bundle_name, $version, '[id]');
  }

  /**
   * @ToDo: Add description.
   */
  public static function getInternalDeleteItemUrl($entity_type_name, $bundle_name, $version) {
    return self::getInternalUrl($entity_type_name, $bundle_name, $version, '[id]');
  }

  /**
   * @ToDo: Add description.
   */
  public static function getInternalReadListUrl($entity_type_name, $bundle_name, $version) {
    return self::getInternalUrl($entity_type_name, $bundle_name, $version, self::READ_LIST_ENTITY_ID);
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

    $entityPluginManager = \Drupal::service('plugin.manager.dcs_entity_handler');
    $fieldPluginManager = \Drupal::service('plugin.manager.dcs_field_handler');

    $entity_types = $this->sync_entities;

    foreach ($entity_types as $id => $type) {
      // Ignore field definitions.
      if (substr_count($id, '-') != 1) {
        continue;
      }

      preg_match('/^(.+)-(.+)$/', $id, $matches);

      $entity_type_name = $matches[1];
      $bundle_name = $matches[2];
      $version = self::getEntityTypeVersion($entity_type_name, $bundle_name);

      if ($type['handler'] != self::HANDLER_IGNORE) {
        $handler = $entityPluginManager->createInstance(
          $type['handler'],
          [
            'entity_type_name' => $entity_type_name,
            'bundle_name' => $bundle_name,
            'settings' => $type,
          ]
        );

        /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $fields */
        $fields = $this->entityFieldManager->getFieldDefinitions($entity_type_name, $bundle_name);

        $entity_type_id = self::getExternalEntityTypeId($api, $entity_type_name, $bundle_name, $version);
        $entity_type = [
          'id' => $entity_type_id,
          'name_space' => $entity_type_name,
          'name' => $bundle_name,
          'version' => $version,
          'base_class' => "api-unify/services/drupal/v0.1/models/base.model",
          'custom' => TRUE,
          'new_properties' => [
            'id' => [
              'type' => 'string',
            ],
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
          'api_id' => $this->{'api'} . '-1.0',
        ];

        $handler->updateEntityTypeDefinition($entity_type);

        foreach ($fields as $key => $field) {
          if (!isset($entity_types[$id . '-' . $key]) || $entity_types[$id . '-' . $key]['handler'] == self::HANDLER_IGNORE) {
            continue;
          }

          $field_handler = $fieldPluginManager->createInstance(
            $entity_types[$id . '-' . $key]['handler'],
            [
              'entity_type_name' => $entity_type_name,
              'bundle_name' => $bundle_name,
              'field_name' => $key,
              'field_definition' => $field,
              'settings' => $entity_types[$id . '-' . $key],
            ]
          );

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

          $user = user_load_by_mail(DRUPAL_CONTENT_SYNC_EMAIL);

          if (!$user) {
            throw new \Exception(
              t("No user found with email: @email. Encrypted data can't be saved",
                ['@email' => DRUPAL_CONTENT_SYNC_EMAIL])
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
              'url' => self::getInternalCreateItemUrl($entity_type_name, $bundle_name, $version),
            ],
            'update_item' => [
              'url' => self::getInternalUpdateItemUrl($entity_type_name, $bundle_name, $version),
            ],
            'delete_item' => [
              'url' => self::getInternalDeleteItemUrl($entity_type_name, $bundle_name, $version),
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
            $crud_operations['read_list']['url'] = self::getInternalReadListUrl($entity_type_name, $bundle_name, $version);
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
