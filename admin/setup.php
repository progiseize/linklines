<?php
/* 
 * Copyright (C) 2023 Anthony Damhet - Progiseize <a.damhet@progiseize.fr>
*/

$res=0;
if (! $res && file_exists("../main.inc.php")): $res=@include '../main.inc.php'; endif;
if (! $res && file_exists("../../main.inc.php")): $res=@include '../../main.inc.php'; endif;
if (! $res && file_exists("../../../main.inc.php")): $res=@include '../../../main.inc.php'; endif;

// Protection if external user
if ($user->socid > 0): accessforbidden(); endif;

// Droits
if (!$user->rights->linklines->configurer): accessforbidden(); endif;

// ON CHARGE LES FICHIERS NECESSAIRES
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

// ON CHARGE LA LANGUE DU MODULE
$langs->load("linklines@linklines");

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
$array_css = array('progilib/assets/css/dolpgs.css');
llxHeader('',$langs->trans('setupLinkLines'),'','','','',$array_js,$array_css); 

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("setupLinkLines"), $linkback, 'title_setup'); ?>

<div class="dolpgs-main-wrapper">
	 <table class="dolpgs-table tab-light">
	 	<tbody>
	 		<tr class="dolpgs-thead noborderside">
	 			<th><?php echo $langs->trans('Parameter'); ?></th>
	 			<th><?php echo $langs->trans('Description'); ?></th>
	 			<th class="right"></th>
	 		</tr>
	 		<tr class="dolpgs-tbody tbody-oddeven">
	 			<td><?php echo $langs->trans('setupLinkLinesCustomer'); ?></td>
	 			<td><?php echo $langs->trans('setupLinkLinesCustomerDesc'); ?></td>
	 			<td class="right"><?php echo ajax_constantonoff('MAIN_MODULE_LINKLINES_CUSTOMERLINKS'); ?></td>
	 		</tr>
	 		<tr class="dolpgs-tbody tbody-oddeven">
	 			<td><?php echo $langs->trans('setupLinkLinesSupplier'); ?></td>
	 			<td><?php echo $langs->trans('setupLinkLinesSupplierDesc'); ?></td>
	 			<td class="right"><?php echo ajax_constantonoff('MAIN_MODULE_LINKLINES_SUPPLIERLINKS'); ?></td>
	 		</tr>
	 	</tbody>
	 </table>
</div>




<?php llxFooter(); $db->close(); ?>