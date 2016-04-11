<?php

/**
 * @file
 * Contains \Drupal\simple_rest\Plugin\rest\resource\EntityResource.
 */

namespace Drupal\simple_rest\Plugin\rest\resource;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Entity;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Represents entities as resources.
 *
 * @RestResource(
 *   id = "simple_entity",
 *   label = @Translation("Entity"),
 *   serialization_class = "Drupal\Core\Entity\Entity",
 *   deriver = "Drupal\simple_rest\Plugin\Deriver\EntityDeriver",
 *   uri_paths = {
 *     "canonical" = "simple/entity/{entity_type}/{entity}",
 *     "https://www.drupal.org/link-relations/create" = "/entity/{entity_type}"
 *   }
 * )
 *
 * @see \Drupal\simple_rest\Plugin\Deriver\EntityDeriver
 */
class EntityResource extends ResourceBase {

  /**
   * Responds to entity GET requests.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the entity with its accessible fields.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function get(EntityInterface $entity) {
    $entity_access = $entity->access('view', NULL, TRUE);
    if (!$entity_access->isAllowed()) {
      throw new AccessDeniedHttpException();
    }

    $simple_data = $this->simplifyEntity($entity);
    $response = new ResourceResponse($simple_data, 200);
    $response->addCacheableDependency($entity);
    $response->addCacheableDependency($entity_access);
    foreach ($entity as $field_name => $field) {
      /** @var \Drupal\Core\Field\FieldItemListInterface $field */
      $field_access = $field->access('view', NULL, TRUE);
      $response->addCacheableDependency($field_access);

      if (!$field_access->isAllowed()) {
        $entity->set($field_name, NULL);
      }
    }

    return $response;
  }

  protected function simplifyEntity(EntityInterface $entity) {
    $simple_data = [];
    /** @var ContentEntityInterface $entity */
    foreach ($entity->getFieldDefinitions() as $field_name => $field) {
      $storage = $field->getFieldStorageDefinition();
      if ($storage->getCardinality() == 1) {
        $field_value  = $entity->get($field_name)->getValue()[0];
        $simple_data[$field_name] = $this->simplifyValue($field_value);
      }
      else {
        $simple_data[$field_name] = [];
        $field_values = $entity->get($field_name)->getValue();
        foreach ($field_values as $field_value) {
          $simple_data[$field_name][] = $this->simplifyValue($field_value);
        }
      }

    }
    return $simple_data;
  }

  /**
   * {@inheritdoc}
   */
  protected function getBaseRoute($canonical_path, $method) {
    $route = parent::getBaseRoute($canonical_path, $method);
    $definition = $this->getPluginDefinition();

    $parameters = $route->getOption('parameters') ?: array();
    $parameters[$definition['entity_type']]['type'] = 'entity:' . $definition['entity_type'];
    $route->setOption('parameters', $parameters);

    return $route;
  }

  protected function simplifyValue($field_value) {
    if (count($field_value) == 1) {
      return array_pop($field_value);
    }
    return $field_value;
  }


}
