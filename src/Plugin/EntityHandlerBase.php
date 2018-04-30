<?php

namespace Drupal\drupal_content_sync\Plugin;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\drupal_content_sync\ApiUnifyRequest;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;
use Drupal\drupal_content_sync\Exception\SyncException;
use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent;
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
   * A sync instance.
   *
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
   * @inheritdoc
   */
  public function getAllowedExportOptions() {
    return [
      DrupalContentSync::EXPORT_DISABLED,
      DrupalContentSync::EXPORT_AUTOMATICALLY,
      DrupalContentSync::EXPORT_AS_DEPENDENCY,
      // Not manually as that requires UI and is not available for all entity
      // types. Advanced handlers will provide this.
    ];
  }

  /**
   * @inheritdoc
   */
  public function getAllowedImportOptions() {
    return [
      DrupalContentSync::IMPORT_DISABLED,
      DrupalContentSync::IMPORT_AUTOMATICALLY,
      DrupalContentSync::IMPORT_AS_DEPENDENCY,
      DrupalContentSync::IMPORT_MANUALLY,
    ];
  }

  /**
   * @inheritdoc
   */
  public function updateEntityTypeDefinition(&$definition) {
  }

  /**
   * @inheritdoc
   */
  public function getHandlerSettings() {
    return [];
  }

  /**
   * Load the requested entity by its UUID.
   *
   * @param \Drupal\drupal_content_sync\ApiUnifyRequest $request
   *   The request.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Returns the loaded entity.
   */
  protected function loadEntity(ApiUnifyRequest $request) {
    return \Drupal::service('entity.repository')
      ->loadEntityByUuid($request->getEntityType(), $request->getUuid());
  }

  /**
   * Check if the import should be ignored.
   *
   * @param \Drupal\drupal_content_sync\ApiUnifyRequest $request
   *   The API Unify Request.
   * @param bool $is_clone
   *   Entity cloned parameter.
   * @param string $reason
   *   The reason why the import should be ignored.
   * @param string $action
   *   The action to apply.
   *
   * @return bool
   *   Whether or not to ignore this import request.
   */
  protected function ignoreImport(ApiUnifyRequest $request, $is_clone, $reason, $action) {
    if ($reason == DrupalContentSync::IMPORT_AUTOMATICALLY || $reason == DrupalContentSync::IMPORT_MANUALLY) {
      if ($this->settings['import'] != $reason) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Import the remote entity.
   *
   * @inheritdoc
   */
  public function import(ApiUnifyRequest $request, $is_clone, $reason, $action) {
    if ($this->ignoreImport($request, $is_clone, $reason, $action)) {
      return FALSE;
    }

    /**
     * @var \Drupal\Core\Entity\FieldableEntityInterface $entity
     */
    $entity = $this->loadEntity($request);

    if ($action == DrupalContentSync::ACTION_DELETE) {
      if ($entity) {
        return $this->deleteEntity($entity);
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

    if( !$this->setEntityValues($request, $entity, $is_clone, $reason, $action) ) {
      return FALSE;
    }

    // Make sure that menu items that were created for this entity before
    // the entity was available now reference this entity correctly by ID
    // {@see DefaultLinkHandler}
    $menu_links = \Drupal::entityTypeManager()
      ->getStorage('menu_link_content')
      ->loadByProperties(['link.uri' => 'internal:/'.$this->entityTypeName.'/'.$entity->uuid()]);
    foreach ($menu_links as $item) {
      /**
       * @var \Drupal\menu_link_content\Entity\MenuLinkContent $item
       */
      $item->set('link','entity:'.$this->entityTypeName.'/'.$entity->id());
      $item->save();
    }

    return TRUE;
  }

  /**
   * Delete a entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to delete.
   *
   * @throws \Drupal\drupal_content_sync\Exception\SyncException
   *
   * @return bool
   *   Returns TRUE or FALSE for the deletion process.
   */
  protected function deleteEntity(FieldableEntityInterface $entity) {
    try {
      $entity->delete();
    }
    catch (\Exception $e) {
      throw new SyncException(SyncException::CODE_ENTITY_API_FAILURE, $e);
    }
    return TRUE;
  }

  /**
   * Set the values for the imported entity.
   *
   * @param \Drupal\drupal_content_sync\ApiUnifyRequest $request
   *   The api unify request.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity the values show be set for.
   * @param bool $is_clone
   *   The clone parameter of the imported entity.
   * @param string $reason
   *   The reason why the values should be set. @see DrupalContentSync::REASON_*.
   * @param string $action
   *
   * @see DrupalContentSync::IMPORT_*
   *
   * @throws \Drupal\drupal_content_sync\Exception\SyncException
   *
   * @return bool
   *   Returns TRUE when the values are set.
   */
  protected function setEntityValues(ApiUnifyRequest $request, FieldableEntityInterface $entity, $is_clone, $reason, $action) {
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $field_definitions = $entityFieldManager->getFieldDefinitions($type, $bundle);

    $entity_type = \Drupal::entityTypeManager()->getDefinition($request->getEntityType());
    $label       = $entity_type->getKey('label');
    if ($label) {
      $entity->set($label, $request->getField('title'));
    }

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

    if ($entity instanceof TranslatableInterface) {
      foreach ($request->getTranslationLanguages() as $language) {
        /**
         * If the provided entity is fieldable, translations are as well.
         *
         * @var \Drupal\Core\Entity\FieldableEntityInterface $translation
         */
        if ($entity->hasTranslation($language)) {
          $translation = $entity->getTranslation($language);
        }
        else {
          $translation = $entity->addTranslation($language);
        }

        $request->changeTranslationLanguage($language);
        $this->setEntityValues($request, $translation, $is_clone, $reason, $action);
      }
    }

    $request->changeTranslationLanguage();

    return TRUE;
  }

  /**
   * @param \Drupal\drupal_content_sync\ApiUnifyRequest $request
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *
   * @throws \Drupal\drupal_content_sync\Exception\SyncException
   */
  protected function setSourceUrl(ApiUnifyRequest $request, FieldableEntityInterface $entity) {
    if ($entity->hasLinkTemplate('canonical')) {
      try {
        $url = $entity->toUrl('canonical', ['absolute' => TRUE])
          ->toString(TRUE)
          ->getGeneratedUrl();
        $request->setField(
          'url',
          $url
        );
      }
      catch (\Exception $e) {
        throw new SyncException(SyncException::CODE_UNEXPECTED_EXCEPTION, $e);
      }
    }
  }

  /**
   * Check if the entity should not be ignored from the export.
   *
   * @param \Drupal\drupal_content_sync\ApiUnifyRequest $request
   *   The API Unify Request.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity that could be ignored.
   * @param string $reason
   *   The reason why the entity should be ignored from the export.
   * @param string $action
   *   The action to apply.
   *
   * @return bool
   *   Whether or not to ignore this export request.
   */
  protected function ignoreExport(ApiUnifyRequest $request, FieldableEntityInterface $entity, $reason, $action) {
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
  public function getForbiddenFields() {
    /**
     * @var \Drupal\Core\Entity\EntityTypeInterface $entity_type_entity
     */
    $entity_type_entity = \Drupal::service('entity_type.manager')
      ->getStorage($this->entityTypeName)
      ->getEntityType();
    return [
      // These basic fields are already taken care of, so we ignore them
      // here.
      $entity_type_entity->getKey('id'),
      $entity_type_entity->getKey('revision'),
      $entity_type_entity->getKey('bundle'),
      $entity_type_entity->getKey('uuid'),
      $entity_type_entity->getKey('label'),
    ];
  }

  /**
   * @inheritdoc
   */
  public function export(ApiUnifyRequest $request, FieldableEntityInterface $entity, $reason, $action) {
    if ($this->ignoreExport($request, $entity, $reason, $action)) {
      return FALSE;
    }

    // Base info.
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
      if( !($menu_item instanceof MenuLinkContent)) {
        continue;
      }

      $item = \Drupal::service('entity.repository')
        ->loadEntityByUuid('menu_link_content', $menu_item->getDerivativeId());
      if( !$item ) {
        continue;
      }

      if (!$this->sync->canExportEntity($item, DrupalContentSync::EXPORT_AS_DEPENDENCY)) {
        continue;
      }

      $request->embedEntity($item);
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
