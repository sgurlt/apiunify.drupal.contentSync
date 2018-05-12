<?php

namespace Drupal\drupal_content_sync;

use Drupal\drupal_content_sync\Entity\Flow;
use Drupal\drupal_content_sync\Exception\SyncException;

/**
 * Class ApiUnifyRequest.
 *
 * For every import and export of every entity, an instance of this class is
 * created and passed through the entity and field handlers. When exporting,
 * you can set field values and embed entities. When exporting, you can
 * receive these values back and resolve the entity references you saved.
 *
 * The same class is used for export and import to allow adjusting values
 * with hook integration.
 */
class ApiUnifyRequest {
  /**
   * @var \Drupal\drupal_content_sync\Entity\Flow
   *   The synchronization this request spawned at.
   * @var string            $entityType             Entity type of the processed entity.
   * @var string            $bundle                 Bundle of the processed entity.
   * @var string            $uuid                   UUID of the processed entity.
   * @var array             $fieldValues            The field values for the untranslated entity.
   * @var array             $embedEntities          The entities that should be processed along with this entity. Each entry is an array consisting of all ApiUnifyRequest::_*KEY entries.
   * @var string            $activeLanguage         The currently active language.
   * @var array             $translationFieldValues The field values for the translation of the entity per language as key.
   * @var \Drupal\drupal_content_sync\Entity\MetaInformation $meta    The meta information for this entity and sync.
   */
  protected $sync, $entityType, $bundle, $uuid, $fieldValues, $embedEntities, $activeLanguage, $translationFieldValues, $meta;

  /**
   * Keys used in the definition array for embedded entities.
   *
   * @see ApiUnifyRequest::embedEntity        for its usage on export.
   * @see ApiUnifyRequest::loadEmbeddedEntity for its usage on import.
   *
   * @var string API_KEY                  The API of the processed and referenced entity.
   * @var string ENTITY_TYPE_KEY          The entity type of the referenced entity.
   * @var string BUNDLE_KEY               The bundle of the referenced entity.
   * @var string VERSION_KEY              The version of the entity type of the referenced entity.
   * @var string UUID_KEY                 The UUID of the referenced entity.
   * @var string SOURCE_CONNECTION_ID_KEY The API Unify connection ID of the referenced entity.
   * @var string POOL_CONNECTION_ID_KEY   The API Unify connection ID of the pool for this api + entity type + bundle.
   */
  const API_KEY                  = 'api';
  const ENTITY_TYPE_KEY          = 'type';
  const BUNDLE_KEY               = 'bundle';
  const VERSION_KEY              = 'version';
  const UUID_KEY                 = 'uuid';
  const SOURCE_CONNECTION_ID_KEY = 'connection_id';
  const POOL_CONNECTION_ID_KEY   = 'next_connection_id';

  /**
   * ApiUnifyRequest constructor.
   *
   * @param \Drupal\drupal_content_sync\Entity\Flow $sync
   *   {@see ApiUnifyRequest::$sync}.
   * @param string $entity_type
   *   {@see ApiUnifyRequest::$entityType}.
   * @param string $bundle
   *   {@see ApiUnifyRequest::$bundle}.
   * @param string $uuid
   *   {@see ApiUnifyRequest::$uuid}.
   * @param \Drupal\drupal_content_sync\Entity\MetaInformation $meta
   *   The meta information for this entity and sync.
   * @param array $data
   *   NULL for exports or the data provided from API Unify for imports.
   *   Format is the same as in self::getData.
   */
  public function __construct(Flow $sync, $entity_type, $bundle, $uuid, $meta = NULL, $data = NULL) {
    $this->sync       = $sync;
    $this->entityType = $entity_type;
    $this->bundle     = $bundle;

    $this->uuid                   = $uuid;
    $this->embedEntities          = [];
    $this->activeLanguage         = NULL;
    $this->translationFieldValues = NULL;
    $this->fieldValues            = [];
    $this->meta                   = $meta;

    if (!empty($data['embed_entities'])) {
      $this->embedEntities = $data['embed_entities'];
    }
    if (!empty($data['uuid'])) {
      $this->uuid = $data['uuid'];
    }
    if (!empty($data['apiu_translation'])) {
      $this->translationFieldValues = $data['apiu_translation'];
    }
    if (!empty($data)) {
      $this->fieldValues = array_diff_key(
        $data,
        [
          'embed_entities' => [],
          'apiu_translation' => [],
          'uuid' => NULL,
          'id' => NULL,
          'bundle' => NULL,
        ]
      );
    }
  }

  /**
   * Retrieve a value you stored before via ::setMetaData().
   *
   * @see MetaInformation::getData()
   *
   * @param string|string[] $key
   *   The key to retrieve.
   *
   * @return mixed Whatever you previously stored here.
   */
  public function getMetaData($key) {
    return $this->meta ? $this->meta->getData($key) : NULL;
  }

  /**
   * Store a key=>value pair for later retrieval.
   *
   * @see MetaInformation::setData()
   *
   * @param string|string[] $key
   *   The key to store the data against. Especially
   *   field handlers should use nested keys like ['field','[name]','[key]'].
   * @param mixed $value
   *   Whatever simple value you'd like to store.
   *
   * @return bool
   */
  public function setMetaData($key, $value) {
    if (!$this->meta) {
      return FALSE;
    }
    $this->meta->setData($key, $value);
    return TRUE;
  }

  /**
   * Get all languages for field translations that are currently used.
   */
  public function getTranslationLanguages() {
    return empty($this->translationFieldValues) ? [] : array_keys($this->translationFieldValues);
  }

  /**
   * Change the language used for provided field values. If you want to add a
   * translation of an entity, the same ApiUnifyRequest is used. First, you
   * add your fields using self::setField() for the untranslated version.
   * After that you call self::changeTranslationLanguage() with the language
   * identifier for the translation in question. Then you perform all the
   * self::setField() updates for that language and eventually return to the
   * untranslated entity by using self::changeTranslationLanguage() without
   * arguments.
   *
   * @param string $language
   *   The identifier of the language to switch to or NULL to reset.
   */
  public function changeTranslationLanguage($language = NULL) {
    $this->activeLanguage = $language;
  }

  /**
   * Return the language that's currently used.
   *
   * @see ApiUnifyRequest::changeTranslationLanguage() for a detailed explanation.
   */
  public function getActiveLanguage() {
    return $this->activeLanguage;
  }

  /**
   * Get the definition for a referenced entity that should be exported /
   * embedded as well.
   *
   * @see ApiUnifyRequest::$embedEntities
   *
   * @param string $entity_type
   *   The entity type of the referenced entity.
   * @param string $bundle
   *   The bundle of the referenced entity.
   * @param string $uuid
   *   The UUID of the referenced entity.
   * @param array $details
   *   Additional details you would like to export.
   *
   * @return array The definition to be exported.
   */
  public function getEmbedEntityDefinition($entity_type, $bundle, $uuid, $details = NULL) {
    $version = Flow::getEntityTypeVersion($entity_type, $bundle);

    return array_merge([
      self::API_KEY           => $this->sync->api,
      self::ENTITY_TYPE_KEY   => $entity_type,
      self::UUID_KEY          => $uuid,
      self::BUNDLE_KEY        => $bundle,
      self::VERSION_KEY       => $version,
      self::SOURCE_CONNECTION_ID_KEY => ApiUnifyConfig::getExternalConnectionId(
        $this->sync->api,
        $this->sync->site_id,
        $entity_type,
        $bundle,
        $version
      ),
      self::POOL_CONNECTION_ID_KEY => ApiUnifyConfig::getExternalConnectionId(
        $this->sync->api,
        ApiUnifyConfig::POOL_SITE_ID,
        $entity_type,
        $bundle,
        $version
      ),
    ], $details ? $details : []);
  }

  /**
   * Embed an entity by its properties.
   *
   * @see ApiUnifyRequest::getEmbedEntityDefinition
   * @see ApiUnifyRequest::embedEntity
   *
   * @param string $entity_type
   *   {@see ApiUnifyRequest::getEmbedEntityDefinition}.
   * @param string $bundle
   *   {@see ApiUnifyRequest::getEmbedEntityDefinition}.
   * @param string $uuid
   *   {@see ApiUnifyRequest::getEmbedEntityDefinition}.
   * @param array $details
   *   {@see ApiUnifyRequest::getEmbedEntityDefinition}.
   *
   * @return array The definition you can store via {@see ApiUnifyRequest::setField} and on the other end receive via {@see ApiUnifyRequest::getField}.
   *
   * @throws \Drupal\drupal_content_sync\Exception\SyncException
   */
  public function embedEntityDefinition($entity_type, $bundle, $uuid, $details = NULL) {
    // Prevent circle references without middle man.
    if ($entity_type == $this->entityType && $uuid == $this->uuid) {
      throw new SyncException(
        SyncException::CODE_INTERNAL_ERROR,
        NULL,
        "Can't circle-reference own entity (" . $entity_type . " " . $uuid . ")."
      );
    }

    // Already included? Just return the definition then.
    foreach ($this->embedEntities as $definition) {
      if ($definition[self::ENTITY_TYPE_KEY] == $entity_type && $definition[self::UUID_KEY] == $uuid) {
        return $this->getEmbedEntityDefinition(
          $entity_type, $bundle, $uuid, $details
        );
      }
    }

    return $this->embedEntities[] = $this->getEmbedEntityDefinition(
      $entity_type, $bundle, $uuid, $details
    );
  }

  /**
   * Export the provided entity along with the processed entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The referenced entity to export as well.
   * @param array $details
   *   {@see ApiUnifyRequest::getEmbedEntityDefinition}.
   *
   * @return array The definition you can store via {@see ApiUnifyRequest::setField} and on the other end receive via {@see ApiUnifyRequest::getField}.
   *
   * @throws \Drupal\drupal_content_sync\Exception\SyncException
   */
  public function embedEntity($entity, $details = NULL) {
    return $this->embedEntityDefinition(
      $entity->getEntityTypeId(),
      $entity->bundle(),
      $entity->uuid(),
      $details
    );
  }

  /**
   * Restore an entity that was added via
   * {@see ApiUnifyRequest::embedEntityDefinition} or
   * {@see ApiUnifyRequest::embedEntity}.
   *
   * @param array $definition
   *   The definition you saved in a field and gotten
   *   back when calling one of the mentioned functions above.
   *
   * @return \Drupal\Core\Entity\EntityInterface The restored entity.
   */
  public function loadEmbeddedEntity($definition) {
    $version = Flow::getEntityTypeVersion(
      $definition[self::ENTITY_TYPE_KEY],
      $definition[self::BUNDLE_KEY]
    );
    if ($version != $definition[self::VERSION_KEY]) {
      \Drupal::logger('drupal_content_sync')->error('Failed to resolve reference to @entity_type:@bundle: Remote version @remote_version doesn\'t match local version @local_version', [
        '@entity_type'  => $definition[self::ENTITY_TYPE_KEY],
        '@bundle' => $definition[self::BUNDLE_KEY],
        '@remote_version' => $definition[self::VERSION_KEY],
        '@local_version' => $version,
      ]);
      return NULL;
    }

    $entity = \Drupal::service('entity.repository')->loadEntityByUuid(
      $definition[self::ENTITY_TYPE_KEY],
      $definition[self::UUID_KEY]
    );

    return $entity;
  }

  /**
   * Get the data that shall be exported to API Unify.
   *
   * @return array The result.
   */
  public function getData() {
    return array_merge($this->fieldValues, [
      'embed_entities'    => $this->embedEntities,
      'uuid'              => $this->uuid,
      'id'                => $this->uuid,
      'apiu_translation'  => $this->translationFieldValues,
    ]);
  }

  /**
   * Provide the value of a field you stored when exporting by using.
   *
   * @see ApiUnifyRequest::setField()
   *
   * @param string $name
   *   The name of the field to restore.
   *
   * @return mixed The value you stored for this field.
   */
  public function getField($name) {
    $source = $this->getFieldValues();

    return isset($source[$name]) ? $source[$name] : NULL;
  }

  /**
   * Get all field values at once for the currently active language.
   *
   * @return array All field values for the active language.
   */
  public function getFieldValues() {
    if ($this->activeLanguage) {
      $source = $this->translationFieldValues[$this->activeLanguage];
    }
    else {
      $source = $this->fieldValues;
    }

    return $source;
  }

  /**
   * Set the value of the given field. By default every field handler
   * will have a field available for storage when importing / exporting that
   * accepts all non-associative array-values. Within this array you can
   * use the following types: array, associative array, string, integer, float,
   * boolean, NULL. These values will be JSON encoded when exporting and JSON
   * decoded when importing. They will be saved in a structured database by
   * API Unify in between, so you can't pass any non-array value by default.
   *
   * @param string $name
   *   The name of the field in question.
   * @param mixed $value
   *   The value to store.
   */
  public function setField($name, $value) {
    if ($this->activeLanguage) {
      if ($this->translationFieldValues === NULL) {
        $this->translationFieldValues = [];
      }
      $this->translationFieldValues[$this->activeLanguage][$name] = $value;
      return;
    }

    $this->fieldValues[$name] = $value;
  }

  /**
   * @see ApiUnifyRequest::$entityType
   *
   * @return string
   */
  public function getEntityType() {
    return $this->entityType;
  }

  /**
   * @see ApiUnifyRequest::$bundle
   */
  public function getBundle() {
    return $this->bundle;
  }

  /**
   * @see ApiUnifyRequest::$uuid
   */
  public function getUuid() {
    return $this->uuid;
  }

}
