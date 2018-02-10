<?php

namespace Drupal\drupal_content_sync\Plugin\rest\resource;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\field_collection\Entity\FieldCollectionItem;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\Core\Render\Renderer;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides entity interfaces for Drupal Content Sync.
 *
 * @RestResource(
 *   id = "drupal_content_sync_entity_resource",
 *   label = @Translation("DrupalContentSync Entity Resource"),
 *   uri_paths = {
 *     "canonical" = "/drupal_content_sync_entity_resource/{entity_type}/{entity_bundle}/{entity_uuid}",
 *     "https://www.drupal.org/link-relations/create" = "/drupal_content_sync_entity_resource/{entity_type}/{entity_bundle}"
 *   }
 * )
 */
class DrupalContentSyncEntityResource extends ResourceBase {

  /**
   * @const ENTITY_HAS_NOT_BEEN_FOUND
   */
  const TYPE_HAS_NOT_BEEN_FOUND = 'The entity type has not been found.';

  /**
   * @const CODE_NOT_FOUND
   */
  const CODE_NOT_FOUND = 404;

  /**
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo $entityTypeBundleInfo
   */
  protected $entityTypeBundleInfo;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Render\Renderer $renderedManager
   */
  protected $renderedManager;

  /**
   * @var EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs an object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param EntityTypeBundleInfo $entity_type_bundle_info
   *   An entity type bundle info instance.
   * @param EntityTypeManager $entity_type_manager
   *   An entity type manager instance.
   * @param Renderer $render_manager
   *   A rendered instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    EntityTypeBundleInfo $entity_type_bundle_info,
    EntityTypeManager $entity_type_manager,
    Renderer $render_manager,
    EntityRepositoryInterface $entity_repository
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $serializer_formats,
      $logger
    );

    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityTypeManager = $entity_type_manager;
    $this->renderedManager = $render_manager;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('entity.repository')
    );
  }

  /**
   * Responds to entity GET requests.
   *
   * @param $entity_type string
   *   The name of an entity type.
   *
   * @param $entity_bundle string
   *   The name of an entity bundle.
   *
   * @param $entity_uuid string
   *   The uuid of an entity.
   *
   * @return \Drupal\rest\ResourceResponse
   *   A list of entities of the given type and bundle.
   */
  public function get($entity_type, $entity_bundle, $entity_uuid) {
    $entity_types = $this->entityTypeBundleInfo->getAllBundleInfo();

    $entity_types_keys = array_keys($entity_types);
    if (in_array($entity_type, $entity_types_keys)) {
      $entity_type_entity = \Drupal::entityTypeManager()
        ->getStorage($entity_type)->getEntityType();

      $query = \Drupal::entityQuery($entity_type);
      if ($bundle = $entity_type_entity->getKey('bundle')) {
        $query->condition($bundle, $entity_bundle);
      }
      if (!empty($entity_uuid)) {
        $query->condition('uuid', $entity_uuid);
      }

      if ($entity_type == 'file') {
        $query->condition('status', FILE_STATUS_PERMANENT);
      }

      $entity_ids = array_values($query->execute());

      $entities = array_values(\Drupal::entityTypeManager()->getStorage($entity_type)->loadMultiple($entity_ids));

      $site_id = '';

      // Trying to find site ID.
      $drupal_content_syncs = $this->entityTypeManager
        ->getStorage('drupal_content_sync')
        ->loadMultiple();

      $sync = false;
      foreach ($drupal_content_syncs as $sync) {
        $sync_entities = json_decode($sync->sync_entities, TRUE);
        $entity_key = "$entity_type-$entity_bundle";
        if (!empty($sync_entities[$entity_key]['export'])) {
          break;
        }
      }

      foreach($entities as &$entity) {
        $entity = _drupal_content_sync_preprocess_entity($entity, $entity_type, $entity_bundle, $sync, true);
      }

      if (!empty($entity_uuid)) {
        $entities = $entities[0];
      }

      return new ModifiedResourceResponse($entities);
    }

    return new ResourceResponse(
      ['message' => t(self::TYPE_HAS_NOT_BEEN_FOUND)], self::CODE_NOT_FOUND
    );

  }

  /**
   * Responds to entity PATCH requests.
   *
   * @param $entity_type string
   *   The name of an entity type.
   *
   * @param $entity_bundle string
   *   The name of an entity bundle.
   *
   * @param $entity_uuid string
   *   The uuid of an entity.
   *
   * @param $data array
   *   The data to be stored in the entity.
   *
   * @return Response
   *   A list of entities of the given type and bundle.
   */
  public function patch($entity_type, $entity_bundle, $entity_uuid, $data) {
    if (!$this->isSyncAllowed($entity_type, $entity_bundle, $data)) {
      return new ModifiedResourceResponse($data);
    }

    $entity_type_entity = \Drupal::entityTypeManager()->getStorage($entity_type)->getEntityType();

    $entity_types = $this->entityTypeBundleInfo->getAllBundleInfo();

    $entity_types_keys = array_keys($entity_types);
    if (in_array($entity_type, $entity_types_keys)) {
      $query = \Drupal::entityQuery($entity_type);
      $query->condition($entity_type_entity->getKey('bundle'), $entity_bundle);
      $query->condition('uuid', $entity_uuid);
      $entity_ids = array_values($query->execute());

      if ($entity_ids) {
        $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load(reset($entity_ids));
        $this->setEntityValues($entity, $data);
      }
      else {
        $entity = NULL;
      }

      return new ModifiedResourceResponse($data);
    }

  }

  /**
   * Responds to entity DELETE requests.
   *
   * @param $entity_type string
   *   The name of an entity type.
   *
   * @param $entity_bundle string
   *   The name of an entity bundle.
   *
   * @param $entity_uuid string
   *   The uuid of an entity.
   *
   * @return \Drupal\rest\ResourceResponse
   *   A list of entities of the given type and bundle.
   */
  public function delete($entity_type, $entity_bundle, $entity_uuid) {
    $entity_types = $this->entityTypeBundleInfo->getAllBundleInfo();

    $entity_types_keys = array_keys($entity_types);
    if (in_array($entity_type, $entity_types_keys)) {
      $query = \Drupal::entityQuery($entity_type);
      $query->condition('type', $entity_bundle);
      $query->condition('uuid', $entity_uuid);
      $entity_ids = array_values($query->execute());
      $entities = array_values(\Drupal::entityTypeManager()->getStorage($entity_type)->loadMultiple($entity_ids));

      if (isset($entities[0])) {
        $entities[0]->delete();
        $this->logger->notice('Deleted entity %type with ID %id.', ['%type' => $entity_type, '%id' => $entity_ids[0]]);
      }

      // DELETE responses have an empty body.
      return new ModifiedResourceResponse(NULL, 204);
    }

    return new ResourceResponse(
      ['message' => t(self::TYPE_HAS_NOT_BEEN_FOUND)], self::CODE_NOT_FOUND
    );

  }


  /**
   * Responds to entity POST requests.
   *
   * @param $entity_type_name string
   *   The name of an entity type.
   *
   * @param $entity_bundle string
   *   The name of an entity bundle.
   *
   * @param $data array
   *   The data to be stored in the entity.
   *
   * @return Response
   *   A list of entities of the given type and bundle.
   */
  public function post($entity_type_name, $entity_bundle, $data) {
    if (!$this->isSyncAllowed($entity_type_name, $entity_bundle, $data)) {
      return new ModifiedResourceResponse($data);
    }

    $is_clone = isset($_GET['is_clone']) && $_GET['is_clone'] == 'true';
    $entity_types = $this->entityTypeBundleInfo->getAllBundleInfo();

    $entity_types_keys = array_keys($entity_types);
    if (in_array($entity_type_name, $entity_types_keys)) {
      $storage = \Drupal::entityTypeManager()
        ->getStorage($entity_type_name);
      $entity_type = $storage->getEntityType();
      $entity_data = [
        $entity_type->getKey('bundle') => $entity_bundle,
      ];

      if (!$is_clone) {
        $entity_data[$entity_type->getKey('uuid')] = $data[$entity_type->getKey('uuid')];
      }

      $uuid = $data[$entity_type->getKey('uuid')];

      if ($is_clone || !$this->entityRepository->loadEntityByUuid($entity_type_name, $uuid)) {
        if ($entity_type_name == 'file') {
          if (!empty($data['uri'][0]['value'])) {
            $data['uri'] = $data['uri'][0]['value'];
          }

          $was_prepared = file_prepare_directory(\Drupal::service('file_system')->dirname($data['uri']), FILE_CREATE_DIRECTORY);

          if ($was_prepared && !empty($data['apiu_file_content'])) {
            $entity = file_save_data(base64_decode($data['apiu_file_content']), $data['uri']);
            $entity->setPermanent();
            $entity->set('uuid', $data['uuid']);
            $entity->save();
          }
        } else {
          $entity = $storage->create($entity_data);
          $this->setEntityValues($entity, $data, $is_clone);
        }
      }

      return new ModifiedResourceResponse($data);
    }

    return new ResourceResponse(
      ['message' => t(self::TYPE_HAS_NOT_BEEN_FOUND)], self::CODE_NOT_FOUND
    );
  }

  private function setEntityValues(EntityInterface $entity, $data, $is_clone = FALSE) {
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $field_definitions = $entityFieldManager->getFieldDefinitions($type, $bundle);

    $fields_to_ignore = ['item_id', 'field_name', 'tid', 'nid', 'id', 'uuid', 'vid', 'field_drupal_content_synced', 'uri', 'apiu_file_content', 'apiu_translation', 'revision_id'];

    $fields = array_diff(array_keys($field_definitions), $fields_to_ignore);

    foreach ($fields as $key) {
      switch ($field_definitions[$key]->getType()) {
        case 'entity_reference_revisions':
        case 'entity_reference':
          if (empty($data[$key]) || !is_array($data[$key])) {
            break;
          }

          $reference_ids = [];
          foreach ($data[$key] as $value) {
            if (!isset($value['uuid'], $value['type'])) {
              continue;
            }

            try {
              $reference = $this->entityRepository->loadEntityByUuid($value['type'], $value['uuid']);
              if ($reference) {
                $reference_data = [
                  'target_id' => $reference->id(),
                ];

                if ($reference instanceof RevisionableInterface) {
                  $reference_data['target_revision_id'] = $reference->getRevisionId();
                }

                $reference_ids[] = $reference_data;
              }
            }
            catch (\Exception $exception) {
            }

            $entity->set($key, $reference_ids);
          }
          break;

        case 'file';
        case 'image':
          $file_ids = [];
          foreach ($data[$key] as $value) {
            $dirname = \Drupal::service('file_system')->dirname($value['file_uri']);
            file_prepare_directory($dirname, FILE_CREATE_DIRECTORY);
            $file = file_save_data(base64_decode($value['file_content']), $value['file_uri']);
            $file->setPermanent();
            $file->save();

            $file_ids[] = $file->id();
          }

          $entity->set($key, $file_ids);

          break;

        case 'field_collection':
          if (!$entity->id()) {
            $entity->save();
          }

          foreach ($data[$key] as $items) {
            $fc = FieldCollectionItem::create(['field_name' => 'field_fc_teaser']);

            $original_fields = $fc->getFields();

            foreach ($items as $item_key => $item_value) {
              if (!in_array($item_key, $fields_to_ignore)) {
                if (array_key_exists($item_key, $original_fields)) {
                  $fc_value = reset($item_value);

                  if (isset($fc_value['type'], $fc_value['uuid']))
                  try {
                    $reference = $this->entityRepository->loadEntityByUuid($fc_value['type'], $fc_value['uuid']);

                    if ($reference) {
                      $reference_data = [
                        'target_id' => $reference->id(),
                      ];

                      if ($reference instanceof RevisionableInterface) {
                        $reference_data['target_revision_id'] = $reference->getRevisionId();
                      }

                      $fc_value = [$reference_data];
                    }
                  }
                  catch (\Exception $exception) {
                  }

                  $fc->$item_key->setValue($fc_value);
                }
              }
            }

            $fc->setHostEntity($entity);
          }
          break;

        case 'link':
          if (!isset($data[$key])) {
            continue;
          }
          foreach ($data[$key] as &$link_element) {
            $uri = &$link_element['uri'];
            // Find the linked entity and replace it's id with the UUID
            // References have following pattern: entity:entity_type/entity_id
            preg_match('/^entity:(.*)\/(.*)$/', $uri, $found);
            if (!empty($found)) {
              $link_entity_type = $found[1];
              $link_entity_uuid = $found[2];
              $link_entity = $this->entityRepository->loadEntityByUuid($link_entity_type, $link_entity_uuid);
              if ($link_entity) {
                $uri = 'entity:' . $link_entity_type . '/' . $link_entity->id();
              }
            }
          }
          $entity->set($key, $data[$key]);

          break;

        default:
          if (isset($data[$key])) {
            $entity->set($key, $data[$key]);
          }
          break;
      }
    }

    if (!$is_clone && $entity->hasField('field_drupal_content_synced')) {
      $entity->set('field_drupal_content_synced', TRUE);
    }

    \Drupal::moduleHandler()->alter('drupal_content_sync_set_entity_values', $entity, $data);
    $entity->save();
    if (!empty($data['apiu_translation'])) {
      foreach($data['apiu_translation'] as $language => $translation_data) {
        if ($entity->hasTranslation($language)) {
          $translation = $entity->getTranslation($language);
        } else {
          $translation = $entity->addTranslation($language);
        }
        $this->setEntityValues($translation, $translation_data);
      }
    }
  }

  /**
   * Responds to entity POST requests.
   *
   * @param string $entity_type_name
   *   The name of an entity type.
   * @param string $entity_bundle
   *   The name of an entity bundle.
   * @param array $data
   *   The data to be stored in the entity.
   *
   * @return bool
   *   A list of entities of the given type and bundle.
   */
  protected function isSyncAllowed($entity_type_name, $entity_bundle, $data) {
    $hook_args = [$entity_type_name, $entity_bundle, $data];
    $is_allowed = \Drupal::moduleHandler()->invokeAll('drupal_content_sync_is_sync_allowed', $hook_args);
    return !in_array(FALSE, $is_allowed, TRUE);
  }

}
