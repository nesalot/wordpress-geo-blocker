<?php
/**
 * Plugin Name: LoadUp - Geo Blocking
 * Description: Blocks visitors from specified countries using WP VIP geo-targeting
 * Version: 1.2.0
 * Author: Justin Merrell
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Blocked countries: CN=China, RU=Russia, SG=Singapore, BR=Brazil
define( 'LOADUP_BLOCKED_COUNTRIES', 'CN,RU,SG,BR' );

// Country code to name mapping
function loadup_get_country_name( $code ) {
    $countries = array(
        'CN' => 'China',
        'RU' => 'Russia',
        'SG' => 'Singapore',
        'BR' => 'Brazil',
        'US' => 'United States',
    );
    return isset( $countries[ $code ] ) ? $countries[ $code ] : $code;
}

//Track blocked attempt with activity log
function loadup_track_block( $country_code ) {
    $today = date( 'Y-m-d' );
    $key = 'loadup_blocks_' . $today;

    $data = get_option( $key );
    if ( ! $data ) {
        $data = array();
    }

    if ( ! isset( $data[ $country_code ] ) ) {
        $data[ $country_code ] = 0;
    }
    $data[ $country_code ]++;

    update_option( $key, $data, false );

    // Track last 200 requests for activity monitoring
    $activity_log = get_option( 'loadup_activity_log' );
    if ( ! $activity_log ) {
        $activity_log = array();
    }

    // Get IP address (handle proxies)
    $ip_address = 'unknown';
    if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
        $ip_address = $_SERVER['REMOTE_ADDR'];
    }

    array_unshift( $activity_log, array(
        'time' => current_time( 'mysql' ),
        'country' => $country_code,
        'ip' => $ip_address,
        'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'referer' => $_SERVER['HTTP_REFERER'] ?? 'direct',
        'user_agent' => substr( $_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 100 ),
    ) );

    // Keep only last 200
    $activity_log = array_slice( $activity_log, 0, 200 );

    update_option( 'loadup_activity_log', $activity_log, false );
}

//Block visitors from restricted countries
function loadup_block_countries() {
    // Don't block admin, AJAX, or REST API
    if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return;
    }

    // Allow VIP support staff
    if ( function_exists( 'is_proxied_automattician' ) && is_proxied_automattician() ) {
        return;
    }

    // Check if visitor's country is blocked
    if ( function_exists( 'vip_geo_get_country_code' ) ) {
        $country_code = vip_geo_get_country_code();
        $blocked_countries = explode( ',', LOADUP_BLOCKED_COUNTRIES );

        if ( in_array( $country_code, $blocked_countries, true ) ) {
            loadup_track_block( $country_code );
            loadup_display_blocked_page();
        }
    }
}
add_action( 'template_redirect', 'loadup_block_countries', 1 );

//Display blocked page
function loadup_display_blocked_page() {
    status_header( 403 );

    // Load blocked page template
    $template_path = plugin_dir_path( __FILE__ ) . 'templates/blocked-page.php';

    if ( file_exists( $template_path ) ) {
        include $template_path;
    } else {
        // Fallback if template file is missing
        wp_die(
            'Access to this website is not available from your current location.',
            'Access Restricted',
            array( 'response' => 403 )
        );
    }

    exit;
}

//Add admin menu for stats
function loadup_add_admin_menu() {
    add_management_page(
        'Geo Blocking Stats',
        'Geo Blocks',
        'manage_options',
        'loadup-geo-blocks',
        'loadup_display_stats_page'
    );
}
add_action( 'admin_menu', 'loadup_add_admin_menu' );

//Display stats page
function loadup_display_stats_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Handle clear stats action
    if ( isset( $_POST['clear_stats'] ) && check_admin_referer( 'loadup_clear_stats' ) ) {
        loadup_clear_all_stats();
        echo '<div class="notice notice-success is-dismissible"><p><strong>Statistics cleared successfully!</strong></p></div>';
    }

    $total = 0;
    $country_totals = array();
    $daily_counts = array();

    // Gather last 30 days
    for ( $i = 0; $i < 30; $i++ ) {
        $date = date( 'Y-m-d', strtotime( "-{$i} days" ) );
        $key = 'loadup_blocks_' . $date;
        $data = get_option( $key );

        $day_total = 0;
        if ( $data ) {
            foreach ( $data as $country => $count ) {
                if ( ! isset( $country_totals[ $country ] ) ) {
                    $country_totals[ $country ] = 0;
                }
                $country_totals[ $country ] += $count;
                $total += $count;
                $day_total += $count;
            }
        }
        $daily_counts[ $date ] = $day_total;
    }

    // Sort by count descending
    arsort( $country_totals );

    // Calculate today's blocks
    $today = date( 'Y-m-d' );
    $today_total = isset( $daily_counts[ $today ] ) ? $daily_counts[ $today ] : 0;

    // Calculate yesterday's blocks
    $yesterday = date( 'Y-m-d', strtotime( '-1 day' ) );
    $yesterday_total = isset( $daily_counts[ $yesterday ] ) ? $daily_counts[ $yesterday ] : 0;

    // Calculate daily average (only count days with data)
    $days_with_data = 0;
    foreach ( $daily_counts as $count ) {
        if ( $count > 0 ) {
            $days_with_data++;
        }
    }
    $daily_average = ( $total > 0 && $days_with_data > 0 ) ? round( $total / $days_with_data, 1 ) : 0;

    // Get top country
    $top_country = '';
    $top_country_count = 0;
    if ( $country_totals ) {
        reset( $country_totals );
        $top_country_code = key( $country_totals );
        $top_country = loadup_get_country_name( $top_country_code );
        $top_country_count = current( $country_totals );
    }

    // Find peak day
    $peak_day = '';
    $peak_count = 0;
    foreach ( $daily_counts as $date => $count ) {
        if ( $count > $peak_count ) {
            $peak_count = $count;
            $peak_day = $date;
        }
    }
    $peak_day_formatted = $peak_day ? date( 'M j', strtotime( $peak_day ) ) : 'N/A';

    // Calculate weekly trend (last 7 days vs previous 7 days)
    $last_week = 0;
    $previous_week = 0;
    for ( $i = 0; $i < 7; $i++ ) {
        $date = date( 'Y-m-d', strtotime( "-{$i} days" ) );
        $last_week += isset( $daily_counts[ $date ] ) ? $daily_counts[ $date ] : 0;
    }
    for ( $i = 7; $i < 14; $i++ ) {
        $date = date( 'Y-m-d', strtotime( "-{$i} days" ) );
        $previous_week += isset( $daily_counts[ $date ] ) ? $daily_counts[ $date ] : 0;
    }

    $trend_percentage = 0;
    $trend_direction = '→';
    $trend_color = '#666';
    if ( $previous_week > 0 ) {
        $trend_percentage = round( ( ( $last_week - $previous_week ) / $previous_week ) * 100 );
        if ( $trend_percentage > 0 ) {
            $trend_direction = '↑';
            $trend_color = '#d63638';
        } elseif ( $trend_percentage < 0 ) {
            $trend_direction = '↓';
            $trend_color = '#00a32a';
        }
    } elseif ( $last_week > 0 ) {
        $trend_percentage = 100;
        $trend_direction = '↑';
        $trend_color = '#d63638';
    }

    // Calculate today vs yesterday for the Today widget
    $today_vs_yesterday_diff = $today_total - $yesterday_total;
    $today_arrow = '→';
    $today_color = '#666';
    if ( $today_vs_yesterday_diff > 0 ) {
        $today_arrow = '↑';
        $today_color = '#d63638'; // Red for increase
    } elseif ( $today_vs_yesterday_diff < 0 ) {
        $today_arrow = '↓';
        $today_color = '#00a32a'; // Green for decrease
    }

    // Get activity log
    $activity_log = get_option( 'loadup_activity_log' );

    // Handle search filtering
    $search_term = isset( $_GET['ip_search'] ) ? sanitize_text_field( $_GET['ip_search'] ) : '';
    $filtered_log = $activity_log;

    if ( $search_term !== '' && $activity_log ) {
        $filtered_log = array_filter( $activity_log, function( $entry ) use ( $search_term ) {
            $ip = $entry['ip'] ?? 'unknown';
            return stripos( $ip, $search_term ) !== false;
        } );
        $filtered_log = array_values( $filtered_log ); // Re-index array
    }

    // Pagination
    $per_page = 25;
    $current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;

    $total_entries = $activity_log ? count( $activity_log ) : 0;
    $filtered_entries = $filtered_log ? count( $filtered_log ) : 0;
    $total_pages = ceil( $filtered_entries / $per_page );
    $offset = ( $current_page - 1 ) * $per_page;
    $activity_log_page = $filtered_log ? array_slice( $filtered_log, $offset, $per_page ) : array();

    // Calculate IP statistics (from all entries, not filtered)
    $ip_counts = array();
    $url_counts = array();
    if ( $activity_log ) {
        foreach ( $activity_log as $entry ) {
            $ip = $entry['ip'] ?? 'unknown';
            if ( ! isset( $ip_counts[ $ip ] ) ) {
                $ip_counts[ $ip ] = 0;
            }
            $ip_counts[ $ip ]++;

            $url = $entry['url'] ?? 'unknown';
            if ( ! isset( $url_counts[ $url ] ) ) {
                $url_counts[ $url ] = 0;
            }
            $url_counts[ $url ]++;
        }
    }
    arsort( $ip_counts );
    arsort( $url_counts );
    $unique_ips = count( $ip_counts );
    $top_ips = array_slice( $ip_counts, 0, 10, true );
    $top_urls = array_slice( $url_counts, 0, 10, true );

    // Get full country names for blocked countries list
    $blocked_codes = explode( ',', LOADUP_BLOCKED_COUNTRIES );
    $blocked_names = array_map( 'loadup_get_country_name', $blocked_codes );
    $blocked_countries_text = implode( ', ', $blocked_names );

    ?>
    <div class="wrap">
        <h1>Geo Blocking Statistics</h1>

        <div style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccc; border-radius: 4px;">
            <h2 style="margin-top: 0;">Last 30 Days Summary</h2>
            <p style="font-size: 16px; color: #666; margin-bottom: 15px;">
                Total blocked attempts: <strong style="font-size: 24px; color: #d63638;"><?php echo number_format( $total ); ?></strong>
            </p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px;">

                <!-- TODAY WIDGET (Combined with Yesterday comparison) -->
                <div style="padding: 12px; background: #f7f7f7; border-radius: 4px;">
                    <div style="font-size: 13px; color: #666; margin-bottom: 4px;">📅 Today</div>
                    <div style="font-size: 20px; font-weight: bold; color: #2271b1;"><?php echo number_format( $today_total ); ?> blocks</div>
                    <div style="font-size: 12px; color: #666;">
                        vs yesterday (<?php echo number_format( $yesterday_total ); ?>)
                        <span style="color: <?php echo $today_color; ?>; font-weight: bold;"><?php echo $today_arrow; ?> <?php echo $today_vs_yesterday_diff >= 0 ? '+' : ''; ?><?php echo number_format( abs( $today_vs_yesterday_diff ) ); ?></span>
                    </div>
                </div>

                <div style="padding: 12px; background: #f7f7f7; border-radius: 4px;">
                    <div style="font-size: 13px; color: #666; margin-bottom: 4px;">📊 Daily Average</div>
                    <div style="font-size: 20px; font-weight: bold; color: #2271b1;"><?php echo number_format( $daily_average, 1 ); ?> blocks</div>
                </div>

                <?php if ( $top_country ) : ?>
                <div style="padding: 12px; background: #f7f7f7; border-radius: 4px;">
                    <div style="font-size: 13px; color: #666; margin-bottom: 4px;">🌍 Top Country</div>
                    <div style="font-size: 20px; font-weight: bold; color: #2271b1;"><?php echo esc_html( $top_country ); ?></div>
                    <div style="font-size: 12px; color: #666;"><?php echo number_format( $top_country_count ); ?> blocks</div>
                </div>
                <?php endif; ?>

                <?php if ( $peak_day ) : ?>
                <div style="padding: 12px; background: #f7f7f7; border-radius: 4px;">
                    <div style="font-size: 13px; color: #666; margin-bottom: 4px;">📈 Peak Day</div>
                    <div style="font-size: 20px; font-weight: bold; color: #2271b1;"><?php echo esc_html( $peak_day_formatted ); ?></div>
                    <div style="font-size: 12px; color: #666;"><?php echo number_format( $peak_count ); ?> blocks</div>
                </div>
                <?php endif; ?>

                <div style="padding: 12px; background: #f7f7f7; border-radius: 4px;">
                    <div style="font-size: 13px; color: #666; margin-bottom: 4px;">📈 Weekly Trend</div>
                    <div style="font-size: 20px; font-weight: bold; color: <?php echo $trend_color; ?>;">
                        <?php echo $trend_direction; ?> <?php echo abs( $trend_percentage ); ?>%
                    </div>
                    <div style="font-size: 12px; color: #666;">vs last week</div>
                </div>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 200px;">Country</th>
                    <th>Blocks (30 days)</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $country_totals ) : ?>
                    <?php foreach ( $country_totals as $country => $count ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( loadup_get_country_name( $country ) ); ?></strong></td>
                            <td><?php echo number_format( $count ); ?></td>
                            <td><?php echo $total > 0 ? round( ( $count / $total ) * 100, 1 ) : 0; ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="background: #f0f0f1; font-weight: bold;">
                        <td>TOTAL</td>
                        <td><?php echo number_format( $total ); ?></td>
                        <td>100%</td>
                    </tr>
                <?php else : ?>
                    <tr>
                        <td colspan="3" style="text-align: center; padding: 40px; color: #666;">
                            No blocks recorded yet. Data will appear here once visitors from blocked countries attempt to access the site.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <p style="margin-top: 20px; color: #666;">
            <em>Blocked countries: <?php echo esc_html( $blocked_countries_text ); ?></em>
        </p>

        <!-- RECENT ACTIVITY -->
        <div style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccc; border-radius: 4px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;">📊 Recent Activity</h2>

                <!-- Search Box - Moved to top right -->
                <form method="get" style="margin: 0;">
                    <input type="hidden" name="page" value="loadup-geo-blocks">
                    <input type="text" name="ip_search" id="ip-search-input" value="<?php echo esc_attr( $search_term ); ?>"
                        placeholder="Search by IP..."
                        style="width: 200px; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; vertical-align: middle;">
                    <button type="submit" class="button button-small" style="vertical-align: middle; margin-left: 5px;">Search</button>
                    <?php if ( $search_term !== '' ) : ?>
                        <a href="<?php echo esc_url( remove_query_arg( array( 'ip_search', 'paged' ) ) ); ?>"
                           class="button button-small" style="vertical-align: middle; margin-left: 5px;">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if ( $search_term !== '' ) : ?>
                <div style="background: #e7f3ff; padding: 12px; margin-bottom: 15px; border-left: 4px solid #2271b1; border-radius: 4px;">
                    <strong>🔍 Search Results:</strong> Found <strong><?php echo number_format( $filtered_entries ); ?></strong>
                    <?php echo $filtered_entries === 1 ? 'entry' : 'entries'; ?> matching
                    "<strong><?php echo esc_html( $search_term ); ?></strong>"
                    <?php if ( $filtered_entries === 0 ) : ?>
                        <span style="color: #d63638;">- No matches found</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- IP Statistics Summary -->
            <?php if ( $activity_log ) : ?>
                <div style="background: #f7f7f7; padding: 15px; margin-bottom: 20px; border-radius: 4px; display: flex; gap: 30px; flex-wrap: wrap;">
                    <div>
                        <strong style="color: #2271b1;">Total Requests:</strong> <?php echo number_format( $total_entries ); ?>
                    </div>
                    <div>
                        <strong style="color: #2271b1;">Unique IPs:</strong> <?php echo number_format( $unique_ips ); ?>
                    </div>
                    <div>
                        <strong style="color: #2271b1;">Avg per IP:</strong> <?php echo number_format( $total_entries / max( 1, $unique_ips ), 1 ); ?>
                    </div>
                </div>
            <?php endif; ?>

            <p style="color: #666; margin-bottom: 15px;">
                Showing <?php echo number_format( min( $per_page, $filtered_entries - $offset ) ); ?> of
                <?php echo number_format( $search_term !== '' ? $filtered_entries : $total_entries ); ?>
                <?php echo $search_term !== '' ? 'matching' : 'recent'; ?> blocked requests
            </p>

            <?php if ( $activity_log_page ) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 140px;">Time</th>
                            <th style="width: 100px;">Country</th>
                            <th style="width: 140px;">IP Address</th>
                            <th>URL Attempted</th>
                            <th style="width: 140px;">Referer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $activity_log_page as $entry ) : ?>
                            <tr>
                                <td style="font-size: 12px;"><?php echo esc_html( $entry['time'] ); ?></td>
                                <td><strong><?php echo esc_html( loadup_get_country_name( $entry['country'] ) ); ?></strong></td>
                                <td style="font-family: monospace; font-size: 11px;">
                                    <?php echo esc_html( $entry['ip'] ?? 'unknown' ); ?>
                                </td>
                                <td style="font-family: monospace; font-size: 11px; word-break: break-all;">
                                    <?php echo esc_html( $entry['url'] ); ?>
                                </td>
                                <td style="font-size: 11px; word-break: break-all;">
                                    <?php
                                    $referer = $entry['referer'];
                                    if ( $referer === 'direct' ) {
                                        echo '<em>direct</em>';
                                    } else {
                                        echo esc_html( substr( $referer, 0, 35 ) );
                                        if ( strlen( $referer ) > 35 ) echo '...';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ( $total_pages > 1 ) : ?>
                    <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <?php if ( $current_page > 1 ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( 'paged', $current_page - 1 ) ); ?>" class="button">← Previous</a>
                            <?php endif; ?>
                        </div>
                        <div style="color: #666;">
                            Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                        </div>
                        <div>
                            <?php if ( $current_page < $total_pages ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( 'paged', $current_page + 1 ) ); ?>" class="button">Next →</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif ( $search_term !== '' ) : ?>
                <p style="text-align: center; padding: 40px; color: #999;">No results found for "<?php echo esc_html( $search_term ); ?>"</p>
            <?php else : ?>
                <p style="text-align: center; padding: 40px; color: #999;">No recent activity to display.</p>
            <?php endif; ?>

            <!-- Attack Analysis - Combined Section -->
            <?php if ( $activity_log && ( count( $top_ips ) > 0 || count( $top_urls ) > 0 ) ) : ?>
                <details style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                    <summary style="cursor: pointer; user-select: none; color: #2271b1; font-weight: 600; font-size: 15px;">
                        📊 Attack Pattern Analysis
                    </summary>
                    <div style="margin-top: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">

                        <!-- Top IPs Section -->
                        <?php if ( count( $top_ips ) > 0 ) : ?>
                        <div style="padding: 15px; background: #fafafa; border-radius: 4px; border: 1px solid #e0e0e0;">
                            <h3 style="margin: 0 0 12px 0; font-size: 14px; color: #555;">🔁 Top Repeat IPs</h3>
                            <div style="font-family: monospace; font-size: 12px; color: #333;">
                                <?php foreach ( $top_ips as $ip => $count ) : ?>
                                    <div style="padding: 6px 0; border-bottom: 1px solid #e8e8e8; display: flex; justify-content: space-between; align-items: center;">
                                        <a href="<?php echo esc_url( add_query_arg( array( 'ip_search' => $ip, 'paged' => 1 ) ) ); ?>"
                                           style="text-decoration: none; color: #2271b1; flex: 1;">
                                            <?php echo esc_html( $ip ); ?>
                                        </a>
                                        <span style="background: #f0f0f0; padding: 2px 8px; border-radius: 3px; font-size: 11px; color: #666;">
                                            <?php echo $count; ?>×
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Top URLs Section -->
                        <?php if ( count( $top_urls ) > 0 ) : ?>
                        <div style="padding: 15px; background: #fafafa; border-radius: 4px; border: 1px solid #e0e0e0;">
                            <h3 style="margin: 0 0 12px 0; font-size: 14px; color: #555;">🎯 Top Targeted URLs</h3>
                            <div style="font-family: monospace; font-size: 12px; color: #333;">
                                <?php foreach ( $top_urls as $url => $count ) : ?>
                                    <div style="padding: 6px 0; border-bottom: 1px solid #e8e8e8; display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
                                        <span style="word-break: break-all; flex: 1; color: #555;">
                                            <?php echo esc_html( strlen( $url ) > 50 ? substr( $url, 0, 50 ) . '...' : $url ); ?>
                                        </span>
                                        <span style="background: #f0f0f0; padding: 2px 8px; border-radius: 3px; font-size: 11px; color: #666; white-space: nowrap;">
                                            <?php echo $count; ?>×
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>
                </details>
            <?php endif; ?>
        </div>

        <!-- Clear Stats Button (moved to bottom, less prominent) -->
        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd;">
            <details style="color: #666; font-size: 13px;">
                <summary style="cursor: pointer; user-select: none;">⚙️ Advanced Options</summary>
                <div style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                    <p style="margin-bottom: 10px; color: #d63638;"><strong>⚠️ Danger Zone</strong></p>
                    <p style="margin-bottom: 15px; font-size: 12px;">Clearing statistics will permanently delete all blocking data and cannot be undone.</p>
                    <form method="post">
                        <?php wp_nonce_field( 'loadup_clear_stats' ); ?>
                        <button type="submit" name="clear_stats" class="button button-link-delete" style="font-size: 12px;"
                            onclick="return confirm('⚠️ WARNING: This will permanently delete all statistics and activity logs.\n\nAre you absolutely sure you want to continue?');">
                            Clear All Statistics
                        </button>
                    </form>
                </div>
            </details>
        </div>
    </div>
    <?php
}

//Clear all statistics
function loadup_clear_all_stats() {
    // Delete last 30 days of block data
    for ( $i = 0; $i < 30; $i++ ) {
        $date = date( 'Y-m-d', strtotime( "-{$i} days" ) );
        delete_option( 'loadup_blocks_' . $date );
    }

    // Also clear activity log
    delete_option( 'loadup_activity_log' );
}
