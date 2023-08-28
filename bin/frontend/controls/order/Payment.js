/**
 * @module package/quiqqer/payments/bin/frontend/controls/order/Payment
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/payments/bin/frontend/controls/order/Payment', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'Ajax',
    'Locale',

    'css!package/quiqqer/payments/bin/frontend/controls/order/Payment.css'

], function(QUI, QUIControl, QUIConfirm, QUIAjax, QUILocale) {
    'use strict';

    const lg = 'quiqqer/payments';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/payments/bin/frontend/controls/order/Payment',

        Binds: [
            '$onClick'
        ],

        initialize: function(options) {
            this.parent(options);

            this.$Input = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function() {
            this.getElm().addEvent('click', this.$onClick);

            this.$Input = this.getElm().getElement('input');

            if (this.$Input.checked) {
                this.getElm().addClass('selected');
            }
        },

        /**
         * event: on click
         */
        $onClick: function(event) {
            if (event.target.nodeName !== 'INPUT') {
                event.stop();
            }

            let List = this.getElm().getParent('.quiqqer-order-step-payments-list');
            let Entry = List.getElements('.quiqqer-order-step-payments-list-entry');
            Entry.removeClass('selected');

            const isSupported = !!parseInt(this.getElm().get('data-is-supported'));

            if (isSupported === false) {
                new QUIConfirm({
                    icon: 'fa fa-money',
                    texticon: false,
                    title: QUILocale.get(lg, 'order.change.to.supported.payment.title'),
                    text: QUILocale.get(lg, 'order.change.to.supported.payment.text'),
                    information: QUILocale.get(lg, 'order.change.to.supported.payment.information'),
                    maxHeight: 500,
                    maxWidth: 600,
                    autoclose: false,
                    cancel_button: {
                        text: QUILocale.get(lg, 'order.change.to.supported.payment.btnCancel'),
                        textimage: 'fa fa-remove'
                    },

                    ok_button: {
                        text: QUILocale.get(lg, 'order.change.to.supported.payment.btnSubmit'),
                        textimage: 'fa fa-check'
                    },
                    events: {
                        onOpen: (Win) => {
                            Win.Loader.show();

                            const Currencies = new Element('form', {
                                'class': 'quiqqer-payments-supportedPayments-currency-change'
                            }).inject(Win.getContent());

                            QUIAjax.get(
                                'package_quiqqer_payments_ajax_frontend_getAvailablePaymentCurrencies',
                                function(currencies) {

                                    currencies.forEach((currency) => {
                                        new Element('label', {
                                            html: '<input type="radio" name="currency" value="' + currency.code + '" ' +
                                                '<span>' + currency.text + '</span>'
                                        }).inject(Currencies);
                                    });

                                    Win.Loader.hide();
                                },
                                {
                                    'package': 'quiqqer/payments',
                                    paymentId: this.getElm().getElement('[name="payment"]').value
                                }
                            );
                        },

                        onSubmit: (Win) => {
                            if (!Win.getContent().getElement('[name="currency"]:checked')) {
                                return;
                            }

                            Win.Loader.show();

                            this.$Input.checked = true;
                            this.getElm().addClass('selected');

                            const currency = Win.getContent().getElement('[name="currency"]:checked').value;


                            QUIAjax.post('package_quiqqer_currency_ajax_setUserCurrency', () => {
                                window.DEFAULT_USER_CURRENCY = currency;

                                const orderProcess = this.getElm().getParent(
                                    '[data-qui="package/quiqqer/order/bin/frontend/controls/OrderProcess"]'
                                );

                                if (orderProcess) {
                                    QUI.Controls.getById(orderProcess.get('data-quiid')).refreshCurrentStep();
                                }

                                Win.close();
                            }, {
                                'package': 'quiqqer/currency',
                                currency: currency
                            });
                        },

                        onCancel: function() {
                            if (Entry) {
                                Entry.addClass('selected');
                            }
                        }
                    }
                }).open();

                return;
            }

            this.$Input.checked = true;
            this.getElm().addClass('selected');
        }
    });
});