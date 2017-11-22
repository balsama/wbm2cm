<?php

namespace Drupal\Tests\wbm2cm\Plugin\Deriver;

use Drupal\KernelTests\KernelTestBase;

/**
 * @covers \Drupal\wbm2cm\Plugin\Deriver\ClearDeriver
 * @group wbm2cm
 */
class ClearDeriverTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'migrate',
    'moderation_upgrade',
    'node',
    'options',
    'system',
    'text',
    'user',
    'views',
    'workbench_moderation',
  ];

  public function testDeriver() {
    $migration = $this->container->get('plugin.manager.migration')
      ->getDefinition('moderation_upgrade_clear:node');

    $this->assertEquals('entity_moderation_state:node', $migration['source']['plugin']);
    $this->assertEquals('vid', $migration['process']['vid']);
    $this->assertEquals('langcode', $migration['process']['langcode']);
    $this->assertEquals('entity_revision:node', $migration['destination']['plugin']);
    $this->assertContains('moderation_upgrade_save:node', $migration['migration_dependencies']['required']);
  }

}
