<?php


function add_to_customizer($wp_customize){

    $wp_customize->add_panel( 'qji_plugin', array(
        'title' => __( 'QJI Plugin Options' ),
        'description' => 'Configure Plugin & API', // Include html tags such as <p>.
        'priority' => 160, // Mixed with top-level-section hierarchy.
    ) );

    $wp_customize->add_section( 'qji_plugin_section_api' , array(
        'title' => 'API',
        'panel' => 'qji_plugin',
        'description'   =>  'API information from Quintessence Jewelry'
    ) );
    $wp_customize->add_section( 'qji_plugin_section_pricing' , array(
        'title' => 'Pricing Options',
        'panel' => 'qji_plugin',
    ) );

    /**
     * API Username
     */
    $wp_customize->add_setting( 'qji_plugin_setting_api_user', array(
        'type' => 'option', // or 'option'
        'capability' => 'manage_options',
        'default' => '',
        'transport' => 'refresh', // or postMessage
    ) );
    $wp_customize->add_control( 'qji_plugin_setting_api_user', array(
        'type' => 'text',
        'priority' => 10, // Within the section.
        'section' => 'qji_plugin_section_api', // Required, core or custom.
        'label' => __( 'API Username' )
    ) );

    /**
     * API Password
     */
    $wp_customize->add_setting( 'qji_plugin_setting_api_password', array(
        'type' => 'option', // or 'option'
        'capability' => 'manage_options',
        'default' => '',
        'transport' => 'refresh', // or postMessage
    ) );
    $wp_customize->add_control( 'qji_plugin_setting_api_password', array(
        'type' => 'text',
        'priority' => 10, // Within the section.
        'section' => 'qji_plugin_section_api', // Required, core or custom.
        'label' => __( 'API Password' )
    ) );

    /**
     * API Enpoint
     */

    $wp_customize->add_setting( 'qji_plugin_setting_api_endpoint', array(
        'type' => 'option', // or 'option'
        'capability' => 'manage_options',
        'default' => '',
        'transport' => 'refresh', // or postMessage
    ) );
    $wp_customize->add_control( 'qji_plugin_setting_api_endpoint', array(
        'type' => 'text',
        'priority' => 10, // Within the section.
        'section' => 'qji_plugin_section_api', // Required, core or custom.
        'label' => __( 'API Endpoint' )
    ) );

    /**
     * Price Multiplier
     */
    $wp_customize->add_setting( 'qji_plugin_setting_price_multiplier', array(
        'type' => 'option', // or 'option'
        'capability' => 'manage_options',
        'default' => '2',
        'transport' => 'refresh', // or postMessage
    ) );
    $wp_customize->add_control( 'qji_plugin_setting_price_multiplier', array(
        'type' => 'text',
        'priority' => 10, // Within the section.
        'section' => 'qji_plugin_section_pricing', // Required, core or custom.
        'label' => __( 'Price Multiplier' )
    ) );
}


/**
 * render admin menu
 *
 */
function register_qji_admin_menu(){
    add_menu_page(
        __( 'QJI Admin Menu', 'qji' ),
        'Import Products',
        'manage_options',
        'qji-plugin-admin-menu',
        'render_qji_admin_menu',
        'dashicons-admin-tools',
        58
    );
}

function render_qji_admin_menu(){
    $context = [
        'title'     =>  'QJI Product Importer',
        'info'      =>  'Import products to Woocommerce'
    ];

    Timber::render('admin.twig', $context);
}


add_action('customize_register', 'add_to_customizer', 40);
add_action( 'admin_menu', 'register_qji_admin_menu', 10);