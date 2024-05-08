<?php

namespace Drupal\wb_commerce\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ShippingMethodFilter.
 */
class ShippingMethodFilter extends ConfigFormBase {

  /**
   * Drupal\commerce_shipping\ShippingMethodManager definition.
   *
   * @var \Drupal\commerce_shipping\ShippingMethodManager
   */
  protected $pluginManagerCommerceShippingMethod;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->pluginManagerCommerceShippingMethod = $container->get('plugin.manager.commerce_shipping_method');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'wb_commerce.shippingmethodfilter',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wb_commerce_shipping_method_filter';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('wb_commerce.shippingmethodfilter');
    $plugins = $this->pluginManagerCommerceShippingMethod->getDefinitions();

    $form['active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable the filter'),
      '#description' => $this->t('Weither or not the filter is applied'),
      '#default_value' => $config->get('active'),
    ];

    $form['available_plugins'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('available plugins'),
      '#options' => array_map(function ($element) {
        return $element["label"];
      }, $plugins),
      '#default_value' => $config->get('available_plugins') ?? array_keys($plugins),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('wb_commerce.shippingmethodfilter')
      ->set('active', $form_state->getValue('active'))
      ->set('available_plugins', $form_state->getValue('available_plugins'))
      ->save();
  }
}
