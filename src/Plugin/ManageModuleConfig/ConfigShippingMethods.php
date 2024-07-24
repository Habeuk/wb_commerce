<?php

namespace Drupal\wb_commerce\Plugin\ManageModuleConfig;

use Drupal\manage_module_config\ManageModuleConfigPluginBase;
use Drupal\Core\Url;

/**
 * Gestion Shipping methods.
 *
 * @ManageModuleConfig(
 *   id = "config_shipping_methods",
 *   label = @Translation("Shipping methods configurations"),
 *   description = @Translation("Foo description.")
 * )
 */
class ConfigShippingMethods extends ManageModuleConfigPluginBase {

  /**
   *
   * {@inheritdoc}
   * @see \Drupal\manage_module_config\ManageModuleConfigInterface::GetName()
   */
  public function GetName() {
    return $this->configuration['name'];
  }

  /**
   *
   * {@inheritdoc}
   * @see \Drupal\manage_module_config\ManageModuleConfigInterface::getRoute()
   */
  public function getRoute() {
    /**
     *
     * @var \Drupal\Core\Http\RequestStack $RequestStack
     */
    $RequestStack = \Drupal::service('request_stack');
    $Request = $RequestStack->getCurrentRequest();
    return Url::fromRoute('wb_commerce.shipping_method_list', [], []);
  }

  /**
   *
   * {@inheritdoc}
   * @see \Drupal\manage_module_config\ManageModuleConfigInterface::getDescription()
   */
  public function getDescription() {
    return $this->configuration['description'];
  }

  /**
   *
   * {@inheritdoc}
   * @see \Drupal\manage_module_config\ManageModuleConfigPluginBase::defaultConfiguration()
   */
  public function defaultConfiguration() {
    return [
      'name' => 'Shipping Methodes',
      'description' => "Permet de configurer les shipping methodes",
      'icon_svg_class' => 'btn-lg btn-sm btn-wbu-secondary',
      'icon_svg' => '<svg height="1.5em" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M18 21a2 2 0 1 1 0-4 2 2 0 0 1 0 4M7 21a2 2 0 1 1 0-4 2 2 0 0 1 0 4m9-3H9" stroke="#000" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M15.75 18a.75.75 0 0 1-1.5 0zm-13-13a.75.75 0 0 1-1.5 0zm11.5 13V7h1.5v11zM11 3.75H3v-1.5h8zM2.75 4v1h-1.5V4zM3 3.75a.25.25 0 0 0-.25.25h-1.5c0-.966.784-1.75 1.75-1.75zM14.25 7A3.25 3.25 0 0 0 11 3.75v-1.5A4.75 4.75 0 0 1 15.75 7zM9 8.25a.75.75 0 0 1 0 1.5zm-8 1.5a.75.75 0 0 1 0-1.5zm8 0H1v-1.5h8zm0 1.5a.75.75 0 0 1 0 1.5zm-5 1.5a.75.75 0 0 1 0-1.5zm5 0H4v-1.5h5z"/><path d="M20 11.25a.75.75 0 0 1 0 1.5zm-5 1.5a.75.75 0 0 1 0-1.5zm5 0h-5v-1.5h5z"/><path d="M2 16v1a1 1 0 0 0 1 1h2" stroke="#000" stroke-width="1.5" stroke-linecap="round"/><path d="M15 8h2.711c.297 0 .589.086.842.249.252.162.457.395.59.672L20 12l1.974.658A1.5 1.5 0 0 1 23 14.081V16.5a1.5 1.5 0 0 1-1.5 1.5H20" stroke="#000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
      'enable' => true
    ] + parent::defaultConfiguration();
  }
}
