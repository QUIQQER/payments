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

    const lg = 'quiqqer/payments';
    const current = QUILocale.getCurrent();

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

            const self = this;

            this.$Grid.getButtons().filter(function (Btn) {
                return Btn.getAttribute('name') === 'edit';
            })[0].disable();

            this.$Grid.getButtons().filter(function (Btn) {
                return Btn.getAttribute('name') === 'delete';
            })[0].disable();


            Payments.getPayments().then(function (result) {
                const toggle = function (Btn) {
                    const data      = Btn.getAttribute('data'),
                          paymentId = data.id,
                          status    = parseInt(data.active);


                    Btn.setAttribute('icon', 'fa fa-spinner fa-spin');

                    if (status) {
                        Payments.deactivatePayment(paymentId);
                        return;
                    }

                    Payments.activatePayment(paymentId);
                };

                let i, len, entry, title, workingTitle;

                const gridResult = [];

                for (i = 0, len = result.length; i < len; i++) {
                    entry = result[i];

                    if (parseInt(entry.active)) {
                        entry.status = {
                            icon  : 'fa fa-check',
                            styles: {
                                'float'   : 'none',
                                lineHeight: 20,
                                padding   : 0,
                                width     : 20
                            },
                            events: {
                                onClick: toggle
                            }
                        };
                    } else {
                        entry.status = {
                            icon  : 'fa fa-remove',
                            styles: {
                                'float'   : 'none',
                                lineHeight: 20,
                                padding   : 0,
                                width     : 20
                            },
                            events: {
                                onClick: toggle
                            }
                        };
                    }

                    entry.paymentType_display = '';

                    if (typeof entry.title === 'undefined' ||
                        typeof entry.title[current] === 'undefined') {
                        title = '---';
                    } else if (typeOf(entry.title) === 'string') {
                        title = entry.title;
                    } else if (typeof entry.title[current] !== 'undefined') {
                        title = entry.title[current];
                    }

                    if (typeof entry.workingTitle === 'undefined' ||
                        typeof entry.workingTitle[current] === 'undefined') {
                        workingTitle = '---';
                    } else if (typeOf(entry.workingTitle) === 'string') {
                        workingTitle = entry.workingTitle;
                    } else if (typeof entry.workingTitle[current] !== 'undefined') {
                        workingTitle = entry.workingTitle[current];
                    }

                    entry.title = title;
                    entry.workingTitle = workingTitle;

                    if ("paymentType" in entry && entry.paymentType) {
                        entry.paymentType_display = entry.paymentType.title;
                    }

                    gridResult.push(entry);
                }

                self.$Grid.setData({
                    data: gridResult
                });

                self.Loader.hide();
            });
        },

        /**
         * event: on create
         */
        $onCreate: function () {
            const Container = new Element('div', {
                styles: {
                    minHeight: 300,
                    width    : '100%'
                }
            }).inject(this.getContent());

            this.$Grid = new Grid(Container, {
                buttons    : [
                    {
                        name     : 'add',
                        text     : QUILocale.get('quiqqer/quiqqer', 'add'),
                        textimage: 'fa fa-plus',
                        events   : {
                            onClick: this.$openCreateDialog
                        }
                    },
                    {
                        type: 'separator'
                    },
                    {
                        name     : 'edit',
                        text     : QUILocale.get('quiqqer/quiqqer', 'edit'),
                        textimage: 'fa fa-edit',
                        disabled : true,
                        events   : {
                            onClick: this.$onEditClick
                        }
                    },
                    {
                        name     : 'delete',
                        text     : QUILocale.get('quiqqer/system', 'delete'),
                        textimage: 'fa fa-trash',
                        disabled : true,
                        events   : {
                            onClick: this.$openDeleteDialog
                        }
                    }
                ],
                columnModel: [
                    {
                        header   : QUILocale.get('quiqqer/system', 'priority'),
                        dataIndex: 'priority',
                        dataType : 'number',
                        width    : 50
                    },
                    {
                        header   : QUILocale.get('quiqqer/system', 'status'),
                        dataIndex: 'status',
                        dataType : 'button',
                        width    : 60,
                        className: 'grid-align-center'
                    },
                    {
                        header   : QUILocale.get('quiqqer/system', 'title'),
                        dataIndex: 'title',
                        dataType : 'string',
                        width    : 200
                    },
                    {
                        header   : QUILocale.get('quiqqer/system', 'workingtitle'),
                        dataIndex: 'workingTitle',
                        dataType : 'string',
                        width    : 200
                    },
                    {
                        header   : QUILocale.get('quiqqer/system', 'id'),
                        dataIndex: 'id',
                        dataType : 'number',
                        width    : 30
                    },
                    {
                        header   : QUILocale.get(lg, 'grid.payments.type'),
                        dataIndex: 'paymentType_display',
                        dataType : 'string',
                        width    : 200
                    }
                ]
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

            const Body = this.getContent();

            if (!Body) {
                return;
            }

            const size = Body.getSize();
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
            const data = this.$Grid.getSelectedData();

            if (data.length) {
                this.openPayment(data[0].id);
            }
        },

        /**
         * open the add dialog
         */
        $openCreateDialog: function () {
            const self = this;

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
                        const Content = Win.getContent(),
                              Body    = Content.getElement('.textbody');

                        Win.Loader.show();

                        const Container = new Element('div', {
                            html  : QUILocale.get(lg, 'window.create.paymentType'),
                            styles: {
                                clear      : 'both',
                                'float'    : 'left',
                                marginTop  : 20,
                                paddingLeft: 80,
                                width      : '100%'
                            }
                        }).inject(Body, 'after');

                        const Select = new Element('select', {
                            styles: {
                                marginTop: 10,
                                maxWidth : '100%',
                                width    : 300
                            }
                        }).inject(Container);

                        Payments.getPaymentTypes().then(function (result) {
                            for (const i in result) {
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

                        const Select = Win.getContent().getElement('select');

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
            const selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return;
            }

            const self = this;
            let payment   = selected[0].title,
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
            const selected = this.$Grid.getSelectedIndices(),
                  buttons  = this.$Grid.getButtons();

            const Edit = buttons.filter(function (Btn) {
                return Btn.getAttribute('name') === 'edit';
            })[0];

            const Delete = buttons.filter(function (Btn) {
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
