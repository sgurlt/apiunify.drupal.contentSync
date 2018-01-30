<?php

namespace Drupal\drupal_content_sync\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use GuzzleHttp\Exception\RequestException;

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

  const EXPORT_DISABLED = 0;
  const EXPORT_AUTOMATICALLY = 1;
  const EXPORT_MANUALLY = 2;

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
   * @var string
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
        ' information see the error output above.', 'error');
      return;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);

    foreach ($entities as $name => $entity) {
      $entity->client = \Drupal::httpClient();

      $entity->prepareDataCleaning($entity->url);
      $entity->cleanUnifyData();
    }
  }

  /**
   * Method do create all Drupal Content Sync entities which are needed for a snychronization
   *
   * @return bool
   */
  protected function initialize() {
    $url          = $this->{'url'};
    $this->client = \Drupal::httpClient();

    //Check if a connection to Drupal Content Sync can be established
    try {
      $this->client->get($url . '/status');

      // Create "drupal" API entity.
      $this->sendEntityRequest($url . '/api_unify-api_unify-api-0_1', [
        'json' => [
          'id' => 'drupal-0.1',
          'name' => 'drupal',
          'version' => '0.1',
        ],
      ]);
      // Create the child entity.
      $this->sendEntityRequest($url . '/api_unify-api_unify-api-0_1', [
        'json' => [
          'id' => $this->{'api'} . '-0.1',
          'name' => $this->{'api'},
          'version' => '0.1',
          'parent_id' => 'drupal-0.1',
        ],
      ]);

      //Create the instance entity
      $this->sendEntityRequest($url . '/api_unify-api_unify-instance-0_1', [
        'json' => [
          'id' => $this->{'site_id'},
          'api_id' => $this->{'api'} . '-0.1',
        ],
      ]);

      //Create the preview connection entity
      $this->sendEntityRequest($url . '/api_unify-api_unify-connection-0_1', [
        'json' => [
          'id' => 'drupal_drupal-content-sync_preview',
          'name' => 'Drupal preview connection',
          'hash' => 'drupal/drupal-content-sync/preview',
          'usage' => 'EXTERNAL',
          'status' => 'READY',
          'entity_type_id' => 'drupal-synchronization-entity_preview-0_1',
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
      drupal_set_message($e->getMessage(), 'error');
      return FALSE;
    }
    return TRUE;
  }

  protected function createEntityTypes() {
    global $base_url;
    $url = $this->{'url'};
    $localConnections = [];

    $sync_entities = $this->sync_entities;
    $entity_types  = json_decode($sync_entities);

    foreach ($entity_types as $type) {
      $type = (array) $type;

      if ($type['export'] != self::EXPORT_DISABLED || $type['preview'] != 'excluded') {
        /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $fields */
        $fields = $this->entityFieldManager->getFieldDefinitions($type['entity_type'], $type['entity_bundle']);

        $fields_to_ignore = [
          'uuid',
          'id',
          'nid',
          'vid',
          'type',
          'path',
          'revision_log',
          'revision_translation_affected',
          'menu_link',
          'field_drupal_content_synced',
          'field_media_id',
          'field_media_connection_id',
          'field_media',
          'field_term_ref_id',
          'field_term_ref_connection_id',
          'field_term_ref',
          'field_drupal_content_synced',
          'created',
          'changed',
          'title',
        ];

        $entity_type = [
          'id' => 'drupal-' . $type['entity_type'] . '-' . $type['entity_bundle'] . '-' . $type['version_hash'],
          'name_space' => $type['entity_type'],
          'name' => $type['entity_bundle'],
          'version' => $type['version_hash'],
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
          'api_id' => $this->{'api'} . '-0.1',
        ];

        if ($type['entity_type'] == 'file') {
          $entity_type['new_properties']['apiu_file_content'] = [
            'type' => 'string',
            'default_value' => NULL,
          ];
          $entity_type['new_property_lists']['details']['apiu_file_content'] = 'value';
          $entity_type['new_property_lists']['filesystem']['apiu_file_content'] = 'value';
          $entity_type['new_property_lists']['required']['apiu_file_content'] = 'value';
        }

        foreach ($fields as $key => $field) {
          if (in_array($key, $fields_to_ignore)) {
            continue;
          }

          $entity_type['new_properties'][$key] = [
            'type' => 'object',
            'default_value' => NULL,
            'multiple' => TRUE,
          ];

          $entity_type['new_property_lists']['details'][$key] = 'value';
          $entity_type['new_property_lists']['database'][$key] = 'value';

          if ($field->isRequired()) {
            $entity_type['new_property_lists']['required'][$key] = 'value';
          }

          if (!$field->isReadOnly()) {
            $entity_type['new_property_lists']['modifiable'][$key] = 'value';
          }

          switch ($field->getType()) {
            case 'file':
            case 'image':
              $entity_type['new_property_lists']['filesystem'][$key] = 'value';
              break;
          }
        }

        try {
          $this->prepareDataCleaning($url);

          //Create the entity type
          $this->sendEntityRequest($url . '/api_unify-api_unify-entity_type-0_1', [
            'json' => $entity_type,
          ]);

          //Create the pool connection entity for this entity type
          $this->sendEntityRequest($url . '/api_unify-api_unify-connection-0_1', [
            'json' => [
              'id' => 'drupal_pool_' . $entity_type['name'],
              'name' => 'Drupal pool connection for ' . $entity_type['name'],
              'hash' => 'drupal/drupal-content-sync-pool/' . $entity_type['name'],
              'usage' => 'EXTERNAL',
              'status' => 'READY',
              'entity_type_id' => $entity_type['id'],
              'options' => [
                'crud' => [
                  'read_list' => []
                ],
                'static_values' => [],
              ],
            ],
          ]);

          //Create a synchronization from the pool to the preview connection
          $this->sendEntityRequest($url . '/api_unify-api_unify-connection_synchronisation-0_1', [
            'json' => [
              'id' => 'drupal_pool_' . $entity_type['id'] . '_synchronization_to_preview',
              'name' => 'Synchronization Pool ' . $entity_type['name'] . ' -> Preview',
              'options' => [
                'create_entities' => true,
                'update_entities' => true,
                'delete_entities' => true,
                'clone_entities' => false,
                'exclude_reference_properties' => [
                  'pSource'
                ]
              ],
              'status' => 'READY',
              'source_connection_id' => 'drupal_pool_' . $entity_type['name'],
              'destination_connection_id' => 'drupal_drupal-content-sync_preview',
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

          $read_list = [];
          if ($type['export'] == self::EXPORT_AUTOMATICALLY) {
            $read_list['url'] = $base_url . '/drupal_content_sync_entity_resource/' . $entity_type['name_space'] . '/' . $entity_type['name'] . '/0?_format=json';
          }
          else {
            $read_list['url'] = $base_url . '/drupal-content-sync/publish-changes/entities?_format=json';
          }

          //Create the instance connection entity for this entity type
          $this->sendEntityRequest($url . '/api_unify-api_unify-connection-0_1', [
            'json' => [
              'id' => 'drupal_' . $this->{'site_id'} . '_' . $entity_type['name'],
              'name' => 'Drupal connection on ' . $this->{'site_id'} . ' for ' . $entity_type['name'],
              'hash' => 'drupal/' . $this->{'site_id'} . '/' . $entity_type['name_space'] . '/' . $entity_type['name'],
              'usage' => 'EXTERNAL',
              'status' => 'READY',
              'entity_type_id' => $entity_type['id'],
              'instance_id' => $this->{'site_id'},
              'options' => [
                'pull_interval' => 86400000,
                'authentication' => [
                  'type' => 'drupal8_services',
                  'username' => $data['userName'],
                  'password' => $data['userPass'],
                  'base_url' => $base_url,
                ],
                'crud' => [
                  'read_list' => $read_list,
                  'create_item' => [
                    'url' => $base_url . '/drupal_content_sync_entity_resource/' . $entity_type['name_space'] . '/' . $entity_type['name'] . '?is_clone=[is_clone]&_format=json',
                  ],
                  'update_item' => [
                    'url' => $base_url . '/drupal_content_sync_entity_resource/' . $entity_type['name_space'] . '/' . $entity_type['name'] . '/[id]?_format=json',
                  ],
                  'delete_item' => [
                    'url' => $base_url . '/drupal_content_sync_entity_resource/' . $entity_type['name_space'] . '/' . $entity_type['name'] . '/[id]?_format=json',
                  ],
                ],
                'static_values' => [],
              ],
            ],
          ]);
          $localConnections[] = 'drupal_' . $this->{'site_id'} . '_' . $entity_type['name'];

          //Create a synchronization from the pool to the local connection
          $this->sendEntityRequest($url . '/api_unify-api_unify-connection_synchronisation-0_1', [
            'json' => [
              'id' => 'drupal_' . $this->{'site_id'} . '_' . $entity_type['id'] . '_synchronization_to_drupal',
              'name' => 'Synchronization for ' . $entity_type['name'] . ' from Pool -> ' . $this->{'site_id'},
              'options' => [
                'create_entities' => $type['sync_import'] == 'automatically' || $type['cloned_import'] == 'automatically',
                'update_entities' => true,
                'delete_entities' => boolval($type['delete_entity']),
                'clone_entities' => $type['cloned_import'] == 'automatically',
                'exclude_reference_properties' => [
                  'pSource'
                ]
              ],
              'status' => 'READY',
              'source_connection_id' => 'drupal_pool_' . $entity_type['name'],
              'destination_connection_id' => 'drupal_' . $this->{'site_id'} . '_' . $entity_type['name'],
            ],
          ]);

          if ($type['export'] != self::EXPORT_DISABLED) {
            $this->sendEntityRequest($url . '/api_unify-api_unify-connection_synchronisation-0_1', [
              'json' => [
                'id' => 'drupal_' . $this->{'site_id'} . '_' . $entity_type['id'] . '_synchronization_to_pool',
                'name' => 'Synchronization for ' . $entity_type['name'] . ' from ' . $this->{'site_id'} . ' -> Pool',
                'options' => [
                  'create_entities' => true,
                  'update_entities' => true,
                  'delete_entities' => true,
                  'clone_entities' => false,
                  'exclude_reference_properties' => [
                    'pSource'
                  ]
                ],
                'status' => 'READY',
                'source_connection_id' => 'drupal_' . $this->{'site_id'} . '_' . $entity_type['name'],
                'destination_connection_id' => 'drupal_pool_' . $entity_type['name'],
              ],
            ]);
          }

        } catch (RequestException $e) {
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

  protected function generateUrl($url, $parameters = []) {
    $resultUrl = Url::fromUri($url, [
      'query' => $parameters,
    ]);

    return $resultUrl->toUriString();
  }

  protected function getEntitiesByUrl($url, $parameters = []) {
    $result    = [];
    $finalStep = FALSE;
    $url       = $this->generateUrl($url, $parameters);

    while (!$finalStep) {
      $finalStep = TRUE;

      $responce  = $this->client->get($url);
      $body      = $responce->getBody()->getContents();
      $body      = json_decode($body);

      if ($body->number_of_pages > 1) {
        $finalStep  = FALSE;

        $parameters = array_merge($parameters, [
          'items_per_page' => $body->total_number_of_items,
        ]);

        $url = $this->generateUrl($url, $parameters);

        continue;
      }
    }

    foreach ($body->items as $key => $value) {
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
   *
   */
  protected function checkEntityExists($url, $entityId) {
    if (empty($this->unifyData[$url])) {
      $this->unifyData[$url]   = $this->getEntitiesByUrl($url);
    }

    $entityIndex  = array_search($entityId, $this->unifyData[$url]);
    $entityExists = (FALSE !== $entityIndex);

    if ($entityExists) {
      if (array_key_exists($entityId, $this->toBeDeleted)) {
        unset($this->toBeDeleted[$entityId]);
      }
    }

    return $entityExists;
  }

  protected function getRelatedEntities($url, $fieldName, $value) {
    $query = '{"operator":"==","values":[{"source":"data","field":"'. $fieldName . '"},{"source":"value","value":"' . $value . '"}]}';

    return $this->getEntitiesByUrl($url, ['condition' => $query]);
  }

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

  protected function cleanUnifyData() {
    try {
      foreach ($this->toBeDeleted as $id => $url) {
        $responce = $this->client->delete($url . '/' . $id);
      }
    }
    catch (RequestException $e) {
      drupal_set_message($e->getMessage(), 'error');
      return FALSE;
    }

    return TRUE;
  }

}
