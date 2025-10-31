(function (window) {
    // Cache field mappings.
    let fieldMappings = null;
    function SmartManagerUrlParser() {
        if (typeof SmartManagerPro != 'undefined') {
            SmartManagerPro.apply();
        } else {
            SmartManager.apply();
        }
    }

    SmartManagerUrlParser.prototype = (typeof SmartManagerPro != 'undefined') ? Object.create(SmartManagerPro.prototype) : Object.create(SmartManager.prototype);
    SmartManagerUrlParser.prototype.constructor = SmartManagerUrlParser;

    /**
     * Build field mapping from window.smart_manager.advancedSearchFields
     * @returns {Object|false}
     */
    SmartManager.prototype.buildFieldMapping = function () {
        if (fieldMappings) {
            return fieldMappings;
        }
        let mapping = {};
        if (!window.smart_manager?.advancedSearchFields || !Array.isArray(window.smart_manager.advancedSearchFields)) {
            return false;
        }
        window.smart_manager.advancedSearchFields.forEach(field => {
            if (!field?.id || typeof field.id !== 'string'){
                return;
            }
            let parts = field.id.split('.');
            if (parts.length === 2) {
                let [tableWithPrefix, fieldName] = parts;
                let tableName = tableWithPrefix.replace(window[pluginKey].wpDbPrefix, '');
                mapping[fieldName] = { tableName, fullId: field.id };
            }
        });
        fieldMappings = mapping;
        return mapping;
    }

    /**
     * Check if field exists in advancedSearchFields
     * @param {string} fieldName
     * @returns {boolean}
     */
    SmartManager.prototype.isValidField = function (fieldName='') {
        let mapping = this.buildFieldMapping();
        return mapping && mapping.hasOwnProperty(fieldName);
    }

    /**
     * Get field info with table prefix
     * @param {string} fieldName
     * @param {string} tablePrefix
     * @returns {Object|false}
     */
    SmartManager.prototype.getFieldInfo = function (fieldName='', tablePrefix = '') {
        if (!fieldName || typeof fieldName !== 'string' || !tablePrefix || typeof tablePrefix !== 'string' || fieldName==='' || tablePrefix===''){
            return false;
        }
        let mapping = this.buildFieldMapping();
        let fieldData = mapping[fieldName];
        if (!fieldData?.tableName){
            return false;
        } 
        return { type: `${tablePrefix}${fieldData.tableName}.${fieldName}` };
    }
   
    /**
     * Parse URL parameters into filters
     * @param {URLSearchParams} params
     * @returns {Array}
     */
    SmartManager.prototype.parseUrlParams = function (params, showEditHistory = false) {
        if (!params || typeof params.entries !== 'function') {
            return [];
        }
        let filters = [];
        let specialParams = ['start_date', 'end_date'];
        let dateField = (window.smart_manager.dashboardKey) === 'product_stock_log' ? 'completed_date' : 'post_date';
        // Get date-related params
        let startDate = params.get('start_date');
        let endDate = params.get('end_date');
        if (startDate || endDate) {
            let start = null;
            let end = null;
            if (startDate || endDate) {
                start = startDate;
                end = endDate;
            }
            // Add date filters if valid
            if (this.isValidField(dateField)) {
                if (start) {
                    filters.push({ field: dateField, op: 'gte', value: start });
                }
                if (end) {
                    filters.push({ field: dateField, op: 'lte', value: end });
                }
            }
        }
        // Parse other parameters
        for (let [key, value] of params.entries()) {
            if(key.toLowerCase()==='id' && ( (window.smart_manager.dashboardKey === 'product_stock_log') || (showEditHistory===true))){
                key='record_id';
            }
            if (specialParams.includes(key) || !value || !this.isValidField(key)){
                continue;
            }
            filters.push({ field: key, op: 'eq', value: value });
        }
        return filters;
    }

    /**
     * Build JSON structure for advanced search
     * @param {Array} filters
     * @param {string} tablePrefix
     * @returns {Array}
     */
    SmartManager.prototype.buildJsonStructure = function (filters, tablePrefix) {
        if (!Array.isArray(filters) || filters.length === 0) {
            return false;
        }
        
        let rules = filters
            .filter(rule => rule?.field && rule?.op && rule?.value !== undefined)
            .map(rule => {
                let fieldInfo = this.getFieldInfo(rule.field, tablePrefix);
                if (fieldInfo?.type) {
                    return {
                        type: fieldInfo.type,
                        operator: rule.op,
                        value: rule.value
                    };
                }
                return null;
            })
            .filter(Boolean);
        
        if (rules.length === 0) {
            return false;
        }
        
        return [{
            condition: 'OR',
            rules: [{
                condition: 'AND',
                rules: rules
            }]
        }];
    }

    /**
     * Build advanced search params from URL
     * @param {string} url
     * @returns {Array|false}
     */
    SmartManager.prototype.buildSearchParamsFromUrl = function (url = '', showEditHistory = false) {
        if (!url || typeof url !== 'string' || url==='') {
            return false;
        }
        try {
            let urlObj = new URL(url);
            let filters = this.parseUrlParams(new URLSearchParams(urlObj.search), showEditHistory);
            if (!filters || filters.length === 0) {
                return false;
            }
            let searchRules = this.buildJsonStructure(filters, window[pluginKey].wpDbPrefix);
            return (searchRules && searchRules[0]?.rules?.length) ? searchRules : false;
        } catch (error) {
            console.error('Error parsing URL:', error);
            return false;
        }
    }

    if (typeof window.smart_manager_product === 'undefined') {
        window.smart_manager = new SmartManagerUrlParser();
    }
})(window);
