$(document).ready(function() {
	init_toggles();
	init_checks();
	init_ack();
	init_downtime();
});

function init_checks() {
	$(".recheck_now").on('click', function() {
		check_now($(this));
	});
}

function init_ack() {
	$("#ack_form").on('submit', function(e) {
		e.preventDefault();
		send_svc_command($(this), "ack_modal", "ack_error", "ACKNOWLEDGE_SVC_PROBLEM", "Unable to send acknowledgement");
	});
}

function init_downtime() {
	$("#downtime_modal").on('show.bs.modal', set_downtime_defaults);
	$("input[name=fixed]").on('change', toggle_downtime_fixed);

	$("#downtime_form").on('submit', function(e) {
		e.preventDefault();
		send_svc_command($(this), "downtime_modal", "downtime_error", "SCHEDULE_SVC_DOWNTIME", "Unable to schedule downtime");
	});
}

function set_downtime_defaults() {
	var start = new Date();
	var end   = new Date(start.getTime() + 2 * 60 * 60 * 1000);

	$("#downtime_start").val(local_datetime(start));
	$("#downtime_end").val(local_datetime(end));
	$("#downtime_flexible").prop("checked", true);
	toggle_downtime_fixed();
}

function toggle_downtime_fixed() {
	var fixed = $("#downtime_fixed").is(":checked");

	$("#downtime_end_wrap").toggleClass("d-none", !fixed);
	$("#downtime_duration_wrap").toggleClass("d-none", fixed);
	$("#downtime_end").prop("disabled", !fixed).prop("required", fixed);
	$("#downtime_duration").prop("disabled", fixed);
}

function local_datetime(d) {
	var pad = function(n) { return String(n).padStart(2, "0"); };

	return d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate()) +
		"T" + pad(d.getHours()) + ":" + pad(d.getMinutes());
}

function send_svc_command(form, modal_id, error_id, command, fail_msg) {
	var error_box = $("#" + error_id);
	var submit    = $("button[type=submit]", form);
	var data      = form.serialize() + "&" + $.param({
		host:    $("#host").data("value"),
		service: $("#service").data("value"),
		command: command
	});

	error_box.addClass("d-none");
	submit.prop("disabled", true);

	$.ajax("ajax/ajax.php", {
		data: data,
		method: "POST",
		success: function() {
			var modal = bootstrap.Modal.getInstance(document.getElementById(modal_id));
			if (modal) {
				modal.hide();
			}

			setTimeout(function() {
				location.reload();
			}, 1500);
		},
		error: function(xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.msg) || fail_msg;

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
