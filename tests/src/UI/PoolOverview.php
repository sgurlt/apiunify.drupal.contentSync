<?php

namespace Drupal\Tests\drupal_content_sync\UI;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests that the Drupal Content Sync Pool overview page is reachable.
 *
 * @group dcs_ui
 */
class PoolOverview extends BrowserTestBase {
  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['drupal_content_sync'];

  /**
   * Tests that the reaction rule listing page works.
   */
  public function testPoolOverviewPage() {
    $account = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($account);

    $this->drupalGet('admin/config/services/drupal_content_sync/pool');
    $this->assertSession()->statusCodeEquals(200);

    // Test that there is an empty reaction pool listing.
    $this->assertSession()->pageTextContains('There is no Pool yet.');
  }
}