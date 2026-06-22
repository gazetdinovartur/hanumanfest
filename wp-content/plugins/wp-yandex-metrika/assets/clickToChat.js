jQuery(function($) {
	if (window.wpym === undefined){return;}

	const {counters} = window.wpym;

	$(this).on('ht_ctc_event_analytics', function (e, data) {
		counters.forEach(counter => {
			ym(counter.number, 'reachGoal', 'ym-open-chat');
		});
	});
});