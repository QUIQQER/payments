/**
 * @module package/quiqqer/payments/bin/backend/controls/Settings
 * @author www.pcsg.de (Henning Leutz)
 *
 * @requires qui/QUI
 * @requires qui/controls/Control
 * @requires Mustache
 * @requires text!package/quiqqer/payments/bin/backend/controls/Settings.html
 */
define('package/quiqqer/payments/bin/backend/controls/Settings', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'package/quiqqer/payments/bin/backend/Payments',
    'controls/grid/Grid',
    'Mustache',
    'Locale',
    'Ajax',

    'text!package/quiqqer/payments/bin/backend/controls/Settings.html'

], function (QUI, QUIControl, QUIConfirm, Payments, Grid, Mustache, QUILocale, QUIAjax, template) {
    "use strict";

    var lg = 'quiqqer/payments';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/payments/bin/backend/controls/Settings',

        Binds: [
            'refresh',
            '$onEditClick',
            '$openAddDialog',
            '$openDeleteDialog',
            '$refreshButtonStatus'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Input = null;
            this.$Grid  = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * Refresh the value and the display
         */
        refresh: function () {
            if (!this.$Elm) {
                return;
            }

            var self = this;

            Payments.getPayments().then(function (result) {
                self.$Grid.setData({
                    data: result
                });
            });
        },

        /**
         * Return the domnode element
         *
         * @return {Element}
         */
        create: function () {
            this.$Elm = new Element('div', {
                styles: {
                    minHeight: 300,
                    width    : '100%'
                }
            });

            var Container = new Element('div', {
                styles: {
                    minHeight: 300,
                    width    : '100%'
                }
            }).inject(this.$Elm);

            this.$Grid = new Grid(Container, {
                buttons    : [{
                    name     : 'add',
                    text     : QUILocale.get('quiqqer/quiqqer', 'add'),
                    textimage: 'fa fa-plus',
                    events   : {
                        onClick: this.$openAddDialog
                    }
                }, {
                    name     : 'edit',
                    text     : QUILocale.get('quiqqer/quiqqer', 'edit'),
                    textimage: 'fa fa-edit',
                    disabled : true,
                    events   : {
                        onClick: this.$onEditClick
                    }
                }, {
                    name     : 'delete',
                    text     : QUILocale.get('quiqqer/system', 'delete'),
                    textimage: 'fa fa-trash',
                    disabled : true,
                    events   : {
                        onClick: this.$openDeleteDialog
                    }
                }],
                columnModel: [{
                    header   : QUILocale.get('quiqqer/system', 'id'),
                    dataIndex: 'id',
                    dataType : 'number',
                    width    : 30
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
                }]
            });

            this.$Grid.addEvents({
                onRefresh : this.refresh,
                onClick   : this.$refreshButtonStatus,
                onDblClick: this.$onEditClick
            });

            return this.$Elm;
        },

        /**
         * @event on inject
         */
        $onInject: function () {
            this.$Grid.setHeight(300);
            this.refresh();
        },

        /**
         * @event : on import
         */
        $onImport: function () {
            this.$Input = this.$Elm;
            this.create().inject(this.$Input, 'after');
            this.$onInject();
        },

        /**
         * open the edit dialog
         */
        openPayment: function (paymentId) {
            require([
                'package/quiqqer/payments/bin/backend/controls/Payment',
                'utils/Panels'
            ], function (Payment, Utils) {
                Utils.openPanelInTasks(
                    new Payment({
                        paymentId: paymentId
                    })
                );
            });
        },

        /**
         * event: on edit
         */
        $onEditClick: function () {
            var data = this.$Grid.getSelectedData();

            if (data.length) {
                this.openPayment(data[0].id);
            }
        },

        /**
         * open the add dialog
         */
        $openAddDialog: function () {
            new QUIConfirm({
                title    : 'Zahlungsart hinzufügen',
                icon     : 'fa fa-plus',
                autoclose: true,
                maxHeight: 400,
                maxWidth : 600,
                events   : {
                    onOpen: function () {

                    },

                    onSubmit: function (Win) {
                        Win.Loader.show();
                    }
                }
            }).open();
        },

        /**
         * open the add dialog
         */
        $openDeleteDialog: function () {
            new QUIConfirm({
                title    : 'Zahlungsart löschen',
                icon     : 'fa fa-trash',
                autoclose: true,
                maxHeight: 400,
                maxWidth : 600,
                events   : {
                    onOpen: function () {

                    },

                    onSubmit: function (Win) {
                        Win.Loader.show();
                    }
                }
            }).open();
        },

        /**
         * refresh the button disable enable status
         * looks at the grid
         */
        $refreshButtonStatus: function () {
            var selected = this.$Grid.getSelectedIndices(),
                buttons  = this.$Grid.getButtons();

            var Edit = buttons.filter(function (Btn) {
                return Btn.getAttribute('name') === 'edit';
            })[0];

            var Delete = buttons.filter(function (Btn) {
                return Btn.getAttribute('name') === 'delete';
            })[0];

            if (!selected.length) {
                Edit.disable();
                Delete.disable();
                return;
            }

            Edit.enable();
            Delete.enable();
        }
    });
});