/**
 * @module
 */
define('package/quiqqer/payments/bin/frontend/controls/order/Payment', [

    'qui/QUI',
    'qui/controls/Control'

], function (QUI, QUIControl) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/payments/bin/frontend/controls/order/Payment',

        Binds: [
            '$onClick'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Input = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            this.getElm().addEvent('click', this.$onClick);

            this.$Input = this.getElm().getElement('input');
        },

        /**
         * event: on click
         */
        $onClick: function (event) {
            event.stop();

            this.$Input.checked = true;
        }
    });
});