/* LMS Admin — official DataTables (https://datatables.net/)
 * Pinned CDN version 3.1.5; do not initialize non-tabular layouts.
 * Search/pagination are client-side over all rows in the current PHP status/role filter.
 */
(function () {
    'use strict';
    function boot() {
        if (typeof window.DataTable !== 'function') {
            // The HTML table remains readable if the CDN is blocked or offline.
            console.warn('LMS: DataTables could not load. Check internet connectivity or CDN access.');
            return;
        }
        document.querySelectorAll('table[data-admin-datatable]').forEach(function (table) {
            if (table.dataset.dtInitialized === 'true') return;
            const compact = table.dataset.compact === 'true';
            const noSort = Array.from(table.tHead ? table.tHead.querySelectorAll('th') : [])
                .flatMap(function (cell, index) { return cell.hasAttribute('data-dt-order') ? [index] : []; });
            const options = {
                autoWidth: false,
                order: [],  // Keep PHP's newest-first / chosen initial ordering.
                paging: !compact,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                searching: !compact,
                info: !compact,
                scrollX: !compact,
                language: {
                    search: 'Search records:',
                    searchPlaceholder: 'Type to filter…',
                    lengthMenu: '_MENU_ rows per page',
                    emptyTable: table.dataset.empty || 'No records available.',
                    zeroRecords: 'No matching records found.'
                },
                columnDefs: noSort.length ? [{ targets: noSort, orderable: false, searchable: false }] : [],
                layout: compact ? { topStart: null, topEnd: null, bottomStart: null, bottomEnd: null } : {
                    topStart: 'pageLength',
                    topEnd: 'search',
                    bottomStart: 'info',
                    bottomEnd: 'paging'
                }
            };
            if (!compact && table.dataset.search) {
                options.search = { search: table.dataset.search };
            }
            try {
                new window.DataTable(table, options);
                table.dataset.dtInitialized = 'true';
            } catch (error) {
                console.error('LMS: Failed to initialize admin DataTable.', error);
            }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
