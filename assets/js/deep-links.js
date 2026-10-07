(function() {
    function initSocialDeepLinks() {
        document.addEventListener('click', function(e) {
            var badge = e.target.closest('a.social-badge[data-app-url]');
            if (!badge) return;

            var appUrl = badge.getAttribute('data-app-url');
            var webUrl = badge.getAttribute('href');
            if (!appUrl) return;

            // Only intercept on mobile / tablet touch devices
            var isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent || '');
            if (!isMobile) return;

            e.preventDefault();

            var appOpened = false;
            var onVisibilityChange = function() {
                if (document.hidden || document.webkitHidden) {
                    appOpened = true;
                }
            };
            var onBlurOrHide = function() {
                appOpened = true;
            };

            document.addEventListener('visibilitychange', onVisibilityChange, { once: true });
            window.addEventListener('pagehide', onBlurOrHide, { once: true });
            window.addEventListener('blur', onBlurOrHide, { once: true });

            var start = Date.now();

            // Attempt to launch native app via custom URI scheme
            window.location.href = appUrl;

            // Fallback: If device has not switched focus to native app, open browser URL
            setTimeout(function() {
                document.removeEventListener('visibilitychange', onVisibilityChange);
                window.removeEventListener('pagehide', onBlurOrHide);
                window.removeEventListener('blur', onBlurOrHide);

                if (!appOpened && (Date.now() - start < 2000)) {
                    window.open(webUrl, '_blank', 'noopener,noreferrer');
                }
            }, 800);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSocialDeepLinks);
    } else {
        initSocialDeepLinks();
    }
})();
