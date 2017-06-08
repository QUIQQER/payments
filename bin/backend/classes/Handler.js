/**
 * @module package/quiqqer/payments/bin/backend/classes/Handler
 *
 * @require qui/QUI
 * @require qui/classes/DOM
 * @require Ajax
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
        },

        /**
         * Return active payments
         *
         * @return {Promise}
         */
        getPayments: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_payments_ajax_backend_getPayments', resolve, {
                    'package': 'quiqqer/payments',
                    onError  : reject
                });
            });
        },

        /**
         *
         * @param paymentId
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
         * Create a new inactive payment type
         *
         * @return {Promise}
         */
        createPayment: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_payments_ajax_backend_create', function (paymentId) {
                    require([
                        'package/quiqqer/translator/bin/Translator'
                    ], function (Translator) {
                        Translator.refreshLocale().then(function () {
                            resolve(paymentId);
                        });
                    });
                }, {
                    'package': 'quiqqer/payments',
                    onError  : reject
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
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_payments_ajax_backend_update', function () {
                    require([
                        'package/quiqqer/translator/bin/Translator'
                    ], function (Translator) {
                        Translator.refreshLocale().then(function () {
                            resolve(paymentId);
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
         * @param paymentId
         * @return {Promise}
         */
        deletePayment: function (paymentId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_payments_ajax_backend_delete', resolve, {
                    'package': 'quiqqer/payments',
                    onError  : reject,
                    paymentId: paymentId
                });
            });
        }
    });
});