/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'Magento_Ui/js/modal/modal',
    'jquery',
    'mage/translate'
], function (alert, $, $t) {
    'use strict';

    var reasonModal;
    var $form = $('form.change-transaction-reason');

    var mpEditTransactionStatusPopup = function () {
        if (!reasonModal) {
            reasonModal = $('#mp_edit_transaction_reason').modal({
                title: 'Set Reason',
                content: 'Warning content',
                buttons: []
            });
        }

        reasonModal.modal('openModal');
    };

    var mpSaveNewStatusFormPost = function ( postUrl ) {

        if ($form.valid()) {
            var url = $form.attr('action');
            var postData = $form.serializeArray();
            postData.push({form_key : FORM_KEY});

            try {
                $.ajax({
                    url: url,
                    dataType: 'json',
                    type: 'POST',
                    showLoader: true,
                    data: $.param(postData),
                    complete: function (data) {
                        document.location.reload();
                    }
                });
            } catch (e) {
                $(".mage-error").html(e.message);
            }
        } else {
            $("div.menu-wrapper._fixed").removeAttr("style");
        }

        return false;

    };

    return function (config) {

        $('#change_transaction_status').click(function () {
            mpEditTransactionStatusPopup();
        });

        $('form.change-transaction-reason button').on('click', function () {
            mpSaveNewStatusFormPost(config.postUrl);
        });

        $form.on("keypress", function (event) {
            if (event.keyCode === 13) {
                mpSaveNewStatusFormPost(config.postUrl);
            }

            return event.keyCode != 13;
        });

        $form.submit(function (event) {
            return false;
        });
    }
});
