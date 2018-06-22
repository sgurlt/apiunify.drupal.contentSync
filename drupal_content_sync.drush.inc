<?php

/**
 * @file
 * Contains Drush commands for Drupal Content Sync.
 */

use Drupal\drupal_content_sync\Entity\Pool;
use Drupal\drupal_content_sync\Entity\Flow;

/**
 * Implements hook_drush_command().
 */
function drupal_content_sync_drush_command() {
  $items['drupal-content-sync-export'] = [
    'description' => dt('Export configuration to the Content Sync backend.'),
    'aliases' => ['dcse'],
  ];

  return $items;
}

/**
 * Export configuration to the Content Sync backend.
 */
function drush_drupal_content_sync_export() {
  drush_print('Started export of pools.');
  foreach (Pool::getAll() as $entity) {
    drush_print('- ' . $entity->label() . ' exported');
    $entity->save();
  }
  drush_print('Finished export of pools.');
  drush_print('Started export of flows.');
  foreach (Flow::getAll() as $entity) {
    drush_print('- ' . $entity->label() . ' exported');
    $entity->save();
  }
  drush_print('Finished export of flows.');
}
