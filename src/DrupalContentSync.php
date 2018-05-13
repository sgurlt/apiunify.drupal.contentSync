<?php

namespace Drupal\drupal_content_sync;

use Drupal\Core\Entity\EntityInterface;
use Drupal\drupal_content_sync\Entity\Flow;
use Drupal\drupal_content_sync\Entity\MetaInformation;
use Drupal\drupal_content_sync\Exception\SyncException;

/**
 * Class DrupalContentSync handles all requests regarding synchronization,
 * forwarding requests to the correct Flow and Pool, handling meta information
 * etc.
 * This is the root of all sync logic handling.
 */
class DrupalContentSync {

  /**
   * Helper function to export an entity and display the user the results. If
   * you want to make changes programmatically, create your own handler instead.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to export.
   * @param string $reason
   *   {@see Flow::EXPORT_*}.
   * @param string $action
   *   {@see Flow::ACTION_*}.
   * @param \Drupal\drupal_content_sync\Entity\Flow $sync
   *   The sync to be used. First matching sync will be used if none is given.
   *
   * @return bool Whether the entity is configured to be exported or not.
   *
   * @throws \Drupal\drupal_content_sync\Exception\SyncException
   */
  public static function exportEntity(EntityInterface $entity, $reason, $action, Flow $sync = NULL) {
    if (!$sync) {
      $sync = Flow::getExportSynchronizationForEntity($entity, $reason, $action);
      if (!$sync) {
        // If this entity has been exported as a dependency, we want to export the
        // Update and deletion automatically as well.
        if ($reason == Flow::EXPORT_AUTOMATICALLY &&
          $action != Flow::ACTION_CREATE) {
          $sync = Flow::getExportSynchronizationForEntity(
            $entity,
            Flow::EXPORT_AS_DEPENDENCY,
            $action
          );
          if ($sync) {
            $info = MetaInformation::getInfoForEntity(
              $entity->getEntityTypeId(),
              $entity->uuid()
            )[$sync->id];
            if (!$info || !$info->getLastExport()) {
              return FALSE;
            }
          }
          else {
            return FALSE;
          }
        }
        else {
          return FALSE;
        }
      }
    }

    return $sync->exportEntity($entity, $reason, $action);
  }

  /**
   * Helper function to export an entity and display the user the results. If
   * you want to make changes programmatically, use ::exportEntity() instead.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to export.
   * @param string $reason
   *   {@see Flow::EXPORT_*}.
   * @param string $action
   *   {@see Flow::ACTION_*}.
   * @param \Drupal\drupal_content_sync\Entity\Flow $sync
   *   The sync to be used. First matching sync will be used if none is given.
   *
   * @return bool Whether the entity is configured to be exported or not.
   */
  public static function exportEntityFromUi(EntityInterface $entity, $reason, $action, Flow $sync = NULL) {
    $messenger = \Drupal::messenger();
    try {
      $status = self::exportEntity($entity,$reason,$action,$sync);

      if ($status) {
        $messenger->addMessage(t('%label has been exported with Drupal Content Sync.', ['%label' => $entity->label()]));
        return TRUE;
      }
      return FALSE;
    }
    catch (SyncException $e) {
      $message = $e->parentException ? $e->parentException->getMessage() : (
      $e->errorCode == $e->getMessage() ? '' : $e->getMessage()
      );
      if ($message) {
        $messenger->addWarning(t('Failed to export %label with Drupal Content Sync (%code). Message: %message', [
          '%label' => $entity->label(),
          '%code' => $e->errorCode,
          '%message' => $message,
        ]));
      }
      else {
        $messenger->addWarning(t('Failed to export %label with Drupal Content Sync (%code).', [
          '%label' => $entity->label(),
          '%code' => $e->errorCode,
        ]));
      }
      return TRUE;
    }
  }
}
