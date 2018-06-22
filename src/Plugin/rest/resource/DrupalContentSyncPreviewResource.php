<?php

namespace Drupal\drupal_content_sync\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\Renderer;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Drupal Content Sync Preview resource.
 *
 * @RestResource(
 *   id = "drupal_content_sync_preview_resource",
 *   label = @Translation("Drupal Content Sync Preview"),
 *   uri_paths = {
 *     "canonical" = "/entity/drupal_content_sync_preview/{entity_uuid}"
 *   }
 * )
 */
class DrupalContentSyncPreviewResource extends ResourceBase {

  /**
   * @const ENTITY_HAS_NOT_BEEN_FOUND
   */
  const ENTITY_HAS_NOT_BEEN_FOUND = 'An entity has not been found.';

  /**
   * @const CODE_NOT_FOUND
   */
  const CODE_NOT_FOUND = 404;

  /**
   * @const DRUPAL_CONTENT_SYNC_PREVIEW_FIELD
   */
  const DRUPAL_CONTENT_SYNC_PREVIEW_FIELD = 'drupal_content_sync_preview';

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
    Renderer $render_manager
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
      $container->get('renderer')
    );
  }

  /**
   * Responds to entity GET requests.
   *
   * @param string $entity_uuid
   *   A UUID.
   *
   * @return \Drupal\rest\ResourceResponse
   *   A response.
   */
  public function get($entity_uuid) {
    $entity_types = $this->entityTypeBundleInfo->getAllBundleInfo();

    $entity_types_keys = array_keys($entity_types);

    $loaded_entity = NULL;

    foreach ($entity_types_keys as $entity_type) {
      $storage = $this->entityTypeManager->getStorage($entity_type);

      $result = $storage->loadByProperties(['uuid' => $entity_uuid]);

      if ($result) {
        $loaded_entity = reset($result);
        break;
      }
    }

    if (!$loaded_entity) {
      return new ResourceResponse(
        ['message' => t(self::ENTITY_HAS_NOT_BEEN_FOUND)], self::CODE_NOT_FOUND
      );
    }

    $entity_type_id = $loaded_entity->getEntityTypeId();
    $view_builder = $this->entityTypeManager->getViewBuilder($entity_type_id);

    $preview = $view_builder->view($loaded_entity, self::DRUPAL_CONTENT_SYNC_PREVIEW_FIELD);

    $rendered = \Drupal::service('renderer');

    $html = $rendered->executeInRenderContext(
      new RenderContext(),
      function () use ($rendered, $preview) {
        return $rendered->render($preview);
      }
    );

    $response = array_merge(
      $loaded_entity->toArray(),
      [self::DRUPAL_CONTENT_SYNC_PREVIEW_FIELD => $html]
    );

    $cache_build = [
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    $resource_response = new ResourceResponse($response);
    $resource_response->addCacheableDependency($cache_build);

    return $resource_response;
  }

}
