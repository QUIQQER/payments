/**
 * Payment Manager
 * Manager for the Payment types
 *
 * @author www.pcsg.de (Henning Leutz)
 * @deprecated
 */
define('package/quiqqer/payments/bin/Manager', [

    'require',
    'qui/classes/Control'

], function (require, QUIControl) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/payments/bin/Manager',

        initialize: function (options) {
            this.parent(options);

            this.$payments = {};
            this.$loaded   = false;
        },

        /**
         * load the plugin infos
         *
         * @param {Function} callback
         */
        load: function (callback) {
            if (this.$loaded) {
                callback();
                return;
            }

            var self = this;

            _Ajax.asyncPost('ajax_plugin_payments_data', function (result, Ajax) {
                self.$payments = result;
                self.$loaded   = true;

                if (typeof callback !== 'undefined') {
                    callback(result);
                }
            }, {
                plugin: 'payment'
            });
        },

        /**
         * Get a Payment class
         *
         * @param {String} payment - Payment Name
         * @param {Function} callback - callback function, triggered when all is loaded
         *
         * @example Manager.getPayment('quiqqer/paypal/Paypal', function(PaypalCls) {
         *     var Paypal = new PaypalCls();
         * });
         */
        getPayment: function (payment, callback) {
            var self = this;

            payment = payment.toLowerCase();

            require([

                'plugins/payment/moduls/' + payment + '/bin/Payment'

            ], function (ClsPayment) {
                self.load(function () {
                    var Payment = new ClsPayment(
                        self.$getPaymentData(payment)
                    );

                    callback(Payment);
                });
            });
        },

        /**
         * Get multible payment classes
         *
         * @param {Array} payments - Payment names
         * @param {Function} callback - callback function, triggered when all is loaded
         */
        getPayments: function (payments, callback) {
            var needles = [],
                self    = this;

            for (var i = 0, len = payments.length; i < len; i++) {
                payments[i] = payments[i].toLowerCase();

                needles.push(
                    'plugins/payment/moduls/' + payments[i] + '/bin/Payment'
                );
            }


            require(needles, function () {
                var args = arguments;

                self.load(function () {
                    var i, len, Cls, params, Payment;
                    var res = [];

                    for (i = 0, len = args.length; i < len; i++) {
                        Cls = args[i];

                        Payment = new Cls(
                            self.$getPaymentData(payments[i])
                        );

                        res.push(Payment);
                    }

                    callback(res);
                });
            });
        },

        /**
         * return the detail data for a payment
         *
         * @return {Object|false}
         */
        $getPaymentData: function (payment) {
            payment = payment.toLowerCase();

            for (var i = 0, len = this.$payments.length; i < len; i++) {
                if (this.$payments[i].name.toLowerCase() == payment) {
                    return this.$payments[i];
                }
            }

            return false;
        }
    });

});