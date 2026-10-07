import {readFileSync} from 'node:fs';
import {test} from 'node:test';
import assert from 'node:assert/strict';
import {searchScore, matchingOptions} from '../../resources/js/ui/search-matcher.js';

test('browser and server share typo/numeric/identifier/negative examples', () => {
    const cases = JSON.parse(readFileSync(new URL('../fixtures/search-cases.json', import.meta.url)));
    for (const entry of cases) assert.equal(searchScore(entry.term, entry.texts, entry.identifiers) !== null, entry.matches, entry.term);
});

test('manual choices stay selected; fuzzy discovery never selects a target', () => {
    const options = [{value:'',text:'Pilih produk'}, {value:'1',text:'Reveria Aqua'}, {value:'2',text:'Reverie Aqua'}, {value:'3',text:'CHNO'}];
    assert.deepEqual(matchingOptions(options, 'reverie').map(o => o.value), ['', '2', '1']);
    assert.deepEqual(matchingOptions(options, 'rverie', '3').map(o => o.value), ['', '3', '2']);
    assert.equal(options[3].value, '3');
});
