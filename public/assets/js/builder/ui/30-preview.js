/* Visual page builder — preview hook (Part 7).
 * Part 1's shell calls window.BuilderPreview(canvas) after saving when the
 * Preview button is pressed. This file defines it: opens the rendered page
 * (public/api/builder/render.php) in a new tab. One global, no edits elsewhere.
 */
(function () {
  'use strict';

  /* Persian UI strings (Part 9, P3.3): kept together at the top so no
   * Persian literal sits in the middle of logic. Values are unchanged. */
  var STR_1 = "پیش‌نمایش";
  var STR_2 = "ابتدا بوم را ذخیره کنید.";


  window.BuilderPreview = function (canvas) {
    if (!canvas || canvas.id === null || canvas.id === undefined) {
      if (typeof Swal !== 'undefined') {
        Swal.fire({ title: STR_1, text: STR_2, icon: 'info' });
      }
      return;
    }
    window.open('/api/builder/render.php?canvas_id=' + encodeURIComponent(canvas.id), '_blank', 'noopener');
  };
})();
