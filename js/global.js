/*nav dropdown functions */

$(document).ready(function() {
	init_notification_toggle();

	// Anything related to the log history section
	init_log_history();
});

function init_toggles() {
	$(".input_toggle").on("click", function() {
		var checked  = $(this).prop('checked');
		var cmd      = $(this).data('cmd');
		var host     = $("#host").data("value");
		var svc_name = $("#service").data("value");

		var my_tr = $(this).closest("tr");
		var my_td = $("td:first", my_tr);

		if (checked == true) {
			cmd = "ENABLE" + cmd;
		} else {
			cmd = "DISABLE" + cmd;
		}

		var orig_obj = $(this);

		var url = "ajax/ajax.php";
		var options = {
			data: { host: host, service: svc_name, command: cmd },
			method: "POST",
			success: function(e) {
				var errors = e.errors;

				if (errors !== 0) {
					alert("An error occurred in the AJAX response. Check the console for more details");
					console.log(e);

					// Uncheck the thing we were trying to toggle
					$(orig_obj).prop("checked", !checked);

					return false;
				}

				if (checked == true) {
					$(my_td).removeClass("bg-disabled").addClass("bg-enabled");

					var my_text = $(my_td).text();
					my_text     = my_text.replace("Disabled","Enabled");
					$(my_td).text(my_text);
				} else {
					$(my_td).removeClass("bg-enabled").addClass("bg-disabled");

					var my_text = $(my_td).text();
					my_text     = my_text.replace("Enabled","Disabled");
					$(my_td).text(my_text);
				}
			},
			error: function() {
				alert("An error occurred");
			}
		};

		$.ajax(url,options);
	});
}

function init_notification_toggle() {
	$(".notification-menu").on('click', function(e) {
		e.stopPropagation();
	});

	var on_class  = "btn-success";
	var off_class = "btn-danger";

	$(".notification-toggle").on("click", function() {
		var is_enabled = $(this).hasClass(on_class);

		var url = "ajax/ajax.php";
		var options = {
			data: {},
			method: "POST",
			success: function(e) {
			},
			error: function() {
				alert("An error occurred");
			}
		};

		if (is_enabled) {
			$(this).html("Off");
			$(this).removeClass(on_class).addClass(off_class).blur().data("on", false);

			options['data']['command'] = "DISABLE_NOTIFICATIONS";
		} else {
			$(this).html("On");
			$(this).addClass(on_class).removeClass(off_class).blur().data("on", true);;
			options['data']['command'] = "ENABLE_NOTIFICATIONS";
		}

		$.ajax(url,options);
	});
}

function init_log_history() {
	// Hide the plus/expand sign if there are less than 10 entries
	var log_count = $('.log_item').length;
	if (log_count <= 10) {
		$('.show_more_log').hide();
	}

	// Show all the log entries when clicked
	$(".show_more_log").css('cursor', 'pointer').click(function() {
		show_all_logs();
		$(this).hide(); // Remove the show more link
		location.href = "#log_history";
	});
}

function show_all_logs() {
	$('.log_item').removeClass('d-none');
}
