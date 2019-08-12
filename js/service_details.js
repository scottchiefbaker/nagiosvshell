$(document).ready(function() {
	init_toggles();
});

// Nagios API commands: https://assets.nagios.com/downloads/nagioscore/docs/externalcmds/cmdinfo.php?command_id=12

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

		var url = "ajax/ajax.php";
		var options = {
			data: { host: host, service: svc_name, command: cmd },
			method: "POST",
			success: function() {
				console.log("Good to go");

				if (checked == true) {
					$(my_td).removeClass("disabled").addClass("enabled");

					var my_text = $(my_td).text();
					my_text     = my_text.replace("Disabled","Enabled");
					$(my_td).text(my_text);
				} else {
					$(my_td).removeClass("enabled").addClass("disabled");

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
