/**
 * @module package/quiqqer/payments/bin/backend/controls/SelectItem
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/payments/bin/backend/controls/SelectItem', [

    'qui/controls/Control',
    'package/quiqqer/payments/bin/backend/classes/Handler',
    'Locale',

    'css!package/quiqqer/payments/bin/backend/controls/SelectItem.css'

], function (QUIControl, Handler, QUILocale) {
    "use strict";

    var Payments = new Handler();

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/payments/bin/backend/controls/SelectItem',

        Binds: [
            '$onInject'
        ],

        options: {
            id: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$Icon    = null;
            this.$Text    = null;
            this.$Destroy = null;

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

            Elm.set({
                'class': 'quiqqer-payments-selectItem smooth',
                html   : '<span class="quiqqer-payments-selectItem-icon fa fa-credit-card-alt"></span>' +
                    '<span class="quiqqer-payments-selectItem-text">&nbsp;</span>' +
                    '<span class="quiqqer-payments-selectItem-destroy fa fa-remove"></span>'
            });

            this.$Icon    = Elm.getElement('.quiqqer-payments-selectItem-icon');
            this.$Text    = Elm.getElement('.quiqqer-payments-selectItem-text');
            this.$Destroy = Elm.getElement('.quiqqer-payments-selectItem-destroy');

            this.$Destroy.addEvent('click', function () {
                self.destroy();
            });

            return Elm;
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            var self    = this,
                current = QUILocale.getCurrent();

            this.$Text.set({
                html: '<span class="fa fa-spinner fa-spin"></span>'
            });

            if (!parseInt(this.getAttribute('id'))) {
                (function () {
                    this.$Destroy.click();
                }).delay(500, this);

                return Promise.resolve();
            }


            Payments.getPayment(this.getAttribute('id')).then(function (data) {
                self.$Text.set(
                    'html',
                    '#' + data.id + ' - <b>' + data.title[current] + '</b> (' + data.workingTitle[current] + ')'
                );
            }).catch(function () {
                self.$Icon.removeClass('fa-credit-card-alt');
                self.$Icon.addClass('fa-bolt');
                self.$Text.set('html', '...');
            });
        }
    });
});
