<?php

namespace Drupal\wb_commerce\Controller;

use Drupal\Core\Controller\ControllerBase;
use Stephane888\Debug\Repositories\ConfigDrupal;
use Stephane888\DrupalUtility\HttpResponse;
use Drupal\prise_rendez_vous\Entity\RdvConfigEntity;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_shipping\Entity\ShippingMethod;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\lesroidelareno\Entity\CommercePaymentConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\lesroidelareno\lesroidelareno;
use Drupal\commerce_shipping\ShippingMethodManager;
use PhpParser\Node\Expr\Isset_;

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


  /**
   * @return array
   */
  private function preHandleForm(array &$form) {
    $pluginsToDisable = [
      // "flat_rate_per_item",
      // "free_shipping_wb_horizon"
    ];

    $fieldsToDisable = [
      // "field_domain_access",
      // "field_domain_source",
    ];


    foreach ($pluginsToDisable as $pluginId) {
      unset($form["plugin"]["widget"][0]["target_plugin_id"][$pluginId]);
      unset($form["plugin"]["widget"][0]["target_plugin_id"]["#options"][$pluginId]);
    }

    foreach ($fieldsToDisable as $field) {
      $form[$field]["#access"] = false;
    }
    return $form;
  }

  public function addShippingMethod(Request $request) {

    $shipping_method = $this->entityTypeManager()->getStorage("commerce_shipping_method")->create([
      "field_domain_access" => $this->domainNegotiator->getActiveId(),
      "field_domain_source" => $this->domainNegotiator->getActiveId()
    ]);

    $form = $this->entityFormBuilder()->getForm($shipping_method, "add");
    $this->preHandleForm($form);
    if ($request->isMethod("POST")) {
      $form["plugin"]["widget"][0]["target_plugin_id"]["#access"] = false;
      return  $form;
    } else {
      $plugins = array_keys($this->ShippingMethodsManager->getDefinitions());
      $form = [
        "#type" => "form",
        "target_plugin_id" => $form["plugin"]["widget"][0]["target_plugin_id"],
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

  public function shippingMethod(Request $request, ShippingMethod $shipping_method) {
    $form = $this->entityFormBuilder()->getForm($shipping_method, "edit");
    $this->preHandleForm($form);
    $form["plugin"]["widget"][0]["target_plugin_id"]["#access"] = false;
    return $form;
  }

  /**
   * permet de lister les paiements et de les configurees par le prorietaire du
   * site.
   */
  public function shippingMethodsList(Request $request) {
    if (!lesroidelareno::userIsAdministratorSite() && lesroidelareno::FindUserAuthorDomain()) {
      return $this->forbittenMessage();
    }

    $shipping_methods_manager = $this->entityTypeManager()->getStorage("commerce_shipping_method");
    $datas = [];

    /**
     * chargement des moyens de paiement que peuvent utiliser les clients wb-horizon
     * @var array<ShippingMethod> $shippingMethods
     */
    $shippingMethods = $shipping_methods_manager->loadByProperties([
      "field_domain_access" => $this->domainNegotiator->getActiveId()
    ]);

    // Build the render array for the button.
    $datas['action_button'] = [
      '#type' => 'link',
      '#title' => $this->t("Add a method"),
      '#url' => Url::fromRoute('wb_commerce.shipping_method_add', [], [
        'query' => [
          'destination' => $request->getPathInfo()
        ]
      ]),
      '#attributes' => [
        "class" => ["button", "button--primary"]
      ]
    ];
    $header = [
      'name' => $this->t('Name'),
      'statut' => $this->t('Active'),
      'operations' => $this->t('Operations')
    ];
    $rows = [];
    foreach ($shippingMethods as &$shippingMethod) {
      $id = $shippingMethod->id();
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
            "#links" => [
              'handle' => [
                'title' => $this->t('Edit'),
                'weight' => 10,
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
            ]
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