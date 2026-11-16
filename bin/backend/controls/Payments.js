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
    'Locale',
    'css!package/quiqqer/payments/bin/backend/controls/Payments.css'

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
            this.$refreshRequest = 0;
            this.$destroyed = false;

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
            if (!this.$Elm || !this.$Grid || this.$destroyed) {
                return Promise.resolve();
            }

            this.Loader.show();

            const self = this;
            const request = ++this.$refreshRequest;

            this.$Grid.getButtons().filter(function (Btn) {
                return Btn.getAttribute('name') === 'edit';
            })[0].disable();

            this.$Grid.getButtons().filter(function (Btn) {
                return Btn.getAttribute('name') === 'delete';
            })[0].disable();


            return Payments.getPayments(true).then(function (result) {
                if (self.$destroyed || request !== self.$refreshRequest) {
                    return;
                }

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
                    entry = {...result[i]};

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

                    if (typeof entry.title === 'string') {
                        title = entry.title;
                    } else if (entry.title && typeof entry.title[current] !== 'undefined') {
                        title = entry.title[current];
                    } else {
                        title = '---';
                    }

                    if (typeof entry.workingTitle === 'string') {
                        workingTitle = entry.workingTitle;
                    } else if (entry.workingTitle && typeof entry.workingTitle[current] !== 'undefined') {
                        workingTitle = entry.workingTitle[current];
                    } else {
                        workingTitle = '---';
                    }

                    entry.title = title;
                    entry.workingTitle = workingTitle;

                    if ("paymentType" in entry && entry.paymentType) {
                        entry.paymentType_display = entry.paymentType.title;
                    }

                    gridResult.push(entry);
                }

                self.$setGridPage(gridResult);
            }).finally(() => {
                if (!this.$destroyed && request === this.$refreshRequest) {
                    this.Loader.hide();
                    this.$onResize();
                }
            });
        },

        $setGridPage: function (rows) {
            const options = this.$Grid.options;
            const field = options.sortOn === 'status' ? 'active' : options.sortOn;
            const direction = options.sortBy === 'DESC' ? -1 : 1;

            if (['priority', 'active', 'title', 'workingTitle', 'id', 'paymentType_display'].includes(field)) {
                rows.sort((left, right) => {
                    if (['priority', 'active', 'id'].includes(field)) {
                        return direction * (Number(left[field] || 0) - Number(right[field] || 0));
                    }

                    return direction * String(left[field] ?? '').localeCompare(
                        String(right[field] ?? ''),
                        undefined,
                        {numeric: true, sensitivity: 'base'}
                    );
                });
            }

            const perPage = Math.max(1, Number(options.perPage) || 50);
            const page = Math.max(1, Math.min(Number(options.page) || 1, Math.ceil(rows.length / perPage)));
            this.$Grid.setData({
                data: rows.slice((page - 1) * perPage, page * perPage),
                total: rows.length,
                page: page
            });
        },

        /**
         * event: on create
         */
        $onCreate: function () {
            this.getElm().classList.add('quiqqer-payments-panel');
            this.getContent().style.padding = '10px';
            const Container = document.createElement('div');
            Container.style.height = '100%';
            this.getContent().appendChild(Container);
            const deleteLabel = QUILocale.get(lg, 'panel.button.delete');

            this.$Grid = new Grid(Container, {
                buttons    : [
                    {
                        name     : 'add',
                        text     : QUILocale.get(lg, 'panel.button.create'),
                        textimage: 'fa fa-plus',
                        events   : {
                            onClick: this.$openCreateDialog
                        }
                    },
                    {
                        name     : 'edit',
                        text     : QUILocale.get(lg, 'panel.button.edit'),
                        textimage: 'fa fa-edit',
                        disabled : true,
                        events   : {
                            onClick: this.$onEditClick
                        }
                    },
                    {
                        name     : 'delete',
                        text     : deleteLabel,
                        title    : deleteLabel,
                        'class'  : 'quiqqer-payments-delete',
                        icon     : 'fa fa-trash',
                        position : 'right',
                        disabled : true,
                        events   : {
                            onClick: this.$openDeleteDialog
                        }
                    }
                ],
                pagination: true,
                perPage: 50,
                page: 1,
                serverSort: true,
                sortOn: 'priority',
                sortBy: 'ASC',
                columnModel: [
                    {
                        header: QUILocale.get('quiqqer/core', 'id'),
                        dataIndex: 'id',
                        dataType: 'number',
                        width: 80,
                        className: 'grid-align-right',
                        styles: {'justify-content': 'flex-end'}
                    },
                    {
                        header   : QUILocale.get('quiqqer/core', 'priority'),
                        dataIndex: 'priority',
                        dataType : 'number',
                        width    : 110,
                        className: 'grid-align-right',
                        styles: {'justify-content': 'flex-end'}
                    },
                    {
                        header   : QUILocale.get('quiqqer/core', 'status'),
                        dataIndex: 'status',
                        dataType : 'button',
                        width    : 60,
                        className: 'grid-align-center'
                    },
                    {
                        header   : QUILocale.get('quiqqer/core', 'title'),
                        dataIndex: 'title',
                        dataType : 'string',
                        width    : 200
                    },
                    {
                        header   : QUILocale.get('quiqqer/core', 'workingtitle'),
                        dataIndex: 'workingTitle',
                        dataType : 'string',
                        width    : 200
                    },
                    {
                        header   : QUILocale.get(lg, 'grid.payments.type'),
                        dataIndex: 'paymentType_display',
                        dataType : 'string',
                        width    : 200
                    }
                ]
            });

            for (const name of ['add', 'edit', 'delete']) {
                const Button = this.$Grid.getButton(name).getElm();
                Button.style.removeProperty('outline');
                Button.firstElementChild?.setAttribute('aria-hidden', 'true');
            }
            this.$Grid.getButton('delete').getElm().setAttribute('aria-label', deleteLabel);

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
            return this.refresh();
        },

        /**
         * event: on destroy
         */
        $onDestroy: function () {
            this.$destroyed = true;
            ++this.$refreshRequest;
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
            return Promise.all([
                this.$Grid.setHeight(size.y - 20),
                this.$Grid.setWidth(size.x - 20)
            ]);
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

            if (data.length === 1) {
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

            if (selected.length !== 1) {
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
                    text     : QUILocale.get('quiqqer/core', 'delete'),
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

            if (selected.length !== 1) {
                Edit.disable();
                Delete.disable();
                return;
            }

            Edit.enable();
            Delete.enable();
        }
    });
});
