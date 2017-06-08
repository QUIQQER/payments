/**
 * @module package/quiqqer/payments/bin/backend/controls/Payments
 * @author www.pcsg.de (Henning Leutz)
 *
 * Payments Panel
 *
 * @require qui/QUI
 * @require qui/controls/desktop/Panel
 * @require qui/controls/windows/Confirm
 * @require package/quiqqer/payments/bin/backend/Payments
 * @require controls/grid/Grid
 * @require Mustache
 * @require Locale
 * @require Ajax
 */
define('package/quiqqer/payments/bin/backend/controls/Payments', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/windows/Confirm',
    'package/quiqqer/payments/bin/backend/Payments',
    'controls/grid/Grid',
    'Mustache',
    'Locale',
    'Ajax'

], function (QUI, QUIPanel, QUIConfirm, Payments, Grid, Mustache, QUILocale, QUIAjax) {
    "use strict";

    var lg = 'quiqqer/payments';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/payments/bin/backend/controls/Payments',

        Binds: [
            'refresh',
            '$onCreate',
            '$onInject',
            '$onResize',
            '$onEditClick',
            '$openCreateDialog',
            '$openDeleteDialog',
            '$refreshButtonStatus'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Grid = null;

            this.setAttributes({
                icon : 'fa fa-credit-card-alt',
                title: QUILocale.get(lg, 'menu.erp.payments.title')
            });

            this.addEvents({
                onCreate: this.$onCreate,
                onInject: this.$onInject,
                onResize: this.$onResize

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
                var Active = new Element('span', {
                    'class': 'fa fa-check'
                });

                var Deactive = new Element('span', {
                    'class': 'fa fa-remove'
                });

                for (var i = 0, len = result.length; i < len; i++) {
                    if (parseInt(result[i].active)) {
                        result[i].status = Active.clone();
                    } else {
                        result[i].status = Deactive.clone();
                    }
                }

                self.$Grid.setData({
                    data: result
                });
            });
        },

        /**
         * event: on create
         */
        $onCreate: function () {
            var Container = new Element('div', {
                styles: {
                    minHeight: 300,
                    width    : '100%'
                }
            }).inject(this.getContent());

            this.$Grid = new Grid(Container, {
                buttons    : [{
                    name     : 'add',
                    text     : QUILocale.get('quiqqer/quiqqer', 'add'),
                    textimage: 'fa fa-plus',
                    events   : {
                        onClick: this.$openCreateDialog
                    }
                }, {
                    type: 'separator'
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
                    header   : QUILocale.get('quiqqer/system', 'priority'),
                    dataIndex: 'priority',
                    dataType : 'number',
                    width    : 50
                }, {
                    header   : QUILocale.get('quiqqer/system', 'status'),
                    dataIndex: 'status',
                    dataType : 'node',
                    width    : 60
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
                }]
            });

            this.$Grid.addEvents({
                onRefresh : this.refresh,
                onClick   : this.$refreshButtonStatus,
                onDblClick: this.$onEditClick
            });
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            this.refresh();
        },

        /**
         * event : on resize
         */
        $onResize: function () {
            if (!this.$Grid) {
                return;
            }

            var Body = this.getContent();

            if (!Body) {
                return;
            }

            var size = Body.getSize();
            this.$Grid.setHeight(size.y - 40);
            this.$Grid.setWidth(size.x - 40);
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
        $openCreateDialog: function () {
            var self = this;

            new QUIConfirm({
                icon       : 'fa fa-plus',
                texticon   : 'fa fa-plus',
                title      : QUILocale.get(lg, 'window.create.title'),
                text       : QUILocale.get(lg, 'window.create.title'),
                information: QUILocale.get(lg, 'window.create.information'),
                autoclose  : false,
                maxHeight  : 400,
                maxWidth   : 600,
                events     : {
                    onSubmit: function (Win) {
                        Win.Loader.show();

                        Payments.createPayment().then(function (newId) {
                            Win.close();
                            self.refresh();
                            self.openPayment(newId);
                        });
                    }
                }
            }).open();
        },

        /**
         * open the add dialog
         */
        $openDeleteDialog: function () {
            var selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return;
            }

            var self      = this,
                payment   = selected[0].title,
                paymentId = selected[0].id;

            if (payment === '') {
                payment = paymentId;
            }

            new QUIConfirm({
                texticon   : 'fa fa-trash',
                icon       : 'fa fa-trash',
                title      : QUILocale.get(lg, 'window.delete.title'),
                information: QUILocale.get(lg, 'window.delete.information', {
                    payment: payment
                }),
                text       : QUILocale.get(lg, 'window.delete.text', {
                    payment: payment
                }),
                autoclose  : false,
                maxHeight  : 400,
                maxWidth   : 600,
                events     : {
                    onSubmit: function (Win) {
                        Win.Loader.show();

                        Payments.deletePayment(paymentId).then(function () {
                            Win.close();
                            self.refresh();
                        });
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