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

    $form['plugins'] = [
      '#type' => 'details',
      '#title' => $this->t('Plugins'),
      "#tree" => true,
      '#open' => true, // Set to TRUE to have this open by default.
    ];
    foreach ($plugins as $plugin_id => $plugin) {
      $active = $config->get('plugins')[$plugin_id]['active'] ?? true;
      $form['plugins'][$plugin_id] = [
        '#type' => 'details',
        '#title' => $plugin["label"],
        '#open' => $active,
        '#tree' => true
      ];

      $form['plugins'][$plugin_id]['active'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Active'),
        '#default_value' => $active
      ];

      $form['plugins'][$plugin_id]['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#default_value' => $config->get('plugins')[$plugin_id]['label'] ?? $plugin["label"]
      ];
      $open = false;
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    // dd($form_state);
    $this->config('wb_commerce.shippingmethodfilter')
      ->set('active', $form_state->getValue('active'))
      ->set('plugins', $form_state->getValue('plugins'))
      ->save();
  }
}
