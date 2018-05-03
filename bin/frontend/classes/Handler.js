/**
 * Payments Handler
 *
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/payments/bin/frontend/classes/Handler', [

    'Ajax'

], function (QUIAjax) {
    "use strict";

    var pkg = 'quiqqer/payments';

    return new Class({

        Type: 'package/quiqqer/payments/bin/frontend/controls/Handler',

        /**
         * Log a payments error
         *
         * @param {String} errMsg
         * @param {String|Number} [errCode]
         * @return {Promise}
         */
        logPaymentsError: function (errMsg, errCode) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_log_ajax_logPaymentsError', resolve, {
                    'package': pkg,
                    errMsg   : errMsg,
                    errCode  : errCode || 'N/A',
                    onError  : reject
                });
            });
        }
    });

});