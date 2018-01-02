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
}
