<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2006-2016 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

chdir('../../');

include_once('./include/auth.php');
include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

$thold_actions = array(
	1 => 'Export',
	2 => 'Delete'
);

$action = get_nfilter_request_var('action');

if (isset_request_var('drp_action') && get_filter_request_var('drp_action') == 2) {
	$action = 'delete';
}

if (isset_request_var('drp_action') && get_filter_request_var('drp_action') == 1) {
	$action = 'export';
}

if (isset_request_var('import')) {
	$action = 'import';
}

switch ($action) {
	case 'add':
		template_add();
		break;
	case 'save':
		if (isset_request_var('save_component_import')) {
			template_import();
		}elseif (isset_request_var('save') && get_nfilter_request_var('save') == 'edit') {
			template_save_edit();

			if (isset($_SESSION['graph_return'])) {
				$return_to = $_SESSION['graph_return'];
				unset($_SESSION['graph_return']);
				kill_session_var('graph_return');
				header('Location: ' . $return_to);
			}
		} elseif (isset_request_var('save') && get_nfilter_request_var('save') == 'add') {

		}

		break;
	case 'delete':
		template_delete();

		break;
	case 'export':
		template_export();

		break;
	case 'import':
		top_header();
		import();
		bottom_footer();

		break;
	case 'edit':
		top_header();
		template_edit();
		bottom_footer();

		break;
	default:
		top_header();
		templates();
		bottom_footer();

		break;
}

function template_export() {
	$output = "<templates>\n";
	if (sizeof($_POST)) {
		foreach($_POST as $t => $v) {
			if (substr($t, 0,4) == 'chk_') {
				$id = substr($t, 4);

				if (is_numeric($id)) {
					$data = db_fetch_row_prepared('SELECT * FROM thold_template WHERE id = ?', array($id));
					if (sizeof($data)) {
						$data_template_hash = db_fetch_cell_prepared('SELECT hash
							FROM data_template
							WHERE id = ?', array($data['data_template_id']));

						$data_source_hash   = db_fetch_cell_prepared('SELECT hash
							FROM data_template_rrd
							WHERE id = ?', array($data['data_source_id']));

						unset($data['id']);
						$data['data_template_id'] = $data_template_hash;
						$data['data_source_id']   = $data_source_hash;
						$output .= array2xml($data);
					}
				}
			}
		}
	}

	$output .= "</templates>\n";

	header('Content-type: application/xml');
	header('Content-Disposition: attachment; filename=thold_template_export.xml');

	print $output;

	exit;
}

function template_delete() {
	foreach($_POST as $t=>$v) {
		if (substr($t, 0,4) == 'chk_') {
			$id = substr($t, 4);
			input_validate_input_number($id);
			plugin_thold_log_changes($id, 'deleted_template', array('id' => $id));
			db_fetch_assoc('DELETE FROM thold_template WHERE id = ? LIMIT 1', array($id));
			db_execute_prepared('DELETE FROM plugin_thold_template_contact WHERE template_id = ?', array($id));
			db_execute_prepared("UPDATE thold_data SET template = '', template_enabled = 'off' WHERE template = ?", array($id));
		}
	}

	header('Location: thold_templates.php?header=false');
	exit;
}

function template_add() {
	if ((!isset_request_var('save')) || (get_nfilter_request_var('save') == '')) {
		$data_templates = array_rekey(db_fetch_assoc('select id, name from data_template order by name'), 'id', 'name');

		top_header();

		?>
		<script type='text/javascript'>

		function applyFilter(type) {
			if (type == 'dt' && $('#data_source_id')) {
				$('#data_source_id').val('');
			}

			if ($('#save')) {
				$('#save').val('');
			}

			document.tholdform.submit();
		}

		</script>
		<?php

		html_start_box('Threshold Template Creation Wizard', '50%', '', '3', 'center', '');

		print "<tr><td><form action=thold_templates.php method='post' name='tholdform'>";

		if (!isset_request_var('data_template_id')) set_request_var('data_template_id', '');
		if (!isset_request_var('data_source_id'))   set_request_var('data_source_id', '');

		if (get_fitler_request_var('data_template_id') == '') {
			print '<center><h3>Please select a Data Template</h3></center>';
		} else if (get_filter_request_var('data_source_id') == '') {
			print '<center><h3>Please select a Data Source</h3></center>';
		} else {
			print '<center><h3>Please press "Create" to create your Threshold Template</h3></center>';
		}

		/* display the data template dropdown */
		?>
		<table class='filterTable'>
			<tr>
				<td>
					Data Template
				</td>
				<td>
					<select id=data_template_id onChange="applyFilter('dt')">
						<option value=''>None</option><?php
						foreach ($data_templates as $id => $name) {
							echo "<option value='" . $id . "'" . ($id == get_request_var('data_template_id') ? ' selected' : '') . '>' . $name . '</option>';
						}?>
					</select>
				</td>
			</tr><?php

		if (get_request_var('data_template_id') != '') {
			$data_template_id = get_request_var('data_template_id');
			$data_fields      = array();
			$temp             = db_fetch_assoc('select id, local_data_template_rrd_id, data_source_name, data_input_field_id from data_template_rrd where local_data_template_rrd_id = 0 and data_template_id = ' . $data_template_id);

			foreach ($temp as $d) {
				if ($d['data_input_field_id'] != 0) {
					$temp2 = db_fetch_assoc('select name, data_name from data_input_fields where id = ' . $d['data_input_field_id']);
					$data_fields[$d['id']] = $temp2[0]['data_name'] . ' (' . $temp2[0]['name'] . ')';
				} else {
					$temp2[0]['name'] = $d['data_source_name'];
					$data_fields[$d['id']] = $temp2[0]['name'];
				}
			}

			/* display the data source dropdown */
			?>
			<tr>
				<td>
					Data Source
				</td>
				<td>
					<select id='data_source_id' name='data_source_id' onChange="applyTholdFilterChange(document.tholdform, 'ds')">
						<option value=''>None</option><?php
						foreach ($data_fields as $id => $name) {
							echo "<option value='" . $id . "'" . ($id == get_request_var('data_source_id') ? ' selected' : '') . '>' . $name . '</option>';
						}?>
					</select>
				</td>
			</tr>
			<?php
		}

		if (get_request_var('data_source_id') != '') {
			echo '<tr><td colspan=2><input type=hidden name=action value="add"><input id="save" type=hidden name="save" value="save"><br><center><input type="submit" value="Create"></center></td></tr>';
		} else {
			echo '<tr><td colspan=2><input type=hidden name=action value="add"><br><br><br></td></tr>';
		}
		echo '</table></form></td></tr>';

		html_end_box();

		bottom_footer();
	} else {
		$data_template_id = get_filter_request_var('data_template_id');
		$data_source_id   = get_filter_request_var('data_source_id');

		$save['id']       = '';
		$save['hash']     = get_hash_thold_template(0);
		$temp             = db_fetch_row('SELECT id, name FROM data_template WHERE id=' . $data_template_id . ' LIMIT 1');
		$save['name']     = $temp['name'];

		$save['data_template_id']   = $data_template_id;
		$save['data_template_name'] = $temp['name'];
		$save['data_source_id']     = $data_source_id;

		$temp = db_fetch_row('SELECT id, local_data_template_rrd_id, 
			data_source_name, data_input_field_id 
			FROM data_template_rrd 
			WHERE id = ' . $data_source_id . ' 
			LIMIT 1');

		$save['data_source_name']  = $temp['data_source_name'];
		$save['name']             .= ' [' . $temp['data_source_name'] . ']';

		if ($temp['data_input_field_id'] != 0) {
			$temp2 = db_fetch_row('SELECT name FROM data_input_fields WHERE id = ' . $temp['data_input_field_id'] . ' LIMIT 1');
		} else {
			$temp2['name'] = $temp['data_source_name'];
		}

		$save['data_source_friendly'] = $temp2['name'];
		$save['thold_enabled']        = 'on';
		$save['thold_type']           = 0;
		$save['repeat_alert']         = read_config_option('alert_repeat');

		$id = sql_save($save, 'thold_template');

		if ($id) {
			plugin_thold_log_changes($id, 'modified_template', $save);
			Header("Location: thold_templates.php?action=edit&id=$id");
			exit;
		} else {
			raise_message('thold_save');
			Header('Location: thold_templates.php?action=add');
			exit;
		}
	}
}

function template_save_edit() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('thold_type');
	get_filter_request_var('thold_hi');
	get_filter_request_var('thold_low');
	get_filter_request_var('thold_fail_trigger');
	get_filter_request_var('time_hi');
	get_filter_request_var('time_low');
	get_filter_request_var('time_fail_trigger');
	get_filter_request_var('time_fail_length');
	get_filter_request_var('thold_warning_type');
	get_filter_request_var('thold_warning_hi');
	get_filter_request_var('thold_warning_low');
	get_filter_request_var('thold_warning_fail_trigger');
	get_filter_request_var('time_warning_hi');
	get_filter_request_var('time_warning_low');
	get_filter_request_var('time_warning_fail_trigger');
	get_filter_request_var('time_warning_fail_length');
	get_filter_request_var('bl_ref_time_range');
	get_filter_request_var('bl_pct_down');
	get_filter_request_var('bl_pct_up');
	get_filter_request_var('bl_fail_trigger');
	get_filter_request_var('repeat_alert');
	get_filter_request_var('data_type');
	get_filter_request_var('cdef');
	get_filter_request_var('notify_warning');
	get_filter_request_var('notify_alert');
	get_filter_request_var('snmp_event_severity');
	get_filter_request_var('snmp_event_warning_severity');
	/* ==================================================== */

	/* clean up date1 string */
	if (isset_request_var('name')) {
		set_request_var('name', trim(str_replace(array("\\", "'", '"'), '', get_nfilter_request_var('name'))));
	}

	if (isset_request_var('snmp_trap_category')) {
		set_request_var('snmp_event_category', db_qstr(trim(str_replace(array("\\", "'", '"'), '', get_nfilter_request_var('snmp_event_category')))));
	}

	/* save: data_template */
	$save['id']                 = get_request_var('id');
	$save['hash']               = get_hash_thold_template($save['id']);
	$save['name']               = get_request_var('name');
	$save['thold_type']         = get_request_var('thold_type');

	// High / Low
	$save['thold_hi']           = get_request_var('thold_hi');
	$save['thold_low']          = get_request_var('thold_low');
	$save['thold_fail_trigger'] = get_request_var('thold_fail_trigger');
	// Time Based
	$save['time_hi']            = get_request_var('time_hi');
	$save['time_low']           = get_request_var('time_low');

	$save['time_fail_trigger']  = get_request_var('time_fail_trigger');
	$save['time_fail_length']   = get_request_var('time_fail_length');

	if (isset_request_var('thold_fail_trigger') && get_request_var('thold_fail_trigger') != '') {
		$save['thold_fail_trigger'] = get_request_var('thold_fail_trigger');
	} else {
		$alert_trigger = read_config_option('alert_trigger');
		if ($alert_trigger != '' && is_numeric($alert_trigger)) {
			$save['thold_fail_trigger'] = $alert_trigger;
		} else {
			$save['thold_fail_trigger'] = 5;
		}
	}

	/***  Warnings  ***/
	// High / Low Warnings
	$save['thold_warning_hi']           = get_request_var('thold_warning_hi');
	$save['thold_warning_low']          = get_request_var('thold_warning_low');
	$save['thold_warning_fail_trigger'] = get_request_var('thold_warning_fail_trigger');

	// Time Based Warnings
	$save['time_warning_hi']            = get_request_var('time_warning_hi');
	$save['time_warning_low']           = get_request_var('time_warning_low');

	$save['time_warning_fail_trigger']  = get_request_var('time_warning_fail_trigger');
	$save['time_warning_fail_length']   = get_request_var('time_warning_fail_length');

	if (isset_request_var('thold_warning_fail_trigger') && get_request_var('thold_warning_fail_trigger') != '') {
		$save['thold_warning_fail_trigger'] = get_request_var('thold_warning_fail_trigger');
	} else {
		$alert_trigger = read_config_option('alert_trigger');
		if ($alert_trigger != '' && is_numeric($alert_trigger)) {
			$save['thold_warning_fail_trigger'] = $alert_trigger;
		} else {
			$save['thold_warning_fail_trigger'] = 5;
		}
	}

	$save['thold_enabled']  = isset_request_var('thold_enabled')  ? 'on' : 'off';
	$save['exempt']         = isset_request_var('exempt')         ? 'on' : 'off';
	$save['restored_alert'] = isset_request_var('restored_alert') ? 'on' : 'off';

	if (isset_request_var('bl_ref_time_range') && get_request_var('bl_ref_time_range') != '') {
		$save['bl_ref_time_range'] = get_request_var('bl_ref_time_range');
	} else {
		$alert_bl_timerange_def = read_config_option('alert_bl_timerange_def');
		if ($alert_bl_timerange_def != '' && is_numeric($alert_bl_timerange_def)) {
			$save['bl_ref_time_range'] = $alert_bl_timerange_def;
		} else {
			$save['bl_ref_time_range'] = 10800;
		}
	}

	$save['bl_pct_down'] = get_request_var('bl_pct_down');
	$save['bl_pct_up']   = get_request_var('bl_pct_up');

	if (isset_request_var('bl_fail_trigger') && get_request_var('bl_fail_trigger') != '') {
		$save['bl_fail_trigger'] = get_request_var('bl_fail_trigger');
	} else {
		$alert_bl_trigger = read_config_option('alert_bl_trigger');
		if ($alert_bl_trigger != '' && is_numeric($alert_bl_trigger)) {
			$save['bl_fail_trigger'] = $alert_bl_trigger;
		} else {
			$save['bl_fail_trigger'] = 3;
		}
	}

	if (isset_request_var('repeat_alert') && get_request_var('repeat_alert') != '') {
		$save['repeat_alert'] = get_request_var('repeat_alert');
	} else {
		$alert_repeat = read_config_option('alert_repeat');
		if ($alert_repeat != '' && is_numeric($alert_repeat)) {
			$save['repeat_alert'] = $alert_repeat;
		} else {
			$save['repeat_alert'] = 12;
		}
	}

	if (isset_request_var('snmp_event_category')) {
		$save['snmp_event_category'] = get_request_var('snmp_event_category');
		$save['snmp_event_severity'] = get_request_var('snmp_event_severity');
	}
	if (isset_request_var('snmp_event_warning_severity')) {
		if (get_request_var('snmp_event_warning_severity') > get_request_var('snmp_event_severity')) {
			$save['snmp_event_warning_severity'] = get_request_var('snmp_event_severity');
		}else {
			$save['snmp_event_warning_severity'] = get_request_var('snmp_event_warning_severity');
		}
	}

	$save['notify_extra']         = get_request_var('notify_extra');
	$save['notify_warning_extra'] = get_request_var('notify_warning_extra');
	$save['notify_warning']       = get_request_var('notify_warning');
	$save['notify_alert']         = get_request_var('notify_alert');
	$save['cdef']                 = get_request_var('cdef');

	$save['data_type']            = get_request_var('data_type');
	$save['percent_ds']           = get_request_var('percent_ds');
	$save['expression']           = get_request_var('expression');

	if (!is_error_message()) {
		$id = sql_save($save, 'thold_template');
		if ($id) {
			raise_message(1);
			if (isset_request_var('notify_accounts') && is_array(get_nfilter_request_var('notify_accounts'))) {
				thold_save_template_contacts($id, get_nfilter_request_var('notify_accounts'));
			} elseif (!isset_request_var('notify_accounts')) {
				thold_save_template_contacts($id, array());
			}
			thold_template_update_thresholds ($id);

			plugin_thold_log_changes($id, 'modified_template', $save);
		} else {
			raise_message(2);
		}
	}

	if ((is_error_message()) || (isempty_request_var('id'))) {
		header('Location: thold_templates.php?header=false&action=edit&id=' . (empty($id) ? get_request_var('id') : $id));
	} else {
		header('Location: thold_templates.php?header=false');
	}
}

function template_edit() {
	global $config;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$id = get_request_var('id');

	$thold_item_data = db_fetch_row('SELECT * 
		FROM thold_template 
		WHERE id=' . $id . ' 
		LIMIT 1');

	$temp = db_fetch_assoc('SELECT id, name 
		FROM data_template 
		WHERE id=' . $thold_item_data['data_template_id']);

	foreach ($temp as $d) {
		$data_templates[$d['id']] = $d['name'];
	}

	$temp = db_fetch_row('SELECT id, data_source_name, data_input_field_id
		FROM data_template_rrd
		WHERE id=' . $thold_item_data['data_source_id'] . ' 
		LIMIT 1');

	$source_id = $temp['data_input_field_id'];

	if ($source_id != 0) {
		$temp2 = db_fetch_assoc('SELECT id, name FROM data_input_fields WHERE id=' . $source_id);
		foreach ($temp2 as $d) {
			$data_fields[$d['id']] = $d['name'];
		}
	} else {
		$data_fields[$temp['id']]= $temp['data_source_name'];
	}

	$send_notification_array = array();

	$users = db_fetch_assoc("SELECT plugin_thold_contacts.id, plugin_thold_contacts.data,
		plugin_thold_contacts.type, user_auth.full_name
		FROM plugin_thold_contacts, user_auth
		WHERE user_auth.id=plugin_thold_contacts.user_id
		AND plugin_thold_contacts.data!=''
		ORDER BY user_auth.full_name ASC, plugin_thold_contacts.type ASC");

	if (!empty($users)) {
		foreach ($users as $user) {
			$send_notification_array[$user['id']] = $user['full_name'] . ' - ' . ucfirst($user['type']);
		}
	}
	if (isset($thold_item_data['id'])) {
		$sql = 'SELECT contact_id as id FROM plugin_thold_template_contact WHERE template_id=' . $thold_item_data['id'];
	} else {
		$sql = 'SELECT contact_id as id FROM plugin_thold_template_contact WHERE template_id=0';
	}

	$step = db_fetch_cell('SELECT rrd_step FROM data_template_data WHERE data_template_id = ' . $thold_item_data['data_template_id'], FALSE);

	include($config['base_path'] . '/plugins/thold/includes/arrays.php');

	$rra_steps = db_fetch_assoc("SELECT dspr.steps
		FROM data_template_data AS dtd
		INNER JOIN data_source_profiles AS dsp
	    ON dsp.id=dtd.data_source_profile_id
		INNER JOIN data_source_profiles_rra AS dspr
		ON dsp.id=dspr.data_source_profile_id
	    WHERE dspr.steps>1
		AND dtd.data_template_id=" . $thold_item_data['data_template_id'] . "
	    AND dtd.local_data_template_data_id=0
		ORDER BY steps");

	$reference_types = array();
	foreach($rra_steps as $rra_step) {
	    $seconds = $step * $rra_step['steps'];
	    $reference_types[$seconds] = $timearray[$rra_step['steps']] . " Average" ;
	}

	$data_fields2 = array();
	$temp = db_fetch_assoc('SELECT id, local_data_template_rrd_id, data_source_name,
		data_input_field_id
		FROM data_template_rrd
		WHERE local_data_template_rrd_id=0
		AND data_template_id=' . $thold_item_data['data_template_id']);

	foreach ($temp as $d) {
		if ($d['data_input_field_id'] != 0) {
			$temp2 = db_fetch_assoc('SELECT id, name, data_name
				FROM data_input_fields
				WHERE id=' . $d['data_input_field_id'] . '
				ORDER BY data_name');

			$data_fields2[$d['data_source_name']] = $temp2[0]['data_name'] . ' (' . $temp2[0]['name'] . ')';
		} else {
			$temp2[0]['name'] = $d['data_source_name'];
			$data_fields2[$d['data_source_name']] = $temp2[0]['name'];
		}
	}

	$replacements = db_fetch_assoc("SELECT DISTINCT field_name
		FROM data_local AS dl
		INNER JOIN (SELECT DISTINCT field_name, snmp_query_id FROM host_snmp_cache) AS hsc
		ON dl.snmp_query_id=hsc.snmp_query_id
		WHERE dl.data_template_id=" . $thold_item_data['data_template_id']);

	$nr = array();
	if (sizeof($replacements)) {
	foreach($replacements as $r) {
		$nr[] = "<span style='color:blue;'>|query_" . $r['field_name'] . "|</span>";
	}
	}

	$vhf = explode("|", trim(VALID_HOST_FIELDS, "()"));
	if (sizeof($vhf)) {
	foreach($vhf as $r) {
		$nr[] = "<span style='color:blue;'>|" . $r . "|</span>";
	}
	}

	$replacements = "<br><b>Replacement Fields:</b> " . implode(", ", $nr);

	$dss = db_fetch_assoc("SELECT data_source_name FROM data_template_rrd WHERE data_template_id=" . $thold_item_data['data_template_id'] . " AND local_data_id=0");

	if (sizeof($dss)) {
	foreach($dss as $ds) {
		$dsname[] = "<span style='color:blue;'>|ds:" . $ds["data_source_name"] . "|</span>";
	}
	}

	$datasources = "<br><b>Data Sources:</b> " . implode(", ", $dsname);

	print "<form name='THold' action='thold_templates.php' method='post'>\n";

	html_start_box('', '100%', '', '3', 'center', '');
	$form_array = array(
		'general_header' => array(
			'friendly_name' => 'General Settings',
			'method' => 'spacer',
		),
		'name' => array(
			'friendly_name' => 'Template Name',
			'method' => 'textbox',
			'max_length' => 100,
			'default' => $thold_item_data['data_template_name'] . ' [' . $thold_item_data['data_source_name'] . ']',
			'description' => 'Provide the Threshold Template a meaningful name.  Device Substritution and Data Query Substitution variables can be used as well as |graph_title| for the Graph Title',
			'value' => isset($thold_item_data['name']) ? $thold_item_data['name'] : ''
		),
		'data_template_name' => array(
			'friendly_name' => 'Data Template',
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => 'Data Template that you are using. (This can not be changed)',
			'value' => $thold_item_data['data_template_id'],
			'array' => $data_templates,
		),
		'data_field_name' => array(
			'friendly_name' => 'Data Field',
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => 'Data Field that you are using. (This can not be changed)',
			'value' => $thold_item_data['id'],
			'array' => $data_fields,
		),
		'thold_enabled' => array(
			'friendly_name' => 'Enabled',
			'method' => 'checkbox',
			'default' => 'on',
			'description' => 'Whether or not this Threshold will be checked and alerted upon.',
			'value' => isset($thold_item_data['thold_enabled']) ? $thold_item_data['thold_enabled'] : ''
		),
		'exempt' => array(
			'friendly_name' => 'Weekend Exemption',
			'description' => 'If this is checked, this Threshold will not alert on weekends.',
			'method' => 'checkbox',
			'default' => 'off',
			'value' => isset($thold_item_data['exempt']) ? $thold_item_data['exempt'] : ''
			),
		'restored_alert' => array(
			'friendly_name' => 'Disable Restoration Email',
			'description' => 'If this is checked, Thold will not send an alert when the Threshold has returned to normal status.',
			'method' => 'checkbox',
			'default' => 'off',
			'value' => isset($thold_item_data['restored_alert']) ? $thold_item_data['restored_alert'] : ''
			),
		'thold_type' => array(
			'friendly_name' => 'Threshold Type',
			'method' => 'drop_array',
			'on_change' => 'changeTholdType()',
			'array' => $thold_types,
			'default' => read_config_option('thold_type'),
			'description' => 'The type of Threshold that will be monitored.',
			'value' => isset($thold_item_data['thold_type']) ? $thold_item_data['thold_type'] : ''
		),
		'repeat_alert' => array(
			'friendly_name' => 'Re-Alert Cycle',
			'method' => 'drop_array',
			'array' => $repeatarray,
			'default' => read_config_option('alert_repeat'),
			'description' => 'Repeat alert after this amount of time has pasted since the last alert.',
			'value' => isset($thold_item_data['repeat_alert']) ? $thold_item_data['repeat_alert'] : ''
		),
		'thold_warning_header' => array(
			'friendly_name' => 'Warning - High / Low Settings',
			'method' => 'spacer',
		),
		'thold_warning_hi' => array(
			'friendly_name' => 'High Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes above this number, alert will be triggered',
			'value' => isset($thold_item_data['thold_warning_hi']) ? $thold_item_data['thold_warning_hi'] : ''
		),
		'thold_warning_low' => array(
			'friendly_name' => 'Low Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes below this number, alert will be triggered',
			'value' => isset($thold_item_data['thold_warning_low']) ? $thold_item_data['thold_warning_low'] : ''
		),
		'thold_warning_fail_trigger' => array(
			'friendly_name' => 'Min Trigger Duration',
			'method' => 'drop_array',
			'array' => $alertarray,
			'description' => 'The amount of time the data source must be in a breach condition for an alert to be raised.',
			'value' => isset($thold_item_data['thold_warning_fail_trigger']) ? $thold_item_data['thold_warning_fail_trigger'] : read_config_option('alert_trigger')
		),
		'thold_header' => array(
			'friendly_name' => 'Alert - High / Low Settings',
			'method' => 'spacer',
		),
		'thold_hi' => array(
			'friendly_name' => 'High Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes above this number, alert will be triggered',
			'value' => isset($thold_item_data['thold_hi']) ? $thold_item_data['thold_hi'] : ''
		),
		'thold_low' => array(
			'friendly_name' => 'Low Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes below this number, alert will be triggered',
			'value' => isset($thold_item_data['thold_low']) ? $thold_item_data['thold_low'] : ''
		),
		'thold_fail_trigger' => array(
			'friendly_name' => 'Min Trigger Duration',
			'method' => 'drop_array',
			'array' => $alertarray,
			'description' => 'The amount of time the data source must be in a breach condition for an alert to be raised.',
			'value' => isset($thold_item_data['thold_fail_trigger']) ? $thold_item_data['thold_fail_trigger'] : read_config_option('alert_trigger')
		),
		'time_warning_header' => array(
			'friendly_name' => 'Warning - Time Based Settings',
			'method' => 'spacer',
		),
		'time_warning_hi' => array(
			'friendly_name' => 'High Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes above this number, warning will be triggered',
			'value' => isset($thold_item_data['time_warning_hi']) ? $thold_item_data['time_warning_hi'] : ''
		),
		'time_warning_low' => array(
			'friendly_name' => 'Low Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes below this number, warning will be triggered',
			'value' => isset($thold_item_data['time_warning_low']) ? $thold_item_data['time_warning_low'] : ''
		),
		'time_warning_fail_trigger' => array(
			'friendly_name' => 'Trigger Count',
			'method' => 'textbox',
			'max_length' => 5,
			'size' => 10,
			'default' => read_config_option('thold_warning_time_fail_trigger'),
			'description' => 'The number of times the data source must be in breach condition prior to issuing a warning.',
			'value' => isset($thold_item_data['time_warning_fail_trigger']) ? $thold_item_data['time_warning_fail_trigger'] : read_config_option('alert_trigger')
		),
		'time_warning_fail_length' => array(
			'friendly_name' => 'Time Period Length',
			'method' => 'drop_array',
			'array' => $timearray,
			'description' => 'The amount of time in the past to check for Threshold breaches.',
			'value' => isset($thold_item_data['time_warning_fail_length']) ? $thold_item_data['time_warning_fail_length'] : (read_config_option('thold_time_fail_length') > 0 ? read_config_option('thold_warning_time_fail_length') : 1)
		),
		'time_header' => array(
			'friendly_name' => 'Alert - Time Based Settings',
			'method' => 'spacer',
		),
		'time_hi' => array(
			'friendly_name' => 'High Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes above this number, alert will be triggered',
			'value' => isset($thold_item_data['time_hi']) ? $thold_item_data['time_hi'] : ''
		),
		'time_low' => array(
			'friendly_name' => 'Low Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes below this number, alert will be triggered',
			'value' => isset($thold_item_data['time_low']) ? $thold_item_data['time_low'] : ''
		),
		'time_fail_trigger' => array(
			'friendly_name' => 'Trigger Count',
			'method' => 'textbox',
			'max_length' => 5,
			'size' => 10,
			'description' => 'The number of times the data source must be in breach condition prior to issuing an alert.',
			'value' => isset($thold_item_data['time_fail_trigger']) ? $thold_item_data['time_fail_trigger'] : read_config_option('thold_time_fail_trigger')
		),
		'time_fail_length' => array(
			'friendly_name' => 'Time Period Length',
			'method' => 'drop_array',
			'array' => $timearray,
			'description' => 'The amount of time in the past to check for Threshold breaches.',
			'value' => isset($thold_item_data['time_fail_length']) ? $thold_item_data['time_fail_length'] : (read_config_option('thold_time_fail_length') > 0 ? read_config_option('thold_time_fail_length') : 2)
		),
		'baseline_header' => array(
			'friendly_name' => 'Baseline Monitoring',
			'method' => 'spacer',
		),
		'bl_ref_time_range' => array(
			'friendly_name' => 'Time reference in the past',
			'method' => 'drop_array',
			'array' => $reference_types,
			'description' => 'Specifies the point in the past (based on rrd resolution) that will be used as a reference',
			'value' => isset($thold_item_data['bl_ref_time_range']) ? $thold_item_data['bl_ref_time_range'] : read_config_option('alert_bl_timerange_def')
		),
		'bl_pct_up' => array(
			'friendly_name' => 'Baseline Deviation UP',
			'method' => 'textbox',
			'max_length' => 3,
			'size' => 10,
			'description' => 'Specifies allowed deviation in percentage for the upper bound Threshold. If not set, upper bound Threshold will not be checked at all.',
			'value' => isset($thold_item_data['bl_pct_up']) ? $thold_item_data['bl_pct_up'] : read_config_option("alert_bl_percent_def")
		),
		'bl_pct_down' => array(
			'friendly_name' => 'Baseline Deviation DOWN',
			'method' => 'textbox',
			'max_length' => 3,
			'size' => 10,
			'description' => 'Specifies allowed deviation in percentage for the lower bound Threshold. If not set, lower bound Threshold will not be checked at all.',
			'value' => isset($thold_item_data['bl_pct_down']) ? $thold_item_data['bl_pct_down'] : read_config_option("alert_bl_percent_def")
		),
		'bl_fail_trigger' => array(
			'friendly_name' => 'Baseline Trigger Count',
			'method' => 'textbox',
			'max_length' => 3,
			'size' => 10,
			'description' => 'Number of consecutive times the data source must be in a breached condition for an alert to be raised.<br>Leave empty to use default value (<b>Default: ' . read_config_option('alert_bl_trigger') . ' cycles</b>)',
			'value' => isset($thold_item_data['bl_fail_trigger']) ? $thold_item_data['bl_fail_trigger'] : read_config_option("alert_bl_trigger")
		),
		'data_manipulation' => array(
			'friendly_name' => 'Data Manipulation',
			'method' => 'spacer',
		),
		'data_type' => array(
			'friendly_name' => 'Data Type',
			'method' => 'drop_array',
			'on_change' => 'changeDataType()',
			'array' => $data_types,
			'description' => 'Special formatting for the given data.',
			'value' => isset($thold_item_data['data_type']) ? $thold_item_data['data_type'] : read_config_option('data_type')
		),
		'cdef' => array(
			'friendly_name' => 'Threshold CDEF',
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => 'Apply this CDEF before returning the data.',
			'value' => isset($thold_item_data['cdef']) ? $thold_item_data['cdef'] : 0,
			'array' => thold_cdef_select_usable_names()
		),
		'percent_ds' => array(
			'friendly_name' => 'Percent Datasource',
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => 'Second Datasource Item to use as total value to calculate percentage from.',
			'value' => isset($thold_item_data['percent_ds']) ? $thold_item_data['percent_ds'] : 0,
			'array' => $data_fields2,
		),
		'expression' => array(
			'friendly_name' => 'RPN Expression',
			'method' => 'textbox',
			'default' => '',
			'description' => 'An RPN Expression is an RRDtool Compatible RPN Expression.  Syntax includes
			all functions below in addition to both Device and Data Query replacement expressions such as
			<span style="color:blue;">|query_ifSpeed|</span>.  To use a Data Source in the RPN Expression, you must use the syntax: <span style="color:blue;">|ds:dsname|</span>.  For example, <span style="color:blue;">|ds:traffic_in|</span> will get the current value
			of the traffic_in Data Source for the RRDfile(s) associated with the Graph. Any Data Source for a Graph can be included.<br>Math Operators: <span style="color:blue;">+, -, /, *, %, ^</span><br>Functions: <span style="color:blue;">SIN, COS, TAN, ATAN, SQRT, FLOOR, CEIL, DEG2RAD, RAD2DEG, ABS, EXP, LOG, ATAN, ADNAN</span><br>Flow Operators: <span style="color:blue;">UN, ISINF, IF, LT, LE, GT, GE, EQ, NE</span><br>Comparison Functions: <span style="color:blue;">MAX, MIN, INF, NEGINF, NAN, UNKN, COUNT, PREV</span>'.$replacements.$datasources,
			'value' => isset($thold_item_data['expression']) ? $thold_item_data['expression'] : '',
			'max_length' => '255',
			'size' => '80'
		),
		'other_header' => array(
			'friendly_name' => 'Other Settings',
			'method' => 'spacer',
		),
		'notify_warning' => array(
			'friendly_name' => 'Warning Notification List',
			'method' => 'drop_sql',
			'description' => 'You may specify choose a Notification List to receive Warnings for this Data Source',
			'value' => isset($thold_item_data['notify_warning']) ? $thold_item_data['notify_warning'] : '',
			'none_value' => 'None',
			'sql' => 'SELECT id, name FROM plugin_notification_lists ORDER BY name'
		),
		'notify_alert' => array(
			'friendly_name' => 'Alert Notification List',
			'method' => 'drop_sql',
			'description' => 'You may specify choose a Notification List to receive Alerts for this Data Source',
			'value' => isset($thold_item_data['notify_alert']) ? $thold_item_data['notify_alert'] : '',
			'none_value' => 'None',
			'sql' => 'SELECT id, name FROM plugin_notification_lists ORDER BY name'
		)
	);

	if (read_config_option("thold_alert_snmp") == 'on') {
		$extra = array(
			'snmp_event_category' => array(
				'friendly_name' => 'SNMP Notification - Event Category',
				'method' => 'textbox',
				'description' => 'To allow a NMS to categorize different SNMP notifications more easily please fill in the category SNMP notifications for this template should make use of. E.g.: "disk_usage", "link_utilization", "ping_test", "nokia_firewall_cpu_utilization" ...',
				'value' => isset($thold_item_data['snmp_event_category']) ? $thold_item_data['snmp_event_category'] : '',
				'default' => '',
				'max_length' => '255',
			),
			'snmp_event_severity' => array(
				'friendly_name' => 'SNMP Notification - Alert Event Severity',
				'method' => 'drop_array',
				'default' => '3',
				'description' => 'Severity to be used for alerts. (low impact -> critical impact)',
				'value' => isset($thold_item_data['snmp_event_severity']) ? $thold_item_data['snmp_event_severity'] : 3,
				'array' => array( 1=>"low", 2=> "medium", 3=> "high", 4=> "critical"),
			),
		);
		$form_array += $extra;

		if(read_config_option("thold_alert_snmp_warning") != "on") {
			$extra = array(
				'snmp_event_warning_severity' => array(
					'friendly_name' => 'SNMP Notification - Warning Event Severity',
					'method' => 'drop_array',
					'default' => '2',
					'description' => 'Severity to be used for warnings. (low impact -> critical impact).<br>Note: The severity of warnings has to be equal or lower than the severity being defined for alerts.',
					'value' => isset($thold_item_data['snmp_event_warning_severity']) ? $thold_item_data['snmp_event_warning_severity'] : 2,
					'array' => array( 1=>"low", 2=> "medium", 3=> "high", 4=> "critical"),
				),
			);
		}
		$form_array += $extra;
	}

	if (read_config_option("thold_disable_legacy") != 'on') {
		$extra = array(
			'notify_accounts' => array(
				'friendly_name' => 'Notify accounts',
				'method' => 'drop_multi',
				'description' => 'This is a listing of accounts that will be notified when this Threshold is breached.<br><br><br><br>',
				'array' => $send_notification_array,
				'sql' => $sql,
			),
			'notify_extra' => array(
				'friendly_name' => 'Alert Emails',
				'method' => 'textarea',
				'textarea_rows' => 3,
				'textarea_cols' => 50,
				'description' => 'You may specify here extra Emails to receive alerts for this data source (comma separated)',
				'value' => isset($thold_item_data['notify_extra']) ? $thold_item_data['notify_extra'] : ''
			),
			'notify_warning_extra' => array(
				'friendly_name' => 'Warning Emails',
				'method' => 'textarea',
				'textarea_rows' => 3,
				'textarea_cols' => 50,
				'description' => 'You may specify here extra Emails to receive warnings for this data source (comma separated)',
				'value' => isset($thold_item_data['notify_warning_extra']) ? $thold_item_data['notify_warning_extra'] : ''
			)
		);

		$form_array += $extra;
	} else {
		$extra = array(
			'notify_accounts' => array(
				'method' => 'hidden',
				'value' => 'ignore',
			),
			'notify_extra' => array(
				'method' => 'hidden',
				'value' => isset($thold_item_data['notify_extra']) ? $thold_item_data['notify_extra'] : ''
			),
			'notify_warning_extra' => array(
				'method' => 'hidden',
				'value' => isset($thold_item_data['notify_warning_extra']) ? $thold_item_data['notify_warning_extra'] : ''
			)
		);

		$form_array += $extra;
	}

	draw_edit_form(
		array(
			'config' => array(
				'no_form_tag' => true
				),
			'fields' => $form_array
			)
	);

	form_hidden_box("save", "edit", "");
	form_hidden_box("id", $id, "");

	html_end_box();

	form_save_button('thold_templates.php?id=' . $id, 'save');

	?>

	<script type='text/javascript'>
	function changeTholdType() {
		type = document.getElementById('thold_type').value;
		switch(type) {
		case '0': // Hi/Low
			thold_toggle_hilow('');
			thold_toggle_baseline('none');
			thold_toggle_time('none');
			break;
		case '1': // Baseline
			thold_toggle_hilow('none');
			thold_toggle_baseline('');
			thold_toggle_time('none');
			break;
		case '2': // Time Based
			thold_toggle_hilow('none');
			thold_toggle_baseline('none');
			thold_toggle_time('');
			break;
		}
	}

	function changeDataType() {
		type = $('#data_type').val();
		switch(type) {
		case '0':
			$('#row_cdef, #row_percent_ds, #row_expression').hide();

			break;
		case '1':
			$('#row_cdef').show();
			$('#row_percent_ds, #row_expression').hide();

			break;
		case '2':
			$('#row_cdef').hide();
			$('#row_percent_ds, #row_expression').show();

			break;
		case '3':
			$('#row_cdef').hide();
			$('#row_percent_ds').hide();
			$('#row_expression').show();
			break;
		}
	}

	function thold_toggle_hilow(status) {
		if (status == '') {
			$('#row_thold_header, #row_thold_hi, #row_thold_low, #row_thold_fail_trigger').show();
			$('#row_thold_warning_header, #row_thold_warning_hi').show();
			$('#row_thold_warning_low, #row_thold_warning_fail_trigger').show();
		}else{
			$('#row_thold_header, #row_thold_hi, #row_thold_low, #row_thold_fail_trigger').hide();
			$('#row_thold_warning_header, #row_thold_warning_hi').hide();
			$('#row_thold_warning_low, #row_thold_warning_fail_trigger').hide();
		}
	}

	function thold_toggle_baseline(status) {
		if (status == '') {
			$('#row_baseline_header, #row_bl_ref_time_range').show();
			$('#row_bl_pct_up, #row_bl_pct_down, #row_bl_fail_trigger').show();
		}else{
			$('#row_baseline_header, #row_bl_ref_time_range').hide();
			$('#row_bl_pct_up, #row_bl_pct_down, #row_bl_fail_trigger').hide();
		}
	}

	function thold_toggle_time(status) {
		if (status == '') {
			$('#row_time_header, #row_time_hi, #row_time_low').show();
			$('#row_time_fail_trigger, #row_time_fail_length, #row_time_warning_header').show();
			$('#row_time_warning_hi, #row_time_warning_low').show();
			$('#row_time_warning_fail_trigger, #row_time_warning_fail_length').show();
		}else{
			$('#row_time_header, #row_time_hi, #row_time_low').hide();
			$('#row_time_fail_trigger, #row_time_fail_length, #row_time_warning_header').hide();
			$('#row_time_warning_hi, #row_time_warning_low').hide();
			$('#row_time_warning_fail_trigger, #row_time_warning_fail_length').hide();
		}
	}

	changeTholdType ();
	changeDataType ();

	if (document.THold["notify_accounts[]"] && document.THold["notify_accounts[]"].length == 0) {
		document.getElementById('row_notify_accounts').style.display='none';
	}

	if (document.THold.notify_warning.length == 1) {
		document.getElementById('row_notify_warning').style.display='none';
	}

	if (document.THold.notify_alert.length == 1) {
		document.getElementById('row_notify_alert').style.display='none';
	}

	</script>
	<?php

}

function template_request_validation() {
    /* ================= input validation and session storage ================= */
    $filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'associated' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'true',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_tt');
	/* ================= input validation ================= */
}

function templates() {
	global $thold_actions, $item_rows;

	template_request_validation();

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	html_start_box('Threshold Templates', '100%', '', '3', 'center', 'thold_templates.php?action=add');

	?>
	<tr class='even'>
		<td>
			<form id='listthold' action='thold_templates.php'>
			<table class='filterTable'>
				<tr>
					<td>
						Search
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print get_request_var('filter');?>'>
					</td>
					<td>
						Rows
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows)) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						<input id='refresh' type='button' value='Go' onClick='applyFilter()'>
					</td>
					<td>
						<input id='clear' type='button' value='Clear' onClick='clearFilter()'>
					</td>
					<td>
						<input id='import' type='button' value='Import' onClick='importTemplate()'>
					</td>
				</tr>
			</table>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = 'thold_templates.php?header=false&rows=' + $('#rows').val();
				strURL += '&filter=' + $('#filter').val();
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL  = 'thold_templates.php?header=false&clear=1';
				loadPageNoHeader(strURL);
			}

			function importTemplate() {
				strURL = 'thold_templates.php?header=false&action=import';
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#listthold').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});
			});

			</script>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = '';

	$limit = ' LIMIT ' . ($rows * (get_request_var('page')-1)) . ',' . $rows;
	$order = 'ORDER BY ' . get_request_var('sort_column') . ' ' . get_request_var('sort_direction');

	if (strlen(get_request_var('filter'))) {
		$sql_where .= (strlen($sql_where) ? ' AND': 'WHERE') . " thold_template.name LIKE '%" . get_request_var('filter') . "%'";
	}

	$total_rows    = db_fetch_cell('SELECT count(*) FROM thold_template');
	$template_list = db_fetch_assoc("SELECT * FROM thold_template $sql_where $order $limit");

	form_start('thold_templates.php', 'chk');

	html_start_box('', '100%', '', '3', 'center', '');

	$nav = html_nav_bar('thold_templates.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 10, 'Templates', 'page', 'main');

	print $nav;

	$display_text = array(
		'name'               => array('Name', 'ASC'),
		'data_template_name' => array('Data Template', 'ASC'),
		'data_source_name'   => array('DS Name', 'ASC'),
		'thold_type'         => array('Type', 'ASC'),
		'nosort1'            => array('High/Up', ''),
		'nosort2'            => array('Low/Down', ''),
		'nosort3'            => array('Trigger', ''),
		'nosort4'            => array('Duration', ''),
		'nosort5'            => array('Repeat', '')
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;
	$types = array('High/Low', 'Baseline Deviation', 'Time Based');
	if (sizeof($template_list)) {
		foreach ($template_list as $template) {
			switch ($template['thold_type']) {
			case 0:					# hi/lo
				$value_hi               = thold_format_number($template['thold_hi']);
				$value_lo               = thold_format_number($template['thold_low']);
				$value_trig             = $template['thold_fail_trigger'];
				$value_duration         = '';
				$value_warning_hi       = thold_format_number($template['thold_warning_hi']);
				$value_warning_lo       = thold_format_number($template['thold_warning_low']);
				$value_warning_trig     = $template['thold_warning_fail_trigger'];
				$value_warning_duration = '';

				break;
			case 1:					# baseline
				$value_hi   = $template['bl_pct_up'] . (strlen($template['bl_pct_up']) ? '%':'-');
				$value_lo   = $template['bl_pct_down'] . (strlen($template['bl_pct_down']) ? '%':'-');
				$value_trig = $template['bl_fail_trigger'];

				$step = db_fetch_cell("SELECT rrd_step
					FROM data_template_data
					WHERE data_template_id=" . $template['data_template_id'] . "
					LIMIT 1");

				$value_duration = $template['bl_ref_time_range'] / $step;;

				break;
			case 2:					#time
				$value_hi       = thold_format_number($template['time_hi']);
				$value_lo       = thold_format_number($template['time_low']);
				$value_trig     = $template['time_fail_trigger'];
				$value_duration = $template['time_fail_length'];

				break;
			}

			$name = ($template['name'] == '' ? $template['data_template_name'] . ' [' . $template['data_source_name'] . ']' : $template['name']);
			$name = filter_value($name, get_request_var('filter'));

			form_alternate_row('line' . $template['id']);
			form_selectable_cell('<a class="linkEditMain" href="' . htmlspecialchars('thold_templates.php?action=edit&id=' . $template['id']) . '">' . $name  . '</a>', $template['id']);
			form_selectable_cell(filter_value($template['data_template_name'], get_request_var('filter')), $template['id']);
			form_selectable_cell($template['data_source_name'], $template['id']);
			form_selectable_cell($types[$template['thold_type']], $template['id']);
			form_selectable_cell($value_hi, $template['id']);
			form_selectable_cell($value_lo, $template['id']);

			$trigger =  plugin_thold_duration_convert($template['data_template_id'], $value_trig, 'alert', 'data_template_id');
			form_selectable_cell((strlen($trigger) ? '<i>' . $trigger . '</i>':'-'), $template['id']);

			$duration = plugin_thold_duration_convert($template['data_template_id'], $value_duration, 'time', 'data_template_id');
			form_selectable_cell((strlen($duration) ? $duration:'-'), $template['id']);
			form_selectable_cell(plugin_thold_duration_convert($template['data_template_id'], $template['repeat_alert'], 'repeat', 'data_template_id'), $template['id']);
			form_checkbox_cell($template['data_template_name'], $template['id']);
			form_end_row();
		}

		print $nav;
	} else {
		print "<tr><td><em>No Threshold Templates</em></td></tr>\n";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($thold_actions);

	form_end();
}

function import() {
	$form_data = array(
		'import_file' => array(
			'friendly_name' => 'Import Template from Local File',
			'description' => 'If the XML file containing Threshold Template data is located on your local
				machine, select it here.',
			'method' => 'file'
		),
		'import_text' => array(
			'method' => 'textarea',
			'friendly_name' => 'Import Template from Text',
			'description' => 'If you have the XML file containing Threshold Template data as text, you can paste
				it into this box to import it.',
			'value' => '',
			'default' => '',
			'textarea_rows' => '10',
			'textarea_cols' => '80',
			'class' => 'textAreaNotes'
		)
	);

	?>
	<form method='post' action='thold_templates.php' enctype='multipart/form-data'>
	<?php

	if ((isset($_SESSION['import_debug_info'])) && (is_array($_SESSION['import_debug_info']))) {
		html_start_box('Import Results', '100%', '', '3', 'center', '');

		print '<tr><td>Cacti has imported the following items:</td></tr>';
		foreach($_SESSION['import_debug_info'] as $line) {
			print '<tr><td>' . $line . '</td></tr>';
		}

		html_end_box();

		kill_session_var('import_debug_info');
	}

	html_start_box('Import Threshold Templates', '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => $form_data
		));

	html_end_box();
	form_hidden_box('save_component_import','1','');
	form_save_button('', 'import');
}

function template_import() {
	include_once('./lib/xml.php');

	if (trim(get_nfilter_request_var('import_text') != '')) {
		/* textbox input */
		$xml_data = get_nfilter_request_var('import_text');
	}elseif (($_FILES['import_file']['tmp_name'] != 'none') && ($_FILES['import_file']['tmp_name'] != '')) {
		/* file upload */
		$fp = fopen($_FILES['import_file']['tmp_name'],'r');
		$xml_data = fread($fp,filesize($_FILES['import_file']['tmp_name']));
		fclose($fp);
	}else{
		header('Location: thold_templates.php'); exit;
	}

	/* obtain debug information if it's set */
	$xml_array = xml2array($xml_data);

	$debug_data = array();

	if (sizeof($xml_array)) {
	foreach($xml_array as $template => $contents) {
		$error = false;
		$save  = array();
		if (sizeof($contents)) {
		foreach($contents as $name => $value) {
			$value = htmlentities($value);
			switch($name) {
			case 'data_template_id':
				// See if the hash exists, if it doesn't, Error Out
				$found = db_fetch_cell("SELECT id FROM data_template WHERE hash='$value'");

				if (!empty($found)) {
					$save['data_template_id'] = $found;
				}else{
					$error = true;
					$debug_data[] = "<span style='font-weight:bold;color:red;'>ERROR:</span> Threshold Template Subordinate Data Template Not Found!";
				}

				break;
			case 'data_source_id':
				// See if the hash exists, if it doesn't, Error Out
				$found = db_fetch_cell("SELECT id FROM data_template_rrd WHERE hash='$value'");

				if (!empty($found)) {
					$save['data_source_id'] = $found;
				}else{
					$error = true;
					$debug_data[] = "<span style='font-weight:bold;color:red;'>ERROR:</span> Threshold Template Subordinate Data Source Not Found!";
				}

				break;
			case 'hash':
				// See if the hash exists, if it does, update the thold
				$found = db_fetch_cell("SELECT id FROM thold_template WHERE hash='$value'");

				if (!empty($found)) {
					$save['hash'] = $value;
					$save['id']   = $found;
				}else{
					$save['hash'] = $value;
					$save['id']   = 0;
				}

				break;
			case 'name':
				$tname = $value;
				$save['name'] = $value;

				break;
			default:
				$save[$name] = $value;

				break;
			}
		}
		}

		if (!$error) {
			$id = sql_save($save, 'thold_template');

			if ($id) {
				$debug_data[] = "<span style='font-weight:bold;color:green;'>NOTE:</span> Threshold Template '<b>$tname</b>' " . ($save['id'] > 0 ? 'Updated':'Imported') . '!';
			}else{
				$debug_data[] = "<span style='font-weight:bold;color:red;'>ERROR:</span> Threshold Template '<b>$tname</b>' " . ($save['id'] > 0 ? 'Update':'Import') . ' Failed!';
			}
		}
	}
	}

	if(sizeof($debug_data) > 0) {
		$_SESSION['import_debug_info'] = $debug_data;
	}

	header('Location: thold_templates.php?action=import');
}

