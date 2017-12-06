<?php

namespace Drupal\drupal_content_sync\Form;

use Behat\Mink\Exception\Exception;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Form handler for the DrupalContentSync add and edit forms.
 */
class DrupalContentSyncForm extends EntityForm {

  /**
   * @const DRUPAL_CONTENT_SYNC
   */
  const DRUPAL_CONTENT_SYNC_PREVIEW_FIELD = 'drupal_content_sync_preview';

  /**
   * @const FIELD_DRUPAL_CONTENT_SYNCED
   */
  const FIELD_DRUPAL_CONTENT_SYNCED = 'field_drupal_content_synced';

  /**
   * @var EntityTypeManager $entityTypeManager
   */
  protected $entityTypeManager;

  /**
   * @var EntityTypeBundleInfoInterface $entityTypeManager
   */
  protected $bundleInfoService;

  /**
   * @var EntityFieldManager $entityFieldManager
   */
  protected $entityFieldManager;

  /**
   * Constructs an object.
   *
   * @param EntityTypeManager $entity_type_manager
   *   The entity query.
   * @param EntityTypeBundleInfoInterface $bundle_info_service
   *   The bundle info service.
   * @param EntityFieldManager $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(EntityTypeManager $entity_type_manager, EntityTypeBundleInfoInterface $bundle_info_service, EntityFieldManager $entity_field_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->bundleInfoService = $bundle_info_service;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#attached']['library'][] = 'drupal_content_sync/drupal-content-sync-form';

    $sync_entity = $this->entity;

    $def_sync_entities = json_decode($sync_entity->{'sync_entities'}, TRUE);

    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#maxlength' => 255,
      '#default_value' => $sync_entity->label(),
      '#description' => $this->t("An administrative name describing the workflow intended to be achieved with this synchronization."),
      '#required' => TRUE,
    );

    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $sync_entity->id(),
      '#machine_name' => array(
        'exists' => array($this, 'exist'),
        'source' => array('name'),
      ),
      '#disabled' => !$sync_entity->isNew(),
    );

    $form['api'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('API'),
      '#maxlength' => 255,
      '#default_value' => isset($sync_entity->{'api'}) ? $sync_entity->{'api'} : '',
      '#description' => $this->t("The API identifies the content pool to work with. All sites which should be connected need to have the same API value."),
      '#required' => TRUE,
    );

    $form['url'] = array(
      '#type' => 'url',
      '#title' => $this->t('Drupal Content Sync URL'),
      '#maxlength' => 255,
      '#default_value' => isset($sync_entity->{'url'}) ? $sync_entity->{'url'} : '',
      '#description' => $this->t("The entity types selected below will be exposed to this synchronization backend. Make sure to include the rest path of the app (usually /rest)."),
      '#required' => TRUE,
    );

    $form['site_id'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Site identifier '),
      '#maxlength' => 255,
      '#default_value' => isset($sync_entity->{'site_id'}) ? $sync_entity->{'site_id'} : '',
      '#description' => $this->t("This identifier will be used to identify the origin of entities on other sites and is used as a machine name for identification. Once connected, you cannot change this identifier anylonger. Typicall you want to use the fully qualified domain name of this website as an identifier."),
      '#required' => TRUE,
    );

    $entity_types = $this->bundleInfoService->getAllBundleInfo();

    $display_modes = $this->entityTypeManager
      ->getStorage('entity_view_display')
      ->loadMultiple();

    $display_modes_ids = array_keys($display_modes);

    $field_map = $this->entityFieldManager->getFieldMap();

    $entity_table = [
      '#type' => 'table',
      '#header' => [
        $this->t('Bundle'),
        $this->t('Identifier'),
        $this->t('Export'),
        $this->t('Synchronized Import'),
        $this->t('Cloned Import'),
        $this->t('Preview'),
        $this->t('Delete entities'),
        '',
        '',
        '',
        '',
        '',
      ],
    ];

    foreach ($entity_types as $type_key => $entity_type) {
      // This entity type hasn't contained any fields.
      if (!isset($field_map[$type_key])) {
        continue;
      }

      $entity_table[][] = [
        '#markup' => '<h2>' . str_replace('_', ' ', ucfirst($type_key)) . '</h2>',
        '#wrapper_attributes' => ['colspan' => 8],
      ];

      foreach ($entity_type as $entity_bundle_name => $entity_bundle) {
        $entity_bundle_row = [];

        $entity_type_object = $this->entityTypeManager
          ->getStorage($type_key)
          ->getEntityType();

        $field_definitions = $this->entityFieldManager->getFieldDefinitions($type_key, $entity_bundle_name);

        $entity_type_array = (array) $entity_type_object;
        $field_definitions_array = (array) $field_definitions;

        unset($field_definitions_array['field_drupal_content_synced']);

        ksort($entity_type_array);
        ksort($field_definitions_array);

        $version = md5(json_encode($entity_type_array) . json_encode($field_definitions_array));

        $entity_bundle_row['bundle'] = [
          '#markup' => $this->t('@bundle (@machine_name)', [
            '@bundle' => $entity_bundle['label'],
            '@machine_name' => $entity_bundle_name,
          ]) . '<br><small>version: ' . $version . '</small>',
        ];

        $current_display_mode = $type_key . '.' . $entity_bundle_name . '.' . self::DRUPAL_CONTENT_SYNC_PREVIEW_FIELD;
        $has_preview_mode = in_array($current_display_mode, $display_modes_ids) || $type_key == 'file';

        if (!isset($def_sync_entities[$type_key . '-' . $entity_bundle_name])) {
          $row_default_values = [
            'id' => $type_key . '-' . $entity_bundle_name,
            'export' => FALSE,
            'sync_import' => NULL,
            'cloned_import' => NULL,
            'preview' => NULL,
            'display_name' => $this->t('@bundle', [
              '@bundle' => $entity_bundle['label'],
              ]),
            'entity_type' => $type_key,
            'entity_bundle' => $entity_bundle_name,
            'delete_entity' => NULL,
          ];
        }
        else {
          $row_default_values = $def_sync_entities[$type_key . '-' . $entity_bundle_name];
        }

        $entity_bundle_row['id'] = [
          '#type' => 'textfield',
          '#default_value' => $row_default_values['id'],
          '#title' => $this->t('Identifier'),
          '#title_display' => 'invisible',
        ];

        $entity_bundle_row['export'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Export'),
          '#title_display' => 'invisible',
          '#default_value' => $row_default_values['export'] == 1,
        ];

        $entity_bundle_row['sync_import'] = [
          '#type' => 'select',
          '#title' => $this->t('Synchronized Import'),
          '#title_display' => 'invisible',
          '#options' => [
            'disabled' => $this->t('Disabled'),
            'manually' => $this->t('Manually'),
            'automatically' => $this->t('Automatically'),
          ],
          '#default_value' => $row_default_values['sync_import'],
        ];

        $entity_bundle_row['cloned_import'] = [
          '#type' => 'select',
          '#title' => $this->t('Cloned Import'),
          '#title_display' => 'invisible',
          '#options' => [
            'disabled' => $this->t('Disabled'),
            'manually' => $this->t('Manually'),
            'automatically' => $this->t('Automatically'),
          ],
          '#default_value' => $row_default_values['cloned_import'],
        ];

        $entity_bundle_row['preview'] = [
          '#type' => 'select',
          '#title' => $this->t('Preview'),
          '#title_display' => 'invisible',
          '#options' => [
            'excluded' => $this->t('Excluded'),
            'table' => $this->t('Table'),
            'preview_mode' => $this->t('Preview mode'),
          ],
          '#default_value' => $row_default_values['preview'],
        ];

        $entity_bundle_row['delete_entity'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Delete entity'),
          '#title_display' => 'invisible',
          '#default_value' => $row_default_values['delete_entity'] == 1,
        ];

        $entity_bundle_row['version_hash'] = [
          '#type' => 'hidden',
          '#default_value' => $version,
          '#title' => $this->t('version_hash'),
          '#title_display' => 'invisible',
        ];

        $entity_bundle_row['has_preview_mode'] = [
          '#type' => 'hidden',
          '#default_value' => (int) $has_preview_mode,
          '#title' => $this->t('Has preview mode'),
          '#title_display' => 'invisible',
        ];

        $entity_bundle_row['display_name'] = [
          '#type' => 'hidden',
          '#value' => $row_default_values['display_name'],
        ];

        $entity_bundle_row['entity_type'] = [
          '#type' => 'hidden',
          '#value' => $row_default_values['entity_type'],
        ];

        $entity_bundle_row['entity_bundle'] = [
          '#type' => 'hidden',
          '#value' => $row_default_values['entity_bundle'],
        ];

        $entity_table[$type_key . '-' . $entity_bundle_name] = $entity_bundle_row;
      }
    }

    $form['sync_entities'] = $entity_table;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $config = $this->entity;
    $is_new = !$this->exist($config->id());

    if ($is_new || true) {
      if (!$this->initialize()) {
        drupal_set_message('The communication with the Drupal Content Sync Server failed.' .
          ' Therefore the synchronization entity could not be saved. For more' .
          ' information see the error output above.', 'error');
        return;
      }
    }

    $sync_entities = $config->{'sync_entities'};
    $config->{'sync_entities'} = json_encode($sync_entities);
    $status = $config->save();

    foreach ($sync_entities as $key => $bundle_fields) {
      preg_match('/^(.+)-(.+)$/', $key, $matches);

      $type_key = $matches[1];
      $bundle_key = $matches[2];

      if ('disabled' !== $bundle_fields['sync_import']) {
        $field_storage = FieldStorageConfig::loadByName($type_key, self::FIELD_DRUPAL_CONTENT_SYNCED);

        if (is_null($field_storage)) {
          $field_drupal_content_synced = [
            'field_name' => self::FIELD_DRUPAL_CONTENT_SYNCED,
            'entity_type' => $type_key,
            'type' => 'boolean',
            'cardinality' => -1,
          ];

          FieldStorageConfig::create($field_drupal_content_synced)->save();
        }

        $bundle_field_config = FieldConfig::loadByName($type_key, $bundle_key, self::FIELD_DRUPAL_CONTENT_SYNCED);

        if (is_null($bundle_field_config)) {
          $bundle_field = [
            'field_name' => self::FIELD_DRUPAL_CONTENT_SYNCED,
            'entity_type' => $type_key,
            'bundle' => $bundle_key,
            'label' => 'Drupal Content Synced',
          ];

          FieldConfig::create($bundle_field)->save();
        }
      }
    }

    if ($status) {
      drupal_set_message($this->t('Saved the %label Drupal Content Synchronization.', array(
        '%label' => $config->label(),
      )));
      $uri = 'internal:/admin/content/drupal_content_synchronization/' . $this->entity->id();
      if($is_new) {
        $item = MenuLinkContent::create([
          'link' => ['uri' => $uri],
          'title' => $this->entity->label(),
          'menu_name' => 'admin',
          'parent' => 'system.admin_content',
        ]);
        $item->save();
        menu_cache_clear_all();
      } else {
        $links = \Drupal::entityTypeManager()->getStorage('menu_link_content')
          ->loadByProperties(['link__uri' => $uri]);

        if ($link = reset($links)) {
          $link->set('title', $this->entity->label());
          $link->save();
          menu_cache_clear_all();
        }
      }
    }
    else {
      drupal_set_message($this->t('The %label Drupal Content Synchronization was not saved.', array(
        '%label' => $config->label(),
      )));
    }

    $form_state->setRedirect('entity.drupal_content_sync.collection');
  }

  /**
   * A helper function to check whether an DrupalContentSync configuration entity exists.
   *
   * @param int $id
   *   An ID of sync.
   *
   * @return bool
   *   Checking on exist an entity.
   */
  public function exist($id) {
    $entity = $this->entityTypeManager
      ->getStorage('drupal_content_sync')
      ->getQuery()
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

  /**
   * Method do create all Drupal Content Sync entities which are needed for a snychronization
   *
   * @return bool
   */
  private function initialize() {
    $url = $this->entity->{'url'};
    $client = \Drupal::httpClient();

    //Check if a connection to Drupal Content Sync can be established
    try {
      $client->get($url . '/status');

      //Create the API entity
      $client->post($url . '/api_unify-api_unify-api-0_1', [
        'json' => [
          'id' => $this->entity->{'api'} . '-0.1',
          'name' => $this->entity->{'api'},
          'version' => '0.1',
        ],
      ]);

      //Create the instance entity
      $client->post($url . '/api_unify-api_unify-instance-0_1', [
        'json' => [
          'id' => $this->entity->{'site_id'},
          'api_id' => $this->entity->{'api'} . '-0.1',
        ],
      ]);

      //Create the preview connection entity
      $client->post($url . '/api_unify-api_unify-connection-0_1', [
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
    } catch (RequestException $e) {
      drupal_set_message($e->getMessage(), 'error');
      return FALSE;
    }
    return TRUE;
  }

  private function createEntityTypes() {
    global $base_url;
    $url = $this->entity->{'url'};
    $client = \Drupal::httpClient();
    $localConnections = [];

    $entity_types = $this->entity->{'sync_entities'};
    foreach ($entity_types as $type) {
      if ($type['export'] == 1 || $type['preview'] != 'excluded') {
        $fields = $this->entityFieldManager->getFieldDefinitions($type['entity_type'], $type['entity_bundle']);

        $fields_to_ignore = ['id,', 'nid', 'vid', 'type', 'path', 'revision_log', 'revision_translation_affected', 'menu_link', 'field_drupal_content_synced', 'field_media_id', 'field_media_connection_id', 'field_media', 'field_term_ref_id', 'field_term_ref_connection_id', 'field_term_ref', 'field_drupal_content_synced'];

        $entity_type = [
          'id' => 'drupal-' . $type['entity_type'] . '-' . $type['entity_bundle'] . '-' . $type['version_hash'],
          'name_space' => $type['entity_type'],
          'name' => $type['entity_bundle'],
          'version' => $type['version_hash'],
          'base_class' => "api-unify/services/drupal/v0.1/models/base.model",
          'custom' => true,
          'new_properties' => [
            'id' => [
              'type' => 'string',
            ],
            'source' => [
              'type' => 'reference',
              'default_value' => null,
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
              'default_value' => null,
            ],
            'source_connection_id' => [
              'type' => 'id',
              'default_value' => null,
            ],
            'preview' => [
              'type' => 'string',
              'default_value' => null,
            ],
            'url' => [
              'type' => 'string',
              'default_value' => null,
            ],
            'apiu_translation' => [
              'type' => 'object',
              'default_value' => null
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
            ],
            'database' => [
              'id' => 'value',
              'source_id' => 'value',
              'source_connection_id' => 'value',
              'preview' => 'value',
              'url' => 'value',
              'apiu_translation' => 'value',
            ],
            'modifiable' => [
              'preview' => 'value',
              'url' => 'value',
              'apiu_translation' => 'value',
            ],
            'required' => [],
          ],
          'api_id' => $this->entity->{'api'} . '-0.1',
        ];

        if ($type['entity_type'] == 'file') {
          $entity_type['new_properties']['apiu_file_content'] = [
            'type' => 'string',
            'default_value' => null,
          ];
          $entity_type['new_property_lists']['details']['apiu_file_content'] = 'value';
          $entity_type['new_property_lists']['filesystem']['apiu_file_content'] = 'value';
          $entity_type['new_property_lists']['required']['apiu_file_content'] = 'value';
          $entity_type['new_properties']['title'] = [
            'type' => 'string',
            'default_value' => null,
          ];
          $entity_type['new_property_lists']['database']['title'] = 'value';
        }

        foreach ($fields as $key => $field) {
          if (in_array($key, $fields_to_ignore)) {
            continue;
          }
          switch ($field->getType()) {
            case 'integer':
            case 'created':
            case 'changed':
              $field_type = 'int';
              break;
            case 'boolean':
              $field_type = 'bool';
              break;
            case 'entity_reference':
              $field_type = 'reference';
              break;
            case 'text_with_summary':
              $field_type = 'text_with_summary';
              break;
            case 'image':
              $field_type = 'file';
              break;
            default:
              $field_type = 'string';
          }
          if ($field_type == 'reference') {
            $entity_type['new_properties'][$key . '_id'] = [
              'type' => 'id',
              'default_value' => null,
            ];
            $entity_type['new_properties'][$key . '_connection_id'] = [
              'type' => 'id',
              'default_value' => null,
            ];
            $entity_type['new_properties'][$key] = [
              'type' => $field_type,
              'default_value' => null,
              'connection_identifiers' => [
                [
                  'properties' => [
                    'id' => $key . '_connection_id',
                  ],
                ],
              ],
              'model_identifiers' => [
                [
                  'properties' => [
                    'id' => $key . '_id',
                  ],
                ],
              ],
              'multiple' => FALSE,
            ];
            $entity_type['new_property_lists']['details'][$key] = 'reference';
            $entity_type['new_property_lists']['database'][$key . '_id'] = 'value';
            $entity_type['new_property_lists']['database'][$key . '_connection_id'] = 'value';
            if ($field->isRequired()) {
              $entity_type['new_property_lists']['required'][$key . '_id'] = 'value';
              $entity_type['new_property_lists']['required'][$key . '_connection_id'] = 'value';
            }
            if (!$field->isReadOnly()) {
              $entity_type['new_property_lists']['modifiable'][$key . '_id'] = 'value';
              $entity_type['new_property_lists']['modifiable'][$key . '_connection_id'] = 'value';
            }
          }
          elseif ($field_type == 'text_with_summary') {
            $entity_type['new_properties'][$key] = [
              'type' => 'string',
              'default_value' => null,
            ];
            $entity_type['new_properties'][$key . '_summary'] = [
              'type' => 'string',
              'default_value' => null,
            ];
            $entity_type['new_properties'][$key . '_format'] = [
              'type' => 'string',
              'default_value' => null,
            ];
            $entity_type['new_property_lists']['details'][$key] = 'value';
            $entity_type['new_property_lists']['details'][$key . '_summary'] = 'value';
            $entity_type['new_property_lists']['details'][$key . '_format'] = 'value';
            $entity_type['new_property_lists']['database'][$key] = 'value';
            $entity_type['new_property_lists']['database'][$key . '_summary'] = 'value';
            $entity_type['new_property_lists']['database'][$key . '_format'] = 'value';
            if ($field->isRequired()) {
              $entity_type['new_property_lists']['required'][$key] = 'value';
            }
            if (!$field->isReadOnly()) {
              $entity_type['new_property_lists']['modifiable'][$key] = 'value';
              $entity_type['new_property_lists']['modifiable'][$key . '_summary'] = 'value';
              $entity_type['new_property_lists']['modifiable'][$key . '_format'] = 'value';
            }
          }
          elseif ($field_type == 'file') {
            //TODO
            $entity_type['new_properties'][$key] = [
              'type' => 'string',
              'default_value' => null,
            ];
            $entity_type['new_property_lists']['details'][$key] = 'value';
            $entity_type['new_property_lists']['filesystem'][$key] = 'value';
            if ($field->isRequired()) {
              $entity_type['new_property_lists']['required'][$key] = 'value';
            }
            if (!$field->isReadOnly()) {
              $entity_type['new_property_lists']['modifiable'][$key] = 'value';
            }
          }
          else {
            $entity_type['new_properties'][$key] = [
              'type' => $field_type,
              'default_value' => null,
            ];
            $entity_type['new_property_lists']['details'][$key] = 'value';
            $entity_type['new_property_lists']['database'][$key] = 'value';
            if ($field->isRequired()) {
              $entity_type['new_property_lists']['required'][$key] = 'value';
            }
            if (!$field->isReadOnly()) {
              $entity_type['new_property_lists']['modifiable'][$key] = 'value';
            }
          }
        }

        try {
          //Create the entity type
          $client->post($url . '/api_unify-api_unify-entity_type-0_1', [
            'json' => $entity_type,
          ]);

          //Create the pool connection entity for this entity type
          $client->post($url . '/api_unify-api_unify-connection-0_1', [
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
          $client->post($url . '/api_unify-api_unify-connection_synchronisation-0_1', [
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

          //Create the instance connection entity for this entity type
          $client->post($url . '/api_unify-api_unify-connection-0_1', [
            'json' => [
              'id' => 'drupal_' . $this->entity->{'site_id'} . '_' . $entity_type['name'],
              'name' => 'Drupal connection on ' . $this->entity->{'site_id'} . ' for ' . $entity_type['name'],
              'hash' => 'drupal/' . $this->entity->{'site_id'} . '/' . $entity_type['name_space'] . '/' . $entity_type['name'],
              'usage' => 'EXTERNAL',
              'status' => 'READY',
              'entity_type_id' => $entity_type['id'],
              'instance_id' => $this->entity->{'site_id'},
              'options' => [
                'pull_interval' => 86400000,
                'authentication' => [
                  'type' => 'drupal8_services',
                  'username' => 'Drupal Content Sync',
                  'password' => 'Drupal Content Sync',
                  'base_url' => $base_url,
                ],
                'crud' => [
                  'read_list' => [
                    'url' => $base_url . '/drupal_content_sync_entity_resource/' . $entity_type['name_space'] . '/' . $entity_type['name'] . '/0?_format=json',
                  ],
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
          $localConnections[] = 'drupal_' . $this->entity->{'site_id'} . '_' . $entity_type['name'];

          //Create a synchronization from the pool to the local connection
          $client->post($url . '/api_unify-api_unify-connection_synchronisation-0_1', [
            'json' => [
              'id' => 'drupal_' . $this->entity->{'site_id'} . '_' . $entity_type['id'] . '_synchronization_to_drupal',
              'name' => 'Synchronization for ' . $entity_type['name'] . ' from Pool -> ' . $this->entity->{'site_id'},
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
              'destination_connection_id' => 'drupal_' . $this->entity->{'site_id'} . '_' . $entity_type['name'],
            ],
          ]);

          if ($type['export'] == 1) {
            $client->post($url . '/api_unify-api_unify-connection_synchronisation-0_1', [
              'json' => [
                'id' => 'drupal_' . $this->entity->{'site_id'} . '_' . $entity_type['id'] . '_synchronization_to_pool',
                'name' => 'Synchronization for ' . $entity_type['name'] . ' from ' . $this->entity->{'site_id'} . ' -> Pool',
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
                'source_connection_id' => 'drupal_' . $this->entity->{'site_id'} . '_' . $entity_type['name'],
                'destination_connection_id' => 'drupal_pool_' . $entity_type['name'],
              ],
            ]);
          }

        } catch (RequestException $e) {
          drupal_set_message($e->getMessage(), 'error');
          return FALSE;
        }
      }
    }
    $config = $this->entity;
    $config->{'local_connections'} = json_encode($localConnections);
  }
}
