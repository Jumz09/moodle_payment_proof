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
 * JavaScript for managing payment proof submissions.
 *
 * @module     enrol_paymentproof/submission_manager
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
    'core/modal_factory',
    'core/modal_events',
    'core/fragment'
], function(
    $,
    Notification,
    Str,
    Ajax,
    Config,
    Templates,
    ModalFactory,
    ModalEvents,
    Fragment
) {

    /**
     * Module initialization.
     * @param {Object} options Module configuration options
     */
    const init = function(options) {
        // Set default options
        options = $.extend({
            containerId: '#submission-manager-container',
            courseId: 0,
            contextId: 0,
            approveSelector: '.js-submission-approve',
            rejectSelector: '.js-submission-reject',
            viewSelector: '.js-submission-view',
            submissionListSelector: '.submission-list',
            refreshUrl: '',
            modalTitle: ''
        }, options);

        // Initialize the submission manager once the DOM is ready
        $(document).ready(function() {
            initEventHandlers(options);
        });
    };

    /**
     * Initialize event handlers.
     * @param {Object} options Module configuration options
     */
    const initEventHandlers = function(options) {
        const $container = $(options.containerId);

        // Handle approve action
        $container.on('click', options.approveSelector, function(e) {
            e.preventDefault();
            const submissionId = $(this).data('id');
            handleApprove(submissionId, options);
        });

        // Handle reject action
        $container.on('click', options.rejectSelector, function(e) {
            e.preventDefault();
            const submissionId = $(this).data('id');
            handleReject(submissionId, options);
        });

        // Handle view action
        $container.on('click', options.viewSelector, function(e) {
            e.preventDefault();
            const submissionId = $(this).data('id');
            handleView(submissionId, options);
        });

        // Initialize filters
        initFilters(options);
    };

    /**
     * Initialize submission list filters.
     * @param {Object} options Module configuration options
     */
    const initFilters = function(options) {
        const $container = $(options.containerId);
        const $filterSelects = $container.find('.submission-filter select');

        $filterSelects.on('change', function() {
            refreshSubmissionList(options);
        });

        $container.find('.submission-filter-reset').on('click', function(e) {
            e.preventDefault();
            $filterSelects.val('').trigger('change');
        });
    };

    /**
     * Handle the approve action for a submission.
     * @param {number} submissionId The submission ID
     * @param {Object} options Module configuration options
     */
    const handleApprove = function(submissionId, options) {
        // First confirm the action with the user
        Str.get_strings([
            {key: 'confirmapprovetitle', component: 'enrol_paymentproof'},
            {key: 'confirmapprove', component: 'enrol_paymentproof'},
            {key: 'approve', component: 'enrol_paymentproof'},
            {key: 'cancel', component: 'core'}
        ]).done(function(strings) {
            Notification.confirm(
                strings[0], // title
                strings[1], // message
                strings[2], // confirm button
                strings[3], // cancel button
                function() {
                    // User confirmed, proceed with approval
                    submitAction('approve', submissionId, options);
                }
            );
        });
    };

    /**
     * Handle the reject action for a submission.
     * @param {number} submissionId The submission ID
     * @param {Object} options Module configuration options
     */
    const handleReject = function(submissionId, options) {
        // Create a modal with a comments form for rejection reason
        ModalFactory.create({
            type: ModalFactory.types.SAVE_CANCEL,
            title: M.util.get_string('rejectreason', 'enrol_paymentproof'),
            body: '<div class="feedback-container">' +
                  '<textarea id="rejection-feedback" class="form-control" rows="5" ' +
                  'placeholder="' + M.util.get_string('rejectreason_placeholder', 'enrol_paymentproof') + '">' +
                  '</textarea></div>'
        }).then(function(modal) {
            modal.setSaveButtonText(M.util.get_string('reject', 'enrol_paymentproof'));
            
            // Handle form submission
            modal.getRoot().on(ModalEvents.save, function() {
                const feedback = $('#rejection-feedback').val();
                submitAction('reject', submissionId, options, {
                    feedback: feedback
                });
            });
            
            modal.show();
            return modal;
        }).catch(Notification.exception);
    };

    /**
     * Handle the view action for a submission.
     * @param {number} submissionId The submission ID
     * @param {Object} options Module configuration options
     */
    const handleView = function(submissionId, options) {
        // Create a modal to display submission details
        ModalFactory.create({
            type: ModalFactory.types.DEFAULT,
            title: options.modalTitle || M.util.get_string('submissiondetails', 'enrol_paymentproof'),
            body: getSubmissionModalBody(submissionId, options)
        }).then(function(modal) {
            // Add action buttons to the modal footer if needed
            const $footer = modal.getFooter();
            
            // Handle approval and rejection in modal
            if ($footer) {
                Promise.all([
                    Str.get_string('approve', 'enrol_paymentproof'),
                    Str.get_string('reject', 'enrol_paymentproof')
                ]).then(function(strings) {
                    const $approveBtn = $('<button class="btn btn-success">' + strings[0] + '</button>');
                    const $rejectBtn = $('<button class="btn btn-danger">' + strings[1] + '</button>');
                    
                    $approveBtn.on('click', function() {
                        modal.hide();
                        handleApprove(submissionId, options);
                    });
                    
                    $rejectBtn.on('click', function() {
                        modal.hide();
                        handleReject(submissionId, options);
                    });
                    
                    $footer.append($approveBtn).append($rejectBtn);
                    return;
                }).catch(Notification.exception);
            }
            
            modal.show();
            return modal;
        }).catch(Notification.exception);
    };

    /**
     * Get the submission modal body content.
     * @param {number} submissionId The submission ID
     * @param {Object} options Module configuration options
     * @return {Promise} Promise resolved with the rendered content
     */
    const getSubmissionModalBody = function(submissionId, options) {
        // Load submission details via AJAX
        return Fragment.loadFragment(
            'enrol_paymentproof',
            'submission_details',
            options.contextId,
            {
                submissionid: submissionId,
                courseid: options.courseId
            }
        );
    };

    /**
     * Submit an action (approve/reject) for a submission.
     * @param {string} action The action to perform ('approve' or 'reject')
     * @param {number} submissionId The submission ID
     * @param {Object} options Module configuration options
     * @param {Object} [data] Additional data to send with the request
     */
    const submitAction = function(action, submissionId, options, data) {
        // Show processing indicator
        const $container = $(options.containerId);
        const $loadingIndicator = $('<div class="loading-indicator text-center">' +
            '<span class="spinner-border text-primary" role="status"></span>' +
            '<span class="sr-only">' + M.util.get_string('loading', 'core') + '</span>' +
            '</div>');
        
        $container.find(options.submissionListSelector).prepend($loadingIndicator);
        
        // Prepare request data
        const requestData = $.extend({
            submissionid: submissionId,
            courseid: options.courseId,
            action: action,
            sesskey: M.cfg.sesskey
        }, data || {});
        
        // Perform the AJAX request
        Ajax.call([{
            methodname: 'enrol_paymentproof_process_submission',
            args: requestData,
            done: function(response) {
                $loadingIndicator.remove();
                
                if (response.success) {
                    // Show success notification
                    Notification.addNotification({
                        message: response.message,
                        type: 'success'
                    });
                    
                    // Refresh the submission list
                    refreshSubmissionList(options);
                } else {
                    // Show error notification
                    Notification.addNotification({
                        message: response.message,
                        type: 'error'
                    });
                }
            },
            fail: function(error) {
                $loadingIndicator.remove();
                Notification.exception(error);
            }
        }]);
    };

    /**
     * Refresh the submission list.
     * @param {Object} options Module configuration options
     */
    const refreshSubmissionList = function(options) {
        const $container = $(options.containerId);
        const $list = $container.find(options.submissionListSelector);
        
        // Collect filter values
        const filters = {};
        $container.find('.submission-filter select').each(function() {
            const name = $(this).attr('name');
            const value = $(this).val();
            
            if (value !== '') {
                filters[name] = value;
            }
        });
        
        // Show loading indicator
        const $loadingIndicator = $('<div class="loading-indicator text-center">' +
            '<span class="spinner-border text-primary" role="status"></span>' +
            '<span class="sr-only">' + M.util.get_string('loading', 'core') + '</span>' +
            '</div>');
        
        $list.html($loadingIndicator);
        
        // Load the updated submission list
        if (options.refreshUrl) {
            $.ajax({
                url: options.refreshUrl,
                method: 'GET',
                data: $.extend({
                    courseid: options.courseId,
                    sesskey: M.cfg.sesskey
                }, filters),
                success: function(html) {
                    $list.html(html);
                },
                error: function(xhr, status, error) {
                    $loadingIndicator.remove();
                    Notification.exception(new Error(error));
                }
            });
        } else {
            // Use web service if no refresh URL provided
            Ajax.call([{
                methodname: 'enrol_paymentproof_get_submissions',
                args: {
                    courseid: options.courseId,
                    filters: filters
                },
                done: function(response) {
                    if (response.html) {
                        $list.html(response.html);
                    } else {
                        $loadingIndicator.remove();
                        Notification.addNotification({
                            message: response.message || M.util.get_string('error:refreshlist', 'enrol_paymentproof'),
                            type: 'error'
                        });
                    }
                },
                fail: function(error) {
                    $loadingIndicator.remove();
                    Notification.exception(error);
                }
            }]);
        }
    };

    return {
        init: init
    };
});