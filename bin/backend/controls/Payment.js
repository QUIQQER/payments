/**
 * @module package/quiqqer/payments/bin/backend/controls/Payment
 * @author www.pcsg.de (Henning Leutz)
 *
 * @require qui/QUI
 * @require qui/controls/desktop/Panel
 * @require package/quiqqer/payments/bin/backend/Payments
 */
define('package/quiqqer/payments/bin/backend/controls/Payment', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'package/quiqqer/payments/bin/backend/Payments',
    'Mustache',
    'Locale',

    'text!package/quiqqer/payments/bin/backend/controls/Payment.html'

], function (QUI, QUIPanel, Payments, Mustache, QUILocale, template) {
    "use strict";

    var lg = 'quiqqer/payments';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/payments/bin/backend/controls/Payment',

        Binds: [
            'showInformation',
            'showDescription',
            '$onCreate',
            '$showContainer',
            '$hideContainer'
        ],

        options: {
            paymentId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$Container = null;

            this.addEvents({
                onCreate: this.$onCreate,
                onInject: this.$onInject
            });
        },

        /**
         * event: on create
         */
        $onCreate: function () {
            this.addButton({
                text     : QUILocale.get('quiqqer/quiqqer', 'save'),
                textimage: 'fa fa-save',
                events   : {
                    onClick: function () {

                    }
                }
            });

            this.addCategory({
                name  : 'information',
                text  : 'Informationen',
                icon  : 'fa fa-file-o',
                events: {
                    onClick: this.showInformation
                }
            });

            this.addCategory({
                name  : 'description',
                text  : 'Beschreibung',
                icon  : 'fa fa-file-text-o',
                events: {
                    onClick: this.showDescription
                }
            });

            this.$Container = new Element('div', {
                styles: {
                    height  : '100%',
                    overflow: 'auto',
                    padding : 10,
                    position: 'relative',
                    width   : '100%'
                }
            }).inject(this.getContent());

            this.getContent().setStyles({
                padding : 0,
                position: 'relative'
            });
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            var self      = this,
                paymentId = this.getAttribute('paymentId');

            this.Loader.show();

            Payments.getPayment(paymentId).then(function (result) {
                self.setAttribute('title', result.title);
                self.setAttribute('icon', 'fa fa-credit-card-alt');
                self.setAttribute('data', result);
            }).then(function () {
                self.refresh();
                self.getCategory('information').click();
            });
        },

        /**
         * Show the information
         */
        showInformation: function () {
            var self = this;

            this.$hideContainer().then(function (Container) {
                var data = self.getAttribute('data');

                Container.set({
                    html: Mustache.render(template, {
                        header              : QUILocale.get(lg, 'payment.edit.template.title'),
                        id                  : QUILocale.get(lg, 'payment.edit.template.id'),
                        title               : QUILocale.get(lg, 'payment.edit.template.title'),
                        workingTitle        : QUILocale.get('quiqqer/system', 'workingtitle'),
                        usageHeader         : QUILocale.get(lg, 'payment.edit.template.usage'),
                        usageFrom           : QUILocale.get(lg, 'payment.edit.template.usage.from'),
                        usageTo             : QUILocale.get(lg, 'payment.edit.template.usage.to'),
                        usageAmountOf       : QUILocale.get(lg, 'payment.edit.template.shopping.amount.of'),
                        usageAmountTo       : QUILocale.get(lg, 'payment.edit.template.shopping.amount.to'),
                        usageValueOf        : QUILocale.get(lg, 'payment.edit.template.purchase.value.of'),
                        usageValueTo        : QUILocale.get(lg, 'payment.edit.template.purchase.value.to'),
                        usageAssignment     : QUILocale.get(lg, 'payment.edit.template.assignment'),
                        usageAssignmentAreas: QUILocale.get(lg, 'payment.edit.template.areas'),
                        calculationPriority : QUILocale.get(lg, 'payment.edit.template.calculationPriority'),

                        usageAssignmentProduct : QUILocale.get(lg, 'payment.edit.template.assignment.product'),
                        usageAssignmentCategory: QUILocale.get(lg, 'payment.edit.template.assignment.category'),
                        usageAssignmentUser    : QUILocale.get(lg, 'payment.edit.template.assignment.user'),
                        usageAssignmentCombine : QUILocale.get(lg, 'payment.edit.template.assignment.combine')
                    })
                });

                Container.getElement('.field-id').set('html', data.id);

                console.warn(data);

                return self.$showContainer();
            });
        },

        /**
         * SHow the description
         */
        showDescription: function () {
            var self = this;

            this.$hideContainer().then(function (Container) {
                return new Promise(function (resolve) {
                    require(['controls/lang/ContentMultiLang'], function (ContentMultiLang) {
                        new ContentMultiLang({
                            styles: {
                                height: '100%'
                            },
                            events: {
                                onLoad: resolve
                            }
                        }).inject(Container);
                    });
                });
            }).then(function () {
                return self.$showContainer();
            });
        },

        /**
         * Show the container
         *
         * @return {Promise}
         */
        $showContainer: function () {
            var self = this;

            return new Promise(function (resolve) {
                QUI.parse(self.$Container).then(function () {
                    moofx(self.$Container).animate({
                        opacity: 1,
                        top    : 0
                    }, {
                        duration: 250,
                        callback: function () {
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
        $hideContainer: function () {
            var self = this;

            this.Loader.show();

            return new Promise(function (resolve) {
                moofx(self.$Container).animate({
                    opacity: 0,
                    top    : -20
                }, {
                    duration: 250,
                    callback: function () {
                        self.$Container.set('html', '');
                        resolve(self.$Container);
                    }
                });
            });
        }
    });
});
