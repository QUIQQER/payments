/**
 * @module package/quiqqer/payments/bin/backend/controls/Payments
 * @author www.pcsg.de (Henning Leutz)
 *
 * Payments Panel
 */
define('package/quiqqer/payments/bin/backend/controls/Payments', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/windows/Confirm',
    'qui/controls/buttons/Button',
    'package/quiqqer/payments/bin/backend/Payments',
    'controls/grid/Grid',
    'Mustache',
    'Locale'

], function (QUI, QUIPanel, QUIConfirm, QUIButton, Payments, Grid, Mustache, QUILocale) {
    "use strict";

    var lg      = 'quiqqer/payments';
    var current = QUILocale.getCurrent();

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/payments/bin/backend/controls/Payments',

        Binds: [
            'refresh',
            '$onCreate',
            '$onInject',
            '$onResize',
            '$onDestroy',
            '$onEditClick',
            '$onPaymentChange',
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
                onCreate : this.$onCreate,
                onInject : this.$onInject,
                onResize : this.$onResize,
                onDestroy: this.$onDestroy
            });

            Payments.addEvents({
                onPaymentDeactivate: this.$onPaymentChange,
                onPaymentActivate  : this.$onPaymentChange,
                onPaymentDelete    : this.$onPaymentChange,
                onPaymentCreate    : this.$onPaymentChange,
                onPaymentUpdate    : this.$onPaymentChange
            });
        },

        /**
         * Refresh the value and the display
         */
        refresh: function () {
            if (!this.$Elm) {
                return;
            }

            this.Loader.show();

            var self = this;

            this.$Grid.getButtons().filter(function (Btn) {
                return Btn.getAttribute('name') === 'edit';
            })[0].disable();

            this.$Grid.getButtons().filter(function (Btn) {
                return Btn.getAttribute('name') === 'delete';
            })[0].disable();


            Payments.getPayments().then(function (result) {
                var toggle = function (Btn) {
                    var data      = Btn.getAttribute('data'),
                        paymentId = data.id,
                        status    = parseInt(data.active);


                    Btn.setAttribute('icon', 'fa fa-spinner fa-spin');

                    if (status) {
                        Payments.deactivatePayment(paymentId);
                        return;
                    }

                    Payments.activatePayment(paymentId);
                };

                for (var i = 0, len = result.length; i < len; i++) {
                    if (parseInt(result[i].active)) {
                        result[i].status = {
                            icon  : 'fa fa-check',
                            styles: {
                                lineHeight: 20,
                                padding   : 0,
                                width     : 20
                            },
                            events: {
                                onClick: toggle
                            }
                        };
                    } else {
                        result[i].status = {
                            icon  : 'fa fa-remove',
                            styles: {
                                lineHeight: 20,
                                padding   : 0,
                                width     : 20
                            },
                            events: {
                                onClick: toggle
                            }
                        };
                    }

                    result[i].paymentType_display = '';

                    result[i].title        = result[i].title[current];
                    result[i].workingTitle = result[i].workingTitle[current];

                    if ("paymentType" in result[i] && result[i].paymentType) {
                        result[i].paymentType_display = result[i].paymentType.title;
                    }
                }

                self.$Grid.setData({
                    data: result
                });

                self.Loader.hide();
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
                    dataType : 'button',
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
                }, {
                    header   : QUILocale.get(lg, 'payments.type'),
                    dataIndex: 'paymentType_display',
                    dataType : 'string',
                    width    : 200
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
         * event: on destroy
         */
        $onDestroy: function () {
            Payments.removeEvents({
                onPaymentDeactivate: this.$onPaymentChange,
                onPaymentActivate  : this.$onPaymentChange,
                onPaymentDelete    : this.$onPaymentChange,
                onPaymentCreate    : this.$onPaymentChange,
                onPaymentUpdate    : this.$onPaymentChange
            });
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
         * event : on payment change
         * if a payment changed
         */
        $onPaymentChange: function () {
            this.refresh();
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
                    onOpen  : function (Win) {
                        var Content = Win.getContent(),
                            Body    = Content.getElement('.textbody');

                        Win.Loader.show();

                        var Container = new Element('div', {
                            html  : QUILocale.get(lg, 'window.create.paymentType'),
                            styles: {
                                clear      : 'both',
                                'float'    : 'left',
                                marginTop  : 20,
                                paddingLeft: 80,
                                width      : '100%'
                            }
                        }).inject(Body, 'after');

                        var Select = new Element('select', {
                            styles: {
                                marginTop: 10,
                                maxWidth : '100%',
                                width    : 300
                            }
                        }).inject(Container);

                        Payments.getPaymentTypes().then(function (result) {
                            for (var i in result) {
                                if (!result.hasOwnProperty(i)) {
                                    continue;
                                }

                                new Element('option', {
                                    value: result[i].name,
                                    html : result[i].title
                                }).inject(Select);
                            }

                            Win.Loader.hide();
                        }).catch(function () {
                            Win.Loader.hide();
                        });
                    },
                    onSubmit: function (Win) {
                        Win.Loader.show();

                        var Select = Win.getContent().getElement('select');

                        Payments.createPayment(Select.value).then(function (newId) {
                            Win.close();
                            self.refresh();
                            self.openPayment(newId);
                        }).catch(function () {
                            Win.Loader.hide();
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
                ok_button  : {
                    text     : QUILocale.get('quiqqer/system', 'delete'),
                    textimage: 'fa fa-trash'
                },
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