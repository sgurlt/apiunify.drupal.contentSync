<?php

namespace Drupal\drupal_content_sync\Plugin\rest\resource;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;
use Drupal\drupal_content_sync\Exception\SyncException;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\Core\Render\Renderer;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides entity interfaces for Drupal Content Sync, allowing API Unify to
 * request and manipulate entities.
 *
 * @RestResource(
 *   id = "drupal_content_sync_entity_resource",
 *   label = @Translation("DrupalContentSync Entity Resource"),
 *   uri_paths = {
 *     "canonical" = "/rest/dcs/{api}/{entity_type}/{entity_bundle}/{entity_type_version}/{entity_uuid}",
 *     "https://www.drupal.org/link-relations/create" = "/rest/dcs/{api}/{entity_type}/{entity_bundle}/{entity_type_version}"
 *   }
 * )
 */
class DrupalContentSyncEntityResource extends ResourceBase {

  /**
   * @var int CODE_INVALID_DATA The provided data could not be interpreted.
   */
  const CODE_INVALID_DATA = 401;

  /**
   * @var int CODE_NOT_FOUND The entity doesn't exist or can't be accessed
   */
  const CODE_NOT_FOUND = 404;

  /**
   * @var string TYPE_HAS_NOT_BEEN_FOUND
   *    The entity type doesn't exist or can't be accessed
   */
  const TYPE_HAS_NOT_BEEN_FOUND = 'The entity type has not been found.';

  /**
   * @var string TYPE_HAS_INCOMPATIBLE_VERSION The version hashes are different
   */
  const TYPE_HAS_INCOMPATIBLE_VERSION = 'The entity type has an incompatible version.';

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
   * Responds to entity GET requests.
   *
   * @param string $entity_type
   *   The name of an entity type.
   * @param string $entity_bundle
   *   The name of an entity bundle.
   * @param string $entity_uuid
   *   The uuid of an entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A list of entities of the given type and bundle.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\drupal_content_sync\Exception\SyncException
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
      $items    = [];

      foreach ($entities as $entity) {
        $sync   = DrupalContentSync::getExportSynchronizationForEntity($entity, DrupalContentSync::EXPORT_AUTOMATICALLY);
        $result = [];
        $status = $sync->getSerializedEntity($result, $entity, DrupalContentSync::EXPORT_AUTOMATICALLY);
        if ($status) {
          $items[] = $result;
        }
      }

      if (!empty($entity_uuid)) {
        $items = $items[0];
      }

      return new ModifiedResourceResponse($items);
    }

    return new ResourceResponse(
      ['message' => t(self::TYPE_HAS_NOT_BEEN_FOUND)->render()], self::CODE_NOT_FOUND
    );

  }

  /**
   * Responds to entity PATCH requests.
   *
   * @param string $api
   *   The used content sync api.
   * @param string $entity_type
   *   The name of an entity type.
   * @param string $entity_bundle
   *   The name of an entity bundle.
   * @param string $entity_type_version
   *   The version of the entity type to compare ours against.
   * @param string $entity_uuid
   *   The uuid of an entity.
   * @param array $data
   *   The data to be stored in the entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A list of entities of the given type and bundle.
   */
  public function patch($api, $entity_type, $entity_bundle, $entity_type_version, $entity_uuid, array $data) {
    return $this->handleIncomingEntity($api, $entity_type, $entity_bundle, $entity_type_version, $data, DrupalContentSync::ACTION_UPDATE);
  }

  /**
   * Responds to entity DELETE requests.
   *
   * @param string $api
   *   The used content sync api.
   * @param string $entity_type
   *   The name of an entity type.
   * @param string $entity_bundle
   *   The name of an entity bundle.
   * @param string $entity_type_version
   *   The version of the entity type.
   * @param string $entity_uuid
   *   The uuid of an entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A list of entities of the given type and bundle.
   */
  public function delete($api, $entity_type, $entity_bundle, $entity_type_version, $entity_uuid) {
    return $this->handleIncomingEntity($api, $entity_type, $entity_bundle, $entity_type_version, ['uuid' => $entity_uuid], DrupalContentSync::ACTION_DELETE);
  }

  /**
   * Responds to entity POST requests.
   *
   * @param string $api
   *   The used content sync api.
   * @param string $entity_type
   *   The posted entity type.
   * @param string $entity_bundle
   *   The name of an entity bundle.
   * @param string $entity_type_version
   *   The version of the entity type.
   * @param array $data
   *   The data to be stored in the entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A list of entities of the given type and bundle.
   */
  public function post($api, $entity_type, $entity_bundle, $entity_type_version, array $data) {
    return $this->handleIncomingEntity($api, $entity_type, $entity_bundle, $entity_type_version, $data, DrupalContentSync::ACTION_CREATE);
  }

  /**
   * @param string $api
   *   The API {@see DrupalContentSync}.
   * @param string $entity_type_name
   *   The entity type of the processed entity.
   * @param string $entity_bundle
   *   The bundle of the processed entity.
   * @param string $entity_type_version
   *   The version the config was saved for.
   * @param array $data
   *   For {@see DrupalContentSync::ACTION_CREATE} and
   *    {@see DrupalContentSync::ACTION_UPDATE}: the data for the entity. Will
   *    be passed to {@see ApiUnifyRequest}.
   * @param string $action
   *   The {@see DrupalContentSync::ACTION_*} to be performed on the entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response The result (error, ignorance or success).
   */
  private function handleIncomingEntity($api, $entity_type_name, $entity_bundle, $entity_type_version, $data, $action) {
    $entity_types = $this->entityTypeBundleInfo->getAllBundleInfo();

    if (empty($entity_types[$entity_type_name])) {
      return new ResourceResponse(
        ['message' => t(self::TYPE_HAS_NOT_BEEN_FOUND)->render()], self::CODE_NOT_FOUND
      );
    }

    $is_dependency = isset($_GET['is_dependency']) && $_GET['is_dependency'] == 'true';
    $is_clone      = isset($_GET['is_clone']) && $_GET['is_clone'] == 'true';
    $is_manual     = isset($_GET['is_manual']) && $_GET['is_manual'] == 'true';
    $reason        = $is_dependency ? DrupalContentSync::IMPORT_AS_DEPENDENCY :
      ($is_manual ? DrupalContentSync::IMPORT_MANUALLY : DrupalContentSync::IMPORT_AUTOMATICALLY);

    $sync = DrupalContentSync::getImportSynchronizationForApiAndEntityType($api, $entity_type_name, $entity_bundle, $reason, $action, $is_clone);
    if (empty($sync)) {
      \Drupal::logger('drupal_content_sync')->error('@not IMPORT @action @entity_type:@bundle @uuid @reason @clone: @message', [
        '@reason' => $reason,
        '@action' => $action,
        '@entity_type'  => $entity_type_name,
        '@bundle' => $entity_bundle,
        '@uuid' => $data['uuid'],
        '@not' => 'NO',
        '@clone' => $is_clone ? 'as clone' : '',
        '@message' => t('No synchronization config matches this request (dependency: @dependency, manual: @manual).', [
          '@dependency' => $is_dependency ? 'YES' : 'NO',
          '@manual' => $is_manual ? 'YES' : 'NO',
        ])->render(),
      ]);
      return new ResourceResponse(
        ['message' => t(self::TYPE_HAS_NOT_BEEN_FOUND)->render()], self::CODE_NOT_FOUND
      );
    }

    $local_version = DrupalContentSync::getEntityTypeVersion($entity_type_name, $entity_bundle);
    if ($entity_type_version != $local_version) {
      \Drupal::logger('drupal_content_sync')->error('@not IMPORT @action @entity_type:@bundle @uuid @reason @clone: @message', [
        '@reason' => $reason,
        '@action' => $action,
        '@entity_type'  => $entity_type_name,
        '@bundle' => $entity_bundle,
        '@uuid' => $data['uuid'],
        '@not' => 'NO',
        '@clone' => $is_clone ? 'as clone' : '',
        '@message' => t('The requested entity type version @requested doesn\'t match the local entity type version @local.', [
          '@requested' => $entity_type_version,
          '@local' => $local_version,
        ])->render(),
      ]);
      return new ResourceResponse(
        ['message' => t(self::TYPE_HAS_INCOMPATIBLE_VERSION)->render()], self::CODE_NOT_FOUND
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
    catch (SyncException $e) {
      $message = $e->parentException ? $e->parentException->getMessage() : (
        $e->errorCode == $e->getMessage() ? '' : $e->getMessage()
      );
      if ($message) {
        $message = t('Internal error @code: @message', [
          '@code' => $e->errorCode,
          '@message' => $message,
        ])->render();
      }
      else {
        $message = t('Internal error @code', [
          '@code' => $e->errorCode,
        ])->render();
      }

      \Drupal::logger('drupal_content_sync')->error('@not IMPORT @action @entity_type:@bundle @uuid @reason @clone: @message', [
        '@reason' => $reason,
        '@action' => $action,
        '@entity_type'  => $entity_type_name,
        '@bundle' => $entity_bundle,
        '@uuid' => $data['uuid'],
        '@not' => 'NO',
        '@clone' => $is_clone ? 'as clone' : '',
        '@message' => $message,
      ]);

      return new ResourceResponse(
        [
          'message' => t('SyncException @code: @message',
            [
              '@code'     => $e->errorCode,
              '@message'  => $e->getMessage(),
            ]
          )->render(),
          'code' => $e->errorCode,
        ], 500
      );
    }
    catch (\Exception $e) {
      $message = $e->getMessage();

      \Drupal::logger('drupal_content_sync')->error('@not IMPORT @action @entity_type:@bundle @uuid @reason @clone: @message', [
        '@reason' => $reason,
        '@action' => $action,
        '@entity_type'  => $entity_type_name,
        '@bundle' => $entity_bundle,
        '@uuid' => $data['uuid'],
        '@not' => 'NO',
        '@clone' => $is_clone ? 'as clone' : '',
        '@message' => $message,
      ]);

      return new ResourceResponse(
        [
          'message' => t('Unexpected error: @message', ['@message' => $e->getMessage()])->render(),
        ], 500
      );
    }

    if ($status) {
      // If we send data for DELETE requests, the Drupal Serializer will throw
      // a random error. So we just leave the body empty then.
      return new ModifiedResourceResponse($action == DrupalContentSync::ACTION_DELETE ? NULL : $data);
    }
    else {
      return new ResourceResponse(
        [
          'message' => t('Entity is not configured to be imported yet.')->render(),
        ], 404
      );
    }
  }

}
