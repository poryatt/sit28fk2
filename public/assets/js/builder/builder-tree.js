/* Visual page builder — pure shared tree rules (Part 9).
 *
 * No DOM, no module state, no work at load time. Every function receives the
 * tree (or a node) as an argument; the only external read is
 * window.BuilderRegistry, and only at call time. Mirrors the client-side
 * constraints of includes/builder/tree_validator.php (max block count, max
 * depth, allowed parent/child types) so any engine can reuse them.
 *
 * Exposes exactly one global: window.BuilderTree.
 */
(function () {
  'use strict';

  var ROOT_DEPTH = 1;
  var TYPE_HEADER = 'header';
  var TYPE_BODY = 'body';
  var TYPE_FOOTER = 'footer';
  var TYPE_COLUMN = 'column';
  var TYPE_CONTAINER = 'column_container';

  var MAX_BLOCKS = 500;
  var MAX_DEPTH = 8;

  /* Mirrored from includes/builder/tree_validator.php, character for character. */
  var V_UNKNOWN_TYPE = 'نوع بلاک نامعتبر است';
  var V_OFF_ROOT = 'بلاک ساختاری فقط میتواند در ریشه قرار بگیرد';
  var V_COUNT = 'تعداد فرزندهای بلاک مجاز نیست';
  var V_TOO_MANY = 'تعداد بلاکها بیش از حد مجاز است';
  var V_TOO_DEEP = 'عمق تودرتویی بیش از حد مجاز است';
  var V_BAD_PLACE = 'این نوع بلاک در این محل مجاز نیست';
  var V_BAD_ROOT = 'ساختار ریشه باید دقیقاً شامل هدر، بدنه و فوتر باشد';

  function registry() {
    return (typeof window !== 'undefined' && window.BuilderRegistry) ? window.BuilderRegistry : null;
  }

  function isStructuralRootName(type) {
    return type === TYPE_HEADER || type === TYPE_BODY || type === TYPE_FOOTER;
  }

  function isStructuralName(type) {
    return isStructuralRootName(type) || type === TYPE_COLUMN || type === TYPE_CONTAINER;
  }

  /* Effective parent constraints, preferring the registry. Part 0 shipped the
   * five structural types server-side only, so when the client registry does
   * not know them this falls back to their specified semantics (§0.3); truly
   * unknown types stay restricted. */
  function effParent(parentNode) {
    var reg = registry();
    var def = reg ? reg.getBlockType(parentNode.type) : null;
    if (def) {
      return { allows: def.allowsChildren, childTypes: def.allowedChildTypes, min: def.minChildren, max: def.maxChildren };
    }
    if (isStructuralRootName(parentNode.type) || parentNode.type === TYPE_COLUMN) {
      return { allows: true, childTypes: [], min: 0, max: 0 };
    }
    if (parentNode.type === TYPE_CONTAINER) {
      return { allows: true, childTypes: [TYPE_COLUMN], min: 2, max: 4 };
    }
    return null;
  }

  function effParentTypes(type) {
    var reg = registry();
    var def = reg ? reg.getBlockType(type) : null;
    if (def) {
      return def.allowedParentTypes;
    }
    if (type === TYPE_COLUMN) {
      return [TYPE_CONTAINER];
    }
    return [];
  }

  function typeKnown(type) {
    var reg = registry();
    return !!(reg && reg.getBlockType(type)) || isStructuralName(type);
  }

  function locate(tree, uid) {
    var found = null;
    function visit(nodes, parent, depth) {
      for (var i = 0; i < nodes.length; i++) {
        if (found) {
          return;
        }
        if (nodes[i].uid === uid) {
          found = { node: nodes[i], parent: parent, index: i, depth: depth };
          return;
        }
        if (nodes[i].children && nodes[i].children.length) {
          visit(nodes[i].children, nodes[i], depth + 1);
        }
      }
    }
    if (tree) {
      visit(tree, null, ROOT_DEPTH);
    }
    return found;
  }

  function countNodes(tree) {
    var count = 0;
    (function walk(nodes) {
      if (!Array.isArray(nodes)) {
        return;
      }
      for (var i = 0; i < nodes.length; i++) {
        count++;
        walk(nodes[i] ? nodes[i].children : null);
      }
    })(tree);
    return count;
  }

  function subtreeSize(node) {
    var size = 1;
    for (var i = 0; i < node.children.length; i++) {
      size += subtreeSize(node.children[i]);
    }
    return size;
  }

  function subtreeHeight(node) {
    var height = 0;
    for (var i = 0; i < node.children.length; i++) {
      var h = 1 + subtreeHeight(node.children[i]);
      if (h > height) {
        height = h;
      }
    }
    return height;
  }

  function deepestDepth(node, depth) {
    var deepest = depth;
    for (var i = 0; i < node.children.length; i++) {
      var d = deepestDepth(node.children[i], depth + 1);
      if (d > deepest) {
        deepest = d;
      }
    }
    return deepest;
  }

  /* Returns null when the insert is legal, else the validator's message. */
  function checkInsert(tree, type, parentNode, parentDepth, addSize, addHeight) {
    if (!typeKnown(type)) {
      return V_UNKNOWN_TYPE;
    }
    if (!parentNode) {
      return V_BAD_ROOT;
    }
    if (isStructuralRootName(type)) {
      return V_OFF_ROOT;
    }
    var pe = effParent(parentNode);
    if (!pe || !pe.allows) {
      return V_COUNT;
    }
    var newCount = parentNode.children.length + 1;
    if (newCount < pe.min || (pe.max !== 0 && newCount > pe.max)) {
      return V_COUNT;
    }
    if (pe.childTypes.length > 0 && pe.childTypes.indexOf(type) === -1) {
      return V_BAD_PLACE;
    }
    var allowedParents = effParentTypes(type);
    if (allowedParents.length > 0 && allowedParents.indexOf(parentNode.type) === -1) {
      return V_BAD_PLACE;
    }
    if (countNodes(tree) + addSize > MAX_BLOCKS) {
      return V_TOO_MANY;
    }
    if (parentDepth + 1 + addHeight > MAX_DEPTH) {
      return V_TOO_DEEP;
    }
    return null;
  }

  window.BuilderTree = {
    checkInsert: checkInsert,
    countNodes: countNodes,
    deepestDepth: deepestDepth,
    effParent: effParent,
    effParentTypes: effParentTypes,
    isStructuralName: isStructuralName,
    isStructuralRootName: isStructuralRootName,
    limits: { maxBlocks: MAX_BLOCKS, maxDepth: MAX_DEPTH },
    locate: locate,
    subtreeHeight: subtreeHeight,
    subtreeSize: subtreeSize,
    typeKnown: typeKnown
  };
})();
