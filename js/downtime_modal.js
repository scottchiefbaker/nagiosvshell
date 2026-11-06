$(document).ready(function() {
	init_downtime();
});

function init_downtime() {
	$("#downtime_modal").on('show.bs.modal', function(e) {
		var command = $(e.relatedTarget).data("command") || "SCHEDULE_SVC_DOWNTIME";

		$("#downtime_form").data("command", command);
		$("#downtime_scope").text(command === "SCHEDULE_AND_PROPAGATE_HOST_DOWNTIME" ? "(and all services)" : "");
		set_downtime_defaults();
	});

	$("input[name=fixed]").on('change', toggle_downtime_fixed);

	$("#downtime_form").on('submit', function(e) {
		e.preventDefault();
		send_modal_command($(this), "downtime_modal", "downtime_error", $(this).data("command") || "SCHEDULE_SVC_DOWNTIME", "Unable to schedule downtime");
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
