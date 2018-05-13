<?php

namespace Drupal\drupal_content_sync\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the meta information entity type.
 *
 * @ingroup dcs_meta_info
 *
 * @ContentEntityType(
 *   id = "dcs_meta_info",
 *   label = @Translation("Meta Information"),
 *   base_table = "dcs_meta_info",
 *   entity_keys = {
 *     "id" = "id",
 *     "flow" = "flow",
 *     "pool" = "pool",
 *     "entity_uuid" = "entity_uuid",
 *     "entity_type" = "entity_type",
 *     "entity_type_version" = "entity_type_version",
 *     "flags" = "flags",
 *   },
 * )
 */
class MetaInformation extends ContentEntityBase implements MetaInformationInterface {

  use EntityChangedTrait;

  const FLAG_CLONED              = 0x00000001;
  const FLAG_DELETED             = 0x00000002;
  const FLAG_USER_ALLOWED_EXPORT = 0x00000004;
  const FLAG_EDIT_OVERRIDE       = 0x00000008;
  const FLAG_IS_SOURCE_ENTITY    = 0x00000010;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    // Set Entity ID or UUID by default one or the other is not set.
    if (!isset($values['entity_type'])) {
      throw new \Exception(t('The type of the entity is required.'));
    }
    if (!isset($values['flow'])) {
      throw new \Exception(t('The flow is required.'));
    }
    if (!isset($values['pool'])) {
      throw new \Exception(t('The pool is required.'));
    }


    /**
     * @var \Drupal\Core\Entity\FieldableEntityInterface $entity
     */
    $entity = \Drupal::service('entity.repository')->loadEntityByUuid($values['entity_type'], $values['entity_uuid']);

    if (!isset($values['entity_type_version'])) {
      $values['entity_type_version'] = Flow::getEntityTypeVersion($entity->getEntityType()->id(), $entity->bundle());
      return;
    }
  }

  /**
   * Get a list of all meta information entities for the given entity.
   * The list will use the sync config ID of the meta info as key. If a sync
   * config doesn't have a meta information entity yet, the value will be NULL.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string $entity_uuid
   *   The entity UUID.
   * @param string $api_id
   *   Optional api_id to filter by.
   *
   * @return \Drupal\drupal_content_sync\Entity\MetaInformation[]
   */
  public static function getInfoForEntity($entity_type, $entity_uuid, $api_id = NULL) {
    // Fill with NULL values by default.
    $result = [];
    $configs = $api_id ?
      Flow::getSynchronizationsByApi($api_id) :
      Flow::getAll();
    foreach ($configs as $sync) {
      $result[$sync->id] = NULL;
    }

    /**
     * @var \Drupal\drupal_content_sync\Entity\MetaInformation[] $entities
     */
    $entities = \Drupal::entityTypeManager()
      ->getStorage('dcs_meta_info')
      ->loadByProperties([
        'entity_type' => $entity_type,
        'entity_uuid' => $entity_uuid,
      ]);

    // Now extend with existing meta information entities.
    foreach ($entities as $info) {
      $result[$info->getEntityTypeConfig()] = $info;
    }

    return $result;
  }

  /**
   * Get the entity this meta information belongs to.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   */
  public function getEntity() {
    return \Drupal::service('entity.repository')->loadEntityByUuid(
      $this->getEntityTypeName(),
      $this->getUuid()
    );
  }

  /**
   * Get the flow this meta information belongs to.
   *
   * @return \Drupal\drupal_content_sync\Entity\Flow
   */
  public function getSync() {
    return Flow::getAll()[$this->getEntityTypeConfig()];
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
   * Returns the information if the entity has originally been created on this
   * site.
   *
   * @param bool $set
   *   Optional parameter to set the value for IsSourceEntity.
   *
   * @return bool
   */
  public function isSourceEntity($set = NULL) {
    if ($set === TRUE) {
      $this->set('flags', $this->get('flags')->value | self::FLAG_IS_SOURCE_ENTITY);
      $this->save();
    }
    elseif ($set === FALSE) {
      $this->set('flags', $this->get('flags')->value & ~self::FLAG_IS_SOURCE_ENTITY);
      $this->save();
    }
    return (bool) ($this->get('flags')->value & self::FLAG_IS_SOURCE_ENTITY);
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
   * Returns the UUID of the entity this information belongs to.
   *
   * @return string
   */
  public function getUuid() {
    return $this->get('entity_uuid')->value;
  }

  /**
   * Returns the entity type name of the entity this information belongs to.
   *
   * @return string
   */
  public function getEntityTypeName() {
    return $this->get('entity_type')->value;
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
   * Get the flow.
   *
   * @return \Drupal\drupal_content_sync\Entity\Flow
   */
  public function getFlow() {
    return Flow::getAll()[ $this->get('flow')->value ];
  }

  /**
   * Get the pool.
   *
   * @return \Drupal\drupal_content_sync\Entity\Pool
   */
  public function getPool() {
    return Pool::getAll()[ $this->get('pool')->value ];
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
   * Get a previously saved key=>value pair.
   *
   * @see self::setData()
   *
   * @param string|string[] $key
   *   The key to retrieve.
   *
   * @return mixed Whatever you previously stored here or NULL if the key
   *   doesn't exist.
   */
  public function getData($key) {
    $data    = $this->get('data')->getValue()[0];
    $storage = &$data;

    if (!is_array($key)) {
      $key = [$key];
    }

    foreach ($key as $index) {
      if (!isset($storage[$index])) {
        return NULL;
      }
      $storage = &$storage[$index];
    }

    return $storage;
  }

  /**
   * Set a key=>value pair.
   *
   * @param string|string[] $key
   *   The key to set (for hierarchical usage, provide
   *   an array of indices.
   * @param mixed $value
   *   The value to set. Must be a valid value for Drupal's
   *   "map" storage (so basic types that can be serialized).
   */
  public function setData($key, $value) {
    $data    = $this->get('data')->getValue()[0];
    $storage = &$data;

    if (!is_array($key)) {
      $key = [$key];
    }

    foreach ($key as $index) {
      if (!isset($storage[$index])) {
        $storage[$index] = [];
      }
      $storage = &$storage[$index];
    }

    $storage = $value;
    $this->set('data', $data);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['flow'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Flow'))
      ->setDescription(t('The flow the meta entity is based on.'));

    $fields['pool'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Pool'))
      ->setDescription(t('The pool the entity is connected to.'));

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
      ->setDescription(t('Stores boolean information about the exported/imported entity.'))
      ->setSetting('unsigned', TRUE)
      ->setDefaultValue(0);

    $fields['data'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Data'))
      ->setDescription(t('Stores further information about the exported/imported entity that can also be used by entity and field handlers.'))
      ->setRequired(FALSE);

    return $fields;
  }

}
