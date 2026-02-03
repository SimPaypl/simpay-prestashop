$(document).ready(() => {
    const $radios = $('input[name="form[show_payment_methods_in_main]"]');
    const $block = $('#simpay-methods-block');

    const $inputOrder = $('input[name$="[payment_methods_list_in_main]"]');
    const $all = $('#simpay-all-methods');
    const $selected = $('#simpay-selected-methods');

    const $refreshBtn = $('#simpay-refresh-channels');

    if (!$radios.length || !$block.length || !$inputOrder.length) return;

    // show/hide block
    const updateVisibility = () => {
        const value = $radios.filter(':checked').val();
        $block.toggleClass('d-none', value !== '1');
    };

    updateVisibility();
    $radios.on('change', updateVisibility);

    $all.add($selected).sortable({
        connectWith: '.simpay-sortable',
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
                const available = Array.isArray(payload.available) ? payload.available : [];
                const selectedMap = (payload.selected && typeof payload.selected === 'object') ? payload.selected : {};

                // render AVAILABLE (left)
                $all.empty();
                available.forEach(c => {
                    $all.append(renderItem({ id: c.id, name: c.name }));
                });

                // render SELECTED (right)
                $selected.empty();
                Object.entries(selectedMap).forEach(([id, name]) => {
                    $selected.append(renderItem({ id, name }));
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
        const map = {};

        $selected.find('[data-id]').each(function () {
            map[$(this).data('id')] = $(this).data('name');
        });

        $inputOrder.val(JSON.stringify(map));
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