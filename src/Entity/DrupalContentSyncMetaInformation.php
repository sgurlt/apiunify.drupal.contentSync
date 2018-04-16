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
 *     "entity_id" = "entity_id",
 *     "entity_uuid" = "entity_uuid",
 *     "entity_type" = "entity_type",
 *     "last_export" = "last_export",
 *     "last_import" = "last_import",
 *     "entity_type_version" = "entity_type_version",
 *     "flags" = "flags",
 *   },
 * )
 */
class DrupalContentSyncMetaInformation extends ContentEntityBase implements DrupalContentSyncMetaInformationInterface {

  use EntityChangedTrait;

  const FLAG_CLONED              = 0x00000001;
  const FLAG_DELETED             = 0x00000002;
  const FLAG_USER_ALLOWED_EXPORT = 0x00000004;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    // Set Entity ID or UUID by default one or the other is not set.
    if (!isset($values['entity_type'])) {
      throw new \Exception(t('The type of the entity is required.'));
    }

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

      $values['entity_type_version'] = DrupalContentSync::getEntityTypeVersion($entity->getEntityType()->id(), $entity->getType());
      return;
    }

    // Set source URL if is not given.
    if ($entity->hasLinkTemplate('canonical')) {
      $values['source_url'] = $entity->toUrl('canonical', ['absolute' => TRUE])
        ->toString(TRUE)
        ->getGeneratedUrl();
    }
  }

  /**
   * Return an element by the entity id and entity type.
   *
   * @param string $entity_type
   *   The type of the entity.
   * @param int $entity_id
   *   The ID of the entity.
   * @param int $entity_uuid
   *   The UUID of the entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface $entity
   *
   * @throws
   *   An exception if neither the entity_id nor the entity_uuid is given.
   */
  public static function getInfoByEntity($entity_type, $entity_id = NULL, $entity_uuid = NULL) {

    // Load the object by the entity id.
    if (!is_null($entity_id)) {
      $entity = \Drupal::entityTypeManager()->getStorage('drupal_content_sync_meta_info')->loadByProperties([
        'entity_type' => $entity_type,
        'entity_id' => $entity_id,
      ]);
    }
    // Load the object by the entity uuid.
    elseif (!is_null($entity_uuid)) {
      $entity = \Drupal::entityTypeManager()->getStorage('drupal_content_sync_meta_info')->loadByProperties([
        'entity_type' => $entity_type,
        'entity_uuid' => $entity_uuid,
      ]);
    }
    // Throw and exception if neither the id nor the uuid is given.
    elseif (is_null($entity_id) && is_null($entity_uuid)) {
      throw new \Exception(t('Either the entity_id or the entity_uuid must be given.'));
    }

    // @ToDo: Is there a better way to just receive one object here?
    return reset($entity);
  }

  /**
   * Return an element by.
   *
   * @param $entity
   *
   * @return \Drupal\Core\Entity\EntityInterface
   */
  public static function getInfoForEntity($entity) {
    return \Drupal::entityTypeManager()->getStorage('drupal_content_sync_meta_info')->load($entity->id());
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
      ->setDescription(t('The last time the entity got exported.'));

    $fields['last_import'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Last import'))
      ->setDescription(t('The last time the entity got imported.'));

    $fields['flags'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Flags'))
      ->setDescription(t('Stores further information about the exported/imported entity.'))
      ->setSetting('unsigned', TRUE);

    return $fields;
  }

}
