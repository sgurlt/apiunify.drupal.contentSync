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
 *     "canonical" = "/drupal_content_sync_entity_resource/{api}/{entity_type}/{entity_bundle}/{entity_type_version}/{entity_uuid}",
 *     "https://www.drupal.org/link-relations/create" = "/drupal_content_sync_entity_resource/{api}/{entity_type}/{entity_bundle}/{entity_type_version}"
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
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository interface.
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
   * Responds to entity GET requests.
   *
   * @param string $entity_type
   *   The name of an entity type.
   * @param string $entity_bundle
   *   The name of an entity bundle.
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

      foreach ($entities as &$entity) {
        $sync   = DrupalContentSync::getExportSynchronizationForEntity($entity, DrupalContentSync::EXPORT_AUTOMATICALLY);
        $entity = $sync->getSerializedEntity($entity, DrupalContentSync::EXPORT_AUTOMATICALLY);
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
   * @param string $api
   *   Describe $api @ToDo.
   * @param string $entity_type
   *   The name of an entity type.
   * @param string $entity_bundle
   *   The name of an entity bundle.
   * @param string $entity_uuid
   *   The uuid of an entity.
   * @param string $entity_uuid
   * @param array $data
   *   The data to be stored in the entity.
   *
   * @return Response
   *   A list of entities of the given type and bundle.
   */
  public function patch($api, $entity_type, $entity_bundle, $entity_type_version, $entity_uuid, array $data) {
    return $this->handleIncomingEntity($api, $entity_type, $entity_bundle, $entity_type_version, $data, DrupalContentSync::ACTION_UPDATE);
  }

  /**
   * Responds to entity DELETE requests.
   *
   * @param string $api
   *   Describe $api @ToDo.
   * @param string $entity_type
   *   The name of an entity type.
   * @param string $entity_bundle
   *   The name of an entity bundle.
   * @param string $entity_uuid
   * @param string $entity_uuid
   *   The uuid of an entity.
   *
   * @return \Drupal\rest\ResourceResponse
   *   A list of entities of the given type and bundle.
   */
  public function delete($api, $entity_type, $entity_bundle, $entity_type_version) {
    return $this->handleIncomingEntity($api, $entity_type, $entity_bundle, $entity_type_version, ['uuid'=>$entity_uuid], DrupalContentSync::ACTION_DELETE);
  }

  /**
   * Responds to entity POST requests.
   *
   * @param string $api
   *   Describe $api @ToDo.
   * @param string $entity_type
   *   Describe $entity_type @ToDo.
   * @param string $entity_bundle
   *   The name of an entity bundle.
   * @param string $entity_type_version
   *   Describe $entity_type_version @ToDo.
   * @param array $data
   *   The data to be stored in the entity.
   *
   * @return Response
   *   A list of entities of the given type and bundle.
   */
  public function post($api, $entity_type, $entity_bundle, $entity_type_version, array $data) {
    return $this->handleIncomingEntity($api, $entity_type, $entity_bundle, $entity_type_version, $data, DrupalContentSync::ACTION_CREATE);
  }

  /**
   * @ToDo: Add description.
   */
  private function handleIncomingEntity($api, $entity_type_name, $entity_bundle, $entity_type_version, $data, $action) {
    $entity_types = $this->entityTypeBundleInfo->getAllBundleInfo();

    if (empty($entity_types[$entity_type_name])) {
      return new ResourceResponse(
        ['message' => t(self::TYPE_HAS_NOT_BEEN_FOUND)], self::CODE_NOT_FOUND
      );
    }

    $is_dependency = isset($_GET['is_dependency']) && $_GET['is_dependency'] == 'true';
    $is_clone      = isset($_GET['is_clone']) && $_GET['is_clone'] == 'true';
    $reason        = $is_dependency ? DrupalContentSync::IMPORT_AS_DEPENDENCY : DrupalContentSync::IMPORT_AUTOMATICALLY;

    $sync = DrupalContentSync::getImportSynchronizationForApiAndEntityType($api, $entity_type_name, $entity_bundle, $reason, $is_clone);
    if (empty($sync)) {
      return new ResourceResponse(
        ['message' => t(self::TYPE_HAS_NOT_BEEN_FOUND)], self::CODE_NOT_FOUND
      );
    }

    try {
      $status = $sync->importEntity(
        $entity_type_name,
        $entity_bundle,
        $data,
        $is_clone,
        $reason,
        $action
      );
    }
    catch (\Exception $e) {
      return new ResourceResponse(
        [
          'message' => t('SyncException @code',
            [
              '@code' => $e->errorCode,
            ]
          ),
          'code' => $e->errorCode,
        ], 500
      );
    }

    if ($status) {
      return new ModifiedResourceResponse($data, $action == DrupalContentSync::ACTION_DELETE ? 204 : 200);
    }
    else {
      return new ResourceResponse(
        ['message' => t(self::FILE_INPUT_DATA_IS_INVALID)], self::CODE_INVALID_DATA
      );
    }
  }

}
