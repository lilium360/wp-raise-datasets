(function () {
	'use strict';

	if (typeof window.raiseDatasetsSettings === 'undefined') {
		return;
	}

	const settings = window.raiseDatasetsSettings;

	const init = () => {
		const containers = document.querySelectorAll('.raise-datasets');
		containers.forEach(setupWidget);
	};

	const setupWidget = (container) => {
		const state = {
			page: 1,
			perPage: parseInt(container.dataset.perPage, 10) || settings.perPage || 10,
			search: ''
		};
		state.perPage = Math.min(Math.max(state.perPage, 1), 50);

		const controlsForm = container.querySelector('.raise-datasets__controls');
		const searchInput = container.querySelector('.raise-datasets__search-input');
		const resultsNode = container.querySelector('.raise-datasets__results');
		const statusNode = container.querySelector('.raise-datasets__status');
		const prevButton = container.querySelector('[data-action="prev"]');
		const nextButton = container.querySelector('[data-action="next"]');

		const buildRequestUrl = (params) => {
			try {
				const url = new URL(settings.apiRoot, window.location.origin);
				params.forEach((value, key) => {
					url.searchParams.set(key, value);
				});
				return url.toString();
			} catch (error) {
				const separator = settings.apiRoot.indexOf('?') === -1 ? '?' : '&';
				return settings.apiRoot + separator + params.toString();
			}
		};

		const truncateText = (value, maxLength) => {
			if (typeof value !== 'string') {
				return '';
			}

			const trimmed = value.trim();
			if (trimmed.length <= maxLength) {
				return trimmed;
			}

			const limit = Math.max(0, maxLength - 3);
			return trimmed.slice(0, limit).trimEnd() + '...';
		};

		const setStatus = (text) => {
			statusNode.textContent = text || '';
		};

		const renderDatasets = (items) => {
			resultsNode.innerHTML = '';

			if (!Array.isArray(items) || items.length === 0) {
				setStatus(settings.i18n.noResults);
				return;
			}

			setStatus('');

			for (const item of items) {
				const card = document.createElement('article');
				card.className = 'raise-datasets__card';

				const title = document.createElement('h3');
				title.className = 'raise-datasets__title';
				title.textContent = item.title || settings.i18n.untitled;
				card.appendChild(title);

				const descriptionText = truncateText(item.description, 256);
				if (descriptionText) {
					const description = document.createElement('p');
					description.className = 'raise-datasets__description';
					description.textContent = descriptionText;
					card.appendChild(description);
				}

				if (item.organization) {
					const organization = document.createElement('p');
					organization.className = 'raise-datasets__organization';

					const label = document.createElement('strong');
					label.textContent = settings.i18n.organization + ':';
					organization.appendChild(label);
					organization.appendChild(document.createTextNode(' ' + item.organization));
					card.appendChild(organization);
				}

				if (item.link) {
					const details = document.createElement('a');
					details.className = 'raise-datasets__link';
					details.href = item.link;
					details.target = '_blank';
					details.rel = 'noopener';
					details.textContent = settings.i18n.viewDetails;
					card.appendChild(details);
				}

				resultsNode.appendChild(card);
			}
		};

		const setLoading = (isLoading) => {
			container.classList.toggle('is-loading', isLoading);
			if (isLoading) {
				setStatus(settings.i18n.loading);
			}
		};

		const updateNavigation = (data) => {
			prevButton.disabled = state.page <= 1;
			const perPage = data && Number.isFinite(Number(data.per_page)) ? Number(data.per_page) : state.perPage;
			const hasMore = data && Object.prototype.hasOwnProperty.call(data, 'has_more')
				? Boolean(data.has_more)
				: Array.isArray(data.items) && data.items.length >= perPage;
			nextButton.disabled = !hasMore;
		};

		const fetchDatasets = async () => {
			setLoading(true);

			const params = new URLSearchParams();
			params.set('page', String(state.page));
			params.set('per_page', String(state.perPage));
			params.set('search', state.search);

			try {
				const requestUrl = buildRequestUrl(params);
				const response = await fetch(requestUrl, {
					credentials: 'same-origin'
				});

				if (!response.ok) {
					throw new Error('Request failed with status ' + response.status);
				}

				const payload = await response.json();
				renderDatasets(payload.items);
				updateNavigation(payload);
			} catch (error) {
				console.error('RAISE datasets request failed:', error);
				resultsNode.innerHTML = '';
				setStatus(settings.i18n.error);
				prevButton.disabled = true;
				nextButton.disabled = true;
			} finally {
				setLoading(false);
			}
		};

		controlsForm.addEventListener('submit', (event) => {
			event.preventDefault();
			state.search = (searchInput.value || '').trim();
			state.page = 1;
			fetchDatasets();
		});

		prevButton.addEventListener('click', () => {
			if (state.page > 1) {
				state.page -= 1;
				fetchDatasets();
			}
		});

		nextButton.addEventListener('click', () => {
			state.page += 1;
			fetchDatasets();
		});

		fetchDatasets();
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
