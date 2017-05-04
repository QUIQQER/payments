/**
 * @module package/quiqqer/payments/bin/backend/controls/Settings
 * @author www.pcsg.de (Henning Leutz)
 *
 * @requires qui/QUI
 * @requires qui/controls/Control
 * @requires Mustache
 * @requires text!package/quiqqer/payments/bin/backend/controls/Settings.html
 */
define('package/quiqqer/payments/bin/backend/controls/Settings', [

    'qui/QUI',
    'qui/controls/Control',
    'package/quiqqer/payments/bin/backend/Payments',
    'Mustache',
    'Ajax',

    'text!package/quiqqer/payments/bin/backend/controls/Settings.html'

], function (QUI, QUIControl, Payments, Mustache, QUIAjax, template) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/payments/bin/backend/controls/Settings',

        Binds: [
            'refresh'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Input = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * Refresh the value and the display
         */
        refresh: function () {
            if (!this.$Elm) {
                return;
            }

            var elements = this.$Elm.getElements('[type="checkbox"]'),
                data     = {};

            for (var i = 0, len = elements.length; i < len; i++) {
                data[elements[i].get('name')] = elements[i].checked;
            }

            this.$Input.value = JSON.encode(data);
        },

        /**
         * Return the domnode element
         *
         * @return {Element}
         */
        create: function () {
            this.$Elm = new Element('div');

            return this.$Elm;
        },

        /**
         * @event on inject
         */
        $onInject: function () {
            var self = this;

            Promise.all([
                Payments.getAvailablePayments(),
                Payments.getPayments()
            ]).then(function (result) {
                var available = result[0],
                    active    = result[1],
                    payments  = [];

                for (var paymentName in available) {
                    if (available.hasOwnProperty(paymentName)) {
                        payments.push(available[paymentName]);
                    }
                }

                self.$Elm.set({
                    html: Mustache.render(template, {
                        payments: payments
                    })
                });

                self.$Elm.getElements('[type="checkbox"]').addEvent('change', self.refresh);

                var payment;

                for (payment in active) {
                    if (active.hasOwnProperty(payment)) {
                        self.$Elm.getElements('[name="' + payment + '"]').set('checked', true);
                    }
                }

                try {
                    var data = JSON.decode(self.$Input.value);

                    if (data) {
                        for (payment in data) {
                            if (data.hasOwnProperty(payment) && data[payment] === true) {
                                self.$Elm.getElements('[name="' + payment + '"]').set('checked', true);
                            }
                        }
                    }
                } catch (e) {
                }

            });
        },

        /**
         * @event : on import
         */
        $onImport: function () {
            this.$Input = this.$Elm;
            this.create().inject(this.$Input, 'after');
            this.$onInject();
        },

        $getSettings: function () {

        }
    });
});