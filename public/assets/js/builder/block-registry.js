/* Visual page builder — client-side block type registry (Part 0).
 * Pure data and pure functions: no DOM access, no UI, no library.
 * Exposes exactly one global: window.BuilderRegistry.
 */
(function () {
  'use strict';

  var TYPE_RE = /^[a-z][a-z0-9_]{1,49}$/;
  var types = {};
  var order = [];

  function isStringArray(value) {
    if (!Array.isArray(value)) {
      return false;
    }
    for (var i = 0; i < value.length; i++) {
      if (typeof value[i] !== 'string' || !TYPE_RE.test(value[i])) {
        return false;
      }
    }
    return true;
  }

  function checkDefinition(definition) {
    if (definition === null || typeof definition !== 'object' || Array.isArray(definition)) {
      throw new Error('BuilderRegistry: definition must be an object');
    }
    if (typeof definition.type !== 'string' || !TYPE_RE.test(definition.type)) {
      throw new Error('BuilderRegistry: invalid type');
    }
    if (typeof definition.label !== 'string') {
      throw new Error('BuilderRegistry: label must be a string');
    }
    if (typeof definition.icon !== 'string') {
      throw new Error('BuilderRegistry: icon must be a string');
    }
    if (definition.category !== 'structure' && definition.category !== 'content') {
      throw new Error('BuilderRegistry: category must be structure or content');
    }
    if (typeof definition.allowsChildren !== 'boolean') {
      throw new Error('BuilderRegistry: allowsChildren must be a boolean');
    }
    if (typeof definition.minChildren !== 'number' || definition.minChildren < 0 || Math.floor(definition.minChildren) !== definition.minChildren) {
      throw new Error('BuilderRegistry: minChildren must be an integer >= 0');
    }
    if (typeof definition.maxChildren !== 'number' || definition.maxChildren < 0 || Math.floor(definition.maxChildren) !== definition.maxChildren) {
      throw new Error('BuilderRegistry: maxChildren must be an integer >= 0');
    }
    if (definition.allowsChildren === false && (definition.minChildren !== 0 || definition.maxChildren !== 0)) {
      throw new Error('BuilderRegistry: a type with allowsChildren=false must have minChildren=0 and maxChildren=0');
    }
    if (definition.maxChildren !== 0 && definition.maxChildren < definition.minChildren) {
      throw new Error('BuilderRegistry: maxChildren must not be smaller than minChildren');
    }
    if (typeof definition.supportsTextFormatting !== 'boolean') {
      throw new Error('BuilderRegistry: supportsTextFormatting must be a boolean');
    }
    if (!isStringArray(definition.allowedChildTypes)) {
      throw new Error('BuilderRegistry: allowedChildTypes must be an array of type strings');
    }
    if (!isStringArray(definition.allowedParentTypes)) {
      throw new Error('BuilderRegistry: allowedParentTypes must be an array of type strings');
    }
    if (definition.allowsChildren === false && definition.allowedChildTypes.length > 0) {
      throw new Error('BuilderRegistry: a type with allowsChildren=false must have an empty allowedChildTypes');
    }
    if (typeof definition.defaultData !== 'function') {
      throw new Error('BuilderRegistry: defaultData must be a function');
    }
    if (definition.render !== undefined && typeof definition.render !== 'function') {
      throw new Error('BuilderRegistry: render must be a function when present');
    }
    if (definition.renderSettingsPanel !== undefined && typeof definition.renderSettingsPanel !== 'function') {
      throw new Error('BuilderRegistry: renderSettingsPanel must be a function when present');
    }
  }

  window.BuilderRegistry = {
    registerBlockType: function (definition) {
      checkDefinition(definition);
      if (types[definition.type] !== undefined) {
        throw new Error('BuilderRegistry: duplicate block type: ' + definition.type);
      }
      types[definition.type] = definition;
      order.push(definition.type);
    },
    getBlockType: function (type) {
      return types[type] !== undefined ? types[type] : null;
    },
    getAllBlockTypes: function () {
      return order.map(function (type) {
        return types[type];
      });
    },
    getInsertableBlockTypes: function () {
      return order
        .map(function (type) {
          return types[type];
        })
        .filter(function (definition) {
          return definition.category === 'content';
        });
    },
    hasBlockType: function (type) {
      return types[type] !== undefined;
    }
  };
})();
