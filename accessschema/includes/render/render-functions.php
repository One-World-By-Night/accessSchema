<?php
/**
 * File: includes/render/render-functions.php
 * @version 2.0.3
 * Author: greghacke
 */

defined('ABSPATH') || exit;

/**
 * Render role badge
 */
function accessSchema_render_role_badge($role_path, $show_full_path = false) {
    $role_parts = explode('/', $role_path);
    $role_name = end($role_parts);
    
    $classes = array('accessSchema-badge');
    $classes[] = 'accessSchema-depth-' . count($role_parts);
    
    $title = $show_full_path ? '' : $role_path;
    
    return sprintf(
        '<span class="%s" title="%s">%s</span>',
        esc_attr(implode(' ', $classes)),
        esc_attr($title),
        esc_html($role_name)
    );
}

/**
 * Render user roles list
 */
function accessSchema_render_user_roles($user_id, $format = 'badges') {
    $roles = accessSchema_get_user_roles($user_id);
    
    if (empty($roles)) {
        return '<em>' . esc_html__('No roles assigned', 'accessschema') . '</em>';
    }
    
    $output = '';
    
    switch ($format) {
        case 'badges':
            $badges = array_map('accessSchema_render_role_badge', $roles);
            $output = '<div class="accessSchema-badges">' . implode(' ', $badges) . '</div>';
            break;
            
        case 'list':
            $output = '<ul class="accessSchema-role-list">';
            foreach ($roles as $role) {
                $output .= '<li>' . esc_html($role) . '</li>';
            }
            $output .= '</ul>';
            break;
            
        case 'comma':
            $output = esc_html(implode(', ', $roles));
            break;
    }
    
    return $output;
}

/**
 * Get role display name
 */
function accessSchema_get_role_display_name($role_path) {
    global $wpdb;
    $table = $wpdb->prefix . 'accessSchema_roles';
    
    $name = wp_cache_get('role_name_' . md5($role_path), 'accessSchema');
    
    if ($name === false) {
        $name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$table} WHERE full_path = %s",
            $role_path
        ));
        
        if (!$name) {
            $parts = explode('/', $role_path);
            $name = end($parts);
        }
        
        wp_cache_set('role_name_' . md5($role_path), $name, 'accessSchema', 3600);
    }
    
    return $name;
}

/**
 * Render role selector dropdown
 */
function accessSchema_render_role_selector($args = array()) {
    $defaults = array(
        'name' => 'accessSchema_role',
        'id' => 'accessSchema_role',
        'selected' => '',
        'show_option_none' => __('— Select Role —', 'accessschema'),
        'option_none_value' => '',
        'class' => 'accessSchema-role-selector',
        'required' => false,
        'multiple' => false,
        'hierarchical' => true
    );
    
    $args = wp_parse_args($args, $defaults);
    $roles = accessSchema_get_available_roles();
    
    $output = sprintf(
        '<select name="%s" id="%s" class="%s"%s%s>',
        esc_attr($args['name']),
        esc_attr($args['id']),
        esc_attr($args['class']),
        $args['required'] ? ' required' : '',
        $args['multiple'] ? ' multiple' : ''
    );
    
    if (!$args['multiple'] && $args['show_option_none']) {
        $output .= sprintf(
            '<option value="%s">%s</option>',
            esc_attr($args['option_none_value']),
            esc_html($args['show_option_none'])
        );
    }
    
    foreach ($roles['hierarchy'] as $path => $role) {
        $selected = '';
        if ($args['multiple'] && is_array($args['selected'])) {
            $selected = in_array($path, $args['selected'], true) ? ' selected' : '';
        } else {
            $selected = $path === $args['selected'] ? ' selected' : '';
        }
        
        $output .= sprintf(
            '<option value="%s"%s>%s</option>',
            esc_attr($path),
            $selected,
            esc_html($role['display_name'])
        );
    }
    
    $output .= '</select>';
    
    return $output;
}

/**
 * Check if rendering in admin context
 */
function accessSchema_is_admin_context() {
    return is_admin() || (defined('DOING_AJAX') && DOING_AJAX);
}

/**
 * Get role icon/color
 */
function accessSchema_get_role_style($role_path) {
    $depth = substr_count($role_path, '/');
    
    $colors = array(
        '#e74c3c', // Red
        '#3498db', // Blue
        '#2ecc71', // Green
        '#f39c12', // Orange
        '#9b59b6', // Purple
        '#1abc9c', // Turquoise
    );
    
    $color = $colors[$depth % count($colors)];
    
    return array(
        'color' => $color,
        'depth' => $depth
    );
}