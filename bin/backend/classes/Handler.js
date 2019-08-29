/**
 * @module package/quiqqer/payments/bin/backend/classes/Handler
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event onPaymentDeactivate [self, paymentId, data]
 * @event onPaymentActivate [self, paymentId, data]
 * @event onPaymentDelete [self, paymentId]
 * @event onPaymentCreate [self, paymentId]
 * @event onPaymentUpdate [self, paymentId, data]
 */
define('package/quiqqer/payments/bin/backend/classes/Handler', [

    'qui/QUI',
    'qui/classes/DOM',
    'Ajax'

], function (QUI, QUIDOM, QUIAjax) {
    "use strict";

    return new Class({

        Extends: QUIDOM,
        Type   : 'package/quiqqer/payments/bin/Manager',

        initialize: function (options) {
            this.parent(options);

            this.$payments = null;
        },

        /**
         * Return active payments
         *
         * @return {Promise}
         */
        getPayments: function () {
            if (this.$payments) {
                return Promise.resolve(this.$payments);
            }

            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_payments_ajax_backend_getPayments', function (result) {
                    self.$payments = result;
                    resolve(self.$payments);
                }, {
                    'package': 'quiqqer/payments',
                    onError  : reject
                });
            });
        },

        /**
         * Return the payment data
         *
         * @param {String|Number} paymentId
         * @return {Promise}
         */
        getPayment: function (paymentId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_payments_ajax_backend_getPayment', resolve, {
                    'package': 'quiqqer/payments',
                    onError  : reject,
                    paymentId: paymentId
                });
            });
        },

        /**
         * Return all available payment methods
         *
         * @return {Promise}
         */
        getPaymentTypes: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_payments_ajax_backend_getPaymentTypes', resolve, {
                    'package': 'quiqqer/payments',
                    onError  : reject
                });
            });
        },

        /**
         * Create a new inactive payment type
         *
         * @param {String} paymentType - Hash of the payment type
         * @return {Promise}
         */
        createPayment: function (paymentType) {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_payments_ajax_backend_create', function (paymentId) {
                    self.$payments = null;

                    require([
                        'package/quiqqer/translator/bin/Translator'
                    ], function (Translator) {
                        Translator.refreshLocale().then(function () {
                            self.fireEvent('paymentCreate', [self, paymentId]);
                            resolve(paymentId);
                        });
                    });
                }, {
                    'package'  : 'quiqqer/payments',
                    onError    : reject,
                    paymentType: paymentType
                });
            });
        },

        /**
         * Update a payment
         *
         * @param {Number|String} paymentId - Payment ID
         * @param {Object} data - Data of the payment
         * @return {Promise}
         */
        updatePayment: function (paymentId, data) {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_payments_ajax_backend_update', function (result) {
                    self.$payments = null;

                    require(['package/quiqqer/translator/bin/Translator'], function (Translator) {
                        Translator.refreshLocale().then(function () {
                            self.fireEvent('paymentUpdate', [self, paymentId, result]);
                            resolve(result);
                        });
                    });
                }, {
                    'package': 'quiqqer/payments',
                    onError  : reject,
                    paymentId: paymentId,
                    data     : JSON.encode(data)
                });
            });
        },

        /**
         *
         * @param {String|Number} paymentId
         * @return {Promise}
         */
        deletePayment: function (paymentId) {
            var self = this;

            return new Promise(function (resolve, reject) {
                self.$payments = null;

                QUIAjax.get('package_quiqqer_payments_ajax_backend_delete', function () {
                    self.fireEvent('paymentDelete', [self, paymentId]);
                    resolve();
                }, {
                    'package': 'quiqqer/payments',
                    onError  : reject,
                    paymentId: paymentId
                });
            });
        },

        /**
         * Activate a payment
         *
         * @param {String|Number} paymentId
         * @return {Promise}
         */
        activatePayment: function (paymentId) {
            var self = this;

            return new Promise(function (resolve, reject) {
                self.$payments = null;

                QUIAjax.get('package_quiqqer_payments_ajax_backend_activate', function (result) {
                    self.fireEvent('paymentActivate', [self, paymentId, result]);
                    resolve(result);
                }, {
                    'package': 'quiqqer/payments',
                    onError  : reject,
                    paymentId: paymentId
                });
            });
        },

        /**
         * Deactivate a payment
         *
         * @param {String|Number} paymentId
         * @return {Promise}
         */
        deactivatePayment: function (paymentId) {
            var self = this;

            return new Promise(function (resolve, reject) {
                self.$payments = null;

                QUIAjax.get('package_quiqqer_payments_ajax_backend_deactivate', function (result) {
                    self.fireEvent('paymentDeactivate', [self, paymentId, result]);
                    resolve(result);
                }, {
                    'package': 'quiqqer/payments',
                    onError  : reject,
                    paymentId: paymentId
                });
            });
        }
    });
});