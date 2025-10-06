/**
 * Admin JavaScript
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

(function ($) {
  "use strict";

  /**
   * WC Qualiopi Formation Admin
   */
  const WCQFAdmin = {
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
      console.log("WCQF Admin initialized");
    },
  };

  // Initialize on DOM ready
  $(document).ready(function () {
    WCQFAdmin.init();
  });
})(jQuery);

