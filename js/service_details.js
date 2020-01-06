$(document).ready(function() {
	init_toggles();
	init_checks();
});

function init_checks() {
	$(".recheck_now").on('click', function() {
		check_now($(this));
	});
}

function check_now(elem) {
	var service  = $("#service").data('value');
	var host     = $("#host").data('value');
	var unixtime = parseInt(new Date().getTime() / 1000);

	var url  = "ajax/ajax.php";
	var opts = {
		data: { 'host': host, 'service': service, 'command': 'SCHEDULE_FORCED_SVC_CHECK', 'extra': unixtime},
		method: 'POST',
		async: false,
		success: function(e) {
			var errors = parseInt(e.errors);

			if (!errors) {
				alert("Command Succeeded");
			} else {
				alert("Some error occurred");
			}
		},
		error: function(e) {
			var errors = parseInt(e.errors);

			alert("Some error state occurred");
		}
	};

	$.ajax(url, opts);
}

// Nagios API commands: https://assets.nagios.com/downloads/nagioscore/docs/externalcmds/cmdinfo.php?command_id=12
