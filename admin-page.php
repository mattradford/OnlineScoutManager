<?php
add_action( 'admin_menu', 'my_plugin_menu' );
function my_plugin_menu() {
	add_menu_page('Online Scout Manager', 'OSM', 'manage_options', 'osm', 'my_plugin_options');
}
function getRoles() {
	$roles = osm_query('api.php?action=getUserRoles');
	$storeRoles = array();
	if ($roles) {
		foreach ($roles as $role) {
			switch ($role['section']) {
				case 'beavers':
				case 'cubs':
				case 'scouts':
				case 'explorers':
					$storeRoles[$role['sectionid']] = array('groupname' => $role['groupname'], 'sectionname' => $role['sectionname'], 'section' => $role['section'], 'sectionid' => $role['sectionid']);
			}
		}
	}
	update_option('OnlineScoutManager_allRoles', $storeRoles);
	return $storeRoles;
}
function resyncDataToActiveRoles() {
	$active_roles = get_option('online_scout_manager_active_roles');
	$allRoles = get_option('OnlineScoutManager_allRoles');
	foreach ($active_roles as $sectionid => $role) {
		$active_roles[$sectionid]['section'] = $allRoles[$sectionid]['section'];
	}
	update_option('online_scout_manager_active_roles', $active_roles);
}
function my_plugin_options() {
	$OnlineScoutManager_options = array('online_scout_manager_userid','online_scout_manager_secret', 'OnlineScoutManager_allRoles', 'online_scout_manager_active_roles'); 
	$OnlineScoutManager_cache = array('OnlineScoutManager_programme', 'OnlineScoutManager_patrols');
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	$authoriseErrorMsg = "";
	if (isset($_POST['mode'])) {
		$mode = $_POST['mode'];
		if ($mode == 'usernamepassword') {
			$email = $_POST['email'];
			$password = $_POST['password'];
			$retVal = osm_query('users.php?action=authorise', array('email' => $email, 'password' => $password));
			if ($retVal['userid'] and $retVal['userid'] > 0) {
				$userid = $retVal['userid'];
				$secret = $retVal['secret'];
				update_option('online_scout_manager_userid', $userid);
				update_option('online_scout_manager_secret', $secret);
				
				$storeRoles = getRoles();
				$mode = 'enableroles';
				include(WP_PLUGIN_DIR . '/' . PLUGIN_SLUG . '/views/admin_authorise.php');
				return;
			} else if (isset($retVal['error'])) {
				$authoriseErrorMsg = $retVal['error'];
			}
		} else if ($mode == 'enableroles') {
			$roles = $_POST['roles'];
			if (count($roles) > 0) {
				$storeRoles = get_option('OnlineScoutManager_allRoles');
				$active_roles = array();
				foreach ($roles as $sectionid => $null) {
					$active_roles[$sectionid] = $storeRoles[$sectionid];
				}
				delete_option('OnlineScoutManager_allRoles');
				update_option('online_scout_manager_active_roles', $active_roles);
				osm_get_terms();
			} else {
				$storeRoles = get_option('OnlineScoutManager_allRoles');
				$authoriseErrorMsg = 'You must select one or more sections to use.';
				$mode = 'enableroles';
				include(WP_PLUGIN_DIR . '/' . PLUGIN_SLUG . '/views/admin_authorise.php');
				return;
			}
		} else if ($mode == 'unauthorise') {
			foreach ($OnlineScoutManager_options as $toDelete) {
				delete_option($toDelete);
			}
			foreach ($OnlineScoutManager_cache as $toDelete) {
				delete_option($toDelete);
			}
		} else if ($mode == 'purgecache') {
			$authoriseErrorMsg = "Cache has been purged";
			$options = get_alloptions();
			foreach ($options as $key => $value) {
				if (strpos($key, 'OnlineScoutManager_') === 0 and !in_array($key, $OnlineScoutManager_options)) {
					delete_option($key);
				}
			}
		}
	}
	$userid = get_option('online_scout_manager_userid');
	if ($userid > 0) {
		include(WP_PLUGIN_DIR . '/' . PLUGIN_SLUG . '/views/admin.php');
	} else {
		$mode = 'usernamepassword';
		include(WP_PLUGIN_DIR . '/' . PLUGIN_SLUG . '/views/admin_authorise.php');
	}
}

?>