$(document).ready(() => {
    const $mainRadios = $('input[name="form[show_payment_methods_in_main]"]');
    const $separateRadios = $('input[name="form[show_separate_payment_methods]"]');
    const $mainBlock = $('#simpay-main-methods-block');
    const $separateBlock = $('#simpay-separate-methods-block');
    const $sharedInfo = $('#simpay-methods-shared-info');
    const $footer = $('#simpay-methods-footer');

    const $inputMain = $('input[name$="[payment_methods_list_in_main]"]');
    const $inputSeparate = $('input[name$="[separate_payment_methods_list]"]');
    const $allMain = $('#simpay-all-methods-main');
    const $selectedMain = $('#simpay-selected-methods-main');
    const $allSeparate = $('#simpay-all-methods-separate');
    const $selectedSeparate = $('#simpay-selected-methods-separate');

    const $refreshBtn = $('#simpay-refresh-channels');

    if (
        !$mainRadios.length ||
        !$separateRadios.length ||
        !$mainBlock.length ||
        !$separateBlock.length ||
        !$sharedInfo.length ||
        !$footer.length ||
        !$inputMain.length ||
        !$inputSeparate.length
    ) {
        return;
    }

    const updateVisibility = () => {
        const mainEnabled = $mainRadios.filter(':checked').val() === '1';
        const separateEnabled = $separateRadios.filter(':checked').val() === '1';
        const anyEnabled = mainEnabled || separateEnabled;

        $mainBlock.toggleClass('d-none', !mainEnabled);
        $separateBlock.toggleClass('d-none', !separateEnabled);
        $sharedInfo.toggleClass('d-none', !anyEnabled);
        $footer.toggleClass('d-none', !anyEnabled);
    };

    updateVisibility();
    $mainRadios.on('change', updateVisibility);
    $separateRadios.on('change', function () {
        updateVisibility();

        if ($(this).val() === '1' && $separateBlock.length) {
            $separateBlock.get(0).scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });

    $allMain.add($selectedMain).sortable({
        connectWith: '.simpay-sortable-main',
        placeholder: 'ui-state-highlight',
        tolerance: 'pointer',
        cursor: 'move',
        update: refresh,
        receive: refresh,
        remove: refresh
    });

    $allSeparate.add($selectedSeparate).sortable({
        connectWith: '.simpay-sortable-separate',
        placeholder: 'ui-state-highlight',
        tolerance: 'pointer',
        cursor: 'move',
        update: refresh,
        receive: refresh,
        remove: refresh
    });

    if ($refreshBtn.length) {
        $refreshBtn.on('click', (e) => {
            e.preventDefault();
            loadMethods({ force: true });
        });
    }

    /**
     * Fetch + render methods.
     * @param {{force?: boolean}} opts
     */
    function loadMethods(opts = {}) {
        const force = opts.force === true;

        const url = new URL(window.simpayChannelsUrl, window.location.origin);
        if (force) {
            url.searchParams.set('force', '1');
        }

        $refreshBtn.prop('disabled', true);

        return fetch(url.toString(), { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(json => {
                const ok = (json.success === true) || (json.ok === true);
                if (!ok) throw new Error(json.error || 'Unknown error');

                const payload = json.data || {};
                const availableMain = Array.isArray(payload.available_main) ? payload.available_main : [];
                const selectedMain = (payload.selected_main && typeof payload.selected_main === 'object') ? payload.selected_main : {};
                const availableSeparate = Array.isArray(payload.available_separate) ? payload.available_separate : [];
                const selectedSeparate = (payload.selected_separate && typeof payload.selected_separate === 'object') ? payload.selected_separate : {};

                $allMain.empty();
                availableMain.forEach(c => {
                    $allMain.append(renderItem({ id: c.id, name: c.name }));
                });

                $selectedMain.empty();
                Object.entries(selectedMain).forEach(([id, name]) => {
                    $selectedMain.append(renderItem({ id, name }));
                });

                $allSeparate.empty();
                availableSeparate.forEach(c => {
                    $allSeparate.append(renderItem({ id: c.id, name: c.name }));
                });

                $selectedSeparate.empty();
                Object.entries(selectedSeparate).forEach(([id, name]) => {
                    $selectedSeparate.append(renderItem({ id, name }));
                });

                refresh();
            })
            .catch(err => {
                console.error('Channels fetch failed:', err);
            })
            .finally(() => {
                $refreshBtn.prop('disabled', false);
            });
    }

    function refresh() {
        const mainMap = {};
        const separateMap = {};

        $selectedMain.find('[data-id]').each(function () {
            mainMap[$(this).data('id')] = $(this).data('name');
        });
        $selectedSeparate.find('[data-id]').each(function () {
            separateMap[$(this).data('id')] = $(this).data('name');
        });

        $inputMain.val(JSON.stringify(mainMap));
        $inputSeparate.val(JSON.stringify(separateMap));
    }

    function renderItem(m) {
        return $(`
      <li class="list-group-item d-flex justify-content-between align-items-center" data-name="${escapeAttr(m.name)}" data-id="${escapeAttr(m.id)}">
        <span>${escapeHtml(m.name)}</span>
        <i class="material-icons text-muted">drag_indicator</i>
      </li>
    `);
    }

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
    }

    function escapeAttr(str) {
        return String(str).replace(/"/g, '&quot;');
    }
});