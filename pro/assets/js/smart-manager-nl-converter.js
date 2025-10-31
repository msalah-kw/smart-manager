/**
 * Simple Natural Language to JSON Converter
 * Exportable class for any module
 */
// Check if class already exists before declaring
if (typeof SmartManagerNLConverter === 'undefined') {
    window.SmartManagerNLConverter = class SmartManagerNLConverter {
        constructor(configData) {
            this.apiKey = configData?.apiKey || false;
            this.modules = new Map();
            this.micBtnID = configData?.micBtnID || false;
            this.promptInputID = configData?.promptInputID || false;
            this.onVoiceRecognitionComplete = configData?.onVoiceRecognitionComplete || false;
            this.init();
        }

        /**
         * Register a module with its system message and callbacks
         * @param {string} moduleId - Module identifier
         * @param {string} systemMessage - Dynamic system message for this module
         * @param {function} onSuccess - Success callback function
         * @param {function} onFailure - Failure callback function
         */
        addModule(data) {
            this.modules.set(data.moduleId, {
                systemMessage: data.systemMessage,
                onSuccess: data.onSuccess,
                beforeSend: data.beforeSend,
                onFailure: data.onFailure,
            });
        }

        /**
         * Convert natural language to JSON
         * @param {string} moduleId - Which module to use
         * @param {string} prompt - User prompt
         * @param {object} data - Optional settings
         */
        async convert(moduleId, prompt, data = {}) {
            const module = this.modules.get(moduleId);

            if (!module) {
                console.error(`Module ${moduleId} not found`);
                return;
            }

           if (!prompt) {
                module.onFailure(_x('Prompt is empty', 'error message', 'smart-manager-for-wp-e-commerce'));
                return;
            }
            if (module.hasOwnProperty('beforeSend') && typeof module.beforeSend === 'function') {
                module.beforeSend();
            }
            // Prepare request parameters in Smart Manager style.
            const requestParams = {
                data_type: 'json',
                data: {
                    cmd: 'get_query_params_from_ai',
                    security: window.smart_manager.saCommonNonce,
                    active_module: 'ai-connector',
                    dashboard_key: window.smart_manager?.dashboardKey || '',
                    prompt,
                    all_dashboards: window.smart_manager?.dashboardSelect2Items || '',
                    is_custom_view: window.smart_manager?.isCustomView || false,
                    show_variations: jQuery('#sm_products_show_variations').is(":checked"),
                }
            };
            //Before send request action.
            if (module.hasOwnProperty('beforeSend') && typeof module.beforeSend === 'function') {
                module.beforeSend();
            }
            //Send AJAX request.
            window.smart_manager.sendRequest(requestParams, function(response) {
                let ack = response.ACK || '';
                if (ack === 'Success') {
                    // Handle success response.
                    if (module.hasOwnProperty('onSuccess') && typeof module.onSuccess === 'function') {
                        module.onSuccess(response.data, prompt);
                    }
                } else if (ack === 'Failure' && response.msg) {
                module.onFailure(response.msg);
                } else {
                   module.onFailure(_x('Unknown error occurred', 'error message', 'smart-manager-for-wp-e-commerce'));
                }
            });
        }

        init() {
            this.voiceSearchSupport()
        }

        /**
         * Initializes voice search functionality using the Web Speech API
         * @method voiceSearchSupport
         * @description Sets up voice recognition on a microphone button to transcribe speech to text input.
         * Handles browser compatibility, recognition events, and UI feedback during voice input.
         * @returns {void}
         * @throws {Error} Logs error to console if voice recognition fails
         */
        voiceSearchSupport() {
            try {
                let micBtn = document.getElementById(this.micBtnID),
                    promptInput = document.getElementById(this.promptInputID),
                    promptPlaceholder = promptInput.placeholder,
                    SmartManagerNLConverter = this
                if (!micBtn || !promptInput) {
                    return;
                }
                if ('webkitSpeechRecognition' in window) {
                    let recognition = new webkitSpeechRecognition();
                    recognition.continuous = false;
                    recognition.interimResults = false;
                    recognition.lang = 'en-US';

                    micBtn.addEventListener('click', function () {
                        try {
                            promptInput.value = '';
                             promptInput.placeholder = _x('Listening...', 'voice recognition status', 'smart-manager-for-wp-e-commerce');
                            recognition.start();
                        } catch (error) {
                            console.error(_x('Error starting voice recognition:', 'error message', 'smart-manager-for-wp-e-commerce'), error);
                            promptInput.placeholder = promptPlaceholder;
                        }
                    });

                    recognition.onresult = function (event) {
                        try {
                            let transcript = event.results[0][0].transcript;
                                promptInput.value = transcript;
                            if(SmartManagerNLConverter.hasOwnProperty('onVoiceRecognitionComplete') && ( 'function' === typeof SmartManagerNLConverter.onVoiceRecognitionComplete)){
                                SmartManagerNLConverter.onVoiceRecognitionComplete()
                            }
                        } catch (error) {
                            console.error(_x('Error processing voice recognition result:', 'error message', 'smart-manager-for-wp-e-commerce'), error);
                        }
                    };

                    recognition.onend = function () {
                        promptInput.placeholder = promptPlaceholder;
                    };

                    recognition.onerror = function (event) {
                        console.error(_x('Voice recognition error:', 'error message', 'smart-manager-for-wp-e-commerce'), event.error);
                    };
                } else {
                    micBtn.disabled = true;
                    micBtn.title = _x('Voice recognition not supported in your browser', 'browser support message', 'smart-manager-for-wp-e-commerce');
                }
            } catch (error) {
                console.error(_x('Error in voiceSearchSupport:', 'error message', 'smart-manager-for-wp-e-commerce'), error);
            }
        }
    }
}
// Export for use in module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = window.SmartManagerNLConverter || SmartManagerNLConverter;
}
