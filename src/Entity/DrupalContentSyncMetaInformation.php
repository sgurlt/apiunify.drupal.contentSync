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
 *     "source_url" = "source_url",
 *     "last_export" = "last_export",
 *     "last_import" = "last_import",
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

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    // @ToDo: Set Entity Version ID if not set (getEntityTypeVersion($type_name, $bundle_name))
    // @ToDo: Set Entity ID or UUID by default one or the other is not set.
    // @ToDo: Set URL of entity by default if not set.
  }

  /**
   * Return an element by the entity id and entity type.
   *
   * @param string $entity_type
   *   The type of the entity.
   *
   * @param integer $entity_id
   *   The ID of the entity.
   *
   * @param integer $entity_uuid
   *   The UUID of the entity.
   *
   * @return array
   *   Entity ID, Entity Type, Entity Bundle, Data, Created, Updated.
   */
  public static function getInfoByEntity($entity_type, $entity_id = NULL, $entity_uuid = NULL) {
    \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'article']);
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
    if($set===TRUE) {
      $this->flags |= self::FLAG_CLONED;
    }
    else if($set===FALSE) {
      $this->flags = $this->flags & ~self::FLAG_CLONED;
    }
    return (bool)($this->flags & self::FLAG_CLONED);
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

  }

  /**
   * Returns the timestamp for the last import.
   *
   * @return integer
   */
  public function getLastImport() {

  }

  /**
   * Set the last import timestamp
   *
   * @param $timestamp integer
   */
  public function setLastImport($timestamp) {

  }

  /**
   * Returns the timestamp for the last export.
   *
   * @return integer
   */
  public function getLastExport() {

  }

  /**
   * Set the last import timestamp
   *
   * @param $timestamp integer
   */
  public function setLastExport($timestamp) {

  }

  /**
   * Returns the entity type version.
   *
   * @return string
   */
  public function getEntityTypeVersion() {

  }

  /**
   * Set the last import timestamp
   *
   * @param $version string
   */
  public function setEntityTypeVersion($version) {

  }

  /**
   * Returns the entities source url.
   *
   * @return string
   */
  public function getSourceURL() {

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
      ->setDescription(t('The entities source URL.'));

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
