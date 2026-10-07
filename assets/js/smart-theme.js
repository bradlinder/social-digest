(function() {
    var forcedMode = (window.sdThemeConfig && window.sdThemeConfig.mode) ? window.sdThemeConfig.mode : 'auto';

    function getLuminance(rgbStr) {
        if (!rgbStr || rgbStr === 'transparent' || rgbStr.indexOf('rgba(0, 0, 0, 0)') === 0) return null;
        var match = rgbStr.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
        if (!match) return null;
        var r = parseInt(match[1], 10) / 255;
        var g = parseInt(match[2], 10) / 255;
        var b = parseInt(match[3], 10) / 255;
        return (0.2126 * r) + (0.7152 * g) + (0.0722 * b);
    }

    function detectSiteTheme() {
        if (forcedMode === 'dark') return 'dark';
        if (forcedMode === 'light') return 'light';

        var docEl = document.documentElement;
        var body = document.body || docEl;

        // 1. Check explicit dark selectors on html/body
        var darkSelectors = [
            '.dark', '.dark-theme', '.dark-mode', '.theme-dark', '.is-dark-theme', '.night-mode',
            '[data-theme="dark"]', '[data-bs-theme="dark"]', '[data-color-scheme="dark"]', '[data-theme-mode="dark"]',
            '[data-theme*="dark"]', '[class*="dark-theme"]', '[class*="theme-dark"]'
        ];
        for (var i = 0; i < darkSelectors.length; i++) {
            try {
                if (docEl.matches(darkSelectors[i]) || (body && body.matches(darkSelectors[i]))) {
                    return 'dark';
                }
            } catch(e) {}
        }

        // 2. Check explicit light selectors on html/body
        var lightSelectors = [
            '.light', '.light-theme', '.light-mode', '.theme-light', '.is-light-theme', '.day-mode',
            '[data-theme="light"]', '[data-bs-theme="light"]', '[data-color-scheme="light"]', '[data-theme-mode="light"]'
        ];
        for (var j = 0; j < lightSelectors.length; j++) {
            try {
                if (docEl.matches(lightSelectors[j]) || (body && body.matches(lightSelectors[j]))) {
                    return 'light';
                }
            } catch(e) {}
        }

        // 3. Computed background luminance check of body / article / container
        var elementsToCheck = [
            body,
            docEl,
            document.querySelector('main'),
            document.querySelector('article'),
            document.querySelector('.entry-content'),
            document.querySelector('.post-content')
        ];

        for (var k = 0; k < elementsToCheck.length; k++) {
            var el = elementsToCheck[k];
            if (!el) continue;
            try {
                var bg = window.getComputedStyle(el).backgroundColor;
                var lum = getLuminance(bg);
                if (lum !== null) {
                    // Luminance < 0.35 represents a dark background; >= 0.35 represents a light background
                    return (lum < 0.35) ? 'dark' : 'light';
                }
            } catch(e) {}
        }

        // 4. Default to 'light' for websites (prevents dark cards on standard white WordPress themes)
        return 'light';
    }

    function updateSocialCardsTheme() {
        var theme = detectSiteTheme();
        var cards = document.querySelectorAll('div.social-post.social-card');
        for (var i = 0; i < cards.length; i++) {
            var card = cards[i];
            card.setAttribute('data-social-theme', theme);
            if (theme === 'dark') {
                card.classList.add('is-dark');
                card.classList.remove('is-light');
            } else {
                card.classList.add('is-light');
                card.classList.remove('is-dark');
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateSocialCardsTheme);
    } else {
        updateSocialCardsTheme();
    }
    window.addEventListener('load', updateSocialCardsTheme);

    // Listen for system preference changes
    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', updateSocialCardsTheme);
    }

    // MutationObserver to adapt dynamically to site dark-mode toggle buttons
    if (window.MutationObserver) {
        var observer = new MutationObserver(function() {
            updateSocialCardsTheme();
        });
        if (document.documentElement) {
            observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme', 'data-bs-theme', 'data-color-scheme', 'style'] });
        }
        if (document.body) {
            observer.observe(document.body, { attributes: true, attributeFilter: ['class', 'data-theme', 'data-bs-theme', 'data-color-scheme', 'style'] });
        }
    }
})();
