<?php

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';

class ActionsLinkLines { 

	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var int		Priority of hook (50 is used if value is not defined)
	 */
	public $priority;

	/**
	 * Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * doActions
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager){

		global $langs, $conf, $user;

		$langs->load('linklines@linklines');

		$contexts = explode(':', $parameters['context']);		
		
		// FACTURES FOURNISSEURS
		if(in_array('invoicesuppliercard', $contexts) && $action == 'editlineorderlinkconfirm' && GETPOST('token') == $_SESSION['token'] && GETPOST('confirm','alphanohtml') == 'yes'):
			
			if($user->rights->linklines->supplier->link_ordertoinvoice):
				$line_id = GETPOST('lineid','int');
				$orderline_id = (intval(GETPOST('orderlineid','int')) > 0)?GETPOST('orderlineid','int'):'';;

				$index = array_search($line_id, array_column($object->lines, 'id'));

				if($object->lines[$index]->array_options['options_fk_commande_fournisseur_line'] != $orderline_id):

					$old_linkid = $object->lines[$index]->array_options['options_fk_commande_fournisseur_line'];

					$object->lines[$index]->array_options['options_fk_commande_fournisseur_line'] = $orderline_id;
					$res = $object->lines[$index]->updateExtraField('fk_commande_fournisseur_line');

					if($res): 

						// On ajoute un event auto
						require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';

						$actevent = new actionComm($this->db);
	                    $actevent->type_id = 40;
	                    $actevent->elementtype = 'invoice_supplier';
	                    $actevent->datep = time();
	                    $actevent->datef = time();
	                    $actevent->fk_user_author = $user->id;
	                    $actevent->userownerid = $user->id;
	                    $actevent->fk_element = $object->id;
	                    $actevent->label = $langs->trans('supplierOrderLineLinkSuccess');
	                    $actevent->note_private = $langs->trans('supplierOrderLineLinkOld',$old_linkid).'<br>';
	                    $actevent->note_private.= $langs->trans('supplierOrderLineLinkNew',$orderline_id);
	                    $actevent->percentage = -1;
	                    $actevent->create($user);

						setEventMessages($langs->trans('supplierOrderLineLinkSuccess'), null, 'mesgs');	
						return 0;
					else: 
						$this->errors[] = $langs->trans('supplierOrderLineLinkError');
						return -1;
					endif;

				endif;
			else: 
				$this->errors[] = $langs->trans('ErrorForbidden');
				return -1;
			endif;	
		endif;

		return 0;		
	}

	/**
	 * addMoreActionsButtons
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager){

		global $conf, $langs, $user;

		$langs->load('linklines@linklines');
		$contexts = explode(':', $parameters['context']);

		if(in_array('invoicesuppliercard', $contexts) && $action == 'editlineorderlink' && GETPOST('token') == $_SESSION['token']):	

			if($user->rights->linklines->supplier->link_ordertoinvoice):

				$line_id = GETPOST('lineid','int');	

				// On récupère les objets liés
				$object->fetchObjectLinked();

				// Si il y a des commandes liées
				if(!empty($object->linkedObjects['order_supplier'])):				

					$form = new Form($this->db);
					$formcontent = array();	
					$list_orderlines = array();

					foreach($object->linkedObjects['order_supplier'] as $orderfourn_id => $orderfourn):

						foreach($orderfourn->lines as $orderfournline):
							$line_plabel = $orderfournline->fk_product?$orderfournline->ref.' - '.$orderfournline->product_label:$orderfournline->desc;
							$list_orderlines[$orderfournline->id] = $orderfourn->ref.' - '.$line_plabel.' ('.price($orderfournline->total_ht).' '.$langs->getCurrencySymbol($conf->currency).') - ID: '.$orderfournline->id;
						endforeach;
					endforeach;
					
					$index = array_search($line_id, array_column($object->lines, 'id'));
					$slct_a = $form->selectarray('orderlineid',$list_orderlines,$object->lines[$index]->array_options['options_fk_commande_fournisseur_line'],1,0,0,'',0,0,0,'','minwidth400imp',1);
					array_push($formcontent, array('type' => 'other', 'name' => 'orderlineid', 'value' => $slct_a, 'label' => $langs->trans('supplierOrderLineLinked')));
					echo $form->formconfirm(
						$_SERVER['PHP_SELF'].'?facid='.$object->id.'&lineid='.$line_id,
						$langs->trans('linkOrderInvoiceSupplierBoxTitle'),
						'','editlineorderlinkconfirm',$formcontent,'',1,0,620,0);

				endif;
			endif;
		endif;

		return 0;
	}

	/**
	 * showOptionals
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function showOptionals($parameters, &$object, &$action, $hookmanager){

		global $conf, $user, $langs;

		$langs->load('linklines@linklines');

		$contexts = explode(':', $parameters['context']);

		if(in_array('invoicesuppliercard', $contexts) /*&& $parameters['display_type'] == 'line'*/):

			// On récupère la facture
			$ff = new FactureFournisseur($this->db);
			$ff->fetch($object->fk_facture_fourn);

			// On récupère les objets liés
			$ff->fetchObjectLinked();			

			// Si il y a des commandes liées
			if(!empty($ff->linkedObjects['order_supplier'])):		

				// MODE EDITION
				if($action == 'editline' && $object->id == GETPOST('lineid','int') && $user->rights->linklines->supplier->link_ordertoinvoice):

					$list_orderlines = array();
					foreach($ff->linkedObjects['order_supplier'] as $orderfourn_id => $orderfourn): 
						foreach($orderfourn->lines as $orderfournline):

							$line_plabel = $orderfournline->fk_product?$orderfournline->ref.' - '.$orderfournline->product_label:$orderfournline->desc;
							$list_orderlines[$orderfournline->id] = $orderfourn->ref.' - '.$line_plabel.' ('.price($orderfournline->total_ht).' '.$langs->getCurrencySymbol($conf->currency).') - ID: '.$orderfournline->id;
						endforeach;
					endforeach;

					$form = new Form($this->db);
					$this->resprints.= $langs->trans('supplierOrderLineLinked');
					$this->resprints.= ' '.$form->selectarray('fk_commande_fournisseur_line',$list_orderlines,$object->array_options['options_fk_commande_fournisseur_line'],1,0,0,'',0,0,0,'','minwidth300imp');

				// MODE VUE
				else:
					$this->resprints.= $langs->trans('supplierOrderLineLinked').': ';
					if(!empty($object->array_options['options_fk_commande_fournisseur_line']) && $object->array_options['options_fk_commande_fournisseur_line'] > 0):

						$orderline = new CommandeFournisseurLigne($this->db);
						$orderline->fetch($object->array_options['options_fk_commande_fournisseur_line']);

						$order = new CommandeFournisseur($this->db);
						$order->fetch($orderline->fk_commande);

						$prodlabel = $orderline->fk_product?$orderline->ref.' - '.$orderline->product_label:$orderline->desc;						
						$this->resprints.= $order->ref.' - '.$prodlabel.' ('.price($orderline->total_ht).' '.$langs->getCurrencySymbol($conf->currency).') - ID: '.$orderline->id;
					else:
						$this->resprints.= '<span class="colorgrey">'.$langs->trans('noSupplierOrderLineLinked').'</span>';
					endif;

					if($ff->statut > 0 && $user->rights->linklines->supplier->link_ordertoinvoice):
						// STYLE LIEN
						$this->resprints.= '<style>';
							$this->resprints.= '.editlinelink {font-size:0.85em; color:#888 !important;}';
							$this->resprints.= '.editlinelink:hover {color:rgb(10,20,100) !important;}';
						$this->resprints.= '</style>';
						// LIEN
						$editlink = $_SERVER['PHP_SELF'].'?facid='.$ff->id.'&action=editlineorderlink&lineid='.$object->id.'&token='.newtoken();
						$this->resprints.= ' <a href="'.$editlink.'" class="editlinelink" ><i class="fas fa-pencil-alt"></i></a>';
					endif;
				endif;
			endif;
		endif;

		return 0;
	}

}

?>