<?php

/**
 * @file
 * Contains \Drupal\simple_rest\Routing\RouteSubscriber.
 */

namespace Drupal\simple_rest\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class RouteSubscriber.
 *
 * @package Drupal\simple_rest\Routing
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {
  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // @todo Dependency injection
    $entityTypeManager = \Drupal::entityTypeManager();
    $entity_types = $entityTypeManager->getDefinitions();
    $a = 'b';
    foreach ($collection->all() as $id => $route) {
      $route_id_parts = explode('.', $id);
      if (count($route_id_parts) == 5 && $route_id_parts[0] == 'rest'
        && $route_id_parts[1] == 'entity'
      ) {
        $route_entity_type = $route_id_parts[2];
        $method = $route_id_parts[3];
        if (isset($entity_types[$route_entity_type]) && $method == 'GET') {
          $simple_route = clone $route;
          $simple_route->setPath('/simple' . $route->getPath());
          //$simple_route->setDefault('_controller', 'Drupal\simple_rest\RequestHandler::handle');
          $simple_route->setDefault('_plugin', "simple_entity:entity:$route_entity_type");
          $route_id_parts['0'] = 'simple_rest';
          $simple_route_id = implode('.', $route_id_parts);
          $collection->add($simple_route_id, $simple_route);
        }
      }

    }

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Come after rest module.

    $events[RoutingEvents::ALTER] = array('onAlterRoutes', -110);
    return $events;
  }

}
