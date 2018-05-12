<?php

namespace Drupal\drupal_content_sync\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\drupal_content_sync\ApiUnifyConfig;
use Drupal\drupal_content_sync\ApiUnifyRequest;
use Drupal\drupal_content_sync\Exception\SyncException;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Entity\EntityInterface;
/**
 * Defines the Flow entity.
 *
 * @ConfigEntityType(
 *   id = "dcs_flow",
 *   label = @Translation("Flow"),
 *   handlers = {
 *     "list_builder" = "Drupal\drupal_content_sync\Controller\FlowListBuilder",
 *     "form" = {
 *       "add" = "Drupal\drupal_content_sync\Form\FlowForm",
 *       "edit" = "Drupal\drupal_content_sync\Form\FlowForm",
 *       "delete" = "Drupal\drupal_content_sync\Form\FlowDeleteForm",
 *     }
 *   },
 *   config_prefix = "flow",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/services/drupal_content_sync/synchronizations/{dcs_flow}/edit",
 *     "delete-form" = "/admin/config/services/drupal_content_sync/synchronizations/{dcs_flow}/delete",
 *   }
 * )
 */
class Flow extends ConfigEntityBase implements FlowInterface {
  /**
   * @var string EXPORT_DISABLED
   *   Disable export completely for this entity type, unless forced.
   *   - used as a configuration option
   *   - not used as $action
   */
  const EXPORT_DISABLED = 'disabled';
  /**
   * @var string EXPORT_AUTOMATICALLY
   *   Automatically export all entities of this entity type.
   *   - used as a configuration option
   *   - used as $action
   */
  const EXPORT_AUTOMATICALLY = 'automatically';
  /**
   * @var string EXPORT_MANUALLY
   *   Export only some of these entities, chosen manually.
   *   - used as a configuration option
   *   - used as $action
   */
  const EXPORT_MANUALLY = 'manually';
  /**
   * @var string EXPORT_AS_DEPENDENCY
   *   Export only some of these entities, exported if other exported entities
   *   use it.
   *   - used as a configuration option
   *   - used as $action
   */
  const EXPORT_AS_DEPENDENCY = 'dependency';
  /**
   * @var string EXPORT_FORCED
   *   Force the entity to be exported (as long as a handler is also selected).
   *   Can be used programmatically for custom workflows.
   *   - not used as a configuration option
   *   - used as $action
   */
  const EXPORT_FORCED = 'forced';


  /**
   * @var string IMPORT_DISABLED
   *   Disable import completely for this entity type, unless forced.
   *   - used as a configuration option
   *   - not used as $action
   */
  const IMPORT_DISABLED = 'disabled';
  /**
   * @var string IMPORT_AUTOMATICALLY
   *   Automatically import all entities of this entity type.
   *   - used as a configuration option
   *   - used as $action
   */
  const IMPORT_AUTOMATICALLY = 'automatically';
  /**
   * @var string IMPORT_MANUALLY
   *   Import only some of these entities, chosen manually.
   *   - used as a configuration option
   *   - used as $action
   */
  const IMPORT_MANUALLY = 'manually';
  /**
   * @var string IMPORT_AS_DEPENDENCY
   *   Import only some of these entities, imported if other imported entities
   *   use it.
   *   - used as a configuration option
   *   - used as $action
   */
  const IMPORT_AS_DEPENDENCY = 'dependency';
  /**
   * @var string IMPORT_FORCED
   *   Force the entity to be imported (as long as a handler is also selected).
   *   Can be used programmatically for custom workflows.
   *   - not used as a configuration option
   *   - used as $action
   */
  const IMPORT_FORCED = 'forced';


  /**
   * @var string IMPORT_UPDATE_IGNORE
   *   Ignore all incoming updates.
   */
  const IMPORT_UPDATE_IGNORE = 'ignore';
  /**
   * @var string IMPORT_UPDATE_FORCE
   *   Overwrite any local changes on all updates.
   */
  const IMPORT_UPDATE_FORCE = 'force';
  /**
   * @var string IMPORT_UPDATE_FORCE_AND_FORBID_EDITING
   *   Import all changes and forbid local editors to change the content.
   */
  const IMPORT_UPDATE_FORCE_AND_FORBID_EDITING = 'force_and_forbid_editing';
  /**
   * @var string IMPORT_UPDATE_FORCE_UNLESS_OVERRIDDEN
   *   Import all changes and forbid local editors to change the content unless
   *   they check the "override" checkbox. As long as that is checked, we
   *   ignore any incoming updates in favor of the local changes.
   */
  const IMPORT_UPDATE_FORCE_UNLESS_OVERRIDDEN = 'allow_override';


  /**
   * @var string ACTION_CREATE
   *   export/import the creation of this entity.
   */
  const ACTION_CREATE = 'create';
  /**
   * @var string ACTION_UPDATE
   *   export/import the update of this entity.
   */
  const ACTION_UPDATE = 'update';
  /**
   * @var string ACTION_DELETE
   *   export/import the deletion of this entity.
   */
  const ACTION_DELETE = 'delete';

  /**
   * @var string ACTION_DELETE_TRANSLATION
   *   Drupal doesn't update the ->getTranslationStatus($langcode) to
   *   TRANSLATION_REMOVED before calling hook_entity_translation_delete, so we
   *   need to use a custom action to circumvent deletions of translations of
   *   entities not being handled. This is only used for calling the
   *   ->exportEntity function. It will then be replaced by a simple
   *   ::ACTION_UPDATE.
   */
  const ACTION_DELETE_TRANSLATION = 'delete translation';


  /**
   * @var string HANDLER_IGNORE
   *    Ignore this entity type / bundle / field completely.
   */
  const HANDLER_IGNORE = 'ignore';

  /**
   * The Flow ID.
   *
   * @var string
   */
  public $id;

  /**
   * The Flow name.
   *
   * @var string
   */
  public $name;

  /**
   * The Flow entities.
   *
   * @var array
   */
  public $sync_entities;

  /**
   * The API Unify backend url.
   *
   * @var string
   */
  public $url;

  /**
   * The API name to be used.
   *
   * @var string
   */
  public $api;

  /**
   * The unique site identifier.
   *
   * @var string
   */
  public $site_id;

  /**
   * A list of all API Unify connections created for this synchronization.
   * Used by the content dashboard to display the different entity types.
   *
   * @var array
   */
  public $local_connections;

  /**
   * Check if the provided entity has just been imported by API Unify in this
   * very request. In this case it doesn't make sense to perform a remote
   * request telling API Unify it has been created/updated/deleted
   * (it will know as a result of this current request).
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to check against.
   * @param string $set_entity_type
   *   Set instead of get.
   * @param string $set_entity_uuid
   *   Set instead of get.
   *
   * @return bool
   */
  public static function entityHasBeenImportedByRemote(FieldableEntityInterface $entity, $set_entity_type = NULL, $set_entity_uuid = NULL) {
    static $entities = [];

    if ($set_entity_type && $set_entity_uuid) {
      return $entities[$set_entity_type][$set_entity_uuid] = TRUE;
    }

    return !empty($entities[$entity->getEntityTypeId()][$entity->uuid()]);
  }

  /**
   * Acts on a saved entity before the insert or update hook is invoked.
   *
   * Used after the entity is saved, but before invoking the insert or update
   * hook. Note that in case of translatable content entities this callback is
   * only fired on their current translation. It is up to the developer to
   * iterate over all translations if needed.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage object.
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // @ToDo: POOL_REFACTOR
    return;
    $exporter = new ApiUnifyConfig($this);
    $exporter->deleteConfig(TRUE);

    if (!$exporter->exportConfig()) {
      $messenger = \Drupal::messenger();
      $warning = 'The communication with the Drupal Content Sync Server failed.' .
        ' Therefore the synchronization entity could not be saved. For more' .
        ' information see the error output above.';

      $messenger->addWarning(t($warning));
      return;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);

    try {
      foreach ($entities as $entity) {
        $exporter = new ApiUnifyConfig($entity);
        $exporter->deleteConfig(FALSE);
      }
    }
    catch (RequestException $e) {
      $messenger = \Drupal::messenger();
      $messenger->addError(t('The API Unify server could not be accessed. Please check the connection.'));
      throw new AccessDeniedHttpException();
    }
  }

  /**
   * Get a unique version hash for the configuration of the provided entity type
   * and bundle.
   *
   * @param string $type_name
   *   The entity type in question.
   * @param string $bundle_name
   *   The bundle in question.
   *
   * @return string
   *   A 32 character MD5 hash of all important configuration for this entity
   *   type and bundle, representing it's current state and allowing potential
   *   conflicts from entity type updates to be handled smoothly.
   */
  public static function getEntityTypeVersion($type_name, $bundle_name) {
    $class = \Drupal::entityTypeManager()
      ->getDefinition($type_name)
      ->getOriginalClass();
    $interface = 'Drupal\Core\Entity\FieldableEntityInterface';
    if (in_array($interface, class_implements($class))) {
      $entityFieldManager = \Drupal::service('entity_field.manager');
      $field_definitions = $entityFieldManager->getFieldDefinitions($type_name, $bundle_name);

      $field_definitions_array = (array) $field_definitions;
      unset($field_definitions_array['field_drupal_content_synced']);

      $field_names = array_keys($field_definitions_array);
      sort($field_names);

      $version = json_encode($field_names);
    }
    else {
      $version = '';
    }

    $version = md5($version);
    return $version;
  }

  /**
   * Check whether the local deletion of the given entity is allowed.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   */
  public static function isLocalDeletionAllowed(EntityInterface $entity) {
    $meta_infos = MetaInformation::getInfoForEntity(
      $entity->getEntityTypeId(),
      $entity->uuid()
    );
    foreach ($meta_infos as $info) {
      if (!$info || !$info->getLastImport() || $info->isSourceEntity()) {
        continue;
      }
      $sync = $info->getSync();
      $config = $sync->getEntityTypeConfig($entity->getEntityTypeId(), $entity->bundle());
      if (!boolval($config['import_deletion_settings']['allow_local_deletion_of_import'])) {
        return FALSE;
        break;
      }
    }
    return TRUE;
  }

  /**
   * Get the correct synchronization for a specific action on a given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param string $reason
   * @param string $action
   *
   * @return \Drupal\drupal_content_sync\Entity\Flow|null
   */
  public static function getExportSynchronizationForEntity(EntityInterface $entity, $reason, $action = self::ACTION_CREATE) {
    $drupal_content_syncs = self::getAll();

    foreach ($drupal_content_syncs as $sync) {
      if ($sync->canExportEntity($entity, $reason, $action)) {
        return $sync;
      }
    }

    return NULL;
  }

  /**
   * Ask this synchronization whether or not it can export the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param string $reason
   * @param string $action
   *
   * @return bool
   */
  public function canExportEntity(EntityInterface $entity, $reason, $action = self::ACTION_CREATE) {
    $config = $this->getEntityTypeConfig($entity->getEntityTypeId(), $entity->bundle());
    if (empty($config) || $config['handler'] == self::HANDLER_IGNORE) {
      return FALSE;
    }

    if ($action == self::ACTION_DELETE && !boolval($config['export_deletion_settings']['export_deletion'])) {
      return FALSE;
    }

    /**
     * If any handler is available, we can export this entity.
     */
    if ($reason == self::EXPORT_FORCED || $config['export'] == self::EXPORT_AUTOMATICALLY) {
      return TRUE;
    }

    return $config['export'] == $reason;
  }

  /**
   * @var \Drupal\drupal_content_sync\Entity\Flow[]
   *   All content synchronization configs. Use {@see Flow::getAll}
   *   to request them.
   */
  public static $all = NULL;

  /**
   * Load all entities.
   *
   * Load all dcs_flow entities and add overrides from global $config.
   *
   * @return \Drupal\drupal_content_sync\Entity\Flow[]
   */
  public static function getAll() {
    if (self::$all !== NULL) {
      return self::$all;
    }

    /**
     * @var \Drupal\drupal_content_sync\Entity\Flow[] $configurations
     */
    $configurations = \Drupal::entityTypeManager()
      ->getStorage('dcs_flow')
      ->loadMultiple();

    foreach ($configurations as $id => &$configuration) {
      global $config;
      $config_name = 'drupal_content_sync.flow.' . $id;
      if (!isset($config[$config_name]) || empty($config[$config_name])) {
        continue;
      }
      foreach ($config[$config_name] as $key => $new_value) {
        $configuration->set($key, $new_value);
      }
      $configuration->getEntityTypeConfig();
    }

    return self::$all = $configurations;
  }

  /**
   * Get all synchronizations that allow the provided entity import.
   *
   * @param string $entity_type_name
   * @param string $bundle_name
   * @param string $reason
   * @param string $action
   * @param bool $is_clone
   *
   * @return \Drupal\drupal_content_sync\Entity\Flow[]
   */
  public static function getImportSynchronizationsForEntityType($entity_type_name, $bundle_name, $reason, $action = self::ACTION_CREATE, $is_clone = FALSE) {
    $flows = self::getAll();

    $result = [];

    foreach ($flows as $sync) {
      if ($sync->canImportEntity($entity_type_name, $bundle_name, $reason, $action, $is_clone)) {
        $result[] = $sync;
      }
    }

    return $result;
  }

  /**
   * Get the first synchronization that allows the import of the provided entity
   * type.
   *
   * @param string $api
   * @param string $entity_type_name
   * @param string $bundle_name
   * @param string $reason
   * @param string $action
   * @param bool $is_clone
   *
   * @return \Drupal\drupal_content_sync\Entity\Flow|null
   */
  public static function getImportSynchronizationForApiAndEntityType($api, $entity_type_name, $bundle_name, $reason, $action = self::ACTION_CREATE, $is_clone = FALSE) {
    $flows = self::getAll();

    foreach ($flows as $sync) {
      if ($api && $sync->api != $api) {
        continue;
      }
      if ($sync->canImportEntity($entity_type_name, $bundle_name, $reason, $action, $is_clone)) {
        return $sync;
      }
    }

    return NULL;
  }

  /**
   * Ask this synchronization whether or not it can export the provided entity.
   *
   * @param string $entity_type_name
   * @param string $bundle_name
   * @param string $reason
   * @param string $action
   * @param bool $is_clone
   *
   * @return bool
   */
  public function canImportEntity($entity_type_name, $bundle_name, $reason, $action = self::ACTION_CREATE, $is_clone = FALSE) {
    $config = $this->getEntityTypeConfig($entity_type_name, $bundle_name);
    if (empty($config) || $config['handler'] == self::HANDLER_IGNORE) {
      return FALSE;
    }
    if ($config['import_clone'] != $is_clone) {
      return FALSE;
    }
    if ($action == self::ACTION_DELETE && !boolval($config['import_deletion_settings']['import_deletion'])) {
      return FALSE;
    }
    // If any handler is available, we can import this entity.
    if ($reason == self::IMPORT_FORCED || $config['import'] == self::IMPORT_AUTOMATICALLY) {
      return TRUE;
    }
    return $config['import'] == $reason;
  }

  /**
   * Ask this synchronization whether it supports the provided entity.
   * Returns false if either the entity type is not known or the config handler
   * is set to {@see Flow::HANDLER_IGNORE}.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   */
  public function supportsEntity(EntityInterface $entity) {
    return $this->getEntityTypeConfig($entity->getEntityTypeId(), $entity->bundle())['handler'] != self::HANDLER_IGNORE;
  }

  /**
   * Get all sync configurations using the same API id.
   *
   * @param string $api_id
   *
   * @return \Drupal\drupal_content_sync\Entity\Flow[]
   */
  public static function getSynchronizationsByApi($api_id) {
    $all = self::getAll();
    $result = [];
    foreach ($all as $sync) {
      if ($sync->api != $api_id) {
        continue;
      }
      $result[] = $sync;
    }

    return $result;
  }

  /**
   * Import the provided entity.
   *
   * @param string $entity_type_name
   * @param string $entity_bundle
   * @param array $data
   * @param bool $is_clone
   * @param string $reason
   * @param string $action
   *
   * @throws \Drupal\drupal_content_sync\Exception\SyncException
   *
   * @return bool
   */
  public function importEntity($entity_type_name, $entity_bundle, array $data, $is_clone, $reason, $action = self::ACTION_CREATE) {
    $import     = time();
    $uuid       = $data['uuid'];
    $meta_infos = MetaInformation::getInfoForEntity($entity_type_name, $uuid, $this->api);
    foreach ($meta_infos as $info) {
      if (!$info) {
        continue;
      }
      if ($info->isDeleted()) {
        return TRUE;
      }
      if ($info->getLastImport() && $action == self::ACTION_CREATE) {
        $action = self::ACTION_UPDATE;
      }
    }

    $info = $meta_infos[$this->id];
    if (!$info) {
      $info = MetaInformation::create([
        'entity_type_config' => $this->id,
        'entity_type' => $entity_type_name,
        'entity_uuid' => $uuid,
        'last_import' => 0,
        'entity_type_version' => self::getEntityTypeVersion($entity_type_name, $entity_bundle),
        'flags' => 0,
        'source_url' => $data['url'],
      ]);
      if ($is_clone) {
        $info->isCloned(TRUE);
      }
      $meta_infos[$this->id] = $info;
    }
    $request = new ApiUnifyRequest($this, $entity_type_name, $entity_bundle, $uuid, $info, $data);

    $config = $this->getEntityTypeConfig($entity_type_name, $entity_bundle);
    $handler = $this->getEntityTypeHandler($config);

    self::entityHasBeenImportedByRemote(NULL, $request->getEntityType(), $request->getUuid());

    $result = $handler->import($request, $is_clone, $reason, $action);

    \Drupal::logger('drupal_content_sync')->info('@not IMPORT @action @entity_type:@bundle @uuid @reason @clone: @message', [
      '@reason' => $reason,
      '@action' => $action,
      '@entity_type'  => $entity_type_name,
      '@bundle' => $entity_bundle,
      '@uuid' => $data['uuid'],
      '@not' => $result ? '' : 'NO',
      '@clone' => $is_clone ? 'as clone' : '',
      '@message' => $result ? t('The entity has been imported.') : t('The entity handler denied to import this entity.'),
    ]);

    // Don't save meta entity if entity wasn't imported anyway.
    if (!$result) {
      return FALSE;
    }

    foreach ($meta_infos as $info) {
      if ($info) {
        $info->setLastImport($import);
        if ($action == self::ACTION_DELETE) {
          $info->isDeleted(TRUE);
        }
        $info->save();
      }
    }

    return TRUE;
  }

  /**
   * Serialize the given entity using the entity export and field export
   * handlers.
   *
   * @param array &$result
   *   The data to be provided to API Unify.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   * @param string $reason
   * @param string $action
   * @param MetaInformation $meta
   *   The meta information for this entity and sync, if any.
   *
   * @throws \Drupal\drupal_content_sync\Exception\SyncException
   *
   * @return bool
   *   Whether or not the export could be gotten.
   */
  public function getSerializedEntity(array &$result, FieldableEntityInterface $entity, $reason, $action = self::ACTION_UPDATE, MetaInformation $meta = NULL) {
    $entity_type   = $entity->getEntityTypeId();
    $entity_bundle = $entity->bundle();
    $entity_uuid   = $entity->uuid();

    $config = $this->getEntityTypeConfig($entity_type, $entity_bundle);
    $handler = $this->getEntityTypeHandler($config);

    $request = new ApiUnifyRequest($this, $entity_type, $entity_bundle, $entity_uuid, $meta);

    $status = $handler->export($request, $entity, $reason, $action);

    if (!$status) {
      return FALSE;
    }

    $result = $request->getData();

    return TRUE;
  }

  /**
   * @var array
   *   A list of all exported entities to make sure entities aren't exported
   *   multiple times during the same request in the format
   *   [$action][$entity_type][$bundle][$uuid] => TRUE
   */
  protected $exported;

  /**
   * Check whether the given entity is currently being exported. Useful to check
   * against hierarchical references as for nodes and menu items for example.
   *
   * @param string $entity_type
   *   The entity type to check for.
   * @param string $uuid
   *   The UUID of the entity in question.
   * @param null|string $action
   *   See self::ACTION_*.
   *
   * @return bool
   */
  public function isExporting($entity_type, $uuid, $action = NULL) {
    foreach ($this->exported as $do => $types) {
      if ($action ? $do != $action : $do == self::ACTION_DELETE) {
        continue;
      }
      if (!isset($types[$entity_type])) {
        continue;
      }
      foreach ($types[$entity_type] as $bundle => $entities) {
        if (!empty($entities[$uuid])) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Export the given entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   * @param string $reason
   * @param string $action
   *
   * @throws \Drupal\drupal_content_sync\Exception\SyncException
   *
   * @return bool Whether or not the entity has actually been exported.
   */
  public function exportEntity(FieldableEntityInterface $entity, $reason, $action = self::ACTION_UPDATE) {
    /**
     * @var array $deletedTranslations
     *   The translations that have been deleted. Important to notice when
     *   updates must be performed (see self::ACTION_DELETE_TRANSLATION).
     */
    static $deletedTranslations = [];

    if ($action == self::ACTION_DELETE_TRANSLATION) {
      $deletedTranslations[$entity->getEntityTypeId()][$entity->uuid()] = TRUE;
      return FALSE;
    }

    if ($entity instanceof TranslatableInterface) {
      $entity = $entity->getUntranslated();
    }
    $export = time();
    if ($entity instanceof EntityChangedInterface) {
      $export = $entity->getChangedTime();
      if ($entity instanceof TranslatableInterface) {
        foreach ($entity->getTranslationLanguages(FALSE) as $language) {
          $translation = $entity->getTranslation($language->getId());
          if ($translation->getChangedTime() > $export) {
            $export = $translation->getChangedTime();
          }
        }
      }
    }

    // If this very request was sent to delete/create this entity, ignore the
    // export as the result of this request will already tell API Unify it has
    // been deleted. Otherwise API Unify will return a reasonable 404 for
    // deletions.
    if (self::entityHasBeenImportedByRemote($entity)) {
      return FALSE;
    }

    $entity_type   = $entity->getEntityTypeId();
    $entity_bundle = $entity->bundle();
    $entity_uuid   = $entity->uuid();

    $meta_infos = MetaInformation::getInfoForEntity($entity_type, $entity_uuid, $this->api);
    $exported   = FALSE;
    foreach ($meta_infos as $info) {
      if (!$info) {
        continue;
      }
      if ($info->getLastExport()) {
        if (!$exported || $exported < $info->getLastExport()) {
          $exported = $info->getLastExport();
        }
      }
    }

    $info = $meta_infos[$this->id];
    if (!$info) {
      $info = MetaInformation::create([
        'entity_type_config' => $this->id,
        'entity_type' => $entity_type,
        'entity_uuid' => $entity_uuid,
        'last_export' => 0,
        'entity_type_version' => self::getEntityTypeVersion($entity_type, $entity_bundle),
        'flags' => 0,
      ]);
      if ($action == self::ACTION_CREATE) {
        $info->isSourceEntity(TRUE);
      }
      $meta_infos[$this->id] = $info;
    }

    if ($exported) {
      if ($action == self::ACTION_CREATE) {
        $action = self::ACTION_UPDATE;
      }
    }
    else {
      if ($action == self::ACTION_UPDATE) {
        $action = self::ACTION_CREATE;
      }
    }

    // If the entity didn't change, it doesn't have to be re-exported.
    if ($exported && $exported >= $export && $reason != self::EXPORT_FORCED &&
      $action != self::ACTION_DELETE &&
      empty($deletedTranslations[$entity->getEntityTypeId()][$entity->uuid()])) {
      return TRUE;
    }

    /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository */
    $entity_repository = \Drupal::service('entity.repository');

    $proceed = TRUE;

    if (!$this->exported) {
      $this->exported = [];
    }
    if (isset($this->exported[$action][$entity_type][$entity_bundle][$entity_uuid])) {
      return TRUE;
    }
    $this->exported[$action][$entity_type][$entity_bundle][$entity_uuid] = TRUE;

    $body = NULL;

    if ($action != self::ACTION_DELETE) {
      $body    = [];
      $proceed = $this->getSerializedEntity($body, $entity, $reason, $action, $info);

      if ($proceed) {
        if (!empty($body['embed_entities'])) {
          foreach ($body['embed_entities'] as $data) {
            try {
              /**
               * @var \Drupal\Core\Entity\FieldableEntityInterface $embed_entity
               */
              $embed_entity = $entity_repository->loadEntityByUuid($data[ApiUnifyRequest::ENTITY_TYPE_KEY], $data[ApiUnifyRequest::UUID_KEY]);
            }
            catch (\Exception $e) {
              throw new SyncException(SyncException::CODE_UNEXPECTED_EXCEPTION, $e);
            }

            if (!$this->supportsEntity($embed_entity)) {
              continue;
            }

            $this->exportEntity($embed_entity, self::EXPORT_AS_DEPENDENCY, self::ACTION_CREATE);
          }
        }
      }
    }

    \Drupal::logger('drupal_content_sync')->info('@not EXPORT @action @entity_type:@bundle @uuid @reason: @message', [
      '@reason' => $reason,
      '@action' => $action,
      '@entity_type'  => $entity_type,
      '@bundle' => $entity_bundle,
      '@uuid' => $entity_uuid,
      '@not' => $proceed ? '' : 'NO',
      '@message' => $proceed ? t('The entity has been exported.') : t('The entity handler denied to export this entity.'),
    ]);

    // Handler chose to deliberately ignore this entity,
    // e.g. a node that wasn't published yet and is not exported unpublished.
    if (!$proceed) {
      return FALSE;
    }

    $url = $this->getExternalUrl($entity_type, $entity_bundle, $action == self::ACTION_CREATE ? NULL : $entity_uuid);

    $headers = [
      'Content-Type' => 'application/json',
    ];

    $methods = [
      self::ACTION_CREATE => 'post',
      self::ACTION_UPDATE => 'put',
      self::ACTION_DELETE => 'delete',
    ];

    try {
      $client = \Drupal::httpClient();
      $response = $client->request(
        $methods[$action],
        $url,
        array_merge(['headers' => $headers], $body ? ['body' => json_encode($body)] : [])
      );
    }
    catch (\Exception $e) {
      \Drupal::logger('drupal_content_sync')->error(
        'Failed to export entity @entity_type-@entity_bundle @entity_uuid to @url' . PHP_EOL . '@message',
        [
          '@entity_type' => $entity_type,
          '@entity_bundle' => $entity_bundle,
          '@entity_uuid' => $entity_uuid,
          '@message' => $e->getMessage(),
          '@url' => $url,
        ]
      );
      throw new SyncException(SyncException::CODE_EXPORT_REQUEST_FAILED, $e);
    }

    if ($response->getStatusCode() != 200 && $response->getStatusCode() != 201) {
      \Drupal::logger('drupal_content_sync')->error(
        'Failed to export entity @entity_type-@entity_bundle @entity_uuid to @url' . PHP_EOL . 'Got status code @status_code @reason_phrase with body:' . PHP_EOL . '@body',
        [
          '@entity_type' => $entity_type,
          '@entity_bundle' => $entity_bundle,
          '@entity_uuid' => $entity_uuid,
          '@status_code' => $response->getStatusCode(),
          '@reason_phrase' => $response->getReasonPhrase(),
          '@message' => $response->getBody() . '',
          '@url' => $url,
        ]
      );
      throw new SyncException(SyncException::CODE_EXPORT_REQUEST_FAILED);
    }

    foreach ($meta_infos as $id => $info) {
      if ($info) {
        if ($id == $this->id) {
          if (!$info->getLastExport() && !$info->getLastImport()) {
            $info->set('source_url', $body['url']);
          }
        }
        $info->setLastExport($export);
        if ($action == self::ACTION_DELETE) {
          $info->isDeleted(TRUE);
        }
        $info->save();
      }
    }
    return TRUE;
  }

  /**
   * Get the config for the given entity type or all entity types.
   *
   * @param string $entity_type
   * @param string $entity_bundle
   *
   * @return array
   */
  public function getEntityTypeConfig($entity_type = NULL, $entity_bundle = NULL) {
    $entity_types = $this->sync_entities;

    $result = [];

    foreach ($entity_types as $id => &$type) {
      // Ignore field definitions.
      if (substr_count($id, '-') != 1) {
        continue;
      }

      preg_match('/^(.+)-(.+)$/', $id, $matches);

      $entity_type_name = $matches[1];
      $bundle_name      = $matches[2];

      if ($entity_type && $entity_type_name != $entity_type) {
        continue;
      }
      if ($entity_bundle && $bundle_name != $entity_bundle) {
        continue;
      }

      // If this is called before being saved, we want to have version etc.
      // available still.
      if (empty($type['version'])) {
        $type['version']          = Flow::getEntityTypeVersion($entity_type_name, $bundle_name);
        $type['entity_type_name'] = $entity_type_name;
        $type['bundle_name']      = $bundle_name;
      }

      if ($entity_type && $entity_bundle) {
        return $type;
      }

      $result[$id] = $type;
    }

    return $result;
  }

  /**
   * The the entity type handler for the given config.
   *
   * @param $config
   *   {@see Flow::getEntityTypeConfig()}
   *
   * @return \Drupal\drupal_content_sync\Plugin\EntityHandlerInterface
   */
  public function getEntityTypeHandler($config) {
    $entityPluginManager = \Drupal::service('plugin.manager.dcs_entity_handler');

    $handler = $entityPluginManager->createInstance(
      $config['handler'],
      [
        'entity_type_name' => $config['entity_type_name'],
        'bundle_name' => $config['bundle_name'],
        'settings' => $config,
        'sync' => $this,
      ]
    );

    return $handler;
  }

  /**
   * Get the correct field handler instance for this entity type and field
   * config.
   *
   * @param $entity_type_name
   * @param $bundle_name
   * @param $field_name
   *
   * @return \Drupal\drupal_content_sync\Plugin\FieldHandlerInterface
   */
  public function getFieldHandler($entity_type_name, $bundle_name, $field_name) {
    $fieldPluginManager = \Drupal::service('plugin.manager.dcs_field_handler');

    $key = $entity_type_name . '-' . $bundle_name . '-' . $field_name;
    if (empty($this->sync_entities[$key])) {
      return NULL;
    }

    if ($this->sync_entities[$key]['handler'] == self::HANDLER_IGNORE) {
      return NULL;
    }

    $entityFieldManager = \Drupal::service('entity_field.manager');
    $field_definition = $entityFieldManager->getFieldDefinitions($entity_type_name, $bundle_name)[$field_name];

    $handler = $fieldPluginManager->createInstance(
      $this->sync_entities[$key]['handler'],
      [
        'entity_type_name' => $entity_type_name,
        'bundle_name' => $bundle_name,
        'field_name' => $field_name,
        'field_definition' => $field_definition,
        'settings' => $this->sync_entities[$key],
        'sync' => $this,
      ]
    );

    return $handler;
  }

  /**
   * Wrapper for {@see Flow::getExternalConnectionPath}.
   *
   * @param string $entity_type_name
   * @param string $bundle_name
   * @param string $entity_uuid
   *
   * @return string
   */
  public function getExternalUrl($entity_type_name, $bundle_name, $entity_uuid = NULL) {
    $url = $this->url . '/' . ApiUnifyConfig::getExternalConnectionPath(
        $this->api,
        $this->site_id,
        $entity_type_name,
        $bundle_name,
        $this->sync_entities[$entity_type_name . '-' . $bundle_name]['version']
      );

    if ($entity_uuid) {
      $url .= '/' . $entity_uuid;
    }

    return $url;
  }

}
