<!--
/*
    htvcenter Enterprise developed by htvcenter Enterprise GmbH.

    All source code and content (c) Copyright 2014, htvcenter Enterprise GmbH unless specifically noted otherwise.

    This source code is released under the htvcenter Enterprise Server and Client License, unless otherwise agreed with htvcenter Enterprise GmbH.
    The latest version of this license can be found here: http://htvcenter-enterprise.com/license

    By using this software, you acknowledge having read this license and agree to be bound thereby.

                http://htvcenter-enterprise.com

    Copyright 2014, htvcenter Enterprise GmbH <info@htvcenter-enterprise.com>
*/
-->
<h2>Select Volume group</h2>

<div id="form" class="gaugetable">
	<div style="float:left; width:380px; margin: 15px 0 0 15px;">
		<div><b>{lang_id}</b>: <span id="storageid">{id}</span></div>
		<div><b>{lang_name}</b>: {name}</div>
		<div><b>{lang_resource}</b>: {resource}</div>
		<!--<div><b>{lang_deployment}</b>: {deployment}</div>-->
		<div><b>{lang_state}</b>: {state}</div>
	</div>
	<div style="float:right; margin: 15px 20px 0 0;">
		<div id="volumepopupbtn">{add}</div>
	</div>
	<div style="clear:both; margin: 0 0 25px 0;" class="floatbreaker">&#160;</div>
	<div id="gaugeajaxside">
		<div id="gauger">
		{table}
		</div>
	</div>

	
</div>

<div id="volumepopup" class="modal-dialog">
<div class="panel">
					
								<!-- Classic Form Wizard -->
								<!--===================================================-->
								<div id="demo-cls-wz">
					
									<!--Nav-->
									<ul class="wz-nav-off wz-icon-inline wz-classic">
										<li class="col-xs-3 bg-info active">
											<a href="#demo-cls-tab1" data-toggle="tab" aria-expanded="true">
												<span class="icon-wrap icon-wrap-xs bg-trans-dark"><i class="fa fa-hdd-o"></i></span> New Volume
											</a>
										</li>
										<div class="volumepopupclass"><a id="volumepopupclose"><i class="fa fa-icon fa-close"></i></a></div>
										
									</ul>
					
									<!--Progress bar-->
									<div class="progress progress-sm progress-striped active">
										<div class="progress-bar progress-bar-info" style="width: 100%;"></div>
									</div>
					
					
									<!--Form-->
									<form class="form-horizontal mar-top">
										<div class="panel-body">
											<div class="tab-content">
					
												<!--First tab-->
												<div class="tab-pane active in" id="demo-cls-tab1">
													<div id="storageform">
													</div>
												</div>
					
												
											</div>
										</div>
					
					
									</form>
								</div>
								<!--===================================================-->
								<!-- End Classic Form Wizard -->
					
							</div>
</div>
