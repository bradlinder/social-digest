(function() {
    function setupGalleryImages() {
        // Upgrade any unlinked gallery images in legacy posts so lightbox plugins or native fallback can inspect them
        var posts = document.querySelectorAll('.social-post');
        posts.forEach(function(post, pIdx) {
            var imgs = post.querySelectorAll('.social-embed-images img, .social-embed-media img');
            if (!imgs.length) return;

            var autoGroup = 'lightbox-gallery-auto-' + pIdx;
            imgs.forEach(function(img) {
                var parentAnchor = img.closest('a');
                var alt = img.getAttribute('alt') || '';
                if (parentAnchor) {
                    if (!parentAnchor.getAttribute('data-rel')) {
                        parentAnchor.setAttribute('data-rel', autoGroup);
                        parentAnchor.setAttribute('rel', autoGroup);
                        parentAnchor.classList.add('social-lightbox-trigger', 'rl-gallery-link');
                        parentAnchor.setAttribute('data-rl_title', alt);
                        parentAnchor.setAttribute('data-rl_caption', alt);
                    }
                } else {
                    var wrap = document.createElement('a');
                    wrap.className = 'social-lightbox-trigger rl-gallery-link';
                    wrap.setAttribute('data-rel', autoGroup);
                    wrap.setAttribute('rel', autoGroup);
                    wrap.setAttribute('title', alt || 'View image');
                    wrap.setAttribute('data-rl_title', alt);
                    wrap.setAttribute('data-rl_caption', alt);
                    var bestSrc = img.currentSrc || img.src || '';
                    var srcset = img.getAttribute('srcset');
                    if (srcset) {
                        var parts = srcset.split(',');
                        var maxW = 0;
                        parts.forEach(function(part) {
                            var pair = part.trim().split(/\s+/);
                            if (pair.length >= 2) {
                                var w = parseInt(pair[1], 10);
                                if (w > maxW) {
                                    maxW = w;
                                    bestSrc = pair[0];
                                }
                            }
                        });
                    }
                    wrap.href = bestSrc;
                    wrap.style.cssText = 'display:block; width:100%; height:100%; text-decoration:none; cursor:zoom-in;';
                    if (img.parentNode) {
                        img.parentNode.insertBefore(wrap, img);
                        wrap.appendChild(img);
                    }
                }
            });
        });

        // If Responsive Lightbox is active, trigger its doResponsiveLightbox event to ensure
        // dynamically discovered or legacy anchors are bound to the site-wide effect
        if (window.jQuery && window.rlArgs) {
            try {
                window.jQuery(document).trigger({
                    type: 'doResponsiveLightbox',
                    script: window.rlArgs.script,
                    selector: window.rlArgs.selector || 'lightbox',
                    args: window.rlArgs
                });
                if (window.rlArgs.customEvents) {
                    window.jQuery(document).trigger(window.rlArgs.customEvents);
                }
            } catch(e) {}
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupGalleryImages);
    } else {
        setupGalleryImages();
    }
    window.addEventListener('load', setupGalleryImages);
    setTimeout(setupGalleryImages, 250);
})();
