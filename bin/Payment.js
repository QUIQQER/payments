/**
 * Parent class for a payment
 *
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event onSelect [ self ]
 * @event onUnselect [ self ]
 * @event onOpen [ self, {DOMNode} ]
 * @event onClose [ self ]
 *
 * @deprecated  OLD, aus NR ... muss umgeschrieben werden!!!
 */

define('package/quiqqer/payments/bin/Payment', [

    'qui/classes/Control',
    'css!package/quiqqer/payments/bin/Payment.css'

], function (QUIControl) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/payments/bin/Payment',

        // defaults
        options: {
            name               : '',
            gateway            : false,        // use the payment a gateway?
            successType        : '',           // what successType is the payment?
            icon               : URL_OPT_DIR + 'bin/quiqqer/payments/zahlung_platzhalter.png',
            checkoutPaymentData: false  // exist extra needed data?
        },

        initialize: function (options) {
            this.$Elm   = null;
            this.$Input = null;

            this.$open     = false;
            this.$selected = false;

            this.parent(options);
        },

        /**
         * API - Execute tha payment step
         *
         * @param {Object} all important parameter
         *  orderHash : The hash of the order
         *  basket    : All basket articles and data, sum, sub sum and so on
         *  Elm       : Node Element where you can display the payment
         *  Popup     : Payment popup
         *  Gateway   : Gateway step of the popup
         * @param {Function} callback - callback function, triggere this function when payment is finish
         */
        execute: function (params, callback) {
            callback();
        },

        /**
         * Create the DOMNode Element
         *
         * @return {DOMNode}
         */
        create: function () {
            var self = this;

            this.$Elm = new Element('div', {
                'class': 'plugin-payment smooth',
                html   : '<input type="radio" name="payment" value="' + this.getAttribute('name') + '" />' +
                '<div class="plugin-payment-image"></div>' +
                '<div class="plugin-payment-information">' +
                '<h2>' + this.getAttribute('title') + '</h2>' +
                '<div class="plugin-payment-description">' +
                this.getAttribute('description') +
                '</div>' +
                '<div class="plugin-payment-extra"></div>' +
                '</div>',

                events: {
                    click: function (event) {
                        self.$Input.fireEvent('click', [event]);
                    }
                }
            });

            this.$Input = this.$Elm.getElement('input');

            this.$Input.addEvents({
                click : function (event) {
                    this.checked = true;
                    this.fireEvent('change');
                },
                change: this.$onInputChange.bind(this)
            });


            if (this.getAttribute('icon')) {
                this.$Elm.getElement('.plugin-payment-image').setStyles({
                    backgroundImage: 'url(' + this.getAttribute('icon') + ')'
                });
            }

            return this.$Elm;
        },

        /**
         * Insert the DOMNode Element into an element
         *
         * @return {this}
         */
        inject: function (Parent, pos) {
            if (typeof pos === 'undefined') {
                this.create().inject(Parent);
            } else {
                this.create().inject(Parent, pos);
            }

            return this;
        },

        /**
         * Save method for the payment
         * The the save method can be overwritten
         *
         * @param {Function} callback - Callback function if the saving is finish
         */
        save: function (callback) {
            callback();
        },

        /**
         * Open the payment extra fields
         *
         * @return {this}
         */
        open: function () {
            if (!this.getAttribute('checkoutPaymentData')) {
                return;
            }

            if (!this.getElm()) {
                return this;
            }

            var self = this;

            _Ajax.asyncPost('ajax_plugin_payment_getEditUserDataTpl', function (result) {
                var Extra = self.getElm().getElement('.plugin-payment-extra');

                Extra.set('html', result);
                self.fireEvent('open', [self, Extra]);
            }, {
                plugin : 'payment',
                payment: this.getAttribute('name')
            });

            return this;
        },

        /**
         * Close the payment extra fields
         *
         * @return {this}
         */
        close: function () {
            this.getElm().getElement('.plugin-payment-extra').set('html', '');
            this.fireEvent('close', [this]);

            return this;
        },

        /**
         * Select the payment
         */
        select: function () {
            if (this.$selected) {
                return;
            }

            this.$selected      = true;
            this.$Input.checked = true;

            if (this.getAttribute('checkoutPaymentData')) {
                this.open();
            }

            this.getElm().addClass('plugin-payment-selected');
            this.fireEvent('select', [this]);
        },

        /**
         * Unselect the payment
         */
        unselect: function () {
            if (!this.$selected) {
                return;
            }

            this.$selected      = false;
            this.$Input.checked = false;

            this.close();
            this.getElm().removeClass('plugin-payment-selected');
            this.fireEvent('unselect', [this]);
        },

        /**
         * Check all needle fields
         *
         * @param {Function} callback - callback function, if the check is finished
         */
        checkFields: function (callback) {
            _Ajax.asyncPost('ajax_plugin_payment_check', callback, {
                plugin : 'payment',
                payment: this.getAttribute('name')
            });
        },

        /**
         * Use the payment a gateway or redirect?
         *
         * @return Bool
         */
        isGateway: function () {
            return this.getAttribute('gateway') ? true : false;
        },

        /**
         * event : if the main input (radio) status changed
         */
        $onInputChange: function () {
            if (!this.$Input.checked) {
                this.unselect();
                return;
            }

            // fire the events onChange the other radio elements
            var list = document.getElements(
                'input[name="' + this.$Input.name + '"]'
            );

            for (var i = 0, len = list.length; i < len; i++) {
                if (this.$Input.value != list[i].value) {

                    list[i].checked = false;
                    list[i].fireEvent('change');
                }
            }

            this.select();
        }
    });
});
