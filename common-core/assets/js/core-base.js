(function (window) {
    function SaCommonManager() {}
    SaCommonManager.prototype.init = function () {
        try{
            this.saCommonNonce = '';
            this.commonManagerAjaxUrl = '';
            this.dateParams = {}; //params for date filter
            this.defaultRoute = "dashboard";
            this.notification = {} // object for handling all notification messages
            this.notificationHideDelayInMs = 16000
            this.modal = {} // object for handling all modal dialogs
            this.selectAll = false;
            this.selectedIds = [];
            this.selectedRows = [];
            this.saManagerStoreModel = new Array()
            this.currentColModel = '';
            this.hideDialog = '';
            this.bulkEditRoute = "bulkEdit";
            // defining operators for diff datatype for advanced search
            let intOperators = {
                'eq': '==',
                'neq': '!=',
                'lt': '<',
                'gt': '>',
                'lte': '<=',
                'gte': '>='
            };
            this.possibleOperators = {
                'numeric': intOperators,
                'date': intOperators,
                'datetime': intOperators,
                'date': intOperators,
                'dropdown': {
                    'is': _x('is', "select options - operator for 'dropdown' data type fields", 'smart-manager-for-wp-e-commerce'),
                    'is not': _x('is not', "select options - operator for 'dropdown' data type fields", 'smart-manager-for-wp-e-commerce')
                },
                'text': {
                    'is': _x('is', "select options - operator for 'text' data type fields", 'smart-manager-for-wp-e-commerce'),
                    'like': _x('contains', "select options - operator for 'text' data type fields", 'smart-manager-for-wp-e-commerce'),
                    'is not': _x('is not', "select options - operator for 'text' data type fields", 'smart-manager-for-wp-e-commerce'),
                    'not like': _x('not contains', "select options - operator for 'text' data type fields", 'smart-manager-for-wp-e-commerce')
                }
            }
            this.ajaxParams = {}
            this.dashboardKey = ''
            this.columnNamesBatchUpdate= new Array()
            this.pluginSlug = '';
            this.savedBulkEditConditions = [];
            this.date_params = {}; //params for date filter
            let additionalDateOperators = { increase_date_by: _x('increase by', "bulk edit action - 'date' fields", 'smart-manager-for-wp-e-commerce'), decrease_date_by: _x('decrease by', "bulk edit action - 'date' fields", 'smart-manager-for-wp-e-commerce') };
            this.batchUpdateActions = {
                'numeric': { increase_by_per: _x('increase by %', "bulk edit action - 'number' fields", 'smart-manager-for-wp-e-commerce'), decrease_by_per: _x('decrease by %', "bulk edit action - 'number' fields", 'smart-manager-for-wp-e-commerce'), increase_by_num: _x('increase by number', "bulk edit action - 'number' fields", 'smart-manager-for-wp-e-commerce'), decrease_by_num: _x('decrease by number', "bulk edit action - 'number' fields", 'smart-manager-for-wp-e-commerce') },
                'image': {},
                'multipleImage': {},
                'datetime': Object.assign({ set_datetime_to: _x('set datetime to', "bulk edit action - 'datetime' fields", 'smart-manager-for-wp-e-commerce'), set_date_to: _x('set date to', "bulk edit action - 'datetime' fields", 'smart-manager-for-wp-e-commerce'), set_time_to: _x('set time to', "bulk edit action - 'datetime' fields", 'smart-manager-for-wp-e-commerce') }, additionalDateOperators),
                'date': Object.assign({ set_date_to: _x('set date to', "bulk edit action - 'date' fields", 'smart-manager-for-wp-e-commerce') }, additionalDateOperators),
                'time': Object.assign({ set_time_to: _x('set time to', "bulk edit action - 'time' fields", 'smart-manager-for-wp-e-commerce') }, additionalDateOperators),
                'dropdown': {},
                'multilist': { add_to: _x('add to', "bulk edit action - 'multiselect list' fields", 'smart-manager-for-wp-e-commerce'), remove_from: _x('remove from', "bulk edit action - 'multiselect list' fields", 'smart-manager-for-wp-e-commerce') },
                'serialized': {},
                'text': { prepend: _x('prepend', "bulk edit action - 'text' fields", 'smart-manager-for-wp-e-commerce'), append: _x('append', "bulk edit action - 'text' fields", 'smart-manager-for-wp-e-commerce'), search_and_replace: _x('search & replace', "bulk edit action - 'text' fields", 'smart-manager-for-wp-e-commerce') }
            }
            let types_exclude_set_to = ['datetime', 'date', 'time']
            Object.keys(this.batchUpdateActions).forEach(key => {
                let setToObj = (types_exclude_set_to.includes(key)) ? {} : { set_to: _x('set to', 'bulk edit action', 'smart-manager-for-wp-e-commerce') }
                this.batchUpdateActions[key] = Object.assign(setToObj, this.batchUpdateActions[key], { copy_from: _x('copy from', 'bulk edit action', 'smart-manager-for-wp-e-commerce') }, { copy_from_field: _x('copy from field', 'bulk edit action', 'smart-manager-for-wp-e-commerce') });
            });
        } catch (e){
            SaErrorHandler.log('Error initializing SaCommonManager:: ', e)
        }
    };

    SaCommonManager.prototype.getDashboardModel = function (sendRequest = true) {
        try{
            this.currentDashboardModel = '';
           // Ajax request params to get the dashboard model.
            this.ajaxParams = {
                data_type: 'json',
                data: {
                    cmd: 'get_dashboard_model',
                    security: this.saCommonNonce,
                    active_module: this.dashboardKey,
                    lang: this?.lang || ''
                }
            }
            if(sendRequest){
                this.sendRequest(this.ajaxParams, this.setDashboardModel)
            }
            //sendrequest for ABE
        } catch (e){
          SaErrorHandler.log('Error in getDashboardModel:: ', e)
        }
    }

    SaCommonManager.prototype.setDashboardModel = function (response) {
        try{
            if (typeof response == 'undefined' || response == '') {
                return;
            }
            this.currentColModel = response.columns;
            //call to function for formatting the column model
            if (typeof (this.formatDashboardColumnModel) !== "undefined" && typeof (this.formatDashboardColumnModel) === "function") {
                this.formatDashboardColumnModel();
            }
            response.columns = this.currentColModel;
            this.currentDashboardModel = response
            if (this.firstLoad) {
                this.firstLoad = false
            }
            if (typeof this.childSetDashboardModel === 'function') {
                this.childSetDashboardModel(response);
            }
            if(typeof this.setSearchableCols === 'function'){
                this.setSearchableCols();
            }
            if(1===parseInt(window.smart_manager.sm_beta_pro)){
                let showEditHistory = window.location.search.includes('show_edit_history');
                //Show tasks list based on url params.
                if(showEditHistory){
                    jQuery("#sm_show_tasks").prop('checked', true);
                    jQuery('#sm_editor_grid').trigger('sm_show_tasks_change');
                }
                //Apply advanced search to dashboards based on url params.
                if( typeof window[pluginKey].buildSearchParamsFromUrl === 'function'){
                    let advancedSearchParamsFromUrl = window[pluginKey].buildSearchParamsFromUrl( window.location.href, showEditHistory );
                    if(advancedSearchParamsFromUrl && typeof (window[pluginKey].applyAdvancedSearch) === "function"){
                        window[pluginKey].applyAdvancedSearch(advancedSearchParamsFromUrl)
                    }else if(showEditHistory){
                        window.smart_manager.refresh();
                    }
                    //Remove url params except page.
                    if(typeof (window.smart_manager.removeURLParams)==='function' ){
                        window.smart_manager.removeURLParams?.([...new URLSearchParams(location.search).keys()].filter(k => k !== 'page'));
                    }
                }
            }
        } catch (e){
            SaErrorHandler.log('Error in setDashboardModel:: ', e)
        }
    }

    SaCommonManager.prototype.setSearchableCols = function () {
        if (typeof (window[pluginKey].currentColModel) == 'undefined') {
            return;
        }

        let colModel = JSON.parse(JSON.stringify(window[pluginKey].currentColModel));
        window[pluginKey].colModelSearch = {}

        Object.entries(colModel).map(([key, obj]) => {
            if (obj.hasOwnProperty('searchable') && obj.searchable == 1) {

                if (obj.type == 'checkbox') {
                    obj.type = 'dropdown';
                    obj.search_values = window[pluginKey].getCheckboxValues(obj);
                }

                if (obj.type == 'sm.multilist') {
                    obj.type = 'dropdown';
                }

                if (obj.type == 'text') {
                    if (obj.hasOwnProperty('validator')) {
                        if (obj.validator == 'customNumericTextEditor') {
                            obj.type = 'numeric';
                        }
                    }
                }

                if (obj.type == "number") {
                    obj.type = 'numeric'
                }

                window[pluginKey].colModelSearch[obj.table_name + '.' + obj.col_name] = {
                    'title': obj.name_display,
                    'type': (obj.hasOwnProperty('search_type')) ? obj.search_type : obj.type,
                    'values': (obj.search_values) ? obj.search_values : {}
                }

            }
        });
        if (window[pluginKey].hasOwnProperty('colModelSearch') && Object.entries(window[pluginKey].colModelSearch).length > 0) {
            window[pluginKey].advancedSearchFields = Object.entries(window[pluginKey].colModelSearch).map(([key, value]) => ({
                id: key,
                text: value.title || key
            }))
        }
        if (window[pluginKey].hasOwnProperty('columnNamesBatchUpdate') && Object.entries(window[pluginKey].columnNamesBatchUpdate).length > 0) {
            window[pluginKey].bulkEditFields = Object.entries(window[pluginKey].columnNamesBatchUpdate).map(([key, value]) => ({
                id: key,
                text: value.title || key
            }))
        }
    }
    //function to format the column model
    SaCommonManager.prototype.formatDashboardColumnModel = function (column_model) {
        try{
            if (this.currentColModel == '' || typeof (this.currentColModel) == 'undefined') {
                return;
            }
            index = 0;
            this.column_names = [];
            this.currentVisibleColumns = [];
            this.columnNamesBatchUpdate= [];
            for (i = 0; i < this.currentColModel.length; i++) {
                if (typeof (this.currentColModel[i]) == 'undefined') {
                    continue;
                }
                hidden = (typeof (this.currentColModel[i].hidden) != 'undefined') ? this.currentColModel[i].hidden : true;
                column_values = (typeof (this.currentColModel[i].values) != 'undefined') ? this.currentColModel[i].values : {};
                type = (typeof (this.currentColModel[i].type) != 'undefined') ? this.currentColModel[i].type : '';
                editor = (typeof (this.currentColModel[i].editor) != 'undefined') ? this.currentColModel[i].editor : '';
                selectOptions = (typeof (this.currentColModel[i].selectOptions) != 'undefined') ? this.currentColModel[i].selectOptions : '';
                multiSelectSeparator = (typeof (this.currentColModel[i].separator) != 'undefined') ? this.currentColModel[i].separator : '';
                allowMultiSelect = false;
                if (type == 'dropdown' && editor == 'select2') {
                    if (this.currentColModel[i].hasOwnProperty('select2Options')) {
                        if (this.currentColModel[i].select2Options.hasOwnProperty('data')) {
                            column_values = {};
                            allowMultiSelect = (this.currentColModel[i].select2Options.hasOwnProperty('multiple')) ? this.currentColModel[i].select2Options.multiple : false;
                            this.currentColModel[i].select2Options.data.forEach(function (obj) {
                                column_values[obj.id] = obj.text;
                            });
                        }
                    }
                }
                let bu_values = []
                if (Object.keys(column_values).length > 0) {
                    Object.keys(column_values).forEach(key => {
                        bu_values.push({ 'key': key, 'value': column_values[key] })
                    });
                }
                let name = '';
                if (typeof (this.currentColModel[i].name) != 'undefined') {
                    name = (this.currentColModel[i].name) ? this.currentColModel[i].name.trim() : '';
                }
                if (this.currentColModel[i].hasOwnProperty('name_display') === false) {// added for state management
                    this.currentColModel[i].name_display = name;
                }
                if (hidden === false) {
                    this.column_names[index] = this.currentColModel[i].name_display; //Array for column headers
                    this.currentVisibleColumns[index] = this.currentColModel[i];
                    index++;
                }
                var batch_enabled_flag = false;
                if (this.currentColModel[i].hasOwnProperty('batch_editable')) {
                    batch_enabled_flag = this.currentColModel[i].batch_editable;
                }
                if (batch_enabled_flag === true) {
                    let type = this.currentColModel[i].type;
                    if (this.currentColModel[i].hasOwnProperty('validator')) {
                        if ('customNumericTextEditor' == this.currentColModel[i].validator) {
                            type = 'numeric';
                        }
                    }
                    this.columnNamesBatchUpdate[this.currentColModel[i].src] = { title: this.currentColModel[i].name_display, type: type, editor: this.currentColModel[i].editor, values: bu_values, src: this.currentColModel[i].data, allowMultiSelect: allowMultiSelect, multiSelectSeparator: multiSelectSeparator };
                    if (this.currentColModel[i].type == 'checkbox') {
                        this.columnNamesBatchUpdate[this.currentColModel[i].src].type = 'dropdown';
                        this.columnNamesBatchUpdate[this.currentColModel[i].src].values = this.getCheckboxValues(this.currentColModel[i]);
                    }
                    if (this.currentColModel[i].type == (this.pluginSlug + '.multilist')) {
                        this.columnNamesBatchUpdate[this.currentColModel[i].src].type = 'multilist';
                        //Code for setting the values
                        let multilistValues = this.columnNamesBatchUpdate[this.currentColModel[i].src].values
                        let multilistBulkEditValues = []
                        multilistValues.forEach((obj) => {
                            let val = (obj.hasOwnProperty('value')) ? obj.value : {}
                            let title = (val.hasOwnProperty('title')) ? val.title : ((val.hasOwnProperty('term')) ? val.term : '')
                            multilistBulkEditValues.push({ 'key': obj.key, 'value': title });
                        })
                        this.columnNamesBatchUpdate[this.currentColModel[i].src].values = multilistBulkEditValues
                    }
                }
                if (typeof (this.currentColModel[i].allow_showhide) != 'undefined' && this.currentColModel[i].allow_showhide === false) {
                    this.currentColModel[i].hidedlg = true;
                }
                this.currentColModel[i].name = this.currentColModel[i].index;
            }
            if (typeof this.childFormatDashboardColumnModel === 'function') {
                this.childFormatDashboardColumnModel(this.currentColModel);
            }
        } catch (e){
            SaErrorHandler.log('Error in formatDashboardColumnModel:: ', e)
        }
    }

    SaCommonManager.prototype.sendRequest = function(params, callback, callbackParams) {
        try{
            jQuery.ajax({
                type: ((typeof (params.call_type) != 'undefined') ? params.call_type : 'POST'),
                url: ((typeof (params.call_url) != 'undefined') ? params.call_url : this.commonManagerAjaxUrl),
                dataType: ((typeof (params.data_type) != 'undefined') ? params.data_type : 'text'),
                async: ((typeof (params.async) != 'undefined') ? params.async : true),
                data: params.data,
                success: function (resp) {
                    if (typeof params.showLoader == 'undefined' || (typeof params.showLoader != 'undefined' && params.showLoader !== false)) {
                        if (false == params.hasOwnProperty('hideLoader') || (params.hasOwnProperty('hideLoader') && false != params.hideLoader)) {
                            SaCommonManager.prototype.showLoader(false);
                        }
                    }
                    return ((typeof (callbackParams) != 'undefined') ? callback(callbackParams, resp) : callback(resp));
                },
                error: function (error) {
                    console.log('AJAX failed::', error);
                }
            });
        }catch(e){
            SaErrorHandler.log('In sending AJAX request:: ', e)
        }

    }

    SaCommonManager.prototype.showLoader = function (is_show = true) {
        try {
            if (is_show) {
                jQuery('.sa-loader-container').hide().show();
            } else {
                jQuery('.sa-loader-container').hide();
            }
        } catch (e) {
            SaErrorHandler.log('Error in showLoader:: ', e)
        }
    }

    //Function to show confirm dialog
    SaCommonManager.prototype.showConfirmDialog = function (params) {
        this.modal = {
            title: (params.hasOwnProperty('title') !== false && params.title != '') ? params.title : _x('Warning', 'modal title', 'smart-manager-for-wp-e-commerce'),
            content: (params.hasOwnProperty('content') !== false && params.content != '') ? params.content : _x('Are you sure?', 'modal content', 'smart-manager-for-wp-e-commerce'),
            autoHide: false,
            showCloseIcon: (params.hasOwnProperty('showCloseIcon')) ? params.showCloseIcon : true,
            cta: {
                title: ((params.btnParams.hasOwnProperty('yesText')) ? params.btnParams.yesText : _x('Yes', 'button', 'smart-manager-for-wp-e-commerce')),
                closeModalOnClick: (params.btnParams.hasOwnProperty('hideOnYes')) ? params.btnParams.hideOnYes : true,
                callback: function () {
                    if (params.btnParams.hasOwnProperty('yesCallback') && typeof params.btnParams.yesCallback === "function") {
                        if (params.btnParams.hasOwnProperty('yesCallbackParams')) {
                            params.btnParams.yesCallback(params.btnParams.yesCallbackParams);
                        } else {
                            params.btnParams.yesCallback();
                        }
                    }
                }
            },
            closeCTA: {
                title: ((params.btnParams.hasOwnProperty('noText')) ? params.btnParams.noText : _x('No', 'button', 'smart-manager-for-wp-e-commerce')),
                callback: function () {
                    if (params.btnParams.hasOwnProperty('noCallback') && typeof params.btnParams.noCallback === "function") {
                        params.btnParams.noCallback();
                    }
                }
            },
            route: params?.route || false
        }
        this.showModal()
    }

    // Function to handle all modal dialog
    SaCommonManager.prototype.showModal = function(){
        try {
            if(this.modal.hasOwnProperty('title') && '' !== this.modal.title && this.modal.hasOwnProperty('content') && '' !== this.modal.content && (typeof (this.showPannelDialog) !== "undefined" && typeof (this.showPannelDialog) === "function" && typeof (this.getDefaultRoute) !== "undefined" && typeof (this.getDefaultRoute) === "function")){
                let test = '';
                test = (this.modal?.route || this.getDefaultRoute());
                this.showPannelDialog(this.modal?.route || this.getDefaultRoute())
            }
        } catch (e) {
            SaErrorHandler.log('Exception occurred in showModal:: ', e)
        }
    }

    // Function for hiding modal
	SaCommonManager.prototype.hideModal = function() {
		setTimeout(() => {
			try{
				this.modal = {}
				if(typeof (this.showPannelDialog) !== "undefined" && typeof (this.showPannelDialog) === "function" && typeof (this.getDefaultRoute) !== "undefined" && typeof (this.getDefaultRoute) === "function"){
					this.showPannelDialog('',this.getDefaultRoute(true))
				}
			} catch(e){
				SaErrorHandler.log('Exception occurred in hideModal:: ', e)
			}
		},200)
	}

    // Function to show the pannel dialog
    SaCommonManager.prototype.showPannelDialog = function(route = '', currentRoute = '') {
        try{
            if(!route && !currentRoute){
                return
            }
            let routeIdentifier = "#!/"
            let currentURL = (window.location.href.indexOf(routeIdentifier) >= 0) ? window.location.href.substring(0, window.location.href.indexOf(routeIdentifier)) : window.location.href
            if(!currentURL){
                return
            }
            let defaultRoute = routeIdentifier+this.defaultRoute
            route = (!route) ? ((routeIdentifier === currentRoute) ? defaultRoute : routeIdentifier) : ((routeIdentifier === route) ? route : routeIdentifier+route)
            window.location.href = currentURL + route
        } catch (e){
            SaErrorHandler.log('Exception occurred in showPannelDialog:: ', e)
        }
    }

    // Function to get default route
    SaCommonManager.prototype.getDefaultRoute = function(isReplaceRoute = false){
        try{
            return (isReplaceRoute) ?
             ((window.location.href.includes(this.defaultRoute) ) ? '/'+this.defaultRoute : '#!/')
             : ((window.location.href.includes(this.defaultRoute) ) ? '#!/' : this.defaultRoute)
        } catch (e){
            SaErrorHandler.log('Exception occurred in getDefaultRoute:: ', e)
        }
    }

    SaCommonManager.prototype.getCheckboxValues = function( colObj ) {
        try{
            if(!colObj){
                return [];
            }
            if( !(colObj.hasOwnProperty('checkedTemplate') && colObj.hasOwnProperty('uncheckedTemplate')) ) {
                colObj.checkedTemplate = 'true';
                colObj.uncheckedTemplate = 'false';
            }
            return new Array({'key': colObj.checkedTemplate, 'value':  String(colObj.checkedTemplate).capitalize()},
                            {'key': colObj.uncheckedTemplate, 'value':  String(colObj.uncheckedTemplate).capitalize()});
        } catch (e){
            SaErrorHandler.log('Exception occurred in getCheckboxValues:: ', e)
        }
    }

    //Function to show progress dialog
    SaCommonManager.prototype.showProgressDialog = function (title = '') {
        try {
            this.modal = {
                title: (title != '') ? title : _x('Please Wait', 'progressbar modal title', 'smart-manager-for-wp-e-commerce'),
                content: '<div class="sa_background_update_progressbar"> <span class="sa_background_update_progressbar_text" style="" >' + _x('Initializing...', 'progressbar modal content', 'smart-manager-for-wp-e-commerce') + '</span></div><div class="sa_' + this.pluginSlug + '_batch_update_background_link" >' + _x('Continue in background', 'progressbar modal content', 'smart-manager-for-wp-e-commerce') + '</div>',
                autoHide: false,
                showCloseIcon: false,
                cta: {}
            }
            this.showModal()
        } catch (e) {
            SaErrorHandler.log('Exception occurred in showProgressDialog:: ', e)
        }
    }

    SaCommonManager.prototype.reset = function (fullReset = false) {
        try {
            if (fullReset) {
                this.column_names = [];
                this.savedBulkEditConditions = []
            }
            this.currentDashboardData = [];
            this.selectAll = false;
            this.scheduledActionContent = '';
            this.isScheduled = false;
            this.ajaxParams = {};
        } catch (e) {
            SaErrorHandler.log('Exception occurred in reset:: ', e)
        }
    }

    SaCommonManager.prototype.refresh = function( dataParams ) {
        try{
            this.reset();
        } catch (e){
            SaErrorHandler.log('Exception occurred in refresh:: ', e)
        }
    }

    String.prototype.capitalize = function() {
        return this.charAt(0).toUpperCase() + this.slice(1);
    }
    window.SaCommonManager = SaCommonManager;
})(window);
