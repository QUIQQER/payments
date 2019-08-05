/**
 * @module package/quiqqer/payments/bin/backend/controls/search/Search
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/payments/bin/backend/controls/search/Search', [

    'qui/controls/Control',
    'package/quiqqer/payments/bin/backend/classes/Handler',

    'css!package/quiqqer/payments/bin/backend/controls/search/Search.css'

], function (QUIControl, Handler) {
    "use strict";

    var Payments = new Handler();

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/payments/bin/backend/controls/search/Search',

        Binds: [
            '$onInject'
        ],

        options: {},

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * Return the DOMNode Element
         *
         * @returns {HTMLElement}
         */
        create: function () {
            var self = this,
                Elm  = this.parent();


            return Elm;
        },

        /**
         * event : on inject
         */
        $onInject: function () {

        }
    });
});
