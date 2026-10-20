const STATUSES = {
    PENDING: {variant: 'badge-warning', label: 'Pending'},
    RUNNING: {variant: 'badge-info', label: 'Running'},
    COMPLETE: {variant: 'badge-success', label: 'Complete'},
    FAILED: {variant: 'badge-danger', label: 'Failed'},
};

function describe(status) {
    return STATUSES[status] || {variant: 'badge-secondary', label: status || 'Unknown'};
}

export function badge(status) {
    const spec = describe(status);
    const node = document.createElement('span');
    node.className = 'badge ' + spec.variant;
    node.textContent = spec.label;

    return node;
}

export function paintBadge(node, status) {
    const spec = describe(status);
    node.className = 'badge ' + spec.variant;
    node.textContent = spec.label;
}
