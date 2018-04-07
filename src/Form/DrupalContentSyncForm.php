<?php

namespace Drupal\drupal_content_sync\Form;

use Behat\Mink\Exception\Exception;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;
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


  protected $entityPluginManager;
  protected $fieldPluginManager;

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
    $this->entityPluginManager = \Drupal::service('plugin.manager.dcs_entity_handler');
    $this->fieldPluginManager = \Drupal::service('plugin.manager.dcs_field_handler');
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
   * Gets migration configurations.
   *
   * @return array
   *   An array of migration names.
   */
  function updateSyncHandler($form, FormStateInterface $form_state) {
    /*$trigger  = $form_state->getTriggeringElement();
    $trigger  = explode('[',str_replace(']','',$trigger['#name']));
    $id       = $trigger[1];
    $value    = $form_state->getValue(['sync_entities',$id,'handler']);
    list($entity_type,$bundle,$field) = explode('-',$id);
    if(empty($field)) {
    }*/
    return $form['sync_entities'];
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /*$forbidden_fields = [
      'uuid','id','revision_id',

      'block_content' => ['type'],
      'freeform' => ['cid','vid','type','entity_id','entity_type'],
      'file' => ['fid'],
      'media'=>['mid','vid','bundle'],
      'node'=>['nid','vid'],
      'search_api_task'=>['type','server_id','index_id'],
      'access_token'=>['bundle'],
      'taxonomy_term'=>['tid','vid'],
      'user'=>['uid','pass'],
    ];*/

    $export_option_labels = [
      DrupalContentSync::EXPORT_DISABLED => $this->t('Disabled')->render(),
      DrupalContentSync::EXPORT_AUTOMATICALLY => $this->t('Automatically')->render(),
      DrupalContentSync::EXPORT_MANUALLY => $this->t('Manually')->render(),
    ];

    $import_option_labels = [
      DrupalContentSync::IMPORT_DISABLED => $this->t('Disabled')->render(),
      DrupalContentSync::IMPORT_AUTOMATICALLY => $this->t('Automatically')->render(),
      DrupalContentSync::IMPORT_MANUALLY => $this->t('Manually')->render(),
    ];

    $form = parent::form($form, $form_state);

    $form['#attached']['library'][] = 'drupal_content_sync/drupal-content-sync-form';

    $sync_entity = $this->entity;

    $def_sync_entities = $sync_entity->{'sync_entities'};

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
      '#prefix' => '<div id="sync-entities-table">',
      '#suffix' => '</div>',
      '#sticky' => TRUE,
      '#header' => array_merge([
        $this->t('Bundle'),
        $this->t('Identifier'),
        $this->t('Handler'),
        $this->t('Export'),
        $this->t('Synchronized Import'),
        $this->t('Cloned Import'),
        '',
        $this->t('Preview'),
        $this->t('Delete entities'),
        $this->t('Handler settings'),
      ]),
    ];

    $input = $form_state->getValue('sync_entities');

    foreach ($entity_types as $type_key => $entity_type) {
      // This entity type hasn't contained any fields.
      if (!isset($field_map[$type_key])) {
        continue;
      }

      $entity_table[$type_key]['title'] = [
        '#markup' => '<h2>' . str_replace('_', ' ', ucfirst($type_key)) . '</h2>',
        '#wrapper_attributes' => ['colspan' => sizeof($entity_table['#header'])],
      ];

      foreach ($entity_type as $entity_bundle_name => $entity_bundle) {
        $entity_bundle_row = [];

        $field_definitions = $this->entityFieldManager->getFieldDefinitions($type_key, $entity_bundle_name);

        $field_definitions_array = (array) $field_definitions;
        unset($field_definitions_array['field_drupal_content_synced']);
        ksort($field_definitions_array);
        $field_definitions_array = array_keys($field_definitions_array);

        $version = md5(json_encode($field_definitions_array));

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
            ])->render(),
            'entity_type' => $type_key,
            'entity_bundle' => $entity_bundle_name,
            'delete_entity' => NULL,
          ];
        }
        else {
          $row_default_values = $def_sync_entities[$type_key . '-' . $entity_bundle_name];
        }
        if( !empty($input[$type_key . '-' . $entity_bundle_name]) ) {
          $row_default_values = array_merge( $row_default_values, $input[$type_key . '-' . $entity_bundle_name] );
        }

        $entity_bundle_row['bundle'] = [
          '#markup' => $this->t('@bundle (@machine_name)', [
              '@bundle' => $entity_bundle['label'],
              '@machine_name' => $entity_bundle_name,
            ]) . '<br><small>version: ' . $version . '</small>',
        ];

        $entity_bundle_row['id'] = [
          '#type' => 'textfield',
          '#default_value' => $row_default_values['id'],
          '#title' => $this->t('Identifier'),
          '#disabled' => TRUE,
          '#size' => 24,
          '#title_display' => 'invisible',
        ];

        $entity_handlers = $this->entityPluginManager->getHandlerOptions($type_key,$entity_bundle_name,TRUE);
        if( empty($entity_handlers) ) {
          $handler_id='ignore';
          $entity_handlers = ['ignore'=>$this->t('Ignore (not supported)')->render()];
        }
        else {
          $entity_handlers = array_merge( ['ignore'=>$this->t('Ignore')->render()], $entity_handlers );
          $handler_id = empty($row_default_values['handler']) ? 'ignore' : $row_default_values['handler'];
        }

        $entity_bundle_row['handler'] = [
          '#type' => 'select',
          '#title' => $this->t('Handler'),
          '#title_display' => 'invisible',
          '#options' => $entity_handlers,
          '#default_value' => $handler_id,
          '#ajax' => array(
            'callback' => '::updateSyncHandler',
            'wrapper' => 'sync-entities-table',
            'progress' => array(
              'type' => 'throbber',
              'message' => "loading...",
            ),
          ),
        ];


        if( $handler_id=='ignore' ) {
          $export_options = [
            DrupalContentSync::EXPORT_DISABLED => $this->t('Disabled')->render(),
          ];
        }
        else {
          $handler = $this->entityPluginManager->createInstance($handler_id,[
            'entity_type_name'=>$type_key,
            'bundle_name'=>$entity_bundle_name,
            'settings'=>$row_default_values,
          ]);

          $allowed_export_options = $handler->getAllowedExportOptions();
          $export_options = [];
          foreach ($allowed_export_options as $option) {
            $export_options[$option] = $export_option_labels[$option];
          }
        }

        $entity_bundle_row['export'] = [
          '#type' => 'select',
          '#title' => $this->t('Export'),
          '#title_display' => 'invisible',
          '#options' => $export_options,
          '#default_value' => $row_default_values['export'],
        ];

        if( $handler_id=='ignore' ) {
          $import_options = [
            DrupalContentSync::IMPORT_DISABLED => $this->t('Disabled')->render(),
          ];
        }
        else {
          $allowed_import_options = $handler->getAllowedSyncImportOptions();
          $import_options = [];
          foreach ($allowed_import_options as $option) {
            $import_options[$option] = $import_option_labels[$option];
          }
        }
        $entity_bundle_row['sync_import'] = [
          '#type' => 'select',
          '#title' => $this->t('Synchronized Import'),
          '#title_display' => 'invisible',
          '#options' => $import_options,
          '#default_value' => $row_default_values['sync_import'],
        ];

        if( $handler_id=='ignore' ) {
          $import_options = [
            DrupalContentSync::IMPORT_DISABLED => $this->t('Disabled')->render(),
          ];
        }
        else {
          $allowed_import_options = $handler->getAllowedClonedImportOptions();
          $import_options = [];
          foreach ($allowed_import_options as $option) {
            $import_options[$option] = $import_option_labels[$option];
          }
        }
        $entity_bundle_row['cloned_import'] = [
          '#type' => 'select',
          '#title' => $this->t('Cloned Import'),
          '#title_display' => 'invisible',
          '#options' => $import_options,
          '#default_value' => $row_default_values['cloned_import'],
        ];


        $entity_bundle_row['has_preview_mode'] = [
          '#type' => 'hidden',
          '#default_value' => (int) $has_preview_mode,
          '#title' => $this->t('Has preview mode'),
          '#title_display' => 'invisible',
        ];
        $entity_bundle_row['preview'] = [
          '#type' => 'select',
          '#title' => $this->t('Preview'),
          '#title_display' => 'invisible',
          '#options' => array_merge([
            'excluded' => $this->t('Excluded')->render(),
          ], $handler_id=='ignore' ? [] : $handler->getAllowedPreviewOptions()),
          '#default_value' => $row_default_values['preview'],
        ];

        $entity_bundle_row['delete_entity'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Delete'),
          '#default_value' => $row_default_values['delete_entity'] == 1,
        ];

        if( $handler_id!='ignore' ) {
          $advanced_settings = $handler->getHandlerSettings();
          if( count($advanced_settings) ) {
            $entity_bundle_row['handler_settings']  = array_merge( [
              '#type' => 'container',
            ], $advanced_settings );
          }
        }

        /*$entity_bundle_row['version_hash'] = [
          '#type' => 'hidden',
          '#default_value' => $version,
          '#title' => $this->t('version_hash'),
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
        ];*/

        $entity_table[$type_key . '-' . $entity_bundle_name] = $entity_bundle_row;


        if( $handler_id!='ignore' ) {
          $entityFieldManager = \Drupal::service('entity_field.manager');
          $fields = $entityFieldManager->getFieldDefinitions($type_key, $entity_bundle_name);
          foreach ($fields as $key => $field) {
            $field_id = $type_key . '-' . $entity_bundle_name . '-' . $key;

            $field_row = [];

            $field_row['bundle'] = [
              '#markup' => $key,
            ];

            if (!isset($def_sync_entities[$field_id])) {
              $field_default_values = [
                'id' => $field_id,
                'export' => NULL,
                'sync_import' => NULL,
                'cloned_import' => NULL,
                'preview' => NULL,
                'entity_type' => $type_key,
                'entity_bundle' => $entity_bundle_name,
                'delete_entity' => NULL,
              ];
            }
            else {
              $field_default_values = $def_sync_entities[$field_id];
            }
            if( !empty($input[$field_id]) ) {
              $field_default_values = array_merge( $field_default_values, $input[$field_id] );
            }

            $field_row['id'] = [
              '#type' => 'textfield',
              '#default_value' => $field_default_values['id'],
              '#title' => $this->t('Identifier'),
              '#disabled' => TRUE,
              '#size' => 24,
              '#title_display' => 'invisible',
            ];

            $entity_type_entity = \Drupal::entityTypeManager()
              ->getStorage($type_key)->getEntityType();
            $forbidden_fields = [
              $entity_type_entity->getKey('id'),
              $entity_type_entity->getKey('revision'),
              $entity_type_entity->getKey('bundle'),
              $entity_type_entity->getKey('uuid'),
            ];

            if (in_array($key, $forbidden_fields) !== FALSE) {
              $handler_id = 'ignore';
              $field_handlers = [
                'ignore' => $this->t('Not configurable')->render()
              ];
            }
            else {
              $field_handlers = $this->fieldPluginManager->getHandlerOptions($type_key, $entity_bundle_name, $key, $field, TRUE);
              if (empty($field_handlers)) {
                $handler_id = 'ignore';
                $field_handlers = [
                  'ignore' => $this->t('Ignore (no handler supports this @type)', ['@type' => $field->getType()])->render()
                ];
              }
              else {
                reset($field_handlers);
                $handler_id = empty($field_default_values['handler']) ? key($field_handlers) : $field_default_values['handler'];
              }
            }

            $field_row['handler'] = [
              '#type' => 'select',
              '#title' => $this->t('Handler'),
              '#title_display' => 'invisible',
              '#options' => array_merge(['ignore' => $this->t('Ignore')->render()], $field_handlers),
              '#default_value' => $handler_id,
              '#ajax' => array(
                'callback' => '::updateSyncHandler',
                'wrapper' => 'sync-entities-table',
                'progress' => array(
                  'type' => 'throbber',
                  'message' => "loading...",
                ),
              ),
            ];

            if( $handler_id=='ignore' ) {
              $export_options = [
                DrupalContentSync::EXPORT_DISABLED => $this->t('Disabled')->render(),
              ];
            }
            else {
              $handler = $this->fieldPluginManager->createInstance($handler_id,[
                'entity_type_name'=>$type_key,
                'bundle_name'=>$entity_bundle_name,
                'field_name'=>$key,
                'field_definition'=>$field,
                'settings'=>$field_default_values,
              ]);

              $allowed_export_options = $handler->getAllowedExportOptions();
              $export_options = [];
              foreach ($allowed_export_options as $option) {
                $export_options[$option] = $export_option_labels[$option];
              }
            }

            $field_row['export'] = [
              '#type' => 'select',
              '#title' => $this->t('Export'),
              '#title_display' => 'invisible',
              '#options' => $export_options,
              '#default_value' => $field_default_values['export'] ? $field_default_values['export'] : (isset($export_options[DrupalContentSync::EXPORT_AUTOMATICALLY]) ? DrupalContentSync::EXPORT_AUTOMATICALLY : DrupalContentSync::EXPORT_DISABLED),
            ];

            if( $handler_id=='ignore' ) {
              $import_options = [
                DrupalContentSync::IMPORT_DISABLED => $this->t('Disabled')->render(),
              ];
            }
            else {
              $allowed_import_options = $handler->getAllowedSyncImportOptions();
              $import_options = [];
              foreach ($allowed_import_options as $option) {
                $import_options[$option] = $import_option_labels[$option];
              }
            }
            $field_row['sync_import'] = [
              '#type' => 'select',
              '#title' => $this->t('Synchronized Import'),
              '#title_display' => 'invisible',
              '#options' => $import_options,
              '#default_value' => $field_default_values['sync_import'] ? $field_default_values['sync_import'] : (isset($import_options[DrupalContentSync::EXPORT_AUTOMATICALLY]) ? DrupalContentSync::EXPORT_AUTOMATICALLY : DrupalContentSync::EXPORT_DISABLED),
            ];

            if( $handler_id=='ignore' ) {
              $import_options = [
                DrupalContentSync::IMPORT_DISABLED => $this->t('Disabled')->render(),
              ];
            }
            else {
              $allowed_import_options = $handler->getAllowedClonedImportOptions();
              $import_options = [];
              foreach ($allowed_import_options as $option) {
                $import_options[$option] = $import_option_labels[$option];
              }
            }
            $field_row['cloned_import'] = [
              '#type' => 'select',
              '#title' => $this->t('Cloned Import'),
              '#title_display' => 'invisible',
              '#options' => $import_options,
              '#default_value' => $field_default_values['cloned_import'] ? $field_default_values['cloned_import'] : (isset($import_options[DrupalContentSync::EXPORT_AUTOMATICALLY]) ? DrupalContentSync::EXPORT_AUTOMATICALLY : DrupalContentSync::EXPORT_DISABLED),
            ];

            $field_row['has_preview'] = [
              '#markup' => '',
            ];
            $field_row['preview'] = [
              '#markup' => '',
            ];
            $field_row['delete_entity'] = [
              '#markup' => '',
            ];

            if( $handler_id!='ignore' ) {
              $advanced_settings = $handler->getHandlerSettings($field_default_values);
              if( count($advanced_settings) ) {
                $field_row['handler_settings']  = array_merge( [
                  '#type' => 'container',
                ], $advanced_settings );
              }
            }

            $entity_table[$field_id] = $field_row;
          }
        }
      }
    }

    $form['sync_entities'] = $entity_table;

    $this->disableOverridenConfigs($form);
    return $form;
  }

  /**
   * Disable form elements which are overridden
   *
   * @param array $form
   */
  private function disableOverridenConfigs(array &$form) {
    global $config;
    $config_name = 'drupal_content_sync.drupal_content_sync.' . $form['id']['#default_value'];

    // If the default overrides aren't used check if a master / subsite setting is used
    if (!isset($config[$config_name]) || empty($config[$config_name])) {
      // Is this site a master site? It is a subsite by default.
      $environment = 'subsite';
      if (\Drupal::config('config_split.config_split.drupal_content_sync_master')
        ->get('status')
      ) {
        $environment = 'master';
      }
      $config_name = 'drupal_content_sync.sync.' . $environment;
    }
    $fields = Element::children($form);
    foreach ($fields as $field_key) {
      if ($this->configIsOverridden($field_key, $config_name)) {
        $form[$field_key]['#disabled'] = 'disabled';
        $form[$field_key]['#value'] = \Drupal::config($config_name)
          ->get($field_key);
        unset($form[$field_key]['#default_value']);
      }
    }
  }


  /**
   * Check if a config is overridden
   *
   * Right now it only checks if the config is in the $config-array (overridden
   * by the settings.php)
   *
   * @todo take care of overriding by modules and languages
   *
   * @param $config_key
   * @param $environment Either subsite or master
   *
   * @return bool
   */
  private function configIsOverridden($config_key, $config_name) {
    global $config;
    return isset($config[$config_name][$config_key]);
  }


  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $config = $this->entity;
    $is_new = !$this->exist($config->id());
    $status = $config->save();

    $sync_entities = $config->{'sync_entities'};
    foreach ($sync_entities as $key => $bundle_fields) {
      // Ignore field settings
      if( substr_count($key,'-')!=1 ) {
        continue;
      }

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
      $link_data = [
        'link' => ['uri' => $uri],
        'title' => $this->entity->label(),
        'menu_name' => 'admin',
        'parent' => 'system.admin_content',
      ];
      if($is_new) {
        $item = MenuLinkContent::create($link_data);
        $item->save();
        menu_cache_clear_all();
      } else {
        $links = \Drupal::entityTypeManager()->getStorage('menu_link_content')
          ->loadByProperties(['link__uri' => $uri]);

        if ($link = reset($links)) {
          $link->set('title', $this->entity->label());
        } else {
          $link = MenuLinkContent::create($link_data);
        }
        $link->save();
        menu_cache_clear_all();
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
}
