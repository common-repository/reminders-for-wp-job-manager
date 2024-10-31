<?php
/*
	Plugin Name: Reminders for WP Job Manager 
	Description: Automatically send email reminders to employers to continue drafted, unpublished, or pending job posting submissions.
	Version: 1.0.0
	Author: Effin Studios
	Author URI: http://effinstudios.com
	License: GPLv2 or later

	Copyright 2021 effin studios (email : support@effinstudios.com)
*/
	namespace REMIND_WPJM;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	define( 'REMIND_WPJM_PATH', plugin_dir_path( __FILE__ ) );
	define( 'REMIND_WPJM_URL', plugin_dir_url(__FILE__) );
	define( 'REMIND_WPJM_CORE_REQUIRED', '1.34.5' );

	class WPJM_Reminders {
		public static function wpjmreminders_init(){
			add_action( 'admin_notices', [ __CLASS__, 'wpjmreminders_admin_notice_handler' ] );
			add_action( 'wpjm_reminders_cron_action', [ __CLASS__, 'wpjmreminders_cron_action' ], 10, 0 );
			add_action( 'wpjm_reminders_cron_mail', [ __CLASS__, 'wpjmreminders_cron_mail' ], 10, 0 );
		}

		public static function wpjmreminders_admin_init(){
			add_action( 'admin_menu', [ __CLASS__, 'wpjmreminders_plugin_menu' ] );
			add_action( 'admin_init', [ __CLASS__, 'wpjmreminders_setup_sections' ] );
			add_action( 'admin_init', [ __CLASS__, 'wpjmreminders_setup_fields' ] );
			add_action( 'admin_enqueue_scripts', [ __CLASS__, 'wpjmreminders_admin_scripts' ] );
			add_action( 'add_meta_boxes', [ __CLASS__, 'wpjmreminders_job_form_fields' ] );
			add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ __CLASS__, 'wpjmreminders_add_settings_link' ] );
		}

		private static function wpjmreminders_version_check() {
			if ( ! class_exists( 'WP_Job_Manager' ) || ! defined( 'JOB_MANAGER_VERSION' ) ) {
				$screen = get_current_screen();
				if ( null !== $screen && 'plugins' === $screen->id ) {
					self::wpjmreminders_admin_notice( 
						__( 'Reminders for WP Job Manager requires WP Job Manager to be installed and activated.', 'wpjm-reminders' ),
						'error'
					);
				}
				/*
			 	* Deactivate plugin if WP_Job_Manager is not present
			 	*/
				deactivate_plugins( plugin_basename( __FILE__ ) );
				return false;
			} elseif ( version_compare( JOB_MANAGER_VERSION, REMIND_WPJM_CORE_REQUIRED, '<' ) ) {
				self::wpjmreminders_admin_notice( 
					sprintf( __( 'Reminders for WP Job Manager requires WP Job Manager %s or above.', 'wpjm-reminders' ), REMIND_WPJM_CORE_REQUIRED ),
					'error'
				);
				return false;
			}
			return true;
		}

		private static function wpjmreminders_cron_check(){
			if( !wp_next_scheduled( 'wpjm_reminders_cron_action' ) ){
				self::wpjmreminders_admin_notice( 
					__( 'Reminders for WP Job Manager\'s automatic checking for unpublished jobs has not been set, please go to the plugin settings page and save your settings.', 'wpjm-reminders' ),
					'error'
				);
			}
			if( !wp_next_scheduled( 'wpjm_reminders_cron_mail' ) ){
				self::wpjmreminders_admin_notice( 
					__( 'Reminders for WP Job Manager\'s automatic email schedule has not been set, please go to the plugin settings page and save your settings.', 'wpjm-reminders' ),
					'error'
				);
			}
		}

		private static function wpjmreminders_default_subject(){
			return "Did you forget to publish your job listing?";
		}

		private static function wpjmreminders_default_message(){
			return "Hi {display_name},\r\n\r\nAre you still looking for a {job_title}?\r\n\r\nYour job listing submission was saved on {job_date} but was not published.\r\n\r\nYou can continue your job listing submission and publish your job opening through the following link: {job_url}";
		}

		public static function wpjmreminders_add_settings_link( $links ){
			$settings_link = '<a href="edit.php?post_type=job_listing&page=wpjm-reminders">' . __( 'Settings' ) . '</a>';
			array_push( $links, $settings_link );
			return $links;
		}

		public static function wpjmreminders_job_form_fields(){
			if( isset( $_GET['action'] ) && ( $_GET['action'] === 'edit' ) ){
				add_meta_box(
					'wpjmreminders_job_form_fields',
					'Reminders',
					[ __CLASS__, 'wpjmreminders_job_form_fields_admin_box' ],
					'job_listing',
					'side',
					'high'
				);
			}
		}

		public static function wpjmreminders_job_form_fields_admin_box(){
			global $post;

			if( ( $post->post_type === 'job_listing' ) && isset( $_REQUEST['wpjm_reminders_nonce'] ) && isset( $_REQUEST['post'] ) && !empty( $_REQUEST['post'] ) && wp_verify_nonce( $_REQUEST['wpjm_reminders_nonce'], 'wpjmreminders_send_reminder' . absint( $_REQUEST['post'] ) ) ){
				if( self::wpjmreminders_send_reminder( $post->ID ) ){
					self::wpjmreminders_admin_notice( 
						__( 'Reminder email has been sent to ', 'wpjm-reminders' ) . esc_attr( get_the_author_meta( 'user_email', get_post_field( 'post_author', $post_id ) ) ) . '.',
						'success'
					);
				} else {
					self::wpjmreminders_admin_notice( 
						__( 'Reminder email failed to send an email to ', 'wpjm-reminders' ) . esc_attr( get_the_author_meta( 'user_email', get_post_field( 'post_author', $post_id ) ) ) . '.',
						'error'
					);
				}
				wp_redirect( esc_url_raw( remove_query_arg( [ 'wpjm_reminders_nonce' ] ) ) );
			}

			print( '<div class="wpjm-reminders-admin-box">' );

				print( '<div class="wpjm-reminders-admin-box-content">' );

					if( empty( $reminder_sent	= esc_attr( get_post_meta( $post->ID, '_wpjm_reminders_sent', true ) ) ) ){
						$reminder_sent = __( 'No Reminders Sent', 'wpjm-reminders' );
					}
					printf( '<p class="wpjm-reminders-admin-box-date">' . __( 'Date Sent: <b>%s</b><br>', 'wpjm-reminders' ) . '</p>', $reminder_sent );

					if( empty( $reminder_count	= esc_attr( get_post_meta( $post->ID, '_wpjm_reminders_count', true ) ) ) ){
						$reminder_count = __( '0', 'wpjm-reminders' );
					}
					printf( '<p class="wpjm-reminders-admin-box-count">' . __( 'Reminded: <b>%s</b>', 'wpjm-reminders' ) . '</p>', $reminder_count );

				print( '</div>' );

				if( isset( $_REQUEST['post'] ) && !empty( $_REQUEST['post'] ) ){
					print( '<div class="wpjm-reminders-admin-box-actions">' );
						print( '<script>jQuery(document).ready(function($){$(".wpjm-reminders-admin-box-send .button").click(function(){$(this).addClass("disabled");$(".wpjm-reminders-admin-box-send .spinner").addClass("is-active");});});</script>' );
						$url_nonce	= wp_create_nonce( 'wpjmreminders_send_reminder' . $post->ID );
						$url_vars	= isset( $_GET ) && !empty( $_GET ) ? $_GET : [];
						$url_bare	= add_query_arg( $url_vars );
						$this_url 	= esc_url_raw( add_query_arg( [ 'wpjm_reminders_nonce' => $url_nonce ], $url_bare ) );
						printf( '<div class="wpjm-reminders-admin-box-send"><span class="spinner"></span><a class="button button-primary button-large" href="%s">' . __( 'Send Reminder Now', 'wpjm-reminders' ) . '</a></div>', $this_url );
					print( '</div>' );
				}

			print( '</div>' );
		}


		public static function wpjmreminders_plugin_menu(){
			/*
			 * Check core version and display comptibility warning
			 */
			self::wpjmreminders_version_check();

			/*
			 * Check if cron is scheduled
			 */
			self::wpjmreminders_cron_check();

			add_submenu_page(
				'edit.php?post_type=job_listing',
				__( 'Reminders for WPJM', 'wpjm-reminders' ),
				__( 'Reminders', 'wpjm-reminders' ),
				'manage_options',
				'wpjm-reminders',
				[ __CLASS__, 'wpjmreminders_settings_page' ]
			);
		}

		public static function wpjmreminders_settings_page(){
			if( isset( $_POST['updated'] ) && $_POST['updated'] === 'true'  ){
				self::wpjmreminders_handle_form();
			}
		?>
			<div class="wrap wpjm-reminders wpjm-reminders-settings">
				<h1><?php _e( 'Reminders for WP Job Manager', 'wpjm-reminders' ); ?></h1>
				<div class="wpjm-reminders-wrapper">
					<div class="wpjm-reminders-content">
						<form method="post">
							<input type="hidden" name="updated" value="true" />
							<?php wp_nonce_field( 'wpjmreminders_settings_update', 'wpjmreminders_settings_update_form' ); ?>
							<?php settings_fields( 'wpjmreminders_settings_fields' ); ?>
							<?php self::wpjmreminders_do_settings_sections( 'wpjmreminders_settings_fields' ); ?>
							<?php 
									echo '<p class="submit">';

									submit_button( null, 'primary', 'submit', false, null );

										if( isset( $_GET['wpjm_reminders_nonce'] ) && wp_verify_nonce( $_GET['wpjm_reminders_nonce'], 'wpjmreminders_send_reminder' . 'test-mail' ) ){
											if( self::wpjmreminders_send_reminder( 'test-mail' ) ){
												self::wpjmreminders_admin_notice( 
													__( 'Reminder email has been sent to ', 'wpjm-reminders' ) . esc_attr( get_option( 'admin_email' ) ) . '.',
													'success'
												);
											} else {
												self::wpjmreminders_admin_notice( 
													__( 'Reminder email failed to send an email to ', 'wpjm-reminders' ) . esc_attr( get_option( 'admin_email' ) ) . '.',
													'error'
												);
											}
											wp_redirect( esc_url_raw( remove_query_arg( [ 'wpjm_reminders_nonce' ] ) ) );
										}

										echo '<script>jQuery(document).ready(function($){$(".wpjm-reminders-settings .button.test").click(function(){$(this).addClass("disabled");$(".wpjm-reminders-settings .spinner.test").addClass("is-active");});});</script>';
										$url_nonce	= wp_create_nonce( 'wpjmreminders_send_reminder' . 'test-mail' );
										$url_vars	= isset( $_GET ) && !empty( $_GET ) ? $_GET : [];
										$url_bare	= add_query_arg( $url_vars );
										$this_url 	= esc_url_raw( add_query_arg( [ 'wpjm_reminders_nonce' => $url_nonce ], $url_bare ) );
										printf( '&nbsp;<a class="button test" href="%s">' . __( 'Send Test Reminder', 'wpjm-reminders' ) . '</a><span class="spinner test" style="float:none;margin:-2px 0 0 4px;"></span>', $this_url );

									echo '</p>';
							?>
						</form>
					</div>
					<div class="wpjm-reminders-sidebar">
						<div class="wpjm-reminders-sidebar-item effin-studios">
							<img src="<?php echo esc_url( REMIND_WPJM_URL . '/images/effinstudios.png' ); ?>" width="200" height="200" alt="Effin Studios">
						</div>
						<div class="wpjm-reminders-sidebar-item support-us">
							<h3>Love what we are doing?</h3>
							<p>Help us keep developing more great stuff by buying us a drink or three, we truly appreciate every bit of your support!</p>
							<a class="button" href="https://ko-fi.com/effinstudios" target="_blank" rel="nofollow">Buy us coffee</a>
						</div>
					</div>
				</div>
			</div>
		<?php
		}

		public static function wpjmreminders_handle_form() {
			if( ! isset( $_POST['wpjmreminders_settings_update_form'] ) || ! wp_verify_nonce( $_POST['wpjmreminders_settings_update_form'], 'wpjmreminders_settings_update' ) ){
				self::wpjmreminders_admin_notice( 
					__( 'Somthing terrible happend... Your settings was not saved, please try again.', 'wpjm-reminders' ),
					'error'
				);
				wp_redirect( esc_url_raw( add_query_arg( [] ) ) );
			} else {
				if( isset( $_POST['updated'] ) && !empty( $_POST['updated'] ) ){
					$fields	= self::wpjmreminders_fields_array();
					foreach( $fields as $fieldarr ){
						$opt_val	= '';
						switch( $fieldarr['type'] ){
							case 'select':
								if( isset( $_POST[ $fieldarr['uid'] ] ) && !empty( $_POST[ $fieldarr['uid'] ] ) && is_array( $fieldarr['options'] ) ){
									$options = array_keys( $fieldarr['options'] );
									if( is_array( $options ) && !empty( $options ) && in_array( $_POST[ $fieldarr['uid'] ], $options ) ){
										$opt_val	= sanitize_text_field( $_POST[ $fieldarr['uid'] ] );
									}
								}
								break;
							case 'multicheckbox':
								$opt_val = [];
								if( isset( $_POST[ $fieldarr['uid'] ] ) && !empty( $_POST[ $fieldarr['uid'] ] ) && is_array( $fieldarr['options'] ) ){
									$options = array_keys( $fieldarr['options'] );
									if( is_array( $options ) && !empty( $options ) && is_array( $_POST[ $fieldarr['uid'] ] ) ){
										foreach( $_POST[ $fieldarr['uid'] ] as $arrval ){
											if( in_array( $arrval, $options ) ){
												$opt_val[] = $arrval;
											}
										}
									}
								}
								break;
							case 'number':
								$opt_val	= isset( $_POST[ $fieldarr['uid'] ] ) ? absint( sanitize_text_field( $_POST[ $fieldarr['uid'] ] ) ) : $fieldarr['default'];
								break;
							case 'text':
							case 'checkbox':
							case 'email':
								$opt_val	= isset( $_POST[ $fieldarr['uid'] ] ) ? sanitize_text_field( $_POST[ $fieldarr['uid'] ] ) : $fieldarr['default'];
								break;
							case 'textarea':
								$opt_val	= isset( $_POST[ $fieldarr['uid'] ] ) ? wp_kses(
											$_POST[ $fieldarr['uid'] ],
											[
												'a'		=> [ 'style' => [], 'href' => [] ],
												'b'		=> [],
												'strong'	=> [],
												'i'		=> [],
												'em'		=> [],
												'p'		=> [ 'style' => [] ],
												'ul'		=> [ 'style' => [] ],
												'li'		=> [ 'style' => [] ],
												'ol'		=> [ 'style' => [] ],
												'span'	=> [ 'style' => [] ],
												'div'		=> [ 'style' => [] ],
												'table'	=> [ 'style' => [] ],
												'tbody'	=> [ 'style' => [] ],
												'thead'	=> [ 'style' => [] ],
												'tfoot'	=> [ 'style' => [] ],
												'tr'		=> [ 'style' => [] ],
												'th'		=> [ 'style' => [] ],
												'td'		=> [ 'style' => [] ],
												'br'		=> [],
											]
										) : $fieldarr['default'];
								break;
						}
						if( isset( $_POST[ $fieldarr['uid'] ] ) ){
							update_option( $fieldarr['uid'], $opt_val );
						}
					}

					if( wp_next_scheduled( 'wpjm_reminders_cron_action' ) ) {
						wp_clear_scheduled_hook( 'wpjm_reminders_cron_action' );
					}
					if( !wp_next_scheduled( 'wpjm_reminders_cron_action' ) ){
						wp_schedule_event( time(), get_option( 'wpjmreminders_field_cron_interval', 'daily' ), 'wpjm_reminders_cron_action' );
					}

					if( wp_next_scheduled( 'wpjm_reminders_cron_mail' ) ) {
						wp_clear_scheduled_hook( 'wpjm_reminders_cron_mail' );
					}
					if( !wp_next_scheduled( 'wpjm_reminders_cron_mail' ) ){
						wp_schedule_event( time(), get_option( 'wpjmreminders_field_cron_mail', 'hourly' ), 'wpjm_reminders_cron_mail' );
					}

					self::wpjmreminders_admin_notice( 
						__( 'Your settings has been saved sucessfuly.', 'wpjm-reminders' ),
						'success'
					);
				} else {
					self::wpjmreminders_admin_notice( 
						__( 'Somthing terrible happend... Your settings was not saved, please try again.', 'wpjm-reminders' ),
						'error'
					);
				}
				wp_redirect( esc_url_raw( add_query_arg( [] ) ) );
			}
		}

		public static function wpjmreminders_setup_sections(){
			add_settings_section( 'email-template', __( 'Email Template', 'wpjm-reminders' ), [ __CLASS__, 'wpjmreminders_section_callback' ], 'wpjmreminders_settings_fields' );
			add_settings_section( 'reminder-settings', __( 'Settings', 'wpjm-reminders' ), [ __CLASS__, 'wpjmreminders_section_callback' ], 'wpjmreminders_settings_fields' );
		}

		public static function wpjmreminders_section_callback( $arguments ){
			switch( $arguments['id'] ){
				case 'email-template':
					break;
				case 'reminder-settings':
					break;
			}
		}

		public static function wpjmreminders_section_bottom_callback( $arguments ){
			switch( $arguments ){
				case 'email-template':
					_e( '<tr class="large-text"><th>Dynamic Data Tags</th><td>', 'wpjm-reminders' );
					_e( '<p>You can use the following tags to insert dynamic data on both <b>Subject</b> and <b>Content</b>:</p>', 'wpjm-reminders' );
					_e( '<p><code>{display_name}</code> for the user\'s display name,<br><code>{email_address}</code> for the user\'s email address,<br><code>{job_title}</code> for the unpublished job\'s title,<br><code>{job_date}</code> for the unpublished job\'s draft date,<br>and <code>{job_url}</code> for the unpublished job\'s url.</p>', 'wpjm-reminders' );
					_e( '<br><p>The following HTML tags are allowed on the <b>Content</b> when HTML formating is enabled:</p>', 'wpjm-reminders' );
					_e( '<p><code>a</code>(style, href), <code>b</code>, <code>strong</code>, <code>i</code>, <code>em</code>, <code>br</code>,<br><code>p</code>(style), <code>ul</code>(style), <code>li</code>(style), <code>ol</code>(style), <code>span</code>(style),<br><code>div</code>(style), <code>table</code>(style), <code>tbody</code>(style), <code>thead</code>(style), <code>tfoot</code>(style),<br><code>tr</code>(style), <code>th</code>(style), and <code>th</code>(style).</p>', 'wpjm-reminders' );
					_e( '</td></tr>', 'wpjm-reminders' );
					break;
				case 'reminder-settings':
					break;
			}
		}

		private static function wpjmreminders_fields_array(){
			return [
						[
							'uid'			=> 'wpjmreminders_field_cron_list',
							'label'		=> __( 'Check Unpublished Job Listings', 'wpjm-reminders' ),
							'section'		=> 'reminder-settings',
							'type' 		=> 'select',
							'class'		=> 'regular-text',
							'options'		=> wp_get_schedules(),
							'placeholder'	=> '',
							'description'	=> '',
							'helper'		=> '',
							'supplemental'	=> __( 'Select how often <b>Reminders for WPJM</b> should automatically check for unpublished jobs.', 'wpjm-reminders' ),
							'default'		=> 'daily'
						],
						[
							'uid'			=> 'wpjmreminders_field_cron_interval',
							'label'		=> __( 'Automatic Email Intervals', 'wpjm-reminders' ),
							'section'		=> 'reminder-settings',
							'type' 		=> 'select',
							'class'		=> 'regular-text',
							'options'		=> wp_get_schedules(),
							'placeholder'	=> '',
							'description'	=> '',
							'helper'		=> '',
							'supplemental'	=> __( 'Select how often <b>Reminders for WPJM</b> should automatically send reminder emails for unpublished jobs.', 'wpjm-reminders' ),
							'default'		=> 'hourly'
						],
						[
							'uid'			=> 'wpjmreminders_field_days_past',
							'label'		=> __( 'Remind After', 'wpjm-reminders' ),
							'section'		=> 'reminder-settings',
							'type' 		=> 'number',
							'class'		=> 'regular-text',
							'options'		=> false,
							'placeholder'	=> '',
							'description'	=> '',
							'helper'		=> '',
							'supplemental'	=> __( 'Enter the number of days before a reminder is sent for an unpublished jobs.', 'wpjm-reminders' ),
							'default'		=> 3
						],
						[
							'uid'			=> 'wpjmreminders_field_days_resend',
							'label'		=> __( 'Resend Reminder', 'wpjm-reminders' ),
							'section'		=> 'reminder-settings',
							'type' 		=> 'number',
							'class'		=> 'regular-text',
							'options'		=> false,
							'placeholder'	=> '',
							'description'	=> '',
							'helper'		=> '',
							'supplemental'	=> __( 'Enter the number of days before a reminder is resent for an unpublished jobs.', 'wpjm-reminders' ),
							'default'		=> 5
						],
						[
							'uid'			=> 'wpjmreminders_field_max_resend',
							'label'		=> __( 'Maximum Number Reminders', 'wpjm-reminders' ),
							'section'		=> 'reminder-settings',
							'type' 		=> 'number',
							'class'		=> 'regular-text',
							'options'		=> false,
							'placeholder'	=> '',
							'description'	=> '',
							'helper'		=> '',
							'supplemental'	=> __( 'Enter the maximum number of times a reminder is sent for an unpublished jobs.', 'wpjm-reminders' ),
							'default'		=> 2
						],
						[
							'uid'			=> 'wpjmreminders_field_email_cc',
							'label'		=> __( 'Carbon Copy (CC)', 'wpjm-reminders' ),
							'section'		=> 'reminder-settings',
							'type' 		=> 'email',
							'class'		=> 'regular-text',
							'options'		=> false,
							'placeholder'	=> '',
							'description'	=> '',
							'helper'		=> '',
							'supplemental'	=> __( 'Enter an email address to add it as a CC on email reminders.', 'wpjm-reminders' ),
							'default'		=> ''
						],
						[
							'uid'			=> 'wpjmreminders_field_email_bcc',
							'label'		=> __( 'Blind Carbon Copy (BCC)', 'wpjm-reminders' ),
							'section'		=> 'reminder-settings',
							'type' 		=> 'email',
							'class'		=> 'regular-text',
							'options'		=> false,
							'placeholder'	=> '',
							'description'	=> '',
							'helper'		=> '',
							'supplemental'	=> __( 'Enter an email address to add it as a BCC on email reminders.', 'wpjm-reminders' ),
							'default'		=> ''
						],
						[
							'uid'			=> 'wpjmreminders_field_email_html',
							'label'		=> __( 'Plain Text Emails', 'wpjm-reminders' ),
							'section'		=> 'reminder-settings',
							'type' 		=> 'checkbox',
							'class'		=> '',
							'options'		=> false,
							'placeholder'	=> '',
							'description'	=> '',
							'helper'		=> __( 'Plain Text Emails', 'wpjm-reminders' ),
							'supplemental'	=> __( 'Check this box if wish to send plain text emails instead of html.', 'wpjm-reminders' ),
							'default'		=> 'true'
						],
						[
							'uid'			=> 'wpjmreminders_field_reminder_statuses',
							'label'		=> __( 'Job Listing Status', 'wpjm-reminders' ),
							'section'		=> 'reminder-settings',
							'type' 		=> 'multicheckbox',
							'class'		=> 'regular-text',
							'options'		=> defined( 'JOB_MANAGER_WCPL_VERSION') ? [ 'draft' => [ 'display' => 'Draft' ], 'preview' => [ 'display' => 'Preview' ], 'pending' => [ 'display' => 'Pending Approval' ], 'pending_payment' => [ 'display' => 'Pending Payment' ] ] : [ 'draft' => [ 'display' => 'Draft' ], 'preview' => [ 'display' => 'Preview' ], 'pending' => [ 'display' => 'Pending Approval' ] ],
							'placeholder'	=> '',
							'description'	=> __( 'Select the status of an unpublished job where a reminder should be sent.', 'wpjm-reminders' ),
							'helper'		=> '',
							'supplemental'	=> '',
							'default'		=> []
						],
						[
							'uid'			=> 'wpjmreminders_field_employer_email_subject',
							'label'		=> __( 'Reminder Email Subject', 'wpjm-reminders' ),
							'section'		=> 'email-template',
							'type' 		=> 'text',
							'class'		=> 'large-text',
							'options'		=> false,
							'placeholder'	=> '',
							'description'	=> '',
							'helper'		=> '',
							'supplemental'	=> __( 'Enter the email subject for your reminders.', 'wpjm-reminders' ),
							'default'		=> self::wpjmreminders_default_subject()
						],
						[
							'uid'			=> 'wpjmreminders_field_employer_email_content',
							'label'		=> __( 'Reminder Email Content', 'wpjm-reminders' ),
							'section'		=> 'email-template',
							'type' 		=> 'textarea',
							'class'		=> 'large-text',
							'options'		=> false,
							'placeholder'	=> '',
							'description'	=> '',
							'helper'		=> '',
							'supplemental'	=> __( 'Enter the email content for your reminders.', 'wpjm-reminders' ),
							'default'		=> self::wpjmreminders_default_message()
						]
				];
		}

		public static function wpjmreminders_setup_fields(){
			$fields	= self::wpjmreminders_fields_array();
			foreach( $fields as $field ){
				add_settings_field( $field['uid'], $field['label'], [ __CLASS__, 'wpjmreminders_field_callback' ], 'wpjmreminders_settings_fields', $field['section'], $field );
				if( get_option( $field['uid'] ) === false ){
					register_setting( 'wpjmreminders_settings_fields', $field['uid'] );
					if( $field['default'] !== '' ){
						update_option( $field['uid'], $field['default'] );
					}
				}
			}
		}

		public static function wpjmreminders_field_callback( $arguments ){
			$value	= get_option( $arguments['uid'] );
			if( empty( $value ) && ( $value != 0 ) ) {
				$value	= $arguments['default'];
			}

			echo '<fieldset>';

			if( $description = $arguments['description'] ){
				printf( '<p class="description">%s</p>', $description );
			}

			switch( $arguments['type'] ){
				case 'text':
				case 'email':
				case 'number':
					printf( '<input name="%1$s" id="%1$s" type="%2$s" class="%3$s" placeholder="%4$s" value="%5$s" />', $arguments['uid'], $arguments['type'], $arguments['class'], $arguments['placeholder'], esc_attr( $value ) );
					break;
				case 'select':
					printf( '<select name="%1$s" id="%1$s" class="%2$s">', $arguments['uid'], $arguments['class'] );
					if( is_array( $arguments['options'] ) ){
						foreach( $arguments['options'] as $option => $array ):
							$selected	= $value == $option ? 'selected' : '';
							printf( '<option value="%1$s" %2$s>%3$s</option>', $option, $selected, $array['display'] );
						endforeach;
					}
					print( '</select>' );
					break;
				case 'textarea':
					if( is_array( $value ) ){
						$value = @preg_filter( ['/\A\/\\\A\(/', '/\)\\\Z\/\Z/', '/\.\+/', '/\\\\\\\\/'], ['', '', '%%%', '\\'], $value );
						$value = @stripslashes( @implode( "\n", $value ) );
					}
					printf( '<textarea name="%1$s" id="%1$s" class="%2$s" rows="4" cols="50">%3$s</textarea>', $arguments['uid'], $arguments['class'], esc_textarea( $value ) );
					break;
				case 'checkbox':
					$checked = $value === "true" ? 'checked' : '';
					printf( '<input name="%1$s" type="%2$s" value="" />', $arguments['uid'], 'hidden' );
					printf( '<input name="%1$s" id="%1$s" type="%2$s" class="%3$s" value="true" %4$s />', $arguments['uid'], $arguments['type'], $arguments['class'], $checked );
										break;
				case 'multicheckbox':
					printf( '<input name="%1$s[]" type="%2$s" value="" />', $arguments['uid'], 'hidden' );
					if( is_array( $arguments['options'] ) && is_array( $value ) ){
						foreach( $arguments['options'] as $check => $array ):
							$checked = in_array( $check, $value ) ? 'checked' : '';
							printf( '<label><input name="%1$s[]" id="%1$s" type="%2$s" class="%3$s" value="%4$s" %5$s />%6$s</label><br>', $arguments['uid'], 'checkbox', $arguments['class'], $check, $checked, $array['display'] );
						endforeach;
					}
					break;
			}

			if( $helper = $arguments['helper'] ){
				printf( '<label class="helper" for="%1$s"> %2$s</label>', $arguments['uid'], $helper );
			}

			if( $supplimental = $arguments['supplemental'] ){
				printf( '<p class="description supplemental">%s</p>', $supplimental );
			}

			echo '</fieldset>';
		}

		public static function wpjmreminders_do_settings_sections( $page ) {
			global $wp_settings_sections, $wp_settings_fields;
			if ( ! isset( $wp_settings_sections[ $page ] ) ) {
				return;
			}

			$default_tab	= 'email-template';
			$tab			= isset( $_GET['tab'] ) && in_array( $_GET['tab'], array( 'reminder-settings', 'email-template' ), true ) ? sanitize_key( $_GET['tab'] ) : $default_tab;

			echo '<nav class="nav-tab-wrapper">';
			foreach ( (array) $wp_settings_sections[ $page ] as $section_tab ) {
				$tab_active = $tab === $section_tab['id'] ? 'nav-tab-active' : null;
				echo '<a href="' . esc_url_raw( add_query_arg( [ 'tab' => $section_tab['id'] ] ) ) . '" class="nav-tab ' . $section_tab['id'] . '_tab ' . $tab_active . '">' . $section_tab['title'] . '</a>';
			}
			echo '</nav>';

			echo '<div class="wpjm-reminders-tab-container tab-content">';
			foreach ( (array) $wp_settings_sections[ $page ] as $section ) {
				if( $tab === $section['id'] ){
					echo "<section class=\"wpjm-reminders-tab {$section['id']} \">\n";
					if ( $section['callback'] ) {
						call_user_func( $section['callback'], $section );
					}
					if ( ! isset( $wp_settings_fields ) || ! isset( $wp_settings_fields[ $page ] ) || ! isset( $wp_settings_fields[ $page ][ $section['id'] ] ) ) {
						continue;
					}
					echo '<table class="form-table settings parent-settings" role="presentation">';
					do_settings_fields( $page, $section['id'] );
					self::wpjmreminders_section_bottom_callback( $section['id'] );
					echo '</table>';
					echo "</section>\n";
				}
			}
			echo '</div>';
		}

		public static function wpjmreminders_admin_scripts(){
			wp_register_style( __NAMESPACE__ . '-admin-style', plugins_url( '/css/admin-style.css', __FILE__ ) );
			wp_enqueue_style( __NAMESPACE__ . '-admin-style' );
			wp_register_script( __NAMESPACE__ . '-admin-script', plugins_url( '/js/admin-script.js', __FILE__ ), ['jquery'] );
			wp_enqueue_script( __NAMESPACE__ . '-admin-script' );
		}

		private static function wpjmreminders_admin_notice( $message, $type = 'info' ){
			if( !is_admin() ){
				return false;
			}

			if( !in_array( $type, array( 'error', 'info', 'success', 'warning' ) ) ){
				return false;
			}

			$transient		= __NAMESPACE__ . '_admin_notice_' . get_current_user_id();
			$notifications	= get_transient( $transient );

			if( !$notifications ){
				$notifications = [];
			}

			$notifications[]	= [
								'message'	=> $message,
								'type'	=> $type
							];

			set_transient( $transient, $notifications );
		}

		public static function wpjmreminders_admin_notice_handler(){
			if( !is_admin() ){
				return;
			}

			$transient		= __NAMESPACE__ . '_admin_notice_' . get_current_user_id();
			$notifications	= get_transient( $transient );

			if( $notifications ){
				foreach( $notifications as $notification ){
					echo '<div class="notice notice-custom notice-' . $notification['type'] . ' notice-' . __NAMESPACE__ . ' is-dismissible"><p>' . esc_html( $notification['message'] ) . '</p></div>';
				}
			}

			delete_transient( $transient );
		}

		public static function wpjmreminders_cron_action(){

			$email_list	= [];

			$remindafter	= get_option( 'wpjmreminders_field_days_past', 3 );
			$remindresend	= get_option( 'wpjmreminders_field_days_resend', 5 );
			$remindmax		= get_option( 'wpjmreminders_field_max_resend', 2 );

			/*
			* Post ID's for not yet emailed listings.
			*/
			$resend_args	= [
							'post_type'			=> 'job_listing',
							'post_status'			=> get_option( 'wpjmreminders_field_reminder_statuses' ),
							'posts_per_page'		=> -1,
							'order'				=> 'ASC',
							'orderby'				=> 'date',
							'fields'				=> 'ids',
							'cache_results'			=> false,
							'update_post_meta_cache'	=> false,
							'update_post_term_cache'	=> false,
							'date_query'			=> [
													[
														'before'		=> $remindafter . ' day ago',
														'inclusive'	=> true
													],
												],
							'meta_query'			=> [
													'relation' => 'OR',
													[
														'key' 	=> '_wpjm_reminders_count',
														'compare'	=> 'NOT EXISTS'
													],
													[
														'key' 	=> '_wpjm_reminders_count',
														'value'	=> 0,
														'compare'	=> '='
													]
												]
						];
			
			if( !empty( $resend_args[ 'post_status' ] ) ){
				$pending_jobs	= new \WP_Query( $resend_args );
				$email_list	= !empty( $pending_jobs->posts ) ? array_merge( $email_list, $pending_jobs->posts ) : $email_list;
				$remindafter	= null;
				wp_reset_postdata();

				/*
				* Post ID's for emailed listings that needs to be reminded.
				*/
				$resend_args['meta_query']				= [
													'relation' => 'AND',
													[
														'key' 	=> '_wpjm_reminders_sent',
														'value'	=> date( "Y-m-d H:i:s", ( time() - strtotime( $remindresend . ' day', 0 ) ) ),
														'type'	=> 'DATETIME',
														'compare'	=> '<'
													],
													[
														'key' 	=> '_wpjm_reminders_count',
														'compare'	=> 'EXISTS'
													],
													[
														'key' 	=> '_wpjm_reminders_count',
														'value'	=> $remindmax,
														'compare'	=> '<'
													]
												];

				$pending_jobs	= new \WP_Query( $resend_args );
				$email_list	= !empty( $pending_jobs->posts ) ? array_merge( $email_list, $pending_jobs->posts ) : $email_list;
				$remindresend	= null;
				$remindmax		= null;
				wp_reset_postdata();
			}

			if( !empty( $email_list ) ){
				if( get_option( 'wpjmreminders_post_id_email_list' ) === false ){
					register_setting( 'wpjmreminders_settings_fields', 'wpjmreminders_post_id_email_list' );
				}
				update_option( 'wpjmreminders_post_id_email_list', $email_list );
			}
		}

		public static function wpjmreminders_cron_mail(){
			$email_list	= get_option( 'wpjmreminders_post_id_email_list' );
			if( !empty( $email_list ) && is_array( $email_list ) ){
				foreach( $email_list as $key => $post_id ){
					if( self::wpjmreminders_send_reminder( $post_id ) ){
						unset( $email_list[ $key ] );
						update_option( 'wpjmreminders_post_id_email_list', $email_list );
					}
				}
			}
		}

		private static function wpjmreminders_cron_schedule(){
			if( !wp_next_scheduled( 'wpjm_reminders_cron_action' ) ){
				wp_schedule_event( time(), get_option( 'wpjmreminders_field_cron_list', 'daily' ), 'wpjm_reminders_cron_action' );
			}
			if( !wp_next_scheduled( 'wpjm_reminders_cron_mail' ) ){
				wp_schedule_event( time(), get_option( 'wpjmreminders_field_cron_interval', 'hourly' ), 'wpjm_reminders_cron_mail' );
			}
		}

		public static function wpjmreminders_send_reminder( $post_id = false ){
			if( !$post_id ){
				return false;
			}

			$mail			= [];
			$mail['message']= get_option( 'wpjmreminders_field_employer_email_content', self::wpjmreminders_default_message() );
			$mail['subject']= get_option( 'wpjmreminders_field_employer_email_subject', self::wpjmreminders_default_subject() );

			/*
			* Apply dynamic data
			* {display_name} - The user's display name.
			* {email_address} - The user's email address.
			* {job_title} - The unpublished job's title.
			* {job_date} - The unpublished job's draft date.
			* {job_url} - The unpublished job's url.
			*/

			if( $post_id === 'test-mail' ){
				$display_name	= '-DISPLAY NAME-';
				$email_address	= get_option( 'admin_email' );
				$job_title		= '-JOB TITLE-';
				$job_date		= '-JOB DATE-';
				$job_url		= '-JOB URL-';
			} else {
				$display_name	= esc_attr( get_the_author_meta( 'display_name', get_post_field( 'post_author', $post_id ) ) );
				$email_address	= esc_attr( get_the_author_meta( 'user_email', get_post_field( 'post_author', $post_id ) ) );
				$job_title		= esc_attr( get_post_field( 'post_title', $post_id ) );
				$job_date		= esc_attr( date( get_option( 'date_format' ), strtotime( get_post_field( 'post_date', $post_id ) ) ) );
				$job_url		= esc_url_raw( add_query_arg( [ 'action' => 'edit', 'job_id' => $post_id ], get_permalink( get_option( 'job_manager_job_dashboard_page_id' ) ) ) );
			}

			$mail			= str_replace(
							[ '{display_name}', '{email_address}', '{job_title}', '{job_date}', '{job_url}' ],
							[ $display_name, $email_address, $job_title, $job_date, $job_url ],
							$mail
						);

			if( "true" === get_option( 'wpjmreminders_field_email_html', false ) ){
				$headers	= [];
			} else {
				$headers	= [ 'Content-Type: text/html; charset=UTF-8' ];
			}

			if( $email_cc = get_option( 'wpjmreminders_field_email_cc', false ) ){
				$headers[]	= 'Cc: ' . str_replace( ' ', '', esc_attr( $email_cc ) );
			}

			if( $email_bcc = get_option( 'wpjmreminders_field_email_bcc', false ) ){
				$headers[]	= 'Bcc: ' . str_replace( ' ', '', esc_attr( $email_bcc ) );
			}

			$to		= apply_filters( 'wpjm_reminders_filter_email', $email_address );
			$subject	= apply_filters( 'wpjm_reminders_filter_subject', $mail['subject'] );
			$message	= apply_filters( 'wpjm_reminders_filter_message', $mail['message'] );
			$headers	= apply_filters( 'wpjm_reminders_filter_headers', $headers );

			$display_name	= null;
			$email_address	= null;
			$job_title		= null;
			$job_date		= null;
			$job_url		= null;
			$email_cc		= null;
			$email_bcc		= null;
			$mail			= null;

			if( !empty( $to ) && !empty( $subject ) && !empty( $message ) && wp_mail( $to, $subject, $message, $headers ) ){
				$to		= null;
				$subject	= null;
				$message	= null;
				$headers	= null;

				if( $post_id === 'test-mail' ){
					return true;
				}

				update_post_meta( $post_id, '_wpjm_reminders_sent', date( "Y-m-d H:i:s", time() ) );

				$reminder_count	= absint( get_post_meta( $post_id, '_wpjm_reminders_count', true ) );
				$reminder_count	= empty( $reminder_count ) ? 1 : $reminder_count + 1;
				update_post_meta( $post_id, '_wpjm_reminders_count', $reminder_count );

				return true;
			}

			return false;
		}

		public static function wpjmreminders_activate_plugin(){
			self::wpjmreminders_cron_schedule();
		}

		public static function wpjmreminders_deactivate_plugin(){
			if( wp_next_scheduled( 'wpjm_reminders_cron_action' ) ) {
				wp_clear_scheduled_hook( 'wpjm_reminders_cron_action' );
			}
			if( wp_next_scheduled( 'wpjm_reminders_cron_mail' ) ) {
				wp_clear_scheduled_hook( 'wpjm_reminders_cron_mail' );
			}
		}

		public static function wpjmreminders_uninstall_plugin(){
			$options	= [
						'wpjmreminders_field_cron_interval',
						'wpjmreminders_field_days_past',
						'wpjmreminders_field_days_resend',
						'wpjmreminders_field_max_resend',
						'wpjmreminders_field_email_cc',
						'wpjmreminders_field_email_bcc',
						'wpjmreminders_field_email_html',
						'wpjmreminders_field_reminder_statuses',
						'wpjmreminders_field_employer_email_subject',
						'wpjmreminders_field_employer_email_content',
						'wpjmreminders_post_id_email_list'
					];
			foreach( $options as $option ){
				delete_option( $option );
			}

			if( wp_next_scheduled( 'wpjm_reminders_cron_action' ) ) {
				wp_clear_scheduled_hook( 'wpjm_reminders_cron_action' );
			}
			if( wp_next_scheduled( 'wpjm_reminders_cron_mail' ) ) {
				wp_clear_scheduled_hook( 'wpjm_reminders_cron_mail' );
			}
		}
	}

	register_activation_hook( __FILE__, [ __NAMESPACE__ . '\WPJM_Reminders', 'wpjmreminders_activate_plugin' ] );
	register_deactivation_hook( __FILE__, [ __NAMESPACE__ . '\WPJM_Reminders', 'wpjmreminders_deactivate_plugin' ] );
	register_uninstall_hook( __FILE__, [ __NAMESPACE__ . '\WPJM_Reminders', 'wpjmreminders_uninstall_plugin' ] );
	add_action( 'plugins_loaded', [ __NAMESPACE__ . '\WPJM_Reminders', 'wpjmreminders_init' ] );
	if ( is_admin() ) {
		add_action( 'plugins_loaded', [ __NAMESPACE__ . '\WPJM_Reminders', 'wpjmreminders_admin_init' ] );
	}
?>