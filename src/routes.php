<?php

/**
 * URI to activity mappings.
 * @var array $routes
 */
$routes = array(
	'review'                => 'review/grid.php',
	'review/bulkmail'       => 'admin/bulk_mail.php',
	'review/country'        => 'review/country_grid.php',
	'review/country/edit'   => 'review/edit_country.php',
	'review/country/grid'   => 'review/country_grid.php',
	'review/dump'           => 'admin/dump.php',           // usecase unknown
	'review/edit'           => 'review/edit.php',
	'review/export'         => 'review/export.php',
	'review/grid'           => 'review/grid.php',
	'review/grid/score'     => 'review/grid_score.php',
	'review/mail'           => 'review/mail.php',
	'review/p1/failList'    => 'review/phase1FailList.php',
	'review/p1/successList' => 'review/phase1SuccessList.php',
	'review/p2/list'        => 'review/phase2List.php',
	'review/phase1'         => 'review/grid.php',
	'review/phase2'         => 'review/grid_phase2.php',
	'review/region'         => 'review/region_grid.php',
	'review/search'         => 'review/searchform.php',
	'review/search/results' => 'review/search_results.php',
	'review/view'           => 'review/view.php',
	'user'                  => 'user/login.php',
	'user/add'              => 'admin/add_user.php',
	'user/list'             => 'admin/user_grid.php',
	'user/login'            => 'user/login.php',
	'user/logout'           => 'user/logout.php',
	'user/password'         => 'user/user_pwreset.php',
	'user/password/reset'   => 'user/user_pwreset.php',
	'user/table'            => 'admin/usertable.php',
	'user/view'             => 'admin/view_user.php',
);

//$defaultRoute = $routes['apply'];