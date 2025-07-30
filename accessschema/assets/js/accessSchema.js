/**
 * File: assets/js/accessSchema.js
 * @version 2.0.3
 * @author greghacke
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        initializeRoleManager();
        initializeUserRoles();
        initializeSelect2();
    });
    
    // Role Manager functionality
    function initializeRoleManager() {
        if (!$('#accessSchema-roles-table').length) return;
        
        // Enhanced role filter with Select2
        const $filter = $('#accessSchema-role-filter');
        if ($filter.length) {
            $filter.select2({
                placeholder: 'Search roles...',
                allowClear: true,
                minimumInputLength: 2,
                ajax: {
                    url: accessSchema_ajax.ajax_url,
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: 'accessSchema_search_roles',
                            nonce: accessSchema_ajax.nonce,
                            q: params.term,
                            page: params.page || 1
                        };
                    },
                    processResults: function(data, params) {
                        params.page = params.page || 1;
                        return {
                            results: data.results,
                            pagination: {
                                more: data.pagination.more
                            }
                        };
                    },
                    cache: true
                },
                templateResult: formatRoleResult,
                templateSelection: formatRoleSelection
            });
            
            // Filter table on selection
            $filter.on('select2:select', function(e) {
                filterTable(e.params.data.id);
            });
            
            $filter.on('select2:clear', function() {
                $('#accessSchema-roles-table tbody tr').show();
            });
        }
        
        // Edit role handler
        $(document).on('click', '.accessSchema-edit-role', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const roleId = $btn.data('id');
            const roleName = $btn.data('name');
            
            $('#edit_role_id').val(roleId);
            $('#edit_role_name').val(roleName);
            $('#accessSchema-edit-modal').fadeIn(200);
        });
        
        // Edit form submission
        $('#accessSchema-edit-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const data = {
                action: 'accessSchema_edit_role',
                nonce: $form.find('#edit_nonce').val(),
                role_id: $('#edit_role_id').val(),
                role_name: $('#edit_role_name').val()
            };
            
            $form.addClass('accessSchema-loading');
            
            $.post(accessSchema_ajax.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        showError(response.data || accessSchema_ajax.i18n.error);
                    }
                })
                .fail(function() {
                    showError(accessSchema_ajax.i18n.error);
                })
                .always(function() {
                    $form.removeClass('accessSchema-loading');
                });
        });
    }
    
    // User Role Management
    function initializeUserRoles() {
        if (!$('#accessSchema-assigned-roles').length) return;
        
        const $container = $('#accessSchema-assigned-roles');
        const removedRoles = [];
        
        // Remove role handler
        $(document).on('click', '.remove-role-button', function(e) {
            e.preventDefault();
            
            const $chip = $(this).closest('.access-role-tag');
            const rolePath = $chip.data('role');
            
            // Add to removed roles
            removedRoles.push(rolePath);
            $('#accessSchema_removed_roles').val(removedRoles.join(','));
            
            // Animate removal
            $chip.fadeOut(200, function() {
                $(this).remove();
                
                if ($container.find('.access-role-tag').length === 0) {
                    $container.html('<p class="no-roles-message"><em>' + accessSchema_admin.i18n.no_roles + '</em></p>');
                }
                
                // Re-add to Select2
                const $select = $('#accessSchema_add_roles');
                if ($select.length) {
                    // Get current Select2 data
                    const currentData = $select.select2('data');
                    
                    // Create new option
                    const newOption = {
                        id: rolePath,
                        text: rolePath,
                        disabled: false
                    };
                    
                    // Trigger change to update Select2
                    $select.append(new Option(newOption.text, newOption.id, false, false));
                    $select.trigger('change.select2');
                }
            });
        });
    }
    
    // Initialize Select2 with advanced features
    function initializeSelect2() {
        const $select = $('#accessSchema_add_roles');
        
        if ($select.length && $.fn.select2) {
            $select.select2({
                placeholder: 'Select roles to add...',
                width: '100%',
                allowClear: true,
                closeOnSelect: false,
                multiple: true,
                
                // Enable tagging for custom roles
                tags: $select.data('allow-tags') || false,
                
                // Search configuration
                minimumInputLength: 0,
                maximumSelectionLength: $select.data('max-roles') || 0,
                
                // Custom rendering
                templateResult: formatRoleDropdown,
                templateSelection: formatRoleSelection,
                
                // Enhanced search
                matcher: hierarchicalMatcher,
                
                // Sorting
                sorter: function(data) {
                    return data.sort(function(a, b) {
                        // Sort by depth first, then alphabetically
                        const depthA = $(a.element).data('depth') || 0;
                        const depthB = $(b.element).data('depth') || 0;
                        
                        if (depthA !== depthB) {
                            return depthA - depthB;
                        }
                        
                        return a.text.localeCompare(b.text);
                    });
                },
                
                // Language customization
                language: {
                    noResults: function() {
                        return accessSchema_admin.i18n.no_results || 'No roles found';
                    },
                    searching: function() {
                        return accessSchema_admin.i18n.searching || 'Searching...';
                    },
                    maximumSelected: function(args) {
                        return 'You can only select ' + args.maximum + ' roles';
                    }
                }
            });
            
            // Handle selection validation
            $select.on('select2:selecting', function(e) {
                const userId = $('#accessSchema-assigned-roles').data('user-id');
                const rolePath = e.params.args.data.id;
                
                // Validate via AJAX
                $.ajax({
                    url: accessSchema_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'accessSchema_validate_role',
                        nonce: accessSchema_admin.nonce,
                        user_id: userId,
                        role_path: rolePath
                    },
                    async: false,
                    success: function(response) {
                        if (!response.success) {
                            e.preventDefault();
                            showError(response.data);
                            
                            // Highlight invalid option
                            setTimeout(function() {
                                $('.select2-results__option--highlighted').addClass('select2-results__option--disabled');
                            }, 100);
                        }
                    }
                });
            });
            
            // Handle selection
            $select.on('select2:select', function(e) {
                const data = e.params.data;
                
                // Add role tag immediately (optimistic UI)
                const $container = $('#accessSchema-assigned-roles');
                $container.find('.no-roles-message').remove();
                
                // Use full path (data.id) for display
                const $tag = $('<span class="access-role-tag" data-role="' + escapeHtml(data.id) + '">' +
                    '<span class="role-name" title="' + escapeHtml(data.text) + '">' + escapeHtml(data.id) + '</span>' +
                    '<button type="button" class="remove-role-button" aria-label="Remove role">' +
                    '<span aria-hidden="true">×</span></button>' +
                    '<input type="hidden" name="accessSchema_current_roles[]" value="' + escapeHtml(data.id) + '">' +
                    '</span>');
                
                $container.append($tag);
                
                // Remove from Select2 options
                const $option = $select.find('option[value="' + data.id + '"]');
                $option.prop('disabled', true);
                $select.trigger('change.select2');
            });
            
            // Enable Select2 search in dropdown
            $select.on('select2:open', function() {
                setTimeout(function() {
                    $('.select2-search__field').focus();
                }, 100);
            });
        }
    }
    
    // Format role in dropdown with hierarchy
    function formatRoleDropdown(role) {
        if (!role.id) return role.text;
        
        const $element = $(role.element);
        const depth = $element.data('depth') || 0;
        const padding = depth * 20;
        
        // Build hierarchical display
        const $result = $('<span class="select2-role-option" style="padding-left:' + padding + 'px">');
        
        // Add icon based on depth
        if (depth === 0) {
            $result.append('<span class="dashicons dashicons-category" style="color: #1565c0;"></span> ');
        } else if (depth === 1) {
            $result.append('<span class="dashicons dashicons-networking" style="color: #6a1b9a;"></span> ');
        } else {
            $result.append('<span class="dashicons dashicons-admin-users" style="color: #2e7d32;"></span> ');
        }
        
        $result.append(escapeHtml(role.text));
        
        // Add path info
        if (role.id !== role.text) {
            $result.append('<small style="color: #666; display: block; padding-left: 20px;">' + escapeHtml(role.id) + '</small>');
        }
        
        return $result;
    }
    
    // Format selected role
    function formatRoleSelection(role) {
        if (!role.id) return role.text;
        
        // Show only the last part of the path
        const parts = role.id.split('/');
        return escapeHtml(parts[parts.length - 1]);
    }
    
    // Hierarchical search matcher
    function hierarchicalMatcher(params, data) {
        // Always return the item if there is no search term
        if ($.trim(params.term) === '') {
            return data;
        }
        
        // Do not display the item if there is no 'text' property
        if (typeof data.text === 'undefined') {
            return null;
        }
        
        const term = params.term.toLowerCase();
        const text = data.text.toLowerCase();
        const id = (data.id || '').toLowerCase();
        
        // Check if the search term appears in the text or full path
        if (text.indexOf(term) > -1 || id.indexOf(term) > -1) {
            return data;
        }
        
        // Check if any part of the path matches
        const parts = id.split('/');
        for (let i = 0; i < parts.length; i++) {
            if (parts[i].indexOf(term) > -1) {
                return data;
            }
        }
        
        return null;
    }
    
    // Format role result for search
    function formatRoleResult(role) {
        if (role.loading) {
            return role.text;
        }
        
        const markup = '<div class="select2-role-result">' +
            '<div class="select2-role-result__title">' + escapeHtml(role.name) + '</div>' +
            '<div class="select2-role-result__path">' + escapeHtml(role.full_path) + '</div>' +
            '</div>';
        
        return $(markup);
    }
    
    // Filter table helper
    function filterTable(searchPath) {
        const $rows = $('#accessSchema-roles-table tbody tr');
        
        $rows.each(function() {
            const $row = $(this);
            const path = $row.find('.role-path').text();
            
            if (path.indexOf(searchPath) > -1) {
                $row.show();
            } else {
                $row.hide();
            }
        });
    }
    
    // Show error message
    function showError(message) {
        const $errors = $('#accessSchema-role-errors');
        
        if ($errors.length) {
            $errors.html('<p>' + escapeHtml(message) + '</p>').slideDown();
            
            setTimeout(function() {
                $errors.slideUp();
            }, 5000);
        } else {
            // Use Select2 notice feature
            const $notice = $('<div class="notice notice-error"><p>' + escapeHtml(message) + '</p></div>');
            $('.select2-container').after($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    }
    
    // Escape HTML
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Modal handlers
    window.closeEditModal = function() {
        $('#accessSchema-edit-modal').fadeOut(200);
    };
    
    $('#accessSchema-edit-modal').on('click', function(e) {
        if (e.target === this) {
            closeEditModal();
        }
    });
    
    $(document).on('keyup', function(e) {
        if (e.key === 'Escape') {
            closeEditModal();
        }
    });
    
})(jQuery);

// Global init
window.accessSchema_init = function() {
    jQuery(document).trigger('accessSchema:init');
};