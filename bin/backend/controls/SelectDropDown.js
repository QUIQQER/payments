/**
 * Select payment methods in dropdown
 *
 * @module package/quiqqer/payments/bin/backend/controls/SelectDropDown
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onChange [value, this]
 * @event onLoaded [this] - Fires if all payment methods have been loaded
 */
define('package/quiqqer/payments/bin/backend/controls/SelectDropDown', [

    'qui/controls/loader/Loader',
    'qui/controls/buttons/Select',

    'package/quiqqer/payments/bin/backend/Payments',

    'Locale',

], function (QUILoader, QUISelect, Payments, QUILocale) {
    "use strict";

    return new Class({
        Extends: QUISelect,
        Type   : 'package/quiqqer/payments/bin/backend/controls/SelectDropDown',

        Binds: [
            '$onImport',
            '$setValue',
            '$onInject',
            'setValue'
        ],

        options: {
            showIcons            : false,
            placeholderText      : false,
            placeholderSelectable: false,
            paymentMehodId       : false    // ID of pre-selected payment method
        },

        initialize: function (options) {
            this.parent(options);

            this.$Input = null;
            this.$Elm   = null;

            this.Loader = new QUILoader();

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on inject
         */
        $onImport: function () {
            var self = this;

            this.$Input      = this.getElm();
            this.$Input.type = 'hidden';

            var Elm = this.create().inject(this.$Input, 'after');
            Elm.setStyle('width', '100%');
            Elm.addClass('field-container-field');

            this.Loader.inject(Elm);
            this.Loader.show();

            Payments.getPayments().then(function (payments) {
                var i, len, title;
                var current = QUILocale.getCurrent();

                for (i = 0, len = payments.length; i < len; i++) {
                    title = payments[i].title;

                    if (typeOf(title) === 'object' && typeof title[current] !== 'undefined') {
                        title = title[current];
                    }

                    self.appendChild(
                        title,
                        payments[i].id
                    );
                }

                if (self.getAttribute('paymentMethodId')) {
                    self.setValue(self.getAttribute('paymentMethodId'));
                }

                self.fireEvent('loaded', [self]);

                self.Loader.hide();
            });
        }
    });
});