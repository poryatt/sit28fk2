/* Visual page builder — client mirror of the five structural types (Part 0).
 * Labels/icons/defaultData only: no render(), no renderSettingsPanel().
 * This lights up Part 2's registry labels without editing any shipped file.
 * Must load first: the 00- prefix sorts before every content type file.
 */
(function () {
  'use strict';

  /* Persian UI strings (Part 9, P3.3): kept together at the top so no
   * Persian literal sits in the middle of logic. Values are unchanged. */
  var STR_1 = "هدر";
  var STR_2 = "بدنه";
  var STR_3 = "فوتر";
  var STR_4 = "ستونبندی";
  var STR_5 = "ستون";


  var defs = [
    { type: 'header', label: STR_1, icon: 'ti-layout-navbar' },
    { type: 'body', label: STR_2, icon: 'ti-file-text' },
{ type: 'footer', label: STR_3, icon: 'ti-layout-bottombar' },
{ type: 'column_container', label: STR_4, icon: 'ti-layout-columns' },
    { type: 'column', label: STR_5, icon: 'ti-layout-sidebar' }
  ];

  var containers = { header: true, body: true, footer: true, column: true, column_container: true };

  for (var i = 0; i < defs.length; i++) {
    (function (d) {
      var isContainer = !!containers[d.type];
      var minC = 0;
      var maxC = 0;
      var childTypes = [];
      var parentTypes = [];
      if (d.type === 'column_container') { minC = 2; maxC = 4; childTypes = ['column']; }
      if (d.type === 'column') { parentTypes = ['column_container']; }
      try {
        if (window.BuilderRegistry && !window.BuilderRegistry.hasBlockType(d.type)) {
          window.BuilderRegistry.registerBlockType({
            type: d.type,
            label: d.label,
            icon: d.icon,
            category: 'structure',
            allowsChildren: isContainer,
            minChildren: minC,
            maxChildren: maxC,
            supportsTextFormatting: false,
            allowedChildTypes: childTypes,
            allowedParentTypes: parentTypes,
            defaultData: function () { return {}; }
          });
        }
      } catch (e) { /* a later file already registered it — keep the first one */ }
    })(defs[i]);
  }
})();
