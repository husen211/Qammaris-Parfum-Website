import {matchingOptions} from './search-matcher';

document.querySelectorAll('[data-target-search]').forEach(input => {
    const select = document.getElementById(input.dataset.targetSearch);
    const options = Array.from(select.options).map(option => ({value: option.value, text: option.text, element: option.cloneNode(true)}));
    input.addEventListener('input', () => {
        const current = select.value;
        const matches = matchingOptions(options, input.value, current);
        select.replaceChildren(...matches.map(option => {
            const node = option.element.cloneNode(true);
            node.selected = option.value === current;
            return node;
        }));
    });
});
