function send_modal_command(form, modal_id, error_id, command, fail_msg) {
	var error_box = $("#" + error_id);
	var submit    = $("button[type=submit]", form);
	var params    = {
		host:    $("#host").data("value"),
		command: command
	};

	if ($("#service").length) {
		params.service = $("#service").data("value");
	}

	var data = form.serialize() + "&" + $.param(params);

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
