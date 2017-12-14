<?php

namespace Drupal\drupal_content_sync\Plugin\rest\resource;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManager;
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
      $entity_ids = array_values($query->execute());

      $entities = array_values(\Drupal::entityTypeManager()->getStorage($entity_type)->loadMultiple($entity_ids));

      $site_id = '';

      // Trying to find site ID.
      $drupal_content_syncs = $this->entityTypeManager
        ->getStorage('drupal_content_sync')
        ->loadMultiple();

      foreach ($drupal_content_syncs as $sync) {
        $sync_entities = json_decode($sync->sync_entities, TRUE);
        $entity_key = "$entity_type-$entity_bundle";
        if (!empty($sync_entities[$entity_key]['export'])) {
          $site_id = $sync->site_id;
          break;
        }
      }

      foreach($entities as &$entity) {
        if ($entity_type == 'file' && $entity->isTemporary()) {
          continue;
        }
        $entity = _drupal_content_sync_preprocess_entity($entity, $entity_type, $entity_bundle, $site_id, true);
      }

      if (!empty($entity_uuid)) {
        $entities = $entities[0];
      }

      $resource_response = new ResourceResponse($entities);

      $cache_build = [
        '#cache' => [
          'max-age' => 0,
        ],
      ];
      $resource_response->addCacheableDependency($cache_build);

      return $resource_response;
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
   * @return \Drupal\rest\ResourceResponse
   *   A list of entities of the given type and bundle.
   */
  public function patch($entity_type, $entity_bundle, $entity_uuid, $data) {
    $entity_types = $this->entityTypeBundleInfo->getAllBundleInfo();

    $entity_types_keys = array_keys($entity_types);
    if (in_array($entity_type, $entity_types_keys)) {
      $query = \Drupal::entityQuery($entity_type);
      $query->condition('type', $entity_bundle);
      $query->condition('uuid', $entity_uuid);
      $entity_ids = array_values($query->execute());

      if ($entity_ids) {
        $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load(reset($entity_ids));
        $this->setEntityValues($entity, $data);
      }
      else {
        $entity = NULL;
      }

      $resource_response = new ResourceResponse($entity);

      $cache_build = [
        '#cache' => [
          'max-age' => 0,
        ],
      ];
      $resource_response->addCacheableDependency($cache_build);
      return $resource_response;
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
   * @return \Drupal\rest\ResourceResponse
   *   A list of entities of the given type and bundle.
   */
  public function post($entity_type_name, $entity_bundle, $data) {
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

      if ($entity_type_name == 'file') {
        file_prepare_directory(\Drupal::service('file_system')->dirname($data['uri']), FILE_CREATE_DIRECTORY);
        $entity = file_save_data(base64_decode($data['apiu_file_content']), $data['uri']);
      } else {
        $entity = $storage->create($entity_data);
      }
      $this->setEntityValues($entity, $data, !$is_clone);

      $resource_response = new ResourceResponse($data);

      $cache_build = [
        '#cache' => [
          'max-age' => 0,
        ],
      ];
      $resource_response->addCacheableDependency($cache_build);
      return $resource_response;
    }

    return new ResourceResponse(
      ['message' => t(self::TYPE_HAS_NOT_BEEN_FOUND)], self::CODE_NOT_FOUND
    );
  }

  private function setEntityValues($entity, $data, $set_synced = TRUE) {
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $type = $entity->getEntityTypeId();
    $bundle = method_exists($entity, 'getType') ? $entity->getType() : $type;
    $field_definitions = $entityFieldManager->getFieldDefinitions($type, $bundle);

    $fields_to_ignore = ['nid', 'id', 'uuid', 'vid', 'field_drupal_content_synced', 'uri', 'apiu_file_content', 'apiu_translation'];
    $fields = array_diff(array_keys($field_definitions), $fields_to_ignore);

    foreach ($fields as $key) {
      if (in_array($key, $fields_to_ignore)) {
        continue;
      }

      switch ($field_definitions[$key]->getType()) {
        case 'text_with_summary':
          if (isset($data[$key])) {
            $entity->set($key, array(
              'value' => $data[$key],
              'summary' => $data[$key . '_summary'],
              'format' => $data[$key . '_format'],
            ));
          }
          break;

        case 'image':
          break;

        case 'entity_reference':
          if (empty($data[$key . '_uuid']) || empty($data[$key . '_type'])) {
            continue;
          }

          try {
            $reference = $this->entityRepository->loadEntityByUuid($data[$key . '_type'], $data[$key . '_uuid']);
            if ($reference) {
              $entity->set($key, $reference->id());
            }
          }
          catch (Exception $exception) {
          }
          break;

        default:
          if (isset($data[$key])) {
            $entity->set($key, $data[$key]);
          }
      }
    }

    if ($set_synced) {
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
}
