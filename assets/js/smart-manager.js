/**
 * Smart Manager JS class
 * Initialize and load data in grid
 * Public interface
 **/
var { __, _x, _n, _nx} = wp.i18n;
if(typeof sprintf === 'undefined' && wp.i18n.sprintf) { //Fix added for client
    var sprintf = wp.i18n.sprintf;
}
(function (window) {
    function SmartManager() {
		SaCommonManager.call(this);
		this.pluginKey = 'smart_manager';
		this.pluginSlug = 'sm';
	}
	SmartManager.prototype = Object.create(SaCommonManager.prototype);
    SmartManager.prototype.constructor = SmartManager;
	window.smart_manager = new SmartManager();
    SmartManager.prototype.init = function () {
		SaCommonManager.prototype.init.call(this); // Reuse base init
		this.commonManagerAjaxUrl = (ajaxurl.indexOf('?') !== -1)
			? ajaxurl + '&action=sa_sm_manager_include_file'
			: ajaxurl + '?action=sa_sm_manager_include_file';
		this.lang = sm_beta_params?.lang;
		this.ajaxParams = {}
		this.pluginKey = 'smart_manager';
		this.pluginSlug = 'sm';
		this.firstLoad = true
		this.currentDashboardModel='';
		this.dashboardKey = '';
		this.dashboardName = '';
		this.dashboard_select_options= '';
		this.saCommonNonce= '';
		this.column_names= new Array();
		this.advancedSearchQuery= new Array();
		this.simpleSearchText = '';
		this.advancedSearchRuleCount = 0;
		this.post_data_params = '';
		this.month_names_short = '';
		this.dashboardStates = {};
		this.current_selected_dashboard = '';
		this.currentDashboardData = [];
		this.currentVisibleColumns = new Array('');
		this.editedData = {};
		this.editedCellIds = [];
		this.selectedRows = [];
		this.duplicateStore = false;
		this.batch_update_action_options_default = '';
		this.batch_update_actions = {};
		this.sm_beta_smart_date_filter = '';
		this.addRecords_count = 0;
		this.defaultColumnsAddRow = new Array('posts_post_status', 'posts_post_title', 'posts_post_content');
		this.columnsVisibilityUsed = false; // flag for handling column visibility
		this.totalRecords = 0;
		this.displayTotalRecords = 0;
		this.loadedTotalRecords = 0;
		this.hotPlugin = {}; //object containing all Handsontable plugins
		this.gettingData = 0;
		this.searchType = sm_beta_params.search_type;
		this.advancedSearchContent = '';
		this.simpleSearchContent = '';
		this.searchTimeoutId = 0;
		this.columnSort = false;
		this.defaultEditor = true;
		this.currentGetDataParams = {};
		this.modifiedRows = [];
		this.dirtyRowColIds = {};
		this.wpToolsPanelWidth = 0;
		this.kpiData = {};
		this.defaultSortParams = { orderby: 'ID', order: 'DESC', default: true };
		this.isColumnModelUpdated = false
		this.state_apply = false;
		this.skip_default_action = false;
		this.search_count = 0;
		this.page = 1;
		this.multiselect_chkbox_list = '';
		this.limit = sm_beta_params.record_per_page;
		this.sm_dashboards_combo = '', // variable to store the dashboard name;
		this.columnNamesBatchUpdate= new Array(), // array for storing the batch update field;
		this.sm_store_table_model = new Array(), // array for storing store table mode;
		this.lastrow = '1';
		this.lastcell = '1';
		this.grid_width = '750';
		this.grid_height = '600';
		this.sm_qtags_btn_init = 1;
		this.sm_grid_nm = 'sm_editor_grid'; //name of div containing jqgrid
		this.sm_wp_editor_html = ''; //variable for storing the html of the wp editor
		this.sm_last_edited_row_id = '';
		this.sm_last_edited_col = '';
		this.colModelSearch = {};
		this.advancedSearchRoute = "advancedSearch";
		this.bulkEditRoute = "bulkEdit";
		this.columnManagerRoute = "columnManager";
		this.settingsRoute = "settings";
		this.privilegeSettingsRoute = "privilegeSettings";
		this.eligibleDashboardSavedSearch = '';
		this.loadingDashboardForsavedSearch = false;
		// Additional operators for advanced search for 'text' type cols
		this.proAdvancedSearchOperators = {
			'startsWith': _x('starts with', "select options - operator for 'text' data type fields", 'smart-manager-for-wp-e-commerce'),
			'endsWith': _x('ends with', "select options - operator for 'text' data type fields", 'smart-manager-for-wp-e-commerce'),
			'anyOf': _x('any of', "select options - operator for 'text' data type fields", 'smart-manager-for-wp-e-commerce'),
			'notStartsWith': _x('not starts with', "select options - operator for 'text' data type fields", 'smart-manager-for-wp-e-commerce'),
			'notEndsWith': _x('not ends with', "select options - operator for 'text' data type fields", 'smart-manager-for-wp-e-commerce'),
			'notAnyOf': _x('not any of', "select options - operator for 'text' data type fields", 'smart-manager-for-wp-e-commerce')
		}
		if(this.possibleOperators.text){
			this.possibleOperators.text = {...this.possibleOperators.text, ...this.proAdvancedSearchOperators}
		}
		this.savedSearch = []
		this.savedBulkEditConditions = []
		this.batch_background_process = sm_beta_params.batch_background_process;
		this.sm_success_msg = sm_beta_params.success_msg;
		this.background_process_name = sm_beta_params.background_process_name;
		this.sm_updated_successful = parseInt(sm_beta_params.updated_successful);
		this.sm_updated_msg = sm_beta_params.updated_msg;
		this.sm_dashboards = sm_beta_params.sm_dashboards;
		this.sm_views = (sm_beta_params.hasOwnProperty('sm_views')) ? JSON.parse(sm_beta_params.sm_views) : {};
		this.ownedViews = (sm_beta_params.hasOwnProperty('sm_owned_views')) ? JSON.parse(sm_beta_params.sm_owned_views) : [];
		this.publicViews = (sm_beta_params.hasOwnProperty('sm_public_views')) ? JSON.parse(sm_beta_params.sm_public_views) : []
		this.savedSearches = (sm_beta_params.hasOwnProperty('sm_saved_searches')) ? JSON.parse(sm_beta_params.sm_saved_searches) : []
		this.viewPostTypes = (sm_beta_params.hasOwnProperty('sm_view_post_types')) ? JSON.parse(sm_beta_params.sm_view_post_types) : {}
		this.recentDashboards = (sm_beta_params.hasOwnProperty('recent_dashboards')) ? JSON.parse(sm_beta_params.recent_dashboards) : [];
		this.recentViews = (sm_beta_params.hasOwnProperty('recent_views')) ? JSON.parse(sm_beta_params.recent_views) : [];
		this.recentDashboardType = (sm_beta_params.hasOwnProperty('recent_dashboard_type')) ? sm_beta_params.recent_dashboard_type : 'post_type';
		this.sm_dashboards_public = sm_beta_params.sm_dashboards_public;
		this.taxonomyDashboards = (sm_beta_params.hasOwnProperty('taxonomy_dashboards')) ? JSON.parse(sm_beta_params.taxonomy_dashboards) : {};
		this.allTaxonomyDashboards = (sm_beta_params.hasOwnProperty('all_taxonomy_dashboards')) ? JSON.parse(sm_beta_params.all_taxonomy_dashboards) : {};
		this.recentTaxonomyDashboards = (sm_beta_params.hasOwnProperty('recent_taxonomy_dashboards')) ? JSON.parse(sm_beta_params.recent_taxonomy_dashboards) : [];
		this.sm_lite_dashboards = sm_beta_params.lite_dashboards;
		this.sm_admin_email = sm_beta_params.sm_admin_email;
		this.sm_deleted_successful = parseInt(sm_beta_params.deleted_successful);
		this.trashEnabled = sm_beta_params.trashEnabled;
		this.clearSearchOnSwitch = true;
		this.sm_is_woo30 = sm_beta_params.SM_IS_WOO30;
		this.sm_id_woo22 = sm_beta_params.SM_IS_WOO22;
		this.sm_is_woo21 = sm_beta_params.SM_IS_WOO21;
		this.sm_beta_pro = sm_beta_params.SM_BETA_PRO;
		this.smAppAdminURL = sm_beta_params.SM_APP_ADMIN_URL;
		this.wooPriceDecimalPlaces = ( typeof sm_beta_params.woo_price_decimal_places != 'undefined' ) ? sm_beta_params.woo_price_decimal_places : 2;
		this.wooPriceDecimalSeparator = ( typeof sm_beta_params.woo_price_decimal_separator != 'undefined' ) ? sm_beta_params.woo_price_decimal_separator : '.';
		this.wpDbPrefix = sm_beta_params.wpdb_prefix;
		this.backgroundProcessRunningMessage = sm_beta_params.background_process_running_message
		this.trashAndDeletePermanently = sm_beta_params.trashAndDeletePermanently
		this.window_width = jQuery(window).width();
		this.window_height = jQuery(window).height();
		this.pricingPageURL = ((this.smAppAdminURL) ? this.smAppAdminURL : location.href) + '-pricing';
		this.month_names_short = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
		this.forceCollapseAdminMenu = (sm_beta_params.hasOwnProperty('forceCollapseAdminMenu')) ? parseInt(sm_beta_params.forceCollapseAdminMenu) : 0
		this.defaultImagePlaceholder = (sm_beta_params.hasOwnProperty('defaultImagePlaceholder')) ? sm_beta_params.defaultImagePlaceholder : ''
		this.rowHeight = (sm_beta_params.hasOwnProperty('rowHeight')) ? sm_beta_params.rowHeight : '50px'
		this.showTasksTitleModal = (sm_beta_params.hasOwnProperty('showTasksTitleModal')) ? parseInt(sm_beta_params.showTasksTitleModal) : 0
		this.useNumberFieldForNumericCols = (sm_beta_params.hasOwnProperty('useNumberFieldForNumericCols')) ? parseInt(sm_beta_params.useNumberFieldForNumericCols) : 0
		this.isViewContainSearchParams = false
		this.WCProductImportURL = (sm_beta_params.hasOwnProperty('WCProductImportURL')) ? sm_beta_params.WCProductImportURL : ''
		this.allSettings = (sm_beta_params.hasOwnProperty('allSettings')) ? sm_beta_params.allSettings : {}
		this.useDatePickerForDateTimeOrDateCols = (sm_beta_params.hasOwnProperty('useDatePickerForDateTimeOrDateCols')) ? parseInt(sm_beta_params.useDatePickerForDateTimeOrDateCols) : 0
		this.select2RulesDropdownParent = '#sa_manager_main aside';
		//Code for setting the default dashboard
		if( typeof this.sm_dashboards != 'undefined' && this.sm_dashboards != '' ) {
			this.sm_dashboards_combo = this.sm_dashboards = JSON.parse(this.sm_dashboards);
			this.sm_lite_dashboards = JSON.parse(this.sm_lite_dashboards);
			let defaultDashboardslug = (Array.isArray(this.recentDashboards) && this.recentDashboards.length > 0) ? this.recentDashboards[0] : '';
			this.dashboardName = (defaultDashboardslug) ? this.sm_dashboards[defaultDashboardslug] : ''
			if(this.sm_beta_pro == 1){
				if((this.recentDashboardType == 'view' || defaultDashboardslug == '') && this.recentViews.length > 0){
					defaultDashboardslug = (this.viewPostTypes.hasOwnProperty(this.recentViews[0])) ? this.recentViews[0] : '';
					this.dashboardName = (this.sm_views[defaultDashboardslug]) ? this.sm_views[defaultDashboardslug] : this.dashboardName
				}

				if((this.recentDashboardType == 'taxonomy' || defaultDashboardslug == '') && this.recentTaxonomyDashboards.length > 0){
					defaultDashboardslug = (this.recentTaxonomyDashboards[0]) ? this.recentTaxonomyDashboards[0] : defaultDashboardslug;
					this.dashboardName = (this.taxonomyDashboards[defaultDashboardslug]) ? this.taxonomyDashboards[defaultDashboardslug] : this.dashboardName
				}
			}
			this.current_selected_dashboard = defaultDashboardslug;
			this.dashboardKey = defaultDashboardslug;
			let savedSearch = (window.smart_manager && typeof window.smart_manager.findSavedSearchBySlug === "function") ? window.smart_manager.findSavedSearchBySlug(defaultDashboardslug) : false;
			if((parseInt(window.smart_manager.sm_beta_pro) === 1) && (savedSearch) && (savedSearch.hasOwnProperty('parent_post_type')) && (savedSearch.hasOwnProperty('params')) && (savedSearch.hasOwnProperty('title'))){
				this.current_selected_dashboard = savedSearch?.slug || '';
				this.dashboardKey = savedSearch?.parent_post_type || '';
				window.smart_manager.loadingDashboardForsavedSearch = true;
				window.smart_manager.advancedSearchQuery = savedSearch?.params?.search_params?.params || [];
				window.smart_manager.savedSearchParams = savedSearch?.params?.search_params || {};
				window.smart_manager.savedSearchDashboardKey = savedSearch?.parent_post_type || '';
				let child = window.smart_manager.findSelect2ParentOrChildByText(savedSearch.parent_post_type,true);
				window.smart_manager.savedSearchDashboardName = child?.childText || '';
			}
			this.saCommonNonce = this.sm_dashboards['sm_nonce'];
			delete this.sm_dashboards['sm_nonce'];
			this.sm_is_woo79 = (sm_beta_params.hasOwnProperty('SM_IS_WOO79')) ? sm_beta_params.SM_IS_WOO79 : '';
			this.modalVals = {}
			this.savedSearchConds = {}
			this.colEditDisableMessage = (sm_beta_params.colEditDisableMessage) ? sm_beta_params.colEditDisableMessage : []
			this.orderStatuses = sm_beta_params?.orderStatuses || []
			this.scheduledExportActionAdminUrl = sm_beta_params?.scheduled_export_actions_admin_url || ''
			this.isSubscriptionPluginActive = sm_beta_params?.isSubscriptionPluginActive || false
			this.subscriptionsExist = sm_beta_params?.subscriptionsExist || false
			this.subscriptionsAcceptManualRenewals = sm_beta_params?.subscriptionsAcceptManualRenewals || false
			this.isStripeGatewayActive = sm_beta_params?.isStripeGatewayActive || false
		}
		window.smart_manager.setDashboardDisplayName();
		this.loadMoreBtnHtml = "<button id='sm_editor_grid_load_items' style='height:2em;border: 1px solid #5850ec;background-color: white;border-radius: 3px;cursor: pointer;line-height: 17px;color: #5850ec;padding: 0 0.5em;'>"+ sprintf(
			/* translators: %s: dashboard display name */
			_x('Load More %s', 'bottom bar button', 'smart-manager-for-wp-e-commerce'), "<span>"+window.smart_manager.dashboardDisplayName+"</span>")+"</button>"
		this.container = document.getElementById('sm_editor_grid');
		this.body_font_size = jQuery("body").css('font-size');
		this.body_font_family = jQuery("body").css('font-family');
		this.editedAttribueSlugs = '';
		this.excludedEditedFieldKeys = [];
		this.processName = '';
		this.processContent = '';
		this.updatedTitle = '';
		this.updatedEditedData = {};
		this.selectedAllTasks = false;
		this.exportStore = '';
		this.isRefreshingLoadedPage = false;
		this.editedColumnTitles = {};
		this.isViewAuthor = false;
		this.searchSwitchClicked = false;
		this.columnsToBeExported = 'visible';
		this.recordSelectNotification = (this.sm_beta_pro == 1) ? true: false;
		this.stockCols = [];
		this.visibleCols = [];
		this.exportCSVActions = ['sm_export_selected_records', 'sm_export_entire_store'];
		this.scheduledActionContent = '';
		this.isScheduled = false;
		this.scheduledActionAdminUrl = (sm_beta_params.scheduled_action_admin_url) ? sm_beta_params.scheduled_action_admin_url : '';
		this.scheduledFor = '0000-00-00 00:00:00';
		this.accessPrivilegeSettings = {};
		this.isAdmin = (sm_beta_params.hasOwnProperty('is_admin')) ? sm_beta_params.is_admin : false
		this.sm_manage_scheduled_bulk_edits = '';
		this.taskId = 0;
		this.isCustomView = (window.smart_manager.getViewSlug(window.smart_manager.dashboardName)) ? true : false;
		this.userSwitchingDashboard = false;
		this.pluginSlug = 'sm';
		this.proConstName = 'SmartManagerPro';
		this.pluginParams = sm_beta_params;
		//Function to set all the states on unload
		window.onbeforeunload = function (evt) {
			if ( typeof (window.smart_manager.updateState) !== "undefined" && typeof (window.smart_manager.updateState) === "function" ) {
				window.smart_manager.updateState({'async': false}); //refreshing the dashboard states
			}
		}
		if ( !jQuery(document.body).hasClass('folded') && window.smart_manager.sm_beta_pro == 1 && window.smart_manager.forceCollapseAdminMenu == 1) {
			jQuery(document.body).addClass('folded');
		}
		let contentwidth = jQuery('#wpbody-content').width() - 20,
			contentheight = 910;
		let grid_height = contentheight - ( contentheight * 0.20 );
		window.smart_manager.grid_width = contentwidth - (contentwidth * 0.01);
		let heightDeduction = (window.smart_manager.sm_beta_pro == 1) ? 200 : 400;
		window.smart_manager.grid_height = ( grid_height < document.body.clientHeight - heightDeduction ) ? document.body.clientHeight - heightDeduction : grid_height;
		jQuery('#sm_editor_grid').trigger( 'smart_manager_init' ); //custom trigger
		window.smart_manager.loadDashboard();
		window.smart_manager.event_handler();
		window.smart_manager.loadNavBar();
		window.smart_manager.exportButtonHtml();
		//Code for setting rowHeight CSS variable
		let r = document.querySelector(':root');
		if(r){
			r.style.setProperty('--row-height', window.smart_manager.rowHeight);
		}
    };

	SmartManager.prototype.loadDashboard = function() {
		jQuery('#sm_editor_grid').trigger( 'smart_manager_pre_loadDashboard' ); //custom trigger

		window.smart_manager.page = 1;

		if( typeof(window.smart_manager.currentDashboardModel) == 'undefined' || window.smart_manager.currentDashboardModel == '' ) {
			window.smart_manager.column_names = new Array('');
			window.smart_manager.columnNamesBatchUpdate= new Array();

			var sm_dashboard_valid = 0;
			if( window.smart_manager.sm_beta_pro == 0 ) {
				sm_dashboard_valid = 0;
				if( window.smart_manager.sm_lite_dashboards.indexOf(window.smart_manager.dashboardKey) >= 0 ) {
					sm_dashboard_valid = 1;
				}
			} else {
				sm_dashboard_valid = 1;
			}

			if(typeof window.smart_manager.hot == 'undefined'){
				window.smart_manager.loadGrid();
			}

			if( sm_dashboard_valid == 1 ) {
				window.smart_manager.getDashboardModel();
				if(window.smart_manager.isCustomView === false && ( ! window.location.search.includes('show_edit_history'))){
					window.smart_manager.getData();
				}
			} else {
				jQuery("#sm_dashboard_select").val(window.smart_manager.current_selected_dashboard);
				window.smart_manager.notification = {message: sprintf(
					/* translators: %1$s: dashboard display name %2$s: success message %3$s: pricing page link */
					_x('For managing %1$s, %2$s %3$s version', 'modal content', 'smart-manager-for-wp-e-commerce'), window.smart_manager.dashboardDisplayName, window.smart_manager.sm_success_msg, '<a href="' + window.smart_manager.pricingPageURL + '" target="_blank">'+_x('Pro', 'modal content', 'smart-manager-for-wp-e-commerce')+'</a>'),hideDelay: window.smart_manager.notificationHideDelayInMs}
				window.smart_manager.showNotification()
			}
		} else {
			window.smart_manager.getData();
		}
		if('product'===window.smart_manager.dashboardKey || ( window.smart_manager.isCustomView && 'product'=== window.smart_manager.viewPostTypes[window.smart_manager.dashboardKey])){
			jQuery('.sm-ai-assistant-icon').show();
		} else{
			jQuery('.sm-ai-assistant-icon').hide();
		}
	}

	SmartManager.prototype.getDashboardModel = function () {
		SaCommonManager.prototype.getDashboardModel.call(this, false);
		if(!this.ajaxParams || (typeof this.ajaxParams !== 'object')){
			return;
		}
		// Ajax request to get the dashboard model.
		this.ajaxParams.data.is_public = (window.smart_manager.sm_dashboards_public.indexOf(window.smart_manager.dashboardKey) != -1) ? 1 : 0
		this.ajaxParams.active_module_title = window.smart_manager.dashboardName
		this.ajaxParams.hideLoader = false
		// Code for passing extra param for taxonomy & view handling.
		if (window.smart_manager.sm_beta_pro == 1) {
			let viewSlug = window.smart_manager.getViewSlug(window.smart_manager.dashboardName);
			this.ajaxParams.data['is_view'] = 0;
			if (viewSlug) {
				this.ajaxParams.data['is_view'] = 1;
				this.ajaxParams.data['active_view'] = viewSlug;
				this.ajaxParams.data['active_module'] = (window.smart_manager.viewPostTypes.hasOwnProperty(viewSlug)) ? window.smart_manager.viewPostTypes[viewSlug] : window.smart_manager.dashboardKey;
				window.smart_manager.refreshIsViewAuthor(viewSlug);
			}
			// Flag for handling taxonomy dashboards
			this.ajaxParams.data['is_taxonomy'] = window.smart_manager.isTaxonomyDashboard();
			// Code for passing extra param for handling tasks
			this.ajaxParams.data = ("undefined" !== typeof (window.smart_manager.addTasksParams) && "function" === typeof (window.smart_manager.addTasksParams) && 1 == window.smart_manager.sm_beta_pro) ? window.smart_manager.addTasksParams(this.ajaxParams.data) : this.ajaxParams.data;
		}
		this.sendRequest(this.ajaxParams, SaCommonManager.prototype.setDashboardModel.bind(this));
	}

	SmartManager.prototype.sendRequest = function (params, callback, callbackParams) {
		if(typeof params.showLoader == 'undefined' || (typeof params.showLoader != 'undefined' && params.showLoader !== false)){
			this.showLoader();
		}
		if(window.smart_manager.sm_beta_pro == 1){
			// Flag for handling taxonomy dashboards
			params.data['is_taxonomy'] = window.smart_manager.isTaxonomyDashboard();
		}
		SaCommonManager.prototype.sendRequest.call(this, params, callback, callbackParams);
	}

	SmartManager.prototype.formatGridColumns = function () {
		if (Array.isArray(window.smart_manager.currentVisibleColumns) && window.smart_manager.currentVisibleColumns.length > 0) {
			window.smart_manager.currentVisibleColumns.map((c, i) => {
				let colWidth = c.width || 0;
				let header_text = window.smart_manager.column_names[i],
					font = '30px Arial';
				// font = '26px ' + window.smart_manager.body_font_family;

				let newWidth = window.smart_manager.getTextWidth(header_text, font);

				if (newWidth > colWidth && !c.width) {
					c.width = (newWidth < 250) ? newWidth : 250;
				}
				c.width = Math.round(parseInt(c.width))
				window.smart_manager.currentVisibleColumns[i] = c
			})
		}
	}

	SmartManager.prototype.childSetDashboardModel = function (response) {
		if (typeof response != 'undefined' && response != '') {
			window.smart_manager.sm_store_table_model = response.tables;
			//Code for rendering the columns in grid
			window.smart_manager.formatGridColumns();
			if (window.smart_manager.hotPlugin.manualColumnResizePlugin) {
				window.smart_manager.hotPlugin.manualColumnResizePlugin.manualColumnWidths = []
			}
			window.smart_manager.hot.updateSettings({
				data: window.smart_manager.currentDashboardData,
				columns: window.smart_manager.currentVisibleColumns,
				colHeaders: window.smart_manager.column_names,
				forceRender: window.smart_manager.firstLoad
			})
			//Code for handling sort state management
			if (window.smart_manager.currentDashboardModel.hasOwnProperty('sort_params')) {
				if (window.smart_manager.currentDashboardModel.sort_params) {
					if (window.smart_manager.currentDashboardModel.sort_params.hasOwnProperty('default')) {
						window.smart_manager.hotPlugin.columnSortPlugin.sort();
					} else {
						if (window.smart_manager.currentVisibleColumns.length > 0) {
							for (let index in window.smart_manager.currentVisibleColumns) {
								if (window.smart_manager.currentVisibleColumns[index].src == window.smart_manager.currentDashboardModel.sort_params.column) {
									let sort_params = Object.assign({}, window.smart_manager.currentDashboardModel.sort_params);
									sort_params.column = parseInt(index);
									window.smart_manager.hotPlugin.columnSortPlugin.setSortConfig([sort_params]);
									break;
								}
							}
						}
					}
				}
			}
			if (window.smart_manager.firstLoad) {
				window.smart_manager.firstLoad = false
			}
			if (window.smart_manager.sm_beta_pro == 1) {
				if ((window.smart_manager.loadingDashboardForsavedSearch === true) && (window.smart_manager.hasOwnProperty('savedSearchParams') && window.smart_manager.savedSearchParams)) {//set search_params in response when applying saved search to any dashboard.
					response.search_params = window.smart_manager.savedSearchParams;
				}
				jQuery('#sm_custom_views_update, #sm_custom_views_delete').hide();
				let viewSlug = window.smart_manager.getViewSlug(window.smart_manager.dashboardName);
				if (viewSlug) {
					if (window.smart_manager.ownedViews.includes(viewSlug)) {
						jQuery('#sm_custom_views_update, #sm_custom_views_delete').show();
					}
				}
				if (response.hasOwnProperty('search_params')) {
					let searchType = 'simple';
					if (response.search_params.hasOwnProperty('isAdvanceSearch')) {
						if (response.search_params.isAdvanceSearch == 'true') {
							searchType = 'advanced'
						}
					}
					if (response.search_params.hasOwnProperty('params')) {
						if (viewSlug) {
							window.smart_manager.isCustomView = true;
						}
						if (searchType == 'simple') {
							window.smart_manager.simpleSearchText = response.search_params.params;
							window.smart_manager.advancedSearchQuery = new Array();
							jQuery('#search_switch').prop('checked', false);
						} else {
							window.smart_manager.simpleSearchText = '';
							window.smart_manager.advancedSearchQuery = response.search_params.params;
							// code to update the advanced seach rule count
							window.smart_manager.advancedSearchRuleCount = 0;
							if (("undefined" !== typeof (window.smart_manager.updateAdvancedSearchRuleCount)) && ("function" === typeof (window.smart_manager.updateAdvancedSearchRuleCount))) {
								window.smart_manager.updateAdvancedSearchRuleCount();
							}
							jQuery('#search_switch').prop('checked', true);
						}
					}
					window.smart_manager.clearSearchOnSwitch = false;
					window.smart_manager.searchType = (searchType == 'simple') ? 'advanced' : 'simple';
					let el = '#search_switch';
					jQuery(el).attr('switchSearchType', searchType);
					jQuery(el).trigger('change'); //Code to re-draw the search content based on search type
					if (searchType == 'simple') {
						jQuery('#sm_simple_search_box').val(window.smart_manager.simpleSearchText);
					}
					window.smart_manager.clearSearchOnSwitch = true;
				}
			}
			if (window.smart_manager.searchType != 'simple' && !response.hasOwnProperty('search_params')) {
				window.smart_manager.initialize_advanced_search(); //initialize advanced search control
			}
			if ("undefined" !== typeof (window.smart_manager.refreshColumnsTitleAttribute) && "function" === typeof (window.smart_manager.refreshColumnsTitleAttribute)) {
				window.smart_manager.refreshColumnsTitleAttribute();
			}
			window.smart_manager.exportButtonHtml();
			jQuery('#sm_editor_grid').trigger('smart_manager_post_load_grid'); //custom trigger
			if (window.smart_manager.isCustomView === true) {
				window.smart_manager.getData();
			}
			if (window.smart_manager.loadingDashboardForsavedSearch === true) {//reset the variables used to apply saved searches params.
				window.smart_manager.loadingDashboardForsavedSearch = false;
				window.smart_manager.savedSearchParams = {};
				window.smart_manager.savedSearchDashboardKey = '';
				window.smart_manager.savedSearchDashboardName = '';
			}
			window.smart_manager.isCustomView = false;
			window.smart_manager.userSwitchingDashboard = false;
		}
	}

	SmartManager.prototype.convert_to_slug = function (text) {
		return text
			.toLowerCase()
			.replace(/ /g, '-')
			.replace(/[^\w-]+/g, '');
	}

	SmartManager.prototype.convert_to_pretty_text = function (text) {
		return text
			.replace(/_/g, ' ')
			.split(' ')
			.map((s) => s.charAt(0).toUpperCase() + s.substring(1))
			.join(' ');
	}

	SmartManager.prototype.setDashboardDisplayName = function () {
		window.smart_manager.dashboardDisplayName = window.smart_manager.dashboardName;
		let viewSlug = window.smart_manager.getViewSlug(window.smart_manager.dashboardName);
		if (viewSlug) {
			window.smart_manager.dashboardDisplayName = (window.smart_manager.sm_dashboards[window.smart_manager.viewPostTypes[viewSlug]]) ? window.smart_manager.sm_dashboards[window.smart_manager.viewPostTypes[viewSlug]] : 'records';
			window.smart_manager.dashboardDisplayName = (window.smart_manager.dashboardDisplayName === 'records' && window.smart_manager.allTaxonomyDashboards[window.smart_manager.viewPostTypes[viewSlug]]) ? window.smart_manager.allTaxonomyDashboards[window.smart_manager.viewPostTypes[viewSlug]] : window.smart_manager.dashboardDisplayName;
		}
		("undefined" !== typeof (window.smart_manager.changeDashboardDisplayName) && "function" === typeof (window.smart_manager.changeDashboardDisplayName)) ? window.smart_manager.changeDashboardDisplayName(window.smart_manager.dashboardDisplayName) : '';
	}

	// Function to create optgroups for dashboards
	SmartManager.prototype.createOptGroups = function (args = {}) {

		if (Object.keys(args).length == 0) {
			return;
		}

		if (!args.parent || !args.child || !args.label) {
			return
		}

		let parent = (!Array.isArray(args.parent)) ? Object.keys(args.parent) : args.parent,
			child = (!Array.isArray(args.child)) ? Object.keys(args.child) : args.child,
			options = '',
			count = 0;

		// Create the navbarComboboxSelect2 object
		let parentId = args.label.toLowerCase().replace(/\s+/g, '_');
		let dashboardSelect2Item = {
			id: parentId,
			text: args.label,
			children: []
		};
		child.map((item) => {
			let key, label;
			if (typeof item === 'string') {
				key = item;
				label = args['is_recently_accessed'] ? args.parent[key] : args.child[key];
			} else if (typeof item === 'object' && item.slug) {
				key = item.slug;
				label = item.title;
			}
			if (((parent.includes(key) || ((typeof item === 'object'))) && args['is_recently_accessed']) || (!parent.includes(key) && !args['is_recently_accessed']) || args['isParentChildSame']) {
				count++;
				options += `<option value="${key}" ${(((key === window.smart_manager.dashboardKey) && window.smart_manager.loadingDashboardForsavedSearch === false) || ((key === window.smart_manager.current_selected_dashboard) && window.smart_manager.loadingDashboardForsavedSearch === true)) ? "selected" : ""}>${label}</option>`;
				dashboardSelect2Item.children.push({
					id: key,
					text: label,
				});
			}
		});

		if (!window.smart_manager.dashboardSelect2Items || (typeof window.smart_manager.dashboardSelect2Items === 'undefined')) {
			window.smart_manager.dashboardSelect2Items = [];
		}
		//Push combox item only if it not exist
		if (!window.smart_manager.dashboardSelect2Items.some(item => item.id === dashboardSelect2Item.id)) {
			window.smart_manager.dashboardSelect2Items.push(dashboardSelect2Item);
		}

		window.smart_manager.dashboard_select_options += (options != '') ? '<optgroup id="' + parentId + '" label="' + args.label + ' (' + count + ')">' + options + '</optgroup>' : '';
	}

	// Function to load top right bar on the page
	SmartManager.prototype.loadNavBar = function () {
		//Code for simple & advanced search
		let selected = '',
			switchSearchType = (window.smart_manager.searchType == 'simple') ? _x('Advanced', 'search type', 'smart-manager-for-wp-e-commerce') : _x('Simple', 'search type', 'smart-manager-for-wp-e-commerce');

		window.smart_manager.simpleSearchContent = "<input type='text' id='sm_simple_search_box' placeholder='" + _x('Type to search...', 'placeholder', 'smart-manager-for-wp-e-commerce') + "'value='" + window.smart_manager.simpleSearchText + "'>";
		window.smart_manager.advancedSearchContent = '<div id="sm_advanced_search" title="' + _x('Click to add/edit condition', 'tooltip', 'smart-manager-for-wp-e-commerce') + '">' +
			'<div id="sm_advanced_search_content">' + sprintf(
				/* translators: %1$d: Advanced search rule count %2$s: search conditions */
				_x('%1$d condition%2$s', 'search conditions', 'smart-manager-for-wp-e-commerce'), window.smart_manager.advancedSearchRuleCount, ((window.smart_manager.advancedSearchRuleCount > 1) ? _x('s', 'search conditions', 'smart-manager-for-wp-e-commerce') : '')) + '</div>' +
			'<div id="sm_advanced_search_icon">' +
			'<span class="dashicons dashicons-edit-large"></span>' +
			'</div>' +
			'</div>';

		//Code for dashboards select2
		window.smart_manager.dashboard_select_options = '';
		if (window.smart_manager.sm_beta_pro == 1) {

			let recentDashboards = (!Array.isArray(window.smart_manager.recentDashboards)) ? window.smart_manager.recentDashboards.values() : window.smart_manager.recentDashboards,
				recentTaxonomyDashboards = (!Array.isArray(window.smart_manager.recentTaxonomyDashboards)) ? window.smart_manager.recentTaxonomyDashboards.values() : window.smart_manager.recentTaxonomyDashboards;

			// Code for rendering recently accessed dashboards
			if (recentDashboards.length > 0) {
				window.smart_manager.createOptGroups({
					'parent': window.smart_manager.sm_dashboards,
					'child': recentDashboards,
					'label': _x('Common post types', 'dashboard option groups', 'smart-manager-for-wp-e-commerce'),
					'is_recently_accessed': true
				});
			}

			// Code for rendering recently accessed taxonomy dashboards
			if (recentTaxonomyDashboards.length > 0) {
				window.smart_manager.createOptGroups({
					'parent': window.smart_manager.taxonomyDashboards,
					'child': recentTaxonomyDashboards,
					'label': _x('Common taxonomies', 'dashboard option groups', 'smart-manager-for-wp-e-commerce'),
					'is_recently_accessed': true
				});
			}

			// Code for rendering recently accessed views
			if (window.smart_manager.recentViews.length > 0 && Object.keys(window.smart_manager.sm_views).length > 0) {
				window.smart_manager.createOptGroups({
					'parent': window.smart_manager.sm_views,
					'child': window.smart_manager.recentViews,
					'label': _x('Recently used views', 'dashboard option groups', 'smart-manager-for-wp-e-commerce'),
					'is_recently_accessed': true
				});
			}

			// Code for rendering all remaining dashboards
			if (Object.keys(window.smart_manager.sm_dashboards).length > 0) {
				window.smart_manager.createOptGroups({
					'parent': recentDashboards,
					'child': window.smart_manager.sm_dashboards,
					'label': _x('Other post types', 'dashboard option groups', 'smart-manager-for-wp-e-commerce'),
					'is_recently_accessed': false
				});
			}

			// Code for rendering all remaining taxonomy dashboards
			if (Object.keys(window.smart_manager.taxonomyDashboards).length > 0) {
				window.smart_manager.createOptGroups({
					'parent': recentTaxonomyDashboards,
					'child': window.smart_manager.taxonomyDashboards,
					'label': _x('Other taxonomies', 'dashboard option groups', 'smart-manager-for-wp-e-commerce'),
					'is_recently_accessed': false
				});
			}

			// Code for rendering all remaining views
			if (Object.keys(window.smart_manager.sm_views).length > 0) {
				let otherSavedViews = Object.entries(window.smart_manager.sm_views)
					.filter(([key]) => (!window.smart_manager.recentViews.includes(key) && !window.smart_manager.findSavedSearchBySlug(key)))
					.reduce((acc, [key, value]) => {
						acc[key] = value;
						return acc;
					}, {})
				window.smart_manager.createOptGroups({
					'parent': window.smart_manager.sm_views,
					'child': otherSavedViews,
					'label': _x('Other saved views', 'dashboard option groups', 'smart-manager-for-wp-e-commerce'),
					'is_recently_accessed': true
				});
			}

			// Code for rendering all Saved searches.
			if (Object.keys(window.smart_manager.savedSearches).length > 0) {
				window.smart_manager.createOptGroups({
					'parent': window.smart_manager.savedSearches,
					'child': window.smart_manager.savedSearches,
					'label': _x('Saved searches', 'saved searches option groups', 'smart-manager-for-wp-e-commerce'),
					'is_recently_accessed': true //show in recent access + show in Saved searches section.
				});
			}

			// Code to change the dashboard key to view post type
			let viewSlug = window.smart_manager.getViewSlug(window.smart_manager.dashboardName);
			if (viewSlug) {
				window.smart_manager.dashboardKey = window.smart_manager.viewPostTypes[viewSlug];
			}
			window.smart_manager.sm_manage_scheduled_bulk_edits = '<div class="sm_beta_dropdown_content">' + '<a href="" class="sm_new_bulk_edits" target="_blank">' +
				_x("New", "button for creating new bulk edit", "smart-manager-for-wp-e-commerce") +
				'</a>' +
				'<a href="' + window.smart_manager.scheduledActionAdminUrl + '" class="sm_scheduled_bulk_edits" target="_blank">' +
				_x("Manage Scheduled edits", "manage button for scheduled bulk edit actions", "smart-manager-for-wp-e-commerce") +
				'</a>' +
				'</div>';
		} else {
			if (Object.keys(window.smart_manager.sm_dashboards).length > 0) {
				window.smart_manager.createOptGroups({
					'parent': window.smart_manager.sm_dashboards,
					'child': window.smart_manager.sm_dashboards,
					'label': _x('All post types', 'dashboard option groups', 'smart-manager-for-wp-e-commerce'),
					'is_recently_accessed': false,
					'isParentChildSame': true
				});
			}

			if (Object.keys(window.smart_manager.taxonomyDashboards).length > 0) {
				window.smart_manager.createOptGroups({
					'parent': window.smart_manager.taxonomyDashboards,
					'child': window.smart_manager.taxonomyDashboards,
					'label': _x('All taxonomies', 'dashboard option groups', 'smart-manager-for-wp-e-commerce'),
					'is_recently_accessed': false,
					'isParentChildSame': true
				});
			}
		}

		let navBar = "<select id='sm_dashboard_select'> </select>" +
			"<div id='sm_nav_bar_search'>" +
			"<div id='sm_search_content_parent'>" +
			"<div id='search_content' style='width:98%;'>" +
			((window.smart_manager.searchType == 'simple') ? window.smart_manager.simpleSearchContent : window.smart_manager.advancedSearchContent) +
			"</div>" +
			"</div>" +
			"<div id='sm_top_bar_advanced_search'>" +
			( ( parseInt(window.smart_manager.sm_beta_pro) === 1 && 'product' === window.smart_manager.dashboardKey) ?  
			`<div class="sm-ai-assistant-icon" title="${_x( 'AI-powered search', 'AI icon label', 'smart-manager-for-wp-e-commerce' )}" context="search"><span><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#ffe658ff" class="size-6" width="25" height="25">
			<path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z"></path>
			</svg></span></div>` : '') +
			"<div id='search_switch_container'> <input type='checkbox' id='search_switch' switchSearchType='" + switchSearchType.toLowerCase() + "' /><label title='" + sprintf(
				/* translators: %s: search type */
				_x('Switch to %s', 'tooltip', 'smart-manager-for-wp-e-commerce'), switchSearchType) + "' for='search_switch'> " + sprintf(
					/* translators: %s: search type */
					_x('%s Search', 'search type', 'smart-manager-for-wp-e-commerce'), switchSearchType) + "</label></div>" +
			// "<div id='search_switch_lbl'> "+ sprintf(_x('%s Search', 'search type', 'smart-manager-for-wp-e-commerce'), String(switchSearchType).capitalize())+"</div>"+
			"</div>" +
			"</div>";

		jQuery('#sm_nav_bar .sm_beta_left').append(navBar);
		jQuery('#sm_dashboard_select').empty().append(window.smart_manager.dashboard_select_options);
		jQuery('#sm_dashboard_select').select2({
			width: '20em',
			dropdownCssClass: 'sm_beta_dashboard_select',
			dropdownParent: jQuery('#sm_nav_bar'),
			templateResult: function (data) {
				if (data.element && data.element.tagName === 'OPTGROUP') {
					return jQuery(`<span id="${data.element.id}" class="select2-group-text">${data.text}</span><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>`)
				}
				return data.text;
			},
		});

		jQuery('#sm_nav_bar #sm_nav_bar_right').append(`<div class="sm_nav_bar_links">
					<div>
						<a href="admin.php?page=smart-manager&landing-page=sm-faqs" class="sm_docs_settings_link" target="_blank" title="${_x('Docs', 'tooltip', 'smart-manager-for-wp-e-commerce')}">
							<svg stroke="currentColor" fill="none" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
							</svg>
						</a>
					</div>
					<div id="sm_nav_bar_settings_btn" style="cursor:pointer;" class="sm_beta_dropdown sm_docs_settings_link">
						<svg stroke="currentColor" fill="none" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
						</svg>
						<div class="sm_beta_dropdown_content settings">
							<a id="sm_general_settings" href="#" title="${_x('General settings', 'tooltip', 'smart-manager-for-wp-e-commerce')}">${_x('General settings', 'settings button', 'smart-manager-for-wp-e-commerce')}</a>
							<a id="sm_access_privilege_settings" href="#" title="${_x('Access Privilege settings', 'tooltip', 'smart-manager-for-wp-e-commerce')}">${_x('Access Privilege Settings', 'access privilege settings button', 'smart-manager-for-wp-e-commerce')}</a>
						</div>
					</div>
				</div>`);
		let sm_top_bar = '<div id="sm_top_bar" style="font-weight:400 !important;width:max-content;">' +
			'<div id="sm_top_bar_left" class="sm_beta_left" style="width:' + window.smart_manager.grid_width + 'px;background-color: white;padding: 0.5em 0em 1em 0em;">' +
			'<div class="sm_top_bar_action_btns">' +
			'<div id="batch_update_sm_editor_grid" title="' + _x('Bulk Edit', 'tooltip', 'smart-manager-for-wp-e-commerce') + '" class="sm_beta_dropdown">' +
			'<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' +
			'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>' +
			'</svg>' +
			'<span>' + _x('Bulk Edit', 'button', 'smart-manager-for-wp-e-commerce') + '</span>' + window.smart_manager.sm_manage_scheduled_bulk_edits +
			'</div>' +
			'</div>' +
			'<div class="sm_top_bar_action_btns">' +
			'<div id="save_sm_editor_grid_btn" title="' + _x('Save', 'tooltip', 'smart-manager-for-wp-e-commerce') + '">' +
			'<svg class="sm-ui-state-disabled" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' +
			'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>' +
			'</svg>' +
			'<span>' + _x('Save', 'button', 'smart-manager-for-wp-e-commerce') + '</span>' +
			'</div>' +
			'<div id="add_sm_editor_grid" title="' + _x('Add Row', 'tooltip', 'smart-manager-for-wp-e-commerce') + '">' +
			'<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' +
			'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>' +
			'</svg>' +
			'<span>' + _x('Add Row', 'button', 'smart-manager-for-wp-e-commerce') + '</span>' +
			'</div>' +
			'<div id="dup_sm_editor_grid" class="sm_beta_dropdown">' +
			'<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' +
			'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>' +
			'</svg>' +
			'<span title="' + _x('Duplicate', 'tooltip', 'smart-manager-for-wp-e-commerce') + '">' + _x('Duplicate', 'button', 'smart-manager-for-wp-e-commerce') + '</span>' +
			'<div class="sm_beta_dropdown_content">' +
			'<a id="sm_beta_dup_selected" href="#">' + _x('Selected Records', 'duplicate button', 'smart-manager-for-wp-e-commerce') + '</a>' +
			'<a id="sm_beta_dup_entire_store" class="sm_entire_store" href="#">' + _x('Entire Store', 'duplicate button', 'smart-manager-for-wp-e-commerce') + '</a>' +
			'</div>' +
			'</div>' +
			'<div id="del_sm_editor_grid" class="sm_beta_dropdown">' +
			'<svg class="sm-error-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' +
			'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>' +
			'</svg>' +
			'<span title="' + _x('Delete', 'tooltip', 'smart-manager-for-wp-e-commerce') + '">' + _x('Delete', 'button', 'smart-manager-for-wp-e-commerce') + '</span>' +
			'<div class="sm_beta_dropdown_content">' +
			'<a id="sm_beta_move_to_trash" href="#">' + _x('Move to Trash', 'delete button', 'smart-manager-for-wp-e-commerce') + '</a>' +
			'<a id="sm_beta_delete_permanently" href="#" class="sm-error-icon">' + _x('Delete Permanently', 'delete button', 'smart-manager-for-wp-e-commerce') + '</a>' +
			'</div>' +
			'</div>' +
			'</div>' +
			'<div class="sm_top_bar_action_btns">' +
			'<div id="sm_custom_views" class="sm_beta_dropdown">' +
			'<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' +
			'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14v6m-3-3h6M6 10h2a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2zm10 0h2a2 2 0 002-2V6a2 2 0 00-2-2h-2a2 2 0 00-2 2v2a2 2 0 002 2zM6 20h2a2 2 0 002-2v-2a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2z"></path>' +
			'</svg>' +
			'<span title="' + _x("Custom Views", 'tooltip', 'smart-manager-for-wp-e-commerce') + '">' + _x('Custom Views', 'button', 'smart-manager-for-wp-e-commerce') + '</span>' +
			'<div class="sm_beta_dropdown_content">' +
			'<a id="sm_custom_views_create" href="#">' + _x('Create', 'custom view button', 'smart-manager-for-wp-e-commerce') + '</a>' +
			'<a id="sm_custom_views_update" href="#">' + _x('Update', 'custom view button', 'smart-manager-for-wp-e-commerce') + '</a>' +
			'<a id="sm_custom_views_delete" href="#" class="sm-error-icon">' + _x('Delete', 'custom view button', 'smart-manager-for-wp-e-commerce') + '</a>' +
			'</div>' +
			'</div>' +
			'<div id="export_csv_sm_editor_grid" class="sm_beta_dropdown">' +
			'<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' +
			'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />' +
			'</svg>' +
			'<span>' + _x('Export CSV', 'button', 'smart-manager-for-wp-e-commerce') + '</span>' +
			'<div class="sm_beta_dropdown_content" id="sm_export_csv">' +
			'</div>' +
			'</div>' +
			'</div>' +
			'<div class="sm_top_bar_action_btns">' +
			'<div id="print_invoice_sm_editor_grid_btn" title="' + _x('Print Invoice', 'tooltip', 'smart-manager-for-wp-e-commerce') + '">' +
			'<svg class="sm-ui-state-disabled" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' +
			'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>' +
			'</svg>' +
			'<span>' + _x('Print Invoice', 'button', 'smart-manager-for-wp-e-commerce') + '</span>' +
			'</div>' +
			'</div>' +
			'<div class="sm_top_bar_action_btns">' +
			'<div id="show_hide_cols_sm_editor_grid" title="' + _x('Show / Hide Columns', 'tooltip', 'smart-manager-for-wp-e-commerce') + '">' +
			'<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">' +
			'<path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />' +
			'</svg>' +
			'<span>' + _x('Columns', 'button', 'smart-manager-for-wp-e-commerce') + '</span>' +
			'</div>' +
			'<div id="sm_show_tasks_container"><label id="sm_show_tasks_lbl"> <input type="checkbox" name="sm_show_tasks" id="sm_show_tasks" value="sm_show_tasks">' + _x("Show Tasks", "checkbox for displaying current dashboard tasks", "smart-manager-for-wp-e-commerce") + '</label></div>' +
			'</div>' +
			'</div>' +
			'</div>';

		let sm_bottom_bar = "<div id='sm_bottom_bar' style='font-weight:500 !important;color:#5850ec;width:" + window.smart_manager.grid_width + "px;'>" +
			"<div id='sm_bottom_bar_left' class='sm_beta_left'></div>" +
			"<div id='sm_bottom_bar_right' class='sm_beta_right'>" +
			"<div id='sm_beta_load_more_records' class='sm_beta_right' style='cursor: pointer;' title='" + _x('Load more records', 'tooltip', 'smart-manager-for-wp-e-commerce') + "'>" + window.smart_manager.loadMoreBtnHtml + "</div>" +
			"<div id='sm_beta_display_records' class='sm_beta_select_blue sm_beta_right'></div>" +
			"</div>" +
			"</div>";

		let sm_msg = jQuery('.sm_design_notice').prop('outerHTML');
		if (sm_msg) {
			jQuery(sm_msg).insertAfter("#sm_nav_bar");
			jQuery('.wrap > .sm_design_notice').show()
		}

		jQuery(sm_top_bar).insertBefore("#sm_editor_grid");
		jQuery(sm_bottom_bar).insertAfter("#sm_editor_grid");

		if (window.smart_manager.dashboardKey == 'shop_order') {
			jQuery('#print_invoice_sm_editor_grid_btn').show();
		} else {
			jQuery('#print_invoice_sm_editor_grid_btn').hide();
		}

		(window.smart_manager.isTaxonomyDashboard()) ? jQuery('#sm_beta_move_to_trash').hide() : jQuery('#sm_beta_move_to_trash').show();
		document.getElementById('sm_access_privilege_settings').style.display = (window.smart_manager.isAdmin) ? 'block' : 'none';
		window.smart_manager.displayShowHideColumnSettings(true);
		if ('undefined' !== typeof (window.smart_manager.displayTasks) && 'function' === typeof (window.smart_manager.displayTasks)) {
			window.smart_manager.displayTasks({ hideTasks: true }); // Hide tasks for custom view dashboard
		}
		//Code for Dashboard KPI
		jQuery('#sm_dashboard_kpi').remove();

		if (window.smart_manager.searchType != 'simple') {
			window.smart_manager.initialize_advanced_search(); //initialize advanced search control
		}

		jQuery('#sm_top_bar').trigger('sm_top_bar_loaded');
		window.smart_manager.toggleTopBar();
	}
	
	SmartManager.prototype.initialize_advanced_search = function () {
		if (typeof (window.smart_manager.currentColModel) == 'undefined') {
			return;
		}
		jQuery('#sm_advanced_search_content').html(sprintf(
			/* translators: %1$d: Advanced search rule count %2$s: search conditions */
			_x('%1$d condition%2$s', 'search conditions', 'smart-manager-for-wp-e-commerce'), window.smart_manager.advancedSearchRuleCount, ((window.smart_manager.advancedSearchRuleCount > 1) ? _x('s', 'search conditions', 'smart-manager-for-wp-e-commerce') : '')))
	}

	SmartManager.prototype.getViewSlug = function (title = '') {
		return Object.keys(window.smart_manager.sm_views).find(key => window.smart_manager.sm_views[key] === title);
	}
	//Function to check if 'Tasks' is enabled or not
	SmartManager.prototype.isTasksEnabled = function () {
		return (jQuery("#sm_show_tasks").is(":checked") || (window.location.search.includes('show_edit_history'))) ? 1 : 0;
	}
	SmartManager.prototype.displayShowHideColumnSettings = function (isShow = true) {
		(isShow) ? jQuery('#show_hide_cols_sm_editor_grid').show() : jQuery('#show_hide_cols_sm_editor_grid').hide();
	}

	SmartManager.prototype.set_data = function (response) {
		if (typeof response != 'undefined' && response != '') {
			let res = {};

			if (response != 'null' && window.smart_manager.isJSON(response)) {
				res = JSON.parse(response);

				if (res && res.hasOwnProperty('meta')) {
					window.smart_manager.isViewContainSearchParams = (res.meta.hasOwnProperty('is_view_contain_search_params') && (true === res.meta.is_view_contain_search_params || 'true' === res.meta.is_view_contain_search_params)) ? true : false;
				}

				window.smart_manager.totalRecords = parseInt(res.total_count);
				window.smart_manager.displayTotalRecords = (res.hasOwnProperty('display_total_count')) ? res.display_total_count : res.total_count;

				// re-initialize the loadedTotalRecords
				if (window.smart_manager.page == 1) {
					window.smart_manager.loadedTotalRecords = 0
				}

				if (!window.smart_manager.isRefreshingLoadedPage) {
					let loadedRecordCount = (res.hasOwnProperty('loaded_total_count')) ? parseInt(res.loaded_total_count) : res.items.length
					window.smart_manager.loadedTotalRecords += loadedRecordCount
				}

				if (window.smart_manager.page > 1) {
					window.smart_manager.showLoader(false);
					let lastRowIndex = window.smart_manager.currentDashboardData.length;

					let idsIndex = {};
					let idKey = window.smart_manager.getKeyID()
					window.smart_manager.currentDashboardData.map((obj, key) => {
						let id = (obj[idKey]) ? obj[idKey] : ''
						if (id != '') {
							idsIndex[id] = key
						}
					})
					//if no matchingids then replace else push/concat
					if (Object.keys(idsIndex).length > 0) {
						res.items.map((data, key) => {
							let id = (data[idKey]) ? data[idKey] : ''
							if (0 <= idsIndex[id]) {
								window.smart_manager.currentDashboardData[idsIndex[id]] = data;
							} else {
								window.smart_manager.currentDashboardData.push(data)
							}
						})
					} else {
						window.smart_manager.currentDashboardData = window.smart_manager.currentDashboardData.concat(res.items)
					}
					window.smart_manager.hot.forceFullRender = false

					window.smart_manager.hot.loadData(window.smart_manager.currentDashboardData, false);

					if (window.smart_manager.sm_beta_pro == 0) {
						if (typeof (window.smart_manager.modifiedRows) != 'undefined') {
							if (window.smart_manager.modifiedRows.length >= window.smart_manager.sm_updated_successful) {
								//call to function for highlighting selected row ids
								if (typeof (window.smart_manager.disableSelectedRows) !== "undefined" && typeof (window.smart_manager.disableSelectedRows) === "function") {
									window.smart_manager.disableSelectedRows(true);
								}
							}
						}
					}
				} else {
					window.smart_manager.currentDashboardData = (window.smart_manager.totalRecords > 0) ? res.items : [];
					if (document.getElementById('sm_export_entire_store') !== null) {
						document.getElementById('sm_export_entire_store').innerHTML = (window.smart_manager.isFilteredData()) ? _x('All Items In Search Results', 'export button', 'smart-manager-for-wp-e-commerce') : _x('Entire Store', 'export button', 'smart-manager-for-wp-e-commerce');
					}
					window.smart_manager.setExportButtonHTML();
					document.getElementById('sm_beta_dup_entire_store').innerHTML = (window.smart_manager.isFilteredData()) ? _x('All Items In Search Results', 'duplicate button', 'smart-manager-for-wp-e-commerce') : _x('Entire Store', 'duplicate button', 'smart-manager-for-wp-e-commerce')

				}
			} else {
				window.smart_manager.currentDashboardData = [];
			}

			if (window.smart_manager.page == 1) {
				if (window.smart_manager.columnSort) {
					window.smart_manager.hot.loadData(window.smart_manager.currentDashboardData);
					window.smart_manager.hot.scrollViewportTo(0, 0);
				} else {

					jQuery('#sm_dashboard_kpi').remove();

					if (res.hasOwnProperty('kpi_data')) {
						window.smart_manager.kpiData = res.kpi_data;
						if (Object.entries(window.smart_manager.kpiData).length > 0) {
							let kpi_html = new Array();

							Object.entries(window.smart_manager.kpiData).forEach(([kpiTitle, kpiObj]) => {
								kpi_html.push('<span class="sm_beta_select_' + ((kpiObj.hasOwnProperty('color') !== false && kpiObj['color'] != '') ? kpiObj['color'] : 'grey') + '"> ' + kpiTitle + '(' + ((kpiObj.hasOwnProperty('count') !== false) ? kpiObj['count'] : 0) + ') </span>');
							});

							if (kpi_html.length > 0) {
								jQuery('#sm_bottom_bar_left').append('<div id="sm_dashboard_kpi">' + kpi_html.join("<span class='sm_separator'> | </span>") + '</div>');
							}
						}
					} else {
						window.smart_manager.kpiData = {};
					}

					if (window.smart_manager.currentVisibleColumns.length > 0) {
						if (window.smart_manager.isColumnModelUpdated) {
							window.smart_manager.formatGridColumns();

							window.smart_manager.hot.updateSettings({
								data: window.smart_manager.currentDashboardData,
								columns: window.smart_manager.currentVisibleColumns,
								colHeaders: window.smart_manager.column_names,
								// forceRender: window.smart_manager.firstLoad
							})
						} else {
							window.smart_manager.hot.updateSettings({
								data: window.smart_manager.currentDashboardData,
								forceRender: window.smart_manager.firstLoad
							})
						}
						if (window.smart_manager.firstLoad) {
							window.smart_manager.firstLoad = false
						}
					}
					window.smart_manager.showLoader(false);
				}
			}

			window.smart_manager.refreshBottomBar();

			if (window.smart_manager.totalRecords == 0) {
				jQuery('#sm_editor_grid_load_items').attr('disabled', 'disabled');
				jQuery('#sm_editor_grid_load_items').addClass('sm-ui-state-disabled');

				jQuery('#sm_bottom_bar_right #sm_beta_display_records').hide();
				jQuery('#sm_bottom_bar_right #sm_beta_load_more_records').text(sprintf(
					/* translators: %s: dashboard display name */
					_x('No %s Found', 'bottom bar status', 'smart-manager-for-wp-e-commerce'), window.smart_manager.dashboardDisplayName));
			} else {
				if (window.smart_manager.currentDashboardData.length >= window.smart_manager.totalRecords) {
					jQuery('#sm_editor_grid_load_items').attr('disabled', 'disabled');
					jQuery('#sm_editor_grid_load_items').addClass('sm-ui-state-disabled');

					jQuery('#sm_bottom_bar_right #sm_beta_display_records').hide();
					jQuery('#sm_bottom_bar_right #sm_beta_load_more_records').text(sprintf(
						/* translators: %1$d: number of display records %2$s: dashboard display name */
						_x('%1$d %2$s loaded', 'bottom bar status', 'smart-manager-for-wp-e-commerce'), window.smart_manager.displayTotalRecords, window.smart_manager.dashboardDisplayName));

				} else {
					jQuery('#sm_bottom_bar_right #sm_beta_display_records').show();
					jQuery('#sm_editor_grid_load_items').removeAttr('disabled');
					jQuery('#sm_editor_grid_load_items').removeClass('sm-ui-state-disabled');
					jQuery('#sm_bottom_bar_right #sm_beta_load_more_records').html(window.smart_manager.loadMoreBtnHtml);
					jQuery('#sm_bottom_bar_right #sm_editor_grid_load_items span').html(window.smart_manager.dashboardDisplayName);
				}
				jQuery('#sm_bottom_bar_right').show();
			}

			window.smart_manager.gettingData = 0;
		}
	}

	//Function to refresh the bottom bar of grid
	SmartManager.prototype.refreshBottomBar = function () {
		let msg = (window.smart_manager.currentDashboardData.length > 0) ? sprintf(
			/* translators: %1$d: number of total records %2$d: number of display records %3$s: dashboard display name */
			_x('%1$d of %2$d %3$s loaded', 'bottom bar status', 'smart-manager-for-wp-e-commerce'), window.smart_manager.loadedTotalRecords, window.smart_manager.displayTotalRecords, window.smart_manager.dashboardDisplayName) : sprintf(
				/* translators: %s: dashboard display name */
				_x('No %s Found', 'bottom bar status', 'smart-manager-for-wp-e-commerce'), window.smart_manager.dashboardDisplayName);
		jQuery('#sm_bottom_bar_right #sm_beta_display_records').html(msg);
	}

	SmartManager.prototype.getDataDefaultParams = function (params) {
		let defaultParams = {};
		defaultParams.data = {
			cmd: 'get_data_model',
			active_module: window.smart_manager.dashboardKey,
			security: window.smart_manager.saCommonNonce,
			is_public: (window.smart_manager.sm_dashboards_public.indexOf(window.smart_manager.dashboardKey) != -1) ? 1 : 0,
			sm_page: window.smart_manager.page,
			sm_limit: window.smart_manager.limit,
			SM_IS_WOO30: window.smart_manager.sm_is_woo30,
			sort_params: (window.smart_manager.currentDashboardModel.hasOwnProperty('sort_params')) ? window.smart_manager.currentDashboardModel.sort_params : '',
			table_model: (window.smart_manager.currentDashboardModel.hasOwnProperty('tables')) ? window.smart_manager.currentDashboardModel.tables : '',
			search_text: (window.smart_manager.searchType == 'simple') ? window.smart_manager.simpleSearchText : '',
			advanced_search_query: JSON.stringify((window.smart_manager.searchType != 'simple' || window.smart_manager.loadingDashboardForsavedSearch === true) ? window.smart_manager.advancedSearchQuery : [])
		};

		// Code for passing extra param for view handling
		if (window.smart_manager.sm_beta_pro == 1) {
			let viewSlug = window.smart_manager.getViewSlug(window.smart_manager.dashboardName);
			defaultParams.data['is_view'] = 0;

			if (viewSlug) {
				defaultParams.data['is_view'] = 1;
				defaultParams.data['active_view'] = viewSlug;
				defaultParams.data['active_module'] = (window.smart_manager.viewPostTypes.hasOwnProperty(viewSlug)) ? window.smart_manager.viewPostTypes[viewSlug] : window.smart_manager.dashboardKey;
			}
			// Code for passing extra param for handling tasks
			defaultParams.data = ("undefined" !== typeof (window.smart_manager.addTasksParams) && "function" === typeof (window.smart_manager.addTasksParams) && 1 == window.smart_manager.sm_beta_pro) ? window.smart_manager.addTasksParams(defaultParams.data) : defaultParams.data;
		}

		if (typeof params != 'undefined') {
			if (Object.getOwnPropertyNames(params).length > 0) {
				let paramsData = (params.hasOwnProperty('data')) ? params.data : {}
				if (Object.getOwnPropertyNames(paramsData).length > 0) {
					defaultParams = Object.assign(paramsData, defaultParams.data);
				}
				defaultParams = Object.assign(params, defaultParams);
			}
		}

		window.smart_manager.currentGetDataParams = defaultParams;
	}

	SmartManager.prototype.getData = function (params = {}) {
		window.smart_manager.gettingData = 1;
		window.smart_manager.isRefreshingLoadedPage = false;

		if (window.smart_manager.page == 1) {
			if (typeof (window.smart_manager.getDataDefaultParams) !== "undefined" && typeof (window.smart_manager.getDataDefaultParams) === "function") {
				window.smart_manager.getDataDefaultParams(params);
				window.smart_manager.currentGetDataParams.hideLoader = false
			}
		} else {
			if (typeof (window.smart_manager.currentGetDataParams.data) != 'undefined' && typeof (window.smart_manager.currentGetDataParams.data.sm_page) != 'undefined') {

				if (params.hasOwnProperty('refreshPage')) {
					window.smart_manager.isRefreshingLoadedPage = true;
					window.smart_manager.currentGetDataParams.data.sm_page = params.refreshPage;
					window.smart_manager.currentGetDataParams.async = false;
				} else {
					window.smart_manager.currentGetDataParams.data.sm_page = window.smart_manager.page;
				}
				window.smart_manager.currentGetDataParams.data.sort_params = window.smart_manager.currentDashboardModel.sort_params;
			}
		}
		window.smart_manager.sendRequest(window.smart_manager.currentGetDataParams, window.smart_manager.set_data);
	}

	SmartManager.prototype.inline_edit_dlg = function (params) {
		if (params.dlg_width == '' || typeof (params.dlg_width) == 'undefined') {
			modal_width = 350;
		} else {
			modal_width = params.dlg_width;
		}

		if (params.dlg_height == '' || typeof (params.dlg_height) == 'undefined') {
			modal_height = 390;
		} else {
			modal_height = params.dlg_height;
		}

		let ok_btn = [{
			text: _x("OK", 'button', 'smart-manager-for-wp-e-commerce'),
			class: 'sm_inline_dialog_ok sm-dlg-btn-yes',
			click: function () {
				jQuery(this).dialog("close");
			}
		}];

		jQuery("#sm_inline_dialog").html(params.content);

		let dialog_params = {
			closeOnEscape: true,
			draggable: false,
			dialogClass: 'sm_ui_dialog_class',
			height: modal_height,
			width: modal_width,
			modal: (params.hasOwnProperty('modal')) ? params.modal : false,
			position: {
				my: (params.hasOwnProperty('position_my')) ? params.position_my : 'left center+250px',
				at: (params.hasOwnProperty('position_my')) ? params.position_at : 'left center',
				of: params.target
			},
			create: function (event, ui) {
				if (!(params.hasOwnProperty('title') && params.title != '')) {
					jQuery(".ui-widget-header").hide();
				}
			},
			open: function () {

				if (params.hasOwnProperty('show_close_icon') && params.show_close_icon === false) {
					jQuery(this).find('.ui-dialog-titlebar-close').hide();
				}

				jQuery('.ui-widget-overlay').bind('click', function () {
					jQuery('#sm_inline_dialog').dialog('close');
				});

				if (!(params.hasOwnProperty('title') && params.title != '')) {
					jQuery(".ui-widget-header").hide();
				} else if ((params.hasOwnProperty('title') && params.title != '')) {
					jQuery(".ui-widget-header").show();
				}

				if (params.hasOwnProperty('customDataAttributes')) {
					Object.entries(params.customDataAttributes).forEach(([key, value]) => {
						jQuery(this).attr(key, value);
					});
				}

				jQuery(this).html(params.content);
			},
			close: function (event, ui) {
				jQuery(this).dialog('close');
			},
			buttons: (params.hasOwnProperty('display_buttons') && params.display_buttons === false) ? [] : (params.hasOwnProperty('buttons_model') ? params.buttons_model : ok_btn)
		}

		if (params.hasOwnProperty('title')) {
			dialog_params.title = params.title;
		}

		if (params.hasOwnProperty('titleIsHtml')) {
			dialog_params.titleIsHtml = params.titleIsHtml;
		}

		jQuery("#sm_inline_dialog").dialog(dialog_params);
		jQuery('.sm_ui_dialog_class, .ui-widget-overlay').show();
	}

	SmartManager.prototype.getTextWidth = function (text, font) {
		// re-use canvas object for better performance
		let canvas = window.smart_manager.getTextWidthCanvas || (window.smart_manager.getTextWidthCanvas = document.createElement("canvas"));
		let context = canvas.getContext("2d");
		context.font = font;
		let metrics = context.measureText(text);
		return metrics.width;
	}

	SmartManager.prototype.enableDisableButtons = function () {
		//enabling the action buttons
		if (window.smart_manager.selectedRows.length > 0 || window.smart_manager.selectAll) {
			if (jQuery('.sm_top_bar_action_btns #del_sm_editor_grid svg').hasClass('sm-ui-state-disabled')) {
				jQuery('.sm_top_bar_action_btns #del_sm_editor_grid svg').removeClass('sm-ui-state-disabled');
			}

			if (jQuery('.sm_top_bar_action_btns #print_invoice_sm_editor_grid_btn svg').hasClass('sm-ui-state-disabled')) {
				jQuery('.sm_top_bar_action_btns #print_invoice_sm_editor_grid_btn svg').removeClass('sm-ui-state-disabled');
			}

		} else {
			if (!jQuery('.sm_top_bar_action_btns #del_sm_editor_grid svg').hasClass('sm-ui-state-disabled')) {
				jQuery('.sm_top_bar_action_btns #del_sm_editor_grid svg').addClass('sm-ui-state-disabled');
			}

			if (!jQuery('.sm_top_bar_action_btns #print_invoice_sm_editor_grid_btn svg').hasClass('sm-ui-state-disabled')) {
				jQuery('.sm_top_bar_action_btns #print_invoice_sm_editor_grid_btn svg').addClass('sm-ui-state-disabled');
			}
		}
	}

	SmartManager.prototype.disableSelectedRows = function (readonly) {
		for (let i = 0; i < window.smart_manager.hot.countRows(); i++) {
			if (window.smart_manager.modifiedRows.indexOf(i) != -1) {
				continue;
			}
			for (let j = 0; j < window.smart_manager.hot.countCols(); j++) {
				window.smart_manager.hot.setCellMeta(i, j, 'readOnly', readonly);
			}
		}
	}

	//Function to highlight the edited cells
	SmartManager.prototype.highlightEditedCells = function () {

		if (typeof window.smart_manager.dirtyRowColIds == 'undefined' || Object.getOwnPropertyNames(window.smart_manager.dirtyRowColIds).length == 0) {
			return;
		}

		for (let row in window.smart_manager.dirtyRowColIds) {

			window.smart_manager.dirtyRowColIds[row].forEach(function (colIndex) {

				cellProp = window.smart_manager.hot.getCellMeta(row, colIndex);
				prevClassName = cellProp.className;

				if (prevClassName == '' || typeof prevClassName == 'undefined' || (typeof (prevClassName) != 'undefined' && prevClassName.indexOf('sm-grid-dirty-cell') == -1)) {
					window.smart_manager.hot.setCellMeta(row, colIndex, 'className', (prevClassName + ' ' + 'sm-grid-dirty-cell'));
					jQuery('.smCheckboxColumnModel input[data-row=' + row + ']').parents('tr').removeClass('sm_edited').addClass('sm_edited');
				}
			});
		}
	}

	SmartManager.prototype.isHTML = RegExp.prototype.test.bind(/(<([^>]+)>)/i);

	SmartManager.prototype.isJSON = function (str) {
		try {
			return (JSON.parse(str) && !!str);
		} catch (e) {
			return false;
		}
	}

	SmartManager.prototype.getCustomRenderer = function (col) {

		let customRenderer = '';

		let colObj = window.smart_manager.currentVisibleColumns[col];

		if (typeof (colObj) != 'undefined') {

			let renderer = (colObj.hasOwnProperty('renderer')) ? colObj.renderer : '';

			if (colObj.hasOwnProperty('type')) {
				if (colObj.type == 'numeric') {
					customRenderer = 'numericRenderer';
				} else if (colObj.type == 'text' && renderer != 'html') {
					customRenderer = 'customTextRenderer';
				} else if (colObj.type == 'html' || renderer == 'html') {
					customRenderer = 'customHtmlRenderer';
				} else if (colObj.type == 'checkbox') {
					// customRenderer = 'customCheckboxRenderer';
				} else if (colObj.type == 'password') {
					customRenderer = 'customPasswordRenderer';
				}
			}
		}

		return customRenderer;
	}

	SmartManager.prototype.generateImageGalleryDlgHtml = function (imageObj) {
		let html = '';

		if (typeof (imageObj) !== "undefined") {
			Object.entries(imageObj).forEach(([id, imageUrl]) => {
				html += '<div class="sm_beta_left sm_gallery_image">' +
					'<img data-id="' + imageUrl.id + '" src="' + imageUrl.val + '" width="150" height="150"></img>' +
					'<div style="text-align:center;"> <span class="dashicons dashicons-trash sm_beta_select_red sm_gallery_image_delete" title="' + _x('Remove gallery image', 'tooltip', 'smart-manager-for-wp-e-commerce') + '"> </div>' +
					'</div>';

			});
		}

		return html;
	}

	SmartManager.prototype.handleMediaUpdate = function (params) {

		let file_frame;

		// If the media frame already exists, reopen it.
		if (file_frame) {
			file_frame.open();
			return;
		}

		let allowMultiple = (params.hasOwnProperty('allowMultiple')) ? params.allowMultiple : false;

		// Code for attaching media to the posts
		wp.media.model.settings.post.id = 0
		if ('posts_id' === window.smart_manager.getKeyID() && params.hasOwnProperty('row_data_id')) {
			wp.media.model.settings.post.id = params.row_data_id
		}

		// Create the media frame.
		file_frame = wp.media.frames.file_frame = wp.media({
			title: (params.hasOwnProperty('uploaderTitle')) ? params.uploaderTitle : jQuery(this).data('uploader_title'),
			button: {
				text: (params.hasOwnProperty('uploader_button_text')) ? params.uploaderButtonText : jQuery(this).data('uploader_button_text'),
			},
			library: {
				type: 'image'
			},
			multiple: allowMultiple  // Set to true to allow multiple files to be selected
		});

		if (params.hasOwnProperty('callback')) {
			file_frame.on('select', function () {

				let attachments = (allowMultiple) ? file_frame.state().get('selection').toJSON() : file_frame.state().get('selection').first().toJSON();
				params.callback(attachments)
			});
		}

		file_frame.open();

	}

	SmartManager.prototype.inlineUpdateMultipleImages = function (galleryImages) {
		if (!galleryImages || !((Object.keys(galleryImages)).every(galleryImage => galleryImages.hasOwnProperty(galleryImage)))) {
			return;
		}
		let params = {};
		params.data = {
			cmd: 'inline_update',
			active_module: window.smart_manager.dashboardKey,
			edited_data: JSON.stringify({ [galleryImages.id]: { [galleryImages.src]: galleryImages.values } }),
			security: window.smart_manager.saCommonNonce,
			pro: ('undefined' !== typeof (window.smart_manager.sm_beta_pro)) ? window.smart_manager.sm_beta_pro : 0,
			table_model: (window.smart_manager.currentDashboardModel.hasOwnProperty('tables')) ? window.smart_manager.currentDashboardModel.tables : ''
		};
		params.data = ("undefined" !== typeof (window.smart_manager.addTasksParams) && "function" === typeof (window.smart_manager.addTasksParams) && 1 == window.smart_manager.sm_beta_pro) ? window.smart_manager.addTasksParams(params.data) : params.data;
		window.smart_manager.sendRequest(params, function (response) {
			if (galleryImages.hasOwnProperty('rowNo')) {
				window.smart_manager.getData({ refreshPage: (Math.ceil((parseInt(galleryImages.rowNo) / window.smart_manager.limit))) });
				window.smart_manager.hot.render();
			} else {
				window.smart_manager.refresh();
			}

			if (1 == window.smart_manager.sm_beta_pro && 'undefined' !== typeof (window.smart_manager.displayGalleryImagesModal) && 'function' === typeof (window.smart_manager.displayGalleryImagesModal)) {
				window.smart_manager.displayGalleryImagesModal({ id: galleryImages.id, src: galleryImages.src, imageGalleryHtml: galleryImages.imageGalleryHtml, rowNo: galleryImages.rowNo || 0 })
			}
		});
	};

	SmartManager.prototype.showImagePreview = function (params) {
		let xOffset = 150,
			yOffset = 30;

		if (jQuery('#sm_img_preview').length == 0) {
			jQuery("body").append("<div id='sm_img_preview' style='z-index:100199;'><div style='margin: 1em; padding: 1em; border-radius: 0.1em; border: 0.1em solid #ece0e0;'><img src='" + params.current_cell_value + "' width='300' /></div><div id='sm_img_preview_text'>" + params.title + "</div></div>");
		}

		jQuery("#sm_img_preview")
			.css("top", (params.event.pageY - xOffset) + "px")
			.css("left", (params.event.pageX + yOffset) + "px")
			.fadeIn("fast")
			.show();
	}

	SmartManager.prototype.loadGrid = function () {
		jQuery('#sm_editor_grid').html('');
		window.smart_manager.formatGridColumns();
		window.smart_manager.hot = new Handsontable(window.smart_manager.container, {
			data: window.smart_manager.currentDashboardData,
			height: window.smart_manager.grid_height,
			width: window.smart_manager.grid_width,
			//   allowEmpty: true, // default is true
			rowHeaders: function (index) {
				return '<input type="checkbox" />';
			}, // for row headings (like numbering)
			colHeaders: true, // for col headings
			//   renderAllRows: true,
			//   viewportRowRenderingOffset: 100, // -- problem no. of rows outside the visible part of table. Default: auto
			stretchH: 'all', // strech
			autoColumnSize: { useHeaders: true },
			//   wordWrap: true, //default is true
			//   autoRowSize: false, // by default its undefined which is also same
			rowHeights: window.smart_manager.rowHeight,
			colWidths: 100,
			bindRowsWithHeaders: true,
			manualColumnResize: true,
			//   manualRowResize: true,
			manualColumnMove: false,
			columnSorting: true,
			//   columnSorting: { sortEmptyCells: false }, //--problem
			//   fillHandle: 'vertical', //for excel like filling of cells
			fillHandle: { //for excel like filling of cells
				direction: 'vertical',
				autoInsertRow: false // For restricting to add new rows automatically when dragging the cells
			},
			persistentState: true,
			customBorders: true,
			//   disableVisualSelection: true,
			columns: window.smart_manager.currentVisibleColumns,
			colHeaders: window.smart_manager.column_names,
		});

		window.smart_manager.hotPlugin.columnSortPlugin = window.smart_manager.hot.getPlugin('columnSorting');
		window.smart_manager.hotPlugin.manualColumnResizePlugin = window.smart_manager.hot.getPlugin('manualColumnResize')

		window.smart_manager.hot.updateSettings({

			cells: function (row, col, prop) {

				let customRenderer = window.smart_manager.getCustomRenderer(col);
				if (customRenderer != '') {
					let cellProperties = {};
					cellProperties.renderer = customRenderer;
					return cellProperties;
				}
			},

			afterOnCellMouseOver: function (e, coords, td) {
				if (coords.row < 0 || coords.col < 0) {
					return;
				}

				let col = this.getCellMeta(coords.row, coords.col),
					current_cell_value = this.getDataAtCell(coords.row, coords.col);
				if (typeof (col.type) != 'undefined' && current_cell_value) {
					if (col.type == 'sm.image') {
						let row_title = '';
						if (window.smart_manager.dashboardKey == 'product') {
							row_title = this.getDataAtRowProp(coords.row, 'posts_post_title');
							row_title = (window.smart_manager.isHTML(row_title) == true) ? jQuery(row_title).text() : row_title;
							row_title = row_title;
						}
						let params = {
							'current_cell_value': current_cell_value,
							'event': e,
							'title': row_title
						};

						if (typeof (window.smart_manager.showImagePreview) !== "undefined" && typeof (window.smart_manager.showImagePreview) === "function") {
							window.smart_manager.showImagePreview(params);
						}
					}
				}
			},

			afterOnCellMouseOut: function (e, coords, td) {
				if (jQuery('#sm_img_preview').length > 0) {
					jQuery('#sm_img_preview').remove();
				}
			},

			afterRender: function (isForced) { //TODO: check
				if (isForced === true) {
					window.smart_manager.showLoader(false);
				}
			},

			beforeColumnSort: function (currentSortConfig, destinationSortConfigs) {
				window.smart_manager.hotPlugin.columnSortPlugin.setSortConfig(destinationSortConfigs);
				if (typeof (destinationSortConfigs) != 'undefined') {
					if (destinationSortConfigs.length > 0) {
						if (destinationSortConfigs[0].hasOwnProperty('column')) {
							if (window.smart_manager.currentVisibleColumns.length > 0) {
								let colObj = window.smart_manager.currentVisibleColumns[destinationSortConfigs[0].column];

								window.smart_manager.currentDashboardModel.sort_params = {
									'column': colObj.src,
									'sortOrder': destinationSortConfigs[0].sortOrder
								};

								window.smart_manager.columnSort = true;
							}
						}
					} else {
						if (window.smart_manager.currentDashboardModel.hasOwnProperty('sort_params')) {
							window.smart_manager.currentDashboardModel.sort_params = Object.assign({}, window.smart_manager.defaultSortParams);
						}
						window.smart_manager.columnSort = false;
					}

					window.smart_manager.page = 1;
					if ( !window.smart_manager.userSwitchingDashboard && ( (window.smart_manager.columnSort === true) || (destinationSortConfigs.length === 0 && currentSortConfig.length > 0) )) {
						window.smart_manager.getData();
					}
				}
				return false; // The blockade for the default sort action.
			},

			afterCreateRow: function (row, amount) {

				while (amount > 0) {
					// setTimeout( function() { //added for handling dirty class for edited cells

					let idKey = window.smart_manager.getKeyID();
					let row_data_id = window.smart_manager.hot.getDataAtRowProp(row, idKey);

					if (typeof (row_data_id) != 'undefined' && row_data_id) {
						return;
					}

					window.smart_manager.addRecords_count++;
					window.smart_manager.hot.setDataAtRowProp(row, idKey, 'sm_temp_' + window.smart_manager.addRecords_count);

					let val = '',
						colObj = {};

					for (let key in window.smart_manager.currentColModel) {

						colObj = window.smart_manager.currentColModel[key];

						if (colObj.hasOwnProperty('data')) {
							if (jQuery.inArray(colObj.data, window.smart_manager.defaultColumnsAddRow) >= 0) {

								if (typeof colObj.defaultValue != 'undefined') {
									val = colObj.defaultValue;
								} else {
									if (typeof colObj.selectOptions != 'undefined') {
										val = Object.keys(colObj.selectOptions)[0]
									} else {
										val = 'test';
									}
								}

								window.smart_manager.hot.setDataAtRowProp(row, colObj.data, val);
							}
						}
					}
					// }, 1 );
					row++;
					amount--;
				}
			},

			afterChange: function (changes, source) {

				if (window.smart_manager.selectAll === true || window.smart_manager.isRefreshingLoadedPage || changes === null) {
					return;
				}

				let col = {},
					cellProp = {},
					colIndex = '',
					idKey = window.smart_manager.getKeyID(),
					colTypesDisabledHiglight = new Array('sm.image');

				changes.forEach(([row, prop, oldValue, newValue]) => {
					if ((row < 0 && prop == 0) || (oldValue == newValue && String(oldValue).length == String(newValue).length)) {
						return;
					}

					if (window.smart_manager.modifiedRows.indexOf(row) == -1) {
						// To prevent updating more than 3 records by dragging the columns.
						if((parseInt(window.smart_manager.sm_beta_pro) !== 1) && (parseInt(window.smart_manager.modifiedRows.length) >= parseInt(window.smart_manager.sm_updated_successful))) {
							return;
						}
						window.smart_manager.modifiedRows.push(row);
					}

					colIndex = window.smart_manager.hot.propToCol(prop);
					if (typeof (colIndex) == 'number') {
						col = window.smart_manager.hot.getCellMeta(row, colIndex);
					}
					let id = window.smart_manager.hot.getDataAtRowProp(row, idKey);

					if ((oldValue != newValue || String(oldValue).length != String(newValue).length) && prop != idKey && colTypesDisabledHiglight.indexOf(col.type) == -1) { //for inline edit
						cellProp = window.smart_manager.hot.getCellMeta(row, prop);
						prevClassName = (typeof (cellProp.className) != 'undefined') ? cellProp.className : '';

						//dirty cells variable
						if (window.smart_manager.dirtyRowColIds.hasOwnProperty(row) === false) {
							window.smart_manager.dirtyRowColIds[row] = new Array();
						}

						if (window.smart_manager.dirtyRowColIds[row].indexOf(colIndex) == -1) {
							window.smart_manager.dirtyRowColIds[row].push(colIndex);
						}
						window.smart_manager.showSavePrompt();

						if (jQuery('.sm_top_bar_action_btns #save_sm_editor_grid_btn svg').hasClass('sm-ui-state-disabled')) {
							jQuery('.sm_top_bar_action_btns #save_sm_editor_grid_btn svg').removeClass('sm-ui-state-disabled');
						}

						if (prevClassName == '' || (typeof (prevClassName) != 'undefined' && prevClassName.indexOf('sm-grid-dirty-cell') == -1)) {

							//creating the edited json string

							if (window.smart_manager.editedData.hasOwnProperty(id) === false) {
								window.smart_manager.editedData[id] = {};
							}

							if (Object.entries(col).length === 0) {
								if (typeof (window.smart_manager.currentColModel) != 'undefined') {
									window.smart_manager.currentColModel.forEach(function (value) {
										if (value.hasOwnProperty('data') && value.data == prop) {
											col.src = value.src;
										}
									});
								}
							}
							window.smart_manager.editedData[id][col.src] = (window.smart_manager.editedAttribueSlugs && (false === (window.smart_manager.excludedEditedFieldKeys).includes(col.src))) ? window.smart_manager.editedAttribueSlugs : newValue;
							window.smart_manager.editedCellIds.push({ 'row': row, 'col': colIndex });
						}

						if (window.smart_manager.sm_beta_pro == 0) {
							if (typeof (window.smart_manager.modifiedRows) != 'undefined') {
								if (window.smart_manager.modifiedRows.length >= window.smart_manager.sm_updated_successful) {
									//call to function for highlighting selected row ids
									if (typeof (window.smart_manager.disableSelectedRows) !== "undefined" && typeof (window.smart_manager.disableSelectedRows) === "function") {
										window.smart_manager.disableSelectedRows(true);
									}
								}
							}
						}
					}
				});

				//call to function for highlighting edited cell ids
				if (typeof (window.smart_manager.highlightEditedCells) !== "undefined" && typeof (window.smart_manager.highlightEditedCells) === "function") {
					window.smart_manager.highlightEditedCells();
				}

				window.smart_manager.hot.render();
			},

			afterOnCellMouseUp: function (e, coords, td) {
				if ((!coords) || (coords && (coords.row === -1) && (coords.col !== -1))) {
					return;
				}
				window.smart_manager.editedAttribueSlugs = '';
				window.smart_manager.selectAll = false

				//Code for having checkbox column selection
				if (coords.col === -1) {

					//code for handling header checkbox selection
					if (window.smart_manager.hot) {
						if (window.smart_manager.hot.selection) {
							if (window.smart_manager.hot.selection.highlight) {
								if (window.smart_manager.hot.selection.highlight.selectAll) {
									window.smart_manager.selectAll = true
								}
								if (window.smart_manager.hot.selection.highlight.selectedRows) {
									window.smart_manager.selectedRows = window.smart_manager.hot.selection.highlight.selectedRows
								}
							}
						}
					}

					if (typeof (window.smart_manager.enableDisableButtons) !== "undefined" && typeof (window.smart_manager.enableDisableButtons) === "function") {
						window.smart_manager.enableDisableButtons();
					}
					return;
				}

				let col = this.getCellMeta(coords.row, coords.col);
				if (typeof (col.readOnly) != 'undefined' && col.readOnly == 'true') {
					return;
				}

				let id_key = window.smart_manager.getKeyID(),
					row_data_id = this.getDataAtRowProp(coords.row, id_key),
					current_cell_value = this.getDataAtCell(coords.row, coords.col),
					params = {
						'coords': coords,
						'td': td,
						'colObj': col,
						'row_data_id': row_data_id,
						'current_cell_value': current_cell_value
					};

				window.smart_manager.defaultEditor = true;
				jQuery('#sm_editor_grid').trigger('sm_grid_on_afterOnCellMouseUp', [params]);
				if (window.smart_manager.hasOwnProperty('defaultEditor') && window.smart_manager.defaultEditor === false) {
					return;
				}

				if (typeof (col.type) != 'undefined' && col.type == 'sm.multipleImage') { // code to handle the functionality to handle editing of 'image' data types
					let galleryImages = current_cell_value,
						imageGalleryHtml = `<div class="sm_gallery_image_parent" data-id="${row_data_id}" data-col="${col.src || ''}" data-row= "${coords.row || 0}">`;

					if (Object.keys(galleryImages).length > 0) {
						if (typeof (window.smart_manager.generateImageGalleryDlgHtml) !== "undefined" && typeof (window.smart_manager.generateImageGalleryDlgHtml) === "function") {
							imageGalleryHtml += window.smart_manager.generateImageGalleryDlgHtml(galleryImages);
						}
					}

					imageGalleryHtml += '</div>';

					if (Object.entries(col).length === 0) {
						if (typeof (window.smart_manager.currentColModel) != 'undefined') {
							window.smart_manager.currentColModel.forEach(function (value) {
								if (value.hasOwnProperty('data') && value.data == col.prop) {
									col.src = value.src;
								}
							});
						}
					}
					if ('undefined' !== typeof (window.smart_manager.displayGalleryImagesModal) && 'function' === typeof (window.smart_manager.displayGalleryImagesModal) && row_data_id && col.src && imageGalleryHtml) {
						window.smart_manager.displayGalleryImagesModal({ id: row_data_id, src: col.src, imageGalleryHtml: imageGalleryHtml, rowNo: coords.row });
					}
				}

				if (typeof (col.type) != 'undefined' && col.type == 'sm.image' && coords.row >= 0) { // code to handle the functionality to handle editing of 'image' data types

					if (typeof (window.smart_manager.handleMediaUpdate) !== "undefined" && typeof (window.smart_manager.handleMediaUpdate) === "function") {

						let params = { row_data_id: row_data_id };

						// When an image is selected, run a callback.
						params.callback = function (attachment) {
							if (typeof (attachment) == 'undefined') {
								return;
							}
							let params = {};
							params = {
								editedImage: JSON.stringify({ [row_data_id]: { [col.src]: attachment['id'] } }),
								row: coords.row,
								col: coords.col,
								value: attachment['url'],
								source: 'image_inline_update'
							}
							let editedColumnName = '';
							if ("undefined" !== typeof (window.smart_manager.getColDisplayName) && "function" === typeof (window.smart_manager.getColDisplayName)) {
								editedColumnName = window.smart_manager.getColDisplayName(col.src);
							}

							if (1 == window.smart_manager.sm_beta_pro) {
								window.smart_manager.processName = _x('Inline Edit', 'process name', 'smart-manager-for-wp-e-commerce');
								window.smart_manager.processContent = (editedColumnName) ? editedColumnName : col.src;
								if ("undefined" !== typeof (window.smart_manager.inlineUpdateImage) && "function" === typeof (window.smart_manager.inlineUpdateImage)) {
									window.smart_manager.processCallback = window.smart_manager.inlineUpdateImage;
								}
								window.smart_manager.processCallbackParams = params;
								if ("undefined" !== typeof (window.smart_manager.showTitleModal) && "function" === typeof (window.smart_manager.showTitleModal)) {
									window.smart_manager.showTitleModal()
								}
							} else {
								if ("undefined" !== typeof (window.smart_manager.inlineUpdateImage) && "function" === typeof (window.smart_manager.inlineUpdateImage) && params) {
									window.smart_manager.inlineUpdateImage(params);
								}
							}

						};
						window.smart_manager.handleMediaUpdate(params);
					}
				}

				if (typeof (col.type) != 'undefined' && col.type == 'sm.longstring') {

					if (typeof (wp.editor.getDefaultSettings) == 'undefined') {
						return;
					}

					let unformatted_val = current_cell_value; //Code for unformatting the 'longstring' type values
					let initializeWPEditor = function () {
						wp.editor.remove('sm_beta_lonstring_input');
						wp.editor.initialize('sm_beta_lonstring_input', {
							tinymce: {
								height: 200,
								wpautop: true,
								plugins: 'charmap colorpicker compat3x directionality fullscreen hr image lists media paste tabfocus textcolor wordpress wpautoresize wpdialogs wpeditimage wpemoji wpgallery wplink wptextpattern wpview',
								toolbar1: 'formatselect bold,italic,strikethrough,|,bullist,numlist,blockquote,|,justifyleft,justifycenter,justifyright,|,link,unlink,wp_more,|,spellchecker,fullscreen,wp_adv',
								toolbar2: 'underline,justifyfull,forecolor,|,pastetext,pasteword,removeformat,|,media,charmap,|,outdent,indent,|,undo,redo,wp_help'
							},
							quicktags: { buttons: 'strong,em,link,block,del,img,ul,ol,li,code,more,spell,close,fullscreen' },
							mediaButtons: true
						});
					}
					window.smart_manager.modal = {
						title: col.key || '',
						content: '<textarea style="width:100%;height:100%;z-index:100;" id="sm_beta_lonstring_input">' + unformatted_val + '</textarea>',
						autoHide: false,
						cta: {
							title: _x('Ok', 'button', 'smart-manager-for-wp-e-commerce'),
							callback: function () {
								let content = wp.editor.getContent('sm_beta_lonstring_input');
								window.smart_manager.hot.setDataAtCell(coords.row, coords.col, content, 'sm.longstring_inline_update');
								wp.editor.remove('sm_beta_lonstring_input');
							}
						},
						onCreate: initializeWPEditor,
						onUpdate: initializeWPEditor
					}
					window.smart_manager.showModal()
				}

				if (col.editor == 'sm.serialized') { //Code for handling serialized complex data handling

					window.smart_manager.JSONEditorObj = {} // hold JSONEditor instance

					let initializeJSONEditor = function () {
						let container = document.getElementById("sm_beta_json_editor");
						jQuery(container).html('');
						let options = {
							"mode": 'tree',
							"search": true
						};
						window.smart_manager.JSONEditorObj = new JSONEditor(container, options);
						let val = (window.smart_manager.isJSON(current_cell_value)) ? JSON.parse(current_cell_value) : current_cell_value;

						if (col.editor_schema && window.smart_manager.isJSON(col.editor_schema)) {
							window.smart_manager.JSONEditorObj.setSchema(JSON.parse(col.editor_schema));
						}

						window.smart_manager.JSONEditorObj.set(val);
						window.smart_manager.JSONEditorObj.expandAll();
					}

					window.smart_manager.modal = {
						title: col.key || '',
						content: '<div id="sm_beta_json_editor"></div>',
						autoHide: false,
						cta: {
							title: _x('Ok', 'button', 'smart-manager-for-wp-e-commerce'),
							callback: function () {
								let content = (window.smart_manager.JSONEditorObj) ? JSON.stringify(window.smart_manager.JSONEditorObj.get()) : '';
								window.smart_manager.hot.setDataAtCell(coords.row, coords.col, content, 'sm.serialized_inline_update');
								window.smart_manager.JSONEditorObj = {}
								wp.editor.remove('sm_beta_json_editor')
							}
						},
						onCreate: initializeJSONEditor,
						onUpdate: initializeJSONEditor
					}
					window.smart_manager.showModal()
				}

				if (typeof (col.type) != 'undefined' && col.type == 'sm.multilist') { // code to handle the functionality to handle editing of 'multilist' data types
					let actual_value = col.values,
						multiselect_data = new Array(),
						multiselect_chkbox_list = '',
						current_value = new Array();
					// Extracting selected values safely.
					if ('undefined' !== typeof (current_cell_value) && (null !== current_cell_value) && ('' !== current_cell_value)) {
						current_value = ('string' === typeof (current_cell_value)) ? current_cell_value.split(', ') : new Array(String(current_cell_value));
					}
					// Initialize all data and assign children to their respective parents.
					for (let index in actual_value) {
						let title = actual_value[index].hasOwnProperty('title') ? actual_value[index].title : actual_value[index].term;
						let parent_id = actual_value[index]['parent'];
						multiselect_data[index] = {
							id: index,
							term: actual_value[index].term,
							title: title,
							child: {}
						};
						if ((0 !== parseInt(parent_id)) && multiselect_data[parent_id]) {
							multiselect_data[parent_id].child[index] = multiselect_data[index]; // Assign child
						}
					}
					// Keep parent ids only in root level for search.
					multiselect_data = multiselect_data.filter((item, index) => {
						let parent_id = actual_value[index]['parent'];
						return (0 === parseInt(parent_id)); // Keep only items where parent is 0 (root level for search).
					});
					// Add the search box before the checkbox list and generate final checkbox list.
					multiselect_chkbox_list = `<div id="sm_multiselect_container">
					<input type="text" data-ul-id="sm-multilist-data" class="sm-search-box"
						onkeyup="window.smart_manager.processListSearch(this)"
						placeholder="${_x('Search ' + (col.key || 'Taxonomy') + '...', 'placeholder', 'smart-manager-for-wp-e-commerce')}">
					${window.smart_manager.generateCheckboxList(multiselect_data, current_value)}
				</div>`
					window.smart_manager.modal = {
						title: _x((col.key || 'Taxonomy'), 'modal title', 'smart-manager-for-wp-e-commerce'),
						content: multiselect_chkbox_list,
						autoHide: false,
						cta: {
							title: _x('Ok', 'button', 'smart-manager-for-wp-e-commerce'),
							callback: function () {
								let mutiselect_edited_text = '';
								let selected_val = jQuery("input[name='chk_multiselect']:checked").map(function () {
									return jQuery(this).val();
								}).get();
								if (selected_val.length > 0) {
									for (var index in selected_val) {
										if (actual_value.hasOwnProperty(selected_val[index])) {
											if (mutiselect_edited_text != '') {
												mutiselect_edited_text += ', ';
											}
											mutiselect_edited_text += selected_val[index];
										}
									}
								}
								window.smart_manager.hot.setDataAtCell(coords.row, coords.col, mutiselect_edited_text, 'sm.multilist_inline_update');
							}
						},
					}
					window.smart_manager.showModal()
				}
			},
			// to handle updating the state on column resize
			afterColumnResize: function (currentColumn, newSize, isDoubleClick) {
				if (window.smart_manager.currentVisibleColumns[currentColumn]) {
					for (let index in window.smart_manager.currentColModel) {
						if (window.smart_manager.currentColModel[index].src == window.smart_manager.currentVisibleColumns[currentColumn].src) {
							window.smart_manager.currentColModel[index].width = newSize
						}
					}
				}
			},
			afterScrollHorizontally: function () {
				if ("undefined" !== typeof (window.smart_manager.refreshColumnsTitleAttribute) && "function" === typeof (window.smart_manager.refreshColumnsTitleAttribute)) {
					window.smart_manager.refreshColumnsTitleAttribute();
				}
			},
			afterOnCellMouseDown: function (e, coords, td) {
				if ((!coords) || (coords && (coords.row === -1) && (coords.col !== -1))) {
					return;
				}
				let col = this.getCellMeta(coords.row, coords.col);
				if ((!col) || (!col.prop) || ("users_user_pass" !== col.prop) || (!window.smart_manager.colEditDisableMessage) || (!window.smart_manager.colEditDisableMessage.disable) || (!window.smart_manager.colEditDisableMessage.error_message)) {
					return;
				}
				window.smart_manager.disableErrorMessage(window.smart_manager.colEditDisableMessage.error_message);
			}
		});
	}

	SmartManager.prototype.event_handler = function () {
		// Detect when the user is typing in the select2 search box
		jQuery(document).on('input', '#sm_nav_bar .select2-search__field', function (e) {
			if ((!window.smart_manager.hasOwnProperty('dashboardSelect2Items')) || (typeof window.smart_manager.dashboardSelect2Items === 'undefined')) {
				return;
			}
			let select2SearchResult = window.smart_manager.findSelect2ParentOrChildByText(e.target.value, false);
			let matchingParentId = select2SearchResult.hasOwnProperty('parentID') ? select2SearchResult.parentID : '';
			if ((matchingParentId) && (matchingParentId.length)) {
				window.smart_manager.showSelect2Childs(matchingParentId, jQuery("#sm_nav_bar .select2-results__group").first());//by default set the focus on the first element(parent)
				return;
			}
			jQuery('#sm_select2_childs_section').removeClass("visible");
		});

		jQuery(document).on("mouseenter", "#sm_nav_bar .select2-results__group", function () {
			jQuery("#sm_nav_bar .select2-results__group").removeClass("focus");
			let parentId = jQuery(this).find(".select2-group-text").attr("id");
			if ((!parentId) || (parentId.length === 0)) {
				return;
			}
			window.smart_manager.showSelect2Childs(parentId, jQuery(this));
		});

		// Code to handle select2 child item selection and display
		jQuery(document).on("mousedown", ".select2-child-item .dashboard-name", function (event) {
			if (event.button !== 0) {
				return;
			}
			const childId = jQuery(this).parent().data("id");
			if ((!childId) || (typeof childId === 'undefined') || (childId.length === 0)) {
				return;
			}
			jQuery('#sm_select2_childs_section').removeClass("visible");
			if (parseInt(window.smart_manager.sm_beta_pro) === 1) {
				let savedSearch = window.smart_manager.findSavedSearchBySlug(childId);
				if ((savedSearch) && (savedSearch.hasOwnProperty('parent_post_type')) && (savedSearch.hasOwnProperty('slug'))) {
					window.smart_manager.loadingDashboardForsavedSearch = true;
					window.smart_manager.savedSearchDashboardKey = savedSearch.parent_post_type;
					window.smart_manager.advancedSearchQuery = savedSearch?.params?.search_params?.params || [];
					window.smart_manager.savedSearchParams = savedSearch?.params?.search_params || {};
					let child = window.smart_manager.findSelect2ParentOrChildByText(savedSearch.parent_post_type, true);
					window.smart_manager.savedSearchDashboardName = child?.childText || '';
					if (window.smart_manager.checkPostParamsInSavedSearch(savedSearch)) {
						//show eligible dashboards.
						let eligibleDashboards = window.smart_manager.GetEligibleDashboardsForSavedSearch(savedSearch);
						window.smart_manager.eligibleDashboardSavedSearch = savedSearch.slug;
						window.smart_manager.eligibleDashboardsDialog(eligibleDashboards);
						if (!eligibleDashboards.length) {
							jQuery("#sm_dashboard_select").val(childId).trigger("change");
						}
						return;
					}
				}
			}
			jQuery("#sm_dashboard_select").val(childId).trigger("change");
		});

		// Code to handle select2 child items show/hide
		jQuery("#sm_select2_childs_section").on("mouseenter", function () {
			jQuery(this).addClass("visible");
		});

		// Code to handle width of the grid based on the WP collapsable menu
		jQuery(document).on('click', '#collapse-menu', function () {
			let current_url = document.URL;

			if (current_url.indexOf("page=smart-manager") == -1) {
				return;
			}

			if (!jQuery(document.body).hasClass('folded')) {
				window.smart_manager.grid_width = document.documentElement.offsetWidth - (document.documentElement.offsetWidth * 0.10);
			} else {
				window.smart_manager.grid_width = document.documentElement.offsetWidth - (document.documentElement.offsetWidth * 0.04);
			}

			window.smart_manager.hot.updateSettings({ 'width': window.smart_manager.grid_width });
			window.smart_manager.hot.render();

			jQuery('#sm_top_bar, #sm_bottom_bar').css('width', window.smart_manager.grid_width + 'px');
			jQuery('#sm_top_bar_actions').css('width', window.smart_manager.grid_width + 'px');
			jQuery('#sm_top_bar_left').css('width', 'calc(' + window.smart_manager.grid_width + 'px - 2em');
		});

		//Code to handle dashboard change in grid
		jQuery(document).off('change', '#sm_dashboard_select').on('change', '#sm_dashboard_select', function () {
			var sm_dashboard_valid = 0,
				sm_selected_dashboard_key = ((window.smart_manager.loadingDashboardForsavedSearch === true) && (window.smart_manager.hasOwnProperty('savedSearchDashboardKey'))) ? window.smart_manager.savedSearchDashboardKey : jQuery(this).val(),
				sm_selected_dashboard_title = ((window.smart_manager.loadingDashboardForsavedSearch === true) && (window.smart_manager.hasOwnProperty('savedSearchDashboardName'))) ? window.smart_manager.savedSearchDashboardName : jQuery("#sm_dashboard_select option:selected").text();

			window.smart_manager.isCustomView = (window.smart_manager.getViewSlug(sm_selected_dashboard_title)) ? true : false;
			window.smart_manager.userSwitchingDashboard = true;
			if (window.smart_manager.sm_beta_pro == 0) {
				sm_dashboard_valid = 0;
				if (window.smart_manager.sm_lite_dashboards.indexOf(sm_selected_dashboard_key) >= 0) {
					sm_dashboard_valid = 1;
				}
			} else {
				sm_dashboard_valid = 1;
			}

			if (sm_dashboard_valid == 1) {

				window.smart_manager.state_apply = true;
				// window.smart_manager.refreshDashboardStates(); //function to save the state

				if ("undefined" !== typeof (window.smart_manager.updateState) && "function" === typeof (window.smart_manager.updateState)) {
					("undefined" !== typeof (window.smart_manager.isTasksEnabled) && "function" === typeof (window.smart_manager.isTasksEnabled) && window.smart_manager.isTasksEnabled()) ? window.smart_manager.updateState({ isTasksEnabled: 1 }) : window.smart_manager.updateState();
				}
				window.smart_manager.reset(true);
				window.smart_manager.dashboardKey = sm_selected_dashboard_key;
				window.smart_manager.dashboardName = sm_selected_dashboard_title;
				window.smart_manager.current_selected_dashboard = sm_selected_dashboard_key;

				if ("undefined" !== typeof (window.smart_manager.resetSearch) && "function" === typeof (window.smart_manager.resetSearch)) {
					window.smart_manager.resetSearch();
				}
				if (typeof (window.smart_manager.initialize_advanced_search) !== "undefined" && typeof (window.smart_manager.initialize_advanced_search) === "function" && window.smart_manager.searchType != 'simple') {
					window.smart_manager.initialize_advanced_search();
				}

				if (window.smart_manager.dashboardKey == 'shop_order') {
					jQuery('#print_invoice_sm_editor_grid_btn').show();
				} else {
					jQuery('#print_invoice_sm_editor_grid_btn').hide();
				}

				(window.smart_manager.isTaxonomyDashboard()) ? jQuery('#sm_beta_move_to_trash').hide() : jQuery('#sm_beta_move_to_trash').show();

				jQuery('#sm_show_tasks_container').parents('div.sm_top_bar_action_btns').removeAttr('style');

				window.smart_manager.displayShowHideColumnSettings(true);
				jQuery('#sm_editor_grid').trigger('sm_dashboard_change'); //custom trigger
				if ('undefined' !== typeof (window.smart_manager.displayTasks) && 'function' === typeof (window.smart_manager.displayTasks)) {
					window.smart_manager.displayTasks({ dashboardChange: true });
				}
				window.smart_manager.toggleTopBar();
				window.smart_manager.setDashboardDisplayName();
				window.smart_manager.loadDashboard()
				window.smart_manager.savedSearchConds = {}
			} else {
				jQuery(this).val(window.smart_manager.current_selected_dashboard);
				window.smart_manager.notification = {
					message: sprintf(
						/* translators: %1$s: dashboard display name %2$s: success message %3$s: pricing page link */
						_x('For managing %1$s, %2$s %3$s version', 'modal content', 'smart-manager-for-wp-e-commerce'), sm_selected_dashboard_title, window.smart_manager.sm_success_msg, '<a href="' + window.smart_manager.pricingPageURL + '" target="_blank">' + _x('Pro', 'modal content', 'smart-manager-for-wp-e-commerce') + '</a>'), hideDelay: window.smart_manager.notificationHideDelayInMs
				}
				window.smart_manager.showNotification()
			}
			window.smart_manager.saved_bulk_edits = false;
			window.smart_manager.selectedSavedBulkEdit = "";
			jQuery('.sm-save-changes-notification .sm-notification-close').trigger('click');
		})

			.off('click', '#sm_advanced_search').on('click', '#sm_advanced_search', function (e) {
				e.preventDefault();
				if (typeof (window.smart_manager.showPannelDialog) !== "undefined" && typeof (window.smart_manager.showPannelDialog) === "function") {
					window.smart_manager.showPannelDialog(window.smart_manager.advancedSearchRoute)
				}
			})

			.off('click', '#show_hide_cols_sm_editor_grid').on('click', '#show_hide_cols_sm_editor_grid', function (e) {
				e.preventDefault();
				if ((typeof window.smart_manager.dirtyRowColIds !== 'undefined') && Object.getOwnPropertyNames(window.smart_manager.dirtyRowColIds).length > 0) {
					window.smart_manager.confirmUnsavedChanges({ 'yesCallback': window.smart_manager.showPannelDialog, 'yesCallbackParams': window.smart_manager.columnManagerRoute, 'hideOnYes': false })
				} else if ("undefined" !== typeof (window.smart_manager.showPannelDialog) && "function" === typeof (window.smart_manager.showPannelDialog)) {
					window.smart_manager.showPannelDialog(window.smart_manager.columnManagerRoute);
				}
			})

			//code for handling resetting column state to default state
			.off('click', 'a#sm_reset_state').on('click', 'a#sm_reset_state', function (e) {
				e.preventDefault();

				let params = {},
					viewSlug = window.smart_manager.getViewSlug(window.smart_manager.dashboardName);
				params.data_type = 'json';
				params.data = {
					cmd: 'reset_state',
					security: window.smart_manager.saCommonNonce,
					active_module: window.smart_manager.dashboardKey
				};

				params.data['dashboard_key'] = window.smart_manager.dashboardKey
				// Code for passing extra param for view handling
				if (1 == window.smart_manager.sm_beta_pro) {
					params.data['is_view'] = 0;

					if (viewSlug) {
						params.data['is_view'] = 1;
						params.data['active_module'] = viewSlug;
					}
				}

				window.smart_manager.sendRequest(params, function (response) {
					let dashboardURLParams = (viewSlug) ? viewSlug + "&is_view=1" : window.smart_manager.dashboardKey;
					window.location.href = (window.smart_manager.smAppAdminURL || window.location.href) + ((window.location.href.indexOf("?") === -1) ? "?" : "&") + "dashboard=" + dashboardURLParams;
				})
			})

			.off('click', '#search_switch').on('click', '#search_switch', function () { //Added for setting click flag for handling for custom views
				window.smart_manager.searchSwitchClicked = true
			})

			.off('change', '#search_switch').on('change', '#search_switch', function (e) { //request for handling switch search types
				//Code for showing notice for custom views
				if ((window.smart_manager.isViewContainSearchParams) && (window.smart_manager.searchSwitchClicked) && typeof (window.smart_manager.showNotification) !== "undefined" && typeof (window.smart_manager.showNotification) === "function") {
					e.target.checked = !e.target.checked
					window.smart_manager.searchSwitchClicked = false
					window.smart_manager.notification = { message: _x('Cannot switch search when using Custom Views', 'search switch notice for custom views', 'smart-manager-for-wp-e-commerce'), hideDelay: window.smart_manager.notificationHideDelayInMs }
					window.smart_manager.showNotification()
					return
				}

				let switchSearchType = jQuery(this).attr('switchSearchType'),
					title = jQuery("label[for='" + jQuery(this).attr("id") + "']").attr('title'),
					content = '';

				jQuery(this).attr('switchSearchType', window.smart_manager.searchType);
				jQuery("label[for='" + jQuery(this).attr("id") + "']").attr('title', title.replace(String(switchSearchType).capitalize(), String(window.smart_manager.searchType).capitalize()));

				window.smart_manager.searchType = switchSearchType;
				content = (window.smart_manager.searchType == 'simple') ? window.smart_manager.simpleSearchContent : window.smart_manager.advancedSearchContent;
				jQuery('#sm_nav_bar_search #search_content').html(content);

				if (window.smart_manager.searchType == 'simple') {
					jQuery('#sm_simple_search_box').val(window.smart_manager.simpleSearchText);
				} else {
					// Code to initialize search col model
					if (typeof (window.smart_manager.initialize_advanced_search) !== "undefined" && typeof (window.smart_manager.initialize_advanced_search) === "function") {
						window.smart_manager.initialize_advanced_search();
					}
					if (((typeof window.smart_manager.dirtyRowColIds !== 'undefined') && Object.getOwnPropertyNames(window.smart_manager.dirtyRowColIds).length > 0) && (window.smart_manager.advancedSearchRuleCount === 0)) {
						window.smart_manager.confirmUnsavedChanges({ 'yesCallback': window.smart_manager.showPannelDialog, 'yesCallbackParams': window.smart_manager.advancedSearchRoute, 'hideOnYes': false })
					} else if ((window.smart_manager.advancedSearchRuleCount === 0) && "undefined" !== typeof (window.smart_manager.showPannelDialog) && "function" === typeof (window.smart_manager.showPannelDialog)) { // Code to show the advanced search dialog in case of no conditions.
						window.smart_manager.showPannelDialog(window.smart_manager.advancedSearchRoute);
					}
				}

				// code for refreshing the dashboard based on the search
				if ((window.smart_manager.simpleSearchText != '' || window.smart_manager.advancedSearchRuleCount > 0) && typeof (window.smart_manager.loadDashboard) !== "undefined" && typeof (window.smart_manager.loadDashboard) === "function") {
					if ((window.smart_manager.loadingDashboardForsavedSearch === false) && (window.smart_manager.isCustomView === false)) {
						window.smart_manager.loadDashboard()
					}
				}

			})

			.off('keyup', '#sm_simple_search_box').on('keyup', '#sm_simple_search_box', function () { //request for handling simple search
				clearTimeout(window.smart_manager.searchTimeoutId);
				window.smart_manager.searchTimeoutId = setTimeout(function () {
					//Code for showing notice for custom views
					if ((window.smart_manager.isViewContainSearchParams) && typeof (window.smart_manager.showNotification) !== "undefined" && typeof (window.smart_manager.showNotification) === "function") {
						window.smart_manager.notification = { message: _x('Search string cannot be edited when using Custom Views', 'simple search notice for custom views', 'smart-manager-for-wp-e-commerce'), hideDelay: window.smart_manager.notificationHideDelayInMs }
						window.smart_manager.showNotification()
						jQuery('#sm_simple_search_box').val(window.smart_manager.simpleSearchText)
					} else {
						window.smart_manager.simpleSearchText = jQuery('#sm_simple_search_box').val();
						if ((typeof window.smart_manager.dirtyRowColIds !== 'undefined') && Object.getOwnPropertyNames(window.smart_manager.dirtyRowColIds).length > 0) {
							window.smart_manager.confirmUnsavedChanges({ 'yesCallback': window.smart_manager.refresh.bind(instance) })
						} else if ("undefined" !== typeof (window.smart_manager.refresh) && "function" === typeof (window.smart_manager.refresh)) { // Code to show the advanced search dialog in case of no conditions.
							window.smart_manager.refresh();
						}
					}
				}, 1000);
			})

			//Code to handle the inline save functionality
			.off('click', '.sm_top_bar_action_btns #save_sm_editor_grid_btn').on('click', '.sm_top_bar_action_btns #save_sm_editor_grid_btn', function () {
				jQuery('.sm-notification-close').trigger('click');
				if (Object.keys(window.smart_manager.editedData).length == 0) {
					window.smart_manager.notification = { message: _x('Please edit a record', 'notification', 'smart-manager-for-wp-e-commerce') }
					window.smart_manager.showNotification()
					return;
				}
				if (window.smart_manager.dashboardKey == 'user' && Object.keys(window.smart_manager.dirtyRowColIds).length > 0) {
					for (let row in window.smart_manager.dirtyRowColIds) {
						let userEmail = window.smart_manager.hot.getDataAtRowProp(row, 'users_user_email');
						if (!userEmail) {
							window.smart_manager.notification = { message: _x('Please enter user email', 'notification', 'smart-manager-for-wp-e-commerce') + '<div style="font-size:0.9em;font-style: italic;margin:1em;">' + _x('Enable', 'notification', 'smart-manager-for-wp-e-commerce') + '<code>' + _x('User Email', 'notification', 'smart-manager-for-wp-e-commerce') + '</code>' + _x('column if not enabled using', 'notification', 'smart-manager-for-wp-e-commerce') + '<a href="https://www.storeapps.org/docs/sm-how-to-show-hide-columns-in-dashboard/?utm_source=sm&utm_medium=in_app&utm_campaign=view_docs" target="_blank"> ' + _x('column show/hide functionality', 'notification', 'smart-manager-for-wp-e-commerce') + '</a>.</div>', hideDelay: window.smart_manager.notificationHideDelayInMs }
							window.smart_manager.showNotification()
							return;
						}
					}
				}
				if ("undefined" === typeof (window.smart_manager.saveData) && "function" !== typeof (window.smart_manager.saveData)) {
					return;
				}
				if (1 == window.smart_manager.sm_beta_pro) {
					window.smart_manager.updatedEditedData = {};
					Object.entries(window.smart_manager.editedData).forEach(([key, value]) => {
						if (key && (false === key.includes('sm_temp_')) && (false === key.includes('null'))) {
							window.smart_manager.updatedEditedData[key] = value;
						}
					});
					let inlineUpdatedFields = new Set();
					if (Object.values(window.smart_manager.updatedEditedData).length > 0 && ("undefined" !== typeof (window.smart_manager.getColDisplayName) && "function" === typeof (window.smart_manager.getColDisplayName))) {
						Object.values(window.smart_manager.updatedEditedData).forEach((values) => {
							if (Object.keys(values).length > 0) {
								Object.keys(values).forEach((key) => {
									let editedColumnName = window.smart_manager.getColDisplayName(key);
									if (editedColumnName) {
										inlineUpdatedFields.add(editedColumnName)
									}
								});
							}
						});
					}
					window.smart_manager.processName = _x('Inline Edit', 'process name', 'smart-manager-for-wp-e-commerce');
					window.smart_manager.processContent = [...inlineUpdatedFields].join(', ');
					window.smart_manager.processCallback = window.smart_manager.saveData;
					if ("undefined" !== typeof (window.smart_manager.showTitleModal) && "function" === typeof (window.smart_manager.showTitleModal) && (inlineUpdatedFields.size > 0)) {
						window.smart_manager.showTitleModal()
					} else if (0 === inlineUpdatedFields.size) {
						window.smart_manager.saveData()
					}
				} else {
					window.smart_manager.saveData();
				}
			})

			//Code to handle the delete records functionality
			.off('click', '.sm_top_bar_action_btns #sm_beta_move_to_trash, .sm_top_bar_action_btns #sm_beta_delete_permanently').on('click', '.sm_top_bar_action_btns #sm_beta_move_to_trash, .sm_top_bar_action_btns #sm_beta_delete_permanently', function () {

				let id = jQuery(this).attr('id');
				let deletePermanently = ('sm_beta_delete_permanently' == id) ? 1 : 0;
				let moveToTrash = ('sm_beta_move_to_trash' == id) ? 1 : 0;
				let isBackgroundProcessRunning = window.smart_manager.backgroundProcessRunningNotification(false);

				if (0 == window.smart_manager.sm_beta_pro && deletePermanently) {
					window.smart_manager.notification = { status: 'error', message: _x('To permanently delete records', 'notification', 'smart-manager-for-wp-e-commerce') + ', <a href="' + window.smart_manager.pricingPageURL + '" target="_blank">' + _x('upgrade to Pro', 'notification', 'smart-manager-for-wp-e-commerce') + '</a>', hideDelay: window.smart_manager.notificationHideDelayInMs }
					window.smart_manager.showNotification()
					return false;
				}

				if (((deletePermanently || moveToTrash) && window.smart_manager.trashAndDeletePermanently.disable)) {
					if (!(window.smart_manager.trashAndDeletePermanently.error_message)) {
						return false;
					}
					window.smart_manager.disableErrorMessage(window.smart_manager.trashAndDeletePermanently.error_message);
					return false;
				}

				if (window.smart_manager.selectedRows.length == 0 && !window.smart_manager.selectAll) {
					window.smart_manager.notification = { message: _x('Please select a record', 'notification', 'smart-manager-for-wp-e-commerce') }
					window.smart_manager.showNotification()
					return false;
				}

				if (window.smart_manager.sm_beta_pro == 0 && window.smart_manager.selectedRows.length > window.smart_manager.sm_deleted_successful) {
					window.smart_manager.notification = { message: _x('To delete more than', 'notification', 'smart-manager-for-wp-e-commerce') + ' ' + window.smart_manager.sm_deleted_successful + ' ' + _x('records at a time', 'notification', 'smart-manager-for-wp-e-commerce') + ', <a href="' + window.smart_manager.pricingPageURL + '" target="_blank">' + _x('upgrade to Pro', 'notification', 'smart-manager-for-wp-e-commerce') + '</a>', hideDelay: window.smart_manager.notificationHideDelayInMs }
					window.smart_manager.showNotification()
				} else {

					let params = {};

					params.title = '<span class="sm-error-icon"><span class="dashicons dashicons-warning" style="vertical-align: text-bottom;"></span>&nbsp;' + _x('Attention!', 'modal title', 'smart-manager-for-wp-e-commerce') + '</span>';
					params.titleIsHtml = true;
					params.btnParams = {};

					let actionText = (!window.smart_manager.trashEnabled || deletePermanently) ? '<span class="sm-error-icon">' + _x('permanently delete', 'modal content', 'smart-manager-for-wp-e-commerce') + '</span>' : _x('trash', 'modal content', 'smart-manager-for-wp-e-commerce');

					if (!window.smart_manager.trashEnabled || deletePermanently) {
						params.height = 170;
					}

					let selected_text = '<span style="font-size: 1.2em;">' + sprintf(
						/* translators: %s: action name */
						_x('Are you sure you want to %s', 'modal content', 'smart-manager-for-wp-e-commerce'), '<strong>' + actionText + ' ' + _x('the selected', 'modal content', 'smart-manager-for-wp-e-commerce') + '</strong>' + ' ') + ((window.smart_manager.selectedRows.length > 1) ? _x('records', 'modal content', 'smart-manager-for-wp-e-commerce') : _x('record', 'modal content', 'smart-manager-for-wp-e-commerce')) + '?</span>';
					let all_text = '<span style="font-size: 1.2em;">' + sprintf(
						/* translators: %1$s: action name %2$s: dashboard display name */
						_x('Are you sure you want to %1$s the %2$s?', 'modal content', 'smart-manager-for-wp-e-commerce'), '<strong>' + actionText + ' ' + _x('all', 'modal content', 'smart-manager-for-wp-e-commerce') + '</strong>', window.smart_manager.dashboardDisplayName) + '</span>';

					if (window.smart_manager.isFilteredData()) {
						all_text = '<span style="font-size: 1.2em;">' + sprintf(
							/* translators: %s: action name */
							_x('Are you sure you want to %s?', 'modal content', 'smart-manager-for-wp-e-commerce'), '<strong>' + actionText + ' ' + _x('all items in search results', 'modal content', 'smart-manager-for-wp-e-commerce') + '</strong>') + '</span>';
					}

					params.btnParams.yesCallbackParams = {};

					if (window.smart_manager.sm_beta_pro == 1) {
						params.btnParams.yesCallbackParams = { 'deletePermanently': deletePermanently };

						if (true === window.smart_manager.selectAll) {
							params.content = all_text;
						} else {
							params.content = selected_text;
						}

						if (typeof (window.smart_manager.deleteAllRecords) !== "undefined" && typeof (window.smart_manager.deleteAllRecords) === "function") {
							params.btnParams.yesCallback = window.smart_manager.deleteAllRecords;
						}
					} else {
						if (typeof (window.smart_manager.deleteRecords) !== "undefined" && typeof (window.smart_manager.deleteRecords) === "function") {
							params.content = selected_text;
							if (true === window.smart_manager.selectAll) {
								params.content += '<br><br><br><span style="font-size: 1.2em;"><small><i>' + _x('Note: Looking to', 'modal content', 'smart-manager-for-wp-e-commerce') + ' <strong>' + _x('delete all', 'modal content', 'smart-manager-for-wp-e-commerce') + '</strong> ' + _x('the records?', 'modal content', 'smart-manager-for-wp-e-commerce') + ' <a href="' + window.smart_manager.pricingPageURL + '" target="_blank">' + _x('Upgrade to Pro', 'modal content', 'smart-manager-for-wp-e-commerce') + '</a></i></small></span>';
								params.height = 225;
							}
							params.btnParams.yesCallback = window.smart_manager.deleteRecords;
						}
					}
					if (!isBackgroundProcessRunning) {
						params.btnParams.hideOnYes = (window.smart_manager.sm_beta_pro == 1) ? false : true;
						if ((typeof window.smart_manager.dirtyRowColIds !== 'undefined') && Object.getOwnPropertyNames(window.smart_manager.dirtyRowColIds).length > 0) {
							window.smart_manager.confirmUnsavedChanges({ 'yesCallback': window.smart_manager.showConfirmDialog, 'yesCallbackParams': params, 'hideOnYes': false })
						} else if (typeof (window.smart_manager.showConfirmDialog) !== "undefined" && typeof (window.smart_manager.showConfirmDialog) === "function") {
							window.smart_manager.showConfirmDialog(params);
						}
					}
				}
				return false;
			})

			//Code for handling refresh event
			.off('click', ".sm_gallery_image .sm_gallery_image_delete").on('click', ".sm_gallery_image .sm_gallery_image_delete", function () {
				let colSrc = jQuery(this).parents('div.sm_gallery_image_parent').data('col') || '',
					updateId = jQuery(this).parents('div.sm_gallery_image_parent').data('id') || 0,
					rowNo = parseInt(jQuery(this).parents('div.sm_gallery_image_parent').data('row') || 0);
				jQuery(this).parents('.sm_gallery_image').remove();
				let imageIds = new Array();
				jQuery('.sm_gallery_image').find('img').each(function () {
					imageIds.push(jQuery(this).data('id'));
				});
				let updatedGalleryImages = {};
				updatedGalleryImages[updateId] = {};
				updatedGalleryImages[updateId][colSrc] = imageIds.join(',');
				let params = { id: updateId, src: colSrc, values: updatedGalleryImages[updateId][colSrc], imageGalleryHtml: jQuery('div.modal-body').html(), rowNo: rowNo };
				if (1 == window.smart_manager.sm_beta_pro && "undefined" !== typeof (window.smart_manager.displayTitleModal) && "function" === typeof (window.smart_manager.displayTitleModal)) {
					window.smart_manager.displayTitleModal(params);
				} else if ("undefined" !== typeof (window.smart_manager.inlineUpdateMultipleImages) && "function" === typeof (window.smart_manager.inlineUpdateMultipleImages)) {
					window.smart_manager.inlineUpdateMultipleImages(params);
				}
			})

			//Code for handling refresh event
			.off('click', "#refresh_sm_editor_grid").on('click', "#refresh_sm_editor_grid", function () {
				window.smart_manager.refresh();
			})

			.off('click', "#sm_editor_grid_distraction_free_mode").on('click', "#sm_editor_grid_distraction_free_mode", function () {

				if (window.smart_manager.sm_beta_pro == 1) {
					if (typeof (window.smart_manager.smToggleFullScreen) !== "undefined" && typeof (window.smart_manager.smToggleFullScreen) === "function") {
						let element = document.documentElement;
						window.smart_manager.smToggleFullScreen(element);
					}
				} else {
					window.smart_manager.notification = {
						message: sprintf(
							/* translators: %s: pricing page link */
							_x('This feature is available only in the %s version', 'modal content', 'smart-manager-for-wp-e-commerce'), '<a href="' + window.smart_manager.pricingPageURL + '" target="_blank">' + _x('Pro', 'modal content', 'smart-manager-for-wp-e-commerce') + '</a>'), hideDelay: window.smart_manager.notificationHideDelayInMs
					}
					window.smart_manager.showNotification()
				}

				/*Review*/
				window.smart_manager.hot.updateSettings({ 'width': window.smart_manager.grid_width });
				window.smart_manager.hot.render();

				jQuery('#sm_top_bar, #sm_bottom_bar').css('width', window.smart_manager.grid_width + 'px');
			})

			//Code for load more items
			.off('click', "#sm_editor_grid_load_items").on('click', "#sm_editor_grid_load_items", function () {

				if (window.smart_manager.currentDashboardData.length >= window.smart_manager.totalRecords) {
					return;
				}

				window.smart_manager.page++;
				window.smart_manager.getData();
			})

			.off('click', 'td.htDimmed').on('click', 'td.htDimmed', function () {
				if (window.smart_manager.sm_beta_pro == 0) {
					if (typeof (window.smart_manager.modifiedRows) != 'undefined') {
						if (window.smart_manager.modifiedRows.length >= window.smart_manager.sm_updated_successful) {
							alert(_x('For editing more records upgrade to Pro', 'notification', 'smart-manager-for-wp-e-commerce'));
						}
					}
				}
			})

			//Code for add record functionality
			.off('click', "#add_sm_editor_grid").on('click', "#add_sm_editor_grid", function () {
				window.smart_manager.modal = {
					title: sprintf(
						/* translators: %s: dashboard display name */
						_x('Add %s(s)', 'modal title', 'smart-manager-for-wp-e-commerce'), window.smart_manager.dashboardDisplayName),
					content: '<div style="font-size:1.2em;"> <div style="margin-bottom:1em;">' + sprintf(
						/* translators: %s: dashboard display name */
						_x('Enter how many new %s(s) to create!', 'modal content', 'smart-manager-for-wp-e-commerce'), window.smart_manager.dashboardDisplayName) + '</div> <input type="number" id="sm_beta_add_record_count" min="1" value="1" style="width:5em;"></div>',
					autoHide: false,
					cta: {
						title: _x('Create', 'button', 'smart-manager-for-wp-e-commerce'),
						callback: function () {
							// setTimeout((window.smart_manager.modal = {}),2000) // code to hide the modal
							let count = jQuery('#sm_beta_add_record_count').val();
							if (count > 0) {
								window.smart_manager.hot.alter('insert_row', 0, count);
							}
						}
					},
					closeCTA: { title: _x('Cancel', 'button', 'smart-manager-for-wp-e-commerce') }
				}
				if ((typeof window.smart_manager.dirtyRowColIds !== 'undefined') && Object.getOwnPropertyNames(window.smart_manager.dirtyRowColIds).length > 0) {
					window.smart_manager.confirmUnsavedChanges({ 'yesCallback': window.smart_manager.showModal, 'modalVals': window.smart_manager.modal, 'hideOnYes': false })
				} else if ("undefined" !== typeof (window.smart_manager.showModal) && "function" === typeof (window.smart_manager.showModal)) {
					window.smart_manager.showModal()
				}
			})

			.off('click', "#sm_custom_views_create, #sm_custom_views_update").on('click', "#sm_custom_views_create, #sm_custom_views_update", function (e) {
				e.preventDefault();
				if (window.smart_manager.sm_beta_pro == 1) {
					if (typeof (window.smart_manager.createUpdateViewDialog) !== "undefined" && typeof (window.smart_manager.createUpdateViewDialog) === "function") {
						let id = jQuery(this).attr('id');
						let action = (id === 'sm_custom_views_update') ? 'update' : 'create';
						if ((typeof window.smart_manager.dirtyRowColIds !== 'undefined') && Object.getOwnPropertyNames(window.smart_manager.dirtyRowColIds).length > 0) {
							window.smart_manager.confirmUnsavedChanges({ 'yesCallback': window.smart_manager.createUpdateViewDialog, 'yesCallbackParams': action, 'hideOnYes': false })
						} else if ("undefined" !== typeof (window.smart_manager.createUpdateViewDialog) && "function" === typeof (window.smart_manager.createUpdateViewDialog)) {
							let params = {};
							if ((id === 'sm_custom_views_create')) {
								params.dashboardChecked = true;
								params.advancedSearchChecked = (jQuery('#search_switch').is(':checked')) ? true : false;
							}
							if ((id === 'sm_custom_views_update')) {
								params.dashboardChecked = (window.smart_manager.findSavedSearchBySlug(window.smart_manager.getViewSlug(window.smart_manager.dashboardName))) ? false : true;
								params.advancedSearchChecked = (window.smart_manager.advancedSearchQuery.length) ? true : false;
							}
							window.smart_manager.createUpdateViewDialog(action, params);
						}

					}
				} else {
					window.smart_manager.notification = {
						message: sprintf(
							/* translators: %s: pricing page link */
							_x('Custom Views available (Only in %s)', 'notification', 'smart-manager-for-wp-e-commerce'), '<a href="' + window.smart_manager.pricingPageURL + '" target="_blank">' + _x('Pro', 'notification', 'smart-manager-for-wp-e-commerce') + '</a>'), hideDelay: window.smart_manager.notificationHideDelayInMs
					}
					window.smart_manager.showNotification()
				}

			})

			.off('click', "#sm_custom_views_delete").on('click', "#sm_custom_views_delete", function (e) {
				e.preventDefault();
				if (window.smart_manager.sm_beta_pro == 1) {
					let params = {};

					params.btnParams = {}
					params.title = '<span class="sm-error-icon"><span class="dashicons dashicons-warning" style="vertical-align: text-bottom;"></span>&nbsp;' + _x('Attention!', 'modal title', 'smart-manager-for-wp-e-commerce') + '</span>';
					params.content = '<span style="font-size: 1.2em;">' + _x('This will', 'modal content', 'smart-manager-for-wp-e-commerce') + ' <span class="sm-error-icon"><strong>' + _x('delete', 'modal content', 'smart-manager-for-wp-e-commerce') + '</strong></span> ' + _x('the current view. Are you sure you want to continue?', 'modal content', 'smart-manager-for-wp-e-commerce') + '</span>';
					params.titleIsHtml = true;
					params.height = 200;

					if (typeof (window.smart_manager.deleteView) !== "undefined" && typeof (window.smart_manager.deleteView) === "function") {
						params.btnParams.yesCallbackParams = { success_msg: _x('View deleted successfully!', 'notification', 'smart-manager-for-wp-e-commerce') }
						params.btnParams.yesCallback = window.smart_manager.deleteView;
					}

					window.smart_manager.showConfirmDialog(params);
				} else {
					window.smart_manager.notification = {
						message: sprintf(
							/* translators: %s: pricing page link */
							_x('Custom Views available (Only in %s)', 'notification', 'smart-manager-for-wp-e-commerce'), '<a href="' + window.smart_manager.pricingPageURL + '" target="_blank">' + _x('Pro', 'notification', 'smart-manager-for-wp-e-commerce') + '</a>'), hideDelay: window.smart_manager.notificationHideDelayInMs
					}
					window.smart_manager.showNotification()
				}
			})

			// Code for handling the batch update & duplicate records functionality
			.off('click', "#batch_update_sm_editor_grid, .sm_top_bar_action_btns .sm_beta_dropdown_content a, #print_invoice_sm_editor_grid_btn").on('click', "#batch_update_sm_editor_grid, .sm_top_bar_action_btns .sm_beta_dropdown_content a, #print_invoice_sm_editor_grid_btn", function (e) {
				e.preventDefault();
				let id = jQuery(this).attr('id'),
					btnText = jQuery(this).text(),
					className = jQuery(this).attr('class');
				let clickedElement = jQuery(e.target);
				let clickedElementclassName = clickedElement.attr('class'); // Get the class of the clicked <a> tag
				if (jQuery(this).parents('div#del_sm_editor_grid').length > 0 || jQuery(this).parents('div#sm_custom_views').length > 0) {
					return;
				}
				let params = {},
					isBackgroundProcessRunning = window.smart_manager.backgroundProcessRunningNotification(false);
				params.btnParams = {};
				params.title = _x('Attention!', 'modal title', 'smart-manager-for-wp-e-commerce');
				if ('sm_schedule_export_btns' !== className && 0 === window.smart_manager.selectedRows.length && !window.smart_manager.selectAll && window.smart_manager.recordSelectNotification && ('sm_entire_store' !== className) && ('sm_scheduled_bulk_edits' !== clickedElementclassName)) {
					window.smart_manager.notification = { message: _x('Please select a record', 'notification', 'smart-manager-for-wp-e-commerce') }
					window.smart_manager.showNotification()
				} else if (window.smart_manager.exportCSVActions && 'undefined' !== typeof (id) && id && window.smart_manager.exportCSVActions.includes(id) && !isBackgroundProcessRunning) { //code for handling export CSV functionality.
					if ((typeof window.smart_manager.dirtyRowColIds !== 'undefined') && Object.getOwnPropertyNames(window.smart_manager.dirtyRowColIds).length > 0) {
						window.smart_manager.confirmUnsavedChanges({ 'yesCallback': window.smart_manager.getExportCsv, 'yesCallbackParams': { 'params': params, 'id': id, 'btnText': btnText }, 'hideOnYes': false })
					} else if ("undefined" !== typeof (window.smart_manager.getExportCsv) && "function" === typeof (window.smart_manager.getExportCsv) && params && btnText) {
						window.smart_manager.getExportCsv({ 'params': params, 'id': id, 'btnText': btnText });
					}
				}

				if (1 == window.smart_manager.sm_beta_pro) {
					if ('undefined' !== typeof (id) && id) {
						if ((window.smart_manager.selectedRows.length > 0 || window.smart_manager.selectAll || 'sm_entire_store' === className) && ('sm_scheduled_bulk_edits' !== clickedElementclassName)) {
							if (id == 'batch_update_sm_editor_grid' && !isBackgroundProcessRunning) { //code for handling batch update functionality
								// window.smart_manager.createBatchUpdateDialog();
								if ((typeof window.smart_manager.dirtyRowColIds !== 'undefined') && Object.getOwnPropertyNames(window.smart_manager.dirtyRowColIds).length > 0) {
									window.smart_manager.confirmUnsavedChanges({ 'yesCallback': window.smart_manager.showPannelDialog, 'yesCallbackParams': window.smart_manager.bulkEditRoute, 'hideOnYes': false })
								} else if (typeof (window.smart_manager.showPannelDialog) !== "undefined" && typeof (window.smart_manager.showPannelDialog) === "function") {
									window.smart_manager.showPannelDialog(window.smart_manager.bulkEditRoute)
								}
							} else if ((id == 'sm_beta_dup_entire_store' || id == 'sm_beta_dup_selected') && !isBackgroundProcessRunning) { //code for handling duplicate records functionality
								if (window.smart_manager.isTaxonomyDashboard()) {
									window.smart_manager.notification = { message: _x('Comming soon', 'notification', 'smart-manager-for-wp-e-commerce') }
									if (typeof (window.smart_manager.showNotification) !== "undefined" && typeof (window.smart_manager.showNotification) === "function") {
										window.smart_manager.showNotification();
									}
								} else {
									params.content = (window.smart_manager.dashboardKey != 'product') ? '<p>' + _x('This will duplicate only the records in posts, postmeta and related taxonomies.', 'modal content', 'smart-manager-for-wp-e-commerce') + '</p>' : '';
									params.content += _x('Are you sure you want to duplicate the ', 'modal content', 'smart-manager-for-wp-e-commerce') + '<strong>'+btnText+'</strong>' + '?';
									if('sm_beta_dup_entire_store' !== id && 'product'===window.smart_manager.dashboardKey){
										let variationCount = 0;
										let hasParent = false;
										const selectedRows = window.smart_manager.selectedRows || [];
										for (const i of selectedRows) {
											const data = window.smart_manager?.currentDashboardData[i] || false;
											if (!data || !data.hasOwnProperty('posts_post_parent')){
												continue;
											} 
											if (parseInt(data.posts_post_parent)) {
												variationCount++;
											}else{
												hasParent = true;
											}
										}
										const onlyVariations = variationCount && variationCount === selectedRows.length;
										const mixedSelection = variationCount && hasParent;
										if (onlyVariations) {
											window.smart_manager.showNotificationDialog(
												_x('Note:', 'modal title', 'smart-manager-for-wp-e-commerce'),
												`<div class="text-gray-500" style="font-style: italic;">
													${_x('Individual variation duplication is not currently supported. Please consider submitting a', 'modal content', 'smart-manager-for-wp-e-commerce')} <a href="https://www.storeapps.org/contact-us/?utm_source=sm&utm_medium=in_app_modal&utm_campaign=feature_request" target="_blank">${_x('feature request', 'modal content', 'smart-manager-for-wp-e-commerce')}</a> ${_x('to help us prioritize this enhancement.', 'modal content', 'smart-manager-for-wp-e-commerce')}
												</div>`
											)
											return;
										} else if (mixedSelection) {
											params.content += `<div class="mt-2 text-gray-500" style="font-style: italic;">
												<strong>${_x('Note:', 'modal title', 'smart-manager-for-wp-e-commerce')}</strong> ${_x('Product duplication applies to the parent product and all its variations collectively. Individual variations cannot be duplicated separately at this time. If this feature is important to you, please consider submitting a', 'modal content', 'smart-manager-for-wp-e-commerce')} <a href="https://www.storeapps.org/contact-us/?utm_source=sm&utm_medium=in_app_modal&utm_campaign=feature_request" target="_blank">${_x('feature request', 'modal content', 'smart-manager-for-wp-e-commerce')}</a>.
											</div>`;
										}
									}
									if (typeof (window.smart_manager.duplicateRecords) !== "undefined" && typeof (window.smart_manager.duplicateRecords) === "function") {
										params.btnParams.yesCallback = window.smart_manager.duplicateRecords;
									}

									window.smart_manager.duplicateStore = (id == 'sm_beta_dup_entire_store') ? true : false;

									params.btnParams.hideOnYes = false;
									if ((typeof window.smart_manager.dirtyRowColIds !== 'undefined') && Object.getOwnPropertyNames(window.smart_manager.dirtyRowColIds).length > 0) {
										window.smart_manager.confirmUnsavedChanges({ 'yesCallback': window.smart_manager.showConfirmDialog, 'yesCallbackParams': params, 'hideOnYes': false })
									} else if ("undefined" !== typeof (window.smart_manager.showConfirmDialog) && "function" === typeof (window.smart_manager.showConfirmDialog)) {
										window.smart_manager.showConfirmDialog(params);
									}
								}
							} else if (id == 'print_invoice_sm_editor_grid_btn') { //code for handling Print Invoice functionality
								if ((typeof window.smart_manager.dirtyRowColIds !== 'undefined') && Object.getOwnPropertyNames(window.smart_manager.dirtyRowColIds).length > 0) {
									window.smart_manager.confirmUnsavedChanges({ 'yesCallback': window.smart_manager.printInvoice })
								} else if (typeof (window.smart_manager.printInvoice) !== "undefined" && typeof (window.smart_manager.printInvoice) === "function") {
									window.smart_manager.printInvoice();
								}
							}
						}
					}

				} else {

					if( typeof(id) != 'undefined' && !['sm_schedule_export', 'sm_manage_schedule_export'].includes(id) ) {

						if (!['sm_beta_dup_entire_store', 'sm_beta_dup_selected'].includes(id) && (window.smart_manager.stockCols && !window.smart_manager.stockCols.includes(id))) {

							let description = sprintf(
								/* translators: %s: Bulk Edit doc link */
								_x('You can change/update multiple fields of the entire store OR for selected items using the Bulk Edit feature. Refer to this doc on %s or watch the video below.', 'modal description', 'smart-manager-for-wp-e-commerce'), '<a href="https://www.storeapps.org/docs/sm-how-to-use-batch-update/?utm_source=sm&utm_medium=in_app&utm_campaign=view_docs" target="_blank">' + _x('how to do bulk edit', 'modal description', 'smart-manager-for-wp-e-commerce') + '</a>');
							title = ((id == 'batch_update_sm_editor_grid') ? btnText + ' - <span style="color: red;">' + _x('Biggest Time Saver', 'modal title', 'smart-manager-for-wp-e-commerce') + ' </span>' : btnText) + sprintf(
								/* translators: %s: pricing page link */
								_x('(Only in %s)', 'modal title', 'smart-manager-for-wp-e-commerce'), '<a href="' + window.smart_manager.pricingPageURL + '" target="_blank">' + _x('Pro', 'modal title', 'smart-manager-for-wp-e-commerce') + '</a>');
							if ((id !== 'batch_update_sm_editor_grid') && window.smart_manager.exportCSVActions) {
								title = _x('Export CSV of ', 'modal title', 'smart-manager-for-wp-e-commerce') + btnText;
								description = _x('You can export selected/all records OR filtered records (using Simple Search or Advanced Search) by simply clicking on the Export CSV button at the bottom right of the grid.', 'modal description', 'smart-manager-for-wp-e-commerce');
							}

							content = '<div>' +
								'<p style="font-size:1.2em;margin:1em;">' + description + '</p>' +
								'<div style="height:17rem;"><iframe width="100%" height="100%" src="https://www.youtube.com/embed/' + ((id == 'batch_update_sm_editor_grid') ? 'COXCuX2rFrk' : 'GMgysSQw7_g') + '" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>' +
								'</div>'
							window.smart_manager.modal = {
								title: title,
								content: content,
								width: 'w-2/6',
								autoHide: false,
								isFooterItemsCenterAligned: true,
								cta: {
									title: _x('Upgrade Now', 'button', 'smart-manager-for-wp-e-commerce'),
									callback: function () {
										window.open(window.smart_manager.pricingPageURL, "_blank");
										jQuery(this).dialog("close");
									}
								}
							}
							window.smart_manager.showModal()

						} else if (['sm_beta_dup_entire_store', 'sm_beta_dup_selected'].includes(id)) {
							window.smart_manager.notification = {
								message: sprintf(
									/* translators: %s: pricing page link */
									_x('Duplicate Records (Only in %s)', 'notification', 'smart-manager-for-wp-e-commerce'), '<a href="' + window.smart_manager.pricingPageURL + '" target="_blank">' + _x('Pro', 'notification', 'smart-manager-for-wp-e-commerce') + '</a>'), hideDelay: window.smart_manager.notificationHideDelayInMs
							}
							window.smart_manager.showNotification()
						}
					} else {
						window.smart_manager.notification = {
							message: sprintf(
								/* translators: %s: pricing page link */
								_x('This feature is available only in the %s version', 'modal content', 'smart-manager-for-wp-e-commerce'), '<a href="' + window.smart_manager.pricingPageURL + '" target="_blank">' + _x('Pro', 'modal content', 'smart-manager-for-wp-e-commerce') + '</a>'), hideDelay: window.smart_manager.notificationHideDelayInMs
						}
						window.smart_manager.showNotification()
					}
				}
			})
			.off('click', ".sm_scheduled_bulk_edits").on('click', ".sm_scheduled_bulk_edits", function (e) {
				window.open(window.smart_manager.scheduledActionAdminUrl, '_blank');
			})

			.off('mouseover', '.sm_gallery_image > img').on('mouseover', '.sm_gallery_image > img', function (e) {

				let params = {
					'current_cell_value': jQuery(this).attr('src'),
					'event': e,
					'title': ''
				};

				if (typeof (window.smart_manager.showImagePreview) !== "undefined" && typeof (window.smart_manager.showImagePreview) === "function") {
					window.smart_manager.showImagePreview(params);
				}

			})

			.off('mouseout', '.sm_gallery_image > img').on('mouseout', '.sm_gallery_image > img', function (e) {

				if (jQuery('#sm_img_preview').length > 0) {
					jQuery('#sm_img_preview').remove();
				}

			})

			// Code for handling the dropdown menu for the Duplicate and Bulk Edit button.
			.off('mouseenter', '.sm_beta_dropdown').on('mouseenter', '.sm_beta_dropdown', function () {
				jQuery(this).find('.sm_beta_dropdown_content').show();
			})

			// Code for handling the dropdown menu for the Duplicate and Bulk Edit button.
			.off('mouseleave', '.sm_beta_dropdown').on('mouseleave', '.sm_beta_dropdown', function () {
				jQuery(this).find('.sm_beta_dropdown_content').hide();
			})

			.off("click", ".sm_click_to_copy").on("click", ".sm_click_to_copy", function () {
				let temp = jQuery("<input>");
				jQuery("body").append(temp);
				temp.val(jQuery(this).html()).select();
				document.execCommand("copy");
				temp.remove();
			})
			.off("change", "#sm_show_tasks_lbl").on("change", "#sm_show_tasks_lbl", function () {
				jQuery('#sm_editor_grid').trigger('sm_show_tasks_change');
				if (0 == window.smart_manager.sm_beta_pro) {
					jQuery("#sm_show_tasks").prop('checked', false);
					window.smart_manager.notification = {
						message: sprintf(
							/* translators: %s: pricing page link */
							_x('This feature is available only in the %s version', 'modal content', 'smart-manager-for-wp-e-commerce'), '<a href="' + window.smart_manager.pricingPageURL + '" target="_blank">' + _x('Pro', 'modal content', 'smart-manager-for-wp-e-commerce') + '</a>'), hideDelay: window.smart_manager.notificationHideDelayInMs
					}
					window.smart_manager.showNotification()
				}
			})
			.off('click', '.sm-column-title-editor-icon').on('click', '.sm-column-title-editor-icon', function (e) {
				if (window.smart_manager.sm_beta_pro == 1 && 'undefined' !== typeof (window.smart_manager.displayColumnTitleEditor) && 'function' === typeof (window.smart_manager.displayColumnTitleEditor)) {
					window.smart_manager.displayColumnTitleEditor(e);
				} else {
					window.smart_manager.notification = {
						message: sprintf(
							/* translators: %s: pricing page link */
							_x('This feature is available only in the %s version', 'modal content', 'smart-manager-for-wp-e-commerce'), '<a href="' + window.smart_manager.pricingPageURL + '" target="_blank">' + _x('Pro', 'modal content', 'smart-manager-for-wp-e-commerce') + '</a>'), hideDelay: window.smart_manager.notificationHideDelayInMs
					}
					window.smart_manager.showNotification()
				}
			})
			//Code to handle access privilege settings
			.off('click', '#sm_access_privilege_settings').on('click', '#sm_access_privilege_settings', function (e) {
				e.preventDefault();
				if (0 == window.smart_manager.sm_beta_pro) {
					window.smart_manager.notification = { message: sprintf(_x('This feature is available only in the %s version', 'modal content', 'smart-manager-for-wp-e-commerce'), '<a href="' + window.smart_manager.pricingPageURL + '" target="_blank">' + _x('Pro', 'modal content', 'smart-manager-for-wp-e-commerce') + '</a>'), hideDelay: window.smart_manager.notificationHideDelayInMs }
					window.smart_manager.showNotification()
					return false;
				}
				if (typeof (window.smart_manager.showPannelDialog) !== "undefined" && typeof (window.smart_manager.showPannelDialog) === "function") {
					window.smart_manager.showPannelDialog(window.smart_manager.privilegeSettingsRoute)
				}
			})
			//Code to handle the general settings
			.off('click', '#sm_general_settings').on('click', '#sm_general_settings', function (e) {
				e.preventDefault();
				if (typeof (window.smart_manager.showPannelDialog) !== "undefined" && typeof (window.smart_manager.showPannelDialog) === "function") {
					window.smart_manager.showPannelDialog(window.smart_manager.settingsRoute)
				}
			})
			jQuery(document).on('click', '#sm_floating_save_bar .save-btn', function () {
				jQuery('#save_sm_editor_grid_btn').trigger('click');
				jQuery('.sm-notification-close').trigger('click');
			})
			jQuery(document).on('click', '#sm_floating_save_bar .close-btn', function () {
				jQuery('.sm-notification-close').trigger('click');
				window.smart_manager.dirtyRowColIds = {};
				window.smart_manager.getData();
			})

		jQuery(document).trigger('sm_event_handler');
	}
	//Function to equalize the enabled and disabled section height in column visibility dialog
	SmartManager.prototype.columnVisibilityEqualizeHeight = function () {
		let enabledHeight = jQuery('#sm-columns-enabled').height(),
			disabledHeight = jQuery('#sm-columns-disabled').height(),
			maxHeight = enabledHeight > disabledHeight ? enabledHeight : disabledHeight;

		if (maxHeight > 0) {
			jQuery('#sm-columns-enabled, #sm-columns-disabled').height(maxHeight);
		}
	}

	//Function to process search for unorder list of items from 'Column Manager' and Multilist field like 'Category' in inline edit
	SmartManager.prototype.processListSearch = function (eventObj) {
		let searchString = jQuery(eventObj).val(),
			ulId = jQuery(eventObj).attr('data-ul-id');
		if ('' !== ulId) {
			jQuery("#" + ulId).find('li').each(function () {
				let txtValue = jQuery(this).find('.sm-title-input').val();
				let isMatch = txtValue.toUpperCase().indexOf(searchString.toUpperCase()) > -1;
				(isMatch) ? jQuery(this).css("display", "block") : jQuery(this).hide();
				// Ensure that if a child is visible, its parent remains visible
				let isChildVisible = jQuery(this).find('li:visible').length > 0;
				let isMatched = ('none' !== jQuery(this).css('display'));
				if (isChildVisible || isMatched) {
					jQuery(this).css("display", "block").parents('ul, li').css("display", "block");
				}
			});
		}
	};

	//Function to create column Visibility dialog
	SmartManager.prototype.createColumnVisibilityDialog = function () {
		if ('undefined' === typeof (window.smart_manager.currentColModel)) {
			return;
		}
		let enabledColumnsArray = new Array(),
			hiddenColumnsArray = new Array(),
			colText = '',
			colVal = '',
			temp = '',
			panelContent = '';

		for (let key in window.smart_manager.currentColModel) {

			colObj = window.smart_manager.currentColModel[key];

			if (!colObj.hasOwnProperty('data')) {
				continue;
			}

			if (colObj.hasOwnProperty('allow_showhide') && true === colObj.allow_showhide) {

				colText = (colObj.hasOwnProperty('name_display')) ? colObj.name_display : '';
				colVal = (colObj.hasOwnProperty('data')) ? colObj.data : '';
				colPosition = (colObj.hasOwnProperty('position')) ? ((colObj.position != '') ? colObj.position - 1 : '') : '';


				temp = `<li>
						<span class="handle">::</span>
						<input type="text" class="sm-title-input" title="${colText}" value="${colText}" readonly />
						<span class="handle sm-column-title-editor-icon">
							<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"></path>
							</svg>
						</span>
						<input type="hidden" name="columns[]" class="js-column-key" value="${colVal}">
						<input type="hidden" name="columns_names[]" class="js-column-title" value="${colText}">
					</li>`;

				if (colObj.hasOwnProperty('hidden') && false === colObj.hidden) {
					enabledColumnsArray.push(temp);
				} else if (colObj.hasOwnProperty('hidden') && true === colObj.hidden) {
					hiddenColumnsArray.push(temp);
				}
			}
		}


		panelContent = '<form id="sm-column-visibility"> ' +
			'<ul class="unstyled-list"> ' +
			'<li> ' + _x('Drag the enabled columns to the right to disable them and vise-versa. Drag the columns top or bottom to rearrange their position in the grid.', 'columns settings description', 'smart-manager-for-wp-e-commerce') +
			'</li> ' +
			'<li style="margin-top: 1em;"> ' + sprintf(
				/* translators: %s: reset columns link */
				_x('Click %s to reset the Columns order to default.', 'columns settings description', 'smart-manager-for-wp-e-commerce'), '<a href="#" id="sm_reset_state" style="cursor:pointer;">' + _x('here', 'columns settings description', 'smart-manager-for-wp-e-commerce') + '</a>') +
			'</li> ' +
			'<li> ' +
			'<div class="sm-sorter-section"> ' +
			'<h3>' + _x('Enabled', 'columns settings searchbox heading', 'smart-manager-for-wp-e-commerce') + '</h3> ' +
			'<input type="text" id="searchEnabledColumns" data-ul-id="sm-columns-enabled" class="sm-search-box" onkeyup="window.smart_manager.processListSearch(this)" placeholder="' + _x('Search For Enabled Columns...', 'placeholder', 'smart-manager-for-wp-e-commerce') + '"> ' +
			'<ul class="sm-sorter columns-enabled" id="sm-columns-enabled"> ' +
			enabledColumnsArray.join("") +
			'</ul> ' +
			'</div> ' +
			'<div class="sm-sorter-section"> ' +
			'<h3>' + _x('Disabled', 'columns settings searchbox heading', 'smart-manager-for-wp-e-commerce') + '</h3> ' +
			'<input type="text" id="searchDisabledColumns" data-ul-id="sm-columns-disabled" class="sm-search-box" onkeyup="window.smart_manager.processListSearch(this)" placeholder="' + _x('Search For Disabled Columns...', 'placeholder', 'smart-manager-for-wp-e-commerce') + '"> ' +
			'<ul class="sm-sorter columns-disabled" id="sm-columns-disabled"> ' +
			hiddenColumnsArray.join("") +
			'</ul> ' +
			'</div> ' +
			'</li> ' +
			'</ul> ' +
			'<input type="hidden" value="" id="sm-all-enabled-columns"> ' +
			'</form> ';


		document.getElementById('column-settings').innerHTML = panelContent;

		if ("undefined" !== typeof (window.smart_manager.processColumnVisibility) && "function" === typeof (window.smart_manager.processColumnVisibility)) {
			window.smart_manager.processColumnVisibility;
		}

		if ("undefined" !== typeof (window.smart_manager.columnVisibilityEqualizeHeight) && "function" === typeof (window.smart_manager.columnVisibilityEqualizeHeight)) {
			window.smart_manager.columnVisibilityEqualizeHeight();
		}

		let $columns = document.getElementById('sm-columns-enabled'),
			$columnsDisabled = document.getElementById('sm-columns-disabled');

		window.smart_manager.enabledSortable = Sortable.create($columns, {
			group: 'smartManagerColumns',
			animation: 100,
			onSort: function (evt) {
				if ("undefined" !== typeof (window.smart_manager.columnsMoved) && "function" === typeof (window.smart_manager.columnsMoved)) {
					window.smart_manager.columnsMoved();
				}
			}
		});
		window.smart_manager.disabledSortable = Sortable.create($columnsDisabled, {
			group: 'smartManagerColumns',
			animation: 100
		});
	}

	//Function to block Bulk Edit/Duplicate Records/Delete Records functionality when background process is running
	SmartManager.prototype.backgroundProcessRunningNotification = function (isBackgroundProcessRunning = false) {
		isBackgroundProcessRunning = ("undefined" !== typeof (window.smart_manager.isBackgroundProcessRunning) && "function" === typeof (window.smart_manager.isBackgroundProcessRunning)) ? window.smart_manager.isBackgroundProcessRunning() : false;
		if (isBackgroundProcessRunning) {
			window.smart_manager.notification = { message: window.smart_manager.backgroundProcessRunningMessage, hideDelay: window.smart_manager.notificationHideDelayInMs }
			window.smart_manager.showNotification()
		}
		return isBackgroundProcessRunning;
	}

	//Function to update the list of enabled columns on column move event
	SmartManager.prototype.columnsMoved = function () {
		let enabled = jQuery('#sm-column-visibility').find('.columns-enabled .js-column-key');
		let allEnabled = enabled.map(function () {
			return jQuery(this).val();
		}).get().join(',');
		jQuery('#sm-column-visibility').find('#sm-all-enabled-columns').val(allEnabled);
		window.smart_manager.columnsVisibilityUsed = true;
	}

	//Function to load the updated list of enabled columns in the grid
	SmartManager.prototype.processColumnVisibility = function () {
		if (false === window.smart_manager.columnsVisibilityUsed && Object.keys(window.smart_manager.editedColumnTitles).length == 0) {
			return false;
		}

		let enabledColumns = jQuery('#sm-column-visibility').find('#sm-all-enabled-columns').val();

		if ('undefined' === typeof (enabledColumns) || 'undefined' === typeof (window.smart_manager.currentColModel)) {
			return;
		}

		if (enabledColumns.length > 0) {

			// let idKey = ( window.smart_manager.dashboardKey == 'user' ) ? 'users_id' : 'posts_id';
			// enabledColumns = idKey + ',' + enabledColumns;

			let enabledColumnsArray = enabledColumns.split(','),
				colVal = '',
				position = 0;

			window.smart_manager.column_names = [];
			window.smart_manager.currentVisibleColumns = [];

			for (let key in window.smart_manager.currentColModel) {

				colObj = window.smart_manager.currentColModel[key];

				if (colObj.hasOwnProperty('allow_showhide') && true === colObj.allow_showhide) {
					colVal = (colObj.hasOwnProperty('data')) ? colObj.data : '';

					if (enabledColumnsArray.indexOf(colVal) != -1) {

						position = enabledColumnsArray.indexOf(colVal) + 1;

						window.smart_manager.currentColModel[key].hidden = false; //Code for refreshing the column visibility
						window.smart_manager.currentColModel[key].position = position; //Code for refreshing the column position

					} else {
						window.smart_manager.currentColModel[key].hidden = true;
					}
				}
			}

			if ("undefined" !== typeof (window.smart_manager.sortColumns) && "function" === typeof (window.smart_manager.sortColumns)) {
				window.smart_manager.sortColumns();
			}
		}

		if (enabledColumns.length > 0 || Object.keys(window.smart_manager.editedColumnTitles).length > 0) {

			let index = 0;

			window.smart_manager.currentColModel.forEach(function (colObj) {

				let hidden = ('undefined' !== typeof (colObj.hidden)) ? colObj.hidden : true,
					data = (colObj.hasOwnProperty('data')) ? colObj.data : '';

				// COde for updating the column titles
				if (Object.keys(window.smart_manager.editedColumnTitles).length > 0 && data && window.smart_manager.editedColumnTitles.hasOwnProperty(data)) {
					colObj.name = colObj.key = colObj.name_display = window.smart_manager.editedColumnTitles[data]
				}

				if (false === hidden) {
					if (false === colObj.hasOwnProperty('name_display')) {// added for state management
						colObj.name_display = name;
					}
					let name = ('undefined' !== typeof (colObj.name)) ? colObj.name.trim() : '';

					window.smart_manager.column_names[index] = colObj.name_display; //Array for column headers
					window.smart_manager.currentVisibleColumns[index] = colObj;

					index++;
				}
			});

			//code to trigger update state ajax call
			if ("undefined" !== typeof (window.smart_manager.updateState) && "function" === typeof (window.smart_manager.updateState)) {
				let params = { refreshDataModel: true, async: true };
				if (window.smart_manager.isViewAuthor) {
					params.updateView = true
				}
				window.smart_manager.isColumnModelUpdated = true
				window.smart_manager.showLoader();
				window.smart_manager.updateState(params); //refreshing the dashboard states

				if ("undefined" !== typeof (window.smart_manager.refreshColumnsTitleAttribute) && "function" === typeof (window.smart_manager.refreshColumnsTitleAttribute)) {
					setTimeout(() => {
						window.smart_manager.refreshColumnsTitleAttribute();
					}, 1000);
				}
			}
		}
	}

	SmartManager.prototype.sortColumns = function () {
		if (typeof window.smart_manager.currentColModel == 'undefined') {
			return;
		}
		window.smart_manager.indexPointer = 0;
		let enabledColumns = new Array(),
			disabledColumns = new Array();
		enabledColumnsFinal = new Array();
		window.smart_manager.currentColModel.forEach(function (colObj) {
			enabled = 0;
			if (colObj.hasOwnProperty('position') != false && colObj.hasOwnProperty('hidden') != false) {
				if (colObj.position != '' && colObj.hidden === false) {
					enabledColumns[colObj.position] = colObj;
					enabled = 1;
				}
			}
			if (enabled == 0) {
				disabledColumns.push(colObj);
			}
		});
		enabledColumns.forEach(function (colObj) { //done this to re-index the array for proper array length
			enabledColumnsFinal.push(colObj);
		});
		enabledColumnsFinal.sort(function (a, b) {
			return parseInt(a.position) - parseInt(b.position);
		});
		window.smart_manager.currentColModel = enabledColumnsFinal.concat(disabledColumns);
	}

	SmartManager.prototype.formatDashboardColumnModel = function (column_model) {
		SaCommonManager.prototype.formatDashboardColumnModel.call(this, column_model);
		if (window.smart_manager.currentColModel == '' || typeof (window.smart_manager.currentColModel) == 'undefined') {
			return;
		}
		if (typeof (window.smart_manager.sortColumns) !== "undefined" && typeof (window.smart_manager.sortColumns) === "function") {
			window.smart_manager.sortColumns();
		}
		for (i = 0; i < window.smart_manager.currentColModel.length; i++) {
			window.smart_manager.currentColModel[i].wordWrap = true;
		}
		jQuery('#sm_editor_grid').trigger('smart_manager_post_format_columns'); //custom trigger
	}

	//Function to get the seleted IDs
	SmartManager.prototype.getSelectedKeyIds = function () {
		let idKey = window.smart_manager.getKeyID(),
			selectedIds = [];
		window.smart_manager.selectedRows.forEach((rowId) => {
			selectedIds.push(window.smart_manager.currentDashboardData[rowId][idKey]);
		})
		return selectedIds;
	}
	//Function to show columns menu

	SmartManager.prototype.refreshIsViewAuthor = function (viewSlug) {
		let params = {};
		params.data_type = 'json';
		params.data = {
			cmd: 'is_view_author',
			module: 'custom_views',
			active_module: viewSlug,
			security: window.smart_manager.saCommonNonce,
			slug: viewSlug,
		};
		window.smart_manager.sendRequest(params, function (response) {
			window.smart_manager.isViewAuthor = (response) ? true : false
			window.smart_manager.displayShowHideColumnSettings(response);
		});
	}

	//Function to delete records
	SmartManager.prototype.deleteRecords = function () {

		if (window.smart_manager.selectedRows.length == 0 && !window.smart_manager.selectAll) {
			return;
		}

		let params = {};
		params.data = {
			cmd: 'delete',
			active_module: window.smart_manager.dashboardKey,
			security: window.smart_manager.saCommonNonce,
			ids: JSON.stringify(window.smart_manager.getSelectedKeyIds())
		};

		window.smart_manager.sendRequest(params, function (response) {
			if ('failed' !== response) {
				if (jQuery('.sm_top_bar_action_btns #del_sm_editor_grid svg').hasClass('sm-ui-state-disabled') === false) {
					jQuery('.sm_top_bar_action_btns #del_sm_editor_grid svg').addClass('sm-ui-state-disabled');
				}
				window.smart_manager.refresh();
				window.smart_manager.notification = { status: 'success', message: response }
				window.smart_manager.showNotification()
			}
		});
	}

	SmartManager.prototype.updateLitePromoMessage = function (countRows) {
		let count = parseInt(countRows);
		if (count >= 2) {
			jQuery('.sm_design_notice .sm_sub_headline.action').hide();
			jQuery('.sm_design_notice .sm_sub_headline.response').show();
		}
	}

	//Function to save inline edited data
	SmartManager.prototype.saveData = function () {
		if (Object.getOwnPropertyNames(window.smart_manager.editedData).length <= 0) {
			return;
		}
		let params = {};
		params.data = {
			cmd: 'inline_update',
			active_module: window.smart_manager.dashboardKey,
			edited_data: JSON.stringify(window.smart_manager.editedData),
			security: window.smart_manager.saCommonNonce,
			pro: ((typeof (window.smart_manager.sm_beta_pro) != 'undefined') ? window.smart_manager.sm_beta_pro : 0),
			table_model: (window.smart_manager.currentDashboardModel.hasOwnProperty('tables')) ? window.smart_manager.currentDashboardModel.tables : '',
			is_advanced_search: jQuery('#search_switch').is(':checked')
		};
		params.data = ("undefined" !== typeof (window.smart_manager.addTasksParams) && "function" === typeof (window.smart_manager.addTasksParams) && 1 == window.smart_manager.sm_beta_pro) ? window.smart_manager.addTasksParams(params.data) : params.data;
		let hasInvalidClass = jQuery('.sm-grid-dirty-cell').hasClass('htInvalid');
		if (hasInvalidClass == false) {
			window.smart_manager.sendRequest(params, function (response) {
				if ('failed' !== response) {
					let title = 'success'
					if (window.smart_manager.isJSON(response)) {
						// title = 'note'
						response = JSON.parse(response);
						msg = response.msg;
					} else {
						msg = response;
					}
					if (('undefined' === typeof (window.smart_manager.sm_beta_pro) || ('undefined' !== typeof (window.smart_manager.sm_beta_pro) && 1 != window.smart_manager.sm_beta_pro))) {
						if (typeof (response.sm_inline_update_count) != 'undefined') {
							if ("undefined" !== typeof (window.smart_manager.updateLitePromoMessage) && "function" === typeof (window.smart_manager.updateLitePromoMessage)) {
								window.smart_manager.updateLitePromoMessage(response.sm_inline_update_count);
							}
						}
					}
					if (window.smart_manager.editedCellIds.length > 0) {
						for (let i = 0; i < window.smart_manager.editedCellIds.length; i++) {
							colProp = window.smart_manager.hot.getCellMeta(window.smart_manager.editedCellIds[i].row, window.smart_manager.editedCellIds[i].col);
							currentClassName = (colProp.hasOwnProperty('className')) ? colProp.className : '';
							if (currentClassName.indexOf('sm-grid-dirty-cell') != -1) {
								currentClassName = currentClassName.substr(0, currentClassName.indexOf('sm-grid-dirty-cell'));
							}
							window.smart_manager.hot.setCellMeta(window.smart_manager.editedCellIds[i].row, window.smart_manager.editedCellIds[i].col, 'className', currentClassName);
							jQuery('.smCheckboxColumnModel input[data-row=' + window.smart_manager.editedCellIds[i].row + ']').parents('tr').removeClass('sm_edited');
						}
						// Code to get modified page nos.
						let modifiedPageNumbers = new Set()
						window.smart_manager.modifiedRows.map((rowNo) => {
							modifiedPageNumbers.add(Math.ceil(((rowNo + 1) / window.smart_manager.limit)))
						})
						window.smart_manager.dirtyRowColIds = {};
						window.smart_manager.editedData = {};
						window.smart_manager.modifiedRows = new Array();
						modifiedPageNumbers.forEach(r => window.smart_manager.getData({ refreshPage: r }));
						window.smart_manager.isRefreshingLoadedPage = false;
					}
					window.smart_manager.hot.render();
					if (('undefined' !== typeof (window.smart_manager.sm_beta_pro) && 1 != window.smart_manager.sm_beta_pro) &&
						(response.hasOwnProperty('modal_message') && response.modal_message.trim() !== '') &&
						sm_beta_params.hasOwnProperty('manHoursData') && ('success' === title)) {
						window.smart_manager.showManHoursSaved({ message: response.modal_message, title: msg });
					} else {
						window.smart_manager.notification = { message: msg }
						if ('success' === title) {
							window.smart_manager.notification.status = title
						}
						window.smart_manager.showNotification()
					}
				}
			});
		} else {
			window.smart_manager.notification = { status: 'error', message: _x('You have entered incorrect data in the highlighted cells.', 'notification', 'smart-manager-for-wp-e-commerce') }
			window.smart_manager.showNotification()
		}
	}

	SmartManager.prototype.hideNotificationDialog = function () {
		jQuery("#sm_inline_dialog").dialog("close");
	}

	//Function to show notification messages
	SmartManager.prototype.showNotificationDialog = function (title = '', content = '', dlgparams = {}) {

		window.smart_manager.modal = {
			title: (title) ? title : _x('Note', 'modal title', 'smart-manager-for-wp-e-commerce'),
			content: (content) ? content : sprintf(
				/* translators: %s: pricing page link */
				_x('This feature is available only in the %s version', 'modal content', 'smart-manager-for-wp-e-commerce'), '<a href="' + window.smart_manager.pricingPageURL + '" target="_blank">' + _x('Pro', 'modal content', 'smart-manager-for-wp-e-commerce') + '</a>'),
			autoHide: (dlgparams.hasOwnProperty('autoHide')) ? dlgparams.autoHide : false
		}
		window.smart_manager.showModal()
	}

	//Function to show confirm dialog
	SmartManager.prototype.showConfirmDialog = function (params) {

		window.smart_manager.modal = {
			title: (params.hasOwnProperty('title') !== false && params.title != '') ? params.title : _x('Warning', 'modal title', 'smart-manager-for-wp-e-commerce'),
			content: (params.hasOwnProperty('content') !== false && params.content != '') ? params.content : _x('Are you sure?', 'modal content', 'smart-manager-for-wp-e-commerce'),
			autoHide: false,
			showCloseIcon: (params.hasOwnProperty('showCloseIcon')) ? params.showCloseIcon : true,
			modalClass: params?.modalClass || '',
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
		window.smart_manager.showModal()
	}

	SmartManager.prototype.getCurrentDashboardState = function () {
		let tempDashModel = JSON.parse(JSON.stringify(window.smart_manager.currentDashboardModel));
		let tempColModel = JSON.parse(JSON.stringify(window.smart_manager.currentColModel));

		if (!Array.isArray(tempColModel)) {
			tempColModel = []
		}

		tempDashModel.columns = new Array();
		tempColModel.forEach(function (colObj) {
			if (typeof (colObj.hidden) != 'undefined' && colObj.hidden === false) {
				tempDashModel.columns.push(colObj);
			}
		});
		let dashboardState = { 'columns': tempDashModel.columns, 'sort_params': tempDashModel.sort_params };
		let viewSlug = window.smart_manager.getViewSlug(window.smart_manager.dashboardName);
		if (viewSlug) {
			dashboardState['search_params'] = {
				'isAdvanceSearch': ((window.smart_manager.advancedSearchQuery.length > 0) ? 'true' : 'false'),
				'params': ((window.smart_manager.advancedSearchQuery.length > 0) ? window.smart_manager.advancedSearchQuery : window.smart_manager.simpleSearchText)
			}
		}
		return JSON.stringify(dashboardState);
	}

	SmartManager.prototype.refreshDashboardStates = function () {
		window.smart_manager.dashboardStates[window.smart_manager.dashboardKey] = window.smart_manager.getCurrentDashboardState();
	}

	//Function to handle the state apply at regular intervals
	SmartManager.prototype.updateState = function (refreshParams = {}) {
		let viewSlug = window.smart_manager.getViewSlug(window.smart_manager.dashboardName);
		if (1 == window.smart_manager.sm_beta_pro && viewSlug && !refreshParams.updateView) { //Added for skipping `save_state` when simply switching from custom views dashboards
			window.smart_manager.refresh();
			return;
		}
		// do not refresh the states if view
		if ("undefined" !== typeof (window.smart_manager.refreshDashboardStates) && "function" === typeof (window.smart_manager.refreshDashboardStates)) {
			window.smart_manager.refreshDashboardStates(); //refreshing the dashboard states
		}
		if (Object.getOwnPropertyNames(window.smart_manager.dashboardStates).length <= 0) {
			return;
		}
		//Ajax request to update the dashboard states
		let params = {};
		params.data_type = 'json';
		params.data = {
			cmd: 'save_state',
			security: window.smart_manager.saCommonNonce,
			active_module: window.smart_manager.dashboardKey,
			dashboard_states: window.smart_manager.dashboardStates
		};
		// Code for passing extra param for view handling
		if (1 == window.smart_manager.sm_beta_pro) {
			params.data['is_view'] = 0;
			if (viewSlug && (refreshParams.updateView || false)) {
				params.data['is_view'] = 1;
				params.data['active_module'] = viewSlug;
			}
			// Flag for handling taxonomy dashboards
			params.data['is_taxonomy'] = window.smart_manager.isTaxonomyDashboard();
			// code for handling tasks of the current dashboard
			if (refreshParams && 'undefined' !== typeof (refreshParams.isTasksEnabled)) {
				params.data['isTasks'] = refreshParams.isTasksEnabled;
			}
			// Code for handling renaming of columns
			if (Object.keys(window.smart_manager.editedColumnTitles).length > 0) {
				params.data['edited_column_titles'] = window.smart_manager.editedColumnTitles;
			}
		}
		params.showLoader = false;
		if (refreshParams && 'undefined' !== typeof (refreshParams.async)) {
			params.async = refreshParams.async;
		}
		window.smart_manager.sendRequest(params, function (refreshParams, response) {
			window.smart_manager.dashboardStates = {};
			if (refreshParams) {
				if ('undefined' !== typeof (refreshParams.refreshDataModel)) {
					window.smart_manager.refresh();
				}
			}
		}, refreshParams);
	}

	// Function to determine if the selected dashhboard is a taxonomy dashboard or not
	SmartManager.prototype.isTaxonomyDashboard = function () {
		let viewSlug = window.smart_manager.getViewSlug(window.smart_manager.dashboardName);
		return (window.smart_manager.allTaxonomyDashboards[(window.smart_manager.viewPostTypes.hasOwnProperty(viewSlug)) ? window.smart_manager.viewPostTypes[viewSlug] : window.smart_manager.dashboardKey]) ? 1 : 0
	}

	// Function to get keyId for the dashboard
	SmartManager.prototype.getKeyID = function () {
		let ordersPostTypes = ['shop_order', 'shop_subscription'];
		switch (true) {
			case ("undefined" !== typeof (window.smart_manager.isTasksEnabled) && "function" === typeof (window.smart_manager.isTasksEnabled) && (1 === window.smart_manager.isTasksEnabled()) || ('product_stock_log' === window.smart_manager.dashboardKey)):
				return 'sm_tasks_id'
			case (('undefined' !== typeof window.smart_manager.taxonomyDashboards[window.smart_manager.dashboardKey]) || (('undefined' !== typeof window.smart_manager.viewPostTypes[window.smart_manager.dashboardKey]) && (('undefined' !== typeof window.smart_manager.taxonomyDashboards[window.smart_manager.viewPostTypes[window.smart_manager.dashboardKey]])))):
				return 'terms_term_id'
			case ('user' === window.smart_manager.dashboardKey):
				return 'users_id'
			case ((ordersPostTypes.includes(window.smart_manager.dashboardKey) || ordersPostTypes.includes(window.smart_manager.viewPostTypes[window.smart_manager.dashboardKey])) && ("undefined" !== typeof (window.smart_manager.sm_is_woo79)) && ('true' === window.smart_manager.sm_is_woo79)):
				return 'wc_orders_id'
			default:
				return 'posts_id';
		}
	}

	// Function to save settings
	SmartManager.prototype.saveSettings = function (settings = {}, settingsDialogObj = {}) {
		if (0 == Object.keys(settings).length || (0 < Object.keys(settings).length && !settings.hasOwnProperty('general'))) {
			return;
		}
		let params = {};
		params.data = {
			cmd: 'save_settings',
			active_module: 'smart_manager_settings',
			settings: JSON.stringify(settings),
			security: window.smart_manager.saCommonNonce,
			pro: ('undefined' !== typeof (window.smart_manager.sm_beta_pro)) ? window.smart_manager.sm_beta_pro : 0
		};
		params.data_type = 'json'
		window.smart_manager.sendRequest(params, function (response) {
			let ack = (response.hasOwnProperty('ACK')) ? response.ACK : ''
			if ('Success' === ack) {
				settingsDialogObj.closeDialog();
				window.smart_manager.notification = {
					status: 'success', message:
						_x('Settings saved successfully!', 'notification', 'smart-manager-for-wp-e-commerce')
				}
				window.smart_manager.showNotification()
				setTimeout(function () {
					location.reload();
				}, 2000);
			}
			if ('Failure' === ack && response.hasOwnProperty('msg')) {
				window.smart_manager.notification = {message: response.msg, route: 'settings'}
				window[pluginKey].showPannelDialog('settings')
			}
		});
	};

	// Function to change export CSV button text.
	SmartManager.prototype.exportButtonHtml = function () {
		if (document.getElementById('sm_export_csv') !== null) {
			document.getElementById('sm_export_csv').innerHTML = `
		<a id="sm_export_selected_records" href="#">${_x('Selected Records', 'export button', 'smart-manager-for-wp-e-commerce')}</a>
		<a id="sm_export_entire_store" class="sm_entire_store" href="#">${_x('Entire Store', 'export button', 'smart-manager-for-wp-e-commerce')}</a>
		${window.smart_manager.current_selected_dashboard === 'shop_order'?`
			<a id="sm_schedule_export" class="sm_schedule_export_btns" href="#">${_x('Schedule Export', 'schedule export button', 'smart-manager-for-wp-e-commerce')}</a>
			<a id="sm_manage_schedule_export" class="sm_schedule_export_btns" target="_blank" href="${window.smart_manager?.scheduledExportActionAdminUrl || ''}">${_x('Manage Scheduled Exports', 'manage scheduled exports button', 'smart-manager-for-wp-e-commerce')}</a>`:''
		}`;
		}
	}

	//Function to show the Select2 Childs for navbar dashboard select2.
	SmartManager.prototype.showSelect2Childs = function (parentID = '', parentElement = '') {
		// Check if dashboardSelect2Items exists.
		if ((!window.smart_manager.hasOwnProperty('dashboardSelect2Items')) || (!parentID) || (typeof parentID === 'undefined') || (parentID.length === 0) || (!parentElement) || (typeof parentElement === 'undefined') || (parentElement.length === 0)) {
			return;
		}
		parentElement.addClass("focus")
		let parent = window.smart_manager.dashboardSelect2Items.find((d) => d.id === parentID);
		if ((!parent) || (typeof parentID === 'undefined') || (parentID.length === 0)) {
			return;
		}
		let childs_section = jQuery("#sm_select2_childs_section");
		if ((!childs_section) || (typeof childs_section === 'undefined') || (childs_section.length === 0)) {
			return;
		}
		childs_section.html(""); // Clear previous content.
		// Get the value from the search field.
		let searchValue = jQuery(".select2-search__field").val().trim().toLowerCase();
		// Filter children based on the search value if it exists, if not them display all childrens of the parent.
		let select2SearchResult = window.smart_manager.findSelect2ParentOrChildByText(jQuery("#sm_dashboard_select").val(), false);
		let selectedChildID = select2SearchResult.hasOwnProperty('childID') ? select2SearchResult.childID : ''; //this is to highlight the current selected child, ie. current dashboard element
		let matchingChildren = (searchValue && searchValue !== '') ? parent.children.filter((child) => child.text.toLowerCase().includes(searchValue)) : parent.children;
		if ((!matchingChildren) || (typeof matchingChildren === 'undefined') || (matchingChildren.length === 0)) {
			matchingChildren = parent.children;
		}
		if (matchingChildren.length) {
			let nestedList = jQuery("<ul>").addClass("nested-list");
			matchingChildren.forEach((child) => {
				let childElement = jQuery("<li>").html(`<div class="dashboard-name">${child.text}</div>`).addClass("select2-child-item").attr("data-id", child.id);
				if (selectedChildID === child.id) {
					childElement.addClass("selected")
				}
				if (window.smart_manager.findSavedSearchBySlug(child.id)) {
					let savedSearchActions = jQuery("<div>").addClass("dashboard-combobox-saved-search-actions");
					savedSearchActions.html(`<div class="dashboard-combobox-saved-search-action dashboard-combobox-saved-search-delete" view_slug="${child.id}" title="Delete" view_name="${child.text}"><svg class="sm-error-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></div>`)
					childElement.append(savedSearchActions);
				}
				nestedList.append(childElement);
			});
			childs_section.addClass("visible").append(nestedList);
		} else {
			childs_section.removeClass("visible");
		}
		// Position the childs_section beside the hovered parent element.
		let offset = parentElement.offset();
		let rootFontSize = parseFloat(getComputedStyle(document.documentElement).fontSize);
		childs_section.css({
			top: `${(offset.top) / rootFontSize}rem`,
			left: `${(offset.left + parentElement.outerWidth() + 2) / rootFontSize}rem`,
		});
	};

	//Function to find navbar dashboard select2 parent or child elements based on matching text.
	SmartManager.prototype.findSelect2ParentOrChildByText = function (ParentOrChildText = '', matchExactChild = false) {
		if ((!ParentOrChildText) || (typeof ParentOrChildText === 'undefined') || (ParentOrChildText.length === 0) || (!window.smart_manager.hasOwnProperty('dashboardSelect2Items'))) {
			return false;
		}
		ParentOrChildText = ParentOrChildText.trim().toLowerCase();
		let parentID = window.smart_manager.dashboardSelect2Items.find((item) => { return item.text.trim().toLowerCase().startsWith(ParentOrChildText) })?.id;
		let childID = '';
		let childText = '';
		// If matching parent is not found, search within children.
		if ((!parentID) || (typeof parentID === 'undefined') || (parentID.length === 0)) {
			window.smart_manager.dashboardSelect2Items.some((item) => {
				let matchingChild = item.children.find((child) => {
					if (matchExactChild) {
						if ((child.id === ParentOrChildText) || (child.text === ParentOrChildText)) {
							childID = child.id;
							childText = child.text;
							return true;
						}
					} else {
						if ((child.text.trim().toLowerCase().includes(ParentOrChildText)) || (child.id.includes(ParentOrChildText.toLowerCase()))) {
							childID = child.id;
							childText = child.text;
							return true;
						}
					}
					return false;
				});
				if (matchingChild) {
					parentID = item.id;
					return true;
				}
				return false;
			});
		}
		return { parentID, childID, childText };
	}

	//Function to Check if a saved search with a specific slug exists.
	SmartManager.prototype.findSavedSearchBySlug = function (slug = "") {
		if ((parseInt(window.smart_manager.sm_beta_pro) !== 1) || (!window.smart_manager.hasOwnProperty("savedSearches")) || (!Array.isArray(window.smart_manager.savedSearches)) || (!slug.length)) {
			return false;
		}
		return (window.smart_manager.savedSearches.find((item) => item.hasOwnProperty("slug") && item.slug === slug) || false);
	}

	SmartManager.prototype.hideElementOnClickOutside = function (event = {}, elementId = "") {
		if (!event || typeof event !== "object" || !event.target || !elementId) {
			return;
		}
		let element = document.getElementById(elementId);
		if (!element) {
			return;
		}
		if (!element.contains(event.target)) {
			element?.classList?.add("hidden")
		}
	};

	// Function to display disable error message.
	SmartManager.prototype.disableErrorMessage = function (disableErrorMessage = '') {
		if (!disableErrorMessage) {
			return;
		}
		window.smart_manager.notification = { status: 'error', message: disableErrorMessage, hideDelay: window.smart_manager.notificationHideDelayInMs }
		window.smart_manager.showNotification()
	}
	SmartManager.prototype.showSavePrompt = function () {
		let recordsCount = parseInt(Object.keys(window.smart_manager.dirtyRowColIds).length);
		window.smart_manager.notification = {
			message: `
			<div id='sm_floating_save_bar' class="flex-align-center">
				<span class="mr-3">${_x(`You've edited ${recordsCount} ${(recordsCount===1)?"record":"records"}`,'save changes text','smart-manager-for-wp-e-commerce')}.</span>
				<button class='button button-large close-btn hover:text-gray-700 bg-gray-300' style="background-color:#d2d6dc;" type='button'>${_x('Discard Changes', 'undo all button', 'smart-manager-for-wp-e-commerce')}</button>
				<button class='ml-4 button button-large bg-indigo-600 text-white hover:bg-indigo-500 focus:outline-none focus:shadow-outline-indigo save-btn' type='button'>${_x('Save Changes', 'save changes button', 'smart-manager-for-wp-e-commerce')}</button>
			</div>`,
			status: 'warning400',
			autoHide: false,
			hideIcon: true,
			customClass: 'sm-save-changes-notification',
		}
		window.smart_manager.showNotification();
	}
	let instance = new SmartManager();
	// Attach to window using dynamic pluginKey
	window[instance.pluginKey] = instance;
	if(typeof window.SmartManager === 'undefined'){
		window.SmartManager = SmartManager;
	}
	if(typeof window.smart_manager === 'undefined'){
		window.smart_manager = instance;
	}
	if(typeof window.SaCommonInstance === 'undefined'){
		window.SaCommonInstance = instance;
	}
})(window);

String.prototype.capitalize = function () {
	return this.charAt(0).toUpperCase() + this.slice(1);
}
//Events to be handled on document ready
jQuery(document).ready(function () {
	jQuery("body").append('<div id="sm_select2_childs_section"></div>');
	if ('#!/pricing' != document.location.hash) {
		window.smart_manager.init();
	}
	jQuery(document)
		.on('select2:open', function (event) {
			if (event.target.id === 'sm_dashboard_select') {
				setTimeout(() => {
					let select2SearchResult = window.smart_manager.findSelect2ParentOrChildByText(event.target.value, true);
					let select2ParentId = (!select2SearchResult || !select2SearchResult.hasOwnProperty('parentID')) ? false : select2SearchResult.parentID;
					if (!select2ParentId) {
						return;
					}
					let Select2ParentElement = jQuery(`span#${select2ParentId}`).parent(".select2-results__group");
					if ((!Select2ParentElement) || (!Select2ParentElement.length)) {
						return;
					}
					window.smart_manager.showSelect2Childs(select2ParentId, Select2ParentElement);
				}, 10);
				jQuery("#sm_select2_childs_section").addClass("visible");
			}
			jQuery('.select2-search__field').focus();
		})
		.on('click', '#select2-sm_dashboard_select-container', function (event) {
			if (!(jQuery("#sm_select2_childs_section").hasClass("visible"))) {
				jQuery('#sm_select2_childs_section').removeClass("visible");
			}
		})
		.on('click', function (event) {
			if (!jQuery(event.target).closest('.select2-container').length && !jQuery(event.target).closest('#sm_select2_childs_section').length) {
				jQuery('#sm_select2_childs_section').removeClass("visible");
			}
		})
		.on('select2:close', function (event) {
			//not hiding #sm_select2_childs_section here because click event will not work on this.
			jQuery("#sm_select2_childs_section").removeClass("visible");
		})
		// Prevent closing dashboard select2 if mouse is over select2_childs_section scroller part.
		jQuery('#sm_dashboard_select').on('select2:closing', function (event) {
			if (jQuery('#sm_select2_childs_section:hover').length > 0 && !jQuery('#sm_select2_childs_section .select2-child-item:hover').length > 0) {
				event.preventDefault();
			}
		});
});

jQuery.widget('ui.dialog', jQuery.extend({}, jQuery.ui.dialog.prototype, {
	_title: function (title) {
		let $title = this.options.title || '&nbsp;'
		if (('titleIsHtml' in this.options) && this.options.titleIsHtml == true)
			title.html($title);
		else title.text($title);
	}
}));

//Code for custom rendrers and extending Handsontable
(function(Handsontable){
	  let defaultTextEditor = Handsontable.editors.TextEditor.prototype.extend();

	//Function to override the SelectEditor function to handle color codes
    Handsontable.editors.SelectEditor.prototype.prepare = function () {

      	// Call the original prepare method
      	Handsontable.editors.BaseEditor.prototype.prepare.apply(this, arguments);

      	let _this2 = this,
      		selectOptions = this.cellProperties.selectOptions,
      		colorCodes = ( typeof(this.cellProperties.colorCodes) != 'undefined' ) ? this.cellProperties.colorCodes : '',
      		options = '';

			if (typeof selectOptions === 'function') {
				options = this.prepareOptions(selectOptions(this.row, this.col, this.prop));
			} else {
		    	options = this.prepareOptions(selectOptions);
		  	}

	      	this.select.innerHTML = '';

	      	Object.entries(options).forEach(([key, value]) => {
				let optionElement = document.createElement('OPTION');
					optionElement.value = key;

				if( colorCodes != ''  ) {
					for( let color in colorCodes ) {
						if( colorCodes[color].indexOf(key) != -1 ) {
							optionElement.className = 'sm_beta_select_'+color;
							break;
						}
					}
				}

				optionElement.innerHTML = value;
				_this2.select.appendChild(optionElement);
			});
	};

	SmartManager.prototype.dateEditor = function(currObj, arguments, format = 'Y-m-d H:i:s', placeholder = 'YYYY-MM-DD HH:MM:SS', cssClass = 'htDateTimeEditor', showDatetimeField = true) {
      // Call the original createElements method
      Handsontable.editors.TextEditor.prototype.createElements.apply(currObj, arguments);

      // Create datepicker input and update relevant properties
      currObj.TEXTAREA = document.createElement('input');
      currObj.TEXTAREA.setAttribute('type', (showDatetimeField === false) ? 'date' : 'datetime-local');
      currObj.TEXTAREA.className = cssClass;
      currObj.textareaStyle = currObj.TEXTAREA.style;
      currObj.textareaStyle.width = 0;
      currObj.textareaStyle.height = 0;

	  currObj.TEXTAREA.setSelectionRange = false;
      // Replace textarea with datepicker
      Handsontable.dom.empty(currObj.TEXTAREA_PARENT);
      currObj.TEXTAREA_PARENT.appendChild(currObj.TEXTAREA);
	  jQuery('.'+cssClass).attr('placeholder',placeholder);
    };

	function customNumericTextEditor(query, callback) {
	    // ...your custom logic of the validator

	    RegExp.escape= function(s) {
		    return s.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
		};

	    let regx = new RegExp("^[0-9]*"+ RegExp.escape(window.smart_manager.wooPriceDecimalSeparator) +"?[0-9]*$");

	    if (regx.test(query)) {
	      callback(true);
	    } else {
	      callback(false);
	    }
	  }

	  function customPhoneTextEditor(value, callback) {
	    // ...your custom logic of the validator
	   	if (/^(\d|\-|\+|\.|\(|\)|\ )*$/.test(value)) {
        	callback(true);
      	} else {
        	callback(false);
      	}
	  }

	  // Register an alias
	  Handsontable.validators.registerValidator('customNumericTextEditor', customNumericTextEditor);
	  Handsontable.validators.registerValidator('customPhoneTextEditor', customPhoneTextEditor);


	  let dateTimeEditor = Handsontable.editors.TextEditor.prototype.extend(),
	  		dateEditor = Handsontable.editors.TextEditor.prototype.extend(),
	  		timeEditor = Handsontable.editors.TextEditor.prototype.extend(),
			customNumericEditor = Handsontable.editors.NumericEditor.prototype.extend();

			customNumericEditor.prototype.createElements = function() {
				// Call the original createElements method
				Handsontable.editors.NumericEditor.prototype.createElements.apply(this, arguments);

				// Create number input and update relevant properties
				this.TEXTAREA = document.createElement('input');
				this.TEXTAREA.setAttribute('type', ((0 === window.smart_manager.useNumberFieldForNumericCols) ? 'text' : 'number'));

				 // Replace textarea with number
				 Handsontable.dom.empty(this.TEXTAREA_PARENT);
				 this.TEXTAREA_PARENT.appendChild(this.TEXTAREA);
			}

			Handsontable.editors.registerEditor('customNumericEditor', customNumericEditor);


        dateTimeEditor.prototype.createElements = function() { window.smart_manager.dateEditor( this, arguments ) };
        dateEditor.prototype.createElements = function() { window.smart_manager.dateEditor( this, arguments, 'Y-m-d', 'YYYY-MM-DD', 'htDateEditor', false ) };
        timeEditor.prototype.createElements = function() { window.smart_manager.dateEditor( this, arguments, 'H:i', 'HH:MM', 'htTimeEditor' ) };

        function numericRenderer(hotInstance, td, row, col, prop, value, cellProperties) {
		    Handsontable.renderers.NumericRenderer.apply(this, arguments);
			let currentRowData = (Object.keys(window.smart_manager.currentDashboardData).length > 0 && window.smart_manager.currentDashboardData.hasOwnProperty(row)) ? window.smart_manager.currentDashboardData[row] : {};
		    let colObj = ( window.smart_manager.currentVisibleColumns.indexOf(col) != -1 ) ? window.smart_manager.currentVisibleColumns[col] : {};

		    if( !value && '' === value && null === value ) {
		    	value = parseFloat(value);
		    	value = ( colObj.hasOwnProperty('decimalPlaces') ) ? value.toFixed( parseInt( colObj.decimalPlaces ) ) : value;
		    }

		    if(!value || value === '' || value == null || value === 0 || value === 0.00 || value === '0' || value === '0.00' ) {
		        td.innerHTML = '<div class="wrapper htRight htNumeric htNoWrap">' + value + '</div>';
		    } else {
		    	td.innerHTML = '<div title="'+ td.innerHTML +'" class="wrapper">' + td.innerHTML + '</div>';
		    }
			// Code for handling colorCodes highlighting for the cells
			let colorCodes = ( typeof(cellProperties.colorCodes) != 'undefined' ) ? cellProperties.colorCodes : '';

			if( value !== '' && value != null ) {

				if( colorCodes != '' ) {
					if ( currentRowData && currentRowData.hasOwnProperty('custom_stock_color_code') ) {
						return td.classList.add(...['sm_beta_select_'+currentRowData['custom_stock_color_code'], 'sm_font_bold']);
					}
					for( let color in colorCodes ) {

						let min = (colorCodes[color].hasOwnProperty('min')) ? colorCodes[color]['min'] : -1,
							max = (colorCodes[color].hasOwnProperty('max')) ? colorCodes[color]['max'] : -1

						if(min < 0 && max < 0){
							continue;
						}

						let v = parseFloat(value);

						if(isNaN(v)){
							continue;
						}
						if( ((min < 0 || max < 0) && ((min >= 0 && v >= min) || (max >= 0 && v <= max)))
							|| ((min >= 0 && max >= 0) && (v >= min) && (v <= max)) ){
							td.classList.add(...['sm_beta_select_'+color, 'sm_font_bold'])
							break;
						}
					}
				}
			}

		    return td;
		}
	  	Handsontable.renderers.registerRenderer('numericRenderer', numericRenderer);

	  	function customTextRenderer(hotInstance, td, row, col, prop, value, cellProperties) {
		    Handsontable.renderers.TextRenderer.apply(this, arguments);
		    td.innerHTML = '<div title="'+ td.innerHTML +'" class="wrapper">' + td.innerHTML + '</div>';

		    return td;
		}
	  	Handsontable.renderers.registerRenderer('customTextRenderer', customTextRenderer);

	  	function customHtmlRenderer(hotInstance, td, row, col, prop, value, cellProperties) {
			Handsontable.renderers.HtmlRenderer.apply(this, arguments);
			td.innerHTML = '<div title="'+ td.innerText +'" class="wrapper">' + td.innerHTML + '</div>';

		    return td;
		}
	  	Handsontable.renderers.registerRenderer('customHtmlRenderer', customHtmlRenderer);

	  	function customCheckboxRenderer(hotInstance, td, row, col, prop, value, cellProperties) {

		    Handsontable.renderers.CheckboxRenderer.apply(this, arguments);
		    td.innerHTML = '<div class="wrapper">' + td.innerHTML + '</div>';

		    return td;
		}
	  	Handsontable.renderers.registerRenderer('customCheckboxRenderer', customCheckboxRenderer);

	  	function customPasswordRenderer(hotInstance, td, row, col, prop, value, cellProperties) {

		    Handsontable.renderers.PasswordRenderer.apply(this, arguments);
		    td.innerHTML = '<div class="wrapper">' + td.innerHTML + '</div>';

		    return td;
		}
	  	Handsontable.renderers.registerRenderer('customPasswordRenderer', customPasswordRenderer);

      function datetimeRenderer(hotInstance, td, row, column, prop, value, cellProperties) {
        if( typeof(cellProperties.className) != 'undefined' ) { //code to higlight the cell on selection
            td.setAttribute('class',cellProperties.className);
        }
		if( (value) && (typeof(value) !== 'undefined') && (value.length) ){
			value = value.replace(/T/g, ' ');//replace T with space.
		}

        td.innerHTML = value;

        td.innerHTML = '<div class="wrapper">' + td.innerHTML + '</div>';

        return td;
      }

		function longstringRenderer(hotInstance, td, row, column, prop, value, cellProperties) {
			Handsontable.renderers.HtmlRenderer.apply(this, arguments);
			if( typeof(cellProperties.className) != 'undefined' ) { //code to higlight the cell on selection
				td.setAttribute('class',cellProperties.className);
			}

			td.innerHTML = '<div title="'+ td.innerText +'" class="wrapper">' + td.innerHTML + '</div>';

			return td;
		}

		function selectValueRenderer(hotInstance, td, row, column, prop, value, cellProperties) {
			let source = cellProperties.selectOptions || {},
				className = ( typeof(cellProperties.className) != 'undefined' ) ? cellProperties.className : '',
				colorCodes = ( typeof(cellProperties.colorCodes) != 'undefined' ) ? cellProperties.colorCodes : '';

			// if( className != '' ) { //code to higlight the cell on selection
			// 	td.setAttribute('class',className);
			// }

			if( typeof source != 'undefined' && typeof value != 'undefined' && source.hasOwnProperty(value) ) {
				td.setAttribute('data-value',value);

				if( colorCodes != '' ) {
					for( let color in colorCodes ) {
						if( colorCodes[color].indexOf(value) != -1 ) {
							// className = (( className != '' ) ? className + ' ' : '') + 'sm_beta_select_'+color;
							// td.setAttribute('class',className);
							td.classList.add('sm_beta_select_'+color)
							break;
						}
					}
				}
				td.innerHTML = source[value];
			}

			td.innerHTML = '<div title="'+ td.innerText +'" class="wrapper">' + td.innerHTML + '</div>';

			return td;
		}

		function multilistRenderer(hotInstance, td, row, column, prop, value, cellProperties) {
		// ...renderer logic
			Handsontable.renderers.TextRenderer.apply(this, arguments);
			if( typeof(cellProperties.className) != 'undefined' ) { //code to higlight the cell on selection
				td.setAttribute('class',cellProperties.className);
			}

			td.innerHTML = '<div class="wrapper">' + window.smart_manager?.decodeHTMLString(td.innerHTML, ('terms_product_cat' === prop)) + '</div>';

			return td;
		}

	  Handsontable.renderers.registerRenderer('selectValueRenderer', selectValueRenderer);

	  	function select2Renderer(instance, td, row, col, prop, value, cellProperties) {

		    let selectedId;
		    let optionsList = (cellProperties.select2Options.data) ? cellProperties.select2Options.data : [];
		    let dynamicSelect2 = ( cellProperties.select2Options.hasOwnProperty('loadDataDynamically') ) ? true : false;

		    if( (typeof optionsList === "undefined" || typeof optionsList.length === "undefined" || !optionsList.length) && !dynamicSelect2 ) {
		        Handsontable.cellTypes.text.renderer(instance, td, row, col, prop, value, cellProperties);
		        return td;
		    }

		    if( dynamicSelect2 && typeof(value) == 'object' ) {
				jQuery(td).attr('data-value', JSON.stringify(value));

		    	let values = ( value ) ? value : [];

		    	value = [];
		    	var text = '';
		    	values.forEach(function(obj) {
		    		if( obj.text ) {
						value.push(obj.text.trim());
		    		}
				});

		    } else {
		    	let values = (value + "").split(",");

			    value = [];
			    for (let index = 0; index < optionsList.length; index++) {

			        if (values.indexOf(optionsList[index].id + "") > -1) {
			            selectedId = optionsList[index].id;
			            value.push(optionsList[index].text);
			        }
			    }
		    }

		    value = value.join(", ");

		    Handsontable.cellTypes.text.renderer(instance, td, row, col, prop, value, cellProperties);

			td.innerHTML = '<div class="wrapper">' + td.innerHTML + '</div>';
		    return td;
		}
	  	Handsontable.renderers.registerRenderer('select2Renderer', select2Renderer);

		function generateImageHtml(params = {}){
			if(!params.td || !params.value || !params.cellProperties || !params.currentInstance){
				return
			}

			let escaped = Handsontable.helper.stringify(params.value),
				img,
				className = ((params.className) ? (params.className + ' ') : '') + 'sm_image_thumbnail';
			if (escaped.indexOf('http') === 0) {
				img = document.createElement('IMG');
				img.src = params.value;
				img.width = 30;
				img.height = 30;

				img.setAttribute('class',className);

				Handsontable.dom.addEvent(img, 'mousedown', function (e){
					e.preventDefault(); // prevent selection quirk
				});

				// Handsontable.dom.empty(td);
				params.td.appendChild(img);
			}
			else {
				// render as text
				Handsontable.renderers.TextRenderer.apply(params.currentInstance, arguments);
			}

			if( typeof(params.cellProperties.className) != 'undefined' ) {
					className += ' '+ params.cellProperties.className;
					params.td.setAttribute('class',params.cellProperties.className);
			}

			return params.td
		}

	  	function imageRenderer(hotInstance, td, row, column, prop, value, cellProperties) {
			try{
				value = (!value || value == 0) ? window.smart_manager.defaultImagePlaceholder : value
				Handsontable.dom.empty(td);
				td = generateImageHtml({
					td: td,
					value: value,
					cellProperties: cellProperties,
					currentInstance: this
				})
				td.innerHTML = '<div class="wrapper">' + td.innerHTML + '</div>';
			}catch(e){
				console.log('imageRenderer:: ', e)
			}
		  	return td;
	  	}
	 	function multipleImageRenderer(hotInstance, td, row, column, prop, value, cellProperties) {
			try{
				value = (!Array.isArray(value)) ? [] : value;
				value = (value.length === 0) ? [{id:0, val:window.smart_manager.defaultImagePlaceholder}] : value
				Handsontable.dom.empty(td);
				value.map((obj) => {
					td = generateImageHtml({
						td: td,
						value: obj.val || '',
						cellProperties: cellProperties,
						currentInstance: this
					})
				})
				td.innerHTML = '<div class="wrapper">' + td.innerHTML + '</div>';
			}catch(e){
				console.log('multipleImageRenderer:: ', e)
			}
			return td;
  		}
	// For passing tasks params when updating using inline edit
	SmartManager.prototype.addTasksParams = function(params = {}){
		if(!params || !params.hasOwnProperty('cmd') || ("undefined" === typeof(window.smart_manager.isTasksEnabled) && "function" !== typeof(window.smart_manager.isTasksEnabled))) {
			return params;
		}
		let cmdNames = ['get_dashboard_model','get_data_model'];
		if(cmdNames.includes(params['cmd'])){
			return Object.assign(params,{isTasks: (window.smart_manager.isTasksEnabled()) ? 1 : 0});
		}else if('batch_update' !== params['cmd'] && params.hasOwnProperty('edited_data')){
			params.updatedEditedData = ((window.smart_manager.updatedEditedData) && (Object.values(window.smart_manager.updatedEditedData).length > 0)) ? JSON.stringify(window.smart_manager.updatedEditedData) : params['edited_data'];
		}
		return Object.assign(params,{isTasks: (window.smart_manager.isTasksEnabled()) ? 1 : 0,
			title: (window.smart_manager.updatedTitle) ? window.smart_manager.updatedTitle : window.smart_manager.processContent,
			isScheduled: window.smart_manager.isScheduled,
			scheduledFor: (window.smart_manager.isScheduled) ? window.smart_manager.scheduledFor : '0000-00-00 00:00:00',
			scheduledActionAdminUrl: (window.smart_manager.isScheduled) ? window.smart_manager.scheduledActionAdminUrl : ''
		});
	}
	// For getting edited column name
	SmartManager.prototype.getColDisplayName = function(colSrc = ''){
		if(!colSrc || ('undefined' === typeof(window.smart_manager.excludedEditedFieldKeys)) || ('undefined' === typeof(window.smart_manager.currentColModel))){
			return colSrc;
		}
		for(i = 0; i < window.smart_manager.currentColModel.length; i++){
			if(('undefined' !== typeof(window.smart_manager.currentColModel[i])) && window.smart_manager.currentColModel[i] && window.smart_manager.currentColModel[i].hasOwnProperty('src') && window.smart_manager.currentColModel[i].hasOwnProperty('name_display') && (window.smart_manager.currentColModel[i].src === colSrc) && false === window.smart_manager.excludedEditedFieldKeys.includes(colSrc)){
				return window.smart_manager.currentColModel[i].name_display;
			}
		}
	}
	// For updating image
	SmartManager.prototype.inlineUpdateImage = function(imageParams){
		if(!imageParams || !((Object.keys(imageParams)).every(imageParam => imageParams.hasOwnProperty(imageParam)))){
			return;
		}
		let params = {};
		params.data = {
			cmd: 'inline_update',
			active_module: window.smart_manager.dashboardKey,
			edited_data: imageParams.editedImage,
			security: window.smart_manager.saCommonNonce,
			pro: ('undefined' !== typeof(window.smart_manager.sm_beta_pro)) ? window.smart_manager.sm_beta_pro : 0,
			table_model: (window.smart_manager.currentDashboardModel.hasOwnProperty('tables')) ? window.smart_manager.currentDashboardModel.tables : ''
		};
		params.data = ("undefined" !== typeof(window.smart_manager.addTasksParams) && "function" === typeof(window.smart_manager.addTasksParams) && 1 == window.smart_manager.sm_beta_pro) ? window.smart_manager.addTasksParams(params.data) : params.data;
		window.smart_manager.sendRequest(params, function(response){
			if('failed' !== response){
				window.smart_manager.hot.setDataAtCell(imageParams.row, imageParams.col, imageParams.value, imageParams.source);
				if(window.smart_manager.isJSON(response) && ('undefined' === typeof(window.smart_manager.sm_beta_pro) || ('undefined' !== typeof(window.smart_manager.sm_beta_pro) && 1 != window.smart_manager.sm_beta_pro))){
					response = JSON.parse(response);
					msg = response.msg;
					if('undefined' !== typeof(response.sm_inline_update_count)){
						if("undefined" !== typeof(window.smart_manager.updateLitePromoMessage) && "function" === typeof(window.smart_manager.updateLitePromoMessage)){
							window.smart_manager.updateLitePromoMessage(response.sm_inline_update_count);
						}
					}
				}else{
					msg = response;
				}
			}
		});
	};
	// For changing dashboard display name in bottom bar for tasks
	SmartManager.prototype.changeDashboardDisplayName = function(dashboardDisplayName = ''){
		if(!dashboardDisplayName){
			return;
		}
		window.smart_manager.dashboardDisplayName = ("undefined" !== typeof(window.smart_manager.isTasksEnabled) && "function" === typeof(window.smart_manager.isTasksEnabled) && window.smart_manager.isTasksEnabled()) ? _x('Tasks','bottom bar display name for tasks','smart-manager-for-wp-e-commerce') : dashboardDisplayName;
	}
	// Code for handling gallery image modal.
	SmartManager.prototype.displayGalleryImagesModal = function(galleryImageParams){
		if(!galleryImageParams || !((Object.keys(galleryImageParams)).every(galleryImageParam => galleryImageParams.hasOwnProperty(galleryImageParam)))){
			return;
		}
		let rowNo = (galleryImageParams.hasOwnProperty('rowNo')) ? galleryImageParams.rowNo : 0;
		window.smart_manager.modal = {
			title: _x('Gallery Images','gallery modal title','smart-manager-for-wp-e-commerce'),
			content: galleryImageParams.imageGalleryHtml,
			autoHide: false,
			cta: {
					title: _x('Add','button','smart-manager-for-wp-e-commerce'),
					closeModalOnClick: false,
					callback: function(){
						if("undefined" !== typeof(window.smart_manager.handleMediaUpdate) && "function" === typeof(window.smart_manager.handleMediaUpdate)){
							jQuery('.sm_ui_dialog_class, .ui-widget-overlay').hide();
							let params = {
								UploaderText: _x('Add images to product gallery','button','smart-manager-for-wp-e-commerce'),
								UploaderButtonText: _x('Add to gallery','button','smart-manager-for-wp-e-commerce'),
								allowMultiple: true,
								row_data_id: galleryImageParams.id
							};
							params.callback = function(attachments){
								jQuery('.sm_ui_dialog_class , .ui-widget-overlay').show();
								if('undefined' === typeof(attachments)){
									return;
								}
								let imageGalleryHtml = `<div class="sm_gallery_image_parent" data-id="${galleryImageParams.id}" data-col="${galleryImageParams.src || ''}" data-row="${rowNo || 0}">`,
									modifiedGalleryImages = [],
									imageIds = new Set();
								jQuery('.sm_gallery_image').find('img').each(function(){
									modifiedGalleryImages.push({
										id:jQuery(this).data('id'),
										val:jQuery(this).attr('src')
									});
									imageIds.add(jQuery(this).data('id'));
								});
								attachments.forEach(function(attachmentObj){
									modifiedGalleryImages.push({
										id:attachmentObj.id,
										val:attachmentObj.sizes.full.url
									});
									imageIds.add(attachmentObj.id);
								});
								if("undefined" !== typeof(window.smart_manager.generateImageGalleryDlgHtml) && "function" === typeof(window.smart_manager.generateImageGalleryDlgHtml)){
									imageGalleryHtml += window.smart_manager.generateImageGalleryDlgHtml(modifiedGalleryImages);
								}
								imageGalleryHtml += '</div>';
								let args = {id:galleryImageParams.id,src:galleryImageParams.src,values:[...imageIds].join(','),imageGalleryHtml:imageGalleryHtml,rowNo:rowNo};
								if(1 == window.smart_manager.sm_beta_pro && "undefined" !== typeof(window.smart_manager.displayTitleModal) && "function" === typeof(window.smart_manager.displayTitleModal)){
									window.smart_manager.displayTitleModal(args);
								}else if("undefined" !== typeof(window.smart_manager.inlineUpdateMultipleImages) && "function" === typeof(window.smart_manager.inlineUpdateMultipleImages)){
									jQuery('div.modal-body').html(imageGalleryHtml);
									window.smart_manager.inlineUpdateMultipleImages(args);
								}
							}
							window.smart_manager.handleMediaUpdate( params );
						}
					}
			},
		}
		window.smart_manager.showModal()
	}
	// Code for displaying title modal in case of gallery images.
	SmartManager.prototype.displayTitleModal = function(params){
		if(!params || !((Object.keys(params)).every(param => params.hasOwnProperty(param)))){
			return;
		}
		let editedColumnName = '';
		if("undefined" !== typeof(window.smart_manager.getColDisplayName) && "function" === typeof(window.smart_manager.getColDisplayName)){
			editedColumnName = window.smart_manager.getColDisplayName(params.src);
		}
		window.smart_manager.processName = _x('Inline Edit','process name','smart-manager-for-wp-e-commerce');
		window.smart_manager.processContent = (editedColumnName) ? editedColumnName : params.src;
		if("undefined" !== typeof(window.smart_manager.inlineUpdateMultipleImages) && "function" === typeof(window.smart_manager.inlineUpdateMultipleImages)){
			window.smart_manager.processCallback = window.smart_manager.inlineUpdateMultipleImages;
		}
		window.smart_manager.processCallbackParams = params;
		if("undefined" !== typeof(window.smart_manager.showTitleModal) && "function" === typeof (window.smart_manager.showTitleModal)){
			window.smart_manager.showTitleModal()
		}
	}
	// Code for show/hide tasks
	SmartManager.prototype.displayTasks = function (params = {}){
		let actionBtns = jQuery(".sm_top_bar_action_btns:nth-last-child(2), .sm_top_bar_action_btns:nth-last-child(3), .sm_top_bar_action_btns:nth-last-child(4), .sm_top_bar_action_btns #batch_update_sm_editor_grid, .sm_top_bar_action_btns #show_hide_cols_sm_editor_grid");
		let revDelBtns = jQuery(".sm_top_bar_action_btns:nth-last-child(5) #undo_sm_editor_grid, .sm_top_bar_action_btns:nth-last-child(5) #delete_tasks_sm_editor_grid");
    	switch(true){
    		case (params.hasOwnProperty('hideTasks')):
		    case params.hasOwnProperty('dashboardChange'):
		    	jQuery("#sm_show_tasks").prop('checked', false);
		    	actionBtns.show();
				revDelBtns.hide();
		    	(window.smart_manager.getViewSlug(window.smart_manager.dashboardName)) ? jQuery('#sm_show_tasks_container').hide() : jQuery('#sm_show_tasks_container').show();
		    	break;
		    case params.hasOwnProperty('showHideTasks'):
		    	actionBtns.toggle();
		    	if(1 === params.showHideTasks){
		    		revDelBtns.show();
					jQuery('#sm_show_tasks_container').parents('div.sm_top_bar_action_btns').attr('style','width: 100% !important;');
					 if(!window.location.search.includes('show_edit_history')){
						window.smart_manager.updateState();
					 }
		    	}else{
			    	revDelBtns.hide();
					jQuery('#sm_show_tasks_container').parents('div.sm_top_bar_action_btns').removeAttr('style');
					if(!window.location.search.includes('show_edit_history')){
						window.smart_manager.updateState({isTasksEnabled:0});
					}
		    	}
		    	break;
		}
	}

	//Function to add title for each of the column headers
	SmartManager.prototype.refreshColumnsTitleAttribute = function(){
		setTimeout(() => {
			window.smart_manager.hot.updateSettings({
				data: window.smart_manager.currentDashboardData,
				columns: window.smart_manager.currentVisibleColumns,
				colHeaders: window.smart_manager.column_names
			});
			jQuery('table.htCore').find('.colHeader').each(function() {
				jQuery(this).attr('title',jQuery(this).text()+' '+_x('(Click to sort)', 'tooltip', 'smart-manager-for-wp-e-commerce'));
			});
		}, 1000);
	}


	SmartManager.prototype.reset = function (fullReset = false) {
		SaCommonManager.prototype.reset.call(this, fullReset);
		if (fullReset) {
			this.currentDashboardModel = '';
			this.currentVisibleColumns = [];
			this.simpleSearchText = '';
			if (this.loadingDashboardForsavedSearch === false) {
				this.advancedSearchQuery = new Array();
				this.advancedSearchRuleCount = 0;
			}
			this.colModelSearch = {}
		}
		this.selectedRows = [];
		this.addRecords_count = 0;
		this.page = 1;
		this.dirtyRowColIds = {};
		this.editedData = {};
		this.updatedEditedData = {};
		this.processContent = '';
		this.updatedTitle = '';
		this.editedColumnTitles = {};
		this.exportCSVActions = ['sm_export_selected_records', 'sm_export_entire_store'];
		this.recordSelectNotification = (this.sm_beta_pro == 1) ? true : false;
		if (this.hot) {
			if (this.hot.selection) {
				if (this.hot.selection.highlight) {
					if (this.hot.selection.highlight.selectAll) {
						delete this.hot.selection.highlight.selectAll
					}
					this.hot.selection.highlight.selectedRows = []
				}
			}
		}
	}

	SmartManager.prototype.refresh = function (dataParams) {
		SaCommonManager.prototype.refresh.call(this, dataParams);
		this.reset();
		if (this.sm_beta_pro == 0) {
			if (typeof (this.disableSelectedRows) !== "undefined" && typeof (this.disableSelectedRows) === "function") {
				this.disableSelectedRows(false);
			}
		}
        this.getData(dataParams);
	}

	SmartManager.prototype.showLoader = function (is_show = true) {
		SaCommonManager.prototype.showLoader.call(this, is_show);
	}

	SmartManager.prototype.showNotification = function () {
		if((!window.location.href.includes(this.advancedSearchRoute) && !window.location.href.includes(this.bulkEditRoute)) && this.notification.hasOwnProperty('message') && '' !== this.notification.message && (typeof (this.showPannelDialog) !== "undefined" && typeof (this.showPannelDialog) === "function" && typeof (this.getDefaultRoute) !== "undefined" && typeof (this.getDefaultRoute) === "function")){
			if (window.location.href.includes(this.columnManagerRoute)) { // Added as Col manager HTML & click events are not coming from raw js -- flickers for chrome. Can fix later
				setTimeout(() => { this.showPannelDialog(this.columnManagerRoute) }, 1)
			}
			this.showPannelDialog(this.getDefaultRoute())
		}
	}

	SmartManager.prototype.showModal = function () {
		SaCommonManager.prototype.showModal.call(this);
	}

	SmartManager.prototype.hideModal = function () {
		SaCommonManager.prototype.hideModal.call(this);
	}

	SmartManager.prototype.showPannelDialog = function (route = '', currentRoute = '') {
		SaCommonManager.prototype.showPannelDialog.call(this, route, currentRoute);
	}

	SmartManager.prototype.getDefaultRoute = function (isReplaceRoute = false) {
		return SaCommonManager.prototype.getDefaultRoute.call(this, isReplaceRoute);
	}

	SmartManager.prototype.getCheckboxValues = function (colObj) {
		return SaCommonManager.prototype.getCheckboxValues.call(this, colObj);
	}

	// Code for resetting search functionalities.
	SmartManager.prototype.resetSearch = function(){
		('advanced' === window.smart_manager.searchType) ? jQuery('#search_switch').prop('checked', false).trigger('change') : jQuery('#sm_simple_search_box').val('');
	}

	// Function to detect if filtered data or not
	SmartManager.prototype.isFilteredData = function(){
		return (('simple' === window.smart_manager.searchType && window.smart_manager.simpleSearchText !== '') || ('simple' !== window.smart_manager.searchType && window.smart_manager.advancedSearchQuery.length > 0))
	}
	// Function to toggle top bar in case of product stock log dashboard.
	SmartManager.prototype.toggleTopBar = function(){
		('product_stock_log' === window.smart_manager.dashboardKey) ? jQuery('#sm_top_bar').toggle(false) : jQuery('#sm_top_bar').toggle(true);
	}

	// Function for displaying warning modal before doing export csv
	SmartManager.prototype.getExportCsv = function(args){
	    if(!args || !((Object.keys(args)).every(arg => args.hasOwnProperty(arg)))){
	        return;
	    }
	    args.params.content = _x('Are you sure you want to export the ','modal content','smart-manager-for-wp-e-commerce') + '<strong>' + args.btnText + '</strong>' + '?';
	    if("undefined" !== typeof(window.smart_manager.generateCsvExport) && "function" === typeof(window.smart_manager.generateCsvExport)){
	        args.params.btnParams.yesCallback = window.smart_manager.generateCsvExport;
	    }
	    window.smart_manager.exportStore = (['sm_export_entire_store_stock_cols', 'sm_export_entire_store_visible_cols', 'sm_export_entire_store'].includes(args.id)) ? (args.id === 'sm_export_entire_store_stock_cols' ? 'entire_store_stock_cols' : 'entire_store') : '';
	    window.smart_manager.columnsToBeExported = (window.smart_manager.stockCols && window.smart_manager.stockCols.includes(args.id)) ? 'stock' : 'visible';
	    if("undefined" !== typeof(window.smart_manager.showConfirmDialog) && "function" === typeof(window.smart_manager.showConfirmDialog)){
	        window.smart_manager.showConfirmDialog(args.params);
	    }
	}

	// ========================================================================
	// EXPORT CSV
	// ========================================================================

	SmartManager.prototype.generateCsvExport = function(data = {}) {

	    let params = {
	                            cmd: 'get_export_csv',
	                            active_module: window.smart_manager.dashboardKey,
	                            security: window.smart_manager.saCommonNonce,
	                            pro: true,
	                            SM_IS_WOO30: window.smart_manager.sm_is_woo30,
	                            sort_params: (window.smart_manager.currentDashboardModel.hasOwnProperty('sort_params') ) ? window.smart_manager.currentDashboardModel.sort_params : '',
	                            table_model: (window.smart_manager.currentDashboardModel.hasOwnProperty('tables') ) ? window.smart_manager.currentDashboardModel.tables : '',
	                            search_text: (window.smart_manager.searchType == 'simple') ? window.smart_manager.simpleSearchText : '',
							    advanced_search_query: JSON.stringify((window.smart_manager.searchType != 'simple') ? window.smart_manager.advancedSearchQuery : []),
	                            is_taxonomy: window.smart_manager.isTaxonomyDashboard() || 0,
	                            storewide_option: window.smart_manager.exportStore || '',
	                            selected_ids: (window.smart_manager.getSelectedKeyIds()) ? JSON.stringify(window.smart_manager.getSelectedKeyIds()) : '',
	                            columnsToBeExported: window.smart_manager.columnsToBeExported || ''
	                          };
	    //Code for handling views
	    let viewSlug = window.smart_manager.getViewSlug(window.smart_manager.dashboardName);

	    if(viewSlug){
	        params['is_view'] = 1;
	        params['active_view'] = viewSlug;
	        params['active_module'] = (window.smart_manager.viewPostTypes.hasOwnProperty(viewSlug)) ? window.smart_manager.viewPostTypes[viewSlug] : window.smart_manager.dashboardKey;
	    }
	if(data.hasOwnProperty('isScheduledExport') && data.isScheduledExport === true && data.hasOwnProperty('scheduleParams') && data.hasOwnProperty('scheduleExportAjaxCallback') && typeof data.scheduleExportAjaxCallback === 'function'){
			params.is_scheduled_export = data?.isScheduledExport || false;
			params.scheduled_export_params = data?.scheduleParams || false;
			params.selected_ids = params.storewide_option = params.advanced_search_query = params.search_text = '';
			window.smart_manager.sendRequest({data:params,data_type: 'json'},data.scheduleExportAjaxCallback)
			return;
		}

	    let export_url = window.smart_manager.commonManagerAjaxUrl + '&cmd='+ params['cmd'] +'&active_module='+ params['active_module'] +'&security='+ params['security'] +'&pro='+ params['pro'] +'&SM_IS_WOO30='+ params['SM_IS_WOO30'] +'&is_taxonomy='+ params['is_taxonomy'] +'&sort_params='+ encodeURIComponent(JSON.stringify(params['sort_params'])) +'&table_model='+ encodeURIComponent(JSON.stringify(params['table_model'])) +'&advanced_search_query='+params['advanced_search_query']+'&search_text='+ params['search_text'] + '&storewide_option=' + params['storewide_option'] + '&selected_ids=' + params['selected_ids'] + '&columnsToBeExported=' + params['columnsToBeExported'];
	    export_url += ( window.smart_manager.date_params && window.smart_manager.date_params.hasOwnProperty('date_filter_params') ) ? '&date_filter_params='+ window.smart_manager.date_params['date_filter_params'] : '';
	    export_url += ( window.smart_manager.date_params && window.smart_manager.date_params.hasOwnProperty('date_filter_query') ) ? '&date_filter_query='+ window.smart_manager.date_params['date_filter_query'] : '';

	    if(viewSlug){
	        export_url += '&is_view='+params['is_view']+'&active_view='+params['active_view'];
	    }

	    setTimeout(()=>{window.location = export_url},500);
	}

// Function for displaying confirm dialog for unsaved changes.
	SmartManager.prototype.confirmUnsavedChanges = function(params ={}) {
			try{
				window.smart_manager.modal = {
					title: _x('Attention!', 'modal title', 'smart-manager-for-wp-e-commerce'),
					content: '<div style="font-size:1.2em;margin:1em;"> <div style="margin-bottom:1em;">'+
						_x('You have unsaved changes. Are you sure you want to continue?', 'modal content', 'smart-manager-for-wp-e-commerce')+'</div></div>',
					autoHide: false,
					cta: {
						title: _x('Yes', 'button', 'smart-manager-for-wp-e-commerce'),
						//closeModalOnClick: (params.hasOwnProperty('hideOnYes')) ? params.hideOnYes : true,
						callback: function() {
							 setTimeout(() => { // TODO: improve it
								if(params.hasOwnProperty('modalVals')){
									window.smart_manager.modal = params.modalVals
								}
								if(params.hasOwnProperty('yesCallback') && typeof params.yesCallback === "function"){
									if(params.hasOwnProperty('yesCallbackParams')){
										params.yesCallback( params.yesCallbackParams );
									}else{
										params.yesCallback();
									}
								}
							 },300)
						}
					},
					closeCTA: { title: _x('No', 'button', 'smart-manager-for-wp-e-commerce'),
						callback: function() {
							if( params.hasOwnProperty('noCallback') && typeof params.noCallback === "function" ) {
								params.noCallback();
							}
						}
					}
				}
				window.smart_manager.showModal()
			}
			catch(e){
				SaErrorHandler.log('Exception occurred in confirmUnsavedChanges:: ', e)
			}
	}
	// Function to show man-hrs saved message, post inline-update in lite version
	SmartManager.prototype.showManHoursSaved = function(params ={}) {
		try{
			window.smart_manager.modal = {
				title: _x( params.hasOwnProperty('title')?params.title:"", 'modal title', 'smart-manager-for-wp-e-commerce'),
				content: '<div style="font-size:1.2em;margin:1em;"> <div style="margin-bottom:1em;">'+
					_x( params.hasOwnProperty('message')?params.message:"", 'modal content', 'smart-manager-for-wp-e-commerce')+'</div></div>',
				hideFooter:true,
				autoHide: false,
				isFooterItemsCenterAligned: true
			}
			window.smart_manager.showModal()
		}
		catch(e){
			SaErrorHandler.log('Exception occurred in showManHoursSaved:: ', e)
		}
	}
	// Function for updating Advanced Search rule count.
	SmartManager.prototype.updateAdvancedSearchRuleCount = function(){
		if(!window.smart_manager.advancedSearchQuery || !window.smart_manager.advancedSearchQuery.length > 0){
			return;
		}
        let firstQuery = window.smart_manager.advancedSearchQuery[0];
        if(Object.keys(firstQuery).length > 0){
            let rules = firstQuery.hasOwnProperty('rules') ? firstQuery.rules : [];
            if(rules.length > 0){
                rules.forEach(ruleSet => {
                    window.smart_manager.advancedSearchRuleCount += ruleSet.rules.length;
                });
        	}
        }
	}
	// Generate final checkbox list for multilist data for inline edit.
	SmartManager.prototype.generateCheckboxList = function(multiselect_data = {}, selectedValues = []){
		if(!multiselect_data){
			return;
		}
		let html = '<ul id="sm-multilist-data">';
		Object.keys(multiselect_data).forEach((key) => {
			let data = multiselect_data[key];
			let checked = (selectedValues) && ((selectedValues.includes(data.title) || selectedValues.includes(data.id.toString()))) ? 'checked' : '';
			html += `<li><input type="hidden" name="chk_multiselect" value="${data.term}" class="sm-title-input"><input type="checkbox" name="chk_multiselect" value="${data.id}" ${checked}> ${data.term}`;
			// Recursively add child data
			if(data.child && Object.keys(data.child).length > 0){
				html += '<ul class="children">'+window.smart_manager.generateCheckboxList(data.child, selectedValues)+'</ul>';
			}
			html += '</li>';
		});
		html += '</ul>';
		return html;
	}

	//Function to convert special characters into HTML characters.
	SmartManager.prototype.decodeHTMLString = function (str = '', isCategoryName = false) {
		if ('' === str.trim()) {
			return str;
		}
		// Decode HTML entities using DOMParser.
		const decodedStr = new DOMParser().parseFromString(str, 'text/html').documentElement.textContent;
		// Replace the ? sequence with >
		return (isCategoryName === true) ? decodedStr.replace(/\u0080\?/g, '>') : decodedStr;
	}

	// Register an alias for datetime
	Handsontable.cellTypes.registerCellType('sm.datetime', {
		editor: dateTimeEditor,
		renderer: datetimeRenderer,
		allowInvalid: true,
	});

	// Register an alias for date
	Handsontable.cellTypes.registerCellType('sm.date', {
		editor: dateEditor,
		renderer: datetimeRenderer,
		allowInvalid: true,
	});

	// Register an alias for time
	Handsontable.cellTypes.registerCellType('sm.time', {
		editor: timeEditor,
		renderer: datetimeRenderer,
		allowInvalid: true,
	});

	// Register an alias for image
	Handsontable.cellTypes.registerCellType('sm.image', {
		renderer: imageRenderer,
		allowInvalid: true,
	});

	// Register an alias for multiple gallery images
	Handsontable.cellTypes.registerCellType('sm.multipleImage', {
		// renderer: Handsontable.renderers.HtmlRenderer,
		renderer: multipleImageRenderer,
		allowInvalid: true,
	});

	// Register an alias for longstrings
	Handsontable.cellTypes.registerCellType('sm.longstring', {
		editor: defaultTextEditor,
		renderer: multilistRenderer,
		allowInvalid: true,
	});

	// Register an alias for serialized
	Handsontable.cellTypes.registerCellType('sm.serialized', {
		editor: defaultTextEditor,
		renderer: multilistRenderer,
		allowInvalid: true,
	});

	// Register an alias for multilist
	Handsontable.cellTypes.registerCellType('sm.multilist', {
		editor: defaultTextEditor,
		renderer: multilistRenderer,
		allowInvalid: true,
	});

})(Handsontable);
