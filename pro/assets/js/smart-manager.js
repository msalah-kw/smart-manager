(function(window) {
    function SmartManagerPro() {
        SmartManager.call(this); // Call parent constructor
    }
    SmartManagerPro.prototype = Object.create(SmartManagerPro.prototype);
    SmartManager.prototype.constructor = SmartManagerPro;
    //Function to determine if background process is running or not
    SmartManager.prototype.isBackgroundProcessRunning = function () {
        if (jQuery('#sa_background_process_progress').length > 0 && jQuery('#sa_background_process_progress').is(":visible")) {
            return true;
        }

        return false;
    }

    SmartManager.prototype.showProgressDialog = function (title = '') {
        SaCommonManager.prototype.showProgressDialog.call(this, title);
    }
    //function to clear the datepicker filter
    SmartManager.prototype.clearDateFilter = function () {
        let startDate = jQuery('#sm_date_selector_start_date').val(),
            endDate = jQuery('#sm_date_selector_end_date').val(),
            refresh = 0;

        if (startDate != '') {
            let startDateDatepicker = jQuery('.sm_date_range_container input.start-date').data('Zebra_DatePicker');
            jQuery('#sm_date_selector_start_date').val('');
            refresh = 1;
        }
        if (endDate != '') {
            let endDateDatepicker = jQuery('.sm_date_range_container input.end-date').data('Zebra_DatePicker');
            jQuery('#sm_date_selector_end_date').val('');
            refresh = 1;
        }

        if (typeof (window.smart_manager.currentGetDataParams.date_filter_params) != 'undefined' && typeof (window.smart_manager.currentGetDataParams.date_filter_query) != 'undefined') {
            delete window.smart_manager.currentGetDataParams.date_filter_params;
            delete window.smart_manager.currentGetDataParams.date_filter_query;
        }

        if (window.smart_manager.date_params.hasOwnProperty('date_filter_params')) {
            delete window.smart_manager.date_params['date_filter_params'];
        }

        if (window.smart_manager.date_params.hasOwnProperty('date_filter_query')) {
            delete window.smart_manager.date_params['date_filter_query'];
        }

        if (refresh == 1) {
            window.smart_manager.refresh();
        }
    }

    //function to process the datepicker filter
    SmartManager.prototype.sm_handle_date_filter = function (params) {

        let date_search_array = new Array(),
            dataParams = {};

        if (window.smart_manager.dashboardKey == 'user') {
            date_search_array = new Array({ "key": "User Registered", "value": params.start_date_default_format, "type": "date", "operator": ">=", "table_name": window.smart_manager.wpDbPrefix + "users", "col_name": "user_registered", "date_filter": 1 },
                { "key": "User Registered", "value": params.end_date_default_format, "type": "date", "operator": "<=", "table_name": window.smart_manager.wpDbPrefix + "users", "col_name": "user_registered", "date_filter": 1 });
        } else {
            date_search_array = new Array({ "key": "Post Date", "value": params.start_date_default_format, "type": "date", "operator": ">=", "table_name": window.smart_manager.wpDbPrefix + "posts", "col_name": "post_date", "date_filter": 1 },
                { "key": "Post Date", "value": params.end_date_default_format, "type": "date", "operator": "<=", "table_name": window.smart_manager.wpDbPrefix + "posts", "col_name": "post_date", "date_filter": 1 });
        }

        window.smart_manager.date_params['date_filter_params'] = JSON.stringify(params);
        window.smart_manager.date_params['date_filter_query'] = JSON.stringify(date_search_array);


        if (Object.getOwnPropertyNames(window.smart_manager.date_params).length > 0) {
            dataParams.data = window.smart_manager.date_params;
        }

        window.smart_manager.refresh(dataParams);
    }

    //function to append 0's to str
    SmartManager.prototype.strPad = function (str, len) {

        str += '';
        while (str.length < len) str = '0' + str;
        return str;

    },

    SmartManager.prototype.proSelectDate = function (dateValue) {

            if (dateValue == 'all') {
                if (typeof (window.smart_manager.clearDateFilter) !== "undefined" && typeof (window.smart_manager.clearDateFilter) === "function") {
                    window.smart_manager.clearDateFilter();
                }
                return;
            }

            let fromDate,
                toDate,
                from_time,
                to_time,
                from_date_formatted,
                from_date_default_format,
                to_date_formatted,
                to_date_default_format,
                now = new Date(),
                params = {
                    'start_date_formatted': '',
                    'start_date_default_format': '',
                    'end_date_formatted': '',
                    'end_date_default_format': ''
                };

            switch (dateValue) {

                case 'today':
                    fromDate = now;
                    toDate = now;
                    break;

                case 'yesterday':
                    fromDate = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1);
                    toDate = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1);
                    break;

                case 'this_week':
                    fromDate = new Date(now.getFullYear(), now.getMonth(), now.getDate() - (now.getDay() - 1));
                    toDate = now;
                    break;

                case 'last_week':
                    fromDate = new Date(now.getFullYear(), now.getMonth(), (now.getDate() - (now.getDay() - 1) - 7));
                    toDate = new Date(now.getFullYear(), now.getMonth(), (now.getDate() - (now.getDay() - 1) - 1));
                    break;

                case 'last_4_week':
                    fromDate = new Date(now.getFullYear(), now.getMonth(), (now.getDate() - 29)); //for exactly 30 days limit
                    toDate = now;
                    break;

                case 'this_month':
                    fromDate = new Date(now.getFullYear(), now.getMonth(), 1);
                    toDate = now;
                    break;

                case 'last_month':
                    fromDate = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                    toDate = new Date(now.getFullYear(), now.getMonth(), 0);
                    break;

                case '3_months':
                    fromDate = new Date(now.getFullYear(), now.getMonth() - 2, 1);
                    toDate = now;
                    break;

                case '6_months':
                    fromDate = new Date(now.getFullYear(), now.getMonth() - 5, 1);
                    toDate = now;
                    break;

                case 'this_year':
                    fromDate = new Date(now.getFullYear(), 0, 1);
                    toDate = now;
                    break;

                case 'last_year':
                    fromDate = new Date(now.getFullYear() - 1, 0, 1);
                    toDate = new Date(now.getFullYear(), 0, 0);
                    break;

                default:
                    fromDate = new Date(now.getFullYear(), now.getMonth(), 1);
                    toDate = now;
                    break;
            }

            //Code for format
            if (typeof fromDate === 'object' && fromDate instanceof Date) {
                var y = fromDate.getFullYear() + '',
                    m = fromDate.getMonth(),
                    d = window.smart_manager.strPad(fromDate.getDate(), 2);

                from_time = '00:00:00';
                params.start_date_formatted = d + ' ' + window.smart_manager.month_names_short[m] + ' ' + y + ' ' + from_time;
                params.start_date_default_format = y + '-' + window.smart_manager.strPad((m + 1), 2) + '-' + d + ' ' + from_time;
            }

            if (typeof toDate === 'object' && toDate instanceof Date) {
                var y = toDate.getFullYear() + '',
                    m = toDate.getMonth(),
                    d = window.smart_manager.strPad(toDate.getDate(), 2);

                to_time = '23:59:59';
                params.end_date_formatted = d + ' ' + window.smart_manager.month_names_short[m] + ' ' + y + ' ' + to_time;
                params.end_date_default_format = y + '-' + window.smart_manager.strPad((m + 1), 2) + '-' + d + ' ' + to_time;
            }

            var start_date = jQuery('.sm_date_range_container input.start-date').data('Zebra_DatePicker'),
                end_date = jQuery('.sm_date_range_container input.end-date').data('Zebra_DatePicker');

            if (typeof (start_date) != 'undefined') {
                start_date.set_date(params.start_date_formatted);
                start_date.update({ 'current_date': new Date(params.start_date_default_format), 'start_date': new Date(params.start_date_formatted) });
            }

            if (typeof (end_date) != 'undefined') {
                end_date.set_date(params.end_date_formatted);
                end_date.update({ 'current_date': new Date(params.end_date_default_format), 'start_date': new Date(params.end_date_formatted) });
            }

            window.smart_manager.sm_handle_date_filter(params);

        };

    var sm_beta_hide_dialog = function (IDs, gID) {
        jQuery.jgrid.hideModal("#" + IDs.themodal, { gb: "#gbox_" + gID, jqm: true, onClose: null });
        index = 0;
    }

    // ========================================================================
    // PRINT INVOICE
    // ========================================================================

    SmartManager.prototype.printInvoice = function () {

        if (window.smart_manager.duplicateStore === false && window.smart_manager.selectedRows.length == 0 && !window.smart_manager.selectAll) {
            return;
        }

        let params = {};
        params.data = {
            cmd: 'get_print_invoice',
            active_module: window.smart_manager.dashboardKey,
            security: window.smart_manager.saCommonNonce,
            pro: true,
            sort_params: (window.smart_manager.currentDashboardModel.hasOwnProperty('sort_params')) ? window.smart_manager.currentDashboardModel.sort_params : '',
            table_model: (window.smart_manager.currentDashboardModel.hasOwnProperty('tables')) ? window.smart_manager.currentDashboardModel.tables : '',
            SM_IS_WOO30: window.smart_manager.sm_is_woo30,
            SM_IS_WOO22: window.smart_manager.sm_id_woo22,
            SM_IS_WOO21: window.smart_manager.sm_is_woo21,
            search_text: (window.smart_manager.searchType == 'simple') ? window.smart_manager.simpleSearchText : '',
            advanced_search_query: JSON.stringify((window.smart_manager.searchType != 'simple') ? window.smart_manager.advancedSearchQuery : []),
            storewide_option: (true === window.smart_manager.selectAll) ? 'entire_store' : '',
            selected_ids: (window.smart_manager.getSelectedKeyIds()) ? JSON.stringify(window.smart_manager.getSelectedKeyIds()) : ''
        };

        let url = window.smart_manager.commonManagerAjaxUrl + '&cmd=' + params.data['cmd'] + '&active_module=' + params.data['active_module'] + '&security=' + params.data['security'] + '&pro=' + params.data['pro'] + '&SM_IS_WOO30=' + params.data['SM_IS_WOO30'] + '&SM_IS_WOO30=' + params.data['SM_IS_WOO30'] + '&sort_params=' + encodeURIComponent(JSON.stringify(params.data['sort_params'])) + '&table_model=' + encodeURIComponent(JSON.stringify(params.data['table_model'])) + '&advanced_search_query=' + params.data['advanced_search_query'] + '&search_text=' + params.data['search_text'] + '&storewide_option=' + params.data['storewide_option'] + '&selected_ids=' + params.data['selected_ids'];

        url += (window.smart_manager.isFilteredData()) ? '&filteredResults=1' : '';
        params.call_url = url;
        params.data_type = 'html';

        url += (window.smart_manager.date_params.hasOwnProperty('date_filter_params')) ? '&date_filter_params=' + window.smart_manager.date_params['date_filter_params'] : '';
        url += (window.smart_manager.date_params.hasOwnProperty('date_filter_query')) ? '&date_filter_query=' + window.smart_manager.date_params['date_filter_query'] : '';

        window.smart_manager.sendRequest(params, function (response) {
            let win = window.open('', 'Invoice');
            win.document.write(response);
            win.document.close();
            win.print();
        });
    }

    // ========================================================================
    // DUPLICATE RECORDS
    // ========================================================================

    SmartManager.prototype.duplicateRecords = function () {
        if (!window.smart_manager.duplicateStore && window.smart_manager.selectedRows.length == 0 && !window.smart_manager.selectAll) {
            return;
        }
        window.smart_manager.selectAll = (window.smart_manager.duplicateStore || window.smart_manager.selectAll) ? true : false;
        setTimeout(function () {

            window.smart_manager.showProgressDialog(_x('Duplicate Records', 'progressbar modal title', 'smart-manager-for-wp-e-commerce'));

            if (typeof (sa_background_process_heartbeat) !== "undefined" && typeof (sa_background_process_heartbeat) === "function") {
                sa_background_process_heartbeat(1000, 'duplicate', 'smart_manager');
            }

        }, 1);

        let params = {};
        params.data = {
            cmd: 'duplicate_records',
            active_module: window.smart_manager.dashboardKey,
            security: window.smart_manager.saCommonNonce,
            pro: true,
            storewide_option: (window.smart_manager.selectAll) ? 'entire_store' : '',
            selected_ids: JSON.stringify(window.smart_manager.getSelectedKeyIds()),
            table_model: (window.smart_manager.currentDashboardModel.hasOwnProperty('tables')) ? window.smart_manager.currentDashboardModel.tables : '',
            active_module_title: window.smart_manager.dashboardName,
            backgroundProcessRunningMessage: window.smart_manager.backgroundProcessRunningMessage,
            SM_IS_WOO30: window.smart_manager.sm_is_woo30,
            SM_IS_WOO22: window.smart_manager.sm_id_woo22,
            SM_IS_WOO21: window.smart_manager.sm_is_woo21
        };

        params.showLoader = false;

        if (window.smart_manager.isFilteredData()) {
            params.data.filteredResults = 1;
        }

        window.smart_manager.sendRequest(params, function (response) {

        });

        // setTimeout(function() {
        //     params = { 'func_nm' : 'duplicate_records', 'title' : 'Duplicate Records' }
        //     window.smart_manager.background_process_hearbeat( params );
        // }, 1000);

    };

    // ========================================================================
    // Function to handle request for both creating & updating view
    // ========================================================================
    SmartManager.prototype.saveView = function (params = {}) {
        if ((!params.hasOwnProperty('name') || params.name.trim() === '') || (!params.hasOwnProperty('action') || params.action.trim() === '')) {
            return;
        }
        try {
            let type = params.type || 'custom_views',
                action = params.action,
                viewSlug = window.smart_manager.getViewSlug(window.smart_manager.dashboardName),
                activeDashboard = (viewSlug) ? viewSlug : window.smart_manager.dashboardKey,
                currentDashboardState = '';
            if (type === 'custom_views' && typeof window.smart_manager.getCurrentDashboardState === "function") {
                currentDashboardState = window.smart_manager.getCurrentDashboardState();
                if (currentDashboardState) {
                    currentDashboardState = JSON.parse(currentDashboardState);
                    currentDashboardState['search_params'] = {
                        'isAdvanceSearch': (window.smart_manager.advancedSearchQuery.length > 0) ? 'true' : 'false',
                        'params': window.smart_manager.advancedSearchQuery.length > 0 ? window.smart_manager.advancedSearchQuery : window.smart_manager.simpleSearchText
                    };
                }
            }

            let requestParams = {
                data_type: 'json',
                data: {
                    module: 'custom_views',
                    cmd: action,
                    active_module: activeDashboard,
                    security: window.smart_manager.saCommonNonce,
                    name: params.name
                }
            };

            if (type === 'custom_views') {
                requestParams.data.isPublic = jQuery('#sm_view_access_public').is(":checked");
                requestParams.data.isSaveDashboardAndCols = jQuery('#sm_save_dashboard_and_cols').is(":checked");
                requestParams.data.isSaveAdvancedSearch = jQuery('#sm_save_advanced_search').is(":checked");
                requestParams.data.is_view = viewSlug ? 1 : 0;
                requestParams.data.currentView = JSON.stringify(currentDashboardState);
            } else if (type === 'saved_bulk_edits' && params.hasOwnProperty('bulk_edit_params')) {
                requestParams.data.bulk_edit_params = JSON.stringify(params.bulk_edit_params);
            }

            window.smart_manager.sendRequest(requestParams, function (response) {
                let ack = response.ACK || '';
                if (ack === 'Success') {
                    if (params.hasOwnProperty('onSuccess') && typeof params.onSuccess === 'function') {
                        params.onSuccess(response);
                    }
                    if (type === 'custom_views') {
                        window.smart_manager.notification = {
                            status: 'success', message: sprintf(
                                /* translators: %s: success notification message */
                                _x(`${((requestParams.data.isSaveDashboardAndCols && requestParams.data.isSaveAdvancedSearch) || (requestParams.data.isSaveDashboardAndCols && !requestParams.data.isSaveAdvancedSearch)) ? 'View' : 'Saved Search'} %sd successfully!`, 'notification', 'smart-manager-for-wp-e-commerce'), String(action).capitalize())
                        }
                        if (response?.slug && response.slug !== '') {
                            setTimeout(() => {
                                window.location.href = `${window.smart_manager.smAppAdminURL || window.location.href}${window.location.href.includes("?") ? "&" : "?"}dashboard=${response.slug}&is_view=1`;
                                window.smart_manager.showNotification();
                            }, 500);
                        }
                    }
                } else if (ack === 'Failed' && response.msg) {
                    if (params.hasOwnProperty('onError') && typeof params.onError === 'function') {
                        params.onError(response);
                    }
                    if (type === 'custom_views') {
                        jQuery('#sm_view_error_msg').text(response.msg).show();
                        jQuery('#sm_view_name').addClass('sm_border_red');
                    }
                }
            });
        } catch (e) {
            SaErrorHandler.log('In saveView:: ', e);
        }
    };

    // ========================================================================
    // function to display confirmdialog for create & update view
    // ========================================================================

    SmartManager.prototype.createUpdateViewDialog = function (action = 'create', params = {}) {
        let viewSlug = window.smart_manager.getViewSlug(window.smart_manager.dashboardName);

        let isView = (viewSlug) ? 1 : 0,
            isPublicView = (window.smart_manager.publicViews.includes(viewSlug)) ? 1 : 0;
        isSaveDashboardAndCols = ((!params.hasOwnProperty('dashboardChecked')) || (!params.dashboardChecked === true)) ? 0 : 1;
        isSaveAdvancedSearch = ((!params.hasOwnProperty('advancedSearchChecked')) || (!params.advancedSearchChecked === true)) ? 0 : 1;

        params.btnParams = {}
        params.title = _x('Custom Views', 'modal title', 'smart-manager-for-wp-e-commerce');
        params.width = 500;
        params.height = 350;
        params.content = `<p id="sm_view_descrip">${_x('Create a custom view to save selected columns from a dashboard. Use it for saved searches, giving specific columns access to other users, etc.', 'modal content', 'smart-manager-for-wp-e-commerce')}</p>
        <input id="sm_view_name" type="text" placeholder="${_x("Give a name to this view", "placeholder", "smart-manager-for-wp-e-commerce")}" value="${(isView == 1 && action != 'create') ? window.smart_manager.dashboardName : ''}" />
        <div id="sm_view_error_msg" style="display:none;"></div>
        <div id="sm_view_access">
            <label id="sm_view_access_public_lbl">
                <input type="checkbox" id="sm_view_access_public" style="height: 1.5em;width: 1.5em;" ${(isPublicView === 1) ? 'checked' : ''} >
                ${_x('Public', 'checkbox for custom view access', 'smart-manager-for-wp-e-commerce')}
            </label>
            <p class="description">${_x('Marking this view public will make it available to all users with access to Smart Manager.', 'description', 'smart-manager-for-wp-e-commerce')}</p>
        </div>
        <div class="sm_view_save_options">
            <div class="sm_view_save_option">
                <label for="sm_save_dashboard_and_cols">
                    <input type="checkbox" id="sm_save_dashboard_and_cols" style="height: 1.5em;width: 1.5em;" ${(isSaveDashboardAndCols === 1) ? 'checked' : ''} >
                    <span>
                        ${_x('Save dashboard along with save columns', 'checkbox to save dashboard along with save columns', 'smart-manager-for-wp-e-commerce')}
                    </span>
                </label>
            </div>
            <div class="sm_view_save_option">
                <label for="sm_save_advanced_search">
                    <input type="checkbox" id="sm_save_advanced_search" style="height: 1.5em;width: 1.5em;" ${(isSaveAdvancedSearch === 1) ? 'checked' : ''} >
                    <span>
                        ${_x('Save Advanced Search conditions', 'checkbox to save advanced search conditions', 'smart-manager-for-wp-e-commerce')}
                    </span>
                </label>
            </div>
            <div id="sm_view_save_options_error_msg"></div>
        </div>`;

        if (typeof (window.smart_manager.createUpdateView) !== "undefined" && typeof (window.smart_manager.createUpdateView) === "function") {
            params.btnParams.yesText = String(action).capitalize();
            params.btnParams.yesCallback = window.smart_manager.createUpdateView;
            params.btnParams.yesCallbackParams = action;
            params.btnParams.hideOnYes = false
        }

        window.smart_manager.showConfirmDialog(params);
    }

    // ========================================================================
    // function to handle functionality for checking iof view exists & if not then creating or updating the same
    // ========================================================================

    SmartManager.prototype.createUpdateView = function (action = 'create') {
        let name = jQuery('#sm_view_name').val()
        // Code to validate name field
        if (!name) {
            jQuery('#sm_view_error_msg').html('Please add view name').show();
            jQuery('#sm_view_name').addClass('sm_border_red')
        } else if ((!jQuery('#sm_save_dashboard_and_cols').is(":checked")) && (!jQuery('#sm_save_advanced_search').is(":checked"))) {
            jQuery('#sm_view_save_options_error_msg').html(_x('You must select at least one option before saving', 'notifiacation', 'smart-manager-for-wp-e-commerce')).show();
            jQuery('.sm_view_save_option').addClass('sm_border_red');
        } else {
            jQuery('#sm_view_name').removeClass('sm_border_red');
            jQuery('#sm_view_error_msg').html('').hide();
            jQuery('.sm_view_save_option').removeClass('sm_border_red');
            jQuery('#sm_view_save_options_error_msg').html('').hide();
            if (typeof (window.smart_manager.saveView) !== "undefined" && typeof (window.smart_manager.saveView) === "function") {
                window.smart_manager.saveView({ action, name, type: 'custom_views' });
            }
        }
    };

    // ========================================================================
    // DELETE VIEW
    // ========================================================================
    SmartManager.prototype.deleteView = function (params = {}, bulkEditDialogObj = {}) {
        let viewSlug = params.view_slug || window.smart_manager.getViewSlug(window.smart_manager.dashboardName),
            ajaxParams = {
                data_type: 'json',
                data: {
                    module: 'custom_views',
                    cmd: 'delete',
                    security: window.smart_manager.saCommonNonce || '',
                    active_module: viewSlug
                }
            };

        window.smart_manager.sendRequest(ajaxParams, function (response) {
            if (response?.ACK !== 'Success') return;

            window.smart_manager.notification = {
                status: 'success',
                message: params?.success_msg
            };
            let type = params?.type || 'custom_views';
            switch (type) {
                case 'custom_views':
                    window.smart_manager.showNotification()
                    location.reload();
                    break;

                case 'saved_bulk_edits':
                    bulkEditDialogObj.BulkEditSavedActions = window.smart_manager.saved_bulk_edits = Array.isArray(window.smart_manager.saved_bulk_edits) ? window.smart_manager.saved_bulk_edits.filter(item => item?.slug !== viewSlug) : [];

                    bulkEditDialogObj.selectedSavedBulkEdit = window.smart_manager.selectedSavedBulkEdit = document.querySelector('.saved-bulk-edit-item.selected')?.getAttribute('slug') || '';
                    // Reset conditions if the deleted item was selected
                    if (params.event?.target?.closest(".saved-bulk-edit-item")?.classList?.contains("selected")) {
                        window.smart_manager.savedBulkEditConditions = [];
                        if ("undefined" !== typeof (bulkEditDialogObj.initialize) && "function" === typeof (bulkEditDialogObj.initialize)) {
                            bulkEditDialogObj.initialize();
                        }
                    }
                    //Re-render the bulk edit pannel dialog.
                    window.smart_manager.showPannelDialog('bulkEdit', m.route.get())
                    break;
            }
        });
    };

    SmartManager.prototype.resetBatchUpdate = function () {

    }

    SmartManager.prototype.displayDefaultBatchUpdateValueHandler = function (row_id) {

        if (row_id == '') {
            return;
        }

        let selected_field = jQuery("#" + row_id + " .batch_update_field option:selected").val(),
            type = window.smart_manager.columnNamesBatchUpdate[selected_field].type,
            editor = window.smart_manager.columnNamesBatchUpdate[selected_field].editor,
            multiSelectSeparator = window.smart_manager.columnNamesBatchUpdate[selected_field].multiSelectSeparator,
            col_val = window.smart_manager.columnNamesBatchUpdate[selected_field].values,
            allowMultiSelect = window.smart_manager.columnNamesBatchUpdate[selected_field].allowMultiSelect,
            skip_default_action = false;

        if (type == 'checkbox') {

            let checkedVal = '',
                uncheckedVal = '',
                checkedDisplayVal = '',
                uncheckedDisplayVal = '';

            if (type == 'checkbox') {
                checkedVal = window.smart_manager.columnNamesBatchUpdate[selected_field].checkedTemplate;
                uncheckedVal = window.smart_manager.columnNamesBatchUpdate[selected_field].uncheckedTemplate;

                checkedDisplayVal = checkedVal.substr(0, 1).toUpperCase() + checkedVal.substr(1, checkedVal.length);
                uncheckedDisplayVal = uncheckedVal.substr(0, 1).toUpperCase() + uncheckedVal.substr(1, uncheckedVal.length);
            }

            jQuery("#" + row_id + " #batch_update_value_td").empty().append('<select class="batch_update_value" style="min-width:130px !important;">' +
                '<option value="' + checkedVal + '"> ' + checkedDisplayVal + ' </option>' +
                '<option value="' + uncheckedVal + '"> ' + uncheckedDisplayVal + ' </option>' +
                '</select>')
            jQuery("#" + row_id + " #batch_update_value_td").find(".batch_update_value").select2({ width: '15em', dropdownCssClass: 'sm_beta_batch_update_field', dropdownParent: jQuery('[aria-describedby="sm_inline_dialog"]') });

        } else if (col_val != '' && type == 'dropdown') {

            var batch_update_value_options = '<select class="batch_update_value" style="min-width:130px !important;">',
                value_options_empty = true;

            for (var key in col_val) {
                if (typeof (col_val[key]) != 'object' && typeof (col_val[key]) != 'Array') {
                    value_options_empty = false;
                    batch_update_value_options += '<option value="' + key + '">' + col_val[key] + '</option>';
                }
            }

            batch_update_value_options += '</select>';

            if (value_options_empty === false) {
                jQuery("#" + row_id + " #batch_update_value_td").empty().append(batch_update_value_options);

                let args = { width: '15em', dropdownCssClass: 'sm_beta_batch_update_field', dropdownParent: jQuery('[aria-describedby="sm_inline_dialog"]') };

                if (editor == 'select2' && allowMultiSelect) {
                    args['multiple'] = true;
                }

                jQuery("#" + row_id + " #batch_update_value_td").find('.batch_update_value').select2(args);
            }

        } else if (col_val != '' && type == 'sm.multilist') {

            let options = {},
                index = 0;

            Object.entries(col_val).forEach(([key, value]) => {
                index = key;

                if (value.hasOwnProperty('parent')) {
                    if (value.parent > 0) {
                        index = value.parent + '_childs';
                        value.term = ' – ' + value.term;
                    }
                }

                if (options.hasOwnProperty(index)) {
                    options[index] += '<option value="' + key + '"> ' + value.term + ' </option>';
                } else {
                    options[index] = '<option value="' + key + '"> ' + value.term + ' </option>';
                }


            });

            let batch_update_value_options = '<select class="batch_update_value" style="min-width:130px !important;">' + Object.values(options).join() + '</select>';

            jQuery("#" + row_id + " #batch_update_value_td").empty().append(batch_update_value_options)
            jQuery("#" + row_id + " #batch_update_value_td").find('.batch_update_value').select2({ width: '15em', dropdownCssClass: 'sm_beta_batch_update_field', dropdownParent: jQuery('[aria-describedby="sm_inline_dialog"]') });

        } else if (type == 'sm.longstring') {
            jQuery("#" + row_id + " #batch_update_value_td").empty().append('<textarea class="batch_update_value" placeholder="' + _x('Enter a value...', 'placeholder', 'smart-manager-for-wp-e-commerce') + '" class="FormElement ui-widget-content"></textarea>');
        } else if (type == 'sm.image') {
            jQuery("#" + row_id + " #batch_update_value_td").empty().append('<div class="batch_update_image" style="width:15em;"><span style="color:#0073aa;cursor:pointer;font-size: 2.25em;line-height: 1;" class="dashicons dashicons-camera"></span></div>');
        } else if (type == 'numeric') {
            jQuery("#" + row_id + " #batch_update_value_td").empty().append('<input type="number" class="batch_update_value" placeholder="' + _x('Enter a value...', 'placeholder', 'smart-manager-for-wp-e-commerce') + '" class="FormElement ui-widget-content" />');
        }
    }


    SmartManager.prototype.createBatchUpdateDialog = function () {

        if (window.smart_manager.selectedRows.length <= 0 && window.smart_manager.selectAll === false) {
            return;
        }

        let allItemsOptionText = (window.smart_manager.simpleSearchText != '' || window.smart_manager.advancedSearchQuery.length > 0) ? _x('All Items In Search Results', 'bulk edit option', 'smart-manager-for-wp-e-commerce') : _x('All Items In Store', 'bulk edit option', 'smart-manager-for-wp-e-commerce');

        let entire_store_batch_update_html = "<tr>" +
            ((window.smart_manager.selectAll === false) ? "<td style='white-space: pre;'><input type='radio' name='batch_update_storewide' value='selected_ids' checked/>" + _x('Selected Items', 'bulk edit option', 'smart-manager-for-wp-e-commerce') + "</td>" : '') +
            "<td style='white-space: pre;'><input type='radio' name='batch_update_storewide' value='entire_store' " + ((window.smart_manager.selectAll === true) ? 'checked' : '') + " />" + allItemsOptionText + "</td>" +
            "</tr>",
            batch_update_field_options = '<option value="" disabled selected>' + _x('Select Field', 'bulk edit field', 'smart-manager-for-wp-e-commerce') + '</option>',
            batch_update_action_options_string = '',
            batch_update_action_options_number = '',
            batch_update_action_options_datetime = '',
            batch_update_action_options_multilist = '',
            batch_update_actions_row = '',
            batch_update_dlg_content = '',
            dlgParams = {};

        if (Object.getOwnPropertyNames(window.smart_manager.columnNamesBatchUpdate).length > 0) {
            for (let key in window.smart_manager.columnNamesBatchUpdate) {
                batch_update_field_options += '<option value="' + key + '" data-type="' + window.smart_manager.columnNamesBatchUpdate[key].type + '" data-editor="' + window.smart_manager.columnNamesBatchUpdate[key].editor + '" data-multiSelectSeparator="' + window.smart_manager.columnNamesBatchUpdate[key].multiSelectSeparator + '">' + window.smart_manager.columnNamesBatchUpdate[key].name + '</option>';
            };
        }

        //Formating options for default actions
        window.smart_manager.batch_update_action_options_default = '<option value="" disabled selected>' + _x('Select Action', 'bulk edit default action', 'smart-manager-for-wp-e-commerce') + '</option>';
        window.smart_manager.batch_update_action_options_default += '<option value="set_to">' + _x('set to', 'bulk edit action', 'smart-manager-for-wp-e-commerce') + '</option>';
        window.smart_manager.batch_update_action_options_default += window.smart_manager.batchUpdateCopyFromOption;

        //Formating options for number actions
        batch_update_action_options_string = window.smart_manager.batchUpdateSelectActionOption;
        let selected = '';
        for (let key in window.smart_manager.batch_update_action_string) {
            selected = '';
            if (key == 'set_to') {
                selected = 'selected';
            }

            batch_update_action_options_string += '<option value="' + key + '" ' + selected + '>' + window.smart_manager.batch_update_action_string[key] + '</option>';
        }
        batch_update_action_options_string += window.smart_manager.batchUpdateCopyFromOption;

        //Formating options for datetime actions
        batch_update_action_options_datetime = window.smart_manager.batchUpdateSelectActionOption;
        for (let key in window.smart_manager.batch_update_action_datetime) {
            selected = '';
            if (key == 'set_datetime_to') {
                selected = 'selected';
            }
            batch_update_action_options_datetime += '<option value="' + key + '" ' + selected + '>' + window.smart_manager.batch_update_action_datetime[key] + '</option>';
        }
        batch_update_action_options_datetime += window.smart_manager.batchUpdateCopyFromOption;

        //Formating options for multilist actions
        batch_update_action_options_multilist = window.smart_manager.batchUpdateSelectActionOption;
        for (let key in window.smart_manager.batch_update_action_multilist) {
            selected = '';
            if (key == 'set_to') {
                selected = 'selected';
            }
            batch_update_action_options_multilist += '<option value="' + key + '" ' + selected + '>' + window.smart_manager.batch_update_action_multilist[key] + '</option>';
        }
        batch_update_action_options_multilist += window.smart_manager.batchUpdateCopyFromOption;

        //Formating options for string actions
        batch_update_action_options_number = window.smart_manager.batchUpdateSelectActionOption;
        for (let key in window.smart_manager.batch_update_action_number) {
            selected = '';
            if (key == 'set_to') {
                selected = 'selected';
            }
            batch_update_action_options_number += '<option value="' + key + '" ' + selected + '>' + window.smart_manager.batch_update_action_number[key] + '</option>';
        }
        batch_update_action_options_number += window.smart_manager.batchUpdateCopyFromOption;


        batch_update_actions_row = "<td style='white-space: pre;'><select required class='batch_update_field' style='min-width:130px;width:auto !important;'>" + batch_update_field_options + "</select></td>" +
            "<td style='white-space: pre;'><select required class='batch_update_action' style='min-width:130px !important;'>" + window.smart_manager.batch_update_action_options_default + "</select></td>" +
            "<td id='batch_update_value_td' style='white-space: pre;'><input type='text' class='batch_update_value' placeholder='" + _x('Enter a value...', 'placeholder', 'smart-manager-for-wp-e-commerce') + "' class='FormElement ui-widget-content'></td>" +
            "<td id='batch_update_add_delete_row' style='float:right;'><div class='dashicons dashicons-plus' style='color:#0073aa;cursor:pointer;line-height:1.7em;'></div><div class='dashicons dashicons-trash' style='color:#FF5B5E;cursor:pointer;line-height:1.5em;'></div></td>";



        batch_update_dlg_content = "<div id='batchUpdateform' class='formdata' style='width: 100%; overflow: auto; position: relative; height: auto;'>" +
            "<table class='batch_update_table' width='100%'>" +
            "<tbody>" +
            entire_store_batch_update_html +
            "<tr id='batch_update_action_row_0'>" +
            batch_update_actions_row +
            "</tr>" +
            "<tr>" +
            "<td>&#160;</td>" +
            "</tr>" +
            "</tbody>" +
            "</table>" +
            "</div>";

        dlgParams.btnParams = {};
        dlgParams.btnParams.yesText = _x('Update', 'button', 'smart-manager-for-wp-e-commerce');
        if (typeof (window.smart_manager.processBatchUpdate) !== "undefined" && typeof (window.smart_manager.processBatchUpdate) === "function") {
            dlgParams.btnParams.yesCallback = window.smart_manager.processBatchUpdate;
        }

        dlgParams.btnParams.noText = _x('Reset', 'button', 'smart-manager-for-wp-e-commerce');
        if (typeof (window.smart_manager.resetBatchUpdate) !== "undefined" && typeof (window.smart_manager.resetBatchUpdate) === "function") {
            dlgParams.btnParams.noCallback = window.smart_manager.resetBatchUpdate;
        }

        dlgParams.title = _x('Bulk Edit', 'modal title', 'smart-manager-for-wp-e-commerce');
        dlgParams.content = batch_update_dlg_content;
        dlgParams.height = 300;
        dlgParams.width = 850;

        window.smart_manager.showConfirmDialog(dlgParams);

        jQuery('.batch_update_field, .batch_update_action').each(function () {
            jQuery(this).select2({ width: '15em', dropdownCssClass: 'sm_beta_batch_update_field', dropdownParent: jQuery('[aria-describedby="sm_inline_dialog"]') });
        })

        jQuery(".batch_update_action_row_0").find('#batch_update_add_delete_row .dashicons-trash').hide(); //for hiding the delete icon for the first row

        //function for handling add row in batch update dialog
        jQuery(document).off('click', '#batch_update_add_delete_row .dashicons-plus').on('click', '#batch_update_add_delete_row .dashicons-plus', function () {
            let count = jQuery('tr[id^=batch_update_action_row_]').length,
                current_id = 'batch_update_action_row_' + count;
            jQuery('.batch_update_table tr:last').before("<tr id=" + current_id + ">" + batch_update_actions_row + "</tr>");

            jQuery("#" + current_id).find('.batch_update_field, .batch_update_action').select2({ width: '15em', dropdownCssClass: 'sm_beta_batch_update_field', dropdownParent: jQuery('[aria-describedby="sm_inline_dialog"]') });

            jQuery(this).hide();

        });

        //function for handling delete row in batch update dialog
        jQuery(document).off('click', '#batch_update_add_delete_row .dashicons-trash').on('click', '#batch_update_add_delete_row .dashicons-trash', function () {

            let add_row_visible = jQuery(this).closest('td').find('.dashicons-plus').is(":visible");
            jQuery(this).closest('tr').remove();

            if (add_row_visible === true) { //condition for removing plus icon only if visible
                jQuery('tr[id^=batch_update_action_row_]:last()').find('.dashicons-plus').show();
            }

        });

        // For the time now
        Date.prototype.timeNow = function () {
            return ((this.getHours() < 10) ? "0" : "") + this.getHours() + ":" + ((this.getMinutes() < 10) ? "0" : "") + this.getMinutes() + ":" + ((this.getSeconds() < 10) ? "0" : "") + this.getSeconds();
        }

        jQuery(document).on('change', '.batch_update_field', function () {

            let row_id = jQuery(this).closest('tr').attr('id');

            let selected_field = jQuery("#" + row_id + " .batch_update_field option:selected").val(),
                type = window.smart_manager.columnNamesBatchUpdate[selected_field].type,
                editor = window.smart_manager.columnNamesBatchUpdate[selected_field].editor,
                col_val = window.smart_manager.columnNamesBatchUpdate[selected_field].values,
                skip_default_action = false;

            // Formating options for default actions
            window.smart_manager.batch_update_action_options_default = window.smart_manager.batchUpdateSelectActionOption +
                '<option value="set_to">' + _x('set to', 'bulk edit action', 'smart-manager-for-wp-e-commerce') + '</option>' +
                window.smart_manager.batchUpdateCopyFromOption;

            jQuery(document).trigger("sm_batch_update_field_on_change", [row_id, selected_field, type, col_val]);

            if (type == 'numeric') {
                jQuery("#" + row_id + " .batch_update_action").empty().append(batch_update_action_options_number);
            } else if (type == 'text' || type == 'sm.longstring') {
                jQuery("#" + row_id + " .batch_update_action").empty().append(batch_update_action_options_string);
            } else if (type == 'sm.datetime') {
                jQuery("#" + row_id + " .batch_update_action").empty().append(batch_update_action_options_datetime);
            } else if (type == 'sm.multilist' || (type == 'dropdown' && editor == 'select2')) {
                jQuery("#" + row_id + " .batch_update_action").empty().append(batch_update_action_options_multilist);
            } else {
                jQuery("#" + row_id + " .batch_update_action").empty().append(window.smart_manager.batch_update_action_options_default);
                jQuery("#" + row_id + " .batch_update_action").find('[value="set_to"]').attr('selected', 'selected');
            }

            let actionOptions = {
                'batch_update_action_options_number': batch_update_action_options_number,
                'batch_update_action_options_string': batch_update_action_options_string,
                'batch_update_action_options_datetime': batch_update_action_options_datetime,
                'batch_update_action_options_multilist': batch_update_action_options_multilist
            };

            jQuery(document).trigger("sm_batch_update_field_post_on_change", [row_id, selected_field, type, col_val, actionOptions]);

            jQuery("#" + row_id + " .batch_update_value").val('');

            jQuery("#" + row_id + " #batch_update_value_td").empty().append('<input type="text" class="batch_update_value" placeholder="' + _x("Enter a value...", "placeholder", "smart-manager-for-wp-e-commerce") + '" class="FormElement ui-widget-content" >');

            if (skip_default_action === true) {
                return;
            }

            if (type == 'sm.date' || type == 'sm.time' || type == 'sm.datetime') {
                let placeholder = 'YYYY-MM-DD' + ((type == 'sm.datetime') ? ' HH:MM:SS' : '');
                placeholder = (type == 'sm.time') ? 'H:i' : placeholder;

                jQuery("#" + row_id + " .batch_update_value").attr('placeholder', placeholder);

                let format = 'Y-m-d' + ((type == 'sm.datetime') ? ' H:i:s' : '');
                format = (type == 'sm.time') ? 'H:i' : format;

                jQuery("#" + row_id + " .batch_update_value").Zebra_DatePicker({
                    format: format,
                    show_icon: false,
                    show_select_today: false,
                    default_position: 'below',
                });
            } else {
                jQuery("#" + row_id + " .batch_update_value").attr('placeholder', _x('Enter a value...', 'placeholder', 'smart-manager-for-wp-e-commerce'));
                let datepicker = jQuery("#" + row_id + " .batch_update_value").data('Zebra_DatePicker');
            }

            if (typeof (window.smart_manager.displayDefaultBatchUpdateValueHandler) !== "undefined" && typeof (window.smart_manager.displayDefaultBatchUpdateValueHandler) === "function") {
                dlgParams.btnParams.noCallback = window.smart_manager.displayDefaultBatchUpdateValueHandler(row_id);
            }
        });

        //Handling action change event only for 'datetime' type fields
        jQuery(document).on('change', '.batch_update_action', function () {

            let row_id = jQuery(this).closest('tr').attr('id');

            let selected_field = jQuery("#" + row_id + " .batch_update_field option:selected").val(),
                selected_action = jQuery("#" + row_id + " .batch_update_action option:selected").val(),
                type = window.smart_manager.columnNamesBatchUpdate[selected_field].type;

            if (jQuery("#" + row_id + " #batch_update_value_td").find(".sm_batch_update_copy_from_ids").length > 0 || jQuery("#" + row_id + " #batch_update_value_td").find(".sm_batch_update_search_value").length > 0) {
                jQuery("#" + row_id + " #batch_update_value_td").empty().append('<input type="text" class="batch_update_value" placeholder="' + _x('Enter a value...', 'placeholder', 'smart-manager-for-wp-e-commerce') + '" class="FormElement ui-widget-content" >');
            }

            if (type == 'sm.datetime') {
                let placeholder = (selected_action == 'set_datetime_to') ? 'YYYY-MM-DD HH:MM:SS' : ((selected_action == 'set_date_to') ? 'YYYY-MM-DD' : 'HH:MM:SS');

                jQuery("#" + row_id + " .batch_update_value").attr('placeholder', placeholder);

                let format = (selected_action == 'set_datetime_to') ? 'Y-m-d H:i:s' : ((selected_action == 'set_date_to') ? 'Y-m-d' : 'H:i:s');
                jQuery("#" + row_id + " .batch_update_value").Zebra_DatePicker({
                    format: format,
                    show_icon: false,
                    show_select_today: false,
                    default_position: 'below',
                });
            }

            //Code for handling 'copy from' functionality
            if (selected_action == 'copy_from') {

                let select_str = '<select class="batch_update_value sm_batch_update_copy_from_ids" style="min-width:130px !important;">' +
                    '<option></option></select>';

                let select2Params = {
                    width: '15em',
                    dropdownCssClass: 'sm_beta_batch_update_field',
                    placeholder: sprintf(
                        /* translators: %s: dashboard display name */
                        _x('Select %s', 'placeholder', 'smart-manager-for-wp-e-commerce'), window.smart_manager.dashboardDisplayName),
                    dropdownParent: jQuery('[aria-describedby="sm_inline_dialog"]'),
                    ajax: {
                        url: window.smart_manager.commonManagerAjaxUrl,
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return {
                                search_term: params.term,
                                cmd: 'get_batch_update_copy_from_record_ids',
                                active_module: window.smart_manager.dashboardKey,
                                security: window.smart_manager.saCommonNonce,
                                is_taxonomy: window.smart_manager.isTaxonomyDashboard()
                            };
                        },
                        processResults: function (data) {
                            var terms = [];
                            if (data) {
                                jQuery.each(data, function (id, title) {
                                    terms.push({
                                        id: id,
                                        text: title
                                    });
                                });
                            }
                            return {
                                results: terms
                            };
                        },
                        cache: true
                    }
                }

                jQuery("#" + row_id + " #batch_update_value_td").empty().append(select_str);
                jQuery("#" + row_id + " #batch_update_value_td").find(".batch_update_value").select2(select2Params);
            } else if (selected_action == 'search_and_replace') {
                jQuery("#" + row_id + " #batch_update_value_td").empty().append('<input type="text" class="batch_update_value sm_batch_update_search_value" placeholder="' + _x('Search for...', 'placeholder', 'smart-manager-for-wp-e-commerce') + '" class="FormElement ui-widget-content" >');
                jQuery("#" + row_id + " #batch_update_value_td").append('<input type="text" style="margin-left: 1em;" class="batch_update_value sm_batch_update_replace_value" placeholder="' + _x('Replace with...', 'placeholder', 'smart-manager-for-wp-e-commerce') + '" class="FormElement ui-widget-content" >');
            } else {
                if (typeof (window.smart_manager.displayDefaultBatchUpdateValueHandler) !== "undefined" && typeof (window.smart_manager.displayDefaultBatchUpdateValueHandler) === "function") {
                    dlgParams.btnParams.noCallback = window.smart_manager.displayDefaultBatchUpdateValueHandler(row_id);
                }
            }
        });

        jQuery(document).off('click', ".batch_update_image").on('click', ".batch_update_image", function (event) {

            let row_id = jQuery(this).closest('tr').attr('id');

            let file_frame;

            // If the media frame already exists, reopen it.
            if (file_frame) {
                file_frame.open();
                return;
            }

            // Create the media frame.
            file_frame = wp.media.frames.file_frame = wp.media({
                title: jQuery(this).data('uploader_title'),
                button: {
                    text: jQuery(this).data('uploader_button_text')
                },
                multiple: false  // Set to true to allow multiple files to be selected
            });

            file_frame.on('open', function () {
                jQuery('[aria-describedby="sm_inline_dialog"]').hide();
            });

            file_frame.on('close', function () {
                jQuery('[aria-describedby="sm_inline_dialog"]').show();
            });

            // When an image is selected, run a callback.
            file_frame.on('select', function () {
                // We set multiple to false so only get one image from the uploader
                attachment = file_frame.state().get('selection').first().toJSON();

                jQuery('#' + row_id + ' .batch_update_image').attr('data-imageId', attachment['id']);
                jQuery('#' + row_id + ' .batch_update_image').html('<img style="cursor:pointer;" src="' + attachment['url'] + '" width="32" height="32">');
            });

            file_frame.open();
        });
    };

    // ========================================================================

    SmartManager.prototype.smToggleFullScreen = function (elem) {
        // ## The below if statement seems to work better ## if ((document.fullScreenElement && document.fullScreenElement !== null) || (document.msfullscreenElement && document.msfullscreenElement !== null) || (!document.mozFullScreen && !document.webkitIsFullScreen)) {
        if ((document.fullScreenElement !== undefined && document.fullScreenElement === null) || (document.msFullscreenElement !== undefined && document.msFullscreenElement === null) || (document.mozFullScreen !== undefined && !document.mozFullScreen) || (document.webkitIsFullScreen !== undefined && !document.webkitIsFullScreen)) {
            if (elem.requestFullScreen) {
                elem.requestFullScreen();
            } else if (elem.mozRequestFullScreen) {
                elem.mozRequestFullScreen();
            } else if (elem.webkitRequestFullScreen) {
                elem.webkitRequestFullScreen(Element.ALLOW_KEYBOARD_INPUT);
            } else if (elem.msRequestFullscreen) {
                elem.msRequestFullscreen();
            }
        } else {
            if (document.cancelFullScreen) {
                document.cancelFullScreen();
            } else if (document.mozCancelFullScreen) {
                document.mozCancelFullScreen();
            } else if (document.webkitCancelFullScreen) {
                document.webkitCancelFullScreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
        }
    }

    SmartManager.prototype.smScreenHandler = function () {
        if (window.smart_manager.wpToolsPanelWidth === 0) {
            window.smart_manager.wpToolsPanelWidth = jQuery('#adminmenuwrap').width();
            jQuery('#adminmenuback').hide();
            jQuery('#adminmenuwrap').hide();
            jQuery('#wpadminbar').hide();
            jQuery('#wpcontent').css('margin-left', '0px');
            window.smart_manager.grid_width = window.smart_manager.grid_width + window.smart_manager.wpToolsPanelWidth;
            window.smart_manager.grid_height = document.documentElement.offsetHeight - 260;
        } else {
            jQuery('#adminmenuback').show();
            jQuery('#adminmenuwrap').show();
            jQuery('#wpadminbar').show();
            jQuery('#wpcontent').removeAttr("style");
            window.smart_manager.grid_width = window.smart_manager.grid_width - window.smart_manager.wpToolsPanelWidth;
            window.smart_manager.grid_height = document.documentElement.offsetHeight - 360;
            window.smart_manager.wpToolsPanelWidth = 0;
        }

        window.smart_manager.hot.updateSettings({ 'width': window.smart_manager.grid_width, 'height': window.smart_manager.grid_height });
        window.smart_manager.hot.render();

        jQuery('#sm_top_bar, #sm_bottom_bar').css('width', window.smart_manager.grid_width + 'px');
    }

    if (document.addEventListener) {
        document.addEventListener('webkitfullscreenchange', window.smart_manager.smScreenHandler, false);
        document.addEventListener('mozfullscreenchange', window.smart_manager.smScreenHandler, false);
        document.addEventListener('fullscreenchange', window.smart_manager.smScreenHandler, false);
        document.addEventListener('MSFullscreenChange', window.smart_manager.smScreenHandler, false);
    }

    // Function to handle deletion of records
    SmartManager.prototype.deleteAllRecords = function (actionArgs) {
        if ("undefined" !== typeof (window.smart_manager.deleteAndUndoRecords) && "function" === typeof (window.smart_manager.deleteAndUndoRecords)) {
            window.smart_manager.deleteAndUndoRecords({ cmd: 'delete_all', args: ('undefined' !== typeof ('actionArgs')) ? actionArgs : {} });
        }
    }
    // Function to handle undo tasks
    SmartManager.prototype.undoTasks = function () {
        if ("undefined" !== typeof (window.smart_manager.deleteAndUndoRecords) && "function" === typeof (window.smart_manager.deleteAndUndoRecords)) {
            window.smart_manager.deleteAndUndoRecords({ cmd: 'undo' });
        }
    }
    // Function to handle tasks deletion
    SmartManager.prototype.deleteTasks = function () {
        if ("undefined" !== typeof (window.smart_manager.deleteAndUndoRecords) && "function" === typeof (window.smart_manager.deleteAndUndoRecords)) {
            window.smart_manager.deleteAndUndoRecords({ cmd: 'delete' });
        }
    }

    //Render Undo Tasks Action Buttons.
    SmartManager.prototype.renderUndoTasksActionButtons = function () {
        if (0 === jQuery(".sm_top_bar_action_btns:nth-last-child(5)").find('#undo_sm_editor_grid').length) {
            jQuery("#sm_top_bar_left .sm_top_bar_action_btns:nth-last-child(5)").append('<div id="undo_sm_editor_grid" class="sm_beta_dropdown">' +
                '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">' +
                '<path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />' +
                '</svg>' +
                '<span title="' + _x('Undo', 'tooltip', 'smart-manager-for-wp-e-commerce') + '">' + _x('Undo', 'button', 'smart-manager-for-wp-e-commerce') + '</span>' +
                '<div class="sm_beta_dropdown_content">' +
                '<a id="sm_beta_undo_selected" href="#">' + _x('Selected Tasks', 'undo button', 'smart-manager-for-wp-e-commerce') + '</a>' +
                '<a id="sm_beta_undo_all_tasks" class="sm_entire_store" href="#">' + _x('All Tasks', 'undo button', 'smart-manager-for-wp-e-commerce') + '</a>' +
                '</div>' +
                '</div>' +
                '<div id="delete_tasks_sm_editor_grid" class="sm_beta_dropdown">' +
                '<svg class="sm-error-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' +
                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>' +
                '</svg>' +
                '<span title="' + _x('Delete', 'tooltip', 'smart-manager-for-wp-e-commerce') + '">' + _x('Delete', 'button', 'smart-manager-for-wp-e-commerce') + '</span>' +
                '<div class="sm_beta_dropdown_content">' +
                '<a id="sm_beta_delete_selected_tasks" href="#">' + _x('Selected Tasks', 'delete tasks button', 'smart-manager-for-wp-e-commerce') + '</a>' +
                '<a id="sm_beta_delete_all_tasks" class="sm_entire_store" href="#">' + _x('All Tasks', 'delete tasks button', 'smart-manager-for-wp-e-commerce') + '</a>' +
                '</div>' +
                '</div>');
        }
    }
    // Function to handle displaying tasks
    SmartManager.prototype.showTasks = function () {
        let props = [window.smart_manager.displayTasks, window.smart_manager.resetSearch, window.smart_manager.setDashboardDisplayName, window.smart_manager.loadDashboard, window.smart_manager.isTasksEnabled, window.smart_manager.reset];
        if (!props && !(props.every(prop => ("undefined" === typeof (prop) && "function" !== typeof (prop))))) {
            return;
        }
        window.smart_manager.renderUndoTasksActionButtons();
        window.smart_manager.displayTasks({ showHideTasks: window.smart_manager.isTasksEnabled() });
        if(!window.location.search.includes('show_edit_history')){
            window.smart_manager.reset(true);
        }
        window.smart_manager.resetSearch();
        if (window.smart_manager.isTasksEnabled() === 1) {
            if (window.smart_manager.savedSearchConds && window.smart_manager.savedSearchConds['task'] && ((typeof (window.smart_manager.handleSavedSearchConditions) !== "undefined") && (typeof (window.smart_manager.handleSavedSearchConditions) === "function"))) {
                window.smart_manager.handleSavedSearchConditions(window.smart_manager.savedSearchConds['task']);
            }
        } else if (window.smart_manager.savedSearchConds && window.smart_manager.savedSearchConds['postType'] && ((typeof (window.smart_manager.handleSavedSearchConditions) !== "undefined") && (typeof (window.smart_manager.handleSavedSearchConditions) === "function"))) {
            window.smart_manager.handleSavedSearchConditions(window.smart_manager.savedSearchConds['postType']);
        }
        window.smart_manager.setDashboardDisplayName();
        if(!window.location.search.includes('show_edit_history')){
            window.smart_manager.loadDashboard();
        }
    }
    // Display title modal
    SmartManager.prototype.showTitleModal = function (params = {}) {
        if (!window.smart_manager.processName || !window.smart_manager.processContent) {
            return;
        }

        let title = sprintf(
            /* translators: %s: Task process content */
            _x('Edited %s', 'process title', 'smart-manager-for-wp-e-commerce'), window.smart_manager.processContent)
        let modalTitle = _x('Task Title', 'modal title', 'smart-manager-for-wp-e-commerce');
        let taskContent = '<input type="text" id="sm_add_title" placeholder="' + _x('Enter desired title here...', 'title placeholder', 'smart-manager-for-wp-e-commerce') + '" value="' + title + '">';
        let taskDesc = sprintf(
            /* translators: %s: Undo modal description */
            _x('Name the task for easier reference and future actions, especially for %s option. A pre-filled title has been suggested based on your changes.', 'modal description', 'smart-manager-for-wp-e-commerce'), '<strong>' + _x('Undo', 'modal description', 'smart-manager-for-wp-e-commerce') + '</strong>');

        if (0 === window.smart_manager.showTasksTitleModal) {
            window.smart_manager.updatedTitle = title
            if ("function" === typeof (window.smart_manager.processCallback)) {
                ("undefined" !== typeof (window.smart_manager.processCallbackParams) && Object.keys(window.smart_manager.processCallbackParams).length > 0) ? window.smart_manager.processCallback(window.smart_manager.processCallbackParams) : window.smart_manager.processCallback()
            }
            return;
        } else if (window.smart_manager.scheduledActionContent) {
            modalTitle = _x('Schedule Bulk Edit', 'modal title', 'smart-manager-for-wp-e-commerce');
            content = '<div id="show_modal_content"><div class="flex items-center mb-3"><label for="title" class="task_title">' + _x('Title', 'modal title', 'smart-manager-for-wp-e-commerce') + '</label>' + taskContent + '</div><div style="padding-bottom: 1em; color: #6b7280!important;">' + taskDesc + '</div><br/>' + window.smart_manager.scheduledActionContent + '</div>';
        } else {
            content = '<div style="padding-bottom: 1em; color: #6b7280!important;">' + taskDesc + '</div><div id="show_modal_content">' + taskContent + '</div>';
        }
        window.smart_manager.modal = {
            title: modalTitle,
            content: content,
            autoHide: false,
            cta: {
                title: _x('Ok', 'button', 'smart-manager-for-wp-e-commerce'),
                closeModalOnClick: params.hasOwnProperty('btnParams') ? ((params.btnParams.hasOwnProperty('hideOnYes')) ? params.btnParams.hideOnYes : true) : true,
                callback: function () {
                    if (window.smart_manager.isScheduled) {
                        if ("undefined" !== typeof (window.smart_manager.scheduledForVal) && "function" === typeof (window.smart_manager.scheduledForVal)) {
                            window.smart_manager.scheduledForVal();
                        }
                        if (!(window.smart_manager.scheduledFor)) {
                            window.smart_manager.notification = { message: _x('Please select your desired date & time for scheduling an action.', 'notification', 'smart-manager-for-wp-e-commerce') }
                            window.smart_manager.showNotification()
                            return;
                        }
                        if ("undefined" !== typeof (window.smart_manager.hideModal) && "function" === typeof (window.smart_manager.hideModal)) {
                            window.smart_manager.hideModal();
                        }
                    }
                    let updatedTitle = jQuery('#sm_add_title').val();
                    if (updatedTitle) {
                        window.smart_manager.updatedTitle = updatedTitle;
                        if ("function" === typeof (window.smart_manager.processCallback)) {
                            ("undefined" !== typeof (window.smart_manager.processCallbackParams) && Object.keys(window.smart_manager.processCallbackParams).length > 0) ? window.smart_manager.processCallback(window.smart_manager.processCallbackParams) : window.smart_manager.processCallback()
                        }
                    }
                }
            },
            closeCTA: { title: _x('Cancel', 'button', 'smart-manager-for-wp-e-commerce') },
            onCreate: function () {
                if ("undefined" !== typeof (window.smart_manager.scheduleDatePicker) && "function" === typeof (window.smart_manager.scheduleDatePicker) && window.smart_manager.scheduledActionContent){
                window.smart_manager.scheduleDatePicker('#scheduled_for');
                }
            }
        }
        window.smart_manager.showModal()
    }
    // Function to handle records deletion, tasks undo and deletion of tasks records
    SmartManager.prototype.deleteAndUndoRecords = function (params = {}) {
        if (!params || (0 === Object.keys(params).length) || (false === params.hasOwnProperty('cmd')) || !params['cmd'] || ((0 === window.smart_manager.selectedRows.length && !window.smart_manager.selectAll) && (!window.smart_manager.selectedAllTasks))) {
            return;
        }
        window.smart_manager.selectAll = (window.smart_manager.selectedAllTasks || window.smart_manager.selectAll) ? true : false;
        params.data = {
            cmd: params['cmd'],
            active_module: window.smart_manager.dashboardKey,
            security: window.smart_manager.saCommonNonce,
            selected_ids: (window.smart_manager.taskId) ? JSON.stringify([window.smart_manager.taskId.toString()]) : JSON.stringify(window.smart_manager.getSelectedKeyIds().sort(function (a, b) { return b - a })),
            storewide_option: (window.smart_manager.selectAll) ? 'entire_store' : '',
            active_module_title: window.smart_manager.dashboardName,
            backgroundProcessRunningMessage: window.smart_manager.backgroundProcessRunningMessage,
            pro: true,
            SM_IS_WOO30: (window.smart_manager.sm_is_woo30) ? window.smart_manager.sm_is_woo30 : '',
            SM_IS_WOO22: (window.smart_manager.sm_id_woo22) ? window.smart_manager.sm_id_woo22 : '',
            SM_IS_WOO21: (window.smart_manager.sm_is_woo21) ? window.smart_manager.sm_is_woo21 : ''
        };
        let processName = '', tasksParams = ['undo', 'delete'];
        let currentProcessName = '';
        if (tasksParams.includes(params['cmd'])) {
            params.data.isTasks = 1;
        }
        switch (params['cmd']) {
            case 'delete_all':
                processName = _x('Delete Records', 'progressbar modal title', 'smart-manager-for-wp-e-commerce');
                params.data.deletePermanently = (params.hasOwnProperty('args') && (params.args.hasOwnProperty('deletePermanently'))) ? params.args.deletePermanently : 0;
                currentProcessName = (params.data.deletePermanently) ? 'delete_permanently' : 'move_to_trash';
                break;
            case 'undo':
                processName = _x('Undo Tasks', 'progressbar modal title', 'smart-manager-for-wp-e-commerce');
                currentProcessName = 'undo';
                break;
            case 'delete':
                processName = _x('Delete Tasks', 'progressbar modal title', 'smart-manager-for-wp-e-commerce');
                currentProcessName = 'delete';
                break;
        }
        setTimeout(function () {
            window.smart_manager.showProgressDialog(processName);
            if ("undefined" !== typeof (sa_background_process_heartbeat) && "function" === typeof (sa_background_process_heartbeat)) {
                sa_background_process_heartbeat(1000, currentProcessName, 'smart_manager');
            }
        }, 1);
        params.showLoader = false;
        if (window.smart_manager.isFilteredData()) {
            params.data.filteredResults = 1;
        }
        window.smart_manager.sendRequest(params, function (response) {
            window.smart_manager.taskId = 0;
        });
    }
    // Function for displaying warning modal before doing undo/delete tasks records
    SmartManager.prototype.taskActionsModal = function (args = {}) {
        if (!args || ('object' !== typeof (args))) {
            return;
        }
        window.smart_manager.selectedAllTasks = (['sm_beta_undo_all_tasks', 'sm_beta_delete_all_tasks'].includes(args.id)) ? true : false;
        if (0 === window.smart_manager.selectedRows.length && !window.smart_manager.selectAll && !window.smart_manager.selectedAllTasks) {
            window.smart_manager.notification = { message: _x('Please select a task', 'notification', 'smart-manager-for-wp-e-commerce') }
            window.smart_manager.showNotification()
            return false;
        }

        let undoTaskIds = (['sm_beta_undo_selected', 'sm_beta_undo_all_tasks'].includes(args.id)) ? 1 : 0,
            deleteTasks = (['sm_beta_delete_selected_tasks', 'sm_beta_delete_all_tasks'].includes(args.id)) ? 1 : 0,
            params = {},
            paramsContent = '',
            isBackgroundProcessRunning = window.smart_manager.backgroundProcessRunningNotification(false);
        params.btnParams = {}
        params.title = '<span class="sm-error-icon"><span class="dashicons dashicons-warning" style="vertical-align: text-bottom;"></span>&nbsp;' + _x('Attention!', 'modal title', 'smart-manager-for-wp-e-commerce') + '</span>';
        let taskInlineEditMessage = args.hasOwnProperty('taskInlineEditMessage') ? args.taskInlineEditMessage : '';
        switch (true) {
            case (('' !== taskInlineEditMessage) || (undoTaskIds && "undefined" !== typeof (window.smart_manager.undoTasks) && "function" === typeof (window.smart_manager.undoTasks))):
                paramsContent = 'undo';
                paramsContent = (taskInlineEditMessage) ? (paramsContent + ' ' + args.taskInlineEditMessage) : paramsContent;
                params.btnParams.yesCallback = window.smart_manager.undoTasks;
                break;
            case (deleteTasks && "undefined" !== typeof (window.smart_manager.deleteTasks) && "function" === typeof (window.smart_manager.deleteTasks)):
                paramsContent = '<span class="sm-error-icon">' + _x('delete', 'modal content', 'smart-manager-for-wp-e-commerce') + '</span>';
                params.btnParams.yesCallback = window.smart_manager.deleteTasks;
                break;
        }
        params.content = _x('Are you sure you want to ' + paramsContent + ' ', 'modal content', 'smart-manager-for-wp-e-commerce');
        params.content = (taskInlineEditMessage) ? params.content : (params.content + '<strong>' + args.btnText.toLowerCase() + '</strong>?');
        if (!isBackgroundProcessRunning) {
            if (window.smart_manager.selectedRows.length > 0 || window.smart_manager.loadedTotalRecords) {
                params.btnParams.hideOnYes = false;
                window.smart_manager.showConfirmDialog(params);
            } else {
                window.smart_manager.notification = { message: _x('No task to ' + paramsContent, 'warning', 'smart-manager-for-wp-e-commerce') }
                window.smart_manager.showNotification()
            }
        }
    }

    // Function for handling display of editor for column titles
    SmartManager.prototype.displayColumnTitleEditor = function (e) {
        let parent = e.target.closest('li');
        if (!parent) return;
        let input = parent.querySelector("input[type='text']");
        if (!input) return;
        let cssClass = 'sm-column-title-input-edit';
        input.readOnly = !input.readOnly;
        if (input.readOnly) {
            input.classList.remove(cssClass)
        } else {
            input.classList.add(cssClass);
            input.focus();
            //Code for setting the cursor at end of input
            let val = input.value;
            input.value = '';
            input.value = val;
        }
    }

    // Function for scheduling bulk edit
    SmartManager.prototype.showScheduleModal = function (params = {}) {
        SaCommonManagerPro.prototype.showScheduleModal.call(this, params);
    }
    // Function for getting scheduled for value
    SmartManager.prototype.scheduledForVal = function () {
        SaCommonManagerPro.prototype.scheduledForVal.call(this);
    }

    SmartManager.prototype.scheduleDatePicker = function(selector) {
        SaCommonManagerPro.prototype.scheduleDatePicker.call(this, selector);
    }
    // ========================================================================
    // PRIVILEGE SETTINGS UPDATE
    // ========================================================================
    SmartManager.prototype.privilegeSettingsUpdate = function () {
        if ((window.smart_manager.privilegeSettingsRules.length <= 0)) {
            return;
        }
        window.smart_manager.sendRequest({
            data: {
                cmd: 'save_access_privilege_settings',
                active_module: 'access-privilege',
                security: window.smart_manager.saCommonNonce,
                access_privileges: JSON.stringify(window.smart_manager.privilegeSettingsRules)
            },
            data_type: 'json'
        }, function (response) {
            let ack = (response.hasOwnProperty('ACK')) ? response.ACK : ''
            if ('success' === ack) {
                window.smart_manager.notification = {
                    status: 'success', message:
                        _x('Privilege settings saved successfully!', 'access privilege settings notification', 'smart-manager-for-wp-e-commerce'), hideDelay: 2000
                }
                window.smart_manager.showNotification()
            }
        });
    }
    // Function for unchecking the 'Show Tasks' when click on it during unsaved changes.
    SmartManager.prototype.handleShowTasks = function () {
        jQuery("#sm_show_tasks").prop('checked', false);
    }
    // Function for handling saved search conditions.
    SmartManager.prototype.handleSavedSearchConditions = function (type = {}) {
        if (!type) {
            return;
        }
        if (type.simpleSearchCond) {
            window.smart_manager.simpleSearchText = type.simpleSearchCond;
            jQuery('#sm_simple_search_box').val(window.smart_manager.simpleSearchText);
        } else if (type.advancedSearchCond) {
            window.smart_manager.advancedSearchQuery = type.advancedSearchCond;
            if (("undefined" !== typeof (window.smart_manager.updateAdvancedSearchRuleCount)) && ("function" === typeof (window.smart_manager.updateAdvancedSearchRuleCount))) {
                window.smart_manager.updateAdvancedSearchRuleCount();
            }
            jQuery('#search_switch').prop('checked', true).trigger('change');
        }
    }

    // Function to check if the saved search params contains post type rules.
    SmartManager.prototype.checkPostParamsInSavedSearch = function (savedSearchParams = {}) {
        if ((!savedSearchParams) || (!savedSearchParams.hasOwnProperty("params")) || (!savedSearchParams.params.hasOwnProperty("search_params")) || (!savedSearchParams.params.search_params.hasOwnProperty("params"))) {
            return false;
        }
        return savedSearchParams.params.search_params.params.some((param) => {
            if ((param.hasOwnProperty("rules")) && (Array.isArray(param.rules))) {
                return param.rules.some((rule) => {
                    if ((rule.hasOwnProperty("rules")) && (Array.isArray(rule.rules))) {
                        return rule.rules.some((nestedRule) => {
                            return ((nestedRule.hasOwnProperty("type")) && (nestedRule.type.includes("_posts.")));
                        });
                    }
                    return false;
                });
            }
            return false;
        });
    }

    // Function to Get list of eligible dashboards.
    SmartManager.prototype.GetEligibleDashboardsForSavedSearch = function (SavedSearch = {}) {
        if ((!window.smart_manager) || (!window.smart_manager.sm_dashboards) || (!SavedSearch) || (!SavedSearch.hasOwnProperty("parent_post_type"))) {
            return false;
        }
        //map params object to load dashboard.
        let eligibleDashboards = Object.keys(window.smart_manager.sm_dashboards);
        if (!eligibleDashboards.length) {
            return false;
        }
        // Move parent post type to the top
        eligibleDashboards = eligibleDashboards.filter(dashboard => ((dashboard !== 'user') && (dashboard !== SavedSearch.parent_post_type)));
        eligibleDashboards.unshift(SavedSearch.parent_post_type);
        return eligibleDashboards.map(type => ({ id: type, text: window.smart_manager.sm_dashboards[type] }));
    }

    // Function to show eligible dashboards list.
    SmartManager.prototype.eligibleDashboardsDialog = function (eligibleDashboards = []) {
        if (!eligibleDashboards.length) {
            return;
        }
        window.smart_manager.modal = {
            title: _x('Select Dashboard', 'saved search dashboards list modal title', 'smart-manager-for-wp-e-commerce'),
            content: window.smart_manager.getEligibleDashboardsHtml(eligibleDashboards),
            showCloseIcon: true,
            cta: {},
            contentClass:"eligible_dashboards_section_content",
		onCreate: function(){
		    if (jQuery('#eligible_dashboards_select').length) {
		        jQuery('#eligible_dashboards_select').select2({
		            dropdownCssClass: 'sm-eligible-dashboards-select2-dropdown'
		        });
		    }
		},
        }
        window.smart_manager.showModal();
    };

    // Function to generate modal HTML with a select dropdown.
    SmartManager.prototype.getEligibleDashboardsHtml = function (eligibleDashboards = []) {
        return `
    <div id="sm_eligible_dashboards_section">
        <div style="font-size: 1.2em;">
            <div style="margin-bottom: 1.3em;">
                ${_x('Choose a dashboard where you want to apply this saved search.', 'dashboard display test', 'smart-manager-for-wp-e-commerce')}
            </div>
        </div>
        <select id="eligible_dashboards_select" style="width: 100%;">
        <option value="">Select Dashboard</option>
            ${eligibleDashboards.map(dashboard => `
                <option value="${dashboard.id}">${dashboard.text}</option>
            `).join('')}
        </select>
    </div>
`;
    };

    //Function to filter advanced search query to keep only "posts" type columns.
    SmartManager.prototype.getPostsColumnsFromQuery = function (query = []) {
        if (!Array.isArray(query) || query.length === 0) {
            return [];
        }
        return query.reduce((filteredQuery, group) => {
            const filteredRules = group.rules.reduce((filteredRules, rule) => {
                const validRules = (rule.rules || []).filter(r => r.type.includes("_posts."));
                if (validRules.length) {
                    filteredRules.push({ ...rule, rules: validRules });
                }
                return filteredRules;
            }, []);

            if (filteredRules.length) {
                filteredQuery.push({ ...group, rules: filteredRules });
            }
            return filteredQuery;
        }, []);
    }
    SmartManagerPro.prototype.getDataDefaultParams = function (params) {
        SmartManager.prototype.getDataDefaultParams.apply(this, [params]);
        if (typeof window.smart_manager.date_params.date_filter_params != 'undefined') {
            window.smart_manager.currentGetDataParams.data['date_filter_params'] = window.smart_manager.date_params.date_filter_params;
        }
        if (typeof window.smart_manager.date_params.date_filter_query != 'undefined') {
            window.smart_manager.currentGetDataParams.data['date_filter_query'] = window.smart_manager.date_params.date_filter_query;
        }
    }
    SmartManager.prototype.scheduleCSVExportModalHTML = function () {
        return `
        <div class="container">
            <form id="sm_schedule_export_form">
                <!-- Start Time -->
                <div class="flex items-center field-parent">
                    <label for="sm_schedule_export_start_time">${_x('Start Time', 'label', 'smart-manager-for-wp-e-commerce')}</label>
                    <input class="sa_bulk_edit_content" type="text" id="sm_schedule_export_start_time" name="schedule_export_start_time" placeholder="${_x('Select future date', 'placeholder', 'smart-manager-for-wp-e-commerce')}" required>
                </div>
                <!-- Recurring Interval -->
                <div class="flex items-center field-parent">
                    <label for="sm_schedule_export_interval">${_x('Export Interval', 'label', 'smart-manager-for-wp-e-commerce')}</label>
                    <div class="flex export-interval-section">
                        <input class="sa_bulk_edit_content" type="number" value="30" id="sm_schedule_export_interval" name="schedule_export_interval" min="1" placeholder="${_x('Interval', 'placeholder', 'smart-manager-for-wp-e-commerce')}" required >
                        <select class="sa_bulk_edit_content" id="sm_schedule_export_interval_unit" name="schedule_export_interval_unit" required>
                            <option value="days">${_x('Days', 'interval unit', 'smart-manager-for-wp-e-commerce')}</option>
                        </select>
                    </div>
                </div>
                <!-- Email -->
                <div class="flex items-center field-parent">
                    <label for="sm_schedule_export_email">${_x('Email', 'label', 'smart-manager-for-wp-e-commerce')}</label>
                    <input class="sa_bulk_edit_content" type="email" id="sm_schedule_export_email" value="${window.smart_manager?.sm_admin_email || ''}" name="schedule_export_email" placeholder="${_x('Enter email for CSV link', 'placeholder', 'smart-manager-for-wp-e-commerce')}" required>
                </div>
                <!-- Order Statuses -->
                <div class="flex items-center field-parent">
                    <label for="sm_schedule_export_order_statuses">
                        ${_x('Order Statuses', 'label', 'smart-manager-for-wp-e-commerce')}
                    </label>
                    <select class="form-control" id="sm_schedule_export_order_statuses" name="schedule_export_order_statuses" multiple="multiple"">
                        ${window.smart_manager.hasOwnProperty('orderStatuses') && Object.entries(window.smart_manager.orderStatuses)
                .map(function ([value, label]) {
                    return `<option value="${value}">${label}</option>`;
                }).join('')}
                    </select>
                </div>
                <div class="mt-4">
                    <div class="scheduled-export-modal-note">
                        <strong>${_x('Notes:', 'modal description', 'smart-manager-for-wp-e-commerce')}</strong>
                        <ul class="mt-2">
                            <li>
                                ${_x('Scheduled actions follow timezone of your site. Avoid overlaps to prevent delays.', 'modal description', 'smart-manager-for-wp-e-commerce')}
                            </li>
                            <li>
                                ${_x('If the CSV export link is unavailable, the file can be accessed directly from the', 'modal description', 'smart-manager-for-wp-e-commerce')}<code>woocommerce_uploads</code> directory.
                            </li>
                        </ul>
                    </div>
                </div>
                <!-- Link to Scheduled Actions -->
                <div class="mt-4">
                    ${_x(
                    `Check all scheduled export actions <a target='_blank' href=${window.smart_manager?.scheduledExportActionAdminUrl || ''}>here</a>.`,
                    'scheduled action list',
                    'smart-manager-for-e-commerce'
                )}
                </div>
            </form>
        </div>`;
    };

    //Initialize and configure the Select2 order statuses dropdown for the schedule export form.
    SmartManager.prototype.initOrderStatusesSelect2 = function () {
        jQuery('#sm_schedule_export_order_statuses').select2({
            tags: true,
            placeholder: _x('Leave blank to include all order statuses.', 'placeholder', 'smart-manager-for-wp-e-commerce'),
            width: '100%',
            containerCssClass: 'sm-schedule-export-order-status-select2-container',
            dropdownCssClass: 'sm-schedule-export-order-status-select2-dropdown',
        });
    }

    // Retrieves and validates the form data from the schedule export form.
    SmartManager.prototype.validateAndGetScheduleExportFormData = function () {
        const form = document.getElementById('sm_schedule_export_form');
        if (!form) {
            return;
        }
        // Remove error classes from all inputs.
        Array.from(form.querySelectorAll('input')).forEach(input => {
            input.classList.remove('border-red');
        });
        let message = '';
        // Remove previous error messages.
        const previousErrors = document.querySelectorAll('.sm-schedule-export-fields-error');
        previousErrors.forEach(el => el.remove());
        // Validate Email
        const emailEl = document.getElementById('sm_schedule_export_email');
        if (emailEl && !emailEl.checkValidity()) {
            emailEl.classList.add('border-red');
            message += `${_x('Please enter a valid email address.', 'validation message', 'smart-manager-for-wp-e-commerce')}<br>`;
        }
        // Validate Start Time.
        const startTimeEl = document.getElementById('sm_schedule_export_start_time');
        if (startTimeEl) {
            if (!startTimeEl.checkValidity()) {
                startTimeEl.classList.add('border-red');
                message += `${_x('Please select a start time.', 'validation message', 'smart-manager-for-wp-e-commerce')}<br>`;
            } else if (new Date(startTimeEl.value) < new Date(Date.now() + 2 * 60 * 60 * 1000)) {
                startTimeEl.classList.add('border-red');
                message += `${_x('Start time must be at least 2 hours from now.', 'validation message', 'smart-manager-for-wp-e-commerce')}<br>`;
            }
        }
        // Validate Interval.
        const intervalEl = document.getElementById('sm_schedule_export_interval');
        if (intervalEl && !intervalEl.checkValidity()) {
            intervalEl.classList.add('border-red');
            message += `${_x('Please select an export interval.', 'validation message', 'smart-manager-for-wp-e-commerce')}<br>`;
        }
        // Show error notification or return form data.
        if (message) {
            window.smart_manager.hidePannelDialog = true;
            window.smart_manager.notification = {
                status: 'error',
                message: message
            }
            return;
        }
        // Convert form data to an object.
        const formData = {};
        const formDataArray = new FormData(form);
        for (let [name, value] of formDataArray.entries()) {
            value = value.trim();
            if (value.length) {
                if (formData[name]) {
                    if (!Array.isArray(formData[name])) {
                        formData[name] = [formData[name]];
                    }
                    formData[name].push(value);
                } else {
                    formData[name] = (name === 'schedule_export_order_statuses') ? [value] : value;
                }
            }
        }
        return formData;
    };

    //schedule Exports Ajax Callback function.
    SmartManager.prototype.scheduleExportAjaxCallback = function (response = {}) {
        if (response && response.hasOwnProperty('ACK') && "success" === response.ACK && response.hasOwnProperty('data') && response.data.hasOwnProperty('msg')) {
            window.smart_manager.notification = { status: 'success', message: response.data.msg }
            window.smart_manager.showNotification()
        } else {
            window.smart_manager.notification = { status: 'error', message: _x('Error in scheduling export, please try again later', 'error message', 'smart-manager-for-wp-e-commerce') }
            window.smart_manager.showNotification()
        }
    }

    SmartManager.prototype.processBatchUpdate = function () {
        SaCommonBulkEdit.prototype.processBatchUpdate.call(this, false);
        if (!this.ajaxParams || (typeof this.ajaxParams !== 'object')) {
            return;
        }
        jQuery(document).trigger("sm_batch_update_on_submit"); //trigger to make changes in batch_update_actions
        //Ajax request to batch update the selected records
        Object.assign(this.ajaxParams.data, {
            SM_IS_WOO30: window.smart_manager.sm_is_woo30,
            SM_IS_WOO22: window.smart_manager.sm_id_woo22,
            SM_IS_WOO21: window.smart_manager.sm_is_woo21
        });
        // Code for passing tasks params
        this.ajaxParams.data = ("undefined" !== typeof (window.smart_manager.addTasksParams) && "function" === typeof (window.smart_manager.addTasksParams) && 1 == window.smart_manager.sm_beta_pro) ? window.smart_manager.addTasksParams(this.ajaxParams.data) : this.ajaxParams.data;
        if (window.smart_manager.isFilteredData()) {
            this.ajaxParams.data.filteredResults = 1;
        }
        if (true === window.smart_manager.isScheduled) {
            this.ajaxParams.data_type = 'text';
        }
        window.smart_manager.sendRequest(this.ajaxParams, function (response) {
            if (window.smart_manager.isScheduled) {
                window.smart_manager.refresh();
                window.smart_manager.notification = { message: response, hideDelay: 25000, status: 'success' }
                window.smart_manager.showNotification()
            }
        });
    }
    //Initializes NLP module for handling advanced search functionality
	SmartManager.prototype.addNLPForAdvancedSearch = function(){
		window.smart_manager.NLConverter.addModule(
			{
				moduleId:'advanced_search',
				//Before send request callback.
				beforeSend: () => {
					jQuery('.sa-loader-container').show();
				},
				// Success callback.
				onSuccess: (result, prompt) => {
					if(result && Array.isArray(result) && result[0] && result[0].hasOwnProperty('text') && window.smart_manager.isJSON(result[0].text)) {
						window.smart_manager.searchType = 'advanced';
						window.smart_manager.advancedSearchQuery = [JSON.parse(result[0].text)];
						// code to update the advanced seach rule count.
						window.smart_manager.advancedSearchRuleCount = 0;
						if (("undefined" !== typeof (window.smart_manager.updateAdvancedSearchRuleCount)) && ("function" === typeof (window.smart_manager.updateAdvancedSearchRuleCount))) {
							window.smart_manager.updateAdvancedSearchRuleCount();
						}
                        jQuery('#sm_advanced_search_content').html(sprintf(
                        /* translators: %1$d: Advanced search rule count %2$s: search conditions */
                        _x('%1$d condition%2$s', 'advanced search conditions', 'smart-manager-for-wp-e-commerce'), window.smart_manager.advancedSearchRuleCount, ((window.smart_manager.advancedSearchRuleCount > 1) ? _x('s', 'advanced search conditions', 'smart-manager-for-wp-e-commerce') : '')))
                        window.smart_manager.advancedSearchPrompt = prompt;
						window.smart_manager.hideModal();
                        if (!jQuery('#search_switch').is(':checked')) {
                            jQuery('#search_switch').prop('checked', true).trigger('change').attr('switchsearchtype', 'simple');
                            return;
                        }
                        window.smart_manager.refresh()
					}
				},
				// Failure callback.
				onFailure: (errorMsg) => {
					jQuery('.sm-ai-assistant-error').show().html(errorMsg);
				}
			}
		);

	}
    //Function to apply advanced search based on its params.
    SmartManager.prototype.applyAdvancedSearch = function(advancedSearchQuery=''){
        if(advancedSearchQuery==='' || advancedSearchQuery==='{}'){
            return;
        }
        window.smart_manager.searchType = 'advanced';
        window.smart_manager.advancedSearchRuleCount = 0;
        window.smart_manager.advancedSearchQuery = advancedSearchQuery;
        if (("undefined" !== typeof (window.smart_manager.updateAdvancedSearchRuleCount)) && ("function" === typeof (window.smart_manager.updateAdvancedSearchRuleCount))) {
            window.smart_manager.updateAdvancedSearchRuleCount();
        }
        if (!jQuery('#search_switch').is(':checked')) {
            jQuery('#search_switch').prop('checked', true).trigger('change').attr('switchsearchtype', 'simple');
        } else{
            window.smart_manager.refresh()
        }
    }

    //Function to Remove params from URL.
    SmartManager.prototype.removeURLParams = function (paramsToRemove = []){
        const url = new URL(window.location.href);
        paramsToRemove.forEach(param => {
            url.searchParams.delete(param);
        });
        history.replaceState({}, '', url.pathname + url.search + url.hash);
    }

    if (typeof window.smart_manager_pro === 'undefined') {
        window.smart_manager = new SmartManagerPro();
    }
})(window);

jQuery(document).on('smart_manager_init','#sm_editor_grid', function() {
    window.smart_manager.date_params = {}; //params for date filter

    let additionalDateOperators = {increase_date_by:_x('increase by', "bulk edit action - 'date' fields", 'smart-manager-for-wp-e-commerce'), decrease_date_by:_x('decrease by', "bulk edit action - 'date' fields", 'smart-manager-for-wp-e-commerce')};

    window.smart_manager.batch_update_actions = {
		'numeric': {increase_by_per:_x('increase by %', "bulk edit action - 'number' fields", 'smart-manager-for-wp-e-commerce'), decrease_by_per:_x('decrease by %', "bulk edit action - 'number' fields", 'smart-manager-for-wp-e-commerce'), increase_by_num:_x('increase by number', "bulk edit action - 'number' fields", 'smart-manager-for-wp-e-commerce'), decrease_by_num:_x('decrease by number', "bulk edit action - 'number' fields", 'smart-manager-for-wp-e-commerce')},
		'image': {},
        	'multipleImage': {},
		'datetime': Object.assign({set_datetime_to:_x('set datetime to', "bulk edit action - 'datetime' fields", 'smart-manager-for-wp-e-commerce'), set_date_to:_x('set date to', "bulk edit action - 'datetime' fields", 'smart-manager-for-wp-e-commerce'), set_time_to:_x('set time to', "bulk edit action - 'datetime' fields", 'smart-manager-for-wp-e-commerce')}, additionalDateOperators),
        	'date': Object.assign({set_date_to:_x('set date to', "bulk edit action - 'date' fields", 'smart-manager-for-wp-e-commerce')},additionalDateOperators),
        	'time': Object.assign({set_time_to:_x('set time to', "bulk edit action - 'time' fields", 'smart-manager-for-wp-e-commerce')},additionalDateOperators),
		'dropdown': {},
		'multilist': {add_to:_x('add to', "bulk edit action - 'multiselect list' fields", 'smart-manager-for-wp-e-commerce'), remove_from:_x('remove from', "bulk edit action - 'multiselect list' fields", 'smart-manager-for-wp-e-commerce')},
        'serialized': {},
		'text': {prepend:_x('prepend', "bulk edit action - 'text' fields", 'smart-manager-for-wp-e-commerce'), append:_x('append', "bulk edit action - 'text' fields", 'smart-manager-for-wp-e-commerce'), search_and_replace:_x('search & replace', "bulk edit action - 'text' fields", 'smart-manager-for-wp-e-commerce')}
	}

    	let types_exclude_set_to = ['datetime', 'date', 'time']

	Object.keys(window.smart_manager.batch_update_actions).forEach(key => {
        let setToObj = (types_exclude_set_to.includes(key)) ? {} : {set_to: _x('set to', 'bulk edit action', 'smart-manager-for-wp-e-commerce')}
		window.smart_manager.batch_update_actions[key] = Object.assign(setToObj, window.smart_manager.batch_update_actions[key],{copy_from: _x('copy from', 'bulk edit action', 'smart-manager-for-wp-e-commerce')}, {copy_from_field: _x('copy from field', 'bulk edit action', 'smart-manager-for-wp-e-commerce')});
	});
})
.on('sm_top_bar_loaded', '#sm_top_bar', function() {

        jQuery(document).off('click', '.sm_date_range_container .smart-date-icon').on('click', '.sm_date_range_container .smart-date-icon', function() {

            if( jQuery('.sm_date_range_container .dropdown-menu').is(':visible') === false ){
                jQuery('.sm_date_range_container .dropdown-menu').show();
            } else {
                jQuery('.sm_date_range_container .dropdown-menu').hide();
            }

            // jQuery('.sm_date_range_container .dropdown-menu').toggle();
        });

        jQuery(document).off('click', ':not(.sm_date_range_container .dropdown-menu)').on('click', ':not(.sm_date_range_container .dropdown-menu)', function( e ){
            if ( jQuery(e.target).hasClass('smart-date-icon') === false && jQuery('.sm_date_range_container .dropdown-menu').is(':visible') === true ) {
                jQuery('.sm_date_range_container .dropdown-menu').hide();
            }
        });

        jQuery(document).off('click', '.sm_date_range_container .dropdown-menu li a').on('click', '.sm_date_range_container .dropdown-menu li a', function(e) {
            e.preventDefault();

            jQuery('.sm_date_range_container .dropdown-menu').hide();
            window.smart_manager.proSelectDate(jQuery(this).attr('data-key'));
        });

        //Code for initializing the date picker
        jQuery('.sm_date_range_container input.sm_date_selector').Zebra_DatePicker({
                                                                                                    format: 'd M Y H:i:s',
                                                                                                    // format: 'dd-mm-yy H:i:s',
                                                                                                    show_icon: false,
                                                                                                    show_select_today: false,
                                                                                                    readonly_element: false,
                                                                                                    default_position: 'below',
                                                                                                    lang_clear_date: 'Clear dates',
                                                                                                    onClear: window.smart_manager.clearDateFilter,
                                                                                                    start_date: new Date( new Date().setHours(0, 0, 0) ),
                                                                                                    onSelect: function(fdate, jsdate) {
                                                                                                        jQuery(this).change();
                                                                                                        let id = jQuery(this).attr('id'),
                                                                                                            selected_date_obj = new Date(fdate),
                                                                                                            params = {'start_date_formatted':'',
                                                                                                                        'start_date_default_format':'',
                                                                                                                        'end_date_formatted':'',
                                                                                                                        'end_date_default_format':''};

                                                                                                        if( id == 'sm_date_selector_start_date' ) { //if end_date is not set

                                                                                                            params.start_date_formatted = fdate;
                                                                                                            params.start_date_default_format = jsdate;

                                                                                                            var end_date = jQuery('#sm_date_selector_end_date').val(),
                                                                                                                end_time = '';

                                                                                                            if( end_date == '' ) {
                                                                                                                end_date_obj = new Date( selected_date_obj.getFullYear(), selected_date_obj.getMonth(), ( selected_date_obj.getDate() + 29 ) );
                                                                                                                end_time =  '23:59:59';
                                                                                                            } else {
                                                                                                                end_date_obj = new Date(end_date);
                                                                                                                end_time =  window.smart_manager.strPad(end_date_obj.getHours(), 2) + ':' + window.smart_manager.strPad(end_date_obj.getMinutes(), 2) + ':' + window.smart_manager.strPad(end_date_obj.getSeconds(), 2);
                                                                                                            }
                                                                                                            var y = end_date_obj.getFullYear() + '',
                                                                                                                m = end_date_obj.getMonth(),
                                                                                                                d = window.smart_manager.strPad(end_date_obj.getDate(), 2);

                                                                                                            params.end_date_formatted = d + ' ' + window.smart_manager.month_names_short[m] + ' ' + y + ' ' + end_time;
                                                                                                            params.end_date_default_format = y + '-' + window.smart_manager.strPad((m+1), 2) + '-' + d + ' ' + end_time;

                                                                                                            if( end_date == '' ) {
                                                                                                                end_date_datepicker = jQuery('.sm_date_range_container input.end-date').data('Zebra_DatePicker');
                                                                                                                end_date_datepicker.set_date(params.end_date_formatted);
                                                                                                                end_date_datepicker.update({'current_date': new Date(params.end_date_default_format)});
                                                                                                            }


                                                                                                        } else if( id == 'sm_date_selector_end_date' ) { //if start_date is not set

                                                                                                            params.end_date_formatted = fdate;
                                                                                                            params.end_date_default_format = jsdate;

                                                                                                            var start_date = jQuery('#sm_date_selector_start_date').val(),
                                                                                                                start_time = '';

                                                                                                            if( start_date == '' ) {
                                                                                                                start_date_obj = new Date( selected_date_obj.getFullYear(), selected_date_obj.getMonth(), ( selected_date_obj.getDate() - 29 ) );
                                                                                                                start_time = '23:59:59';
                                                                                                            } else {
                                                                                                                start_date_obj = new Date(start_date);
                                                                                                                start_time = window.smart_manager.strPad(start_date_obj.getHours(), 2) + ':' + window.smart_manager.strPad(start_date_obj.getMinutes(), 2) + ':' + window.smart_manager.strPad(start_date_obj.getSeconds(), 2);
                                                                                                            }
                                                                                                            var y = start_date_obj.getFullYear() + '',
                                                                                                                m = start_date_obj.getMonth(),
                                                                                                                d = window.smart_manager.strPad(start_date_obj.getDate(), 2);


                                                                                                            params.start_date_formatted = d + ' ' + window.smart_manager.month_names_short[m] + ' ' + y + ' ' + start_time;
                                                                                                            params.start_date_default_format = y + '-' + window.smart_manager.strPad((m+1), 2) + '-' + d + ' ' + start_time;

                                                                                                            if( start_date == '' ) {
                                                                                                                start_date_datepicker = jQuery('.sm_date_range_container input.start-date').data('Zebra_DatePicker');
                                                                                                                start_date_datepicker.set_date(params.start_date_formatted);
                                                                                                                start_date_datepicker.update({'current_date': new Date(params.start_date_default_format)});
                                                                                                            }
                                                                                                        }

                                                                                                        window.smart_manager.sm_handle_date_filter(params);
                                                                                                    }
                                                                                                });

        if( typeof( window.smart_manager.date_params.date_filter_params ) != 'undefined' && window.smart_manager.isJSON( window.smart_manager.date_params.date_filter_params ) ) {

            selected_dates = JSON.parse(window.smart_manager.date_params.date_filter_params);

            start_date_datepicker = jQuery('.sm_date_range_container input.start-date').data('Zebra_DatePicker');
            start_date_datepicker.set_date(selected_dates.start_date_formatted);
            start_date_datepicker.update({'current_date': new Date(selected_dates.start_date_default_format)});

            end_date_datepicker = jQuery('.sm_date_range_container input.end-date').data('Zebra_DatePicker');
            end_date_datepicker.set_date(selected_dates.end_date_formatted);
            end_date_datepicker.update({'current_date': new Date(selected_dates.end_date_default_format)});

        }

    })

.off('click','.sa_sm_batch_update_background_link').on('click','.sa_sm_batch_update_background_link',function() { //Code for enabline background updating
    window.location.reload();

    // window.smart_manager.hideNotification();
    // window.smart_manager.refresh();

    // if( jQuery('#sm_top_bar_action_btns_update #batch_update_sm_editor_grid, #sm_top_bar_action_btns_update .sm_beta_dropdown_content').hasClass('sm-ui-state-disabled') === false ) {
    //     jQuery('#sm_top_bar_action_btns_update #batch_update_sm_editor_grid, #sm_top_bar_action_btns_update .sm_beta_dropdown_content').addClass('sm-ui-state-disabled');
    // }

    // if( jQuery("#wpbody .sm_beta_pro_background_update_notice").length == 0 ) {
    //     jQuery('<div id="sm_beta_pro_background_update_notice" class="notice notice-info sm_beta_pro_background_update_notice"><p><strong>Success!</strong> '+ params.title +' initiated – Your records are being updated in the background. You will be notified on your email address <strong><code>'+window.smart_manager.sm_admin_email+'</code></strong> once the process is completed.</p></div>').insertBefore('#wpbody .wrap');
    //     // To go to start of the SM page so users can see above notice.
    //     window.scrollTo(0,0);
    // }
})
// Code for handling the undo & delete tasks functionality
.on('sm_show_tasks_change', '#sm_editor_grid', function(){
   jQuery(document).off( 'click', ".sm_top_bar_action_btns #sm_beta_undo_selected,.sm_top_bar_action_btns #sm_beta_undo_all_tasks,.sm_top_bar_action_btns #sm_beta_delete_selected_tasks, .sm_top_bar_action_btns #sm_beta_delete_all_tasks").on( 'click', ".sm_top_bar_action_btns #sm_beta_undo_selected,.sm_top_bar_action_btns #sm_beta_undo_all_tasks,.sm_top_bar_action_btns #sm_beta_delete_selected_tasks, .sm_top_bar_action_btns #sm_beta_delete_all_tasks", function(){
        if("undefined" !== typeof(window.smart_manager.taskActionsModal) && "function" === typeof(window.smart_manager.taskActionsModal)){
            window.smart_manager.taskActionsModal({id: jQuery(this).attr('id'),btnText: jQuery(this).text()});
        }
    })
    let simpleSearchCond = (window.smart_manager.searchType === 'simple') ? window.smart_manager.simpleSearchText : '';
    let advancedSearchCond = (window.smart_manager.searchType === 'advanced') ? window.smart_manager.advancedSearchQuery : '';
    let type = (window.smart_manager.isTasksEnabled() === 1) ? 'postType' : 'task';
    window.smart_manager.savedSearchConds[type] = {
        simpleSearchCond,
        advancedSearchCond
    };
    if((typeof window.smart_manager.dirtyRowColIds !== 'undefined') && Object.getOwnPropertyNames(window.smart_manager.dirtyRowColIds).length > 0){
		window.smart_manager.confirmUnsavedChanges({'yesCallback': window.smart_manager.showTasks, 'noCallback': window.smart_manager.handleShowTasks})
    }else if("undefined" !== typeof(window.smart_manager.showTasks) && "function" === typeof(window.smart_manager.showTasks)){
        window.smart_manager.showTasks();
    }
})

// Code for handling renaming of column titles
.off('focusout','.sm-title-input').on('focusout','.sm-title-input', function(e){
    e.target.readOnly = true;
    e.target.classList.remove('sm-column-title-input-edit')
    let parent = e.target.closest('li');
    if(!parent) return;
    let keyInput = parent.querySelector(".js-column-key");
    if(!keyInput) return;

    if(!e.target.value){ //handling for empty values
        (window.smart_manager.editedColumnTitles.hasOwnProperty(keyInput.value)) ? delete window.smart_manager.editedColumnTitles[keyInput.value] : ''
        return;
    }

    let titleInput = parent.querySelector(".js-column-title");
    if(!titleInput) return;
    if(titleInput.value == e.target.value) return;
    window.smart_manager.editedColumnTitles[keyInput.value] = e.target.value;
})
//Code to handle dashboard change in eligible dashboards modal.
.off('change', '#eligible_dashboards_select').on('change', '#eligible_dashboards_select',function(){
    let savedSearchParams = window.smart_manager.findSavedSearchBySlug(window.smart_manager.eligibleDashboardSavedSearch);// need to change.
    if((jQuery(this).val()) !== (savedSearchParams.parent_post_type)){
        savedSearchParams.params.search_params.params = window.smart_manager.getPostsColumnsFromQuery(savedSearchParams.params.search_params.params);
    }
    window.smart_manager.advancedSearchQuery = savedSearchParams.params.search_params.params;
    window.smart_manager.savedSearchParams = savedSearchParams.params.search_params;
    window.smart_manager.loadingDashboardForsavedSearch = true;
    window.smart_manager.savedSearchDashboardKey = jQuery(this).val();
    window.smart_manager.savedSearchDashboardName = jQuery( "#eligible_dashboards_select option:selected" ).text();
    jQuery("#sm_dashboard_select").val(window.smart_manager.eligibleDashboardSavedSearch).trigger('change');
    jQuery(".sm-modal-close").trigger("click");
    window.smart_manager.eligibleDashboardSavedSearch="";
})

// Code to handle undo action from inline edit success message.
.off( 'click', '#undo_action' ).on( 'click', '#undo_action' ,function(e){
    e.preventDefault();
    window.smart_manager.selectedRows = [0]
    let taskId = jQuery(this).data("task-id");
    window.smart_manager.taskId = (taskId) ? taskId : 0;
    if("undefined" !== typeof(window.smart_manager.taskActionsModal) && "function" === typeof(window.smart_manager.taskActionsModal)){
        window.smart_manager.taskActionsModal({taskInlineEditMessage: 'last update?'});
    }
})
//code to delete saved search.
.off('mousedown', ".dashboard-combobox-saved-search-delete").on('mousedown', ".dashboard-combobox-saved-search-delete", function(e){
    if (e.button !== 0) {
        return;
    }
    e.preventDefault();
    let view_name = jQuery(this).attr('view_name');
    if((!view_name) || (!view_name.length)){
        return;
    }
    let params = {};
    params.btnParams = {}
    params.title = '<span class="sm-error-icon"><span class="dashicons dashicons-warning" style="vertical-align: text-bottom;"></span>&nbsp;'+_x('Attention!', 'modal title', 'smart-manager-for-wp-e-commerce')+'</span>';
    params.content = '<span style="font-size: 1.2em;">'+_x('Do you really want to', 'modal content', 'smart-manager-for-wp-e-commerce')+' <span class="sm-error-icon"><strong>'+_x('delete', 'modal content', 'smart-manager-for-wp-e-commerce')+'</strong></span> '+_x(`"${view_name}" saved search?`, 'modal content', 'smart-manager-for-wp-e-commerce')+'</span>';
    params.titleIsHtml = true;
    params.height = 200;
    if ( typeof (window.smart_manager.deleteView) !== "undefined" && typeof (window.smart_manager.deleteView) === "function" ) {
        params.btnParams.yesCallbackParams = {view_slug:jQuery(this).attr('view_slug'),success_msg:`${view_name} saved search deleted successfully!`}
        params.btnParams.yesCallback = window.smart_manager.deleteView;
    }
    window.smart_manager.showConfirmDialog(params);
    jQuery('#sm_select2_childs_section').removeClass("visible");
})
// Toggle the floating text box visibility on save button click in bulk edit section.
.off('click','#sm_bulk_edit_save_btn').on('click','#sm_bulk_edit_save_btn', function(e){
    document.getElementById('sm-bulk-edit-save-floating-box')?.classList?.toggle('hidden');
    document.getElementById('bulk_edit_title')?.focus()
})
//validation on create saved bulk edits input field.
.off('input','#bulk_edit_title').on('input','#bulk_edit_title', function(e){
    let errorDiv = document.getElementById('sm-saved-bulk-edit-validation');
    let input = document.getElementById('bulk_edit_title');
    if((!errorDiv) || ('undefined' === typeof (errorDiv))){
        return
    }
    if (this.value.trim() === '') {
        errorDiv.textContent = _x('Name cannot be empty.', 'saved bulk edit error msg', 'smart-manager-for-wp-e-commerce');
        errorDiv.classList.remove('hidden')
        errorDiv.classList.add('text-error')
        input.classList.add('border-red')
    } else {
        errorDiv.textContent = '';
        errorDiv.classList.add('hidden');
        errorDiv.classList.remove('text-error')
        input.classList.remove('border-red')
    }
})
.off('click','.save-bulk-edit-actions .be-save-close').on('click','.save-bulk-edit-actions .be-save-close', function(e){
    document.getElementById('sm-bulk-edit-save-floating-box')?.classList?.add('hidden');
})
//Hide save bulk edit actions floating box on click outside it.
.on('click', function (event) {
    if (!jQuery(event.target).closest('#sm_bulk_edit_save_btn').length){
        window.smart_manager?.hideElementOnClickOutside(event, "sm-bulk-edit-save-floating-box");
    }
})
//show Schedule Export CSV modal.
.off( 'click', "#sm_export_csv #sm_schedule_export").on('click','#sm_export_csv #sm_schedule_export',function (event) {
    window.smart_manager.modal = {
        title:
            /* translators: %s: Schedule Export CSV modal title */
            _x(`Schedule ${window.smart_manager?.dashboardDisplayName || ''} Export CSV`, 'modal title', 'smart-manager-for-wp-e-commerce'),
        content: window.smart_manager.scheduleCSVExportModalHTML(),
        autoHide: false,
        cta: {
            closeModalOnClick: false,
            title: _x('Create', 'button', 'smart-manager-for-wp-e-commerce'),
            callback: function() {
                // Retrieve form data from the modal form
                if("undefined" !== typeof(window.smart_manager.validateAndGetScheduleExportFormData) && "function" === typeof(window.smart_manager.validateAndGetScheduleExportFormData)){
                    let formData = window.smart_manager.validateAndGetScheduleExportFormData();
                    if(formData){
                        formData.is_new_schedule_export = true;
                        formData.scheduledExportActionAdminUrl = window.smart_manager?.scheduledExportActionAdminUrl || ''
                        window.smart_manager.hideModal();
                        window.smart_manager.hidePannelDialog = false;
                        window.smart_manager.generateCsvExport({
                            isScheduledExport: true,
                            scheduleParams: formData,
                            scheduleExportAjaxCallback: window.smart_manager.scheduleExportAjaxCallback,
                        })
                    }
                }
            }
        },
        onCreate: function(){
            if("undefined" !== typeof(window.smart_manager.initOrderStatusesSelect2) && "function" === typeof(window.smart_manager.initOrderStatusesSelect2)){
                window.smart_manager.initOrderStatusesSelect2();
            }
            if("undefined" !== typeof(window.smart_manager.scheduleDatePicker) && "function" === typeof(window.smart_manager.scheduleDatePicker)){
                window.smart_manager.scheduleDatePicker('#sm_schedule_export_start_time');
            }
        },
        closeCTA: { title: _x('Cancel', 'button', 'smart-manager-for-wp-e-commerce'),
            callback: function() {
                window.smart_manager.hidePannelDialog = false;
            }
        },
        contentClass: "sm-scheduled-export-modal"
    }
    window.smart_manager.showModal();
})
.off( 'click', "#sm_manage_schedule_export").on( 'click', "#sm_manage_schedule_export", function(e){
    if(window.smart_manager.hasOwnProperty('scheduledExportActionAdminUrl')){
        window.open(window.smart_manager.scheduledExportActionAdminUrl, '_blank');
    }
})
.off( 'change', '#sm_schedule_export_interval, #sm_schedule_export_email' ).on( 'change', '#sm_schedule_export_interval, #sm_schedule_export_email', function() {
    jQuery( this ).removeClass( 'border-red' );
})
.off( 'click', '#sm_schedule_export_start_time' ).on( 'click', '#sm_schedule_export_start_time', function() {
    jQuery( this ).removeClass( 'border-red' );
})
//Highlight input area in focus.
.off( 'focus', '#sm_ai_prompt_input' ).on( 'focus', '#sm_ai_prompt_input', function() {
    jQuery('.sm-ai-prompt-input-parent').addClass( 'active' );
} )
.off( 'blur', '#sm_ai_prompt_input' ).on( 'blur', '#sm_ai_prompt_input', function() {
	jQuery('.sm-ai-prompt-input-parent').removeClass( 'active' );
} )
//Open AI assistant modal.
.off( 'click', '.sm-ai-assistant-icon' ).on('click', '.sm-ai-assistant-icon', function (event) {
    if((!sm_beta_params.hasOwnProperty('is_ai_integration_enabled')||(1!==parseInt(sm_beta_params.is_ai_integration_enabled)))){
        window.smart_manager.notification = {
            message: _x('Please configure AI Integration settings under Settings > General Settings to use this feature.','notification', 'smart-manager-for-wp-e-commerce')
        }
        window.smart_manager.showNotification();
        return;
    }
    if(window.smart_manager.hasOwnProperty('isViewContainSearchParams') && window.smart_manager.isViewContainSearchParams===true){
        window.smart_manager.notification = {
            message: _x('Cannot switch search when using Custom Views.','notification', 'smart-manager-for-wp-e-commerce')
        }
        window.smart_manager.showNotification();
        return;
    }
    const privacyDescription = _x('We securely log your AI interactions to improve our service. Your data is handled with strict privacy.', 'ai assistant modal privacy summary', 'smart-manager-for-wp-e-commerce');
    const privacyCta = (window.smart_manager && typeof window.smart_manager.getExternalHelpMarkup === 'function')
        ? window.smart_manager.getExternalHelpMarkup(
            'https://www.storeapps.org/privacy-policy/',
            _x('Read the Smart Manager privacy policy (external site)', 'ai assistant modal privacy button', 'smart-manager-for-wp-e-commerce'),
            _x('Offline help: Contact your site administrator for privacy details or temporarily allow external access to review the policy.', 'ai assistant modal offline privacy note', 'smart-manager-for-wp-e-commerce')
        )
        : '';

    window.smart_manager.modal = {
        title: _x('Your Smart AI Assistant is here!','ai assistant modal title','smart-manager-for-wp-e-commerce'),
        content: `
        <div class="sm-ai-assistant-modal">
            <div class="sm-ai-section sm-ai-command-section">
                <p class="sm-ai-description text-gray-700 text-sm font-medium pb-2">
                ${_x('Tell me what you need, and I’ll find it for you.','ai assistant modal content','smart-manager-for-wp-e-commerce')}
                </p>
            </div>
            <div class="sm-ai-input-container mt-2 command-mode">
                <div class="sm-ai-prompt-input-parent flex items-center w-full border border-gray-300 rounded-lg shadow-sm focus-within:border-indigo-500">
                    <textarea id="sm_ai_prompt_input" type="text"
                        class="flex-1 px-4 py-3 text-sm text-gray-700 bg-transparent mr-2"
                        placeholder="${_x('e.g. Show products with sale price greater than 100','ai assistant modal content','smart-manager-for-wp-e-commerce')}" rows="1">${ ( jQuery('#search_switch').is(':checked') && window.smart_manager.hasOwnProperty('advancedSearchPrompt') ) ? window.smart_manager.advancedSearchPrompt : ''}</textarea>
                    <button id="sm_ai_mic_btn"
                        class="flex justify-center mr-2 w-8 h-8 text-gray-500 hover:text-indigo-600 focus:outline-none focus:shadow-outline">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                        class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z" />
                        </svg>
                    </button>
                </div>
                <div class="sm-ai-assistant-error text-sm pt-3"></div>
                <p class="description mt-4"><strong>Note:</strong> ${privacyDescription}</p>
                ${privacyCta}
            </div>
        </div>
        `,
        autoHide: false,
        cta: {
            title: _x('GO', 'button', 'smart-manager-for-wp-e-commerce'),
            closeModalOnClick: false,
            callback: function () {
                jQuery('#sm_ai_prompt_input').parent().removeClass('sm_border_red');
                jQuery('.sm-ai-assistant-error').hide().html('');
                let prompt = jQuery('#sm_ai_prompt_input').val();
                if(prompt.trim() === ''){
                    jQuery('#sm_ai_prompt_input').parent().addClass('sm_border_red');
                    jQuery('.sm-ai-assistant-error').show().html(_x('Please enter a prompt to continue.','ai assistant modal content','smart-manager-for-wp-e-commerce'));
                    return;
                }
                window.smart_manager.NLConverter.convert('advanced_search',prompt);
            }
        },
        onCreate: function(){
            jQuery('.sm-ai-assistant-error').hide().html('');
            window.smart_manager.NLConverter = new SmartManagerNLConverter({
                micBtnID:'sm_ai_mic_btn',
                promptInputID:'sm_ai_prompt_input',
                // onVoiceRecognitionComplete: window.smart_manager.onVoiceRecognitionComplete
            });
            window.smart_manager.addNLPForAdvancedSearch();
        }
    }
    window.smart_manager.showModal()
});
