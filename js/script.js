(function ($, Drupal) {
  Drupal.behaviors.cartBehavior = {
    attach: function (context, settings) {

      $('<div class="quantity-nav"><div class="quantity-button quantity-up">+</div><div class="quantity-button quantity-down">-</div></div>').insertAfter('.views-field-commerce-ajax-fields-edit-quantity .form-item', context);

      $('.quantity-up', context).once().click(function () {
        let $input = $(this).closest('.views-field').find('input');
        $input.val(parseInt($input.val()) + 1);
        return false;
      });

      $('.quantity-down', context).once().click(function () {
        var $input = $(this).closest('.views-field').find('input');
        var count = parseInt($input.val()) - 1;
        count = count < 1 ? 1 : count;
        $input.val(count);
        return false;
      });

      let timeout = null;

      let quantityElts = document.querySelectorAll('.quantity-button');
      quantityElts.forEach(quantityElt => {
        quantityElt.addEventListener('click', btnEvent, false);
      });

      function btnEvent(e) {
        let $target = $(this).closest('.views-field').find('input');
        clearTimeout(e.currentTarget.timeout);
        e.currentTarget.timeout = setTimeout(function (e) {
          inputChange($target);
        }, 1000);
      }

      function inputChange($input) {
        $input.trigger('change');
      }

      $('input.quantity-edit-input').each(function () {
        var $self = $(this);
        var timeout = null;
        var triggerEvent = $self.data('event') || "keyup_debounced";

        $self.unbind('keyup').keyup(function () {
          clearTimeout(timeout);
          timeout = setTimeout(function () {
            $self.trigger(triggerEvent);
          }, 1000);
        });
      });

    }
  };
})(jQuery, Drupal);

