<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha256-CDOy6cOibCWEdsRiZuaHf8dSGGJRYuBGC+mjoJimHGw="
    crossorigin="anonymous"></script>

<script src="https://unpkg.com/htmx.org@2.0.4"
    integrity="sha384-HGfztofotfshcF7+8n44JQL2oJmowVChPTg48S+jvZoztPfvwD79OC/LTtG6dMp+"
    crossorigin="anonymous"></script>

<script>
    htmx.on('htmx:afterRequest', function (evt) {
        if (evt.detail.successful && evt.detail.xhr.getResponseHeader('Content-Type')?.includes('application/json')) {
            const response = JSON.parse(evt.detail.xhr.responseText);
            if (response.html) {
                const target = evt.detail.target;
                target.innerHTML = response.html;

                if (response.pagination) {
                    document.getElementById('pagination-controls').innerHTML = response.pagination;
                }
            }
        }
    });
</script>