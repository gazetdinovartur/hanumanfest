jQuery(function($) {
	if (window.wpym === undefined){return;}

	const {counters} = window.wpym;

	const observer = new MutationObserver(function(mutations) {
		mutations.forEach(function(mutation) {
			if(mutation.attributeName === 'style'){
				counters.forEach(counter => {
					ym(counter.number, 'reachGoal', 'ym-subscribe');
				});
			}
		});
	});

// Notify me of style changes
	const observerConfig = {
		attributes: true,
		attributeFilter: ["style"]
	};

	const targetNode = document.querySelector('.mailpoet_validate_success');

	if (!targetNode) return;

	observer.observe(targetNode, observerConfig);
});
