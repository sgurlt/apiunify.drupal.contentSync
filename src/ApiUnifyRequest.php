<?php

namespace Drupal\drupal_content_sync;

use \Drupal\drupal_content_sync\Entity\DrupalContentSync;
use Drupal\drupal_content_sync\SyncResult\ErrorResult;
use Drupal\drupal_content_sync\SyncResult\SuccessResult;

/**
 * Class ApiUnifyRequest
 *
 * @TODO Add logger interface and log warnings / exceptions with it
 */
class ApiUnifyRequest {
  protected $sync;
  protected $entityType;
  protected $bundle;
  protected $uuid;
  protected $fieldValues;
  protected $embedEntities;
  protected $activeLanguage;
  protected $translationFieldValues;

  protected $result;

  const ENTITY_TYPE_KEY           = 'type';
  const API_KEY                   = 'api';
  const UUID_KEY                  = 'uuid';
  const BUNDLE_KEY                = 'bundle';
  const VERSION_KEY               = 'version';
  const SOURCE_CONNECTION_ID_KEY  = 'connection_id';
  const POOL_CONNECTION_ID_KEY    = 'next_connection_id';

  public function __construct($sync,$entity_type,$bundle,$data=NULL) {
    $this->sync           = $sync;
    $this->entityType     = $entity_type;
    $this->bundle         = $bundle;

    $this->uuid                   = NULL;
    $this->embedEntities          = [];
    $this->activeLanguage         = NULL;
    $this->translationFieldValues = NULL;
    $this->fieldValues            = [];

    if( !empty($data['embed_entities']) ) {
      $this->embedEntities = $data['embed_entities'];
    }
    if( !empty($data['uuid']) ) {
      $this->uuid = $data['uuid'];
    }
    if( !empty($data['apiu_translation']) ) {
      $this->translationFieldValues = $data['apiu_translation'];
    }
    if( !empty($data) ) {
      $this->fieldValues = array_diff_key(
        $data,
        [
          'embed_entities'=>[],
          'apiu_translation'=>[],
          'uuid'=>NULL,
          'id'=>NULL,
          'bundle'=>NULL,
        ]
      );
    }
  }

  public function getTranslationLanguages() {
    return empty($this->translationFieldValues) ? [] : array_keys($this->translationFieldValues);
  }
  public function changeTranslationLanguage($language=NULL) {
    $this->activeLanguage = $language;
  }
  public function getActiveLanguage() {
    return $this->activeLanguage;
  }

  public function getEmbedEntityDefinition($entity_type,$bundle,$uuid,$details=NULL) {
    $version  = DrupalContentSync::getEntityTypeVersion($entity_type,$bundle);

    return array_merge([
      self::API_KEY           => $this->sync->api,
      self::ENTITY_TYPE_KEY   => $entity_type,
      self::UUID_KEY          => $uuid,
      self::BUNDLE_KEY        => $bundle,
      self::VERSION_KEY       => $version,
      self::SOURCE_CONNECTION_ID_KEY => DrupalContentSync::getExternalConnectionId(
        $this->sync->api,
        $this->sync->site_id,
        $entity_type,
        $bundle,
        $version
      ),
      self::POOL_CONNECTION_ID_KEY => DrupalContentSync::getExternalConnectionId(
        $this->sync->api,
        DrupalContentSync::POOL_SITE_ID,
        $entity_type,
        $bundle,
        $version
      ),
    ],$details?$details:[]);
  }

  public function embedEntityDefinition($entity_type,$bundle,$uuid,$details=NULL) {
    return $this->embedEntities[] = $this->getEmbedEntityDefinition(
      $entity_type,$bundle,$uuid,$details
    );
  }

  public function embedEntity($entity,$details=NULL) {
    // @TODO For menu items, use $entity_type=$bundle=$menu_item->getBaseId() and $menu_uuid = $menu_item->getDerivativeId();

    return $this->embedEntityDefinition(
      $entity->getEntityTypeId(),
      $entity->bundle(),
      $entity->uuid(),
      $details
    );
  }

  public function loadEmbeddedEntity($definition) {
    $version  = DrupalContentSync::getEntityTypeVersion(
      $definition[self::ENTITY_TYPE_KEY],
      $definition[self::BUNDLE_KEY]
    );
    if( $version!=$definition[self::VERSION_KEY] ) {
      // @TODO Log error to drupal_content_sync logger
      return NULL;
    }

    $entity   = \Drupal::service('entity.repository')->loadEntityByUuid(
      $definition[self::ENTITY_TYPE_KEY],
      $definition[self::UUID_KEY]
    );

    return $entity;
  }

  public function getData() {
    return array_merge( $this->fieldValues, [
      'embed_entities'    => $this->embedEntities,
      'uuid'              => $this->uuid,
      'id'                => $this->uuid,
      'apiu_translation'  => $this->translationFieldValues,
    ] );
  }

  public function getField($name) {
    if( $this->activeLanguage ) {
      $source = &$this->translationFieldValues[$this->activeLanguage];
    }
    else {
      $source = &$this->fieldValues;
    }

    return isset($source[$name]) ? $source[$name] : NULL;
  }

  public function getFieldValues() {
    return $this->fieldValues;
  }

  public function setField($name,$value) {
    if( $this->activeLanguage ) {
      if( $this->translationFieldValues===NULL ) {
        $this->translationFieldValues = [];
      }
      $this->translationFieldValues[$this->activeLanguage][$name] = $value;
      return;
    }

    $this->fieldValues[$name] = $value;
  }

  public function getEntityType() {
    return $this->entityType;
  }
  public function getBundle() {
    return $this->bundle;
  }
  public function setEntityType($type,$bundle) {
    $this->entityType = $type;
    $this->bundle     = $bundle;
  }

  public function getUuid() {
    return $this->uuid;
  }
  public function setUuid($uuid) {
    $this->uuid = $uuid;
  }
}
