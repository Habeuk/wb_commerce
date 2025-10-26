<?php

namespace Drupal\wb_commerce\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class RouteSubscriber.
 *
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $routes_to_override = [
      "hbkcolissimochrono.settings",
      "wb_commerce.shipping_method_list",
      "wb_commerce.shipping_method_add",
      "wb_commerce.shipping_method",
      "wb_commerce.duplicate_shipping_method",
      "wb_commerce.wb_commerce_shipping_method_filter",
    ];
    $route = null;
    foreach ($routes_to_override as $route_name) {
      $route = $collection->get($route_name);
      if ($route) {
        $route->setRequirements(
          [
            "_permission" => "access content",
            "_role" => "gerant_de_site_web+administrator",
            "_custom_access" => "\Drupal\wb_commerce\Controller\WbCommerceController::ownerAccess"
          ]
        );
      }
    }

    if ($route = $collection->get('commerce_payment_simple.payment_one_step')) {
      $route->setDefault('_controller', '\Drupal\wb_commerce\Controller\CommercePaymentSimpleController::paymentOneStep');
    }
    if ($route = $collection->get('commerce_payment_simple.payment_end')) {
      $route->setDefault('_controller', '\Drupal\wb_commerce\Controller\CommercePaymentSimpleController::paymentCompletedOverride');
    }
  }
}
