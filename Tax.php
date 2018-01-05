<?php
class Tax {
	public $settings = array(
		'name' => 'Tax',
		'admin_menu_category' => 'Settings',
		'admin_menu_name' => 'Tax Groups',
		'admin_menu_icon' => '<i class="icon-legal"></i>',
		'description' => 'Configure the tax rules for invoicing.',
	);
	function admin_area() {
		global $billic, $db;
		if (isset($_GET['Name'])) {
			$group = $db->q('SELECT * FROM `tax_groups` WHERE `name` = ?', urldecode($_GET['Name']));
			$group = $group[0];
			if (empty($group)) {
				err('Tax Group does not exist');
			}
			echo '<h1>Tax Group ' . safe($group['name']) . '</h1>';
			if (isset($_POST['update'])) {
				if (empty($_POST['name'])) {
					$billic->error('Name can not be empty', 'name');
				} else if (strlen($_POST['name']) > 50) {
					$billic->error('Name is too long. Max 50 chars', 'name');
				} else {
					$group_name_check = $db->q('SELECT COUNT(*) FROM `tax_groups` WHERE `name` = ? AND `id` != ?', $_POST['name'], $group['id']);
					if ($group_name_check[0]['COUNT(*)'] > 0) {
						$billic->error('Name is already in use by a different group', 'name');
					}
				}
				if (empty($billic->errors)) {
					if ($_POST['name'] != $group['name']) {
						$db->q('UPDATE `tax_groups` SET `name` = ? WHERE `id` = ?', $_POST['name'], $group['id']);
						$db->q('UPDATE `tax_rules` SET `group` = ? WHERE `group` = ?', $_POST['name'], $group['name']);
						$db->q('UPDATE `plans` SET `tax_group` = ? WHERE `tax_group` = ?', $_POST['name'], $group['name']);
						$db->q('UPDATE `services` SET `tax_group` = ? WHERE `tax_group` = ?', $_POST['name'], $group['name']);
						// update tax group name in exported plans
						$exported_plans = $db->q('SELECT * FROM `exported_plans`');
						foreach ($exported_plans as $export) {
							$data = json_decode($export['data'], true);
							if ($data['tax_group'] == $group['name']) {
								$data['tax_group'] = $_POST['name'];
								$db->q('UPDATE `exported_plans` SET `data` = ? WHERE `hash` = ?', json_encode($data) , $export['hash']);
							}
						}
					}
					$billic->redirect('/Admin/Tax/Name/' . urlencode($_POST['name']) . '/');
				}
			}
			$billic->show_errors();
			if (isset($_GET['Rule'])) {
				echo '<a href="/Admin/Tax/Name/' . urlencode($group['name']) . '/">&laquo; Go back to Tax Group page</a><br><br>';
				if (isset($_POST['update_rule'])) {
					$db->q('UPDATE `tax_rules` SET `country` = ?, `rate` = ?, `allow_eu_zero` = ? WHERE `id` = ?', $_POST['country'], $_POST['rate'], $_POST['allow_eu_zero'], $_GET['Rule']);
					$billic->status = 'updated';
				}
				$rule = $db->q('SELECT * FROM `tax_rules` WHERE `id` = ?', $_GET['Rule']);
				$rule = $rule[0];
				if (empty($rule)) {
					die('Rule does not exist');
				}
				if (!isset($_POST['country'])) {
					$_POST['country'] = $rule['country'];
				}
				if (!isset($_POST['rate'])) {
					$_POST['rate'] = $rule['rate'];
				}
				if (!isset($_POST['allow_eu_zero'])) {
					$_POST['allow_eu_zero'] = $rule['allow_eu_zero'];
				}
				$billic->show_errors();
				echo '<form method="POST"><table class="table table-striped"><tr><th colspan="2">Edit Rule</th></td></tr>';
				echo '<tr><td' . $billic->highlight('country') . '>Country:</td><td><select class="form-control" name="country">';
				foreach ($billic->countries as $key => $country) {
					echo '<option value="' . $key . '"' . ($key == $_POST['country'] ? ' selected="1"' : '') . '>' . $country . '</option>';
				}
				echo '</select></td></tr>';
				echo '<tr><td>Tax Rate</td><td><div class="input-group" style="width: 150px"><input type="text" class="form-control" name="rate" value="' . $_POST['rate'] . '"><span class="input-group-addon">Percent</span></div></td></tr>';
				echo '<tr><td>Allow 0%</td><td><input type="checkbox" name="allow_eu_zero" value="1"' . ($_POST['allow_eu_zero'] == 1 ? ' checked' : '') . '></td></tr>';
				echo '<tr><td colspan="4" align="center"><input type="submit" class="btn btn-success" name="update_rule" value="Update &raquo;"></td></tr></table></form>';
				exit;
			}
			if (isset($_GET['NewRule'])) {
				$id = $db->insert('tax_rules', array(
					'group' => $group['name'],
				));
				$billic->redirect('/Admin/Tax/Name/' . urlencode($group['name']) . '/Rule/' . $id . '/');
			}
			//$applicable_countries = explode(',', $group['countries']);
			echo '<form method="POST"><table class="table table-striped"><tr><th colspan="2">Group Settings</th></td></tr>';
			echo '<tr><td width="125">Name</td><td><input type="text" class="form-control" name="name" value="' . $group['name'] . '"></td></tr>';
			echo '<tr><td colspan="4" align="center"><input type="submit" class="btn btn-success" name="update" value="Update &raquo;"></td></tr></table></form>';
			echo '<br><h1>Rules</h1>';
			echo '<a href="NewRule/" class="btn btn-success"><i class="icon-plus"></i> New Tax Rule</a>';
			echo '<form method="POST"><table class="table table-striped"><tr><th colspan="2">Country</th><th>Rate</th><th>Allow 0%</th><th>Actions</th></td></tr>';
			$rules = $db->q('SELECT * FROM `tax_rules` WHERE `group` = ? ORDER BY `country` ASC', $group['name']);
			if (empty($rules)) {
				echo '<tr><td colspan="10">No rules for this group</td></tr>';
			}
			foreach ($rules as $rule) {
				echo '<tr><td><input type="checkbox" name="rule[' . $rule['id'] . ']"> ' . $rule['country'] . '</td><td>' . $billic->countries[$rule['country']] . '</td><td>' . $rule['rate'] . '%</td><td>' . $rule['allow_eu_zero'] . '</td><td><a href="/Admin/Tax/Name/' . urlencode($group['name']) . '/Rule/' . $rule['id'] . '" class="btn btn-primary btn-xs"><i class="icon-edit-write"></i> Edit</a></td></tr>';
			}
			echo '</table><br><input type="submit" class="btn btn-success" name="delete" value="Delete Selected Rules"></form>';
			return;
		}
		if (isset($_GET['New'])) {
			$title = 'New Group';
			$billic->set_title($title);
			echo '<h1>' . $title . '</h1>';
			$billic->module('FormBuilder');
			$form = array(
				'name' => array(
					'label' => 'Name',
					'type' => 'text',
					'required' => true,
					'default' => '',
				) ,
			);
			if (isset($_POST['Continue'])) {
				$billic->modules['FormBuilder']->check_everything(array(
					'form' => $form,
				));
				if (empty($billic->errors)) {
					$db->insert('tax_groups', array(
						'name' => $_POST['name'],
					));
					$billic->redirect('/Admin/Tax/Name/' . urlencode($_POST['name']) . '/');
				}
			}
			$billic->show_errors();
			$billic->modules['FormBuilder']->output(array(
				'form' => $form,
				'button' => 'Continue',
			));
			return;
		}
		$total = $db->q('SELECT COUNT(*) FROM `tax_groups`');
		$total = $total[0]['COUNT(*)'];
		$pagination = $billic->pagination(array(
			'total' => $total,
		));
		echo $pagination['menu'];
		$groups = $db->q('SELECT * FROM `tax_groups` ORDER BY `name` ASC LIMIT ' . $pagination['start'] . ',' . $pagination['limit']);
		$billic->set_title('Admin/Tax Groups');
		echo '<h1><i class="icon-legal"></i> Tax Groups</h1>';
		echo '<a href="New/" class="btn btn-success"><i class="icon-plus"></i> New Group</a>';
		$billic->show_errors();
		echo '<div style="float: right;padding-right: 40px;">Showing ' . $pagination['start_text'] . ' to ' . $pagination['end_text'] . ' of ' . $total . ' Tax Groups</div>';
		echo '<table class="table table-striped"><tr><th>Name</th><th>Rules</th></tr>';
		if (empty($groups)) {
			echo '<tr><td colspan="20">No groups matching filter.</td></tr>';
		}
		foreach ($groups as $group) {
			$rule_count = $db->q('SELECT COUNT(*) FROM `tax_rules` WHERE `group` = ?', $group['name']);
			$rule_count = $rule_count[0]['COUNT(*)'];
			echo '<tr><td><a href="/Admin/Tax/Name/' . urlencode($group['name']) . '/">' . safe($group['name']) . '</a></td><td>' . $rule_count . '</td></tr>';
		}
		echo '</table>';
	}
}
