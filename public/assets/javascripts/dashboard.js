function Dashboard() {
	$('.join-group').click(function(event) {
		event.preventDefault();
		if ($(this).data('href')) {
			window.location = $(this).data('href');
		}
	});
};

var Trailer = Trailer || {};
$(document).ready(function() {
	Trailer.Dashboard = new Dashboard();
});
