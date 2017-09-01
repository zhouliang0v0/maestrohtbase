<?php
/*
    htvcenter Enterprise developed by htvcenter Enterprise GmbH.

    All source code and content (c) Copyright 2014, htvcenter Enterprise GmbH unless specifically noted otherwise.

    This source code is released under the htvcenter Enterprise Server and Client License, unless otherwise agreed with htvcenter Enterprise GmbH.
    The latest version of this license can be found here: http://htvcenter-enterprise.com/license

    By using this software, you acknowledge having read this license and agree to be bound thereby.

                http://htvcenter-enterprise.com

    Copyright 2014, htvcenter Enterprise GmbH <info@htvcenter-enterprise.com>
*/


function get_highavailability_appliance_edit($appliance_id, $htvcenter, $response) {
	$appliance = new appliance();
	$appliance->get_instance_by_id($appliance_id);
	
	$a = $response->html->a();
	$a->label = '<img title="Highavailability" alt="Highavailability" height="24" width="24" src="'.$htvcenter->get('baseurl').'/plugins/highavailability/img/plugin.png" border="0">';
	$a->href = $htvcenter->get('baseurl').'/index.php?base=appliance&appliance_action=load_edit&aplugin=highavailability&highavailability_action=edit&appliance_id='.$appliance_id;

	if ($appliance->resources == 0 || $appliance->highavailable != '1') {
		$a = "";
	}
	return $a;
}
?>