/**
 * Frontend JavaScript
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

(function ($) {
  "use strict";

  /**
   * WC Qualiopi Formation Frontend
   */
  const WCQF = {
    /**
     * Initialize
     */
    init: function () {
      this.bindEvents();
    },

    /**
     * Bind events
     */
    bindEvents: function () {
      // Add event listeners here
      console.log("WCQF Frontend initialized");
    },
  };

  // Initialize on DOM ready
  $(document).ready(function () {
    WCQF.init();
  });
})(jQuery);

