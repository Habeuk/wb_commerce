<?php
declare(strict_types = 1);

namespace Drupal\wb_commerce\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\commerce_product\Entity\ProductVariation;
use Stephane888\Debug\debugLog;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commerce_payment_simple\Services\CommercePayment\ManageOrder;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_price\CurrencyFormatter;
use Drupal\Core\Url;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\commerce_payment_simple\Controller\CommercePaymentSimpleController as CommercePaymentSimpleControllerBase;

/**
 * Returns responses for Commerce Payment Simple routes.
 */
final class CommercePaymentSimpleController extends CommercePaymentSimpleControllerBase {
  
  function __construct(private readonly ManageOrder $managePaymentOrder, private readonly CurrencyFormatter $priceFormatter) {
  }
  
  public static function create(ContainerInterface $container) {
    return new static($container->get('commerce_payment_simple.manage_order'), $container->get('commerce_price.currency_formatter'));
  }
  /**
   * Payement en un seule etape.
   */
  public function paymentOneStep(int $product_variation_id, Request $request): array {
    /**
     *
     * @var ProductVariation $productVariation
     */
    $productVariation = ProductVariation::load($product_variation_id);
    if (!$productVariation) {
      $this->messenger()->addWarning("Le produit n'est plus disponible à la vente");
      $this->getLogger('commerce_payment_simple')->warning("Tentative d'achat d'un produit inexsistant");
      debugLog::symfonyDebug($_SERVER, 'commerce_payment_simple', true);
      return $this->redirect('<front>');
    }

    $this->loadTranslate($productVariation);
    $data = $this->managePaymentOrder->CreatePaymentIntentFromProduct($productVariation);
    /**
     *
     * @var Order $order
    */
    $order = $data['order'];
    $price = $order->getTotalPrice();
    // dd('hello world', $product_variation_id, $productVariation);
    $price_formatter = $this->priceFormatter->format($price->getNumber(), $price->getCurrencyCode());
    $text_button_payment = $this->t('Pay now : ') . $price_formatter;
    
    $return_url = Url::fromRoute('commerce_payment_simple.payment_end', [
      'order_id' => $order->id()
    ], [
      'absolute' => TRUE
    ])->toString();
    // url de retour vers le service.
    $back_url = Url::fromRoute('entity.node.canonical', [
      'node' => 182
    ], [
      'absolute' => TRUE
    ])->toString();
    $form = $this->formBuilder()->getForm("Drupal\commerce_payment_simple\Form\PaymentStripeForm");
    if ($form['payment_element_wrapper']['#attributes']) {
      $form['payment_element_wrapper']['#attributes']['data-return_url'] = $return_url;
      $form['payment_element_wrapper']['#attributes']['data-stripe_public_key'] = $data['stripe_public_key'];
      $form['payment_element_wrapper']['#attributes']['data-client_secret'] = $data['client_secret'];
      $form['payment_element_wrapper']['#attributes']['data-order_id'] = $order->id();
      $form['payment_element_wrapper']['#attributes']['data-uid'] = \Drupal::currentUser()->id();
      $form['payment_element_wrapper']['submit_payment_button'][0]['#value'] = $text_button_payment;
      $form['actions']['submit']['#value'] = $text_button_payment;
    }


    // Contrôle la présence de la variable GET 'sid' et charge la soumission webform si elle existe.
    $sid = $request->query->get('sid');
    if ($sid !== null && $sid !== '') {
      $sid = (int) $sid;
      $webform_submission = $this->entityTypeManager()->getStorage('webform_submission')->load($sid);
      // dump($webform_submission->getData());
      if ($webform_submission) {
        // On expose la soumission sur la requête pour réutilisation ultérieure.
        // $request->attributes->set('webform_submission', $webform_submission);
        $webform_data = $webform_submission->getData();
        $form['information']['name_firstname']['#value'] = $webform_data["name"];
        $form['information']['email']['#value'] = $webform_data["email"];
      }
      else {
        $this->messenger()->addWarning($this->t('La soumission webform @sid n\'existe pas.', ['@sid' => $sid]));
        $this->getLogger('commerce_payment_simple')->warning('Tentative de chargement d\'une soumission webform inexistante (@sid).', ['@sid' => $sid]);
      }
    }

    $sku_vente = $this->managePaymentOrder->getSkuVente($order);
    // Désactivation du cache
    $build = [
      '#cache' => [
        'max-age' => 0
      ]
    ];
    $build['content'] = [
      '#theme' => 'commerce_payment_simple_payment_one_step_override',
      '#form' => $form,
      '#product_variation' => $productVariation,
      '#order' => $order,
      '#product_variation' => $productVariation,
      '#client_secret' => $data['client_secret'],
      '#stripe_public_key' => $data['stripe_public_key'],
      '#text_button_payment' => $text_button_payment,
      '#return_url' => $return_url,
      '#back_url_service' => $back_url,
      '#sku_vente' => $sku_vente,
      '#attached' => [
        'library' => [
          'commerce_payment_simple/stripe'
        ]
      ]
    ];
    $titlePrefix = $this->t("Payment");
    // Le module page_title recupere ce title.
    $build['#title'] = $titlePrefix . ' » ' . $productVariation->label();
    // on passe ce titre à la requete
    $request->attributes->set('_title', $build['#title']);
    $build['content']['#title'] = 'Paiement : ' . $productVariation->label();
    return $build;
  }
  
  public function paymentCompleted($order_id, Request $request): array {
    $form = [];
    $order_id = (int) $order_id;
    $Order = Order::load($order_id);
    if (!$Order) {
      throw new \Exception("La commande n'existe plus ");
    }
    $this->managePaymentOrder->validatePayment($Order);
    $webform = $this->entityTypeManager()->getStorage('webform')->load('commande_site_basic');
    $form['webform'] = $this->entityTypeManager()->getViewBuilder('webform')->view($webform);
    $titlePrefix = $this->t('Payment completed');
    $titlePrefix2 = $this->t('Reference');
    // Le module page_title recupere ce title.
    $build['#title'] = $titlePrefix . ' » ' . $titlePrefix2 . ': ' . $this->managePaymentOrder->getSkuVente($Order);
    // On passe ce titre à la requete
    $request->attributes->set('_title', $build['#title']);
    if (!empty($form['webform']['elements']['commande'])) {
      $form['webform']['elements']['commande']['#default_value'] = $Order;
      $form['webform']['elements']['commande']['#value'] = $Order->label() . ' (' . $Order->id() . ')';
    }
    return [
      '#theme' => 'commerce_payment_simple_payment_end',
      '#content' => $form,
      '#time_cache' => time(),
      // Désactivation du cache
      '#cache' => [
        'max-age' => 0
      ]
    ];
  }
  
  /**
   * Le plugin bloc more_fields_titre_de_la_page_encours recuperer le titre à
   * partir du service titleResolver, donc les titres defini via '#title' ne
   * fonctionent pas.
   *
   * @param Request $request
   * @return string
   */
  public function getTitlePage(Request $request) {
    return $request->attributes->get('_title');
  }
  
  private function loadTranslate(ContentEntityBase &$entity) {
    // on doit charger les données en fonction de la langue encours.
    $lang_code = $this->languageManager()->getCurrentLanguage()->getId();
    if ($entity->hasTranslation($lang_code)) {
      $entity = $entity->getTranslation($lang_code);
    }
  }
  
}
