<?php
/*
Plugin Name: WP NET Init
Description: Initialise the WP NET mu-plugin library which connects WordPress to WP NET client management services, loads additional plugins, implements various tweaks and creates the WP NET Dashboard Widgets. If you remove this plugin it will be automatically reinstalled during routine maintenance.
Author: WP NET
Author URI: https://wpnet.nz
Version: 1.4.3
*/

if ( !defined('ABSPATH') ) {
    exit;
}
define('WPNET_PLUGIN_BASE', __FILE__);
$wp_init_data = get_file_data( WPNET_PLUGIN_BASE, array( 'Version' => 'Version' ) );
define( 'WPNET_INIT_PLUGIN_VERSION', $wp_init_data['Version'] );

/*==========================================================
=                      WP NET INIT                         =
==========================================================*/
// Initialises some common functions and loads other plugins
class WPNET_Init {
    public function __construct() {
        add_filter( 'pre_comment_content', array( $this, 'block_long_comment' ), 9999 );
        add_filter( 'admin_bar_menu', array( $this, 'replace_howdy' ), 25 );
        add_filter( 'xmlrpc_enabled', '__return_false' ); // Disable XML-RPC
        add_action( 'init', array( $this, 'super_cache_flush_all' ) );
        // Disable core update emails notifications
        // add_filter( 'auto_core_update_send_email', '__return_false', 9999 );
        // add_filter( 'send_core_update_notification_email', '__return_false', 9999 );
    }
    // As a security precaution, block comments that are too long
    public function block_long_comment( $text ) {
        if ( strlen($text) > 14000 ) {
            wp_die(
                'This comment is longer than permitted and has been blocked.',  // message
                'Comment Blocked',                                              // title
                array( 'response' => 413 )                                      // args
            );
        }
        return $text;
    }
    // Replace the "Howdy" salutation, cuz we're not cowboys
    public function replace_howdy( $wp_admin_bar ) {
        $my_account = $wp_admin_bar->get_node('my-account');
        $newtitle   = str_replace( 'Howdy,', 'Logged in as', $my_account->title );
        $wp_admin_bar->add_node( array(
            'id'    => 'my-account',
            'title' => $newtitle
        ) );
    }
    // The WP Super Cache WP Admin Bar "Delete Cache" button is very confusing!
    // When in the WP Admin it deletes the entire cache, but on the front-end it only deletes the currently viewed page.
    // This function replaces the front-end button, so it behaves the same on both the front-end and WP Admin.
    public function super_cache_flush_all() {
        if ( function_exists( 'is_plugin_active' ) ) {
            if ( is_plugin_active( 'wp-super-cache/wp-cache.php' ) ) {
                function super_cache_flush_all_admin_button() {
                    global $wp_admin_bar;
                    if ( is_admin_bar_showing() && is_super_admin() && ! is_admin()) {
                        $wp_admin_bar->remove_menu('delete-cache');
                        $wp_admin_bar->add_menu( array(
                            'parent' => '',
                            'id'     => 'delete-cache',
                            'title'  => 'Delete Cache',
                            'meta'   => array( 'title' => 'Delete Super Cache cached files' ),
                            'href'   => wp_nonce_url( admin_url('options-general.php?page=wpsupercache&wp_delete_cache=1&tab=contents'), 'wp-cache' )
                            ) );
                    } else {
                        return;
                    }
                }
                add_action( 'wp_before_admin_bar_render', 'super_cache_flush_all_admin_button', 999 );
            }
        }
    }
}
global $WPNET_Init;
$WPNET_Init = new WPNET_Init();

/*========================================================
=                       BRANDING                         =
========================================================*/
// brands some elements of the WP Admin and the IWP Client
class WPNET_WP_Admin_Branding {
    public function __construct() {
        add_filter( 'admin_footer_text', array( $this, 'wpnet_dashboard_footer' ) ); // add the WP NET branding to the WP Admin footer
        add_filter( 'all_plugins', array($this, 'wpnet_plugin_branding' ) ); // brand IWP Client + SpinupWP
        define( 'WPNET_LOGO_SMALL', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADwAAAASCAYAAAAHWr00AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAABQVJREFUeNrkV1toHFUYPnPZTZtUm2pMQlswtJW2WLPRXIx90IqCooIW6ouKSAUVQcEHfRG0LYIX+tYXKz5IjeKDEC/4YF9ispXYSExtLGjrrbYIbdo0t232MjPH75/9x/3n7Gwukjd/+HbnXOac8/3XM9bQ0NANk5OTrm3biuUyUFRxaQIcIAA84IoxngKaAVrEAnzgKjAFaJrge55qbm21tnX2NPqBtsNZFZkO19X8dllov0a1EqLLf411zrTb19d3YHx8fE8qlYqGXwXeE9OvB4aBa5lwAdgF/Cnm7AEOGYTngQlgDOgrFgoD7T29qy6UWo6en8mtd+0Y4wHgCYPwrcDnK8JX07JaH965+SEXRH9Ip9PPCcJ3G4R3AFuMNToNwrtYMaZs5IPvtbR+xXHdg5eL3saLea81FSf8OHAYhLOCcD2wfiUIB5osoVVJqzRZZIitEUkHu1MkPQlr3GG0b1vCvm8BvY5lzZF1nWrsN9pFJ3neshHtB13aLg7xK3AayPDBNgNtwG/c7k04vFQCxe5W0SbLPwXcBLwNXMf9pNxHOSSShDzrPuBrbvvGOMX5i5wbasnzvE4kHwP9IpJ/cXnh44Iw+XY3E65jlzTlFnZhSnA3A9eIsW+AQUYb54RI2ihheYGWJNaK5LIPZjhaSTMxyQNHFvGieyVhGHXUtqxP5QSX/7PAM6J/J/AJsAm4kfuuctJaw9lzB5PqNjbNiue1csCyrPnW+pQOvLSCa1Mm+RK/XWC3jQn3ovsRHLJfI9ME8XUb8M5j+J8VfT8DZyqxqtNauNNMya+bLfmxTBgRHmFLR7HbLeI5qlcngRxwj4jbQSOeaY0TwO3AnZSsKsbTtNBY+7r6zIa0A+1ryp4ziKyDytLvi4z6OhTTX1auNLRFiv7IUO4+YH91DbJUCpobv5JTUwUf8VsZjR5/51iOhGIybVjvBLu+dGvHiOfT7AnHgHc400r3/QyEVgdkPR1mzw2+0kfwf5bbdNyMHwS7g0DPRH1laOVXI5Dtyrrl51Bd8Xr/r4U9JhMln3Xssh1i7ihwQbS38LgsHd8Dk9XbhHu/QEotBDqd9wOKL3Lxemi8pAP1ZqD0u8LKr8EDntUqVpk1XPqSEd+zsfITvlse9mBhT1enAlc8U3l6UrTv50wrLTzB69pcYx9IiF9f4BIr8hBcdwBabzg+MafOTefCUoEzaSKOc30Ay7wEKluZXgfG9qKfvKWB157A3C4rTjJvEg50RUNkdZc2qUH4O0GG5GG+Uiq+Sp7hGD7HiawFeNAgPMqu28m1/aK0Am1cwomKQYCNLBFxYal6A40PhW88Lc4S8flbV5erSpRXta1yp04mTIT+4Doc3aaiJHaKiZD8yIQpPrvE++epznF4/FTzUGVXDmEckqrCy+hoTzhb1G7H/DnRR3f+s8u5ddnGyzIpOUZsSk9ImjO6yKVgMSFFHVhgvImrySmBLwwOyyIcxXGSDIvnkRpzji3xy8WiXFIDqL9qZIFxF0gJrFpgLiG2V+gm+Xxe5XI5xR8PwzWOOSaeyV1LfCOT8u1CPEuFgqK9Slo7JcSwLjuzYyiDQpvq6lf/0UvspDZ/LZUTWjabVfgeVvw97PKFQZIpiOwbyV0cw4FIKIMJ39GVG4nvq6bmFnvN9kym4AV1HMJTfFuSdwbFucFdAsF5zimRbOK7fSR/hYmOM/f2xtVkcq3+T/KPAAMAZkAPvShYUOUAAAAASUVORK5CYII=');
        add_action( 'admin_enqueue_scripts', array( $this, 'iwp_hide_notice_css' ), 1 );
    }
    public function wpnet_dashboard_footer() {
        echo '<span id="footer-thankyou" style="font-style:normal"><a target="_blank" href="https://wpnet.nz" title="Hosted on WP NET - WordPress Hosting &amp; Support"><img style="vertical-align:bottom;" src="'. WPNET_LOGO_SMALL .'"></a> &#8211; WordPress Hosting &amp; Support</span>';
    }
    // CSS to hide the IWP Client notice (it's a bit naughty)
    public function iwp_hide_notice_css() {
        $custom_css = "
                    body.wp-admin #wpbody .updated[style*=\"text-align: center; display:block !important;\"] {visibility:hidden}
                    body.wp-admin #wpbody .updated[style*=\"text-align: center; display:block !important;\"] * {display:none !important}
                    ";
        wp_add_inline_style( 'common', $custom_css );
    }
    public function wpnet_plugin_branding( $plugins_list ) {
        // Brand IWP Client plugin
        if ( isset( $plugins_list['iwp-client/init.php'] ) ) {
            $plugin_info_original = $plugins_list['iwp-client/init.php'];
            $plugin_info_new  = array(
                'Name'        => 'WP NET Client',
                'Title'       => 'WP NET Client',
                'Description' => 'WP NET Client plugin. If you deactivate or remove this plugin it will be automatically reinstalled. Status: <strong>DISCONNECTED</strong>',
                'Author'      => 'WP NET',
                'AuthorName'  => 'WP NET',
                'AuthorURI'   => 'https://wpnet.nz',
                'PluginURI'   => 'https://wpnet.nz',
                'hide'        => false,
            );
            $plugins_list['iwp-client/init.php'] = array_merge($plugin_info_original, $plugin_info_new);
        }
        // Brand SpinupWP plugin
        if ( isset( $plugins_list['spinupwp/spinupwp.php'] ) ) {
            $plugin_info_original = $plugins_list['spinupwp/spinupwp.php'];
            $plugin_info_new  = array(
                'Name'        => 'Cache Control',
                'Title'       => 'Cache Control',
                'Author'      => 'WP NET',
                'AuthorName'  => 'WP NET',
                'AuthorURI'   => 'https://wpnet.nz',
                'PluginURI'   => 'https://wpnet.nz',
                'Description' => "Creates a WP Admin Menu item for 'Cache'. Options include: 'Purge Page Cache' (Nginx), 'Purge Object Cache' (Redis). If a cache type is inactive, it will not display in the menu.",
                'slug'        => null
            );
            $plugins_list['spinupwp/spinupwp.php'] = array_merge($plugin_info_original, $plugin_info_new);
        }
        return $plugins_list;
        }
}
global $WPNET_WP_Admin_Branding;
$WPNET_WP_Admin_Branding = new WPNET_WP_Admin_Branding();
/*=====  End of BRANDING  ======*/

/*====================================================
=            WP NET Dashboard Info Widget            =
====================================================*/
// Display PHP version, disk space and memory usage in a WP Dashboard widget. Based on 'My Simple Space' by Michael Mann.
if ( is_admin() || is_network_admin() ) {
    class WPNET_Site_Info_Widget {
        public function __construct() {
            if ( is_main_site() && is_multisite() ) {
                add_action( 'wp_network_dashboard_setup', array( $this, 'setup_site_info_widget' ) );
            } elseif ( ! defined( 'WPNET_INFO_WIDGET_WPMU_DISABLE' ) || WPNET_INFO_WIDGET_WPMU_DISABLE == false || ! is_multisite()  ) {
                add_action( 'wp_dashboard_setup', array( $this, 'setup_site_info_widget' ) );
            }
        }
        // Dashboard Widget
        public function setup_site_info_widget() {
            wp_add_dashboard_widget('site_info_widget', 'WP NET - WP Shield <span class="title-version">v' . WPNET_INIT_PLUGIN_VERSION . '</span>', array( $this, 'site_info_dashboard_widget' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'site_info_widget_css' ) );
        }
        // Add CSS for the Widget
        public function site_info_widget_css() {
            $custom_css = "
                        #site_info_widget section {display:inline-block; padding: 0 0 .5em 0}
                        #site_info_widget section.full-width {width:100%}
                        #site_info_widget section:first-of-type {}
                        #site_info_widget section:nth-of-type(2) {border-top: 1px solid #eee; padding: .5em 0 .5em 0}
                        #site_info_widget section p {font-size:1.1em;display: inline-block;margin:0 .7em 0 0}
                        #site_info_widget section.full-width p {width:42%; }
                        #site_info_widget section.full-width p.full-width {width:100%; }
                        #site_info_widget section.full-width p.note {font-style:italic }
                        #site_info_widget section.full-width p:nth-child(even) {width: 55%;margin-right:0}
                        #site_info_widget section p .data {float:right; text-align:center}
                        #site_info_widget section a.toggle-link {text-decoration:none}
                        #site_info_widget section a.toggle-link #open-indicator {transition: transform 0.5s ease-in-out}
                        #site_info_widget section a.toggle-link #open-indicator.open {-webkit-transform: rotate(90deg);-moz-transform: rotate(90deg);-ms-transform: rotate(90deg);-o-transform: rotate(90deg);transform: rotate(90deg);}
                        #site_info_widget section a.toggle-link:active,
                        #site_info_widget section a.toggle-link:focus {box-shadow:none;color:#0073aa}
                        #site_info_widget .widget-footer {border-top:1px solid #eee;padding-top:5px}
                        #site_info_widget .widget-footer p {margin:0.3em 0 0}
                        #site_info_widget h2 span.title-version { color: #AAA; font-weight: normal}
                        #site_info_widget small {font-size:.8em;vertical-align:text-top}
                        #site_info_widget .big {font-size:1.6em;vertical-align:text-top; line-height:1; font-weight:300}
                        #site_info_widget .green {color:green}
                        #site_info_widget .red {color:red}
                        #site_info_widget .grey {color:#ddd}
                        #site_info_widget .orange {color:orange}
                        #site_info_widget .blue {color:#2271b1}
                        #site_info_widget a.button.button-primary {color:#fff}
                        /*! Hint.css - v2.7.0 - 2021-10-01
                        * https://kushagra.dev/lab/hint/
                        * Copyright (c) 2021 Kushagra Gour */
[class*=hint--]{position:relative;display:inline-block}[class*=hint--]:after,[class*=hint--]:before{position:absolute;-webkit-transform:translate3d(0,0,0);-moz-transform:translate3d(0,0,0);transform:translate3d(0,0,0);visibility:hidden;opacity:0;z-index:1000000;pointer-events:none;-webkit-transition:.3s ease;-moz-transition:.3s ease;transition:.3s ease;-webkit-transition-delay:0s;-moz-transition-delay:0s;transition-delay:0s}[class*=hint--]:hover:after,[class*=hint--]:hover:before{visibility:visible;opacity:1;-webkit-transition-delay:.1s;-moz-transition-delay:.1s;transition-delay:.1s}[class*=hint--]:before{content:'';position:absolute;background:0 0;border:6px solid transparent;z-index:1000001}[class*=hint--]:after{background:#383838;color:#fff;padding:8px 10px;font-size:12px;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;line-height:12px;white-space:nowrap;text-shadow:0 -1px 0 #000;box-shadow:4px 4px 8px rgba(0,0,0,.3)}[class*=hint--][aria-label]:after{content:attr(aria-label)}[class*=hint--][data-hint]:after{content:attr(data-hint)}[aria-label='']:after,[aria-label='']:before,[data-hint='']:after,[data-hint='']:before{display:none!important}.hint--top-left:before,.hint--top-right:before,.hint--top:before{border-top-color:#383838}.hint--bottom-left:before,.hint--bottom-right:before,.hint--bottom:before{border-bottom-color:#383838}.hint--top:after,.hint--top:before{bottom:100%;left:50%}.hint--top:before{margin-bottom:-11px;left:calc(50% - 6px)}.hint--top:after{-webkit-transform:translateX(-50%);-moz-transform:translateX(-50%);transform:translateX(-50%)}.hint--top:hover:before{-webkit-transform:translateY(-8px);-moz-transform:translateY(-8px);transform:translateY(-8px)}.hint--top:hover:after{-webkit-transform:translateX(-50%) translateY(-8px);-moz-transform:translateX(-50%) translateY(-8px);transform:translateX(-50%) translateY(-8px)}.hint--bottom:after,.hint--bottom:before{top:100%;left:50%}.hint--bottom:before{margin-top:-11px;left:calc(50% - 6px)}.hint--bottom:after{-webkit-transform:translateX(-50%);-moz-transform:translateX(-50%);transform:translateX(-50%)}.hint--bottom:hover:before{-webkit-transform:translateY(8px);-moz-transform:translateY(8px);transform:translateY(8px)}.hint--bottom:hover:after{-webkit-transform:translateX(-50%) translateY(8px);-moz-transform:translateX(-50%) translateY(8px);transform:translateX(-50%) translateY(8px)}.hint--right:before{border-right-color:#383838;margin-left:-11px;margin-bottom:-6px}.hint--right:after{margin-bottom:-14px}.hint--right:after,.hint--right:before{left:100%;bottom:50%}.hint--right:hover:after,.hint--right:hover:before{-webkit-transform:translateX(8px);-moz-transform:translateX(8px);transform:translateX(8px)}.hint--left:before{border-left-color:#383838;margin-right:-11px;margin-bottom:-6px}.hint--left:after{margin-bottom:-14px}.hint--left:after,.hint--left:before{right:100%;bottom:50%}.hint--left:hover:after,.hint--left:hover:before{-webkit-transform:translateX(-8px);-moz-transform:translateX(-8px);transform:translateX(-8px)}.hint--top-left:after,.hint--top-left:before{bottom:100%;left:50%}.hint--top-left:before{margin-bottom:-11px;left:calc(50% - 6px)}.hint--top-left:after{-webkit-transform:translateX(-100%);-moz-transform:translateX(-100%);transform:translateX(-100%);margin-left:12px}.hint--top-left:hover:before{-webkit-transform:translateY(-8px);-moz-transform:translateY(-8px);transform:translateY(-8px)}.hint--top-left:hover:after{-webkit-transform:translateX(-100%) translateY(-8px);-moz-transform:translateX(-100%) translateY(-8px);transform:translateX(-100%) translateY(-8px)}.hint--top-right:after,.hint--top-right:before{bottom:100%;left:50%}.hint--top-right:before{margin-bottom:-11px;left:calc(50% - 6px)}.hint--top-right:after{-webkit-transform:translateX(0);-moz-transform:translateX(0);transform:translateX(0);margin-left:-12px}.hint--top-right:hover:after,.hint--top-right:hover:before{-webkit-transform:translateY(-8px);-moz-transform:translateY(-8px);transform:translateY(-8px)}.hint--bottom-left:after,.hint--bottom-left:before{top:100%;left:50%}.hint--bottom-left:before{margin-top:-11px;left:calc(50% - 6px)}.hint--bottom-left:after{-webkit-transform:translateX(-100%);-moz-transform:translateX(-100%);transform:translateX(-100%);margin-left:12px}.hint--bottom-left:hover:before{-webkit-transform:translateY(8px);-moz-transform:translateY(8px);transform:translateY(8px)}.hint--bottom-left:hover:after{-webkit-transform:translateX(-100%) translateY(8px);-moz-transform:translateX(-100%) translateY(8px);transform:translateX(-100%) translateY(8px)}.hint--bottom-right:after,.hint--bottom-right:before{top:100%;left:50%}.hint--bottom-right:before{margin-top:-11px;left:calc(50% - 6px)}.hint--bottom-right:after{-webkit-transform:translateX(0);-moz-transform:translateX(0);transform:translateX(0);margin-left:-12px}.hint--bottom-right:hover:after,.hint--bottom-right:hover:before{-webkit-transform:translateY(8px);-moz-transform:translateY(8px);transform:translateY(8px)}.hint--large:after,.hint--medium:after,.hint--small:after{white-space:normal;line-height:1.4em;word-wrap:break-word}.hint--small:after{width:80px}.hint--medium:after{width:150px}.hint--large:after{width:300px}.hint--error:after{background-color:#b34e4d;text-shadow:0 -1px 0 #592726}.hint--error.hint--top-left:before,.hint--error.hint--top-right:before,.hint--error.hint--top:before{border-top-color:#b34e4d}.hint--error.hint--bottom-left:before,.hint--error.hint--bottom-right:before,.hint--error.hint--bottom:before{border-bottom-color:#b34e4d}.hint--error.hint--left:before{border-left-color:#b34e4d}.hint--error.hint--right:before{border-right-color:#b34e4d}.hint--warning:after{background-color:#c09854;text-shadow:0 -1px 0 #6c5328}.hint--warning.hint--top-left:before,.hint--warning.hint--top-right:before,.hint--warning.hint--top:before{border-top-color:#c09854}.hint--warning.hint--bottom-left:before,.hint--warning.hint--bottom-right:before,.hint--warning.hint--bottom:before{border-bottom-color:#c09854}.hint--warning.hint--left:before{border-left-color:#c09854}.hint--warning.hint--right:before{border-right-color:#c09854}.hint--info:after{background-color:#3986ac;text-shadow:0 -1px 0 #1a3c4d}.hint--info.hint--top-left:before,.hint--info.hint--top-right:before,.hint--info.hint--top:before{border-top-color:#3986ac}.hint--info.hint--bottom-left:before,.hint--info.hint--bottom-right:before,.hint--info.hint--bottom:before{border-bottom-color:#3986ac}.hint--info.hint--left:before{border-left-color:#3986ac}.hint--info.hint--right:before{border-right-color:#3986ac}.hint--success:after{background-color:#458746;text-shadow:0 -1px 0 #1a321a}.hint--success.hint--top-left:before,.hint--success.hint--top-right:before,.hint--success.hint--top:before{border-top-color:#458746}.hint--success.hint--bottom-left:before,.hint--success.hint--bottom-right:before,.hint--success.hint--bottom:before{border-bottom-color:#458746}.hint--success.hint--left:before{border-left-color:#458746}.hint--success.hint--right:before{border-right-color:#458746}.hint--always:after,.hint--always:before{opacity:1;visibility:visible}.hint--always.hint--top:before{-webkit-transform:translateY(-8px);-moz-transform:translateY(-8px);transform:translateY(-8px)}.hint--always.hint--top:after{-webkit-transform:translateX(-50%) translateY(-8px);-moz-transform:translateX(-50%) translateY(-8px);transform:translateX(-50%) translateY(-8px)}.hint--always.hint--top-left:before{-webkit-transform:translateY(-8px);-moz-transform:translateY(-8px);transform:translateY(-8px)}.hint--always.hint--top-left:after{-webkit-transform:translateX(-100%) translateY(-8px);-moz-transform:translateX(-100%) translateY(-8px);transform:translateX(-100%) translateY(-8px)}.hint--always.hint--top-right:after,.hint--always.hint--top-right:before{-webkit-transform:translateY(-8px);-moz-transform:translateY(-8px);transform:translateY(-8px)}.hint--always.hint--bottom:before{-webkit-transform:translateY(8px);-moz-transform:translateY(8px);transform:translateY(8px)}.hint--always.hint--bottom:after{-webkit-transform:translateX(-50%) translateY(8px);-moz-transform:translateX(-50%) translateY(8px);transform:translateX(-50%) translateY(8px)}.hint--always.hint--bottom-left:before{-webkit-transform:translateY(8px);-moz-transform:translateY(8px);transform:translateY(8px)}.hint--always.hint--bottom-left:after{-webkit-transform:translateX(-100%) translateY(8px);-moz-transform:translateX(-100%) translateY(8px);transform:translateX(-100%) translateY(8px)}.hint--always.hint--bottom-right:after,.hint--always.hint--bottom-right:before{-webkit-transform:translateY(8px);-moz-transform:translateY(8px);transform:translateY(8px)}.hint--always.hint--left:after,.hint--always.hint--left:before{-webkit-transform:translateX(-8px);-moz-transform:translateX(-8px);transform:translateX(-8px)}.hint--always.hint--right:after,.hint--always.hint--right:before{-webkit-transform:translateX(8px);-moz-transform:translateX(8px);transform:translateX(8px)}.hint--rounded:after{border-radius:4px}.hint--no-animate:after,.hint--no-animate:before{-webkit-transition-duration:0s;-moz-transition-duration:0s;transition-duration:0s}.hint--bounce:after,.hint--bounce:before{-webkit-transition:opacity .3s ease,visibility .3s ease,-webkit-transform .3s cubic-bezier(.71,1.7,.77,1.24);-moz-transition:opacity .3s ease,visibility .3s ease,-moz-transform .3s cubic-bezier(.71,1.7,.77,1.24);transition:opacity .3s ease,visibility .3s ease,transform .3s cubic-bezier(.71,1.7,.77,1.24)}.hint--no-shadow:after,.hint--no-shadow:before{text-shadow:initial;box-shadow:initial}.hint--no-arrow:before{display:none}";
            wp_add_inline_style( 'dashboard', $custom_css );
        }
        // Output the contents to Dashboard Widget
        public function site_info_dashboard_widget() {
            global $wpdb, $wp_version;
            $dbname = $wpdb->dbname;
            // Setup Home Path for Later Usage
            if ( get_home_path() === "/" )
                $homepath = ABSPATH;
            else
                $homepath = get_home_path();
            $memory       = $this->getMemoryInfo();
            $memory_limit = $memory[ 'memory_limit' ];
            $memory_usage = $memory[ 'memory_usage' ];
            $subfolder    = strrpos( get_site_url(), '/', 8 ); // Starts after http:// or https:// to find the last slash
            // Determines if site is using a subfolder, such as /wp
            if ( isset( $subfolder ) && $subfolder != "" ) {
                $remove = substr( get_site_url(), strrpos( get_site_url(), '/' ), strlen( get_site_url() ) );
                $home   = str_replace ( $remove, '', $homepath ); // Strips out subfolder to avoid duplicate folder in path
            } else {
                $home = $homepath;
            }
            // Calculate Database Size
            $result = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );
            $rows   = count( $result );
            $dbsize = 0;
            if ( $wpdb->num_rows > 0 ) {
                foreach ( $result as $row ) {
                    $dbsize += $row[ "Data_length" ] + $row[ "Index_length" ];
                }
            }
            // Check for .wp-stats file
            if ( file_exists( $homepath . ".wp-stats") ) {
                $this->disk_stats = json_decode( file_get_contents( $homepath . ".wp-stats" ), true );
            } else {
                $this->disk_stats = (array) "-";
            }
            // WordPress and PHP version notifications
            $wp_latest_version      = 0;
            $wp_update_available    = 0;
            $is_php74               = 0;
            // $phpversion             = substr(PHP_VERSION,0, 5);
            // get the 'update core' site transient data
            if ( $update_core_data = get_site_transient( 'update_core' ) ) {
                $wp_latest_version = $update_core_data->updates[0]->version;
            }
            // test WP version
            if ( version_compare( $wp_latest_version, $wp_version, '>' ) ) {
                $wp_update_available = 1;
            }
            // test for PHP7.4
            if ( version_compare( PHP_VERSION, '7.4', '=' ) ) {
                $is_php74 = 1;
            }
            // write messages
            if ( $wp_update_available ) {
                $wp_version_notice = '<a href="' . admin_url( 'update-core.php') . '" class="pointer orange hint--top hint--rounded hint--bounce" aria-label="A WordPress update is available"><span class="orange dashicons dashicons-update"></span> '. $wp_version . '</a>';
            } else {
                $wp_version_notice = '<span class="green hint--top hint--rounded hint--bounce" aria-label="WordPress is up to date"><span class="dashicons dashicons-yes"></span>' . $wp_version . '</span>';
            }
            // Test PHP version
            $php_version_notice = '<span class="red hint--top-left hint--rounded hint--bounce" aria-label="Error reading PHP version"><span class="red dashicons dashicons-warning"></span>ERROR</span>';
            if ( version_compare(PHP_VERSION, '8.2', '>=') ) {
                $php_version_notice = '<span class="red hint--bottom-left hint--error hint--rounded hint--bounce hint--large" aria-label="PHP '. PHP_VERSION .' support is in BETA. Not recommended - use for testing only!"><span class="red dashicons dashicons-warning"></span>' . PHP_VERSION . '</span>';
            }
            if ( version_compare(PHP_VERSION, '8', '>=') && version_compare(PHP_VERSION, '8.2', '<') ) {
                $php_version_notice = '<span class="orange hint--bottom-left hint--rounded hint--bounce hint--large" aria-label="WordPress core is compatible with PHP ' . PHP_VERSION . ', but some plugins and themes may not be. Test your site carefully. Contact WP NET Support for help."><span class="orange dashicons dashicons-warning"></span>' . PHP_VERSION . '</span>';
            }     
            if ( version_compare(PHP_VERSION, '7.4', '>=') && version_compare(PHP_VERSION, '8', '<') ) {
                $php_version_notice = '<span class="orange hint--bottom-left hint--rounded hint--bounce hint--large" aria-label="PHP ' . PHP_VERSION . ' is compatible with WordPress, but has reached end-of-life. Contact WP NET Support for help."><span class="orange dashicons dashicons-warning"></span>' . PHP_VERSION . '</span>';
            }                        
            if ( version_compare(PHP_VERSION, '7.4', '<') ) {
                $php_version_notice = '<span class="red hint--bottom-left hint--rounded hint--bounce hint--large" aria-label="You are running an outdated and unsupported version of PHP! Contact WP NET Support for help."><span class="red dashicons dashicons-warning"></span> v' . PHP_VERSION . '</span>';
            }                  
            if ( strlen($memory_limit) >= 6 ) {
                $memory_notice = '<span class="hint--top-left hint--rounded hint--bounce" aria-label="Memory used to load this page"><span class="green">' . size_format( $memory_usage ) . '</span></span>';
            } else {
                $memory_notice = '<span class="hint--top-left hint--rounded hint--bounce" aria-label="Memory used / Memory available"><span class="green">' . size_format( $memory_usage ) . '</span>' . '<small>/' . $memory_limit . 'B</small></span>';
            }
            $max_upload_notice = '<span class="hint--top hint--rounded hint--bounce" aria-label="Maximum file upload size">' . ini_get('upload_max_filesize') . 'B<span>';
            // WordPress version, PHP version, max upload size & memory usage
            $topitems = array(
                'WordPress'  => $wp_version_notice,
                'PHP'        => $php_version_notice,
                'Max Upload' => $max_upload_notice,
                'Memory'     => $memory_notice
            );
            echo '<section class="full-width">';
            foreach ($topitems as $name => $value) {
                echo '<p><strong class="label">' . $name . ':</strong> <span class="data">' . $value . '</span></p>';
            }
            echo '<p><strong class="label">Database:</strong> <span class="data"><span class="hint--top hint--rounded hint--bounce" aria-label="Database size">' . size_format( $dbsize ) . '</span></span></p>';
            echo '<p><strong class="label">Disk Usage:</strong> <span class="big data">';

            if ( $this->getDiskUseStats( 'wp-content' ) !== '-' ) {
                echo '<a href="#" class="toggle-link hint--top-left hint--rounded hint--bounce" aria-label="Updated every 6 hours. Click for details." onclick="event.preventDefault();jQuery(\'#wpnet-diskstats\').slideToggle(\'slow\');jQuery(\'#open-indicator\').toggleClass(\'open\')">' . $this->getDiskUseStats() . '<span id="open-indicator" class="dashicons dashicons-arrow-right"></span></a>';
            } else {
                echo $this->getDiskUseStats();
            }
            echo '</span></p>';
            echo '</section>';

            if ( $this->getDiskUseStats( 'wp-content' ) !== '-' ) {
                $diskstats = array(
                    "wp-content"            => $this->getDiskUseStats( 'wp-content' ),
                    "Backups"               => $this->getDiskUseStats( 'backups' ),
                    "&#10551;&nbsp;cache"   => $this->getDiskUseStats( 'cache' ),
                    "&#10551;&nbsp;plugins" => $this->getDiskUseStats( 'plugins' ),
                    "&#10551;&nbsp;themes"  => $this->getDiskUseStats( 'themes' ),
                    "&#10551;&nbsp;uploads" => $this->getDiskUseStats( 'uploads' ),
                );
                echo '<section id="wpnet-diskstats" class="full-width" style="display:none">';
                foreach ($diskstats as $name => $value) {
                    echo '<p><strong class="label">' . $name . ':</strong> <span class="data">' . $value . '</span></p>';
                }
                echo '<p class="full-width note">Disk usage information updated every 6 hours</p>';
                echo '</section>';
            }
            // misc notifications
            if ( $wp_update_available /*|| 1 == 1 */)  {
                   echo '<section><p><span class="orange dashicons dashicons-warning"></span> <strong><a class="hint--top hint--rounded hint--bounce" href="'. admin_url( 'update-core.php') . '" aria-label="Click to update WordPress">WordPress '. $wp_latest_version . ' is available</a>!</strong></p></section>';
               }
            // Widget footer
            echo wpautop ( stripslashes( '<div class="widget-footer"><p><a href="https://my.wpnet.nz/submitticket.php" class="button button-primary button-large" target="_blank">Open a Support Ticket</a> <a href="https://my.wpnet.nz/knowledgebase" class="button button-primary button-large" target="_blank">KnowledgeBase</a> <a href="https://my.wpnet.nz" class="button button-large" target="_blank">My WP NET</a></p><p style="margin-top:10px"><span class="blue dashicons dashicons-lightbulb"></span><a href="https://wpnet.nz/wordpress-php/" target="blank">Read about WordPress and PHP compatibility</a></p></div>' ) );
        }
        // get memory info
        private function getMemoryInfo() {
            $memory['memory_limit'] = ini_get( 'memory_limit' );
            $memory['memory_usage'] = function_exists( 'memory_get_usage' ) ? round( memory_get_usage(), 2 ) : 0;
            return $memory;
        }
        // get disk use for $dir
        private function getDiskUseStats( $dir = 'webroot' ) {
            return $this->disk_stats[$dir] == "" ? "-" : $this->disk_stats[$dir];
        }
    }
    if ( ! defined( 'WPNET_INFO_WIDGET_DISABLE' ) || WPNET_INFO_WIDGET_DISABLE == false ) {
        global $WPNET_Site_Info_Widget;
        $WPNET_Site_Info_Widget = new WPNET_Site_Info_Widget();
    }
}
/*=====  End of WP NET Dashboard Info Widget  ======*/
