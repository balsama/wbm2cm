<?php

namespace Drupal\Tests\wbm2cm\Plugin\Deriver;

use Drupal\KernelTests\KernelTestBase;

/**
 * @covers \Drupal\wbm2cm\Plugin\Deriver\SaveDeriver
 * @group wbm2cm
 */
class SaveDeriverTest extends KernelTestBase {

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
      ->getDefinition('moderation_upgrade_save:node');

    $this->assertEquals('entity_moderation_state:node', $migration['source']['plugin']);
  }

}
