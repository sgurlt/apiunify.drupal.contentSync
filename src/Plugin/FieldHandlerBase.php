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

  public function updateEntityTypeDefinition(&$definition,$config,$field_name,$field) {
    if( in_array($field->getType(),['file','image']) ) {
      $definition['new_property_lists']['filesystem'][$field_name] = 'value';
    }
    else {
      $definition['new_property_lists']['details'][$field_name] = 'value';
      $definition['new_property_lists']['database'][$field_name] = 'value';
    }

    if ($field->isRequired()) {
      $definition['new_property_lists']['required'][$field_name] = 'value';
    }

    if (!$field->isReadOnly()) {
      $definition['new_property_lists']['modifiable'][$field_name] = 'value';
    }
  }

  public function setField($field_config,$entity,$field_name,&$data,$is_clone) {
    if (isset($data[$field_name])) {
      if( $field_config[($is_clone?'cloned':'sync').'_import']==DrupalContentSync::IMPORT_AUTOMATICALLY ) {
        $entity->set($field_name, $data[$field_name]);
      }
    }
  }
}
