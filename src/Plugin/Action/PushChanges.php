<?php

namespace Drupal\drupal_content_sync\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;

/**
 * Export the node with Drupal Content Sync.
 *
 * @Action(
 *   id = "node_drupal_content_sync_export_action",
 *   label = @Translation("Push changes"),
 *   type = "node"
 * )
 */
class PushChanges extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(FieldableEntityInterface $entity = NULL) {
    if( !$entity ) {
      return;
    }

    _drupal_content_sync_export_entity(
      $entity,
      DrupalContentSync::EXPORT_MANUALLY,
      DrupalContentSync::ACTION_CREATE
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    // TODO return 'publish drupal content sync changes' permission instead

    /** @var \Drupal\node\NodeInterface $object */
    $result = $object
      ->access('update', $account, TRUE)
      ->andIf($object->status
        ->access('edit', $account, TRUE));
    return $return_as_object ? $result : $result
      ->isAllowed();
  }

}