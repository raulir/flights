<?php

if (!file_exists('cache')){
	mkdir('cache');
}

// actions

$do = !empty($_POST['do']) ? $_POST['do'] : '';

if ($do == 'save_settings'){
	
	$filename = 'settings.json';
	
	$data = [];
	$data['appid'] = $_POST['appid'];
	$data['key'] = $_POST['key'];
	
	$data = json_encode($data, JSON_PRETTY_PRINT);
	
	file_put_contents($filename, $data);
	
	print($data);
	die();
	
}

?>
<!-- javascript -->

<script src="https://code.jquery.com/jquery-3.4.1.js"></script>

<script type="text/javascript">

setTimeout(init_settings, 1000);

function init_settings(){

	$('.settings_button').on('click', function(){
		save_settings($('.settings_appid').val(), $('.settings_key').val());
	});
	
}

function save_settings(appid, key){
	
	var params = $.extend({'success': function(){}, 'failure': function(){}}, params);
	
	$.ajax({
		type: 'POST',
	  	url: window.location,
	  	dataType: 'json',
	  	data: {
		  	'do': 'save_settings',
		  	'appid': appid,
		  	'key': key
		},
	  	context: this,
	  	success: () => location.reload()
	});
	
}
</script>

<!-- style -->

<style>

	.settings_button {
		cursor: pointer;
	}

</style>

<!-- template -->
Settings:
<div>
	Flightstats APP ID: <input class="settings_appid" type="text" >
</div>
<div>
	Flightstats Key: <input class="settings_key" type="text" >
</div>
<div class="settings_button">[save]</div>