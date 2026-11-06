$(document).ready(function() {
	init_toggles();
	init_checks();
	init_ack();
});

function init_checks() {
	$(".recheck_now").on('click', function() {
		check_now($(this));
	});
}

function init_ack() {
	$("#ack_form").on('submit', function(e) {
		e.preventDefault();
		ack_problem($(this));
	});
}

function ack_problem(form) {
	var error_box = $("#ack_error");
	var submit    = $("button[type=submit]", form);
	var data      = form.serialize() + "&" + $.param({
		host:    $("#host").data("value"),
		service: $("#service").data("value"),
		command: "ACKNOWLEDGE_SVC_PROBLEM"
	});

	error_box.addClass("d-none");
	submit.prop("disabled", true);

	$.ajax("ajax/ajax.php", {
		data: data,
		method: "POST",
		success: function() {
			var modal = bootstrap.Modal.getInstance(document.getElementById("ack_modal"));
			if (modal) {
				modal.hide();
			}

			setTimeout(function() {
				location.reload();
			}, 1500);
		},
		error: function(xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.msg) || "Unable to send acknowledgement";

			error_box.text(msg).removeClass("d-none");
			submit.prop("disabled", false);
		}
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
