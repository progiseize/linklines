<?php
/* 
 * Copyright (C) 2023 Anthony Damhet - Progiseize <a.damhet@progiseize.fr>
 */

$res=0;
if (! $res && file_exists("../../main.inc.php")): $res=@include '../../main.inc.php'; endif;

// Protection if external user
if ($user->socid > 0): accessforbidden(); endif;

require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

/*******************************************************************
* VARIABLES
********************************************************************/

/*******************************************************************
* ACTIONS
********************************************************************/

/*******************************************************************
* VIEW
********************************************************************/
$array_js = array();
$array_css = array();
llxHeader('',$langs->trans('Migration'),'','','','',$array_js,$array_css); 



/*
$sql = "SELECT rowid, ref_ext FROM ".MAIN_DB_PREFIX."facturedet";
$sql.= " WHERE ref_ext LIKE 'COMLINEID_%'";
$res = $db->query($sql);
if(!$res): echo 'error'; endif;

while ($obj = $db->fetch_object($res)):

	// LIEN COMMANDE
	$orderline_id = str_replace('COMLINEID_','',$obj->ref_ext);
	var_dump('--'.$obj->rowid.'--');
	var_dump($orderline_id);

	// FETCH LINE
	$factline = new FactureLigne($db);
	$factline->fetch($obj->rowid);
	$factline->fetch_optionals();

	$factline->array_options['options_fk_order_line'] = $orderline_id;
	$factline->updateExtraField('fk_order_line');

	var_dump($factline->array_options);

endwhile;

var_dump($res);
*/

?>

<?php llxFooter(); $db->close(); ?>