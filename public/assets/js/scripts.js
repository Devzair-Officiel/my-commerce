(function ($) {
	'use strict';

	// -----------------------------
	// Helpers
	// -----------------------------
	const $win = $(window);
	const $doc = $(document);

	const rafThrottle = (fn) => {
		let ticking = false;
		return function (...args) {
			if (ticking) return;
			ticking = true;
			window.requestAnimationFrame(() => {
				fn.apply(this, args);
				ticking = false;
			});
		};
	};

	// -----------------------------
	// 01. LOADING
	// -----------------------------
	$win.on('load', function () {
		window.setTimeout(function () {
			const $preloader = $('.preloader');
			if (!$preloader.length) return;

			$preloader.delay(700).fadeOut(700, function () {
				$preloader.addClass('loaded');
			});
		}, 800);
	});

	// -----------------------------
	// 02. BACKGROUND IMAGE
	// -----------------------------
	$('.background_bg').each(function () {
		const src = $(this).attr('data-img-src');
		if (src) $(this).css('background-image', `url(${src})`);
	});

	// -----------------------------
	// 03. ANIMATION (Waypoints)
	// -----------------------------
	(function initAnimations() {
		if (!$.fn.waypoint) return;

		function ckScrollInit($items, $trigger) {
			$items.each(function () {
				const $el = $(this);
				const animationClass = $el.attr('data-animation');
				const animationDelay = $el.attr('data-animation-delay') || '0s';

				$el.css({
					'-webkit-animation-delay': animationDelay,
					'-moz-animation-delay': animationDelay,
					'animation-delay': animationDelay,
					opacity: 0,
				});

				const $wpTrigger = $trigger && $trigger.length ? $trigger : $el;

				$wpTrigger.waypoint(
					function () {
						$el.addClass('animated').addClass(animationClass).css('opacity', '1');
					},
					{ triggerOnce: true, offset: '90%' }
				);
			});
		}

		ckScrollInit($('.animation'));
		ckScrollInit($('.staggered-animation'), $('.staggered-animation-wrap'));
	})();

	// -----------------------------
	// 04. MENU (sticky + dropdowns)
	// -----------------------------
	(function initMenu() {
		const $headerFixed = $('header.fixed-top');
		const $headerWrap = $('.header_wrap');
		if (!$headerWrap.length) return;

		// Sticky placeholder (évite le jump)
		let $stickyBar = $('.header_sticky_bar');

		const shouldCreateStickyBar =
			!$stickyBar.length &&
			$headerWrap.hasClass('fixed-top') &&
			!$headerWrap.hasClass('transparent_header') &&
			!$headerWrap.hasClass('no-sticky');

		if (shouldCreateStickyBar) {
			$headerWrap.before('<div class="header_sticky_bar"></div>');
			$stickyBar = $('.header_sticky_bar');
		}

		const setStickyBarHeight = () => {
			if (!$stickyBar.length) return;
			$stickyBar.css({ height: $headerWrap.outerHeight() });
		};

		const updateStickyState = () => {
			const scrollY = window.scrollY || $win.scrollTop();
			const shouldFix = scrollY >= 150;

			if ($headerFixed.length) {
				if ($headerFixed.hasClass('no-sticky')) {
					$headerFixed.removeClass('nav-fixed');
				} else {
					$headerFixed.toggleClass('nav-fixed', shouldFix);
				}
			}

			setStickyBarHeight();
		};

		window.addEventListener('scroll', rafThrottle(updateStickyState), { passive: true });
		$win.on('load resize', function () {
			setStickyBarHeight();
			updateStickyState();
		});

		// Dropdown submenu toggler (delegated)
		$doc.on('click', '.dropdown-menu a.dropdown-toggler', function (e) {
			e.preventDefault();

			const $link = $(this);
			const $nextMenu = $link.next('.dropdown-menu');
			if (!$nextMenu.length) return;

			if (!$nextMenu.hasClass('show')) {
				$link.parents('.dropdown-menu').first().find('.show').removeClass('show');
			}

			$nextMenu.toggleClass('show');
			$link.parent('li').toggleClass('show');

			// Nettoyage quand bootstrap dropdown se ferme
			$link.parents('li.nav-item.dropdown.show').one('hidden.bs.dropdown', function () {
				$('.dropdown-menu .show').removeClass('show');
			});
		});

		// Hide navbar after clicking links (page-scroll)
		$doc.on('click', '.header_wrap .navbar-collapse ul li a.page-scroll', function () {
			const $collapse = $headerWrap.find('.navbar-collapse');
			if ($collapse.hasClass('show')) $collapse.collapse('hide');
			$('header').removeClass('active');
		});

		// Burger toggler -> class active + ferme search overlay si ouvert
		$doc.on('click', '.navbar-toggler', function () {
			$('header').toggleClass('active');

			const $searchOverlay = $('.search-overlay');
			if ($searchOverlay.hasClass('open')) {
				$searchOverlay.removeClass('open');
				$('.search_trigger').removeClass('open');
			}
		});

		// Side toggle
		$doc.on('click', '.sidetoggle', function () {
			$(this).addClass('open');
			$('body').addClass('sidetoggle_active');
			$('.sidebar_menu').addClass('active');

			if (!$('#header-overlay').length) {
				$('body').append('<div id="header-overlay" class="header-overlay"></div>');
			}
		});

		$doc.on('click', '#header-overlay, .sidemenu_close', function (e) {
			e.preventDefault();

			$('.sidetoggle').removeClass('open');
			$('body').removeClass('sidetoggle_active');
			$('.sidebar_menu').removeClass('active');

			$('#header-overlay').fadeOut(300, function () {
				$(this).remove();
			});
		});

		// Categories / Side navbar togglers
		$doc.on('click', '.categories_btn', function () {
			$('.side_navbar_toggler').attr('aria-expanded', 'false');
			$('#navbarSidetoggle').removeClass('show');
		});

		$doc.on('click', '.side_navbar_toggler', function () {
			$('.categories_btn').attr('aria-expanded', 'false');
			$('#navCatContent').removeClass('show');
		});

		// Product search trigger
		$doc.on('click', '.pr_search_trigger', function () {
			$(this).toggleClass('show');
			$('.product_search_form').toggleClass('show');
		});

		// Click outside close categories (plus robuste via stopPropagation)
		$doc.on('click', '.categories_btn, #navCatContent, #navbarSidetoggle .navbar-nav, .side_navbar_toggler', function (e) {
			e.stopPropagation();
		});

		$doc.on('click', function () {
			$('.categories_btn').addClass('collapsed');
			$('.categories_btn,.side_navbar_toggler').attr('aria-expanded', 'false');
			$('#navCatContent,#navbarSidetoggle').removeClass('show');
		});
	})();

	// -----------------------------
	// 05. SMOOTH SCROLLING + SCROLLSPY "maison"
	// -----------------------------
	(function initSmoothScroll() {
		const $menuItems = $('.header_wrap').find('a.page-scroll');
		if (!$menuItems.length) return;

		let lastId = '';
		const getHeaderOffset = () => {
			const topHeaderHeight = $('.top-header').innerHeight() || 0;
			const mainHeaderHeight = $('.header_wrap').innerHeight() || 0;
			return mainHeaderHeight - topHeaderHeight - 20;
		};

		$doc.on('click', 'a.page-scroll[href*="#"]:not([href="#"])', function (e) {
			const href = this.getAttribute('href');
			if (!href) return;

			// On-page only
			if (
				location.pathname.replace(/^\//, '') !== this.pathname.replace(/^\//, '') ||
				location.hostname !== this.hostname
			) {
				return;
			}

			const $target = $(this.hash).length ? $(this.hash) : $(`[name="${this.hash.slice(1)}"]`);
			if (!$target.length) return;

			e.preventDefault();

			$('a.page-scroll.active').removeClass('active');
			$(this).closest('.page-scroll').addClass('active');

			const speed = $(this).data('speed') || 800;
			$('html, body').animate({ scrollTop: $target.offset().top - getHeaderOffset() }, speed);
		});

		const onScrollSpy = () => {
			const topMenuHeight = ($('.header_wrap').innerHeight() || 0) + 20;
			const fromTop = $win.scrollTop() + topMenuHeight;

			const scrollItems = $menuItems
				.map(function () {
					const sel = $(this).attr('href');
					if (!sel || sel.charAt(0) !== '#') return null;
					const $it = $(sel);
					return $it.length ? $it : null;
				})
				.get();

			let currentId = '';
			for (let i = 0; i < scrollItems.length; i++) {
				if ($(scrollItems[i]).offset().top < fromTop) currentId = scrollItems[i].id;
			}

			if (currentId && lastId !== currentId) {
				lastId = currentId;
				$menuItems.closest('.page-scroll').removeClass('active');
				$menuItems.filter(`[href="#${currentId}"]`).closest('.page-scroll').addClass('active');
			}
		};

		window.addEventListener('scroll', rafThrottle(onScrollSpy), { passive: true });
		$win.on('load resize', rafThrottle(onScrollSpy));
	})();

	// More categories
	$('.more_slide_open').slideUp();
	$doc.on('click', '.more_categories', function () {
		$(this).toggleClass('show');
		$('.more_slide_open').slideToggle();
	});

	// -----------------------------
	// 06. SEARCH
	// -----------------------------
	(function initSearch() {
		const $searchWrap = $('.search_wrap');
		if (!$searchWrap.length) return;

		if (!$('.search_overlay').length) $searchWrap.after('<div class="search_overlay"></div>');

		$doc.on('click', '.close-search', function (e) {
			e.stopPropagation();
			$('.search_wrap,.search_overlay').removeClass('open');
			$('body').removeClass('search_open');
		});

		$doc.on('click', '.search_trigger', function (e) {
			e.preventDefault();
			e.stopPropagation();
			$('.search_wrap,.search_overlay').toggleClass('open');
			$('body').toggleClass('search_open');

			const $collapse = $('.navbar-collapse');
			if ($collapse.hasClass('show')) {
				$collapse.removeClass('show');
				$('.navbar-toggler').addClass('collapsed').attr('aria-expanded', 'false');
			}
		});

		// empêcher fermeture quand on clique dans le formulaire
		$doc.on('click', '.search_wrap form', function (e) {
			e.stopPropagation();
		});

		// click outside => close
		$doc.on('click', function () {
			$('body').removeClass('open');
			$('.search_wrap,.search_overlay').removeClass('open');
			$('body').removeClass('search_open');
		});
	})();

	// -----------------------------
	// 07. SCROLLUP (fusionné sur 1 handler scroll global possible)
	// -----------------------------
	(function initScrollUp() {
		const $btn = $('.scrollup');
		if (!$btn.length) return;

		const toggle = () => {
			$btn.toggle($win.scrollTop() > 150);
		};

		window.addEventListener('scroll', rafThrottle(toggle), { passive: true });
		$win.on('load', toggle);

		$doc.on('click', '.scrollup', function (e) {
			e.preventDefault();
			$('html, body').animate({ scrollTop: 0 }, 600);
		});
	})();

	// 08. PARALLAX — lib non chargée, skip

	// 09. MASONRY (Isotope) — lib non chargée sur cette page, skip

	// -----------------------------
	// 10. SLIDERS (Owl + Slick)
	// -----------------------------
	function carousel_slider(context) {
		if (!$.fn.owlCarousel) return;
		const $scope = context ? $(context) : $doc;
		$scope.find('.carousel_slider').each(function () {
			const $carousel = $(this);
			$carousel.owlCarousel({
				dots: $carousel.data('dots'),
				loop: $carousel.data('loop'),
				items: $carousel.data('items'),
				margin: $carousel.data('margin'),
				mouseDrag: $carousel.data('mouse-drag'),
				touchDrag: $carousel.data('touch-drag'),
				autoHeight: $carousel.data('autoheight'),
				center: $carousel.data('center'),
				nav: $carousel.data('nav'),
				rewind: $carousel.data('rewind'),
				navText: ['<i class="fa fa-chevron-left"></i>', '<i class="fa fa-chevron-right"></i>'],
				autoplay: $carousel.data('autoplay'),
				animateIn: $carousel.data('animate-in'),
				animateOut: $carousel.data('animate-out'),
				autoplayTimeout: $carousel.data('autoplay-timeout'),
				smartSpeed: $carousel.data('smart-speed'),
				responsive: $carousel.data('responsive'),
			});
		});
	}

	function slick_slider(context) {
		if (!$.fn.slick) return;
		const $scope = context ? $(context) : $doc;
		$scope.find('.slick_slider').each(function () {
			const $slick = $(this);
			$slick.slick({
				arrows: $slick.data('arrows'),
				dots: $slick.data('dots'),
				infinite: $slick.data('infinite'),
				centerMode: $slick.data('center-mode'),
				vertical: $slick.data('vertical'),
				fade: $slick.data('fade'),
				cssEase: $slick.data('css-ease'),
				autoplay: $slick.data('autoplay'),
				verticalSwiping: $slick.data('vertical-swiping'),
				autoplaySpeed: $slick.data('autoplay-speed'),
				speed: $slick.data('speed'),
				pauseOnHover: $slick.data('pause-on-hover'),
				draggable: $slick.data('draggable'),
				slidesToShow: $slick.data('slides-to-show'),
				slidesToScroll: $slick.data('slides-to-scroll'),
				asNavFor: $slick.data('as-nav-for'),
				focusOnSelect: $slick.data('focus-on-select'),
				responsive: $slick.data('responsive'),
			});
		});
	}

	$doc.ready(function () {
		carousel_slider();
		slick_slider();
	});

	// 11. Contact form géré côté Symfony — pas de JS nécessaire ici

	// 12. POPUPS (Magnific) — chargé uniquement sur la page produit via block page_scripts

	// -----------------------------
	// 13. Select dropdown states
	// -----------------------------
	$('select').each(function () {
		const $el = $(this);

		if ($el.val() === '') $el.addClass('first_null');
		if (!$el.val()) $el.addClass('not_chosen');

		$el.on('change', function () {
			$el.toggleClass('not_chosen', !$el.val());
		});
	});

	// 14. FITVIDS — lib non chargée, skip
	// 15. msDropdown — lib non chargée, skip
	// 16. MAP — lib non chargée, skip
	// 17. COUNTDOWN — lib non chargée, skip

	// -----------------------------
	// 18. List/Grid (attention: $container doit exister)
	// -----------------------------
	$doc.on('click', '.shorting_icon', function () {
		const $icon = $(this);
		const $shop = $('.shop_container');
		if (!$shop.length) return;

		if ($icon.hasClass('grid')) {
			$shop.removeClass('list').addClass('grid');
		} else if ($icon.hasClass('list')) {
			$shop.removeClass('grid').addClass('list');
		}

		$icon.addClass('active').siblings().removeClass('active');

		$shop.append('<div class="loading_pr"><div class="mfp-preloader"></div></div>');
		window.setTimeout(function () {
			$('.loading_pr').remove();
			// Si tu utilises Isotope sur la boutique, utilise l’instance réelle ici
			if ($shop.data('isotope')) $shop.isotope('layout');
		}, 800);
	});

	// -----------------------------
	// 19. Tooltips / Popovers (Bootstrap)
	// -----------------------------
	$doc.ready(function () {
		if ($.fn.tooltip) $('[data-toggle="tooltip"]').tooltip({ trigger: 'hover' });
		if ($.fn.popover) $('[data-toggle="popover"]').popover();
	});

	// -----------------------------
	// 20. Product color / size
	// -----------------------------
	$('.product_color_switch span').each(function () {
		const c = $(this).attr('data-color');
		if (c) $(this).css('background-color', c);
	});

	$doc.on('click', '.product_color_switch span, .product_size_switch span', function () {
		$(this).addClass('active').siblings().removeClass('active');
	});

	// -----------------------------
	// 21. Quickview zoom + gallery
	// -----------------------------
	(function initZoomAndGallery() {
		const $image = $('#product_img');
		if (!$image.length) return;

		function switchGalleryImage($thumb) {
			const newSrc     = $thumb.attr('data-image');
			const newZoomSrc = $thumb.attr('data-zoom-image') || newSrc;
			if (!newSrc) return;

			$image.attr('src', newSrc);

			if ($.fn.elevateZoom) {
				const ez = $image.data('elevateZoom');
				if (ez) ez.swaptheimage(newSrc, newZoomSrc);
			}

			$('#pr_item_gallery .product_gallery_item').removeClass('active');
			$thumb.addClass('active');
			$('.product_img_zoom').attr('href', newZoomSrc);
		}

		// ── Clic sur une vignette galerie ────────────────────────────────
		// On utilise mousedown/mouseup sur le document pour contourner
		// l'interception de slick (draggable) qui annule parfois les clicks.
		var galleryDragging = false;

		$doc.on('mousedown touchstart', '#pr_item_gallery .product_gallery_item', function () {
			galleryDragging = false;
		});
		$doc.on('mousemove touchmove', '#pr_item_gallery', function () {
			galleryDragging = true;
		});
		$doc.on('mouseup touchend', '#pr_item_gallery .product_gallery_item', function (e) {
			e.preventDefault();
			if (!galleryDragging) {
				switchGalleryImage($(this));
			}
			galleryDragging = false;
		});

		// ── Initialisation elevateZoom ────────────────────────────────────
		if ($.fn.elevateZoom) {
			$image.elevateZoom({
				cursor: 'crosshair',
				easing: true,
				zoomType: 'inner',
			});
		}

		// ── Lightbox custom (simple, fiable, sans dépendance) ────────────
		var $lightbox = null;

		function buildLightbox() {
			if ($lightbox) return;
			$lightbox = $('<div id="nm-lightbox" role="dialog" aria-modal="true" aria-label="Image agrandie">' +
				'<button class="nm-lightbox__close" aria-label="Fermer">&#x2715;</button>' +
				'<div class="nm-lightbox__prev" aria-label="Précédent">&#8249;</div>' +
				'<div class="nm-lightbox__next" aria-label="Suivant">&#8250;</div>' +
				'<img class="nm-lightbox__img" src="" alt="">' +
			'</div>');
			$('body').append($lightbox);

			// Fermer sur clic du fond (pas sur l'image)
			$lightbox.on('click', function (e) {
				if ($(e.target).is('#nm-lightbox')) closeLightbox();
			});
			$lightbox.on('click', '.nm-lightbox__close', closeLightbox);
			$lightbox.on('click', '.nm-lightbox__prev', function () { navigateLightbox(-1); });
			$lightbox.on('click', '.nm-lightbox__next', function () { navigateLightbox(1); });
		}

		var lightboxImages = [];
		var lightboxIndex  = 0;

		function openLightbox(index) {
			buildLightbox();
			lightboxImages = $('#pr_item_gallery .product_gallery_item').map(function () {
				return { src: $(this).attr('data-zoom-image'), alt: $(this).find('img').attr('alt') || '' };
			}).get();
			lightboxIndex = index ?? 0;
			renderLightbox();
			$lightbox.addClass('nm-lightbox--open');
			$('.zoomContainer').css('visibility', 'hidden');
			$('body').css('overflow', 'hidden');
			// Focus sur le bouton fermer pour l'accessibilité
			$lightbox.find('.nm-lightbox__close').focus();
		}

		function closeLightbox() {
			if ($lightbox) $lightbox.removeClass('nm-lightbox--open');
			$('.zoomContainer').css('visibility', '');
			$('body').css('overflow', '');
		}

		function navigateLightbox(dir) {
			lightboxIndex = (lightboxIndex + dir + lightboxImages.length) % lightboxImages.length;
			renderLightbox();
		}

		function renderLightbox() {
			var item = lightboxImages[lightboxIndex];
			if (!item) return;
			$lightbox.find('.nm-lightbox__img').attr('src', item.src).attr('alt', item.alt);
			$lightbox.find('.nm-lightbox__prev, .nm-lightbox__next').toggle(lightboxImages.length > 1);
		}

		// Touche Échap
		$doc.on('keydown', function (e) {
			if (!$lightbox || !$lightbox.hasClass('nm-lightbox--open')) return;
			if (e.key === 'Escape')     closeLightbox();
			if (e.key === 'ArrowLeft')  navigateLightbox(-1);
			if (e.key === 'ArrowRight') navigateLightbox(1);
		});

		$doc.on('click', '.product_img_zoom', function (e) {
			e.preventDefault();
			var activeIndex = $('#pr_item_gallery .product_gallery_item.active').closest('.item').index();
			openLightbox(Math.max(0, activeIndex));
		});
	})();

	// qty +/- (optimisation: ne manipule que le lien concerné si possible)
	function updateAddToCartHref($btn, qty) {
		if (!$btn || !$btn.length) return;
		const href = $btn.attr('href');
		if (!href) return;

		const parts = href.split('/');
		parts.pop();
		$btn.attr('href', `${parts.join('/')}/${qty}`);
	}

	$doc.on('click', '.plus', function () {
		const $input = $(this).prev('input');
		if (!$input.length) return;

		const v = parseInt($input.val(), 10);
		if (!Number.isFinite(v)) return;

		const next = v + 1;
		$input.val(next);

		updateAddToCartHref($('a.btn-addtocart'), next);
	});

	$doc.on('click', '.minus', function () {
		const $input = $(this).next('input');
		if (!$input.length) return;

		const v = parseInt($input.val(), 10);
		if (!Number.isFinite(v) || v <= 1) return;

		const next = v - 1;
		$input.val(next);

		updateAddToCartHref($('a.btn-addtocart'), next);
	});

	// 22. PRICE FILTER (jQuery UI slider) — lib non chargée, skip

	// -----------------------------
	// 23. RATING STAR
	// -----------------------------
	$doc.on('click', '.star_rating span', function () {
		const onStar = parseFloat($(this).data('value'));
		if (!Number.isFinite(onStar)) return;

		const $stars = $(this).parent().children('.star_rating span');
		$stars.removeClass('selected');
		$stars.slice(0, onStar).addClass('selected');
	});

	// -----------------------------
	// 24. CHECKBOX TOGGLES
	// -----------------------------
	$('.create-account,.different_address').hide();

	$doc.on('change', '#createaccount', function () {
		$('.create-account')[$(this).is(':checked') ? 'slideDown' : 'slideUp']();
	});

	$doc.on('change', '#differentaddress', function () {
		$('.different_address')[$(this).is(':checked') ? 'slideDown' : 'slideUp']();
	});

	// -----------------------------
	// 25. Payment option
	// -----------------------------
	$doc.on('change', '[name="payment_option"]', function () {
		const value = $(this).attr('value');
		$('.payment-text').slideUp();
		$(`[data-method="${value}"]`).slideDown();
	});

	// -----------------------------
	// 26. Onload popup
	// -----------------------------
	$win.on('load', function () {
		window.setTimeout(function () {
			if ($('#onload-popup').length) $('#onload-popup').modal('show');
		}, 3000);
	});
})(jQuery);
