'use strict';

/**
 * Extension to display the variant breadcrumb to navigate among a variant product structure (parents and children)
 *
 * @author    Adrien Pétremann <adrien.petremann@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
define([
        'jquery',
        'underscore',
        'oro/translator',
        'pim/form',
        'pim/template/product/form/navigation/variant-breadcrumb'
    ],
    function (
        $,
        _,
        __,
        BaseForm,
        template
    ) {
        return BaseForm.extend({
            className: 'AknBreadcrumb',
            template: _.template(template),
            events: {
            },

            /**
             * {@inheritdoc}
             */
            render: function () {
                var product = this.getFormData();
                console.log(product);
                // meta.family_variant.variant_attribute_sets

                this.$el.empty().append(
                    this.template({
                        product: product
                    })
                );
            }
        });
    }
);
