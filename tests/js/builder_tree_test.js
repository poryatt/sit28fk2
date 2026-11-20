'use strict';
// Node test for public/assets/js/builder/builder-tree.js (Part 9, P4.3).
// Loads the module in a vm sandbox with a stubbed window.BuilderRegistry and
// checks locate/count/height helpers, every checkInsert message, and parity
// with includes/builder/tree_validator.php (limits + exact Persian strings).
//
// Expected Persian strings are parsed out of the two source files rather than
// retyped here, so parity is checked char-for-char without the test file
// becoming a second, drift-prone copy of the text.
//
// Run: node tests/js/builder_tree_test.js   (exit 0 = pass)

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const ROOT = path.resolve(__dirname, '..', '..');
const TREE_JS_PATH = path.join(ROOT, 'public', 'assets', 'js', 'builder', 'builder-tree.js');
const VALIDATOR_PATH = path.join(ROOT, 'includes', 'builder', 'tree_validator.php');

const TREE_JS = fs.readFileSync(TREE_JS_PATH, 'utf8');
const VALIDATOR_PHP = fs.readFileSync(VALIDATOR_PATH, 'utf8');

let failures = 0;
function check(name, cond, detail) {
  if (cond) {
    console.log('ok   - ' + name);
  } else {
    failures++;
    console.log('FAIL - ' + name + (detail === undefined ? '' : ' :: ' + JSON.stringify(detail)));
  }
}

function loadTree() {
  const registryStub = {
    getBlockType: function (type) {
      const defs = {
        text: { allowsChildren: false, allowedChildTypes: [], minChildren: 0, maxChildren: 0, allowedParentTypes: [] },
        button: { allowsChildren: false, allowedChildTypes: [], minChildren: 0, maxChildren: 0, allowedParentTypes: [] }
      };
      return Object.prototype.hasOwnProperty.call(defs, type) ? defs[type] : null;
    }
  };
  const sandbox = { window: { BuilderRegistry: registryStub }, console: console };
  sandbox.globalThis = sandbox;
  vm.runInNewContext(TREE_JS, sandbox, { filename: 'builder-tree.js' });
  return sandbox.window.BuilderTree;
}

function uid(ch) {
  return ch.repeat(32);
}

// Parse the V_* message constants declared in builder-tree.js.
function extractVarMessages(src) {
  const out = {};
  const re = /var\s+(V_[A-Z_]+)\s*=\s*'([^']*)'\s*;/g;
  let m;
  while ((m = re.exec(src)) !== null) {
    out[m[1]] = m[2];
  }
  return out;
}

const BT = loadTree();
const V = extractVarMessages(TREE_JS);

check('module exposes window.BuilderTree', BT !== null && typeof BT === 'object');
['checkInsert', 'countNodes', 'deepestDepth', 'isStructuralName', 'isStructuralRootName',
 'limits', 'locate', 'subtreeHeight', 'subtreeSize'].forEach(function (m) {
  check('has documented member ' + m, m in BT);
});

const expectedVars = ['V_BAD_PLACE', 'V_BAD_ROOT', 'V_COUNT', 'V_OFF_ROOT', 'V_TOO_DEEP', 'V_TOO_MANY', 'V_UNKNOWN_TYPE'];
check('builder-tree.js defines the seven rule messages', Object.keys(V).sort().join(',') === expectedVars.join(','), Object.keys(V).sort());

// ---- fixtures ----
const text1 = { uid: uid('c'), type: 'text', data: {}, children: [] };
const button1 = { uid: uid('d'), type: 'button', data: {}, children: [] };
const text2 = { uid: uid('m'), type: 'text', data: {}, children: [] };
const col1 = { uid: uid('g'), type: 'column', data: {}, children: [text1] };
const col2 = { uid: uid('h'), type: 'column', data: {}, children: [button1] };
const containerNode = { uid: uid('f'), type: 'column_container', data: {}, children: [col1, col2] };
const bodyNode = { uid: uid('b'), type: 'body', data: {}, children: [containerNode, text2] };
const headerNode = { uid: uid('a'), type: 'header', data: {}, children: [] };
const footerNode = { uid: uid('e'), type: 'footer', data: {}, children: [] };
const tree = [headerNode, bodyNode, footerNode];

// ---- locate ----
let loc = BT.locate(tree, uid('c'));
check('locate finds nested uid', loc !== null && loc.node === text1);
check('locate reports depth (body=1..text=4)', loc && loc.depth === 4, loc && loc.depth);
check('locate reports parent', loc && loc.parent === col1);
check('locate reports index', loc && loc.index === 0);
let locRoot = BT.locate(tree, uid('b'));
check('locate root child has null parent', locRoot && locRoot.parent === null && locRoot.depth === 1);
check('locate missing uid returns null', BT.locate(tree, uid('z')) === null);
check('locate null tree returns null', BT.locate(null, uid('a')) === null);

// ---- counts / heights ----
check('countNodes', BT.countNodes(tree) === 9, BT.countNodes(tree));
check('countNodes(null) = 0', BT.countNodes(null) === 0);
check('subtreeSize(leaf) = 1', BT.subtreeSize(text1) === 1);
check('subtreeSize(container) = 5', BT.subtreeSize(containerNode) === 5);
check('subtreeHeight(leaf) = 0', BT.subtreeHeight(text1) === 0);
check('subtreeHeight(container) = 2', BT.subtreeHeight(containerNode) === 2);
check('deepestDepth(container,1) = 3', BT.deepestDepth(containerNode, 1) === 3);

// ---- structural helpers ----
check('isStructuralRootName header', BT.isStructuralRootName('header') === true);
check('isStructuralRootName text', BT.isStructuralRootName('text') === false);
check('isStructuralName column', BT.isStructuralName('column') === true);
check('isStructuralName column_container', BT.isStructuralName('column_container') === true);
check('isStructuralName text', BT.isStructuralName('text') === false);

// ---- checkInsert: every message ----
check('checkInsert legal insert -> null', BT.checkInsert(tree, 'text', bodyNode, 1, 1, 0) === null);
check('checkInsert unknown type', BT.checkInsert(tree, 'nope', bodyNode, 1, 1, 0) === V.V_UNKNOWN_TYPE);
check('checkInsert structural off-root', BT.checkInsert(tree, 'header', bodyNode, 1, 1, 0) === V.V_OFF_ROOT);
check('checkInsert null parent -> bad root', BT.checkInsert(tree, 'text', null, 0, 1, 0) === V.V_BAD_ROOT);
check('checkInsert leaf parent -> count', BT.checkInsert(tree, 'text', text1, 2, 1, 0) === V.V_COUNT);
check('checkInsert container over max -> count', BT.checkInsert(tree, 'column', { uid: uid('k'), type: 'column_container', data: {}, children: [text1, text1, text1, text1] }, 1, 1, 0) === V.V_COUNT);
check('checkInsert bad place (allowedParents)', BT.checkInsert(tree, 'column', bodyNode, 1, 1, 0) === V.V_BAD_PLACE);
check('checkInsert bad place (childTypes)', BT.checkInsert(tree, 'text', containerNode, 1, 1, 0) === V.V_BAD_PLACE);
check('checkInsert too many', BT.checkInsert(tree, 'text', bodyNode, 1, 500, 0) === V.V_TOO_MANY);
check('checkInsert too deep', BT.checkInsert(tree, 'text', bodyNode, 8, 1, 0) === V.V_TOO_DEEP);

// ---- parity with tree_validator.php ----
const maxBlocks = /MAX_BLOCKS\s*=\s*(\d+)/.exec(VALIDATOR_PHP);
const maxDepth = /MAX_DEPTH\s*=\s*(\d+)/.exec(VALIDATOR_PHP);
check('validator MAX_BLOCKS parsed', !!maxBlocks);
check('validator MAX_DEPTH parsed', !!maxDepth);
check('limits.maxBlocks parity', maxBlocks && BT.limits.maxBlocks === parseInt(maxBlocks[1], 10), BT.limits.maxBlocks);
check('limits.maxDepth parity', maxDepth && BT.limits.maxDepth === parseInt(maxDepth[1], 10), BT.limits.maxDepth);

const validatorStrings = [];
const re = /\$errors\[\]\s*=\s*'([^']*)'/g;
let m;
while ((m = re.exec(VALIDATOR_PHP)) !== null) {
  validatorStrings.push(m[1]);
}
expectedVars.forEach(function (name) {
  check('mirrored message present in validator: ' + name, validatorStrings.indexOf(V[name]) !== -1);
});

if (failures > 0) {
  console.log('\n' + failures + ' check(s) failed.');
  process.exit(1);
}
console.log('\nAll builder-tree checks passed.');
