<?php
/* Copyright (C) ---Put here your own copyright and developer email---
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    core/triggers/interface_99_modMyModule_MyModuleTriggers.class.php
 * \ingroup mymodule
 * \brief   Example trigger.
 *
 * Put detailed description here.
 *
 * \remarks You can create other triggers by copying this one.
 * - File name should be either:
 *      - interface_99_modMyModule_MyTrigger.class.php
 *      - interface_99_all_MyTrigger.class.php
 * - The file must stay in core/triggers
 * - The class name must be InterfaceMytrigger
 * - The constructor method must be named InterfaceMytrigger
 * - The name property name must be MyTrigger
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';


/**
 *  Class of triggers for MyModule module
 */
class InterfaceLinkLines extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "Progiseize";
		$this->description = "MyModule triggers.";
		// 'development', 'experimental', 'dolibarr' or version
		$this->version = 'development';
		$this->picto = 'technic';
	}

	/**
	 * Trigger name
	 *
	 * @return string Name of trigger file
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Trigger description
	 *
	 * @return string Description of trigger file
	 */
	public function getDesc()
	{
		return $this->description;
	}


	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "runTrigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * @param string 		$action 	Event action code
	 * @param CommonObject 	$object 	Object
	 * @param User 			$user 		Object user
	 * @param Translate 	$langs 		Object langs
	 * @param Conf 			$conf 		Object conf
	 * @return int              		<0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (empty($conf->linklines->enabled)): return 0; endif; // If module is not enabled, we do nothing

		//
		switch ($action):

			/*********************************************************************/
			/* CUSTOMER */
			/*********************************************************************/

			// COMMANDE
			case 'LINEORDER_INSERT':
				$active_customer = getDolGlobalInt('MAIN_MODULE_LINKLINES_CUSTOMERLINKS');
				if($active_customer):
					if(!empty($object->origin) && $object->origin == 'propal'):
						$object->array_options['options_fk_propal_line'] = $object->origin_id;
						$object->updateExtraField('fk_propal_line');
					endif;
				endif;
			break;
			case 'LINEORDER_MODIFY':
				$active_customer = getDolGlobalInt('MAIN_MODULE_LINKLINES_CUSTOMERLINKS');
				if($active_customer):
					if(GETPOSTISSET('fk_propal_line')):
						$fk_propal_line = GETPOST('fk_propal_line','int')>0?GETPOST('fk_propal_line','int'):'';
						$object->array_options['options_fk_propal_line'] = $fk_propal_line;
						$object->updateExtraField('fk_propal_line');
					endif;
				endif;
			break;

			// FACTURE
			case 'LINEBILL_INSERT':
				$active_customer = getDolGlobalInt('MAIN_MODULE_LINKLINES_CUSTOMERLINKS');
				if($active_customer):
					if(!empty($object->origin) && $object->origin == 'propal'):
						$object->array_options['options_fk_propal_line'] = $object->origin_id;
						$object->updateExtraField('fk_propal_line');
					elseif(!empty($object->origin) && $object->origin == 'commande'):

						global $db;

						$object->array_options['options_fk_order_line'] = $object->origin_id;
						$object->updateExtraField('fk_order_line');

						$orderline = new OrderLine($db);
						$orderline->fetch($object->origin_id);
						$orderline->fetch_optionals();
						$object->array_options['options_fk_propal_line'] = $orderline->array_options['options_fk_propal_line'];
						$object->updateExtraField('options_fk_propal_line');

					endif;
				endif;
			break;
			case 'LINEBILL_MODIFY':
				$active_customer = getDolGlobalInt('MAIN_MODULE_LINKLINES_CUSTOMERLINKS');
				if($active_customer):
					if(GETPOSTISSET('fk_order_line')):
						$fk_order_line = GETPOST('fk_order_line','int')>0?GETPOST('fk_order_line','int'):'';
						$object->array_options['options_fk_order_line'] = $fk_order_line;
						$object->updateExtraField('fk_order_line');

						global $db;
						$orderline = new OrderLine($db);
						$orderline->fetch($fk_order_line);
						$orderline->fetch_optionals();
						$object->array_options['options_fk_propal_line'] = $orderline->array_options['options_fk_propal_line'];
						$object->updateExtraField('fk_propal_line');
					endif;
				endif;
			break;
			

			/*********************************************************************/
			/* SUPPLIER */
			/*********************************************************************/

			// FACTURES FOURNISSEURS
			case 'LINEBILL_SUPPLIER_CREATE': 
				$active_supplier = getDolGlobalInt('MAIN_MODULE_LINKLINES_SUPPLIERLINKS');
				if($active_supplier):
					if(!empty($object->origin) && $object->origin == 'order_supplier'):
						$object->array_options['options_fk_commande_fournisseur_line'] = $object->origin_id;
						$object->updateExtraField('fk_commande_fournisseur_line');
					endif;
				endif;
			break;
			case 'LINEBILL_SUPPLIER_MODIFY':
				$active_supplier = getDolGlobalInt('MAIN_MODULE_LINKLINES_SUPPLIERLINKS');
				if($active_supplier):
					if(GETPOSTISSET('fk_commande_fournisseur_line')):
						$fk_commande_fournisseur_line = GETPOST('fk_commande_fournisseur_line','int')>0?GETPOST('fk_commande_fournisseur_line','int'):'';
						$object->array_options['options_fk_commande_fournisseur_line'] = $fk_commande_fournisseur_line;
						$object->updateExtraField('fk_commande_fournisseur_line');
					endif;
				endif;
			break;
			
			// DEFAULT
			default:
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
			break;

		endswitch;

		return 0;
	}
}
