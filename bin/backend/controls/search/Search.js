/**
 * @module package/quiqqer/payments/bin/backend/controls/search/Search
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/payments/bin/backend/controls/search/Search', [

    'qui/controls/Control',
    'package/quiqqer/payments/bin/backend/classes/Handler',
    'qui/controls/buttons/Button',
    'qui/controls/buttons/Switch',
    'Locale',
    'Ajax',
    'controls/grid/Grid',

    'css!package/quiqqer/payments/bin/backend/controls/search/Search.css'

], function (QUIControl, Handler, QUIButton, QUISwitch, QUILocale, QUIAjax, Grid) {
    "use strict";

    var Payments = new Handler();
    var lg       = 'quiqqer/payments';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/payments/bin/backend/controls/search/Search',

        Binds: [
            'search'
        ],

        options: {
            limit : 20,
            page  : 1,
            search: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$Container = null;
            this.$Grid      = null;
            this.$Input     = null;
        },

        /**
         * Return the DOMNode Element
         *
         * @returns {HTMLElement}
         */
        create: function () {
            this.$Elm = new Element('div', {
                'class': 'quiqqer-payment-search',
                html   : '',
                styles : {
                    height: '100%',
                    width : '100%'
                }
            });

            this.$Input = this.$Elm.getElement('[type="search"]');

            if (this.getAttribute('search')) {
                this.$Input.value = this.getAttribute('search');
            }

            this.$Container = new Element('div');
            this.$Container.inject(this.$Elm);

            this.$Grid = new Grid(this.$Container, {
                columnModel      : [{
                    header   : QUILocale.get('quiqqer/system', 'priority'),
                    dataIndex: 'priority',
                    dataType : 'number',
                    width    : 50
                }, {
                    header   : QUILocale.get('quiqqer/system', 'status'),
                    dataIndex: 'status',
                    dataType : 'node',
                    width    : 60,
                    className: 'grid-align-center'
                }, {
                    header   : QUILocale.get('quiqqer/system', 'title'),
                    dataIndex: 'title',
                    dataType : 'string',
                    width    : 200
                }, {
                    header   : QUILocale.get('quiqqer/system', 'workingtitle'),
                    dataIndex: 'workingTitle',
                    dataType : 'string',
                    width    : 200
                }, {
                    header   : QUILocale.get('quiqqer/system', 'id'),
                    dataIndex: 'id',
                    dataType : 'number',
                    width    : 30
                }, {
                    header   : QUILocale.get(lg, 'payments.type'),
                    dataIndex: 'paymentType_display',
                    dataType : 'string',
                    width    : 200
                }],
                pagination       : true,
                filterInput      : true,
                perPage          : this.getAttribute('limit'),
                page             : this.getAttribute('page'),
                sortOn           : this.getAttribute('field'),
                serverSort       : true,
                showHeader       : true,
                sortHeader       : true,
                alternaterows    : true,
                resizeColumns    : true,
                selectable       : true,
                multipleSelection: true,
                resizeHeaderOnly : true
            });

            // Events
            this.$Grid.addEvents({
                onDblClick: function () {
                    this.fireEvent('dblClick', [this]);
                }.bind(this),
                onRefresh : this.search
            });

            this.$Grid.refresh();

            return this.$Elm;
        },

        /**
         * Resize
         *
         * @return {Promise}
         */
        resize: function () {
            var size = this.$Elm.getSize();

            return Promise.all([
                this.$Grid.setHeight(size.y),
                this.$Grid.setWidth(size.x)
            ]);
        },

        /**
         * execute the search
         */
        search: function () {
            this.fireEvent('searchBegin', [this]);

            var self    = this,
                current = QUILocale.getCurrent();

            return Payments.getPayments().then(function (result) {
                result = result.clone();

                for (var i = 0, len = result.length; i < len; i++) {
                    if (parseInt(result[i].active)) {
                        result[i].status = new Element('span', {
                            class: 'fa fa-check'
                        });
                    } else {
                        result[i].status = new Element('span', {
                            class: 'fa fa-remove'
                        });
                    }

                    result[i].paymentType_display = '';

                    if (typeof result[i].title[current] !== 'undefined') {
                        result[i].title = result[i].title[current];
                    } else {
                        result[i].title = '';
                    }

                    if (typeof result[i].workingTitle[current] !== 'undefined') {
                        result[i].workingTitle = result[i].workingTitle[current];
                    } else {
                        result[i].workingTitle = '';
                    }

                    if ("paymentType" in result[i] && result[i].paymentType) {
                        result[i].paymentType_display = result[i].paymentType.title;
                    }
                }

                self.$Grid.setData({
                    data: result
                });

                self.fireEvent('searchEnd', [self]);
            });
        },

        /**
         * Return the selected user data
         *
         * @return {Array}
         */
        getSelectedData: function () {
            return this.$Grid.getSelectedData();
        }
    });
});
