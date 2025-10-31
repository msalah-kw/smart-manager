(function (window) {
    function SaCommonProduct() {
        SaCommonManagerPro.call(this);
    }
    SaCommonProduct.prototype = Object.create(SaCommonManagerPro.prototype);
    SaCommonProduct.prototype.constructor = SaCommonProduct;
    //Code to handle after change of batch update field
    jQuery(document).on(this.pluginKey + '_post_format_columns', '#' + this.pluginSlug + '_editor_grid', function () {
        if (window[pluginKey].dashboardKey == 'product' && typeof (window[pluginKey].columnNamesBatchUpdate) != 'undefined') {
            //Code for handling product attribute
            if (typeof (window[pluginKey].columnNamesBatchUpdate['custom/product_attributes']) != 'undefined' && Object.keys(window[pluginKey].columnNamesBatchUpdate['custom/product_attributes']['values']).length > 0) {
                let attrObj = window[pluginKey].columnNamesBatchUpdate['custom/product_attributes'];
                let attributes = window[pluginKey].columnNamesBatchUpdate['custom/product_attributes']['values'];
                // Code for handling in new UI
                let attributesActions = {}
                let attributesValues = {}
                attributes.forEach((attr) => {
                    let key = attr.hasOwnProperty('key') ? attr.key : ''
                    if (key != '') {
                        let valObj = attr.hasOwnProperty('value') ? attr.value : {}
                        attributesActions[key] = (valObj.hasOwnProperty('lbl')) ? valObj.lbl : ''

                        let values = valObj.hasOwnProperty('val') ? valObj.val : {}

                        if (Object.keys(values).length > 0) {
                            attributesValues[key] = [{ 'key': 'all', 'value': 'All' }]
                            Object.keys(values).forEach((valKey) => {
                                attributesValues[key].push({ 'key': valKey, 'value': values[valKey] })
                            })
                        }
                    }
                })
                window[pluginKey].columnNamesBatchUpdate['custom/product_attributes']['actions'] = { ...window[pluginKey].batchUpdateActions['multilist'], ...{ copy_from: _x('copy from', 'Bulk Edit option for WooCommerce product attribute', 'smart-manager-for-wp-e-commerce') } };
                ['set_to', 'copy_from_field'].forEach(prop => delete window[pluginKey].columnNamesBatchUpdate['custom/product_attributes']['actions'][prop])
                window[pluginKey].columnNamesBatchUpdate['custom/product_attributes']['additionalValues'] = attributesActions
                window[pluginKey].columnNamesBatchUpdate['custom/product_attributes']['values'] = attributesValues
                window[pluginKey].columnNamesBatchUpdate['custom/product_attributes']['type'] = 'dropdown';
                if (!window[pluginKey].columnNamesBatchUpdate['custom/product_attributes']['additionalValues'].hasOwnProperty('custom')) {
                    window[pluginKey].columnNamesBatchUpdate['custom/product_attributes']['additionalValues']['custom'] = 'Custom';
                }
            }
            // Code for adding set_to_regular_price/set_to_sale_price in bulk edit panel.
            if (window[pluginKey].columnNamesBatchUpdate['postmeta/meta_key=_regular_price/meta_value=_regular_price']) {
                window[pluginKey].columnNamesBatchUpdate['postmeta/meta_key=_regular_price/meta_value=_regular_price']['custom_actions'] = { set_to_sale_price: _x('set to sale price', 'bulk edit option for WooCommerce product regular price', 'smart-manager-for-wp-e-commerce') }
            }
            if (window[pluginKey].columnNamesBatchUpdate['postmeta/meta_key=_sale_price/meta_value=_sale_price']) {
                window[pluginKey].columnNamesBatchUpdate['postmeta/meta_key=_sale_price/meta_value=_sale_price']['custom_actions'] = {
                    set_to_regular_price: _x('set to regular price', 'bulk edit option for WooCommerce product sale price', 'smart-manager-for-wp-e-commerce'), set_to_regular_price_and_decrease_by_per: {
                        label: _x('set to regular price and decrease by %', 'bulk edit option for WooCommerce product sale price', 'smart-manager-for-wp-e-commerce'),
                        hide_value: false
                    },
                    set_to_regular_price_and_decrease_by_num: {
                        label: _x('set to regular price and decrease by number', 'bulk edit option for WooCommerce product sale price', 'smart-manager-for-wp-e-commerce'),
                        hide_value: false
                    }
                }
            }
        }
    });
    window.SaCommonProduct = SaCommonProduct;
})(window);
