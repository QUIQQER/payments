/**
 * @module package/quiqqer/payments/bin/backend/controls/Payment
 * @author www.pcsg.de (Henning Leutz)
 *
 * Payment Panel - Eine Zahlungsart im Backend
 */
define('package/quiqqer/payments/bin/backend/controls/Payment', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/windows/Confirm',
    'package/quiqqer/payments/bin/backend/Payments',
    'package/quiqqer/translator/bin/Translator',
    'qui/utils/Form',
    'Mustache',
    'Locale',
    'Ajax',

    'text!package/quiqqer/payments/bin/backend/controls/Payment.html'

], function(QUI, QUIPanel, QUIConfirm, Payments, Translator, FormUtils,
    Mustache, QUILocale, QUIAjax, template
) {
    'use strict';

    const lg = 'quiqqer/payments';

    return new Class({

        Extends: QUIPanel,
        Type: 'package/quiqqer/payments/bin/backend/controls/Payment',

        Binds: [
            'showInformation',
            'showDescription',
            'openDeleteDialog',
            'showOrderInformation',
            'toggleStatus',
            'save',
            '$onCreate',
            '$onRefresh',
            '$showContainer',
            '$hideContainer',
            '$onPaymentDelete',
            '$onPaymentChange',
            '$formatPaymentFee'
        ],

        options: {
            paymentId: false
        },

        initialize: function(options) {
            this.parent(options);

            this.setAttributes({
                '#id': 'paymentId' in options ? options.paymentId : false,
                title: QUILocale.get(lg, 'payment.type.panel.title')
            });

            this.$Container = null;
            this.$IconField = null;

            this.$DataTitle = null;
            this.$DataWorkingTitle = null;
            this.$DataDescription = null;
            this.$DataOrderInformation = null;
            this.$PaymentFeeTitle = null;

            this.addEvents({
                onCreate: this.$onCreate,
                onInject: this.$onInject,
                onRefresh: this.$onRefresh,
                onDestroy: this.$onDestroy
            });
        },

        /**
         * event: on create
         */
        $onCreate: function() {
            this.addButton({
                name: 'save',
                title: QUILocale.get('quiqqer/quiqqer', 'save'),
                text: QUILocale.get('quiqqer/quiqqer', 'save'),
                textimage: 'fa fa-save',
                events: {
                    onClick: function() {
                        this.save().catch(function() {
                            // nothing
                        });
                    }.bind(this)
                }
            });

            this.addButton({
                type: 'separator'
            });

            this.addButton({
                name: 'status',
                text: QUILocale.get('quiqqer/quiqqer', 'deactivate'),
                title: QUILocale.get('quiqqer/quiqqer', 'deactivate'),
                textimage: 'fa fa-remove',
                disabled: true,
                events: {
                    onClick: this.toggleStatus
                }
            });

            this.addButton({
                name: 'delete',
                title: QUILocale.get('quiqqer/system', 'delete'),
                icon: 'fa fa-trash',
                events: {
                    onClick: this.openDeleteDialog
                },
                styles: {
                    'float': 'right'
                }
            });


            this.addCategory({
                name: 'information',
                text: QUILocale.get('quiqqer/system', 'information'),
                icon: 'fa fa-file-o',
                events: {
                    onClick: this.showInformation
                }
            });

            this.addCategory({
                name: 'description',
                text: QUILocale.get('quiqqer/system', 'description'),
                icon: 'fa fa-file-text-o',
                events: {
                    onClick: this.showDescription
                }
            });

            this.addCategory({
                name: 'orderInformation',
                text: QUILocale.get(lg, 'panel.payment.order.information'),
                icon: 'fa fa-file-text-o',
                events: {
                    onClick: this.showOrderInformation
                }
            });

            this.$Container = new Element('div', {
                styles: {
                    height: '100%',
                    overflow: 'auto',
                    padding: 10,
                    position: 'relative',
                    width: '100%'
                }
            }).inject(this.getContent());

            this.getContent().setStyles({
                padding: 0,
                position: 'relative'
            });
        },

        /**
         * event : on inject
         */
        $onInject: function() {
            const self = this;

            this.Loader.show();

            Payments.addEvents({
                onPaymentDeactivate: this.$onPaymentChange,
                onPaymentActivate: this.$onPaymentChange,
                onPaymentDelete: this.$onPaymentDelete,
                onPaymentUpdate: this.$onPaymentChange
            });

            this.reload().then(function() {
                self.getCategory('information').click();
            });
        },

        /**
         * event: on refresh
         */
        $onRefresh: function() {
            const data = this.getAttribute('data');

            if (!data || !('active' in data)) {
                return;
            }

            const status = parseInt(data.active),
                Status = this.getButtons('status');

            if (status) {
                Status.setAttribute('text', QUILocale.get('quiqqer/quiqqer', 'deactivate'));
                Status.setAttribute('title', QUILocale.get('quiqqer/quiqqer', 'deactivate'));
                Status.setAttribute('textimage', 'fa fa-remove');
            } else {
                Status.setAttribute('text', QUILocale.get('quiqqer/quiqqer', 'activate'));
                Status.setAttribute('title', QUILocale.get('quiqqer/quiqqer', 'activate'));
                Status.setAttribute('textimage', 'fa fa-check');
            }

            Status.enable();
        },

        /**
         * event: on destroy
         */
        $onDestroy: function() {
            Payments.removeEvents({
                onPaymentDeactivate: this.$onPaymentChange,
                onPaymentActivate: this.$onPaymentChange,
                onPaymentDelete: this.$onPaymentDelete,
                onPaymentUpdate: this.$onPaymentChange
            });
        },

        /**
         * event: on payment change
         *
         * @param {Object} PaymentsInstance
         * @param {Number} paymentId
         * @param {Object} data
         */
        $onPaymentChange: function(PaymentsInstance, paymentId, data) {
            if (paymentId !== this.getAttribute('paymentId')) {
                return;
            }

            this.setAttribute('data', data);
            this.refresh();
        },

        /**
         * event: on payment change
         *
         * @param {Object} PaymentsInstance
         * @param {Number} paymentId
         */
        $onPaymentDelete: function(PaymentsInstance, paymentId) {
            if (paymentId === this.getAttribute('paymentId')) {
                this.destroy();
            }
        },

        /**
         * Reload the payment data
         */
        reload: function() {
            const self = this,
                paymentId = this.getAttribute('paymentId');

            return Payments.getPayment(paymentId).then(function(result) {
                const current = QUILocale.getCurrent();

                self.setAttribute('title', QUILocale.get(lg, 'payment.type.panel.title.payment', {
                    paymentType: result.title[current]
                }));

                self.setAttribute('icon', 'fa fa-credit-card-alt');

                delete result.title;
                delete result.workingTitle;
                delete result.paymentFeeTitle;
                delete result.description;

                self.setAttribute('data', result);
                self.refresh();
            }).catch(function(err) {
                console.error(err);
                self.destroy();
            });
        },

        /**
         * Save the current payment settings
         *
         * @return {Promise}
         */
        save: function() {
            const self = this,
                paymentId = this.getAttribute('paymentId');

            this.Loader.show();
            this.$unloadContainerData();

            this.$__storageData = {};
            this.$__running = false;

            const data = this.getAttribute('data');

            return new Promise(function(resolve, reject) {
                Payments.updatePayment(paymentId, data).then(function() {
                    return self.reload();
                }).then(function() {
                    resolve();
                    self.Loader.hide();
                }).catch(function(e) {
                    self.Loader.hide();
                    reject(e);
                });
            });
        },

        /**
         * Activate the payment
         */
        activate: function() {
            const self = this,
                paymentId = this.getAttribute('paymentId');

            self.Loader.show();

            Payments.activatePayment(paymentId).then(function(data) {
                self.setAttribute('data', data);
                self.refresh();
                self.Loader.hide();
            });
        },

        /**
         * Deactivate the payment
         */
        deactivate: function() {
            const self = this,
                paymentId = this.getAttribute('paymentId');

            Payments.deactivatePayment(paymentId).then(function(data) {
                self.setAttribute('data', data);
                self.refresh();
                self.Loader.hide();
            });
        },

        /**
         * Toggle the active status of the payment
         */
        toggleStatus: function() {
            const data = this.getAttribute('data');

            if (!('active' in data)) {
                return;
            }

            if (parseInt(data.active)) {
                return this.deactivate();
            }

            return this.activate();
        },

        /**
         * Show the information
         */
        showInformation: function() {
            const self = this,
                data = self.getAttribute('data'),
                current = QUILocale.getCurrent();

            this.$hideContainer().then(function(Container) {
                Container.set({
                    html: Mustache.render(template, {
                        header: QUILocale.get(lg, 'payment.edit.template.title'),
                        id: QUILocale.get(lg, 'payment.edit.template.id'),
                        type: QUILocale.get(lg, 'payment.edit.template.type'),
                        title: QUILocale.get(lg, 'payment.edit.template.title'),
                        workingTitle: QUILocale.get('quiqqer/system', 'workingtitle'),
                        usageHeader: QUILocale.get(lg, 'payment.edit.template.usage'),
                        usageFrom: QUILocale.get(lg, 'payment.edit.template.usage.from'),
                        usageTo: QUILocale.get(lg, 'payment.edit.template.usage.to'),
                        usageAmountOf: QUILocale.get(lg, 'payment.edit.template.shopping.amount.of'),
                        usageAmountTo: QUILocale.get(lg, 'payment.edit.template.shopping.amount.to'),
                        usageValueOf: QUILocale.get(lg, 'payment.edit.template.purchase.value.of'),
                        usageValueTo: QUILocale.get(lg, 'payment.edit.template.purchase.value.to'),
                        usageAssignment: QUILocale.get(lg, 'payment.edit.template.assignment'),
                        usageAssignmentAreas: QUILocale.get(lg, 'payment.edit.template.areas'),
                        calculationPriority: QUILocale.get(lg, 'payment.edit.template.calculationPriority'),
                        customIcon: QUILocale.get(lg, 'payment.edit.template.customIcon'),
                        customIconDesc: QUILocale.get(lg, 'payment.edit.template.customIcon.description'),

                        paymentFeeHeader: QUILocale.get(lg, 'payment.edit.template.paymentFee.header'),
                        paymentFeeAmount: QUILocale.get(lg, 'payment.edit.template.paymentFee.amount'),
                        paymentFeeTitle: QUILocale.get(lg, 'payment.edit.template.paymentFee.title'),
                        paymentFeeHelp: QUILocale.get(lg, 'payment.edit.template.paymentFee.title.help'),

                        usageAssignmentProduct: QUILocale.get(lg, 'payment.edit.template.assignment.product'),
                        usageAssignmentCategory: QUILocale.get(lg, 'payment.edit.template.assignment.category'),
                        usageAssignmentUser: QUILocale.get(lg, 'payment.edit.template.assignment.user'),
                        usageAssignmentCurrencies: QUILocale.get(lg, 'payment.edit.template.assignment.currency')
                    })
                });

                Container.getElement('.field-id').set('html', data.id);

                if (typeof data.paymentType !== 'undefined' &&
                    typeof data.paymentType.title !== 'undefined') {
                    Container.getElement('.field-type').set('html', data.paymentType.title);
                }

                FormUtils.setDataToForm(data, Container.getElement('form'));

                Container.getElement('[name="paymentFee"]').addEvent('blur', self.$formatPaymentFee);

                self.$formatPaymentFee();

                return Promise.all([
                    self.$getTranslationData('title'),
                    self.$getTranslationData('workingTitle'),
                    self.$getTranslationData('paymentFeeTitle')
                ]);
            }).then(function(translationData) {
                return new Promise(function(resolve, reject) {
                    require(['controls/lang/InputMultiLang'], function(InputMultiLang) {
                        self.$DataTitle = new InputMultiLang().replaces(self.$Container.getElement('.payment-title'));
                        self.$DataWorkingTitle =
                            new InputMultiLang().replaces(self.$Container.getElement('.payment-workingTitle'));
                        self.$PaymentFeeTitle =
                            new InputMultiLang().replaces(self.$Container.getElement('.payment-fee-title'));

                        self.$DataTitle.setData(translationData[0]);
                        self.$DataWorkingTitle.setData(translationData[1]);
                        self.$PaymentFeeTitle.setData(translationData[2]);

                        if (typeof translationData[0][current] !== 'undefined') {
                            if (!translationData[0][current]) {
                                translationData[0][current] = '';
                            }

                            self.setAttribute('title', QUILocale.get(lg, 'payment.type.panel.title.payment', {
                                paymentType: translationData[0][current]
                            }));

                            self.refresh();
                        }

                        resolve();
                    }, reject);
                });
            }).then(function() {
                return self.$showContainer();
            }).then(function() {
                self.$IconField = QUI.Controls.getById(
                    self.$Container.getElement('[name="icon"]').get('data-quiid')
                );
            }).catch(function(err) {
                console.error(err);
            });
        },

        /**
         * Show the description
         */
        showDescription: function() {
            const self = this;

            Promise.all([
                this.$hideContainer(),
                this.$getTranslationData('description')
            ]).then(function(result) {
                const Container = result[0],
                    description = result[1];

                return new Promise(function(resolve) {
                    require(['controls/lang/ContentMultiLang'], function(ContentMultiLang) {
                        self.$DataDescription = new ContentMultiLang({
                            styles: {
                                height: '100%'
                            },
                            events: {
                                onLoad: function() {
                                    self.$DataDescription.setData(description);
                                    resolve();
                                }
                            }
                        }).inject(Container);
                    });
                });
            }).then(function() {
                return self.$showContainer();
            });
        },

        /**
         * Show the order information
         */
        showOrderInformation: function() {
            const self = this;

            Promise.all([
                this.$hideContainer(),
                this.$getTranslationData('orderInformation')
            ]).then(function(result) {
                const Container = result[0],
                    description = result[1];

                return new Promise(function(resolve) {
                    require(['controls/lang/ContentMultiLang'], function(ContentMultiLang) {
                        self.$DataOrderInformation = new ContentMultiLang({
                            styles: {
                                height: '100%'
                            },
                            events: {
                                onLoad: function() {
                                    self.$DataOrderInformation.setData(description);
                                    resolve();
                                }
                            }
                        }).inject(Container);
                    });
                });
            }).then(function() {
                return self.$showContainer();
            });
        },

        /**
         * Opens the delete dialog
         */
        openDeleteDialog: function() {
            const self = this,
                paymentId = this.getAttribute('paymentId');

            new QUIConfirm({
                texticon: 'fa fa-trash',
                icon: 'fa fa-trash',
                title: QUILocale.get(lg, 'window.delete.title'),
                information: QUILocale.get(lg, 'window.delete.information', {
                    payment: this.getAttribute('title')
                }),
                text: QUILocale.get(lg, 'window.delete.text', {
                    payment: this.getAttribute('title')
                }),
                autoclose: false,
                maxHeight: 400,
                maxWidth: 600,
                ok_button: {
                    text: QUILocale.get('quiqqer/system', 'delete'),
                    textimage: 'fa fa-trash'
                },
                events: {
                    onSubmit: function(Win) {
                        Win.Loader.show();

                        Payments.deletePayment(paymentId).then(function() {
                            Win.close();
                            self.destroy();
                        });
                    }
                }
            }).open();
        },

        // region UI Helper

        /**
         * Show the container
         *
         * @return {Promise}
         */
        $showContainer: function() {
            const self = this;

            return new Promise(function(resolve) {
                QUI.parse(self.$Container).then(function() {
                    moofx(self.$Container).animate({
                        opacity: 1,
                        top: 0
                    }, {
                        duration: 250,
                        callback: function() {
                            self.Loader.hide();
                            resolve(self.$Container);
                        }
                    });
                });
            });
        },

        /**
         * Hide the container
         *
         * @return {Promise}
         */
        $hideContainer: function() {
            const self = this;

            this.Loader.show();

            return new Promise(function(resolve) {
                moofx(self.$Container).animate({
                    opacity: 0,
                    top: -20
                }, {
                    duration: 250,
                    callback: function() {
                        self.$unloadContainer();
                        self.$Container.set('html', '');
                        resolve(self.$Container);
                    }
                });
            });
        },

        /**
         * unload the data from the current category
         */
        $unloadContainer: function() {
            this.$unloadContainerData();

            if (this.$DataDescription) {
                this.$DataDescription.destroy();
                this.$DataDescription = null;
            }

            if (this.$DataOrderInformation) {
                this.$DataOrderInformation.destroy();
                this.$DataOrderInformation = null;
            }

            if (this.$DataTitle) {
                this.$DataTitle.destroy();
                this.$DataTitle = null;
            }

            if (this.$DataWorkingTitle) {
                this.$DataWorkingTitle.destroy();
                this.$DataWorkingTitle = null;
            }

            if (this.$PaymentFeeTitle) {
                this.$PaymentFeeTitle.destroy();
                this.$PaymentFeeTitle = null;
            }

            if (this.$IconField) {
                this.$IconField.destroy();
                this.$IconField = null;
            }
        },

        /**
         * Unload the current container data and set the data to the payment object
         * The data is not saved
         */
        $unloadContainerData: function() {
            const Form = this.$Container.getElement('form');

            if (this.$DataDescription) {
                this.$setData('description', this.$DataDescription.getData());
            }

            if (this.$DataOrderInformation) {
                this.$setData('orderInformation', this.$DataOrderInformation.getData());
            }

            if (this.$DataTitle) {
                this.$setData('title', this.$DataTitle.getData());
            }

            if (this.$DataWorkingTitle) {
                this.$setData('workingTitle', this.$DataWorkingTitle.getData());
            }

            if (this.$PaymentFeeTitle) {
                this.$setData('paymentFeeTitle', this.$PaymentFeeTitle.getData());
            }

            if (this.$IconField) {
                this.$setData('icon', this.$IconField.getValue());
            }

            if (Form) {
                const formData = FormUtils.getFormData(Form);

                for (let key in formData) {
                    if (formData.hasOwnProperty(key)) {
                        this.$setData(key, formData[key]);
                    }
                }
            }
        },

        /**
         *
         * @return {Promise}
         */
        $formatPaymentFee: function() {
            const PaymentField = this.getContent().getElement('[name="paymentFee"]');

            if (PaymentField.value === '') {
                return Promise.resolve();
            }

            return new Promise(function(resolve, reject) {
                QUIAjax.get('package_quiqqer_erp_ajax_money_formatPrice', function(result) {
                    PaymentField.value = result;
                    resolve();
                }, {
                    'package': 'quiqqer/erp',
                    price: PaymentField.value,
                    onError: reject
                });
            });
        },

        //endregion

        /**
         * set the payment data for the panel
         *
         * @param {String} name
         * @param {String} value
         */
        $setData: function(name, value) {
            let data = this.getAttribute('data');
            data[name] = value;

            this.setAttribute('data', data);
        },

        /**
         * Return a data entry
         *
         * @param {String} name
         */
        $getData: function(name) {
            const data = this.getAttribute('data');

            if (name in data) {
                return data[name];
            }

            return false;
        },

        /**
         * Return the current data of a language field
         *
         * @param name
         * @return {Promise}
         */
        $getTranslationData: function(name) {
            const paymentId = this.getAttribute('paymentId');

            const title = 'payment.' + paymentId + '.title';
            const description = 'payment.' + paymentId + '.description';
            const workingTitle = 'payment.' + paymentId + '.workingTitle';
            const paymentFeeTitle = 'payment.' + paymentId + '.paymentFeeTitle';
            const orderInformation = 'payment.' + paymentId + '.orderInformation';

            if (typeof this.$__running === 'undefined') {
                this.$__storageData = {};
                this.$__running = false;
            }

            const getData = function() {
                return new Promise(function(resolve, reject) {
                    if ('title' in this.$__storageData) {
                        resolve();
                        return;
                    }

                    if (this.$__running) {
                        (function() {
                            getData().then(resolve, reject);
                        }).delay(100);
                        return;
                    }

                    this.$__running = true;

                    Promise.all([
                        Translator.get(lg, title, lg),
                        Translator.get(lg, description, lg),
                        Translator.get(lg, workingTitle, lg),
                        Translator.get(lg, paymentFeeTitle, lg),
                        Translator.get(lg, orderInformation, lg),
                        Translator.getAvailableLanguages()
                    ]).then(function(promiseResult) {
                        this.$__storageData.title = promiseResult[0];
                        this.$__storageData.description = promiseResult[1];
                        this.$__storageData.workingTitle = promiseResult[2];
                        this.$__storageData.paymentFeeTitle = promiseResult[3];
                        this.$__storageData.orderInformation = promiseResult[4];
                        this.$__storageData.languages = promiseResult[5];

                        this.$__running = false;
                        resolve();
                    }.bind(this), reject);
                }.bind(this));
            }.bind(this);


            return getData().then(function() {
                const data = this.$__storageData;
                const result = {};
                let value = data[name];

                if (this.$getData(name)) {
                    value = this.$getData(name);
                }

                data.languages.each(function(language) {
                    let val = value[language];

                    if (value[language + '_edit'] !== '' && value[language + '_edit']) {
                        val = value[language + '_edit'];
                    }

                    result[language] = val;
                });

                return result;
            }.bind(this));
        }
    });
});
