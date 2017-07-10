<?php
if(IN_MANAGER_MODE != "true") {
	die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODX Content Manager instead of accessing this file directly.");
}
if(!$modx->hasPermission('bk_manager')) {
	$modx->webAlertAndQuit($_lang["error_no_privileges"]);
}

$dbase = trim($dbase, '`');

if(!isset($modx->config['snapshot_path'])) {
	if(is_dir(MODX_BASE_PATH . 'temp/backup/')) {
		$modx->config['snapshot_path'] = MODX_BASE_PATH . 'temp/backup/';
	} else {
		$modx->config['snapshot_path'] = MODX_BASE_PATH . 'assets/backup/';
	}
}

// Backup Manager by Raymond:

$mode = isset($_POST['mode']) ? $_POST['mode'] : '';

if($mode == 'restore1') {
	if(isset($_POST['textarea']) && !empty($_POST['textarea'])) {
		$source = trim($_POST['textarea']);
		$_SESSION['textarea'] = $source . "\n";
	} else {
		$source = file_get_contents($_FILES['sqlfile']['tmp_name']);
	}
	import_sql($source);
	header('Location: index.php?r=9&a=93');
	exit;
} elseif($mode == 'restore2') {
	$path = $modx->config['snapshot_path'] . $_POST['filename'];
	if(file_exists($path)) {
		$source = file_get_contents($path);
		import_sql($source);
		if(headers_sent()) {
			echo "<script>document.location.href='index.php?r=9&a=93';</script>\n";
		} else {
			header("Location: index.php?r=9&a=93");
		}
	}
	exit;
} elseif($mode == 'backup') {
	$tables = isset($_POST['chk']) ? $_POST['chk'] : '';
	if(!is_array($tables)) {
		$modx->webAlertAndQuit("Please select a valid table from the list below.");
	}

	/*
	 * Code taken from Ralph A. Dahlgren MySQLdumper Snippet - Etomite 0.6 - 2004-09-27
	 * Modified by Raymond 3-Jan-2005
	 * Perform MySQLdumper data dump
	 */
	@set_time_limit(120); // set timeout limit to 2 minutes
	$dumper = new Mysqldumper($database_server, $database_user, $database_password, $dbase);
	$dumper->setDBtables($tables);
	$dumper->setDroptables((isset($_POST['droptables']) ? true : false));
	$dumpfinished = $dumper->createDump('dumpSql');
	if($dumpfinished) {
		exit;
	} else {
		$modx->webAlertAndQuit('Unable to Backup Database');
	}

	// MySQLdumper class can be found below
} elseif($mode == 'snapshot') {
	if(!is_dir(rtrim($modx->config['snapshot_path'], '/'))) {
		mkdir(rtrim($modx->config['snapshot_path'], '/'));
		@chmod(rtrim($modx->config['snapshot_path'], '/'), 0777);
	}
	if(!is_file("{$modx->config['snapshot_path']}.htaccess")) {
		$htaccess = "order deny,allow\ndeny from all\n";
		file_put_contents("{$modx->config['snapshot_path']}.htaccess", $htaccess);
	}
	if(!is_writable(rtrim($modx->config['snapshot_path'], '/'))) {
		$modx->webAlertAndQuit(parsePlaceholder($_lang["bkmgr_alert_mkdir"], array('snapshot_path' => $modx->config['snapshot_path'])));
	}
	$sql = "SHOW TABLE STATUS FROM `{$dbase}` LIKE '" . $modx->db->escape($modx->db->config['table_prefix']) . "%'";
	$rs = $modx->db->query($sql);
	$tables = $modx->db->getColumn('Name', $rs);
	//$today = $modx->toDateFormat(time());
	//$today = str_replace(array('/',' '), '-', $today);
	//$today = str_replace(':', '', $today);
	//$today = strtolower($today);
	$today = date('Y-m-d_H-i-s');
	global $path;
	$path = "{$modx->config['snapshot_path']}{$today}.sql";

	@set_time_limit(120); // set timeout limit to 2 minutes
	$dumper = new Mysqldumper($database_server, $database_user, $database_password, $dbase);
	$dumper->setDBtables($tables);
	$dumper->setDroptables(true);
	$dumpfinished = $dumper->createDump('snapshot');

	$pattern = "{$modx->config['snapshot_path']}*.sql";
	$files = glob($pattern, GLOB_NOCHECK);
	$total = ($files[0] !== $pattern) ? count($files) : 0;
	arsort($files);
	while(10 < $total && $limit < 50) {
		$del_file = array_pop($files);
		unlink($del_file);
		$total = count($files);
		$limit++;
	}

	if($dumpfinished) {
		$_SESSION['result_msg'] = 'snapshot_ok';
		header("Location: index.php?a=93");
		exit;
	} else {
		$modx->webAlertAndQuit('Unable to Backup Database');
	}
} else {
	include_once MODX_MANAGER_PATH . 'media/style/' . $modx->config['manager_theme'] . "/includes/header.inc.php";  // start normal header
}

if(isset($_SESSION['result_msg']) && $_SESSION['result_msg'] != '') {
	switch($_SESSION['result_msg']) {
		case 'import_ok':
			$ph['result_msg_import'] = '<div class="msg">' . $_lang["bkmgr_import_ok"] . '</div>';
			$ph['result_msg_snapshot'] = '<div class="msg">' . $_lang["bkmgr_import_ok"] . '</div>';
			break;
		case 'snapshot_ok':
			$ph['result_msg_import'] = '';
			$ph['result_msg_snapshot'] = '<div class="msg">' . $_lang["bkmgr_snapshot_ok"] . '</div>';
			break;
	}
	$_SESSION['result_msg'] = '';
} else {
	$ph['result_msg_import'] = '';
	$ph['result_msg_snapshot'] = '';
}

?>

	<script language="javascript">
		function selectAll() {
			var f = document.forms['frmdb'];
			var c = f.elements['chk[]'];
			for(var i = 0; i < c.length; i++) {
				c[i].checked = f.chkselall.checked;
			}
		}

		function backup() {
			var f = document.forms['frmdb'];
			f.mode.value = 'backup';
			f.target = 'fileDownloader';
			f.submit();
			return false;
		}

		function confirmRevert(filename) {
			var m = '<?php echo $_lang["bkmgr_restore_confirm"] ?>';
			m = m.replace('[+filename+]', filename);
			var c = confirm(m);
			if(c) {
				document.restore2.filename.value = filename;
				document.restore2.save.click();
			}
		}
		<?php echo isset($_REQUEST['r']) ? " doRefresh(" . $_REQUEST['r'] . ");" : "";?>

	</script>

	<h1>
		<i class="fa fa-database"></i><?php echo $_lang['bk_manager']; ?>
	</h1>

	<div id="actions">
		<ul class="actionButtons">
			<li id="Button5" class="transition"><a href="javascript:;" onclick="documentDirty=false;document.location.href='index.php?a=2';"><i class="<?php echo $_style["actions_cancel"] ?>"></i> <span><?php echo $_lang['cancel'] ?></span></a></li>
		</ul>
	</div>

	<div class="sectionBody" id="lyr4">
		<div class="tab-pane" id="dbmPane">
			<script type="text/javascript">
				tpDBM = new WebFXTabPane(document.getElementById('dbmPane'));
			</script>

			<div class="tab-page" id="tabBackup">
				<h2 class="tab"><?php echo $_lang['backup'] ?></h2>
				<script type="text/javascript">tpDBM.addTabPage(document.getElementById('tabBackup'));</script>
				<form name="frmdb" method="post">
					<input type="hidden" name="mode" value="" />
					<p class="element-edit-message"><?php echo $_lang['table_hoverinfo'] ?></p>
					<p>
						<a href="javascript:;" class="btn btn-primary" onclick="backup();return false;"> <i class="<?php echo $_style['actions_save']; ?>"></i> <?php echo $_lang['database_table_clickbackup'] ?></a>
						<label><input type="checkbox" name="droptables" checked="checked" /><?php echo $_lang['database_table_droptablestatements'] ?></label>
					</p>
					<table class="grid">
						<thead>
						<tr>
							<td style="width: 17rem"><label><input type="checkbox" name="chkselall" onclick="selectAll()" title="Select All Tables" /><b><?php echo $_lang['database_table_tablename'] ?></b></label></td>
							<td><b><?php echo $_lang['database_table_records'] ?></b></td>
							<td><b><?php echo $_lang['database_collation'] ?></b></td>
							<td><b><?php echo $_lang['database_table_datasize'] ?></b></td>
							<td><b><?php echo $_lang['database_table_overhead'] ?></b></td>
							<td><b><?php echo $_lang['database_table_effectivesize'] ?></b></td>
							<td><b><?php echo $_lang['database_table_indexsize'] ?></b></td>
							<td><b><?php echo $_lang['database_table_totalsize'] ?></b></td>
						</tr>
						</thead>
						<tbody>
						<?php
						$sql = "SHOW TABLE STATUS FROM `{$dbase}` LIKE '" . $modx->db->escape($modx->db->config['table_prefix']) . "%'";
						$rs = $modx->db->query($sql);
						$i = 0;
						while($db_status = $modx->db->getRow($rs)) {
							//$bgcolor = ($i++ % 2) ? '#EEEEEE' : '#FFFFFF';

							if(isset($tables)) {
								$table_string = implode(',', $table);
							} else {
								$table_string = '';
							}

							echo '<tr bgcolor="' . $bgcolor . '" title="' . $db_status['Comment'] . '" style="cursor:default">' . "\n" . '<td><label><input type="checkbox" name="chk[]" value="' . $db_status['Name'] . '"' . (strstr($table_string, $db_status['Name']) === false ? '' : ' checked="checked"') . ' /><b class="text-success">' . $db_status['Name'] . '</b></label></td>' . "\n" . '<td align="right">' . $db_status['Rows'] . '</td>' . "\n";
							echo '<td align="right">' . $db_status['Collation'] . '</td>' . "\n";

							// Enable record deletion for certain tables (TRUNCATE TABLE) if they're not already empty
							$truncateable = array(
								$modx->db->config['table_prefix'] . 'event_log',
								$modx->db->config['table_prefix'] . 'manager_log',
							);
							if($modx->hasPermission('settings') && in_array($db_status['Name'], $truncateable) && $db_status['Rows'] > 0) {
								echo '<td dir="ltr" align="right">' . '<a href="index.php?a=54&mode=' . $action . '&u=' . $db_status['Name'] . '" title="' . $_lang['truncate_table'] . '">' . $modx->nicesize($db_status['Data_length'] + $db_status['Data_free']) . '</a>' . '</td>' . "\n";
							} else {
								echo '<td dir="ltr" align="right">' . $modx->nicesize($db_status['Data_length'] + $db_status['Data_free']) . '</td>' . "\n";
							}

							if($modx->hasPermission('settings')) {
								echo '<td align="right">' . ($db_status['Data_free'] > 0 ? '<a href="index.php?a=54&mode=' . $action . '&t=' . $db_status['Name'] . '" title="' . $_lang['optimize_table'] . '">' . $modx->nicesize($db_status['Data_free']) . '</a>' : '-') . '</td>' . "\n";
							} else {
								echo '<td align="right">' . ($db_status['Data_free'] > 0 ? $modx->nicesize($db_status['Data_free']) : '-') . '</td>' . "\n";
							}

							echo '<td dir="ltr" align="right">' . $modx->nicesize($db_status['Data_length'] - $db_status['Data_free']) . '</td>' . "\n" . '<td dir="ltr" align="right">' . $modx->nicesize($db_status['Index_length']) . '</td>' . "\n" . '<td dir="ltr" align="right">' . $modx->nicesize($db_status['Index_length'] + $db_status['Data_length'] + $db_status['Data_free']) . '</td>' . "\n" . "</tr>";

							$total = $total + $db_status['Index_length'] + $db_status['Data_length'];
							$totaloverhead = $totaloverhead + $db_status['Data_free'];
						}
						?>
						</tbody>
						<tfoot>
						<tr>
							<td><b><?php echo $_lang['database_table_totals'] ?></b></td>
							<td colspan="3">&nbsp;</td>
							<td><?php echo $totaloverhead > 0 ? '<b class="text-danger">' . $modx->nicesize($totaloverhead) . '</b><br />(' . number_format($totaloverhead) . ' B)' : '-' ?></td>
							<td colspan="2">&nbsp;</td>
							<td><?php echo "<b>" . $modx->nicesize($total) . "</b><br />(" . number_format($total) . " B)" ?></td>
						</tr>
						</tfoot>
					</table>
					<?php
					if($totaloverhead > 0) {
						echo '<p>' . $_lang['database_overhead'] . '</p>';
					}
					?>
				</form>
			</div>
			<!-- This iframe is used when downloading file backup file -->
			<iframe name="fileDownloader" width="1" height="1" style="display:none; width:1px; height:1px;"></iframe>
			<div class="tab-page" id="tabRestore">
				<h2 class="tab"><?php echo $_lang["bkmgr_restore_title"]; ?></h2>
				<?php echo $ph['result_msg_import']; ?>
				<script type="text/javascript">tpDBM.addTabPage(document.getElementById('tabRestore'));</script>
				<div class="element-edit-message">
					<?php echo $_lang["bkmgr_restore_msg"]; ?>
				</div>
				<form method="post" name="mutate" enctype="multipart/form-data" action="index.php">
					<input type="hidden" name="a" value="93" />
					<input type="hidden" name="mode" value="restore1" />
					<script type="text/javascript">
						function showhide(a) {
							var f = document.getElementById('sqlfile');
							var t = document.getElementById('textarea');
							if(a == 'file') {
								f.style.display = 'block';
								t.style.display = 'none';
							}
							else {
								t.style.display = 'block';
								f.style.display = 'none';
							}
						}
					</script>
					<?php
					if(isset($_SESSION['textarea']) && !empty($_SESSION['textarea'])) {
						$value = $_SESSION['textarea'];
						unset($_SESSION['textarea']);
						$_SESSION['console_mode'] = 'text';
						$f_display = 'none';
						$t_display = 'block';
					} else {
						$value = '';
						$_SESSION['console_mode'] = 'file';
						$f_display = 'block';
						$t_display = 'none';
					}

					if(isset($_SESSION['last_result']) || !empty($_SESSION['last_result'])) {
						$last_result = $_SESSION['last_result'];
						unset($_SESSION['last_result']);
						if(count($last_result) < 1) {
							$result = '';
						} else {
							$last_result = array_merge(array(), array_diff($last_result, array('')));
							foreach($last_result['0'] as $k => $v) {
								$title[] = $k;
							}
							$result = '<tr><th>' . implode('</th><th>', $title) . '</th></tr>';
							foreach($last_result as $row) {
								$result_value = array();
								if($row) {
									foreach($row as $k => $v) {
										$result_value[] = $v;
									}
									$result .= '<tr><td>' . implode('</td><td>', $result_value) . '</td></tr>';
								}
							}
							$style = '<style type="text/css">table th {border:1px solid #ccc;background-color:#ddd;}</style>';
							$result = $style . '<table>' . $result . '</table>';
						}
					}

					function checked($cond) {
						if($cond) {
							return ' checked';
						}
					}

					?>
					<p>
						<label><input type="radio" name="sel" onclick="showhide('file');" <?php echo checked(!isset($_SESSION['console_mode']) || $_SESSION['console_mode'] !== 'text'); ?> /> <?php echo $_lang["bkmgr_run_sql_file_label"]; ?></label>
						<label><input type="radio" name="sel" onclick="showhide('textarea');" <?php echo checked(isset($_SESSION['console_mode']) && $_SESSION['console_mode'] === 'text'); ?> /> <?php echo $_lang["bkmgr_run_sql_direct_label"]; ?></label>
					</p>
					<div class="form-group"><input type="file" name="sqlfile" id="sqlfile" size="70" style="display:<?php echo $f_display; ?>;" /></div>
					<div id="textarea" style="display:<?php echo $t_display; ?>;">
						<textarea name="textarea" style="height:200px;"><?php echo $value; ?></textarea>
					</div>
					<a href="javascript:;" class="btn btn-primary" onclick="document.mutate.save.click();"> <i class="<?php echo $_style['actions_save'] ?>"></i> <?php echo $_lang["bkmgr_run_sql_submit"]; ?></a>
					<input type="submit" name="save" style="display:none;" />
				</form>
				<?php
				if(isset($result)) {
					echo '<div style="margin-top:20px;"><p style="font-weight:bold;"><?php echo $_lang["bkmgr_run_sql_result"];?></p>' . $result . '</div>';
				}
				?>
			</div>

			<div class="tab-page" id="tabSnapshot">
				<h2 class="tab"><?php echo $_lang["bkmgr_snapshot_title"]; ?></h2>
				<?php echo $ph['result_msg_snapshot']; ?>
				<script type="text/javascript">tpDBM.addTabPage(document.getElementById('tabSnapshot'));</script>
				<div class="element-edit-message">
					<?php echo parsePlaceholder($_lang["bkmgr_snapshot_msg"], array('snapshot_path' => "snapshot_path={$modx->config['snapshot_path']}")); ?>
				</div>
				<form method="post" name="snapshot" action="index.php">
					<input type="hidden" name="a" value="93" />
					<input type="hidden" name="mode" value="snapshot" />
					<?php echo $_lang["description"]; ?> <input type="text" name="backup_title" style="width: 350px; margin-bottom:1em;" maxlength="350" />
					<a href="javascript:;" class="btn btn-primary" style="display:inline-block;" onclick="document.snapshot.save.click();"> <i class="<?php echo $_style['actions_save'] ?>"></i> <?php echo $_lang["bkmgr_snapshot_submit"]; ?></a>
					<input type="submit" name="save" style="display:none;" />
				</form>
				<style type="text/css">
					table { background-color: #fff; border-collapse: collapse; }
					table td { border: 1px solid #ccc; padding: 4px; }
					.msg { background-color: #edffee; border: 2px solid #3ab63a; padding: 8px; margin-bottom: 8px; }
				</style>
				<div class="sectionHeader"><?php echo $_lang["bkmgr_snapshot_list_title"]; ?></div>
				<div class="sectionBody">
					<form method="post" name="restore2" action="index.php">
						<input type="hidden" name="a" value="93" />
						<input type="hidden" name="mode" value="restore2" />
						<input type="hidden" name="filename" value="" />
						<?php
						$pattern = "{$modx->config['snapshot_path']}*.sql";
						$files = glob($pattern, GLOB_NOCHECK);
						$total = ($files[0] !== $pattern) ? count($files) : 0;
						$detailFields = array(
							'MODX Version',
							'Host',
							'Generation Time',
							'Server version',
							'PHP Version',
							'Database',
							'Description'
						);
						if(is_array($files) && 0 < $total) {
							echo '<table class="grid">';
							echo "<thead><tr><th>{$_lang["files_filename"]}</th><th>{$_lang["files_filesize"]}</th><th>{$_lang["description"]}</th><th>{$_lang["modx_version"]}</th><th>{$_lang["database_name"]}</th><th>{$_lang["onlineusers_action"]}</th></tr></thead>\n";
							arsort($files);
							$tpl = '<tr><td>[+filename+]</td><td>[+filesize+]</td><td>[+filedesc+]</td><td>[+modx_version+]</td><td>[+database_name+]</td><td><a href="javascript:;" onclick="confirmRevert(\'[+filename+]\');" title="[+tooltip+]">' . $_lang["bkmgr_restore_submit"] . '</a></td></tr>' . "\n";
							while($file = array_shift($files)) {
								$filename = substr($file, strrpos($file, '/') + 1);
								$filesize = $modx->nicesize(filesize($file));

								$file = fopen($file, "r");
								$count = 0;
								$details = array();
								while($count < 11) {
									$line = fgets($file);
									foreach($detailFields as $label) {
										$fileLabel = '# ' . $label;
										if(strpos($line, $fileLabel) !== false) {
											$details[$label] = htmlentities(trim(str_replace(array(
												$fileLabel,
												':',
												'`'
											), '', $line)), ENT_QUOTES, $modx_manager_charset);
										}
									}
									$count++;
								};
								fclose($file);

								$tooltip = "Generation Time: " . $details["Generation Time"] . "\n";
								$tooltip .= "Server version: " . $details["Server version"] . "\n";
								$tooltip .= "PHP Version: " . $details["PHP Version"] . "\n";
								$tooltip .= "Host: " . $details["Host"] . "\n";

								echo str_replace(array(
									'[+filename+]',
									'[+filesize+]',
									'[+filedesc+]',
									'[+modx_version+]',
									'[+database_name+]',
									'[+tooltip+]'
								), array(
									$filename,
									$filesize,
									$details['Description'],
									$details['MODX Version'],
									$details['Database'],
									$tooltip
								), $tpl);
							}
							echo '</table>';
						} else {
							echo $_lang["bkmgr_snapshot_nothing"];
						}
						?>
						<input type="submit" name="save" style="display:none;" />
					</form>
				</div>
			</div>

		</div>

	</div>

<?php

if(is_numeric($_GET['tab'])) {
	echo '<script type="text/javascript">tpDBM.setSelectedIndex( ' . $_GET['tab'] . ' );</script>';
}

include_once "footer.inc.php"; // send footer
?>

<?php

/*
* @package  MySQLdumper
* @version  1.0
* @author   Dennis Mozes <opensource@mosix.nl>
* @url		http://www.mosix.nl/mysqldumper
* @since    PHP 4.0
* @copyright Dennis Mozes
* @license GNU/LGPL License: http://www.gnu.org/copyleft/lgpl.html
*
* Modified by Raymond for use with this module
*
**/

class Mysqldumper {
	var $_dbtables;
	var $_isDroptables;
	var $database_server;
	var $dbname;

	function __construct($database_server, $database_user, $database_password, $dbname) {
		// Don't drop tables by default.
		$this->dbname = $dbname;
		$this->setDroptables(false);
	}

	function setDroptables($state) {
		$this->_isDroptables = $state;
	}

	// If set to true, it will generate 'DROP TABLE IF EXISTS'-statements for each table.

	function setDBtables($dbtables) {
		$this->_dbtables = $dbtables;
	}

	function createDump($callBack) {
		global $modx;

		// Set line feed
		$lf = "\n";
		$tempfile_path = $modx->config['base_path'] . 'assets/backup/temp.php';

		$result = $modx->db->query('SHOW TABLES');
		$tables = $this->result2Array(0, $result);
		foreach($tables as $tblval) {
			$result = $modx->db->query("SHOW CREATE TABLE `{$tblval}`");
			$createtable[$tblval] = $this->result2Array(1, $result);
		}

		$version = $modx->getVersionData();

		// Set header
		$output = "#{$lf}";
		$output .= "# " . addslashes($modx->config['site_name']) . " Database Dump{$lf}";
		$output .= "# MODX Version:{$version['version']}{$lf}";
		$output .= "# {$lf}";
		$output .= "# Host: {$this->database_server}{$lf}";
		$output .= "# Generation Time: " . $modx->toDateFormat(time()) . $lf;
		$output .= "# Server version: " . $modx->db->getVersion() . $lf;
		$output .= "# PHP Version: " . phpversion() . $lf;
		$output .= "# Database: `{$this->dbname}`{$lf}";
		$output .= "# Description: " . trim($_REQUEST['backup_title']) . "{$lf}";
		$output .= "#";
		file_put_contents($tempfile_path, $output, FILE_APPEND | LOCK_EX);
		$output = '';

		// Generate dumptext for the tables.
		if(isset($this->_dbtables) && count($this->_dbtables)) {
			$this->_dbtables = implode(',', $this->_dbtables);
		} else {
			unset($this->_dbtables);
		}
		foreach($tables as $tblval) {
			// check for selected table
			if(isset($this->_dbtables)) {
				if(strstr(",{$this->_dbtables},", ",{$tblval},") === false) {
					continue;
				}
			}
			if($callBack === 'snapshot') {
				/*
				switch($tblval)
				{
					case $modx->db->config['table_prefix'].'event_log':
					case $modx->db->config['table_prefix'].'manager_log':
						continue 2;
				}*/
				if(!preg_match('@^' . $modx->db->config['table_prefix'] . '@', $tblval)) {
					continue;
				}
			}
			$output .= "{$lf}{$lf}# --------------------------------------------------------{$lf}{$lf}";
			$output .= "#{$lf}# Table structure for table `{$tblval}`{$lf}";
			$output .= "#{$lf}{$lf}";
			// Generate DROP TABLE statement when client wants it to.
			if($this->isDroptables()) {
				$output .= "SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;{$lf}";
				$output .= "DROP TABLE IF EXISTS `{$tblval}`;{$lf}";
				$output .= "SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;{$lf}{$lf}";
			}
			$output .= "{$createtable[$tblval][0]};{$lf}";
			$output .= $lf;
			$output .= "#{$lf}# Dumping data for table `{$tblval}`{$lf}#{$lf}";
			$result = $modx->db->select('*', $tblval);
			$rows = $this->loadObjectList('', $result);
			foreach($rows as $row) {
				$insertdump = $lf;
				$insertdump .= "INSERT INTO `{$tblval}` VALUES (";
				$arr = $this->object2Array($row);
				foreach($arr as $key => $value) {
					if(is_null($value)) {
						$value = 'NULL';
					} else {
						$value = addslashes($value);
						$value = str_replace(array(
							"\r\n",
							"\r",
							"\n"
						), '\\n', $value);
						$value = "'{$value}'";
					}
					$insertdump .= $value . ',';
				}
				$output .= rtrim($insertdump, ',') . ");\n";
				if(1048576 < strlen($output)) {
					file_put_contents($tempfile_path, $output, FILE_APPEND | LOCK_EX);
					$output = '';
				}
			}
			file_put_contents($tempfile_path, $output, FILE_APPEND | LOCK_EX);
			$output = '';
		}
		$output = file_get_contents($tempfile_path);
		if(!empty($output)) {
			unlink($tempfile_path);
		}

		switch($callBack) {
			case 'dumpSql':
				dumpSql($output);
				break;
			case 'snapshot':
				snapshot($output);
				break;
		}
		return true;
	}

	function result2Array($numinarray = 0, $resource) {
		global $modx;
		$array = array();
		while($row = $modx->db->getRow($resource, 'num')) {
			$array[] = $row[$numinarray];
		}
		$modx->db->freeResult($resource);
		return $array;
	}

	// Private function object2Array.

	function isDroptables() {
		return $this->_isDroptables;
	}

	// Private function loadObjectList.

	function loadObjectList($key = '', $resource) {
		global $modx;
		$array = array();
		while($row = $modx->db->getRow($resource, 'object')) {
			if($key) {
				$array[$row->$key] = $row;
			} else {
				$array[] = $row;
			}
		}
		$modx->db->freeResult($resource);
		return $array;
	}

	// Private function result2Array.

	function object2Array($obj) {
		$array = null;
		if(is_object($obj)) {
			$array = array();
			foreach(get_object_vars($obj) as $key => $value) {
				if(is_object($value)) {
					$array[$key] = $this->object2Array($value);
				} else {
					$array[$key] = $value;
				}
			}
		}
		return $array;
	}
}

function import_sql($source, $result_code = 'import_ok') {
	global $modx, $e;

	if($modx->getLockedElements() !== array()) {
		$modx->webAlertAndQuit("At least one Resource is still locked or edited right now by any user. Remove locks or ask users to log out before proceeding.");
	}

	$settings = getSettings();

	if(strpos($source, "\r") !== false) {
		$source = str_replace(array(
			"\r\n",
			"\n",
			"\r"
		), "\n", $source);
	}
	$sql_array = preg_split('@;[ \t]*\n@', $source);
	foreach($sql_array as $sql_entry) {
		$sql_entry = trim($sql_entry, "\r\n; ");
		if(empty($sql_entry)) {
			continue;
		}
		$rs = $modx->db->query($sql_entry);
	}
	restoreSettings($settings);

	$modx->clearCache();

	$_SESSION['last_result'] = $modx->db->makeArray($rs);

	$_SESSION['result_msg'] = $result_code;
}

function dumpSql(&$dumpstring) {
	global $modx;
	$today = $modx->toDateFormat(time(), 'dateOnly');
	$today = str_replace('/', '-', $today);
	$today = strtolower($today);
	$size = strlen($dumpstring);
	if(!headers_sent()) {
		header('Expires: 0');
		header('Cache-Control: private');
		header('Pragma: cache');
		header('Content-type: application/download');
		header("Content-Length: {$size}");
		header("Content-Disposition: attachment; filename={$today}_database_backup.sql");
	}
	echo $dumpstring;
	return true;
}

function snapshot(&$dumpstring) {
	global $path;
	file_put_contents($path, $dumpstring, FILE_APPEND);
	return true;
}

function getSettings() {
	global $modx;
	$tbl_system_settings = $modx->getFullTableName('system_settings');

	$rs = $modx->db->select('setting_name, setting_value', $tbl_system_settings);

	$settings = array();
	while($row = $modx->db->getRow($rs)) {
		switch($row['setting_name']) {
			case 'rb_base_dir':
			case 'filemanager_path':
			case 'site_url':
			case 'base_url':
				$settings[$row['setting_name']] = $row['setting_value'];
				break;
		}
	}
	return $settings;
}

function restoreSettings($settings) {
	global $modx;
	$tbl_system_settings = $modx->getFullTableName('system_settings');

	foreach($settings as $k => $v) {
		$modx->db->update(array('setting_value' => $v), $tbl_system_settings, "setting_name='{$k}'");
	}
}

function parsePlaceholder($tpl = '', $ph = array()) {
	if(empty($ph) || empty($tpl)) {
		return $tpl;
	}

	foreach($ph as $k => $v) {
		$k = "[+{$k}+]";
		$tpl = str_replace($k, $v, $tpl);
	}
	return $tpl;
}