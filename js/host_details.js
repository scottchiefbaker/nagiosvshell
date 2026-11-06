$(document).ready(function() {
	init_toggles();
	init_disable_svc_notifications();
	init_ack();
	init_schedule_svc_checks();
});

function init_ack() {
	$("#host_ack_form").on('submit', function(e) {
		e.preventDefault();
		send_modal_command($(this), "host_ack_modal", "host_ack_error", "ACKNOWLEDGE_HOST_PROBLEM", "Unable to send acknowledgement");
	});
}

function init_schedule_svc_checks() {
	$(".schedule_host_svc_checks").on("click", function(e) {
		e.preventDefault();

		if (!confirm("Schedule a check for all services on this host?")) {
			return;
		}

		var host = $("#host").data("value");

		$.ajax("ajax/ajax.php", {
			data: { host: host, command: "SCHEDULE_HOST_SVC_CHECKS" },
			method: "POST",
			success: function(e) {
				if (parseInt(e.errors)) {
					alert("Some error occurred");
				} else {
					alert("Command Succeeded");
				}
			},
			error: function() {
				alert("An error occurred");
			}
		});
	});
}

function init_disable_svc_notifications() {
	$(".disable_svc_notifications").on("click", function(e) {
		e.preventDefault();

		if (!confirm("Disable notifications for all services on this host?")) {
			return;
		}

		var host = $("#host").data("value");

		$.ajax("ajax/ajax.php", {
			data: { host: host, command: "DISABLE_HOST_SVC_NOTIFICATIONS" },
			method: "POST",
			success: function(e) {
				if (parseInt(e.errors)) {
					alert("Some error occurred");
				} else {
					alert("Command Succeeded");
				}
			},
			error: function() {
				alert("An error occurred");
			}
		});
	});
}

// Nagios API commands: https://assets.nagios.com/downloads/nagioscore/docs/externalcmds/cmdinfo.php?command_id=12
