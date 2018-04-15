<?php

namespace Drupal\drupal_content_sync\Plugin;

use Drupal\Core\Entity\EntityInterface;
use Drupal\drupal_content_sync\ApiUnifyRequest;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;
use Drupal\drupal_content_sync\Exception\SyncException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Render\RenderContext;
use Psr\Log\LoggerInterface;

/**
 * Common base class for entity handler plugins.
 *
 * @see \Drupal\drupal_content_sync\Annotation\EntityHandler
 * @see \Drupal\drupal_content_sync\Plugin\EntityHandlerInterface
 * @see plugin_api
 *
 * @ingroup third_party
 */
abstract class EntityHandlerBase extends PluginBase implements ContainerFactoryPluginInterface, EntityHandlerInterface {

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  protected $entityTypeName;
  protected $bundleName;
  protected $settings;

  /**
   * @var \Drupal\drupal_content_sync\Entity\DrupalContentSync
   */
  protected $sync;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger         = $logger;
    $this->entityTypeName = $configuration['entity_type_name'];
    $this->bundleName     = $configuration['bundle_name'];
    $this->settings       = $configuration['settings'];
    $this->sync           = $configuration['sync'];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('drupal_content_sync')
    );
  }

  /**
   * @ToDo: Add description.
   */
  public function updateEntityTypeDefinition(&$definition) {
  }

  /**
   * @ToDo: Add description.
   */
  public function getHandlerSettings() {
    return [];
  }

  /**
   * @param \Drupal\drupal_content_sync\ApiUnifyRequest $request
   *
   * @return \Drupal\Core\Entity\EntityInterface
   */
  protected function loadEntity($request) {
    return \Drupal::service('entity.repository')->loadEntityByUuid($request->getEntityType(), $request->getUuid());
  }

  /**
   * @param \Drupal\drupal_content_sync\ApiUnifyRequest $request
   * @param bool $is_clone
   * @param string $reason
   * @param string $action
   *
   * @return bool Whether or not to ignore this import request.
   */
  protected function ignoreImport(ApiUnifyRequest $request, $is_clone, $reason, $action) {
    if ($reason == DrupalContentSync::IMPORT_AUTOMATICALLY || $reason == DrupalContentSync::IMPORT_MANUALLY) {
      if ($this->settings[($is_clone ? 'cloned' : 'sync') . '_import'] != $reason) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * @inheritdoc
   */
  public function import(ApiUnifyRequest $request, $is_clone, $reason, $action) {
    if ($this->ignoreImport($request, $is_clone, $reason, $action)) {
      return FALSE;
    }

    $entity = $this->loadEntity($request);

    if ($action == DrupalContentSync::ACTION_DELETE) {
      if ($entity) {
        return $this->deleteEntity($entity, $reason);
      }
      return FALSE;
    }

    if ($is_clone || !$entity) {
      $entity_type = \Drupal::entityTypeManager()->getDefinition($request->getEntityType());

      $base_data = [
        $entity_type->getKey('bundle') => $request->getBundle(),
        $entity_type->getKey('label') => $request->getField('title'),
      ];

      if (!$is_clone) {
        $base_data[$entity_type->getKey('uuid')] = $request->getUuid();
      }

      $storage = \Drupal::entityTypeManager()->getStorage($request->getEntityType());
      $entity = $storage->create($base_data);

      if (!$entity) {
        throw new SyncException(SyncException::CODE_ENTITY_API_FAILURE);
      }
    }

    return $this->setEntityValues($request, $entity, $is_clone, $reason, $action);
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param $reason
   *
   * @throws \Drupal\drupal_content_sync\Exception\SyncException
   *
   * @return bool
   */
  protected function deleteEntity($entity, $reason) {
    try {
      $entity->delete();
    }
    catch (\Exception $e) {
      throw new SyncException(SyncException::CODE_ENTITY_API_FAILURE, $e);
    }
    return TRUE;
  }

  /**
   * @param \Drupal\drupal_content_sync\ApiUnifyRequest $request
   * @see self::import
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @see self::import
   * @param bool $is_clone
   * @see self::import
   * @param string $reason
   * @see self::import
   * @param string $action
   * @see self::import
   *
   * @throws \Drupal\drupal_content_sync\Exception\SyncException
   *
   * @return bool
   */
  protected function setEntityValues(ApiUnifyRequest $request, EntityInterface $entity, $is_clone, $reason, $action) {
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $field_definitions = $entityFieldManager->getFieldDefinitions($type, $bundle);

    $entity_type = \Drupal::entityTypeManager()->getDefinition($request->getEntityType());
    $entity->set($entity_type->getKey('label'), $request->getField('title'));

    foreach ($field_definitions as $key => $field) {
      $handler = $this->sync->getFieldHandler($type, $bundle, $key);

      if (!$handler) {
        continue;
      }

      $handler->import($request, $entity, $is_clone, $reason, $action);
    }

    try {
      $entity->save();
    }
    catch (\Exception $e) {
      throw new SyncException(SyncException::CODE_ENTITY_API_FAILURE, $e);
    }

    foreach ($request->getTranslationLanguages() as $language) {
      if ($entity->hasTranslation($language)) {
        $translation = $entity->getTranslation($language);
      }
      else {
        $translation = $entity->addTranslation($language);
      }

      $request->changeTranslationLanguage($language);
      $this->setEntityValues($request, $translation, $is_clone, $reason, $action);
    }

    $request->changeTranslationLanguage();

    return TRUE;
  }

  /**
   *
   */
  protected function setSourceUrl(ApiUnifyRequest $request, EntityInterface $entity) {
    if ($entity->hasLinkTemplate('canonical')) {
      $request->setField(
        'url',
        $entity->toUrl('canonical', ['absolute' => TRUE])
          ->toString(TRUE)
          ->getGeneratedUrl()
      );
    }
  }

  /**
   * @param \Drupal\drupal_content_sync\ApiUnifyRequest $request
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param string $reason
   * @param string $action
   *
   * @return bool Whether or not to ignore this export request.
   */
  protected function ignoreExport(ApiUnifyRequest $request, EntityInterface $entity, $reason, $action) {
    if ($reason == DrupalContentSync::EXPORT_AUTOMATICALLY || $reason == DrupalContentSync::EXPORT_MANUALLY) {
      if ($this->settings['export'] != $reason) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * @inheritdoc
   */
  public function export(ApiUnifyRequest $request, EntityInterface $entity, $reason, $action) {
    if ($this->ignoreExport($request, $entity, $reason, $action)) {
      return FALSE;
    }

    // Base info.
    $request->setUuid($entity->uuid());
    $request->setField('title', $entity->label());

    // Translations.
    if (!$request->getActiveLanguage() &&
      method_exists($entity, 'getTranslationLanguages') &&
      method_exists($entity, 'getTranslation')) {
      $languages = array_keys($entity->getTranslationLanguages(FALSE));

      foreach ($languages as $language) {
        $request->changeTranslationLanguage($language);
        $this->export($request, $entity->getTranslation($language), $request, $action);
      }

      $request->changeTranslationLanguage();
    }

    // Menu items.
    $menu_link_manager = \Drupal::service('plugin.manager.menu.link');
    $menu_items = $menu_link_manager->loadLinksByRoute('entity.' . $this->entityTypeName . '.canonical', [$this->entityTypeName => $entity->id()]);
    foreach ($menu_items as $menu_item) {
      if (!$this->sync->exportsEntity($menu_item, DrupalContentSync::EXPORT_AS_DEPENDENCY)) {
        continue;
      }

      $request->embedEntity($menu_item);
    }

    // Preview.
    $entityTypeManager = \Drupal::entityTypeManager();
    $view_builder = $entityTypeManager->getViewBuilder($this->entityTypeName);
    $preview = $view_builder->view($entity, 'drupal_content_sync_preview');
    $rendered = \Drupal::service('renderer');
    $html = $rendered->executeInRenderContext(
      new RenderContext(),
      function () use ($rendered, $preview) {
        return $rendered->render($preview);
      }
    );
    $request->setField('preview', $html);

    // Source URL.
    $this->setSourceUrl($request, $entity);

    // Fields.
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $type               = $entity->getEntityTypeId();
    $bundle             = $entity->bundle();
    $field_definitions  = $entityFieldManager->getFieldDefinitions($type, $bundle);

    foreach ($field_definitions as $key => $field) {
      $handler = $this->sync->getFieldHandler($type, $bundle, $key);

      if (!$handler) {
        continue;
      }

      $handler->export($request, $entity, $reason, $action);
    }

    return TRUE;
  }

}
