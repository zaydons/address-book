// Applies the light or dark theme, following the device setting unless the user has chosen one
// This is loaded in the <head> so that the theme is applied before the page is shown, avoiding a flash of the wrong theme
(function() {
	// Key used to remember the user's choice in the browser
	var KEY = 'address-book-theme';
	// The choices which the theme button cycles through
	var CHOICES = ['auto', 'dark', 'light'];
	// Used if the browser doesn't allow storage, such as in some private browsing modes
	var fallback = 'auto';
	// Whether the device is set to dark mode
	var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
	
	// Get the user's choice of theme
	function getChoice() {
		try {
			var choice = window.localStorage.getItem(KEY);
			return CHOICES.indexOf(choice) === -1 ? 'auto' : choice;
		} catch(e) {
			return fallback;
		}
	}
	
	// Remember the user's choice of theme
	function setChoice(choice) {
		fallback = choice;
		try {
			window.localStorage.setItem(KEY, choice);
		} catch(e) {}
	}
	
	// Set the theme on the page, which the CSS uses to choose colours
	function apply() {
		var choice = getChoice();
		var dark = choice === 'dark' || (choice === 'auto' && media !== null && media.matches);
		document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
		document.documentElement.setAttribute('data-theme-choice', choice);
	}
	
	apply();
	
	// Follow changes to the device setting while the page is open
	if(media !== null) {
		if(media.addEventListener) {
			media.addEventListener('change', apply);
		} else if(media.addListener) {
			media.addListener(apply);
		}
	}
	
	// The theme button moves to the next choice
	document.addEventListener('click', function(event) {
		var button = event.target.closest ? event.target.closest('.navbar-theme') : null;
		if(!button) {
			return;
		}
		var next = CHOICES[(CHOICES.indexOf(getChoice()) + 1) % CHOICES.length];
		setChoice(next);
		apply();
	});
})();
