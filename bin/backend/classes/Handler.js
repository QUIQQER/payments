/**
 * @module package/quiqqer/payments/bin/backend/classes/Handler
 *
 * @requires qui/QUI
 * @requires qui/classes/DOM
 * @requires Ajax
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
         * Return all available payments
         *
         * @return {Promise}
         */
        getAvailablePayments: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_payments_ajax_backend_getAvailablePayments', resolve, {
                    'package': 'quiqqer/payments',
                    onError  : reject
                });
            });
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
        }
    });
});