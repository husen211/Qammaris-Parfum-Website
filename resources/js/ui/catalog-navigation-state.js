export function catalogLocationKey(value) {
    const url = new URL(value);
    url.hash = '';
    url.searchParams.sort();
    return url.href;
}

export function matchingPosition(record, catalogUrl) {
    if (!record || record.url !== catalogLocationKey(catalogUrl)
        || !Number.isFinite(record.y) || record.y < 0
        || !Number.isFinite(record.sidebarY) || record.sidebarY < 0) {
        return null;
    }

    return record;
}
