<?php

namespace Drupal\drupal_content_sync\Plugin;

use Drupal\drupal_content_sync\Entity\DrupalContentSync;
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

  public function getAllowedExportOptions() {
    return [
      DrupalContentSync::EXPORT_DISABLED,
      DrupalContentSync::EXPORT_AUTOMATICALLY,
    ];
  }

  public function getAllowedSyncImportOptions() {
    return [
      DrupalContentSync::IMPORT_DISABLED,
      DrupalContentSync::IMPORT_AUTOMATICALLY,
    ];
  }

  public function getAllowedClonedImportOptions() {
    return [
      DrupalContentSync::IMPORT_DISABLED,
      DrupalContentSync::IMPORT_AUTOMATICALLY,
    ];
  }

  public function getAdvancedSettingsForFieldAtEntityType() {
    // Nothing special here
    return [];
  }

  /**
   * Advanced entity type definition settings for the Node.js backend. You
   * can usually ignore these.
   *
   * @param $entity_type string
   * @param $bundle string
   * @param $field_name string
   * @param $field \Drupal\Core\Field\FieldDefinitionInterface
   *
   * @return boolean
   */
  public function updateEntityTypeDefinition(&$definition) {
    if( in_array($this->fieldDefinition->getType(),['file','image']) ) {
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
   * Restore a serialized field value.
   *
   * @param $field_config array The settings defined for this field via UI.
   * @param $entity \Drupal\Core\Entity\Entity The entity to alter.
   * @param $field_name string The name of the field.
   * @param $data array The data, as provided by another site via ::getField()
   * @param $is_clone boolean Whether this is cloned (synchronized otherwise)
   *
   * @return boolean
   */
  public function setField($entity,&$data,$is_clone) {
    if (isset($data[$this->fieldName])) {
      if( $this->settings[($is_clone?'cloned':'sync').'_import']==DrupalContentSync::IMPORT_AUTOMATICALLY ) {
        $entity->set($this->fieldName, $data[$this->fieldName]);
      }
    }

    return TRUE;
  }

  /**
   * Serialize a field value.
   *
   * @param $field_config array The settings defined for this field via UI.
   * @param $entity \Drupal\Core\Entity\Entity The entity to alter.
   * @param $field_name string The name of the field.
   *
   * @return array The data as it should be given to ::setField()
   */
  public function getField($entity) {
    return (array)$entity->get($this->fieldName);
  }
}
