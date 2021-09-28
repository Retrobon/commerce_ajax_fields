<?php

namespace Drupal\commerce_ajax_fields\Plugin\views\field;

use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_cart\Plugin\views\field\EditQuantity as BaseEditQuantity;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form element for editing the order item quantity.
 *
 * @ViewsField("commerce_ajax_fields_item_edit_quantity")
 */
class EditQuantity extends BaseEditQuantity {

  /**
   * {@inheritdoc}
   */
  public function viewsForm(array &$form, FormStateInterface $form_state) {
    parent::viewsForm($form, $form_state);

    $form['#attached']['library'][] = 'commerce_ajax_fields/commerce_ajax_fields';

    $form['view_result'] = [
      '#type' => 'value',
      '#value' => $this->view->result,
    ];
    foreach ($this->view->result as $row_index => $row) {
      $form[$this->options['id']][$row_index] += [
        '#show_update_message' => FALSE,
        '#ajax' => [
          'callback' => [static::class, 'ajaxRefreshSummary'],
          'keypress' => TRUE,
          'event' => 'change keyup_debounced',
          'effect' => 'fade',
          'prevent' => 'submit',
        ],
      ];
    }
    $form['actions']['submit']['#update_cart'] = FALSE;
    unset($form['actions']['submit']);
  }

  /**
   * Ajax callback function to refresh cart item summary on change of quantity
   * field.
   */
  public static function ajaxRefreshSummary(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $triggering_element = $form_state->getTriggeringElement();
    /** @var CartProviderInterface $cpi */
    $cartProvider = \Drupal::service('commerce_cart.cart_provider');
    $cart_manager = \Drupal::service('commerce_cart.cart_manager');
    /** @var \Drupal\commerce_order\Entity\OrderInterface[] $carts */
    $carts = $cartProvider->getCarts();
    $carts = array_filter($carts, function ($cart) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $cart */
      return $cart->cart->value;
    });

    $order = reset($carts);
    $quantities = $form_state->getValue('commerce_ajax_fields_edit_quantity', []);
    $view_result = $form_state->getValue('view_result', []);

    $save_cart = FALSE;

    foreach ($quantities as $row_index => $quantity) {
      if (is_numeric($quantity) && floor($quantity) != $quantity) {
        continue;
      }
      if (!is_numeric($quantity) || $quantity < 0) {
        continue;
      }

      /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
      $resultRow = $view_result[$row_index];
      $order_item = $resultRow->_relationship_entities['order_items'];
      $order_id = $order_item->get('order_id')->target_id;
      if ($order_item->getQuantity() == $quantity) {
        continue;
      }

      if ($quantity > 0) {
        $order_item->setQuantity($quantity);
        $cart_manager->updateOrderItem($order, $order_item, FALSE);
      }
      else {
        $cart_manager->removeOrderItem($order, $order_item, FALSE);
        $response->addCommand(new RemoveCommand('#views-form-commerce-cart-form-default-' . $order_id));
      }

      $order_item_total = $order_item->get('total_price')->view([
        'label' => 'hidden',
        'type' => 'commerce_order_total_summary',
      ]);

      $response->addCommand(new HtmlCommand('#views-form-commerce-cart-form-default-' . $order_id . ' .quantity .cart-body', '<b>' . $quantity . '</b>'));
      $response->addCommand(new HtmlCommand('#views-form-commerce-cart-form-default-' . $order_id . ' .total-price__number .cart-body', '<b>' . \Drupal::service('renderer')
          ->render($order_item_total) . '</b>'));
      $save_cart = TRUE;
    }

    if ($save_cart) {
      $order->save();
      if (!empty($triggering_element['#show_update_message'])) {
        $response->addCommand(new MessageCommand(t('Your shopping cart has been updated.')));
      }
    }
    $order_total = $order->get('total_price')->view([
      'label' => 'hidden',
      'type' => 'commerce_order_total_summary',
    ]);
    $order_total['#prefix'] = '<div data-drupal-selector="order-total-summary">';
    $order_total['#suffix'] = '</div>';

    $rendered_total = \Drupal::service('renderer')->render($order_total);

    if (isset($order_total)) {
      $response->addCommand(new ReplaceCommand('#views-form-commerce-cart-form-default-' . $order_id . ' [data-drupal-selector="order-total-summary"]', $rendered_total));
    }

    $status_messages = ['#type' => 'status_messages'];
    $messages = \Drupal::service('renderer')->renderRoot($status_messages);
    if (!empty($messages)) {
      $response->addCommand(new MessageCommand($messages));
    }

    return $response;
  }

}
