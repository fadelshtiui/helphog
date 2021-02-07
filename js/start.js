$(function () {
	window.addEventListener("load", function () {
		$(".button").click(function () {
			$(".button").addClass("onclic", 250, validate);
		});

		function validate() {
			setTimeout(function () {
				$(".button").removeClass("onclic");
				$(".button").addClass("validate", 450, callback);
			}, 2250);
		}

		function callback() {
			setTimeout(function () {
				$(".button").removeClass("validate", 250, begun);
			}, 1250);
		}

		function begun() {
			$(".button").addClass("inprogress");
			$(".button").value = "STOP";
			$(".button").addEventListener("click", function () {
				$(".button").addClass("onclic", 250, validate);
			});
		}
	});
});