<?php

namespace Drupal\drupal_content_sync\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the Sync entity entity.
 *
 * @ingroup drupal_content_sync_meta_info
 *
 * @ContentEntityType(
 *   id = "drupal_content_sync_meta_info",
 *   label = @Translation("Drupal Content Sync Meta Info"),
 *   base_table = "drupal_content_sync_meta_info",
 *   entity_keys = {
 *     "id" = "id",
 *     "entity_type_config" = "entity_type_config",
 *     "entity_id" = "entity_id",
 *     "entity_uuid" = "entity_uuid",
 *     "entity_type" = "entity_type",
 *     "entity_type_version" = "entity_type_version",
 *     "flags" = "flags",
 *   },
 * )
 */
class DrupalContentSyncMetaInformation extends ContentEntityBase implements DrupalContentSyncMetaInformationInterface {

  use EntityChangedTrait;

  const FLAG_CLONED               = 0x00000001;
  const FLAG_DELETED              = 0x00000002;
  const FLAG_USER_ALLOWED_EXPORT  = 0x00000004;
  const FLAG_EDIT_OVERRIDE        = 0x00000008;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    // Set Entity ID or UUID by default one or the other is not set.
    if (!isset($values['entity_type'])) {
      throw new \Exception(t('The type of the entity is required.'));
    }
    if (!isset($values['entity_type_config'])) {
      throw new \Exception(t('The entity type config is required.'));
    }

    /**
     * @var \Drupal\Core\Entity\FieldableEntityInterface $entity
     */
    // Set the uuid if the entity_id is given and the entity_uuid is not given.
    if (isset($values['entity_id']) && !isset($values['entity_uuid'])) {
      $entity = \Drupal::entityTypeManager()->getStorage($values['entity_type'])->load($values['entity_id']);
      $values['entity_uuid'] = $entity->uuid();
    }
    // Set the id if the entity_uuid is given and the entity_id is not given.
    elseif (!isset($values['entity_id']) && isset($values['entity_uuid'])) {
      $entity = \Drupal::service('entity.repository')->loadEntityByUuid($values['entity_type'], $values['entity_uuid']);
      $values['entity_id'] = $entity->id();
    }
    // Throw and exception if neither the id or the uuid is given.
    elseif (!isset($values['entity_id']) && !isset($values['entity_uuid'])) {
      throw new \Exception(t('Either the entity_id or the entity_uuid must be given.'));
    }

    // Set the Entity Version ID if it is not given.
    $entity = \Drupal::entityTypeManager()->getStorage($values['entity_type'])->load($values['entity_id']);
    if (!isset($values['entity_type_version'])) {

      $values['entity_type_version'] = DrupalContentSync::getEntityTypeVersion($entity->getEntityType()->id(), $entity->bundle());
      return;
    }
  }

  /**
   * Get a list of all meta information entities for the given entity.
   * The list will use the sync config ID of the meta info as key. If a sync
   * config doesn't have a meta information entity yet, the value will be NULL.
   *
   * @param string $entity_type The entity type ID.
   * @param string $entity_uuid The entity UUID.
   * @param string $api_id Optional api_id to filter by
   *
   * @return \Drupal\drupal_content_sync\Entity\DrupalContentSyncMetaInformation[]
   */
  public static function getInfoForEntity($entity_type, $entity_uuid, $api_id=NULL) {
    // Fill with NULL values by default
    $result   = [];
    $configs  = $api_id ?
      DrupalContentSync::getSynchronizationsByApi($api_id) :
      DrupalContentSync::getAll();
    foreach($configs as $sync) {
      $result[$sync->id] = NULL;
    }

    /**
     * @var \Drupal\drupal_content_sync\Entity\DrupalContentSyncMetaInformation[] $entities
     */
    $entities = \Drupal::entityTypeManager()
      ->getStorage('drupal_content_sync_meta_info')
      ->loadByProperties([
        'entity_type' => $entity_type,
        'entity_uuid' => $entity_uuid,
      ]);

    // Now extend with existing meta information entities
    foreach($entities as $info) {
      $result[ $info->getEntityTypeConfig() ] = $info;
    }

    return $result;
  }

  /**
   * Returns the information if the entity is cloned or not.
   *
   * @param bool $set
   *   Optional parameter to the the value for cloned.
   *
   * @return bool
   */
  public function isCloned($set = NULL) {
    if ($set === TRUE) {
      $this->set('flags', $this->get('flags')->value | self::FLAG_CLONED);
      $this->save();
    }
    elseif ($set === FALSE) {
      $this->set('flags', $this->get('flags')->value & ~self::FLAG_CLONED);
      $this->save();
    }
    return (bool) ($this->get('flags')->value & self::FLAG_CLONED);
  }

  /**
   * Returns the information if the user override the entity locally.
   *
   * @param bool $set
   *   Optional parameter to set the value for EditOverride.
   *
   * @return bool
   */
  public function isOverriddenLocally($set = NULL) {
    if ($set === TRUE) {
      $this->set('flags', $this->get('flags')->value | self::FLAG_EDIT_OVERRIDE);
      $this->save();
    }
    elseif ($set === FALSE) {
      $this->set('flags', $this->get('flags')->value & ~self::FLAG_EDIT_OVERRIDE);
      $this->save();
    }
    return (bool) ($this->get('flags')->value & self::FLAG_EDIT_OVERRIDE);
  }

  /**
   * Returns the information if the user allowed the export.
   *
   * @param bool $set
   *   Optional parameter to set the value for UserAllowedExport.
   *
   * @return bool
   */
  public function didUserAllowExport($set = NULL) {
    if ($set === TRUE) {
      $this->set('flags', $this->get('flags')->value | self::FLAG_USER_ALLOWED_EXPORT);
      $this->save();
    }
    elseif ($set === FALSE) {
      $this->set('flags', $this->get('flags')->value & ~self::FLAG_USER_ALLOWED_EXPORT);
      $this->save();
    }
    return (bool) ($this->get('flags')->value & self::FLAG_USER_ALLOWED_EXPORT);
  }

  /**
   * Returns the information if the entity is deleted.
   *
   * @param bool $set
   *   Optional parameter to set the value for Deleted.
   *
   * @return bool
   */
  public function isDeleted($set = NULL) {
    if ($set === TRUE) {
      $this->set('flags', $this->get('flags')->value | self::FLAG_DELETED);
      $this->save();
    }
    elseif ($set === FALSE) {
      $this->set('flags', $this->get('flags')->value & ~self::FLAG_DELETED);
      $this->save();
    }
    return (bool) ($this->get('flags')->value & self::FLAG_DELETED);
  }

  /**
   * Returns the timestamp for the last import.
   *
   * @return int
   */
  public function getLastImport() {
    return $this->get('last_import')->value;
  }

  /**
   * Set the last import timestamp.
   *
   * @param int $timestamp
   */
  public function setLastImport($timestamp) {
    $this->set('last_import', $timestamp);
    $this->save();
  }

  /**
   * Returns the timestamp for the last export.
   *
   * @return int
   */
  public function getLastExport() {
    return $this->get('last_export')->value;
  }

  /**
   * Set the last import timestamp.
   *
   * @param int $timestamp
   */
  public function setLastExport($timestamp) {
    $this->set('last_export', $timestamp);
    $this->save();
  }

  /**
   * Set the entity type config.
   *
   * @param string $entity_type_config
   */
  public function setEntityTypeConfig($entity_type_config) {
    $this->set('entity_type_config', $entity_type_config);
    $this->save();
  }

  /**
   * Get the entity type config.
   *
   * @return string
   */
  public function getEntityTypeConfig() {
    return $this->get('entity_type_config')->value;
  }

  /**
   * Returns the entity type version.
   *
   * @return string
   */
  public function getEntityTypeVersion() {
    return $this->get('entity_type_version')->value;
  }

  /**
   * Set the last import timestamp.
   *
   * @param string $version
   */
  public function setEntityTypeVersion($version) {
    $this->set('entity_type_version', $version);
    $this->save();
  }

  /**
   * Returns the entities source url.
   *
   * @return string
   */
  public function getSourceUrl() {
    return $this->get('source_url')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Entity ID'))
      ->setDescription(t('The ID of the entity that is synchronized.'));

    $fields['entity_type_config'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type config'))
      ->setDescription(t('The entity type config the meta entity is based on.'));

    $fields['entity_uuid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity UUID'))
      ->setDescription(t('The UUID of the entity that is synchronized.'))
      ->setSetting('max_length', 128);

    $fields['entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type'))
      ->setDescription(t('The entity type of the entity that is synchronized.'));

    $fields['entity_type_version'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type version'))
      ->setDescription(t('The version of the entity type provided by Content Sync.'))
      ->setSetting('max_length', 32);

    $fields['source_url'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Source URL'))
      ->setDescription(t('The entities source URL.'))
      ->setRequired(FALSE);

    $fields['last_export'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Last exported'))
      ->setDescription(t('The last time the entity got exported.'))
      ->setRequired(FALSE);

    $fields['last_import'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Last import'))
      ->setDescription(t('The last time the entity got imported.'))
      ->setRequired(FALSE);

    $fields['flags'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Flags'))
      ->setDescription(t('Stores further information about the exported/imported entity.'))
      ->setSetting('unsigned', TRUE)
      ->setDefaultValue(0);

    return $fields;
  }

}
