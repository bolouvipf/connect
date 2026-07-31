(function($) {
  'use strict';

  var modalHtml = '' +
    '<div class="hwc-modal-overlay" id="hwc-modal">' +
      '<div class="hwc-modal">' +
        '<button class="hwc-modal-close" id="hwc-modal-close">&times;</button>' +
        '<h3 id="hwc-modal-title">Passer une commande</h3>' +
        '<div id="hwc-modal-message"></div>' +
        '<form id="hwc-order-form">' +
          '<input type="hidden" name="item_id" id="hwc-item-id" value="" />' +
          '<div class="hwc-form-group">' +
            '<label for="hwc-name">Nom *</label>' +
            '<input type="text" id="hwc-name" name="name" required />' +
          '</div>' +
          '<div class="hwc-form-group">' +
            '<label for="hwc-email">Email *</label>' +
            '<input type="email" id="hwc-email" name="email" required />' +
          '</div>' +
          '<div class="hwc-form-group">' +
            '<label for="hwc-phone">Téléphone</label>' +
            '<input type="tel" id="hwc-phone" name="phone" />' +
          '</div>' +
          '<div class="hwc-form-group">' +
            '<label for="hwc-message">Message</label>' +
            '<textarea id="hwc-message" name="message"></textarea>' +
          '</div>' +
          '<button type="submit" class="hwc-btn hwc-btn-submit">Envoyer la commande</button>' +
        '</form>' +
      '</div>' +
    '</div>';

  $(document).on('click', '.hwc-btn-order', function(e) {
    e.preventDefault();
    var itemId = $(this).data('item-id');
    if ($('#hwc-modal').length === 0) {
      $('body').append(modalHtml);
    }
    $('#hwc-item-id').val(itemId);
    $('#hwc-modal-message').empty().hide();
    $('#hwc-modal').fadeIn(200);
  });

  $(document).on('click', '#hwc-modal-close', function() {
    $('#hwc-modal').fadeOut(200);
  });

  $(document).on('click', '.hwc-modal-overlay', function(e) {
    if (e.target === this) {
      $('#hwc-modal').fadeOut(200);
    }
  });

  $(document).on('submit', '#hwc-order-form', function(e) {
    e.preventDefault();

    var $form = $(this);
    var $message = $('#hwc-modal-message');
    $message.empty().hide();

    var data = {
      action: 'hwc_submit_order',
      nonce: hwc_ajax.nonce,
      name: $('#hwc-name').val(),
      email: $('#hwc-email').val(),
      phone: $('#hwc-phone').val(),
      message: $('#hwc-message').val(),
      item_id: $('#hwc-item-id').val()
    };

    $.post(hwc_ajax.ajax_url, data, function(response) {
      if (response.success) {
        $message
          .removeClass('hwc-message-error')
          .addClass('hwc-message-success')
          .text(response.data.message)
          .show();
        $form[0].reset();
      } else {
        $message
          .removeClass('hwc-message-success')
          .addClass('hwc-message-error')
          .text(response.data.message || 'Une erreur est survenue.')
          .show();
      }
    }).fail(function() {
      $message
        .removeClass('hwc-message-success')
        .addClass('hwc-message-error')
        .text('Erreur de connexion. Veuillez réessayer.')
        .show();
    });
  });

})(jQuery);
