
//Code to handle after change of batch update field in case of product attributes and categories
jQuery(document).on('sm_batch_update_field_on_change', function(e, rowId, selectedField, type, colVal) {

	if( jQuery("#"+rowId+" #batch_update_value_td_2").length ) { //handling for removing custom attribute td
		jQuery("#"+rowId+" #batch_update_value_td_2").remove();
	}

	if( jQuery('#batchmod_sm_editor_grid #batch_update_value_td_2').length == 0 ) { //handling for restoring the batch update dialog size
		jQuery("#batchmod_sm_editor_grid").css('width','640px');
	}

	if( selectedField == 'custom/product_attributes' ) {
		window.smart_manager.batch_update_action_options_default = '<option value="" disabled selected>'+_x('Select Attribute', 'bulk edit default action for WooCommerce product attribute', 'smart-manager-for-wp-e-commerce')+'</option>';

		if( Object.keys(colVal).length > 0 ) {
			for( attr_nm in colVal ) {
				window.smart_manager.batch_update_action_options_default += '<option value="'+ attr_nm +'">'+ colVal[attr_nm].lbl +'</option>';
			}
		}

	} else if( selectedField.indexOf('custom/product_cat') != -1 ) {
		window.smart_manager.batch_update_action_options_default = '<option value="" disabled selected>'+_x('Select Action', 'bulk edit default action for WooCommerce product category', 'smart-manager-for-wp-e-commerce')+'</option>'+
																	'<option value="set_to">'+_x('set to', 'bulk edit option for WooCommerce product category', 'smart-manager-for-wp-e-commerce')+'</option>'+
																	'<option value="add_to">'+_x('add to', 'bulk edit option for WooCommerce product category', 'smart-manager-for-wp-e-commerce')+'</option>'+
																	'<option value="remove_from">'+_x('remove from', 'bulk edit option for WooCommerce product category', 'smart-manager-for-wp-e-commerce')+'</option>';
	}
})

//Code to handle after change of batch update field in regular & sales price
.on('sm_batch_update_field_post_on_change', function(e, rowId, selectedField, type, colVal, actionOptions) {

	if( selectedField == 'postmeta/meta_key=_regular_price/meta_value=_regular_price' || selectedField == 'postmeta/meta_key=_sale_price/meta_value=_sale_price' ) {

		let option = ( selectedField == 'postmeta/meta_key=_regular_price/meta_value=_regular_price' ) ? 'set_to_sale_price' : 'set_to_regular_price';
		actionOptions.batch_update_action_options_number += '<option value="'+option+'">'+window.smart_manager.convert_to_pretty_text(option)+'</option>';

        jQuery("#"+rowId+" .batch_update_action").empty().append(actionOptions.batch_update_action_options_number);
    }
})

//Code to handle after specific attribute has been selected in case of add or remove attribute
.on('change','.batch_update_action',function(){
	let rowId = jQuery(this).closest('tr').attr('id'),
		selectedField = jQuery( "#"+rowId+" .batch_update_field option:selected" ).val(),
        selectedAction = jQuery( "#"+rowId+" .batch_update_action option:selected" ).val(),
        type = window.smart_manager.columnNamesBatchUpdate[selectedField].type,
        colVal = window.smart_manager.columnNamesBatchUpdate[selectedField].values;

    if( selectedAction == 'set_to_sale_price' || selectedAction == 'set_to_regular_price' ) {
   		jQuery("#"+rowId+" #batch_update_value_td").hide();
    } else {
    	jQuery("#"+rowId+" #batch_update_value_td").show();
    }


    if( jQuery("#"+rowId+" #batch_update_value_td_2").length ) { //handling for removing custom attribute td
		jQuery("#"+rowId+" #batch_update_value_td_2").remove();
	}

	if( jQuery('#batchmod_sm_editor_grid #batch_update_value_td_2').length == 0 ) { //handling for restoring the batch update dialog size
		jQuery("#batchmod_sm_editor_grid").css('width','640px');
	}

    if( selectedField == 'custom/product_attributes' ) {

    	if( selectedAction != 'custom' ) { //code for handling action for non-custom attribute

    		let batchUpdateValueOptions = '',
    			batchUpdateValueSelectOptions = '',
	    		valueOptionsEmpty = true;

	    	if( typeof (colVal[selectedAction]) != 'undefined' && typeof (colVal[selectedAction].val) != 'undefined' ) {

	    		colVal = colVal[selectedAction].val;

	    		for (var key in colVal) {
		            if( typeof (colVal[key]) != 'object' && typeof (colVal[key]) != 'Array' ) {
		                valueOptionsEmpty = false;
		                batchUpdateValueSelectOptions += '<option value="'+key+'">'+ colVal[key] + '</option>';
		            }
		        }
	    	}

	        if( valueOptionsEmpty === false ) {
	        	batchUpdateValueOptions = '<select class="batch_update_value" style="min-width:130px !important;">'+
	        									'<option value="all">'+_x('All', 'radio - bulk edit', 'smart-manager-for-wp-e-commerce')+'</option>'+
	        									batchUpdateValueSelectOptions +
	        									'</select>';
	            jQuery("#"+rowId+" #batch_update_value_td").empty().append(batchUpdateValueOptions)
	            jQuery("#"+rowId+" #batch_update_value_td").find('.batch_update_value').select2({ width: '15em', dropdownCssClass: 'sm_beta_batch_update_field', dropdownParent: jQuery('[aria-describedby="sm_inline_dialog"]') });
	        }

    	} else { //code for handling action for custom attribute

    		jQuery("#"+rowId+" #batch_update_value_td").html("<input type='text' class='batch_update_value' placeholder='"+_x('Enter Attribute name...', 'placeholder', 'smart-manager-for-wp-e-commerce')+"' class='FormElement ui-widget-content'>");
    		jQuery("<td id='batch_update_value_td_2' style='white-space: pre;'><input type='text' class='batch_update_value_2' placeholder='"+_x('Enter values...', 'placeholder', 'smart-manager-for-wp-e-commerce')+"' title='"+_x('For more than one values, use pipe (|) as delimiter', 'tooltip', 'smart-manager-for-wp-e-commerce')+"' class='FormElement ui-widget-content'></td>").insertAfter("#"+rowId+" #batch_update_value_td");
    		jQuery("#batchmod_sm_editor_grid").css('width','760px');
    	}

    }

})
