import postcss from 'postcss';

// Positive hover selectors change UI on hover. Negated hover also defines base
// visibility in DaisyUI, so moving it inside a mouse query would break touch UI.
export function activatesHover(selector) {
    const functions = [];
    let quote = null;
    let brackets = 0;
    for (let i = 0; i < selector.length; i++) {
        const char = selector[i];
        if (char === '\\') { i++; continue; }
        if (quote) { if (char === quote) quote = null; continue; }
        if (char === '"' || char === "'") { quote = char; continue; }
        if (char === '[') brackets++;
        if (char === ']') brackets--;
        if (brackets) continue;
        if (char === '(') functions.push(selector.slice(0, i).match(/:([\w-]+)$/)?.[1] ?? '');
        if (char === ')') functions.pop();
        if (/^:hover(?![\w-])/.test(selector.slice(i))
            && functions.filter((name) => name === 'not').length % 2 === 0) return true;
    }
    return false;
}

export function guardHover(css) {
    const root = postcss.parse(css);
    root.walkRules((rule) => {
        let guardedByMouse = false;
        for (let parent = rule.parent; parent; parent = parent.parent) {
            if (parent.type === 'atrule' && parent.name === 'media'
                && /^\(\s*hover\s*:\s*hover\s*\)\s+and\s+\(\s*pointer\s*:\s*fine\s*\)$/.test(parent.params)) {
                guardedByMouse = true;
            }
        }
        if (guardedByMouse) return;
        const hoverSelectors = rule.selectors.filter(activatesHover);
        if (!hoverSelectors.length) return;
        const otherSelectors = rule.selectors.filter((selector) => !activatesHover(selector));
        const guarded = postcss.atRule({ name: 'media', params: '(hover: hover) and (pointer: fine)' });
        guarded.append(rule.clone({ selectors: hoverSelectors }));
        rule.after(guarded);
        if (otherSelectors.length) rule.selectors = otherSelectors;
        else rule.remove();
    });
    return root.toString();
}

export default function mouseOnlyHover() {
    return {
        name: 'qammaris-mouse-only-hover',
        enforce: 'pre',
        // Runs after Tailwind's pre-transform, before Vite emits hashed CSS.
        transform(code, id) {
            if (!id.split('?')[0].endsWith('.css')) return;
            return { code: guardHover(code), map: null };
        },
    };
}
