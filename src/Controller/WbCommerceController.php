<?php

namespace Drupal\wb_commerce\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\commerce_shipping\Entity\ShippingMethod;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\commerce_shipping\ShippingMethodManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Class DonneeSiteInternetEntityController.
 *
 * Returns responses for Donnee site internet des utilisateurs routes.
 */
class WbCommerceController extends ControllerBase {
  /**
   *
   * @var \Drupal\domain\DomainNegotiatorInterface
   */
  protected $domainNegotiator;

  /**
   * @var \Drupal\commerce_shipping\ShippingMethodManager $ShippingMethodsManager
   */
  protected $ShippingMethodsManager;
  /**
   *
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('domain.negotiator'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_shipping_method'),
    );
  }

  /**
   *
   * @param DomainNegotiatorInterface $domainNegotiator
   * @param EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\commerce_shipping\ShippingMethodManager $pluginManager
   */
  public function __construct(DomainNegotiatorInterface $domainNegotiator, EntityTypeManagerInterface $entity_type_manager, ShippingMethodManager $shipping_method_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->domainNegotiator = $domainNegotiator;
    $this->ShippingMethodsManager = $shipping_method_manager;
  }

  public function addShippingMethod(Request $request) {
    /**
     * @var ShippingMethod $shipping_method
     */
    $shipping_method = $this->entityTypeManager()->getStorage("commerce_shipping_method")->create([
      "field_domain_access" => $this->domainNegotiator->getActiveId(),
      "field_domain_source" => $this->domainNegotiator->getActiveId()
    ]);
    $form = $this->entityFormBuilder()->getForm($shipping_method, "add");
    if ($request->isMethod("POST")) {
      $shipping_method->set("plugin", $request->get("plugin"));
      return  $this->shippingMethod($request, $shipping_method);
    } else {
      // dd($form["plugin"]['widget'][0]["target_plugin_id"]);
      $plugins = array_keys($this->ShippingMethodsManager->getDefinitions());
      $default_value = $this->formatePluginField($form);
      $form = [
        "#type" => "form",
        "plugin" => $form["plugin"]["widget"][0]["target_plugin_id"],
        "#method" => "post",
        "#action" => Url::fromRoute(
          'wb_commerce.shipping_method_add',
          [],
          [
            'query' => [
              'destination' => $request->get("destination") ?? $request->getPathInfo()
            ]
          ]
        )->toString()
      ];
      $form['submit'] = [
        '#name' => "op",
        '#type' => 'submit',
        '#value' => $this->t('Suivant'),
        '#button_type' => 'primary'
      ];
      $form["plugin"]["#default_value"] = $default_value;
      $form["plugin"]["#value"] = $default_value;
      // dd($default_value);
      //delete call back
      unset($form["#ajax"]);
      foreach ($plugins as $plugin) {
        if (isset($form[$plugin]["#ajax"])) {
          unset($form[$plugin]["#ajax"]);
          $form[$plugin]["#ajax_processed"] = false;
        }
      }
    }
    return $form;
  }

  public function duplicateShippingMethod(Request $request, ShippingMethod $shipping_method) {
    $new_shipping = $shipping_method->createDuplicate();
    $new_shipping->set('is_public', false);
    $new_shipping->set('field_domain_access', $this->domainNegotiator->getActiveId());
    $new_shipping->set('field_domain_source', $this->domainNegotiator->getActiveId());
    return $this->shippingMethod($request, $new_shipping);
  }

  public function   shippingMethod(Request $request, ShippingMethod $shipping_method) {
    $is_new = $shipping_method->isNew();
    $post_plugin = $request->get("plugin");

    $form = $this->entityFormBuilder()->getForm($shipping_method, $is_new ? "add" :  "edit");
    $pluginId =  $shipping_method->get("plugin")->getValue()[0]["target_plugin_id"] ??  $post_plugin[0]["target_plugin_id"] ??  null;
    // dump($shipping_method->toArray());
    $this->formatePluginField($form, $pluginId);


    //hide the plugin field 
    // $form["plugin"]["widget"][0]["target_plugin_id"]["#attributes"]["class"][] = "hidden";
    return $form;
  }

  /**
   * @var  array<ShippingMehod> $shippingMethods
   * @return array
   */
  protected function constructShippingMethodtable(Request $request, array &$shippingMethods, array $operation_disabled = []) {
    $datas = [];
    $header = [
      'name' => $this->t('Name'),
      'statut' => $this->t('Active'),
      'operations' => $this->t('Operations')
    ];
    $rows = [];
    foreach ($shippingMethods as &$shippingMethod) {
      $id = $shippingMethod->id();

      $operations = [
        'handle' => [
          'title' => $this->t('Edit'),
          'weight' => 0,
          'url' => Url::fromRoute(
            "wb_commerce.shipping_method",
            [
              'shipping_method' => $shippingMethod->id()
            ],
            [
              'query' => [
                'destination' => $request->getPathInfo()
              ]
            ]
          )
        ],
        'duplicate' => [
          'title' => $this->t('Duplicate'),
          'weight' => 9,
          'url' => Url::fromRoute(
            "wb_commerce.duplicate_shipping_method",
            [
              'shipping_method' => $shippingMethod->id()
            ],
            [
              'query' => [
                'destination' => $request->getPathInfo()
              ]
            ]
          )
        ],
        'delete' => [
          'title' => $this->t('Delete'),
          'weight' => 10,
          'url' => Url::fromRoute(
            "entity.commerce_shipping_method.delete_form",
            [
              'commerce_shipping_method' => $shippingMethod->id()
            ],
            [
              'query' => [
                'destination' => $request->getPathInfo()
              ]
            ]
          )
        ]
      ];
      foreach ($operation_disabled as $operation) {
        unset($operations[$operation]);
      }
      $rows[$id] = [
        'name' => $shippingMethod->hasLinkTemplate('canonical') ? [
          'data' => [
            '#type' => 'link',
            '#title' => $shippingMethod->label(),
            '#weight' => 10,
            '#url' => $shippingMethod->toUrl('canonical')
          ]
        ] : $shippingMethod->label(),
        'statut' => $shippingMethod->isEnabled() ? $this->t("Yes") : $this->t("No"),
        'operations' => [
          'data' => [
            "#type" => "operations",
            "#links" => $operations
          ]
        ]
      ];
    }
    if ($rows) {
      $build['table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#title' => 'Titre de la table',
        '#rows' => $rows,
        '#weight' => 3,
        '#empty' => 'Aucun contenu',
        '#attributes' => [
          'class' => [
            'page-content00'
          ]
        ]
      ];
      $build['pager'] = [
        '#type' => 'pager'
      ];

      $datas["table"] = $build;
    }
    return $datas;
  }


  public function ownerAccess(AccountInterface $account) {
    if (\Drupal::moduleHandler()->moduleExists('lesroidelareno')) {
      if (\Drupal\lesroidelareno\lesroidelareno::FindUserAuthorDomain() || \Drupal\lesroidelareno\lesroidelareno::isAdministrator()) {
        return AccessResult::allowed();
      }
    } elseif (in_array('administrator', $account->getRoles())) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }


  /**
   * permet de lister les paiements et de les configurees par le prorietaire du
   * site.
   */
  public function shippingMethodsList(Request $request) {
    if ($this->ownerAccess(\Drupal::currentUser())->isForbidden()) {
      return $this->forbittenMessage();
    }

    $shipping_methods_manager = $this->entityTypeManager()->getStorage("commerce_shipping_method");
    $duplicate = $request->get("duplicate");
    $requiredProperties = [];
    $operations_disabled = [];
    if (isset($duplicate)) {
      $requiredProperties["is_public"] = true;
      $operations_disabled = ["handle", "delete"];
      // Build the render array for the button.
    } else {
      $requiredProperties["field_domain_access"] = $this->domainNegotiator->getActiveId();
      $datas['action_buttons'] = [
        "#type" => "container",
        "add_method" => [
          '#type' => 'link',
          '#title' => $this->t("Add a method"),
          '#url' => Url::fromRoute('wb_commerce.shipping_method_add', [], [
            'query' => [
              'destination' => $request->getPathInfo()
            ]
          ]),
          '#attributes' => [
            "class" => ["button", "button--primary", "button--action"]
          ]
        ],
        "api_login" => [
          '#type' => 'link',
          '#title' => $this->t("api login"),
          '#url' => Url::fromRoute("hbkcolissimochrono.settings", [], [
            'query' => [
              'domain_config_ui_domain' => $this->domainNegotiator->getActiveId(),
              'domain_config_ui_language' => '',
              'destination' => $request->getPathInfo()
            ],
            'absolute' => TRUE
          ]),
          '#attributes' => [
            "class" => ["button", "claro-details__wrapper"]
          ]
        ],
        "duplicate_public_method" => [
          '#type' => 'link',
          '#title' => $this->t("Duplicate a public shipping method"),
          '#url' => Url::fromRoute("wb_commerce.shipping_method_list", [
            'destination' => $request->getPathInfo(),
            'duplicate' => ''
          ]),
          '#attributes' => [
            "class" => ["button"]
          ]
        ]
      ];
    }
    /**
     * chargement des moyens de paiement que peuvent utiliser les clients wb-horizon
     * @var array<ShippingMethod> $shippingMethods
     */
    $shippingMethods = $shipping_methods_manager->loadByProperties($requiredProperties);

    $datas[] = $this->constructShippingMethodtable($request, $shippingMethods, $operations_disabled);
    return $datas;
  }


  protected function formatePluginField(array &$form, $pluginId = null) {
    $config = $this->config('wb_commerce.shippingmethodfilter');
    $filter_active = $config->get('active');
    $pluginsToDisable = [];
    $pluginsToFormat = [];
    if ((is_null($filter_active) | !$filter_active) && !isset($pluginId)) {
      //if the filter is not active and no plugin have been selected
      //allow all plugins
      $pluginsToDisable = [];
    } else {
      if (isset($pluginId)) {
        $plugins =         $this->ShippingMethodsManager->getDefinitions();        // a plugin have been selected.
        // disable every plugin except the selected one
        $pluginsToFormat = [
          $pluginId => $config->get("plugins")[$pluginId] ?? $plugins[$pluginId]
        ];
        unset($plugins[$pluginId]);
        $pluginsToDisable = array_keys($plugins);
      } else {
        //the filter is activated and a plugin haven't been selected yet
        //allow only the activated plugins of the configuration
        $pluginsToDisable = array_keys(
          array_filter($config->get("plugins"), function ($element) {
            return !(bool) $element["active"];
          })
        );
        $pluginsToFormat = array_filter($config->get("plugins"), function ($plugin) {

          return (bool)$plugin["active"];
        });
      }
    }
    foreach ($pluginsToDisable as $pluginId) {
      unset($form["plugin"]["widget"][0]["target_plugin_id"][$pluginId]);
      unset($form["plugin"]["widget"][0]["target_plugin_id"]["#options"][$pluginId]);
    }

    //disabled is_public field 
    unset($form["is_public"]);

    //formatter les plugins actifs
    $form["plugin"]["widget"][0]["target_plugin_id"]["#ajax"]["callback"] = ["::pluginUpdate"];
    $form["plugin"]["widget"][0]["target_plugin_id"]["#default_value"] = $pluginsToFormat[0] ?? "";
    $form["plugin"]["widget"][0]["target_plugin_id"]["#value"] = $pluginsToFormat[0] ?? "";
    foreach ($pluginsToFormat as $pluginId => $plugin) {
      $currentPlugin = &$form["plugin"]["widget"][0]["target_plugin_id"][$pluginId];
      $currentPlugin["#title"] = $plugin["label"];
    }
  }

  /**
   * Le but de cette fonction est de notifier l'administrateur l'acces à des
   * informations senssible.
   *
   * @param string $message
   * @param array $context
   * @return array
   */
  protected function forbittenMessage($message = "Access non authoriser", $context = []) {
    $this->getLogger("wb_commerce")->critical($message, $context);
    $this->messenger()->addError($message);
    return [];
  }
}
