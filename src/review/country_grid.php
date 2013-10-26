<?php
if ( !isset( $_SESSION['user_id'] ) ) {
	header( 'location: ' . $BASEURL . 'user/login' );
	exit();
}

$order = ( isset( $_GET['order'] ) ) ? $_GET['order']:'';

$dal = new Dao();
$users = $dal->GetListofCountries( $order );
?>
<?php include "templates/header_review.php" ?>
<form method="post" action="grid.php">
<h2>Applications</h2>
<div id="form-container" class="fourteen columns">
<?php include "templates/admin_nav.php" ?>
<table id="grid" class="grid">
	<tr>
		<th style='width: 42%;'>country</th>
		<th style='width: 32%;'>region</th>
		<th style='width: 25%;'>scholarship count</th>
	</tr>
	<?php foreach ( $users as $row ): ?>
	<tr>
		<td><a href='<?php echo $BASEURL . "review/search/results?residence=" . $row['country_name'] . "&last=&first="; ?>'><?= $row['country_name']; ?></a></td>
		<td><a href='<?php
if ( strrpos( $row['region'], "&" ) != "" ) {
	$temp = $row['region'];
	$temp = str_replace( "&", "%26", $temp );
	echo $BASEURL . "review/search/results?region=" . $temp . "&last=&first=";
}
else {
	echo $BASEURL . "review/search/results?region=" . $row['region'] . "&last=&first=";
} ?>'><?= $row['region']; ?></td>
	<td><?= $row['sid']; ?></td>
	</tr>
	<?php endforeach; ?>
</table>
	</form>
	</div>
	<?php include "templates/footer_review.php";
