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

        createPayment: function () {

        },

        editPayment: function () {

        },


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