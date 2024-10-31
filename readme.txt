=== Reminders for WP Job Manager  ===
Contributors: effinstudios
Donate link: https://ko-fi.com/effinstudios
Tags: email reminder, employer submission, job listing, wp job manager, wc paid listings
Requires at least: 5.0
Tested up to: 5.6
Requires PHP: 5.5
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==
Reminders for WP Job Manager is a lightweight plugin for websites and job portals that uses [WP Job Manager](https://wordpress.org/plugins/wp-job-manager/) to automatically send email reminders to their users to complete their unpublished, incomplete, forgotten, or unpaid job listings.

Reminders for WP Job Manager is compatible with any WordPress theme that uses [WP Job Manager Plugin](https://wordpress.org/plugins/wp-job-manager/).

== Features ==
* Create your own custom HTML or plain text email reminders.
* Add dynamic job listing data to your reminder emails.
* Fully manage the frequency of how often your website should check for unpublished job listing submissions.
* Select when an email reminder should be sent for an unpublished job listing.
* Automatically resend an email reminder for an unpublished job listing if there is no action from the user.
* Choose which type of unpublished job listings should receive email reminders.
* Add email cc’s or bcc’s to the email reminders to keep track or inform other administrators, human resources, or marketing personnel about the unpublished job listing.
* Manually send reminder emails for specific unpublished job listings.

== Installation ==
Extract the zip file and just drop the contents in your wp-content/plugins/ directory then activate from your wp-admin/plugins page.

== Frequently Asked Questions ==

= Is this compatible with WP Job Manager WC Paid Listings? =
* Yes.

= Does this require WP Job Manager? =
* Yes, it was developed for WP Job Manager Version 1.34.5.

= What version of WP Job Manager do I need? =
* Atleast version 1.34.5, though it might still work for older versions of WP Job Manager but a warning will be advised about it.

= Can I send an email reminder for a specific unpublished job listing? =
* Yes, select and edit an unpublished job listing to find the Reminders Meta Box which has information on the number of email reminders sent, when, and a button to manually send an email reminder.

= Can I add my own custom dynamic data on the emails being sent? =
* Yes, here are the filter tags you can use to change the contents of the email before it is sent:

1. wpjm_reminders_filter_email: Email address (string)
2. wpjm_reminders_filter_subject: Email subject (string)
3. wpjm_reminders_filter_message: Email content (string)
4. wpjm_reminders_filter_headers: Email headers (string/array)


== Screenshots ==
* Please visit our website - [Effin Studios](https://effinstudios.com/wp-plugins/wp-job-manager-reminders) for screenshots.

== Changelog ==

= Version 1.0.0 =
* Initial public release.

== Upgrade Notice ==

= Version 1.0.0 =
If you are using the pre-release version please upgrade to Version 1.0.0 which fixes several bugs and functionality.