<?php

/**
 * Plugin Name: Export Users Data CSV
 * Description: This Plugin allows you to export users data and some of the metadata into CSV, Excel, XML, JSON file.
 * Author: Kaushik Kalathiya
 * Author URI: https://kaushikkalathiya.github.io/kaushik/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version: 3.0
 * Requires at least: 6.1
 * Requires PHP: 7.4
 * Text Domain: export-users-data-csv
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
	die;
}

/**
 * Prevent CSV/Spreadsheet formula injection.
 *
 * Spreadsheet apps may interpret values starting with =, +, -, @ as formulas.
 * Prefixing with a single quote forces literal text in common spreadsheet apps.
 *
 * @param mixed $value Value to sanitize for spreadsheet cell output.
 * @return string
 */
function eudc_sanitize_spreadsheet_cell($value)
{
	$value = (string) $value;

	// Normalize line breaks to avoid breaking rows unexpectedly.
	$value = str_replace(array("\r\n", "\r", "\n"), ' ', $value);

	// If value begins with a formula sigil (optionally after whitespace), neutralize it.
	if (preg_match('/^\s*[=+\-@]/', $value)) {
		$value = "'" . $value;
	}

	return $value;
}

// Add setting link into plugin listing page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'eudc_settings_page_link');
function eudc_settings_page_link($links)
{
	$links[] = '<a href="' . admin_url('users.php') . '">' . __('Export Users', 'export-users-data-csv') . '</a>';
	return $links;
}

// Add Button into admin side user list
if (!function_exists('eudc_export_users')) {
	add_action('admin_footer', 'eudc_export_users');
	function eudc_export_users()
	{
		$screen = get_current_screen();
		// Only add to users.php page
		if ($screen->id != "users")
			return;
?>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				var nonce = '<?php echo esc_js(wp_create_nonce('eudc_export_users')); ?>';
				jQuery('.tablenav.top .clear, .tablenav.bottom .clear').before('<form name="eud_form" action="#" method="POST">' +
					'<input type="hidden" name="eudc_nonce" value="' + nonce + '">' +
					'<select name="edu_select">' +
					'<option value="eud_csv">Export as CSV</option>' +
					'<option value="eud_excel">Export as Excel</option>' +
					'<option value="eud_xml">Export as XML</option>' +
					'<option value="eud_json">Export as JSON</option>' +
					'</select> ' +
					'<input type="submit" class="button" name="eud_btn" value="Export">' +
					'</form>');
			});
		</script>
<?php
	}
}

// Helper function to extract user data from WP_User object
if (!function_exists('eudc_get_user_data')) {
	function eudc_get_user_data($user)
	{
		if (!($user instanceof WP_User)) {
			return null;
		}

		$roles = $user->roles;
		$role = !empty($roles) ? $roles[0] : '';

		return array(
			'user_id' => $user->ID,
			'user_name' => $user->user_login,
			'first_name' => $user->first_name,
			'last_name' => $user->last_name,
			'email' => $user->user_email,
			'nickname' => $user->nickname,
			'user_role' => $role,
			'user_reg_date' => $user->user_registered,
		);
	}
}

// Function to export users data
if (!function_exists('eudc_export_users_data')) {
	add_action('admin_init', 'eudc_export_users_data');
	function eudc_export_users_data()
	{
		if (current_user_can('manage_options') && isset($_POST['eud_btn']) && $_POST['eud_btn'] == 'Export') {
			// Verify nonce
			if (!isset($_POST['eudc_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['eudc_nonce'])), 'eudc_export_users')) {
				wp_die(esc_html__('Security check failed. Please try again.', 'export-users-data-csv'));
			}

			$edu_select = isset($_POST['edu_select']) ? sanitize_text_field(wp_unslash($_POST['edu_select'])) : '';
			if (empty($edu_select)) {
				wp_send_json_error(__('Please select a export format', 'export-users-data-csv'));
			}
			$users = get_users();
			if (empty($users)) {
				wp_send_json_error(__('No users found', 'export-users-data-csv'));
			}

			// Code for CSV
			if ($edu_select == 'eud_csv') {
				header("Content-type: application/force-download");
				header('Content-Disposition: inline; filename="users_' . gmdate('Y_m_d_H_i_s') . '.csv"');

				echo '" User ID "," User Name "," First Name "," Last Name "," Email ID "," Nick Name "," User Role "," Registered Date "' . "\r\n";
				foreach ($users as $user) {
					$user_data = eudc_get_user_data($user);
					if (!$user_data) {
						continue;
					}

					echo '"' . esc_html(eudc_sanitize_spreadsheet_cell($user_data['user_id'])) . '","' .
						esc_html(eudc_sanitize_spreadsheet_cell($user_data['user_name'])) . '","' .
						esc_html(eudc_sanitize_spreadsheet_cell($user_data['first_name'])) . '","' .
						esc_html(eudc_sanitize_spreadsheet_cell($user_data['last_name'])) . '","' .
						esc_html(eudc_sanitize_spreadsheet_cell($user_data['email'])) . '","' .
						esc_html(eudc_sanitize_spreadsheet_cell($user_data['nickname'])) . '","' .
						esc_html(eudc_sanitize_spreadsheet_cell($user_data['user_role'])) . '","' .
						esc_html(eudc_sanitize_spreadsheet_cell($user_data['user_reg_date'])) . '"' . "\r\n";
				}
				exit();
			}

			// Code for Excel
			if ($edu_select == 'eud_excel') {
				header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
				header('Content-Disposition: attachment;filename="users_' . gmdate('Y_m_d_H_i_s') . '.xlsx');

				echo '" User_ID " " User_Name " " First_Name " " Last_Name " " Email_ID " " Nick_Name " " User_Role " " Registered_Date "' . "\r\n";
				foreach ($users as $user) {
					$user_data = eudc_get_user_data($user);
					if (!$user_data) {
						continue;
					}

					echo '" ' . esc_html(eudc_sanitize_spreadsheet_cell($user_data['user_id'])) . ' " " ' .
						esc_html(eudc_sanitize_spreadsheet_cell($user_data['user_name'])) . ' " " ' .
						esc_html(eudc_sanitize_spreadsheet_cell($user_data['first_name'])) . ' " " ' .
						esc_html(eudc_sanitize_spreadsheet_cell($user_data['last_name'])) . ' " " ' .
						esc_html(eudc_sanitize_spreadsheet_cell($user_data['email'])) . '" "' .
						esc_html(eudc_sanitize_spreadsheet_cell($user_data['nickname'])) . '" "' .
						esc_html(eudc_sanitize_spreadsheet_cell($user_data['user_role'])) . '" " ' .
						esc_html(eudc_sanitize_spreadsheet_cell($user_data['user_reg_date'])) . ' " ' . "\r\n";
				}
				exit();
			}

			// Code for XML
			if ($edu_select == 'eud_xml') {
				header("Content-type: text/xml");
				header('Content-Disposition: attachment; filename="users_' . gmdate('Y_m_d_H_i_s') . '.xml');
				echo '<users>';
				foreach ($users as $user) {
					$user_data = eudc_get_user_data($user);
					if (!$user_data) {
						continue;
					}

					echo "\n\t" . '<user>' . "\n\t\t";
					echo '<user_id>' . esc_xml($user_data['user_id']) . '</user_id>' . "\n\t\t" . '<user_name>' . esc_xml($user_data['user_name']) . '</user_name>' . "\n\t\t" . '<first_name>' . esc_xml($user_data['first_name']) . '</first_name>' . "\n\t\t" . '<last_name>' . esc_xml($user_data['last_name']) . '</last_name>' . "\n\t\t" . '<email>' . esc_xml($user_data['email']) . '</email>' . "\n\t\t" . '<nickname>' . esc_xml($user_data['nickname']) . '</nickname>' . "\n\t\t" . '<user_role>' . esc_xml($user_data['user_role']) . '</user_role>' . "\n\t\t" . '<user_reg_date>' . esc_xml($user_data['user_reg_date']) . '</user_reg_date>';
					echo "\n\t" . '</user>';
				}
				echo "\n" . '</users>';
				exit();
			}

			// Code for JSON
			if ($edu_select == 'eud_json') {
				header("Content-type: application/json");
				header('Content-Disposition: attachment; filename="users_' . gmdate('Y_m_d_H_i_s') . '.json');

				$users_data = array();
				foreach ($users as $user) {
					$user_data = eudc_get_user_data($user);
					if ($user_data) {
						$users_data[] = $user_data;
					}
				}
				echo wp_json_encode($users_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
				exit();
			}
		}
	}
}
