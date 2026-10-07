export function normalizeSearch(value) {
    return (String(value ?? '').slice(0, 8000).normalize('NFKD').replace(/\p{M}/gu, '').toLowerCase().match(/\p{L}+|\p{N}+/gu) ?? []).join(' ');
}

function editDistance(a, b) {
    let previous = Array.from({length: b.length + 1}, (_, i) => i);
    for (let i = 1; i <= a.length; i++) {
        const row = [i];
        for (let j = 1; j <= b.length; j++) {
            row[j] = Math.min(row[j - 1] + 1, previous[j] + 1, previous[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
        }
        previous = row;
    }
    let distance = previous[b.length];
    if (distance === 2 && a.length === b.length) {
        for (let i = 0; i < a.length - 1; i++) {
            if (`${a.slice(0, i)}${a[i + 1]}${a[i]}${a.slice(i + 2)}` === b) return 1;
        }
    }
    return distance;
}

export function searchScore(term, texts, identifiers = []) {
    term = typeof term === 'string' ? term.trim().slice(0, 100) : '';
    const needle = normalizeSearch(term);
    if (!needle) return term ? null : 0;
    if (identifiers.some(id => id != null && String(id).trim().toLowerCase() === term.toLowerCase())) return 0;
    const fields = texts.map(normalizeSearch);
    const words = [...new Set(fields.join(' ').split(' '))];
    let score = 0;
    for (const part of new Set(needle.split(' '))) {
        let best = null;
        for (const word of words) {
            let cost;
            if (word === part) cost = 0;
            else if (!/^\d+$/.test(part) && part.length >= 2 && word.startsWith(part)) cost = 2;
            else if (!/^\d+$/.test(part) && part.length >= 3 && word.includes(part)) cost = 3;
            else {
                const limit = part.length >= 8 ? 2 : (part.length >= 4 ? 1 : 0);
                if (!limit || !/^[a-z]+$/.test(part) || !/^[a-z]+$/.test(word) || Math.abs(part.length - word.length) > limit) continue;
                const distance = editDistance(part, word);
                if (distance > limit) continue;
                cost = 10 + distance;
            }
            best = best === null ? cost : Math.min(best, cost);
            if (best === 0) break;
        }
        if (best === null) return null;
        score += best;
    }
    return score * 10 + (fields.includes(needle) ? 0 : 1);
}

export function matchingOptions(options, term, current = '') {
    return options.map(option => ({...option, score: searchScore(term, [option.text])}))
        .filter(option => !option.value || option.value === current || option.score !== null)
        .sort((a, b) => (a.score ?? -1) - (b.score ?? -1));
}
