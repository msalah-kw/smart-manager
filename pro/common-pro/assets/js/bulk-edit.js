
(function (window) {
    function SaCommonBulkEdit() {
        SaCommonManagerPro.call(this);
    }
    SaCommonBulkEdit.prototype = Object.create(SaCommonManagerPro.prototype);
    SaCommonBulkEdit.prototype.constructor = SaCommonBulkEdit;

    SaCommonBulkEdit.prototype.processBatchUpdate = function () {
        try{
            this.selectedIds = ((this.selectedIds.length == 0) && ('undefined' !== typeof (this.selectedRows) && this.selectedRows.length > 0)) ? this.selectedRows : this.selectedIds;
            if (this.savedBulkEditConditions.length <= 0 || (('undefined' !== typeof (this.selectedRows) && this.selectedRows.length == 0) || ('undefined' !== typeof (this.selectedIds) && this.selectedIds.length == 0)) && !this.selectAll){
                return;
            }
            let ruleGroups = (this.savedBulkEditConditions[0].hasOwnProperty('rules')) ? this.savedBulkEditConditions[0].rules : []
            let ruleMeta = (this.savedBulkEditConditions[0].hasOwnProperty('meta')) ? this.savedBulkEditConditions[0].meta : []
            if (ruleGroups.length <= 0) {
                return;
            }
            let actions = (ruleGroups[0].hasOwnProperty('rules')) ? ruleGroups[0].rules : [];
            let updateAll = (ruleMeta.hasOwnProperty('updateAll')) ? ruleMeta.updateAll : false;
            this.selectAll = (updateAll || this.selectAll) ? true : false;
            if (!this.isScheduled) {
                setTimeout(() => {
                    this.showProgressDialog(_x('Bulk Edit', 'progressbar modal title', 'smart-manager-for-wp-e-commerce'));
                    if (typeof sa_background_process_heartbeat === "function") {
                        sa_background_process_heartbeat(1000, 'bulk_edit', this.pluginKey);
                    }
                }, 1);
            }
            let selected_ids = ('undefined' !== typeof (this.getSelectedKeyIds) && "function" === typeof (this.getSelectedKeyIds)) ? this.getSelectedKeyIds() : (('undefined' !== typeof (this.selectedIds) && this.selectedIds.length > 0) ? this.selectedIds : []);
            //Ajax request to batch update the selected records
            this.ajaxParams = this.ajaxParams || {};
            this.ajaxParams.data = Object.assign({}, this.ajaxParams.data, {
                cmd: 'batch_update',
                active_module: this.dashboardKey,
                security: this.saCommonNonce,
                pro: true,
                storewide_option: (this.selectAll) ? 'entire_store' : '',
                selected_ids: JSON.stringify(selected_ids),
                batchUpdateActions: JSON.stringify(actions),
                active_module_title: this.dashboardName,
                backgroundProcessRunningMessage: this.backgroundProcessRunningMessage,
                table_model: (this.currentDashboardModel.hasOwnProperty('tables')) ? this.currentDashboardModel.tables : '',
                update_product_subscriptions_price: window[pluginKey ]?.updateProductSubscriptions || false,
                subscription_product_ids: JSON.stringify(window[pluginKey ]?.subscriptionProductIds || []),
                subscription_update_actions: JSON.stringify(window[pluginKey ]?.subscriptionUpdateActions || []),
                isScheduled: this.isScheduled,
                scheduledFor: (this.isScheduled) ? this.scheduledFor : '0000-00-00 00:00:00',
                scheduledActionAdminUrl: (this.isScheduled) ? this.scheduledActionAdminUrl : ''
            } ); // Merge plugin-specific ajaxParams.data here
            this.ajaxParams.showLoader = (this.isScheduled) ? true : false;
        } catch (e){
            SaErrorHandler.log('Error processing batch update:: ', e);
        }
    }
    window.SaCommonBulkEdit = SaCommonBulkEdit;
})(window);
