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

  public function updateEntityTypeDefinition(&$definition,$config) {
  }

  public function createEntity($config,$entity_type_name,$entity_bundle,$base_data,$field_data,$is_clone) {
    $storage = \Drupal::entityTypeManager()
      ->getStorage($entity_type_name);
    $entity = $storage->create($base_data);

    $this->setEntityValues($config,$entity, $field_data,$is_clone);

    if (!$is_clone && $entity->hasField('field_drupal_content_synced')) {
      $entity->set('field_drupal_content_synced', TRUE);
    }

    return $entity;
  }

  protected function setEntityValues($config,EntityInterface $entity, $data,$is_clone) {
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $field_definitions = $entityFieldManager->getFieldDefinitions($type, $bundle);

    $field_handlers = [];
    $fieldPluginManager = \Drupal::service('plugin.manager.dcs_field_handler');

    foreach( $field_definitions as $key=>$field ) {
      if(empty($config[$type.'-'.$bundle.'-'.$key])) {
        continue;
      }

      if($config[$type.'-'.$bundle.'-'.$key]==DrupalContentSync::HANDLER_IGNORE) {
        continue;
      }

      $field_config = $config[$type.'-'.$bundle.'-'.$key];
      if(empty($field_handlers[$field_config['handler']])) {
        $field_handlers[$field_config['handler']] = $fieldPluginManager->createInstance($field_config['handler']);
      }

      $handler = $field_handlers[$field_config['handler']];

      $handler->setField($field_config,$entity,$key,$data,$is_clone);
    }

    \Drupal::moduleHandler()->alter('drupal_content_sync_set_entity_values', $entity, $data);
    $entity->save();

    if (!empty($data['apiu_translation'])) {
      foreach($data['apiu_translation'] as $language => $translation_data) {
        if ($entity->hasTranslation($language)) {
          $translation = $entity->getTranslation($language);
        } else {
          $translation = $entity->addTranslation($language);
        }
        $this->setEntityValues($config, $translation, $translation_data,$is_clone);
      }
    }
  }

  public function updateEntity($config,$entity,&$field_data) {
    $this->setEntityValues($config, $entity, $field_data);
  }
}
