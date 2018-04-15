<?php

namespace Drupal\drupal_content_sync\Plugin;

use Drupal\Core\Entity\EntityInterface;
use Drupal\drupal_content_sync\ApiUnifyRequest;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;
use Drupal\drupal_content_sync\SyncResult\SuccessResult;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Psr\Log\LoggerInterface;

/**
 * Common base class for entity handler plugins.
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

  protected $entityTypeName;
  protected $bundleName;
  protected $fieldName;
  protected $fieldDefinition;
  protected $settings;
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
   * @ToDo: Add description.
   */
  public function getAllowedExportOptions() {
    return [
      DrupalContentSync::EXPORT_DISABLED,
      DrupalContentSync::EXPORT_AUTOMATICALLY,
    ];
  }

  /**
   * @ToDo: Add description.
   */
  public function getAllowedSyncImportOptions() {
    return [
      DrupalContentSync::IMPORT_DISABLED,
      DrupalContentSync::IMPORT_AUTOMATICALLY,
    ];
  }

  /**
   * @ToDo: Add description.
   */
  public function getAllowedClonedImportOptions() {
    return [
      DrupalContentSync::IMPORT_DISABLED,
      DrupalContentSync::IMPORT_AUTOMATICALLY,
    ];
  }

  /**
   * @ToDo: Add description.
   */
  public function getHandlerSettings() {
    // Nothing special here.
    return [];
  }

  /**
   * Advanced entity type definition settings for the Node.js backend. You
   * can usually ignore these.
   *
   * @param $definition array
   */
  public function updateEntityTypeDefinition(&$definition) {
    if (in_array($this->fieldDefinition->getType(), ['file', 'image'])) {
      $definition['new_property_lists']['filesystem'][$this->fieldName] = 'value';
    }
    else {
      $definition['new_property_lists']['details'][$this->fieldName] = 'value';
      $definition['new_property_lists']['database'][$this->fieldName] = 'value';
    }

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
  public function import(ApiUnifyRequest $request,EntityInterface $entity,$is_clone,$reason,$action) {
    // Deletion doesn't require any action on field basis for static data
    if( $action==DrupalContentSync::ACTION_DELETE ) {
      return FALSE;
    }

    if( $reason==DrupalContentSync::IMPORT_AUTOMATICALLY || $reason==DrupalContentSync::IMPORT_MANUALLY ) {
      if( $this->settings[($is_clone ? 'cloned' : 'sync') . '_import']!=$reason ) {
        return FALSE;
      }
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
  public function export(ApiUnifyRequest $request,EntityInterface $entity,$reason,$action) {
    if( $reason==DrupalContentSync::EXPORT_AUTOMATICALLY || $reason==DrupalContentSync::EXPORT_MANUALLY ) {
      if( $this->settings['export']!=$reason ) {
        return FALSE;
      }
    }

    // Deletion doesn't require any action on field basis for static data
    if( $action==DrupalContentSync::ACTION_DELETE ) {
      return FALSE;
    }

    $request->setField($this->fieldName,$entity->get($this->fieldName)->getValue());

    return TRUE;
  }

}
