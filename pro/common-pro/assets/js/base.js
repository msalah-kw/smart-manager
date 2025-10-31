(function (window) {
    function SaCommonManagerPro() {
        this.proConstName.call(this); // Call parent constructor.
    }
    let additionalDateOperators = {increase_date_by:_x('increase by', "bulk edit action - 'date' fields", 'smart-manager-for-wp-e-commerce'), decrease_date_by:_x('decrease by', "bulk edit action - 'date' fields", 'smart-manager-for-wp-e-commerce')};
    let types_exclude_set_to = ['datetime', 'date', 'time']

    // Function for getting scheduled for value.
    SaCommonManagerPro.prototype.scheduledForVal = function() {
        try{
            this.scheduledFor = jQuery("#scheduled_for").val();
        } catch(e) {
            SaErrorHandler.log('Error getting scheduled value:: ', e)
        }
    }

    // Function for initializing datepicker for schedule bulk edit.
    SaCommonManagerPro.prototype.scheduleDatePicker = function(selector) {
        try{
            jQuery(selector).Zebra_DatePicker({
                format: 'Y-m-d H:i:s',
                show_icon: false,
                show_select_today: false,
                default_position: 'below',
                readonly_element: false,
                direction: true // To hide past dates.
            })
            .attr('placeholder','YYYY-MM-DD HH:MM:SS')
        } catch(e){
            SaErrorHandler.log('Error initializing datepicker:: ', e)
        }
    }

    // Function for scheduling bulk edit
    SaCommonManagerPro.prototype.showScheduleModal = function(params = {}) {
        try{
            if(!params || Object.keys(params).length === 0){
                return;
            }
            let description = sprintf(
                /* translators: %s: schedule actions modal description */
                _x('Schedule actions at your convenient date and time.','modal description','smart-manager-for-wp-e-commerce'), '<strong>'+_x('Schedule Actions','modal description','smart-manager-for-wp-e-commerce')+'</strong>');
            let scheduledForContent = `<div class="flex items-center mb-6"><label>${_x('Schedule For','modal title','smart-manager-for-wp-e-commerce')}</label><input type="text" id="scheduled_for" placeholder="YYYY-MM-DD HH:MM:SS" class="sa_bulk_edit_content"/></div>${this.scheduledActionAdminUrl
                ? `
                <div class="mb-3">
                    ${_x(
                    `Check all scheduled actions <a target='_blank' href=${this.scheduledActionAdminUrl}>here</a>.`,
                    'scheduled action list',
                    'smart-manager-for-wp-e-commerce'
                    )}
                </div>
                `
                : ''}<div style="font-size: 1.2em;"><small><i><strong>${_x('Note: ', 'modal description', 'smart-manager-for-wp-e-commerce')}</strong>${_x('Scheduled actions follow timezone of your site. Avoid overlaps to prevent delays.','modal description','smart-manager-for-wp-e-commerce')}</i></small></div>`;
            this.scheduledActionContent = '<div id="show_modal_content"><div style="padding-bottom: 1em; color: #6b7280!important;">'+description+'</div>'+scheduledForContent+'</div>';
            this.modal = {
                title: _x('Schedule Bulk Edit','modal title','smart-manager-for-wp-e-commerce'),
                content: this.scheduledActionContent,
                autoHide: false,
                width: 'w-1/4',
                cta: {
                    title: _x('Ok','button','smart-manager-for-wp-e-commerce'),
                    closeModalOnClick: params.hasOwnProperty('btnParams') ? ((params.btnParams.hasOwnProperty('hideOnYes')) ? params.btnParams.hideOnYes : true) : true,
                    callback: () => {
                        if(this.isScheduled){
                            if("undefined" !== typeof(this.scheduledForVal) && "function" === typeof(this.scheduledForVal)){
                                this.scheduledForVal();
                            }
                            if(!(this.scheduledFor)){
                                this.notification = {message: _x('Please select your desired date & time for scheduling an action.', 'notification', 'smart-manager-for-wp-e-commerce')}
                                this.showNotification()
                                return;
                            }
                        }
                        if("function" === typeof(this.processCallback)){
                            ("undefined" !== typeof(this.processCallbackParams) && Object.keys(this.processCallbackParams).length > 0) ? this.processCallback(this.processCallbackParams) : this.processCallback()
                        }
                        if("undefined" !== typeof(this.hideModal) && "function" === typeof(this.hideModal)){
                            this.hideModal();
                        }
                    }
                },
                closeCTA: {title: _x('Cancel','button','smart-manager-for-wp-e-commerce')},
                onCreate: () => {
                    if (this.scheduledActionContent && typeof this.scheduleDatePicker !== 'undefined' && typeof this.scheduleDatePicker === 'function') {
                        this.scheduleDatePicker('#scheduled_for');
                    }
                }
            }
            if(1 === this.showTasksTitleModal){
                if("undefined" !== typeof(this.showTitleModal) && "function" === typeof(this.showTitleModal)){
                    this.scheduledActionContent = scheduledForContent;
                    this.showTitleModal({btnParams:{hideOnYes: false}})
                }
            }else{
                this.showModal()
            }
        } catch(e){
            SaErrorHandler.log('Error showing schedule modal:: ', e)
        }
    }
    window.SaCommonManagerPro = SaCommonManagerPro;
})(window);