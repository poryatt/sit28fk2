/* Visual page builder — client-side core helpers (Part 0).
 * Pure data and pure functions: no DOM access, no UI, no library.
 * Exposes exactly one global: window.BuilderCore.
 */
(function () {
  'use strict';

  function randomHex(count) {
    var out = '';
    var chars = '0123456789abcdef';
    if (typeof crypto !== 'undefined' && crypto.getRandomValues) {
      var bytes = new Uint8Array(count);
      crypto.getRandomValues(bytes);
      for (var i = 0; i < count; i++) {
        out += chars.charAt(bytes[i] % 16);
      }
      return out;
    }
    for (var j = 0; j < count; j++) {
      out += chars.charAt(Math.floor(Math.random() * 16));
    }
    return out;
  }

  function createUid() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
      return crypto.randomUUID().replace(/-/g, '').toLowerCase();
    }
    return randomHex(32);
  }

  function getDefinition(type) {
    var registry = window.BuilderRegistry;
    if (!registry) {
      throw new Error('BuilderCore: BuilderRegistry is not loaded');
    }
    var definition = registry.getBlockType(type);
    if (!definition) {
      throw new Error('BuilderCore: unknown block type: ' + type);
    }
    return definition;
  }

  function createNode(type) {
    var definition = getDefinition(type);
    return {
      uid: createUid(),
      type: type,
      data: definition.defaultData(),
      children: []
    };
  }

  function createColumnContainer(count) {
    if (typeof count !== 'number' || Math.floor(count) !== count || count < 2 || count > 4) {
      throw new Error('BuilderCore: column count must be an integer between 2 and 4');
    }
    var node = createNode('column_container');
    for (var i = 0; i < count; i++) {
      node.children.push(createNode('column'));
    }
    return node;
  }

  function defaultTree() {
    return [createNode('header'), createNode('body'), createNode('footer')];
  }

  function walkTree(tree, callback) {
    function visit(nodes, parentNode, depth) {
      for (var index = 0; index < nodes.length; index++) {
        var node = nodes[index];
        callback(node, parentNode, depth, index);
        if (node.children && node.children.length) {
          visit(node.children, node, depth + 1);
        }
      }
    }
    visit(tree, null, 1);
  }

  function findByUid(tree, uid) {
    var found = null;
    walkTree(tree, function (node) {
      if (found === null && node.uid === uid) {
        found = node;
      }
    });
    return found;
  }

  window.BuilderCore = {
    createUid: createUid,
    createNode: createNode,
    createColumnContainer: createColumnContainer,
    defaultTree: defaultTree,
    walkTree: walkTree,
    findByUid: findByUid
  };
})();
