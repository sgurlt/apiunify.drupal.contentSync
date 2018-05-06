<?php

namespace Drupal\drupal_content_sync\Form;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\drupal_content_sync\ApiUnifyConfig;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\drupal_content_sync\Plugin\Type\EntityHandlerPluginManager;
use Drupal\drupal_content_sync\Plugin\Type\FieldHandlerPluginManager;
use Drupal\Core\Config\ConfigFactory;
use GuzzleHttp\Client;
use Drupal\Core\Site\Settings;

/**
 * Form handler for the DrupalContentSync add and edit forms.
 */
class DrupalContentSyncForm extends EntityForm {

  /**
   * @var string DRUPAL_CONTENT_SYNC_PREVIEW_FIELD
   *    The name of the view mode that must be present to allow teaser previews.
   */
  const DRUPAL_CONTENT_SYNC_PREVIEW_FIELD = 'drupal_content_sync_preview';

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfoService;

  /**
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * @var \Drupal\drupal_content_sync\Plugin\Type\EntityHandlerPluginManager
   */
  protected $entityPluginManager;

  /**
   * @var \Drupal\drupal_content_sync\Plugin\Type\FieldHandlerPluginManager
   */
  protected $fieldPluginManager;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The config factory to load configuration.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The http client to connect to API Unify.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Constructs an object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity query.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info_service
   *   The bundle info service.
   * @param \Drupal\Core\Entity\EntityFieldManager $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\drupal_content_sync\Plugin\Type\EntityHandlerPluginManager $entity_plugin_manager
   *   The drupal content sync entity manager.
   * @param \Drupal\drupal_content_sync\Plugin\Type\FieldHandlerPluginManager $field_plugin_manager
   *   The drupal content sync field plugin manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The messenger service.
   * @param \GuzzleHttp\Client $http_client
   *   The http client to connect to API Unify.
   */
  public function __construct(EntityTypeManager $entity_type_manager,
                              EntityTypeBundleInfoInterface $bundle_info_service,
                              EntityFieldManager $entity_field_manager,
                              EntityHandlerPluginManager $entity_plugin_manager,
                              FieldHandlerPluginManager $field_plugin_manager,
                              MessengerInterface $messenger,
                              ConfigFactory $config_factory,
                              Client $http_client) {
    $this->entityTypeManager = $entity_type_manager;
    $this->bundleInfoService = $bundle_info_service;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityPluginManager = $entity_plugin_manager;
    $this->fieldPluginManager = $field_plugin_manager;
    $this->messenger = $messenger;
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.dcs_entity_handler'),
      $container->get('plugin.manager.dcs_field_handler'),
      $container->get('messenger'),
      $container->get('config.factory'),
      $container->get('http_client')
    );
  }

  /**
   * A sync handler has been updated, so the options must be updated as well.
   * We're simply reloading the table in this case.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   *   The new sync_entities table.
   */
  public function updateSyncHandler($form, FormStateInterface $form_state) {
    return $form['sync_entities'];
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $export_option_labels = [
      DrupalContentSync::EXPORT_DISABLED => $this->t('Disabled')->render(),
      DrupalContentSync::EXPORT_AUTOMATICALLY => $this->t('All')->render(),
      DrupalContentSync::EXPORT_AS_DEPENDENCY => $this->t('Referenced')->render(),
      DrupalContentSync::EXPORT_MANUALLY => $this->t('Manually')->render(),
    ];
    $export_option_labels_fields = [
      DrupalContentSync::EXPORT_DISABLED => $this->t('No')->render(),
      DrupalContentSync::EXPORT_AUTOMATICALLY => $this->t('Yes')->render(),
    ];

    $import_option_labels = [
      DrupalContentSync::IMPORT_DISABLED => $this->t('Disabled')->render(),
      DrupalContentSync::IMPORT_AUTOMATICALLY => $this->t('All')->render(),
      DrupalContentSync::IMPORT_AS_DEPENDENCY => $this->t('Referenced')->render(),
      DrupalContentSync::IMPORT_MANUALLY => $this->t('Manually')->render(),
    ];

    $import_option_labels_fields = [
      DrupalContentSync::IMPORT_DISABLED => $this->t('No')->render(),
      DrupalContentSync::IMPORT_AUTOMATICALLY => $this->t('Yes')->render(),
    ];

    $form = parent::form($form, $form_state);

    $form['#attached']['library'][] = 'drupal_content_sync/drupal-content-sync-form';

    $sync_entity = $this->entity;

    $def_sync_entities = $sync_entity->{'sync_entities'};

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#maxlength' => 255,
      '#default_value' => $sync_entity->label(),
      '#description' => $this->t("An administrative name describing the workflow intended to be achieved with this synchronization."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $sync_entity->id(),
      '#machine_name' => [
        'exists' => [$this, 'exist'],
        'source' => ['name'],
      ],
      '#disabled' => !$sync_entity->isNew(),
    ];

    $form['api'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API'),
      '#maxlength' => 255,
      '#default_value' => isset($sync_entity->{'api'}) ? $sync_entity->{'api'} : '',
      '#description' => $this->t("The API identifies the content pool to work with. All sites which should be connected need to have the same API value."),
      '#required' => TRUE,
    ];

    $form['url'] = [
      '#type' => 'url',
      '#title' => $this->t('Drupal Content Sync URL'),
      '#maxlength' => 255,
      '#default_value' => isset($sync_entity->{'url'}) ? $sync_entity->{'url'} : '',
      '#description' => $this->t("The entity types selected below will be exposed to this synchronization backend. Make sure to include the rest path of the app (usually /rest)."),
      '#required' => TRUE,
    ];

    // Check if the site id got set within the settings*.php.
    if (!is_null($sync_entity->id)) {
      $config_machine_name = $sync_entity->id;
      $dcs_settings = Settings::get('drupal_content_sync');
      if (!is_null($dcs_settings) && isset($dcs_settings[$sync_entity->id])) {
        $site_id = $dcs_settings[$sync_entity->id];
      }
    }
    if (!isset($config_machine_name)) {
      $config_machine_name = '<machine_name_of_the_configuration>';
    }

    $form['site_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site identifier'),
      '#maxlength' => 255,
      '#default_value' => isset($sync_entity->{'site_id'}) ? $sync_entity->{'site_id'} : '',
      '#description' => $this->t("This identifier will be used to identify the origin of entities on other sites and is used as a machine name for identification. 
      Once connected, you cannot change this identifier anylonger. Typicall you want to use the fully qualified domain name of this website as an identifier.<br>
      The Site identifier can be overridden within your environment specific settings.php file by using <i>@settings</i>.<br>
      If you do so, you should exclude the Site identifier for this configuration from the configuration import/export by using the module <a href='https://www.drupal.org/project/config_ignore' target='_blank'>Config ignore</a>.
      The exclude could for example look like this: <i>drupal_content_sync.sync.@config_machine_name:site_id</i><br>
      <i>Hint: If this configuration is saved before the value with the settings.php got set, you need to resave this configuration once the value within the settings.php got set.</i>", [
        '@settings' => '$settings["drupal_content_sync"]["' . $config_machine_name . '"] = "my-site-identifier"',
        '@config_machine_name' => $config_machine_name,
      ]),
      '#required' => TRUE,
    ];

    // If the site id is set within the settings.php, the form field is disabled.
    if (isset($site_id)) {
      $form['site_id']['#disabled'] = TRUE;
      $form['site_id']['#default_value'] = $site_id;
      $form['site_id']['#description'] = $this->t('Site identifier ist set with the environment specific settings.php file.');
    }

    $entity_types = $this->bundleInfoService->getAllBundleInfo();

    // Remove the Drupal Content Sync Meta Info entity type form the array.
    unset($entity_types['drupal_content_sync_meta_info']);

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
        $this->t('Import'),
        $this->t('Clone'),
        $this->t('Delete'),
        '',
        $this->t('Preview'),
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
        '#wrapper_attributes' => [
          'colspan' => count($entity_table['#header']),
        ],
      ];

      foreach ($entity_type as $entity_bundle_name => $entity_bundle) {
        $entity_bundle_row = [];

        $version = DrupalContentSync::getEntityTypeVersion($type_key, $entity_bundle_name);

        $current_display_mode = $type_key . '.' . $entity_bundle_name . '.' . self::DRUPAL_CONTENT_SYNC_PREVIEW_FIELD;
        $has_preview_mode = in_array($current_display_mode, $display_modes_ids) || $type_key == 'file';

        if (!isset($def_sync_entities[$type_key . '-' . $entity_bundle_name])) {
          $row_default_values = [
            'id' => $type_key . '-' . $entity_bundle_name,
            'export' => FALSE,
            'import' => NULL,
            'import_clone' => FALSE,
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
        if (!empty($input[$type_key . '-' . $entity_bundle_name])) {
          $row_default_values = array_merge($row_default_values, $input[$type_key . '-' . $entity_bundle_name]);
        }

        $entity_bundle_row['bundle'] = [
          '#markup' => $this->t('@bundle (@machine_name)', [
            '@bundle' => $entity_bundle['label'],
            '@machine_name' => $entity_bundle_name,
          ]) . '<br><small>version: ' . $version . '</small>' .
          (empty($row_default_values['version'])||$version == $row_default_values['version'] ? '' : '<br><strong>Changed from ' . $row_default_values['version'] . '</strong>'),
        ];

        $entity_bundle_row['id'] = [
          '#type' => 'textfield',
          '#default_value' => $row_default_values['id'],
          '#title' => $this->t('Identifier'),
          '#disabled' => TRUE,
          '#size' => 24,
          '#title_display' => 'invisible',
        ];

        $entity_handlers = $this->entityPluginManager->getHandlerOptions($type_key, $entity_bundle_name, TRUE);
        if (empty($entity_handlers)) {
          $handler_id = 'ignore';
          $entity_handlers = ['ignore' => $this->t('Not supported')->render()];
        }
        else {
          $entity_handlers = array_merge(['ignore' => $this->t('Ignore')->render()], $entity_handlers);
          $handler_id = empty($row_default_values['handler']) ? 'ignore' : $row_default_values['handler'];
        }

        $entity_bundle_row['handler'] = [
          '#type' => 'select',
          '#title' => $this->t('Handler'),
          '#title_display' => 'invisible',
          '#options' => $entity_handlers,
          '#disabled' => count($entity_handlers) < 2 && isset($entity_handlers['ignore']),
          '#default_value' => $handler_id,
          '#ajax' => [
            'callback' => '::updateSyncHandler',
            'wrapper' => 'sync-entities-table',
            'progress' => [
              'type' => 'throbber',
              'message' => "loading...",
            ],
          ],
        ];

        $handler = NULL;
        if ($handler_id == 'ignore') {
          $export_options = [
            DrupalContentSync::EXPORT_DISABLED => $this->t('Disabled')->render(),
          ];
        }
        else {
          $handler = $this->entityPluginManager->createInstance($handler_id, [
            'entity_type_name' => $type_key,
            'bundle_name' => $entity_bundle_name,
            'settings' => $row_default_values,
            'sync' => NULL,
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

        if ($handler) {
          $allowed_import_options = $handler->getAllowedImportOptions();
          $import_options = [];
          foreach ($allowed_import_options as $option) {
            $import_options[$option] = $import_option_labels[$option];
          }
        }
        else {
          $import_options = [
            DrupalContentSync::IMPORT_DISABLED => $this->t('Disabled')->render(),
          ];
        }

        $entity_bundle_row['import'] = [
          '#type' => 'select',
          '#title' => $this->t('Synchronized Import'),
          '#title_display' => 'invisible',
          '#options' => $import_options,
          '#default_value' => $row_default_values['import'],
        ];

        $entity_bundle_row['import_clone'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Clone'),
          '#default_value' => $row_default_values['import_clone'],
        ];

        $entity_bundle_row['delete_entity'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Delete'),
          '#default_value' => $row_default_values['delete_entity'] == 1,
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
          ], $handler_id == 'ignore' ? ['table' => $this->t('Table')->render()] : $handler->getAllowedPreviewOptions()),
          '#default_value' => $row_default_values['preview'],
        ];

        $entity_bundle_row['handler_settings'] = [
          '#markup' => '',
        ];
        if ($handler_id != 'ignore') {
          $advanced_settings = $handler->getHandlerSettings();
          if (count($advanced_settings)) {
            $entity_bundle_row['handler_settings'] = array_merge([
              '#type' => 'container',
            ], $advanced_settings);
          }
        }

        // Add row class.
        $entity_bundle_row['#attributes'] = ['class' => ['row-' . $entity_bundle_row['id']['#default_value']]];

        $entity_table[$type_key . '-' . $entity_bundle_name] = $entity_bundle_row;

        if ($handler_id != 'ignore') {
          $forbidden_fields = array_merge($handler->getForbiddenFields(),
            // These are standard fields defined by the DrupalContentSync
            // Entity type that entities may not override (otherwise
            // these fields will collide with DCS functionality)
            [
              'source',
              'source_id',
              'source_connection_id',
              'preview',
              'url',
              'apiu_translation',
              'metadata',
              'embed_entities',
              'title',
              'created',
              'changed',
              'uuid',
            ]);

          $entityFieldManager = $this->entityFieldManager;
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
                'import' => NULL,
                'import_clone' => FALSE,
                'preview' => NULL,
                'entity_type' => $type_key,
                'entity_bundle' => $entity_bundle_name,
                'delete_entity' => NULL,
              ];
            }
            else {
              $field_default_values = $def_sync_entities[$field_id];
            }
            if (!empty($input[$field_id])) {
              $field_default_values = array_merge($field_default_values, $input[$field_id]);
            }

            $field_row['id'] = [
              '#type' => 'textfield',
              '#default_value' => $field_default_values['id'],
              '#title' => $this->t('Identifier'),
              '#disabled' => TRUE,
              '#size' => 24,
              '#title_display' => 'invisible',
            ];

            if (in_array($key, $forbidden_fields) !== FALSE) {
              $handler_id = 'ignore';
              $field_handlers = [
                'ignore' => $this->t('Not configurable')->render(),
              ];
            }
            else {
              $field_handlers = $this->fieldPluginManager->getHandlerOptions($type_key, $entity_bundle_name, $key, $field, TRUE);
              if (empty($field_handlers)) {
                $handler_id = 'ignore';
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
              '#options' => count($field_handlers) ? array_merge(['ignore' => $this->t('Ignore')->render()], $field_handlers) : [
                'ignore' => $this->t('Not supported')->render(),
              ],
              '#disabled' => !count($field_handlers) || (count($field_handlers) == 1 && isset($field_handlers['ignore'])),
              '#default_value' => $handler_id,
              '#ajax' => [
                'callback' => '::updateSyncHandler',
                'wrapper' => 'sync-entities-table',
                'progress' => [
                  'type' => 'throbber',
                  'message' => "loading...",
                ],
              ],
            ];

            if ($handler_id == 'ignore') {
              $export_options = [
                DrupalContentSync::EXPORT_DISABLED => $this->t('No')->render(),
              ];
            }
            else {
              $handler = $this->fieldPluginManager->createInstance($handler_id, [
                'entity_type_name' => $type_key,
                'bundle_name' => $entity_bundle_name,
                'field_name' => $key,
                'field_definition' => $field,
                'settings' => $field_default_values,
                'sync' => NULL,
              ]);

              $allowed_export_options = $handler->getAllowedExportOptions();
              $export_options = [];
              foreach ($allowed_export_options as $option) {
                $export_options[$option] = $export_option_labels_fields[$option];
              }
            }

            $field_row['export'] = [
              '#type' => 'select',
              '#title' => $this->t('Export'),
              '#title_display' => 'invisible',
              '#disabled' => count($export_options) < 2,
              '#options' => $export_options,
              '#default_value' => $field_default_values['export'] ? $field_default_values['export'] : (isset($export_options[DrupalContentSync::EXPORT_AUTOMATICALLY]) ? DrupalContentSync::EXPORT_AUTOMATICALLY : DrupalContentSync::EXPORT_DISABLED),
            ];

            if ($handler_id == 'ignore') {
              $import_options = [
                DrupalContentSync::IMPORT_DISABLED => $this->t('No')->render(),
              ];
            }
            else {
              $allowed_import_options = $handler->getAllowedImportOptions();
              $import_options = [];
              foreach ($allowed_import_options as $option) {
                $import_options[$option] = $import_option_labels_fields[$option];
              }
            }
            $field_row['import'] = [
              '#type' => 'select',
              '#title' => $this->t('Import'),
              '#title_display' => 'invisible',
              '#options' => $import_options,
              '#disabled' => count($import_options) < 2,
              '#default_value' => $field_default_values['import'] ? $field_default_values['import'] : (isset($import_options[DrupalContentSync::IMPORT_AUTOMATICALLY]) ? DrupalContentSync::IMPORT_AUTOMATICALLY : DrupalContentSync::IMPORT_DISABLED),
            ];
            $field_row['import_clone'] = [
              '#markup' => '',
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

            $field_row['handler_settings'] = [
              '#markup' => '',
            ];
            if ($handler_id != 'ignore') {
              $advanced_settings = $handler->getHandlerSettings($field_default_values);
              if (count($advanced_settings)) {
                $field_row['handler_settings'] = array_merge([
                  '#type' => 'container',
                ], $advanced_settings);
              }
            }

            $field_row['#attributes'] = ['class' => ['row-' . $entity_bundle_row['id']['#default_value']]];
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
   * Disable form elements which are overridden.
   *
   * @param array $form
   */
  private function disableOverridenConfigs(array &$form) {
    global $config;
    $config_name = 'drupal_content_sync.drupal_content_sync.' . $form['id']['#default_value'];

    // If the default overrides aren't used check if a
    // master / subsite setting is used.
    if (!isset($config[$config_name]) || empty($config[$config_name])) {
      // Is this site a master site? It is a subsite by default.
      $environment = 'subsite';
      if ($this->configFactory->get('config_split.config_split.drupal_content_sync_master')->get('status')) {
        $environment = 'master';
      }
      $config_name = 'drupal_content_sync.sync.' . $environment;
    }
    $fields = Element::children($form);
    foreach ($fields as $field_key) {
      if ($this->configIsOverridden($field_key, $config_name)) {
        $form[$field_key]['#disabled'] = 'disabled';
        $form[$field_key]['#value'] = $this->configFactory->get($config_name)->get($field_key);
        unset($form[$field_key]['#default_value']);
      }
    }
  }

  /**
   * Validate format of input fields and make sure the API Unify backend is
   * accessible to actually update it.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $api = $form_state->getValue('api');
    if (!preg_match('@^([a-z0-9\-]+)$@', $api)) {
      $form_state->setErrorByName('api', $this->t('Please only use letters, numbers and dashes.'));
    }
    if ($api == 'drupal' || $api == 'api-unify') {
      $form_state->setErrorByName('api', $this->t('This name is reserved.'));
    }

    $site_id = $form_state->getValue('site_id');
    if ($site_id == ApiUnifyConfig::POOL_SITE_ID) {
      $form_state->setErrorByName('site_id', $this->t('This name is reserved.'));
    }

    $url    = $form_state->getValue('url');
    $client = $this->httpClient;
    try {
      $response = $client->get($url . '/status');
      if ($response->getStatusCode() != 200) {
        $form_state->setErrorByName('url', $this->t('The backend did not respond with 200 OK. Please ask your technical contact person for support.'));
      }
    }
    catch (\Exception $e) {
      $form_state->setErrorByName('url', $this->t('The backend did not respond with 200 OK. Please ask your technical contact person for support. The error messages is @message', ['@message' => $e->getMessage()]));
    }
  }

  /**
   * Check if a config is overridden.
   *
   * Right now it only checks if the config is in the $config-array (overridden
   * by the settings.php)
   *
   * @TODO take care of overriding by modules and languages
   *
   * @param string $config_key
   *   The configuration key.
   * @param string $config_name
   *   The configuration name.
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

    $sync_entities = &$config->{'sync_entities'};
    foreach ($sync_entities as $key => $bundle_fields) {
      // Ignore field settings.
      if (substr_count($key, '-') != 1) {
        continue;
      }

      preg_match('/^(.+)-(.+)$/', $key, $matches);

      $type_key = $matches[1];
      $bundle_key = $matches[2];

      $sync_entities[$key]['version'] = DrupalContentSync::getEntityTypeVersion($type_key, $bundle_key);
      $sync_entities[$key]['entity_type_name'] = $type_key;
      $sync_entities[$key]['bundle_name'] = $bundle_key;
    }

    $is_new = !$this->exists($config->id());
    $status = $config->save();

    if ($status) {
      $this->messenger->addMessage($this->t('Saved the %label Drupal Content Synchronization.', [
        '%label' => $config->label(),
      ]));
      $uri = 'internal:/admin/content/drupal_content_synchronization/' . $this->entity->id();
      $link_data = [
        'link' => ['uri' => $uri],
        'title' => $this->entity->label(),
        'menu_name' => 'admin',
        'parent' => 'system.admin_content',
      ];
      if ($is_new) {
        // @ToDo: Needs to be refactored - "Manual Import Dashboard".
        // $item = MenuLinkContent::create($link_data);
        // $item->save();
        // menu_cache_clear_all();
      }
      else {
        $links = $this->entityTypeManager->getStorage('menu_link_content')->loadByProperties(['link__uri' => $uri]);

        if ($link = reset($links)) {
          $link->set('title', $this->entity->label());
        }
        else {
          $link = MenuLinkContent::create($link_data);
        }
        $link->save();
        menu_cache_clear_all();
      }
    }
    else {
      $this->messenger->addMessage($this->t('The %label Drupal Content Synchronization was not saved.', [
        '%label' => $config->label(),
      ]));
    }

    $form_state->setRedirect('entity.drupal_content_sync.collection');
  }

  /**
   * Check if the entity exists.
   *
   * A helper function to check whether an
   * DrupalContentSync configuration entity exists.
   *
   * @param int $id
   *   An ID of sync.
   *
   * @return bool
   *   Checking on exist an entity.
   */
  public function exists($id) {
    $entity = $this->entityTypeManager
      ->getStorage('drupal_content_sync')
      ->getQuery()
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

}
