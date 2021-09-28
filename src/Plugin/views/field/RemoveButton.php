<?php

namespace Drupal\commerce_ajax_fields\Plugin\views\field;

use Drupal\commerce_cart\Plugin\views\field\RemoveButton as BaseRemoveButton;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Defines a form element for removing the order item via ajax.
 *
 * @ViewsField("commerce_ajax_fields_item_remove_button")
 */
class RemoveButton extends BaseRemoveButton {

  /**
   * {@inheritdoc}
   */
  public function viewsForm(array &$form, FormStateInterface $form_state) {
    parent::viewsForm($form, $form_state);

    foreach ($this->view->result as $row_index => $row) {
      $order_item = $this->getEntity($row);
      if (!$order_item) {
        continue;
      }

      $form[$this->options['id']][$row_index] = [
        '#type' => 'submit',
        '#value' => t('Remove'),
        '#name' => 'delete-order-item-' . $row_index,
        '#remove_order_item' => TRUE,
        '#row_index' => $row_index,
        '#attributes' => [
          'class' => [
            'delete-order-item',
          ],
        ],
        '#ajax' => [
          'callback' => [static::class, 'ajaxRefresh'],
          'event' => 'click',
          'url' => Url::fromRoute('commerce_cart.page'),
          'options' => ['query' => \Drupal::request()->query->all() + [FormBuilderInterface::AJAX_FORM_REQUEST => TRUE]],
        ],
      ];
    }
    if (empty($this->view->result)) {
      unset($form['actions']);
    }
  }

  public function ajaxRefresh(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new InvokeCommand('.cart-form-ajax', 'trigger', ['RefreshView']));
    return $response;
  }

}
