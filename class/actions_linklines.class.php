<?php

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';

require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

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

		// ********************************************************
		$active_customer = getDolGlobalInt('MAIN_MODULE_LINKLINES_CUSTOMERLINKS');
		$view_customer = getDolGlobalInt('MAIN_MODULE_LINKLINES_CUSTOMERLINKS_VIEW');
		if($user->admin): $view_customer = 1; endif;

		if($active_customer && $view_customer):

			// COMMANDES
			if(in_array('ordercard', $contexts) && $action == 'editlinepropallinkconfirm' && GETPOST('token') == $_SESSION['token'] && GETPOST('confirm','alphanohtml') == 'yes'):

				if($user->rights->linklines->customer->link_propaltoorder):
					$line_id = GETPOST('lineid','int');
					$propalline_id = (intval(GETPOST('propallineid','int')) > 0)?GETPOST('propallineid','int'):'';

					$index = array_search($line_id, array_column($object->lines, 'id'));

					if($object->lines[$index]->array_options['options_fk_propal_line'] != $propalline_id):

						$old_linkid = $object->lines[$index]->array_options['options_fk_propal_line'];

						$object->lines[$index]->array_options['options_fk_propal_line'] = $propalline_id;
						$res = $object->lines[$index]->updateExtraField('fk_propal_line');

						if($res): 

							// On ajoute un event auto
							require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';

							$actevent = new actionComm($this->db);
		                    $actevent->type_id = 40;
		                    $actevent->elementtype = 'order';
		                    $actevent->datep = time();
		                    $actevent->datef = time();
		                    $actevent->fk_user_author = $user->id;
		                    $actevent->userownerid = $user->id;
		                    $actevent->fk_element = $object->id;
		                    $actevent->label = $langs->trans('customerPropalLineLinkSuccess');
		                    $actevent->note_private = $langs->trans('elementLineID',$object->lines[$index]->id).'<br>';
		                    $actevent->note_private.= $langs->trans('customerPropalLineLinkOld',$old_linkid).'<br>';
		                    $actevent->note_private.= $langs->trans('customerPropalLineLinkNew',$propalline_id);
		                    $actevent->percentage = -1;
		                    $actevent->create($user);

							setEventMessages($langs->trans('customerPropalLineLinkSuccess'), null, 'mesgs');	
							return 0;
						else: 
							$this->errors[] = $langs->trans('customerPropalLineLinkError');
							return -1;
						endif;

					endif;
				else: 
					$this->errors[] = $langs->trans('ErrorForbidden');
					return -1;
				endif;
			endif;

			// FACTURES
			if(in_array('invoicecard', $contexts) && $action == 'editlineorderlinkconfirm' && GETPOST('token') == $_SESSION['token'] && GETPOST('confirm','alphanohtml') == 'yes'):

				if($user->rights->linklines->customer->link_ordertoinvoice):
					$line_id = GETPOST('lineid','int');
					$orderline_id = (intval(GETPOST('orderlineid','int')) > 0)?GETPOST('orderlineid','int'):'';

					$index = array_search($line_id, array_column($object->lines, 'id'));

					if($object->lines[$index]->array_options['options_fk_order_line'] != $orderline_id):

						$old_linkid = $object->lines[$index]->array_options['options_fk_order_line'];

						$object->lines[$index]->array_options['options_fk_order_line'] = $orderline_id;
						$res = $object->lines[$index]->updateExtraField('fk_order_line');

						if($res): 

							$orderline = new OrderLine($this->db);
							$orderline->fetch($orderline_id);
							$orderline->fetch_optionals();

							$object->lines[$index]->array_options['options_fk_propal_line'] = $orderline->array_options['options_fk_propal_line'];
							$object->lines[$index]->updateExtraField('fk_propal_line');

							if($object->type == Facture::TYPE_SITUATION && !$object->is_first()): 

								$end_cycle = false; $i = 0;
								$last_previd = $object->lines[$index]->fk_prev_id;

								while(!$end_cycle): $i++;
									$prevline = new FactureLigne($this->db);
									$prevline->fetch($last_previd);
									$prevline->fetch_optionals();

									if($prevline->array_options['options_fk_order_line'] != $object->lines[$index]->array_options['options_fk_order_line']):
										$prevline->array_options['options_fk_order_line'] = $object->lines[$index]->array_options['options_fk_order_line'];
										$prevline->updateExtraField('fk_order_line');
									endif;

									if($prevline->array_options['options_fk_propal_line'] != $object->lines[$index]->array_options['options_fk_propal_line']):
										$prevline->array_options['options_fk_propal_line'] = $object->lines[$index]->array_options['options_fk_propal_line'];
										$prevline->updateExtraField('fk_propal_line');
									endif;

									if(intval($prevline->fk_prev_id) > 0):
										$last_previd = $prevline->fk_prev_id;
									else: $end_cycle = true;
									endif;
								endwhile;

							endif;

							// On ajoute un event auto
							require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';

							$actevent = new actionComm($this->db);
		                    $actevent->type_id = 40;
		                    $actevent->elementtype = 'order';
		                    $actevent->datep = time();
		                    $actevent->datef = time();
		                    $actevent->fk_user_author = $user->id;
		                    $actevent->userownerid = $user->id;
		                    $actevent->fk_element = $object->id;
		                    $actevent->label = $langs->trans('customerOrderLineLinkSuccess');
		                    $actevent->note_private = $langs->trans('elementLineID',$object->lines[$index]->id).'<br>';
		                    $actevent->note_private.= $langs->trans('customerOrderLineLinkOld',$old_linkid).'<br>';
		                    $actevent->note_private.= $langs->trans('customerOrderLineLinkNew',$orderline_id);
		                    $actevent->percentage = -1;
		                    $actevent->create($user);

							setEventMessages($langs->trans('customerPropalLineLinkSuccess'), null, 'mesgs');	
							return 0;
						else: 
							$this->errors[] = $langs->trans('customerPropalLineLinkError');
							return -1;
						endif;

					endif;
				else: 
					$this->errors[] = $langs->trans('ErrorForbidden');
					return -1;
				endif;
			endif;
		endif;

		// ********************************************************
		$active_supplier = getDolGlobalInt('MAIN_MODULE_LINKLINES_SUPPLIERLINKS');
		if($active_supplier):
		
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
		                    $actevent->note_private = $langs->trans('elementLineID',$object->lines[$index]->id).'<br>';
		                    $actevent->note_private.= $langs->trans('supplierOrderLineLinkOld',$old_linkid).'<br>';
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

		// ********************************************************
		$active_customer = getDolGlobalInt('MAIN_MODULE_LINKLINES_CUSTOMERLINKS');
		$view_customer = getDolGlobalInt('MAIN_MODULE_LINKLINES_CUSTOMERLINKS_VIEW');
		if($user->admin): $view_customer = 1; endif;

		if($active_customer && $view_customer):

			// Commandes
			if(in_array('ordercard', $contexts) && $action == 'editlinepropallink' && GETPOST('token') == $_SESSION['token']):	

				if($user->rights->linklines->customer->link_propaltoorder):

					$line_id = GETPOST('lineid','int');	

					// On récupère les objets liés
					$object->fetchObjectLinked();

					// Si il y a des commandes liées
					if(!empty($object->linkedObjects['propal'])):				

						$form = new Form($this->db);
						$formcontent = array();	
						$list_propallines = array();

						foreach($object->linkedObjects['propal'] as $propal_id => $propal):

							foreach($propal->lines as $propalline):
								$line_plabel = $propalline->fk_product?$propalline->ref.' - '.$propalline->product_label:$propalline->desc;
								$list_propallines[$propalline->id] = $propal->ref.' - '.$line_plabel.' ('.price($propalline->total_ht).' '.$langs->getCurrencySymbol($conf->currency).') - ID: '.$propalline->id;
							endforeach;
						endforeach;
						
						$index = array_search($line_id, array_column($object->lines, 'id'));
						$slct_a = $form->selectarray('propallineid',$list_propallines,$object->lines[$index]->array_options['options_fk_propal_line'],1,0,0,'',0,0,0,'','minwidth400imp',1);
						array_push($formcontent, array('type' => 'other', 'name' => 'propallineid', 'value' => $slct_a, 'label' => $langs->trans('customerPropalLineLinked')));
						echo $form->formconfirm(
							$_SERVER['PHP_SELF'].'?id='.$object->id.'&lineid='.$line_id,
							$langs->trans('linkPropalToOrderBoxTitle'),
							'','editlinepropallinkconfirm',$formcontent,'',1,0,620,0);

					endif;
				endif;
			endif;

			// Factures
			if(in_array('invoicecard', $contexts) && $action == 'editlineorderlink' && GETPOST('token') == $_SESSION['token']):	

				if($user->rights->linklines->customer->link_ordertoinvoice):

					$line_id = GETPOST('lineid','int');	

					// On récupère les objets liés
					$object->fetchObjectLinked();

					// Si il y a des commandes liées
					if(!empty($object->linkedObjects['commande'])):				

						$form = new Form($this->db);
						$formcontent = array();	
						$list_orderlines = array();

						foreach($object->linkedObjects['commande'] as $order_id => $order):

							foreach($order->lines as $orderline):
								$line_plabel = $orderline->fk_product?$orderline->ref.' - '.$orderline->product_label:$orderline->desc;
								$list_orderlines[$orderline->id] = $order->ref.' - '.$line_plabel.' ('.price($orderline->total_ht).' '.$langs->getCurrencySymbol($conf->currency).') - ID: '.$orderline->id;
							endforeach;
						endforeach;
						
						$index = array_search($line_id, array_column($object->lines, 'id'));
						$slct_a = $form->selectarray('orderlineid',$list_orderlines,$object->lines[$index]->array_options['options_fk_order_line'],1,0,0,'',0,0,0,'','minwidth400imp',1);
						array_push($formcontent, array('type' => 'other', 'name' => 'orderlineid', 'value' => $slct_a, 'label' => $langs->trans('customerOrderLineLinked')));
						echo $form->formconfirm(
							$_SERVER['PHP_SELF'].'?id='.$object->id.'&lineid='.$line_id,
							$langs->trans('linkOrderToInvoiceBoxTitle'),
							'','editlineorderlinkconfirm',$formcontent,'',1,0,620,0);

					endif;
				endif;
			endif;
		endif;

		// ********************************************************
		$active_supplier = getDolGlobalInt('MAIN_MODULE_LINKLINES_SUPPLIERLINKS');
		if($active_supplier):
			// Factures fournisseurs
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
							$langs->trans('linkSupplierOrderToInvoiceBoxTitle'),
							'','editlineorderlinkconfirm',$formcontent,'',1,0,620,0);

					endif;
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

		//
		$langs->load('linklines@linklines');

		//
		$contexts = explode(':', $parameters['context']);

		// ********************************************************
		$active_customer = getDolGlobalInt('MAIN_MODULE_LINKLINES_CUSTOMERLINKS');
		$view_customer = getDolGlobalInt('MAIN_MODULE_LINKLINES_CUSTOMERLINKS_VIEW');
		if($user->admin): $view_customer = 1; endif;

		if($active_customer && $view_customer && $user->rights->linklines->customer->read):

			// Commandes
			if(in_array('ordercard', $contexts)):

				$order = new Commande($this->db);
				$order->fetch($object->fk_commande);

				// On récupère les objets liés
				$order->fetchObjectLinked();

				// Si il y a des propal liées
				if(!empty($order->linkedObjects['propal'])):

					// MODE EDITION
					if($action == 'editline' && $object->id == GETPOST('lineid','int') && $user->rights->linklines->customer->link_propaltoorder):

						$list_propal_lines = array();
						foreach($order->linkedObjects['propal'] as $prop_id => $prop): 
							foreach($prop->lines as $propline):

								$line_plabel = $propline->fk_product?$propline->ref.' - '.$propline->product_label:$propline->desc;
								$list_propal_lines[$propline->id] = $prop->ref.' - '.$line_plabel.' ('.price($propline->total_ht).' '.$langs->getCurrencySymbol($conf->currency).') - ID: '.$propline->id;
							endforeach;
						endforeach;

						$form = new Form($this->db);
						$this->resprints.= $langs->trans('customerPropalLineLinked');
						$this->resprints.= ' '.$form->selectarray('fk_propal_line',$list_propal_lines,$object->array_options['options_fk_propal_line'],1,0,0,'',0,0,0,'','minwidth300imp');

					// MODE VUE
					else:

						$show_customer_link = false;
						if($order->statut > 0 && $user->rights->linklines->customer->link_propaltoorder):

							$show_customer_link = true;

							// STYLE LIEN
							$this->resprints.= '<style>';
								$this->resprints.= '.editlinelink {color:#25a580 !important;}';
								$this->resprints.= '.addlinelink {color:#f88533 !important;}';
							$this->resprints.= '</style>';

							// LIEN
							$editlink = $_SERVER['PHP_SELF'].'?id='.$order->id.'&action=editlinepropallink&lineid='.$object->id.'&token='.newtoken();

						endif;

						//$this->resprints.= $langs->trans('customerPropalLineLinked').': ';
						if(!empty($object->array_options['options_fk_propal_line']) && $object->array_options['options_fk_propal_line'] > 0):

							$propal_line = new PropaleLigne($this->db);
							$propal_line->fetch($object->array_options['options_fk_propal_line']);

							$propal = new Propal($this->db);
							$propal->fetch($propal_line->fk_propal);
							$prodlabel = $propal_line->fk_product?$propal_line->ref.' - '.$propal_line->product_label:$propal_line->desc;						

							$this->resprints.= '<div style="font-size:0.8em;color:#25a580;font-weight:bold;margin-top:6px;">';							
							if($show_customer_link): $this->resprints.= '<a href="'.$editlink.'" class="editlinelink" >'; endif;
							$this->resprints.= '<span class="fas fa-link" style="font-size:0.75em;color:#25a580;"></span> ';
							$this->resprints.= $propal->ref.' - '.$prodlabel.' ('.price($propal_line->total_ht).' '.$langs->getCurrencySymbol($conf->currency).')';
							if($user->admin): $this->resprints.= ' - Line ID: '.$propal_line->id; endif;
							if($show_customer_link): $this->resprints.= '</a>'; endif;
							$this->resprints.= '</div>';

						else:
							$this->resprints.= '<div style="font-size:0.8em;color:#f88533;font-weight:bold;margin-top:6px;">';
							if($show_customer_link): $this->resprints.= '<a href="'.$editlink.'" class="addlinelink" >'; endif;
							$this->resprints.= '<span class="fas fa-link" style="font-size:0.75em;color:#f88533;"></span> ';
							$this->resprints.= ' '.$langs->trans('noCustomerPropalLineLinked');
							if($show_customer_link): $this->resprints.= '</a>'; endif;
							$this->resprints.= '</div>';
						endif;

						
					endif;
				endif;
			endif;

			// Factures
			if(in_array('invoicecard', $contexts)):

				$invoice = new Facture($this->db);
				$invoice->fetch($object->fk_facture);

				// On récupère les objets liés
				$invoice->fetchObjectLinked();

				// Si il y a des commandes liées
				if(!empty($invoice->linkedObjects['commande'])):

					// MODE EDITION
					if($action == 'editline' && $object->id == GETPOST('lineid','int') && $user->rights->linklines->customer->link_ordertoinvoice):

						$list_order_lines = array();

						foreach($invoice->linkedObjects['commande'] as $order_id => $order): 
							foreach($order->lines as $orderline):
								$line_plabel = $orderline->fk_product?$orderline->ref.' - '.$orderline->product_label:$orderline->desc;
								$list_order_lines[$orderline->id] = $order->ref.' - '.$line_plabel.' ('.price($orderline->total_ht).' '.$langs->getCurrencySymbol($conf->currency).') - ID: '.$orderline->id;
							endforeach;
						endforeach;

						$form = new Form($this->db);
						$this->resprints.= $langs->trans('customerOrderLineLinked');
						$this->resprints.= ' '.$form->selectarray('fk_order_line',$list_order_lines,$object->array_options['options_fk_order_line'],1,0,0,'',0,0,0,'','minwidth300imp');
						
					// MODE VUE
					else:

						$parent_object = new Facture($this->db);
						$parent_object->fetch($object->fk_facture);

						// Check situation
						$viewlink = true;
						if($parent_object->type == Facture::TYPE_SITUATION && !$parent_object->is_last_in_cycle()): $viewlink = false; endif;
						$show_customer_link = false;
						if($invoice->statut > 0 && $user->rights->linklines->customer->link_ordertoinvoice && $viewlink):

							$show_customer_link = true;

							// STYLE LIEN
							$this->resprints.= '<style>';
								$this->resprints.= '.editlinelink {color:#25a580 !important;}';
								$this->resprints.= '.addlinelink {color:#f88533 !important;}';
							$this->resprints.= '</style>';

							// LIEN
							$editlink = $_SERVER['PHP_SELF'].'?facid='.$invoice->id.'&action=editlineorderlink&lineid='.$object->id.'&token='.newtoken();
						endif;

						//$this->resprints.= $langs->trans('customerOrderLineLinked').': ';
						if(!empty($object->array_options['options_fk_order_line']) && $object->array_options['options_fk_order_line'] > 0):

							$order_line = new OrderLine($this->db);
							$order_line->fetch($object->array_options['options_fk_order_line']);

							$order = new Commande($this->db);
							$order->fetch($order_line->fk_commande);
							$prodlabel = $order_line->fk_product?$order_line->ref.' - '.$order_line->product_label:$order_line->desc;						
							
							$this->resprints.= '<div style="font-size:0.8em;color:#25a580;font-weight:bold;margin-top:6px;">';							
							if($show_customer_link): $this->resprints.= '<a href="'.$editlink.'" class="editlinelink" >'; endif;
							$this->resprints.= '<span class="fas fa-link" style="font-size:0.75em;color:#25a580;"></span> ';
							$this->resprints.= $order->ref.' - '.$prodlabel.' ('.price($order_line->total_ht).' '.$langs->getCurrencySymbol($conf->currency).')';
							if($user->admin): $this->resprints.= ' - Line ID: '.$order_line->id; endif;
							if($show_customer_link): $this->resprints.= '</a>'; endif;
							$this->resprints.= '</div>';
							
						else:

							$this->resprints.= '<div style="font-size:0.8em;color:#f88533;font-weight:bold;margin-top:6px;">';
							if($show_customer_link): $this->resprints.= '<a href="'.$editlink.'" class="addlinelink" >'; endif;
							$this->resprints.= '<span class="fas fa-link" style="font-size:0.75em;color:#f88533;"></span> ';
							$this->resprints.= ' '.$langs->trans('noCustomerOrderLineLinked');
							if($show_customer_link): $this->resprints.= '</a>'; endif;
							$this->resprints.= '</div>';

						endif;

						
					endif;
				endif;
			endif;
		endif;

		// ********************************************************
		$active_supplier = getDolGlobalInt('MAIN_MODULE_LINKLINES_SUPPLIERLINKS');
		if($active_supplier || $user->admin):
			// Factures fournisseurs
			if(in_array('invoicesuppliercard', $contexts)): //  /*&& $parameters['display_type'] == 'line'*/

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

						// Show link
						$show_supplier_link = false;
						if($ff->statut > 0 && $user->rights->linklines->supplier->link_ordertoinvoice):

							$show_supplier_link = true;

							// STYLE LIEN
							$this->resprints.= '<style>';
								$this->resprints.= '.editlinelink {color:#25a580 !important;}';
								$this->resprints.= '.addlinelink {color:#f88533 !important;}';
							$this->resprints.= '</style>';

							// LIEN
							$editlink = $_SERVER['PHP_SELF'].'?facid='.$ff->id.'&action=editlineorderlink&lineid='.$object->id.'&token='.newtoken();
							
						endif;

						if(!empty($object->array_options['options_fk_commande_fournisseur_line']) && $object->array_options['options_fk_commande_fournisseur_line'] > 0):

							$orderline = new CommandeFournisseurLigne($this->db);
							$orderline->fetch($object->array_options['options_fk_commande_fournisseur_line']);

							$order = new CommandeFournisseur($this->db);
							$order->fetch($orderline->fk_commande);
							$prodlabel = $orderline->fk_product?$orderline->ref.' - '.$orderline->product_label:$orderline->desc;						
							
							$this->resprints.= '<div style="font-size:0.8em;color:#25a580;font-weight:bold;margin-top:6px;">';							
							if($show_supplier_link): $this->resprints.= '<a href="'.$editlink.'" class="editlinelink" >'; endif;
							$this->resprints.= '<span class="fas fa-link" style="font-size:0.75em;color:#25a580;"></span> ';
							$this->resprints.= $order->ref.' - '.$prodlabel.' ('.price($orderline->total_ht).' '.$langs->getCurrencySymbol($conf->currency).')';
							if($user->admin): $this->resprints.= ' - Line ID: '.$orderline->id; endif;
							if($show_supplier_link): $this->resprints.= '</a>'; endif;
							$this->resprints.= '</div>';
						else:
							$this->resprints.= '<div style="font-size:0.8em;color:#f88533;font-weight:bold;margin-top:6px;">';
							if($show_supplier_link): $this->resprints.= '<a href="'.$editlink.'" class="addlinelink" >'; endif;
							$this->resprints.= '<span class="fas fa-link" style="font-size:0.75em;color:#f88533;"></span> ';
							$this->resprints.= ' '.$langs->trans('noSupplierOrderLineLinked');
							if($show_supplier_link): $this->resprints.= '</a>'; endif;
							$this->resprints.= '</div>';
						endif;

						/**/
					endif;
				endif;
			endif;
		endif;

		return 0;
	}

}

?>