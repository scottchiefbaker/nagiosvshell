/*nav dropdown functions */

/* browser detection */
if (document.layers) {
	visible = "show";
	hidden = "hide";
}
if (document.all || document.getElementById) {
	visible = "visible";
	hidden = "hidden";
}

function showDropdown(id)
{
	if (document.layers)
	{
		//alert("there are layers");
		menu = document.layers[id];
	}
	if(document.getElementById)
	{
		menu = document.getElementById(id);
	}
	if(menu)
	{
		//alert(menu);
		menu.style.display = "block";
	}

}

function hideDropdown(id)
{
	menu = document.getElementById(id);
	if(menu)
	{
		//alert(menu);
		menu.style.display = "none";
	}

}
/*this function toggles the grids and configuration tables */
function showHide(id)
{
	//alert(id);
	var divID = "#"+id;
	$(divID).slideToggle("fast");


}
/*this function hides the grids and configuration tables that can be toggled*/
function hide()
{
	//alert("this is a functional alert");
	$(".hidden").hide();
}

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
