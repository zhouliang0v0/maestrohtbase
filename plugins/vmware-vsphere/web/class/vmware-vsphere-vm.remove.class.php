<?php
/**
 * vSphere Hosts remove VM
 *
    HyperTask Enterprise developed by HyperTask Enterprise GmbH.

    All source code and content (c) Copyright 2014, HyperTask Enterprise GmbH unless specifically noted otherwise.

    This source code is released under the HyperTask Enterprise Server and Client License, unless otherwise agreed with HyperTask Enterprise GmbH.
    The latest version of this license can be found here: http://htvcenter-enterprise.com/license

    By using this software, you acknowledge having read this license and agree to be bound thereby.

                http://htvcenter-enterprise.com

    Copyright 2014, HyperTask Enterprise GmbH <info@htvcenter-enterprise.com>
 */

class vmware_vsphere_vm_remove
{
/**
* name of action buttons
* @access public
* @var string
*/
var $actions_name = 'vmware_vsphere_vm_action';
/**
* message param
* @access public
* @var string
*/
var $message_param = "vmware_vsphere_msg";
/**
* id for tabs
* @access public
* @var string
*/
var $prefix_tab = 'vmware_vsphere_tab';
/**
* identifier name
* @access public
* @var string
*/
var $identifier_name = 'vmw_vsphere_id';
/**
* path to templates
* @access public
* @var string
*/
var $tpldir;
/**
* translation
* @access public
* @var array
*/
var $lang = array();

	//--------------------------------------------
	/**
	 * Constructor
	 *
	 * @access public
	 * @param htvcenter $htvcenter
	 * @param htmlobject_response $response
	 */
	//--------------------------------------------
	function __construct($htvcenter, $response) {
		$this->response = $response;
		$this->htvcenter = $htvcenter;
		$this->user = $htvcenter->user();
		$this->file = $this->htvcenter->file();
		$this->rootdir = $this->htvcenter->get('webdir');
		$this->response->add('appliance_id', $this->response->html->request()->get('appliance_id'));
		$this->response->add($this->identifier_name.'[]', '');
	}

	//--------------------------------------------
	/**
	 * Init
	 *
	 * @access public
	 */
	//--------------------------------------------
	function init() {
		$appliance_id = $this->response->html->request()->get('appliance_id');
		if($appliance_id === '') {
			return false;
		}
		// set ENV
		$virtualization = new virtualization();
		$appliance	= new appliance();
		$resource	= new resource();
		$appliance->get_instance_by_id($appliance_id);
		$resource->get_instance_by_id($appliance->resources);
		$virtualization->get_instance_by_id($appliance->virtualization);
		$this->resource		= $resource;
		$this->appliance	= $appliance;
		$this->virtualization = $virtualization;
		$this->statfile_vm = $this->rootdir.'/plugins/vmware-vsphere/vmware-vsphere-stat/'.$resource->ip.'.vm_list';
		$this->statfile_ne = $this->rootdir.'/plugins/vmware-vsphere/vmware-vsphere-stat/'.$resource->ip.'.net_config';
		$this->statfile_ds = $this->rootdir.'/plugins/vmware-vsphere/vmware-vsphere-stat/'.$resource->ip.'.ds_list';
		$this->vmware_mac_base = "00:50:56";
	}

	//--------------------------------------------
	/**
	 * Action
	 *
	 * @access public
	 * @return htmlobject_template
	 */
	//--------------------------------------------
	function action() {
		$this->init();
		$response = $this->vm_remove();
		if(isset($response->msg)) {
			$this->response->redirect(
				$this->response->get_url($this->actions_name, 'edit', $this->message_param, $response->msg)
			);
		}
		if(isset($response->error)) {
			$_REQUEST[$this->message_param] = $response->error;
		}
		$t = $this->response->html->template($this->tpldir.'/vmware-vsphere-vm-remove.tpl.php');
		$t->add($this->response->html->thisfile, "thisfile");
		$t->add(sprintf($this->lang['label'], $this->appliance->name), 'label');
		$t->add($response->form);
		$t->group_elements(array('param_' => 'form'));
		return $t;
	}

	//--------------------------------------------
	/**
	 * Remove VM
	 *
	 * @access public
	 * @return htmlobject_response
	 */
	//--------------------------------------------
	function vm_remove() {
		$response = $this->get_response();
		$data  = $response->html->request()->get($this->identifier_name);
		$form     = $response->form;
		if( $data !== '' ) {
			$submit = $form->get_elements('submit');
			$submit->handler = 'onclick="wait();"';
			$form->add($submit, 'submit');

			$submit = $form->get_elements('cancel');
			$submit->handler = 'onclick="cancel();"';
			$form->add($submit, 'cancel');

			$i = 0;
			foreach($data as $ex) {
				$d['param_f'.$i]['label']                       = $ex;
				$d['param_f'.$i]['object']['type']              = 'htmlobject_input';
				$d['param_f'.$i]['object']['attrib']['type']    = 'checkbox';
				$d['param_f'.$i]['object']['attrib']['name']    = $this->identifier_name.'['.$i.']';
				$d['param_f'.$i]['object']['attrib']['value']   = $ex;
				$d['param_f'.$i]['object']['attrib']['checked'] = true;
				$i++;
			}
			$form->add($d);
			if(!$form->get_errors() && $response->submit()) {
				$errors     = array();
				$message    = array();
				foreach($data as $key => $vm) {
					$command  = $this->htvcenter->get('basedir')."/plugins/vmware-vsphere/bin/htvcenter-vmware-vsphere-vm remove -i ".$this->resource->ip." -n ".$vm;
					$command .= ' --htvcenter-ui-user '.$this->user->name;
					$command .= ' --htvcenter-cmd-mode background';

					// first find the resource to remove while the statfile is still there
					$vm_resource = new resource();
					if(file_exists($this->statfile_vm)) {
						$lines = explode("\n", file_get_contents($this->statfile_vm));
						if(count($lines) >= 1) {
							foreach($lines as $line) {
								if($line !== '') {
									$line = $this->htvcenter->string_to_array($line, '|', '=');
									if (!strcmp($vm, $line['name'])) {
										// we have found the vm in the details, get the mac
										unset($first_mac);
										$first_nic_str = explode(',', $line['macAddress']);
										$first_mac = $first_nic_str[0];
										$vm_resource->get_instance_by_mac($first_mac);
										break;
									}
								}
							}
						}
					}
					# end remove loop
					$appliance    = new appliance();
					$appliances_using_resource = $appliance->get_ids_per_resource($vm_resource->id);
					if (count($appliances_using_resource) > 0) {
						$appliances_using_resource_str = implode(",", $appliances_using_resource[0]);
						$errors[] = sprintf($this->lang['msg_vm_resource_still_in_use'], $vm, $vm_resource->id, $appliances_using_resource_str);
					} else {
						if(file_exists($this->statfile_vm)) {
							unlink($this->statfile_vm);
						}
						$htvcenter_server = new htvcenter_server();
						$htvcenter_server->send_command($command, NULL, true);
						while (!file_exists($this->statfile_vm)) {
							usleep(10000); // sleep 10ms to unload the CPU
							clearstatcache();
						}
						$form->remove($this->identifier_name.'['.$key.']');
						$message[] = sprintf($this->lang['msg_removed'], $vm);

						// for vmware-vsphere-vm-local we have to also remove the image object
						$vm_virtualization = new virtualization();
						$vm_virtualization->get_instance_by_type("vmware-vsphere-vm-local");
						if ($vm_resource->vtype == $vm_virtualization->id) {
							$vm_image = new image();
							$vm_image->remove_by_name($vm);
						}
						// if we have found the resource of the VM remove it
						if ($vm_resource->id > 0) {
							// here we remove the resource of the VM
							$vm_resource->remove($vm_resource->id, $vm_resource->mac);
						}
					}
				}
				if(count($errors) === 0) {
					$response->msg = join('<br>', $message);
				} else {
					$msg = array_merge($errors, $message);
					$response->error = join('<br>', $msg);
				}
			}
		} else {
			$response->msg = '';
		}
		return $response;
	}

	//--------------------------------------------
	/**
	 * Get Response
	 *
	 * @access public
	 * @param string $mode
	 * @return htmlobject_response
	 */
	//--------------------------------------------
	function get_response() {
		$response = $this->response;
		$form = $response->get_form($this->actions_name, 'remove');
		$response->form = $form;
		return $response;
	}


}
?>
