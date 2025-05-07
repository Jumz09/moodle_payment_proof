// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * JavaScript for handling payment proof uploads.
 *
 * @module     enrol_paymentproof/upload_handler
 * @copyright  2025 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/notification',
    'core/str',
    'core/ajax',
    'core/config',
    'core/templates',
    'core/form-autocomplete'
], function(
    $,
    Notification,
    Str,
    Ajax,
    Config,
    Templates,
    Autocomplete
) {

    /**
     * Maximum allowed file size in bytes.
     * @type {number}
     */
    const MAX_FILE_SIZE = 1024 * 1024 * 10; // 10MB

    /**
     * Allowed file extensions.
     * @type {Array}
     */
    const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];

    /**
     * Module initialization.
     * @param {Object} options Module configuration options
     */
    const init = function(options) {
        // Set the default options
        options = $.extend({
            formId: '#paymentproof_upload_form',
            fileInputId: '#id_paymentfile',
            submitButtonId: '#id_submitbutton',
            previewContainerId: '#file_preview',
            courseId: 0,
            maxFiles: 1
        }, options);

        const $form = $(options.formId);
        const $fileInput = $(options.fileInputId);
        const $submitButton = $(options.submitButtonId);
        const $previewContainer = $(options.previewContainerId);

        if (!$form.length) {
            return;
        }

        // Add file input change handler
        $fileInput.on('change', function(e) {
            handleFileSelection(e, options);
        });

        // Add form submit handler with validation
        $form.on('submit', function(e) {
            if (!validateForm(options)) {
                e.preventDefault();
                return false;
            }
            
            // Disable submit button to prevent double submission
            $submitButton.prop('disabled', true);
            $submitButton.addClass('disabled');
            
            return true;
        });

        // Initialize any custom form components
        initFormComponents(options);
    };

    /**
     * Handle file selection in the file input.
     * @param {Event} e The change event
     * @param {Object} options Module options
     */
    const handleFileSelection = function(e, options) {
        const $fileInput = $(options.fileInputId);
        const $previewContainer = $(options.previewContainerId);
        const $submitButton = $(options.submitButtonId);
        const files = e.target.files;

        // Clear preview
        $previewContainer.empty();

        if (files.length === 0) {
            return;
        }

        // Validate files and show preview for valid ones
        let allValid = true;
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const validationResult = validateFile(file);
            
            if (!validationResult.valid) {
                Notification.alert(
                    Str.get_string('error', 'core'),
                    validationResult.message,
                    Str.get_string('ok', 'core')
                );
                $fileInput.val('');
                allValid = false;
                break;
            }
            
            // Show preview
            if (file.type.match('image.*')) {
                createImagePreview(file, $previewContainer);
            } else {
                createFilePreview(file, $previewContainer);
            }
        }

        $submitButton.prop('disabled', !allValid);
    };

    /**
     * Create image preview for image files.
     * @param {File} file The file object
     * @param {jQuery} $container The container element
     */
    const createImagePreview = function(file, $container) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const $preview = $('<div class="file-preview-item">' +
                '<img src="' + e.target.result + '" alt="' + file.name + '" class="img-fluid mb-2">' +
                '<div class="file-name">' + file.name + '</div>' +
                '</div>');
            $container.append($preview);
        };
        reader.readAsDataURL(file);
    };

    /**
     * Create file preview for non-image files.
     * @param {File} file The file object
     * @param {jQuery} $container The container element
     */
    const createFilePreview = function(file, $container) {
        const extension = file.name.split('.').pop().toLowerCase();
        let iconClass = 'fa-file';
        
        // Determine the appropriate icon based on file type
        switch (extension) {
            case 'pdf':
                iconClass = 'fa-file-pdf';
                break;
            case 'doc':
            case 'docx':
                iconClass = 'fa-file-word';
                break;
            case 'xls':
            case 'xlsx':
                iconClass = 'fa-file-excel';
                break;
            default:
                iconClass = 'fa-file';
        }
        
        const $preview = $('<div class="file-preview-item">' +
            '<i class="fa ' + iconClass + ' fa-3x mb-2"></i>' +
            '<div class="file-name">' + file.name + '</div>' +
            '</div>');
        $container.append($preview);
    };

    /**
     * Validate the selected file.
     * @param {File} file The file object
     * @return {Object} Validation result with valid flag and message
     */
    const validateFile = function(file) {
        // Check file size
        if (file.size > MAX_FILE_SIZE) {
            return {
                valid: false,
                message: M.util.get_string('error:filesizeexceeded', 'enrol_paymentproof', {
                    size: formatFileSize(MAX_FILE_SIZE)
                })
            };
        }
        
        // Check file extension
        const extension = file.name.split('.').pop().toLowerCase();
        if (ALLOWED_EXTENSIONS.indexOf(extension) === -1) {
            return {
                valid: false,
                message: M.util.get_string('error:invalidfileextension', 'enrol_paymentproof', {
                    extensions: ALLOWED_EXTENSIONS.join(', ')
                })
            };
        }
        
        return {
            valid: true,
            message: ''
        };
    };

    /**
     * Format file size in human readable format.
     * @param {number} bytes The file size in bytes
     * @return {string} Formatted file size
     */
    const formatFileSize = function(bytes) {
        if (bytes === 0) {
            return '0 Bytes';
        }
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    /**
     * Validate form before submission.
     * @param {Object} options Module options
     * @return {boolean} Whether the form is valid
     */
    const validateForm = function(options) {
        const $fileInput = $(options.fileInputId);
        
        // Check if file is selected
        if ($fileInput[0].files.length === 0) {
            Notification.alert(
                Str.get_string('error', 'core'),
                M.util.get_string('error:nofileselected', 'enrol_paymentproof'),
                Str.get_string('ok', 'core')
            );
            return false;
        }
        
        return true;
    };

    /**
     * Initialize any additional form components.
     * @param {Object} options Module options
     */
    const initFormComponents = function(options) {
        // Initialize any autocomplete components
        Autocomplete.enhance(options.formId + ' .form-autocomplete-input');
        
        // Initialize any custom UI elements
        $(options.formId + ' .payment-info-toggle').on('click', function(e) {
            e.preventDefault();
            $(options.formId + ' .payment-info-content').toggleClass('hidden');
        });
    };

    return {
        init: init
    };
});