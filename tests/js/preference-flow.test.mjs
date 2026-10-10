import test from 'node:test';
import assert from 'node:assert/strict';
import {activeQuestionKeys, effectiveAnswers, sweetnessBranch, validBudget} from '../../resources/js/ui/preference-flow.js';

test('sweet avoidance skips the question and ignores a previous sweet answer', () => {
    const answers = {avoid_sweet:true, avoid:[], sweetness:'sweet'};
    assert.deepEqual(sweetnessBranch(answers), {skipped:true,value:'none'});
    assert.equal(effectiveAnswers(answers).sweetness, 'none');
    assert.deepEqual(activeQuestionKeys(['avoid','sweetness','projection'],answers), ['avoid','projection']);
});
test('gourmand avoidance skips without equating dessert to every sweet smell', () => {
    const answers = {avoid:['gourmand'], sweetness:'sweet'};
    assert.equal(effectiveAnswers(answers).sweetness, 'any');
    assert.equal(sweetnessBranch({...answers,avoid_sweet:true}).value,'none');
    assert.deepEqual(activeQuestionKeys(['avoid','sweetness','projection'], {avoid:[]}), ['avoid','sweetness','projection']);
});
test('budgets validate range bounds and distinguish open maximum from missing input', () => {
    assert.equal(validBudget(100000,200000,false),true);
    assert.equal(validBudget(300000,200000,false),false);
    assert.equal(validBudget(1000000,null,true),true);
    assert.equal(validBudget(0,null,false),false);
    assert.equal(validBudget(0,200000.5,false),false);
});
