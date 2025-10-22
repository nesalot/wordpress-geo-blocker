<?php
/**
 * Load and configure VIP Go Geo Uniques plugin
 */

// Load the VIP Go Geo Uniques plugin
wpcom_vip_load_plugin( 'vip-go-geo-uniques' );

// Configure it immediately after loading
if ( class_exists( 'VIP_Go_Geo_Uniques' ) ) {
    // Set default location to US
    VIP_Go_Geo_Uniques::set_default_location( 'US' );
    
    // Add US as a tracked location
    VIP_Go_Geo_Uniques::add_location( 'US' );
    
    // Add countries we want to block
    VIP_Go_Geo_Uniques::add_location( 'CN' ); // China
    VIP_Go_Geo_Uniques::add_location( 'RU' ); // Russia
    VIP_Go_Geo_Uniques::add_location( 'SG' ); // Singapore
    VIP_Go_Geo_Uniques::add_location( 'BR' ); // Brazil
}