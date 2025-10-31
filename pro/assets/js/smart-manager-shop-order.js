jQuery(document).on('smart_manager_post_load_grid','#sm_editor_grid', function() {
    if( typeof(window.smart_manager.columnNamesBatchUpdate) === 'undefined' ||(!["shop_subscription", "shop_order"].includes(window.smart_manager.current_selected_dashboard) && !["shop_subscription", "shop_order"].includes(window.smart_manager.viewPostTypes[window.smart_manager.current_selected_dashboard]))) {
        return;
    }
    window.smart_manager.columnNamesBatchUpdate['custom/line_items'] = {
        "title": _x('Line items', 'Bulk Edit option for WooCommerce order line items', 'smart-manager-for-wp-e-commerce'),
        "type": "dropdown",
        "editor": "select",
        "values": [
        ],
        'actions': {
            add_coupon: _x('add coupon', 'Bulk Edit option for WooCommerce order line items', 'smart-manager-for-wp-e-commerce'),
            remove_coupon: _x('remove coupon', 'Bulk Edit option for WooCommerce order line items', 'smart-manager-for-wp-e-commerce'),
            copy_coupon_from: _x('copy coupon from', 'Bulk Edit option for WooCommerce order line items', 'smart-manager-for-wp-e-commerce'),
            add_product: _x('add product', 'Bulk Edit option for WooCommerce order line items', 'smart-manager-for-wp-e-commerce'),
            remove_product: _x('remove product', 'Bulk Edit option for WooCommerce order line items', 'smart-manager-for-wp-e-commerce'),
            copy_product_from: _x('copy product from', 'Bulk Edit option for WooCommerce order line items', 'smart-manager-for-wp-e-commerce'),
            copy_from: _x('copy from', 'Bulk Edit option for WooCommerce order line items', 'smart-manager-for-wp-e-commerce')
        }
    }
    if( window.smart_manager.current_selected_dashboard !== 'shop_order' || typeof(window.smart_manager.columnNamesBatchUpdate) === 'undefined' ) {
        return;
    }
    let orderNotesCols = ['note_for_customer'];
    orderNotesCols.forEach(orderNotesCol => {
        if(typeof(window.smart_manager.columnNamesBatchUpdate['custom/'+orderNotesCol]) !== 'undefined'){
            window.smart_manager.columnNamesBatchUpdate['custom/'+orderNotesCol]['actions'] = {...window.smart_manager.batch_update_actions['dropdown'], ...{add_to: _x('add to', 'Bulk Edit option for WooCommerce shop order - order notes', 'smart-manager-for-wp-e-commerce'),remove_from: _x('remove from', 'Bulk Edit option for WooCommerce shop order - order notes', 'smart-manager-for-wp-e-commerce')}};
        }
        let columnObj = window.smart_manager?.columnNamesBatchUpdate?.['custom/' + orderNotesCol];
        if (columnObj && typeof columnObj === 'object' && columnObj.actions && typeof columnObj.actions === 'object') {
            ['set_to'].forEach(prop => delete columnObj.actions[prop]);
        }
     });
})
