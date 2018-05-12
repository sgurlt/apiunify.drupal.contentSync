<?php

namespace Drupal\drupal_content_sync\Plugin;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\drupal_content_sync\ApiUnifyRequest;
use Drupal\drupal_content_sync\Entity\Flow;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Psr\Log\LoggerInterface;

/**
 * Common base class for field handler plugins.
 *
 * @see \Drupal\drupal_content_sync\Annotation\EntityHandler
 * @see \Drupal\drupal_content_sync\Plugin\FieldHandlerInterface
 * @see plugin_api
 *
 * @ingroup third_party
 */
abstract class FieldHandlerBase extends PluginBase implements ContainerFactoryPluginInterface, FieldHandlerInterface {

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * @var string
   */
  protected $entityTypeName;

  /**
   * @var string
   */
  protected $bundleName;

  /**
   * @var string
   */
  protected $fieldName;

  /**
   * @var \Drupal\Core\Field\FieldDefinitionInterface
   */
  protected $fieldDefinition;

  /**
   * @var array
   *   Additional settings as provided by
   *   {@see FieldHandlerInterface::getHandlerSettings}.
   */
  protected $settings;

  /**
   * @var \Drupal\drupal_content_sync\Entity\Flow
   */
  protected $sync;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   *   Must contain entity_type_name, bundle_name, field_name, field_definition,
   *   settings and sync (see above).
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger;
    $this->entityTypeName = $configuration['entity_type_name'];
    $this->bundleName = $configuration['bundle_name'];
    $this->fieldName = $configuration['field_name'];
    $this->fieldDefinition = $configuration['field_definition'];
    $this->settings = $configuration['settings'];
    $this->sync = $configuration['sync'];
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
      Flow::EXPORT_DISABLED,
      Flow::EXPORT_AUTOMATICALLY,
    ];
  }

  /**
   * @inheritdoc
   */
  public function getAllowedImportOptions() {
    return [
      Flow::IMPORT_DISABLED,
      Flow::IMPORT_AUTOMATICALLY,
    ];
  }

  /**
   * @inheritdoc
   */
  public function getHandlerSettings() {
    // Nothing special here.
    return [];
  }

  /**
   * Advanced entity type definition settings for the Node.js backend. You
   * can usually ignore these. By default it will create a new allowed field
   * in API Unify for the entity type that is:
   * - stored in the filesystem if it is a file field
   * - stored in the database otherwise
   * - required if the field is required by Drupal as well
   * - modifiable if the field is not set to "read only" in Drupal.
   *
   * @param array $definition
   *   The definition to extend.
   */
  public function updateEntityTypeDefinition(&$definition) {
    $definition['new_property_lists']['details'][$this->fieldName] = 'value';
    $definition['new_property_lists']['database'][$this->fieldName] = 'value';

    if ($this->fieldDefinition->isRequired()) {
      $definition['new_property_lists']['required'][$this->fieldName] = 'value';
    }

    if (!$this->fieldDefinition->isReadOnly()) {
      $definition['new_property_lists']['modifiable'][$this->fieldName] = 'value';
    }
  }

  /**
   * @inheritdoc
   */
  public function import(ApiUnifyRequest $request, FieldableEntityInterface $entity, $is_clone, $reason, $action, $merge_only) {
    // Deletion doesn't require any action on field basis for static data.
    if ($action == Flow::ACTION_DELETE) {
      return FALSE;
    }

    if ($merge_only) {
      return FALSE;
    }

    if ($this->settings['import'] != Flow::IMPORT_AUTOMATICALLY) {
      return FALSE;
    }

    $data = $request->getField($this->fieldName);

    if (empty($data)) {
      $entity->set($this->fieldName, NULL);
    }
    else {
      $entity->set($this->fieldName, $data);
    }

    return TRUE;
  }

  /**
   * @inheritdoc
   */
  public function export(ApiUnifyRequest $request, FieldableEntityInterface $entity, $reason, $action) {
    if ($this->settings['export'] != Flow::EXPORT_AUTOMATICALLY) {
      return FALSE;
    }

    // Deletion doesn't require any action on field basis for static data.
    if ($action == Flow::ACTION_DELETE) {
      return FALSE;
    }

    $request->setField($this->fieldName, $entity->get($this->fieldName)->getValue());

    return TRUE;
  }

}
