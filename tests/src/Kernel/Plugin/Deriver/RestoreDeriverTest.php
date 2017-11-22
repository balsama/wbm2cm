<?php

namespace Drupal\Tests\wbm2cm\Plugin\Deriver;

use Drupal\KernelTests\KernelTestBase;

/**
 * @covers \Drupal\wbm2cm\Plugin\Deriver\RestoreDeriver
 * @group wbm2cm
 */
class RestoreDeriverTest extends KernelTestBase {

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
      ->getDefinition('moderation_upgrade_restore:node');

    $this->assertEquals('entity:node', $migration['source']['plugin']);
    $this->assertEquals('vid', $migration['process']['vid']);
    $this->assertEquals('langcode', $migration['process']['langcode']);

    $lookup = $migration['process']['moderation_state'][0];
    $this->assertEquals(['vid', 'langcode'], $lookup['source']);
    $this->assertEquals(['moderation_upgrade_save:node'], $lookup['migration']);

    $this->assertEquals('entity_revision:node', $migration['destination']['plugin']);
    $this->assertContains('moderation_upgrade_save:node', $migration['migration_dependencies']['required']);
  }

}
