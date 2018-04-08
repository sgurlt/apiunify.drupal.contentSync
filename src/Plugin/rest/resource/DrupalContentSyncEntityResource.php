<?php

namespace Drupal\drupal_content_sync\Plugin\rest\resource;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;
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
   * @const ENTITY_HAS_NOT_BEEN_FOUND
   */
  const FILE_INPUT_DATA_IS_INVALID = 'The entity data of the file object is invalid.';

  /**
   * @const CODE_NOT_FOUND
   */
  const CODE_INVALID_DATA = 401;

  /**
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $entityTypeBundleInfo;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderedManager;

  /**
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
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
   * @param \Drupal\Core\Entity\EntityTypeBundleInfo $entity_type_bundle_info
   *   An entity type bundle info instance.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   An entity type manager instance.
   * @param \Drupal\Core\Render\Renderer $render_manager
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
   * @ToDo: Add description.
   */
  protected function getHandlerForEntityType($config) {
    $entityPluginManager = \Drupal::service('plugin.manager.dcs_entity_handler');

    return $entityPluginManager->createInstance($config['handler']);
  }

  /**
   * @ToDo: Add description.
   */
  protected function getConfigForEntityType($entity_type_name, $entity_bundle) {
    $entities = _drupal_content_sync_get_synchronization_configurations();

    foreach ($entities as $entity) {
      $config = json_decode($entity->{'sync_entities'}, TRUE);
      if (empty($config[$entity_type_name . '-' . $entity_bundle])) {
        continue;
      }

      if ($config[$entity_type_name . '-' . $entity_bundle]['handler'] == DrupalContentSync::HANDLER_IGNORE) {
        continue;
      }

      return $config;
    }

    return NULL;
  }

  /**
   * Responds to entity GET requests.
   *
   * @param string $entity_type
   *   The name of an entity type.
   *
   * @param string $entity_bundle
   *   The name of an entity bundle.
   *
   * @param string $entity_uuid
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

      // Trying to find site ID.
      $drupal_content_syncs = _drupal_content_sync_get_synchronization_configurations();

      $sync = FALSE;
      foreach ($drupal_content_syncs as $sync) {
        $sync_entities = json_decode($sync->sync_entities, TRUE);
        $entity_key = "$entity_type-$entity_bundle";
        if (!empty($sync_entities[$entity_key]['export'])) {
          break;
        }
      }

      foreach ($entities as &$entity) {
        $entity = _drupal_content_sync_preprocess_entity($entity, $entity_type, $entity_bundle, $sync, TRUE);
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
   * @param string $entity_type
   *   The name of an entity type.
   *
   * @param string $entity_bundle
   *   The name of an entity bundle.
   *
   * @param string $entity_uuid
   *   The uuid of an entity.
   *
   * @param array $data
   *   The data to be stored in the entity.
   *
   * @return Response
   *   A list of entities of the given type and bundle.
   */
  public function patch($entity_type_name, $entity_bundle, $entity_uuid, $data) {
    return $this->handleIncomingEntity($entity_type_name, $entity_bundle, $data, $entity_uuid);
  }

  /**
   * Responds to entity DELETE requests.
   *
   * @param string $entity_type
   *   The name of an entity type.
   *
   * @param string $entity_bundle
   *   The name of an entity bundle.
   *
   * @param string $entity_uuid
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
   * @param string $entity_type_name
   *   The name of an entity type.
   *
   * @param string $entity_bundle
   *   The name of an entity bundle.
   *
   * @param array $data
   *   The data to be stored in the entity.
   *
   * @return Response
   *   A list of entities of the given type and bundle.
   */
  public function post($entity_type_name, $entity_bundle, $data) {
    return $this->handleIncomingEntity($entity_type_name, $entity_bundle, $data);
  }

  /**
   * @ToDo: Add description.
   */
  private function handleIncomingEntity($entity_type_name, $entity_bundle, $data, $uuid = FALSE) {
    if (!$this->isSyncAllowed($entity_type_name, $entity_bundle, $data)) {
      return new ModifiedResourceResponse($data);
    }

    $is_clone = isset($_GET['is_clone']) && $_GET['is_clone'] == 'true';
    $entity_types = $this->entityTypeBundleInfo->getAllBundleInfo();

    if (empty($entity_types[$entity_type_name])) {
      return new ResourceResponse(
        ['message' => t(self::TYPE_HAS_NOT_BEEN_FOUND)], self::CODE_NOT_FOUND
      );
    }

    $config = $this->getConfigForEntityType($entity_type_name, $entity_bundle);
    if (empty($config)) {
      return new ResourceResponse(
        ['message' => t(self::TYPE_HAS_NOT_BEEN_FOUND)], self::CODE_NOT_FOUND
      );
    }

    $handler = $this->getHandlerForEntityType($config[$entity_type_name . '-' . $entity_bundle]);

    $storage = \Drupal::entityTypeManager()
      ->getStorage($entity_type_name);
    $entity_type = $storage->getEntityType();
    $entity_data = [
      $entity_type->getKey('bundle') => $entity_bundle,
    ];

    if (!$is_clone) {
      $entity_data[$entity_type->getKey('uuid')] = $data[$entity_type->getKey('uuid')];
    }

    if (!$uuid) {
      $uuid = $data[$entity_type->getKey('uuid')];
    }

    $entity = $this->entityRepository->loadEntityByUuid($entity_type_name, $uuid);

    if ($is_clone || !$entity) {
      $entity = $handler->createEntity($config, $entity_type_name, $entity_bundle, $entity_data, $data, $is_clone);
      if (!$entity) {
        return new ResourceResponse(
          ['message' => t(self::FILE_INPUT_DATA_IS_INVALID)], self::CODE_INVALID_DATA
        );
      }
    }
    else {
      $handler->updateEntity($config, $entity, $data);
    }

    return new ModifiedResourceResponse($data);
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
