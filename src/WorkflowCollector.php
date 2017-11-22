<?php

namespace Drupal\wbm2cm;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\workbench_moderation\ModerationStateInterface;
use Drupal\workbench_moderation\ModerationStateTransitionInterface;

class WorkflowCollector {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The moderation state entity storage handler.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $stateStorage;

  /**
   * The moderation state transition entity storage handler.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $transitionStorage;

  /**
   * WorkflowCollector constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->stateStorage = $entity_type_manager->getStorage('moderation_state');
    $this->transitionStorage = $entity_type_manager->getStorage('moderation_state_transition');
  }

  /**
   * Returns all unique content type workflows.
   *
   * @return array
   *   An array of arrays, each of which is a set of values representing a
   *   workflow config entity.
   */
  public function getWorkflows() {
    $workflows = [];

    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $bundle */
    foreach ($this->supported() as $id => $bundle) {
      $states = $bundle->getThirdPartySetting('workbench_moderation', 'allowed_moderation_states', []);
      sort($states);
      $hash = sha1(implode('', $states));

      if (empty($workflows[$hash])) {
        $workflows[$hash] = [
          'id' => substr($hash, 0, 8),
          'type' => 'content_moderation',
          'type_settings' => [
            'states' => $this->mapStates($states),
            'transitions' => $this->mapTransitions($states),
            'entity_types' => [],
          ],
        ];
      }

      $bundle_of = $bundle->getEntityType()->getBundleOf();
      $workflows[$hash]['type_settings']['entity_types'][$bundle_of][] = $id;
    }
    foreach ($workflows as &$workflow) {
      $workflow['label'] = $this->generateLabel($workflow);
    }
    return $workflows;
  }

  /**
   * Generates a descriptive label for a Content Moderation workflow.
   *
   * @param array $workflow
   *   The workflow definition.
   *
   * @return string
   *   A label for the workflow.
   */
  protected function generateLabel(array $workflow) {
    $label = [];

    foreach ($workflow['type_settings']['entity_types'] as $entity_type_id => $bundles) {
      $label[] = $this->mapLabel($entity_type_id, $bundles);
    }
    return implode('; ', $label);
  }

  /**
   * Generates a label for a set of bundles that are enabled in a workflow.
   *
   * @param string|\Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type of which $bundles are the bundle IDs.
   * @param string[] $bundles
   *   The bundle IDs.
   *
   * @return string
   *   The label for the given set of bundles.
   */
  protected function mapLabel($entity_type, array $bundles) {
    if (is_string($entity_type)) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type);
    }

    $bundles = $this->entityTypeManager
      ->getStorage($entity_type->getBundleEntityType())
      ->loadMultiple($bundles);

    $bundle_labels = [];
    foreach ($bundles as $bundle) {
      $bundle_labels[] = $bundle->label();
    }

    return new FormattableMarkup('@bundles @entity_type', [
      '@bundles' => implode(' ', $bundle_labels),
      '@entity_type' => $entity_type->getPluralLabel(),
    ]);
  }

  /**
   * Generates Content Moderation-compatible moderation state definitions.
   *
   * @param string[] $states
   *   The moderation state entity IDs.
   *
   * @return array
   *   The Content Moderation-compatible moderation state definitions.
   */
  protected function mapStates(array $states) {
    $weight = 1;

    $map = function (ModerationStateInterface $state) use (&$weight) {
      return [
        'label' => $state->label(),
        'published' => $state->isPublishedState(),
        'default_revision' => $state->isDefaultRevisionState(),
        'weight' => $weight++,
      ];
    };
    return array_map($map, $this->stateStorage->loadMultiple($states));
  }

  /**
   * Generates Content Moderation-compatible state transition definitions.
   *
   * @param string[] $states
   *   The moderation state entity IDs for which transition definitions should
   *   be generated.
   *
   * @return array
   *   The Content Moderation-compatible state transition definitions.
   */
  protected function mapTransitions(array $states) {
    $excluded_states = array_diff(
      $this->stateStorage->getQuery()->execute(),
      $states
    );

    $transitions = $this->transitionStorage->getQuery()
      ->condition('stateFrom', $excluded_states, 'NOT IN')
      ->condition('stateTo', $excluded_states, 'NOT IN')
      ->execute();

    $weight = 1;

    $map = function (ModerationStateTransitionInterface $transition) use (&$weight) {
      return [
        'label' => $transition->label(),
        'from' => (array) $transition->getFromState(),
        'to' => $transition->getToState(),
        'weight' => $weight++,
      ];
    };
    return array_map($map, $this->transitionStorage->loadMultiple($transitions));
  }

  protected function supported() {
    foreach ($this->entityTypeManager->getDefinitions() as $id => $entity_type) {
      if ($entity_type instanceof ConfigEntityTypeInterface && $entity_type->getBundleOf()) {
        $storage = $this->entityTypeManager->getStorage($id);

        foreach ($storage->getQuery()->execute() as $entity_id) {
          /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
          $entity = $storage->load($entity_id);

          if ($entity->getThirdPartySetting('workbench_moderation', 'enabled', FALSE)) {
            yield $entity_id => $entity;
          }
        }
      }
    }
  }

}
