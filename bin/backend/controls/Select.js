/**
 * Makes an input field to a payment selection field
 *
 * @module package/quiqqer/payments/bin/backend/controls/Select
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event onAddPayment [ this, id ]
 * @event onChange [ this ]
 */
define('package/quiqqer/payments/bin/backend/controls/Select', [

    'qui/QUI',
    'qui/controls/elements/Select',
    'package/quiqqer/payments/bin/backend/classes/Handler',
    'Locale'

], function (QUI, QUIElementSelect, Handler, QUILocale) {
    "use strict";

    var lg       = 'quiqqer/payments';
    var Payments = new Handler();

    /**
     * @class package/quiqqer/payments/bin/backend/controls/Select
     *
     * @param {Object} options
     * @param {HTMLInputElement} [Input]  - (optional), if no input given, one would be created
     *
     * @memberof! <global>
     */
    return new Class({

        Extends: QUIElementSelect,
        Type   : 'package/quiqqer/payments/bin/backend/controls/Select',

        Binds: [
            '$onSearchButtonClick',
            'paymentSearch'
        ],

        initialize: function (options) {
            this.parent(options);

            this.setAttribute('Search', this.paymentSearch);
            this.setAttribute('icon', 'fa fa-credit-card-alt');
            this.setAttribute('child', 'package/quiqqer/payments/bin/backend/controls/SelectItem');

            this.setAttribute(
                'placeholder',
                QUILocale.get(lg, 'control.select.search.placeholder')
            );

            this.addEvents({
                onSearchButtonClick: this.$onSearchButtonClick
            });
        },

        /**
         * Search areas
         *
         * @param {String} value
         * @returns {Promise}
         */
        paymentSearch: function (value) {
            return Payments.search({
                freetext: value
            });
        },

        /**
         * event : on search button click
         *
         * @param self
         * @param Btn
         */
        $onSearchButtonClick: function (self, Btn) {
            Btn.setAttribute('icon', 'fa fa-spinner fa-spin');

            require(['package/quiqqer/payments/bin/backend/controls/search/Window'], function (Search) {
                new Search({
                    events: {
                        onSubmit: function (Win, values) {
                            for (var i = 0, len = values.length; i < len; i++) {
                                self.addItem(parseInt(values[i].id));
                            }
                        }
                    }
                }).open();

                Btn.setAttribute('icon', 'fa fa-search');
            });
        }
    });
});
